<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Batch Management API
 *
 * GET  ?options=1       Form options
 * GET  ?ref=<ref>       Single Batch for View/Edit
 * GET  ?datatable=1     Batch DataTable
 * POST action=save      Create / Update multiple Batch rows in one transaction
 * POST action=delete    Delete one Batch when unused
 */

function batch_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Batch Management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id
           AND b.status=1
           AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function batch_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $requiredTables = [
        'college_batches',
        'college_courses',
        'college_admissions',
        'college_attendance_sessions',
    ];

    foreach ($requiredTables as $table) {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=:table_name'
        );
        $stmt->execute([':table_name' => $table]);

        if ((int)$stmt->fetchColumn() !== 1) {
            json_error('Batch Management database setup is incomplete.', 500, [
                'schema' => 'Missing table: ' . $table
            ]);
        }
    }

    $requiredColumns = [
        'id','branch_id','course_id','batch_code','batch_name','start_date','end_date',
        'start_time','end_time','working_days','faculty_user_id','maximum_students','notes',
        'status','created_by','created_at','updated_at'
    ];

    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge(['college_batches'], $requiredColumns));

    $found = array_map('strtolower', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
    $missing = array_values(array_diff($requiredColumns, $found));

    if ($missing !== []) {
        json_error('Batch Management database structure is incomplete.', 500, [
            'schema' => 'Missing college_batches columns: ' . implode(', ', $missing) . '. Run sql/add-batch-notes.sql if notes is missing.'
        ]);
    }

    $courseColumns = [
        'id',
        'branch_id',
        'course_code',
        'course_name',
        'duration_value',
        'duration_unit',
        'maximum_students',
        'status',
    ];

    $coursePlaceholders =
        implode(',', array_fill(0, count($courseColumns), '?'));

    $courseStmt = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $coursePlaceholders . ')'
    );

    $courseStmt->execute(
        array_merge(['college_courses'], $courseColumns)
    );

    $courseFound = array_map(
        'strtolower',
        array_column(
            $courseStmt->fetchAll(PDO::FETCH_ASSOC),
            'COLUMN_NAME'
        )
    );

    $courseMissing =
        array_values(array_diff($courseColumns, $courseFound));

    if ($courseMissing !== []) {
        json_error(
            'Batch Management database structure is incomplete.',
            500,
            [
                'schema' =>
                    'Missing college_courses columns: ' .
                    implode(', ', $courseMissing)
            ]
        );
    }

    $checked = true;
}

function batch_ref_to_id($value, string $field = 'ref'): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Batch reference is required.', 422, [$field => 'Batch reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'college_batch');
    } catch (Throwable $e) {
        json_error('Invalid Batch reference.', 422, [$field => 'Invalid Batch reference.']);
    }

    return 0;
}

function batch_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : mb_substr($value, 0, $maxLength);
}

function batch_valid_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) ||
        $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $value;
}

function batch_positive_int_or_null($value)
{
    if ($value === '' || $value === null) return null;
    $text = trim((string)$value);
    return preg_match('/^[1-9][0-9]*$/', $text) ? (int)$text : false;
}

function batch_normalize_working_days($value)
{
    if (is_string($value)) {
        $value = $value === '' ? [] : explode(',', $value);
    }

    if (!is_array($value)) return false;

    $days = [];
    foreach ($value as $item) {
        $text = trim((string)$item);
        if (!preg_match('/^[1-7]$/', $text)) return false;
        $days[(int)$text] = true;
    }

    $days = array_keys($days);
    sort($days, SORT_NUMERIC);
    return $days;
}

function batch_working_days_label(string $csv): string
{
    $labels = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    $parts = [];
    foreach (explode(',', $csv) as $item) {
        $day = (int)trim($item);
        if (isset($labels[$day])) $parts[] = $labels[$day];
    }

    return $parts ? implode(', ', $parts) : '-';
}

function batch_course_options(int $branchId, array $includeIds = [], bool $activeOnly = true): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), fn($id) => $id > 0)));

    $sql = 'SELECT
                id,
                course_code,
                course_name,
                duration_value,
                duration_unit,
                maximum_students,
                status
            FROM college_courses
            WHERE branch_id=:branch_id';
    $params = [':branch_id' => $branchId];

    if ($activeOnly) {
        if ($includeIds) {
            $placeholders = [];
            foreach ($includeIds as $index => $id) {
                $key = ':include_' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $sql .= ' AND (status=1 OR id IN (' . implode(',', $placeholders) . '))';
        } else {
            $sql .= ' AND status=1';
        }
    }

    $sql .= ' ORDER BY course_name,course_code';
    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'course_code' => (string)$row['course_code'],
            'course_name' => (string)$row['course_name'],
            'duration_value' => (int)$row['duration_value'],
            'duration_unit' => (string)$row['duration_unit'],
            'maximum_students' => $row['maximum_students'] === null ? null : (int)$row['maximum_students'],
            'status' => (int)$row['status'],
        ];
    }

    return $rows;
}

function batch_course_record(int $branchId, int $courseId): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            course_code,
            course_name,
            duration_value,
            duration_unit,
            maximum_students,
            status
         FROM college_courses
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $courseId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Batch validation failed.', 422, [
            'course_id' => 'Selected Course is not available in this branch.'
        ]);
    }

    return [
        'id' => (int)$row['id'],
        'course_code' => (string)$row['course_code'],
        'course_name' => (string)$row['course_name'],
        'duration_value' => (int)$row['duration_value'],
        'duration_unit' => (string)$row['duration_unit'],
        'maximum_students' => $row['maximum_students'] === null ? null : (int)$row['maximum_students'],
        'status' => (int)$row['status'],
    ];
}

function batch_calculate_end_date(
    string $startDate,
    int $durationValue,
    string $durationUnit
): ?string {
    if ($durationValue < 1) {
        return null;
    }

    $start = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $startDate
    );

    if (!$start || $start->format('Y-m-d') !== $startDate) {
        return null;
    }

    $unit = strtolower(trim($durationUnit));

    if (in_array($unit, ['day', 'days'], true)) {
        return $start
            ->modify('+' . ($durationValue - 1) . ' days')
            ->format('Y-m-d');
    }

    if (in_array($unit, ['week', 'weeks'], true)) {
        $days = ($durationValue * 7) - 1;

        return $start
            ->modify('+' . $days . ' days')
            ->format('Y-m-d');
    }

    if (in_array($unit, ['month', 'months'], true)) {
        $year = (int)$start->format('Y');
        $month = (int)$start->format('n');
        $day = (int)$start->format('j');

        $totalMonths =
            ($year * 12) +
            ($month - 1) +
            $durationValue;

        $targetYear =
            intdiv($totalMonths, 12);

        $targetMonth =
            ($totalMonths % 12) + 1;

        $monthStart =
            new DateTimeImmutable(
                sprintf(
                    '%04d-%02d-01',
                    $targetYear,
                    $targetMonth
                )
            );

        $daysInTargetMonth =
            (int)$monthStart->format('t');

        $targetDay =
            min($day, $daysInTargetMonth);

        $anniversary =
            new DateTimeImmutable(
                sprintf(
                    '%04d-%02d-%02d',
                    $targetYear,
                    $targetMonth,
                    $targetDay
                )
            );

        return $anniversary
            ->modify('-1 day')
            ->format('Y-m-d');
    }

    if (in_array($unit, ['year', 'years'], true)) {
        $targetYear =
            ((int)$start->format('Y')) +
            $durationValue;

        $month =
            (int)$start->format('n');

        $day =
            (int)$start->format('j');

        $monthStart =
            new DateTimeImmutable(
                sprintf(
                    '%04d-%02d-01',
                    $targetYear,
                    $month
                )
            );

        $daysInTargetMonth =
            (int)$monthStart->format('t');

        $targetDay =
            min($day, $daysInTargetMonth);

        $anniversary =
            new DateTimeImmutable(
                sprintf(
                    '%04d-%02d-%02d',
                    $targetYear,
                    $month,
                    $targetDay
                )
            );

        return $anniversary
            ->modify('-1 day')
            ->format('Y-m-d');
    }

    return null;
}

function batch_usage_counts(
    PDO $pdo,
    int $batchId
): array {
    $admissionStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS admission_count,
            SUM(
                CASE
                    WHEN status=1
                     AND admission_state=\'active\'
                    THEN 1
                    ELSE 0
                END
            ) AS active_admission_count
         FROM college_admissions
         WHERE batch_id=:batch_id'
    );

    $admissionStmt->execute([
        ':batch_id' => $batchId
    ]);

    $admissions =
        $admissionStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $attendanceStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM college_attendance_sessions
         WHERE batch_id=:batch_id'
    );

    $attendanceStmt->execute([
        ':batch_id' => $batchId
    ]);

    return [
        'admission_count' =>
            (int)($admissions['admission_count'] ?? 0),
        'active_admission_count' =>
            (int)($admissions['active_admission_count'] ?? 0),
        'attendance_session_count' =>
            (int)$attendanceStmt->fetchColumn(),
    ];
}

function batch_duplicate_exists(
    PDO $pdo,
    int $branchId,
    int $courseId,
    string $batchName,
    string $startDate,
    string $endDate,
    int $excludeId = 0
): bool {
    $sql =
        'SELECT id
         FROM college_batches
         WHERE branch_id=:branch_id
           AND course_id=:course_id
           AND batch_name=:batch_name
           AND start_date=:start_date
           AND end_date=:end_date';

    $params = [
        ':branch_id' => $branchId,
        ':course_id' => $courseId,
        ':batch_name' => $batchName,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetchColumn();
}

function batch_next_number(PDO $pdo, int $branchId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(batch_code,4) AS UNSIGNED)),0)
         FROM college_batches
         WHERE branch_id=:branch_id
           AND batch_code REGEXP '^BAT[0-9]+$'"
    );
    $stmt->execute([':branch_id' => $branchId]);
    return ((int)$stmt->fetchColumn()) + 1;
}

function batch_code_from_number(int $number): string
{
    return 'BAT' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function batch_record(array $ctx, int $id): array
{
    $stmt = db()->prepare(
        'SELECT
            b.id,b.branch_id,b.course_id,b.batch_code,b.batch_name,b.start_date,b.end_date,
            b.working_days,b.maximum_students,b.notes,b.status,b.created_by,b.created_at,b.updated_at,
            c.course_code,
            c.course_name,
            c.duration_value,
            c.duration_unit,
            u.name AS created_by_name
         FROM college_batches b
         INNER JOIN college_courses c ON c.id=b.course_id AND c.branch_id=b.branch_id
         LEFT JOIN users u ON u.id=b.created_by
         WHERE b.id=:id AND b.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => (int)$ctx['branch_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_error('Batch was not found.', 404);

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['course_id'] = (int)$row['course_id'];
    $row['maximum_students'] = $row['maximum_students'] === null ? null : (int)$row['maximum_students'];
    $row['status'] = (int)$row['status'];
    $row['duration_value'] = (int)$row['duration_value'];
    $row['duration_unit'] = (string)$row['duration_unit'];
    $row['working_days'] = batch_normalize_working_days((string)($row['working_days'] ?? '')) ?: [];

    $usage =
        batch_usage_counts(
            db(),
            (int)$row['id']
        );

    $row['admission_count'] =
        (int)$usage['admission_count'];
    $row['active_admission_count'] =
        (int)$usage['active_admission_count'];
    $row['attendance_session_count'] =
        (int)$usage['attendance_session_count'];

    $row['ref'] = encryptReference('college_batch', (int)$row['id']);
    return $row;
}

function batch_dependencies(PDO $pdo, int $batchId): array
{
    $dependencies = [];

    $checks = [
        'Admissions' => ['college_admissions','batch_id'],
        'Attendance Sessions' => ['college_attendance_sessions','batch_id'],
    ];

    foreach ($checks as $label => [$table,$column]) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '`=:batch_id');
        $stmt->execute([':batch_id' => $batchId]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) $dependencies[$label] = $count;
    }

    return $dependencies;
}

function batch_validate_row(
    array $row,
    int $index,
    int $branchId,
    ?array $old
): array {
    $prefix =
        'batches[' . $index . ']';

    $errors = [];

    $courseIdText =
        trim((string)($row['course_id'] ?? ''));

    $courseId =
        preg_match(
            '/^[1-9][0-9]*$/',
            $courseIdText
        )
            ? (int)$courseIdText
            : 0;

    $course = null;

    if ($courseId < 1) {
        $errors[$prefix . '[course_id]'] =
            'Select a Course.';
    } else {
        $course =
            batch_course_record(
                $branchId,
                $courseId
            );

        if ((int)$course['status'] !== 1) {
            $oldCourseId =
                $old
                    ? (int)$old['course_id']
                    : 0;

            if ($oldCourseId !== $courseId) {
                $errors[$prefix . '[course_id]'] =
                    'Select an active Course.';
            }
        }

        if (
            $old &&
            (int)($old['admission_count'] ?? 0) > 0 &&
            (int)$old['course_id'] !== $courseId
        ) {
            $errors[$prefix . '[course_id]'] =
                'Course cannot be changed because Admissions already exist for this Batch.';
        }
    }

    $batchName =
        trim((string)($row['batch_name'] ?? ''));

    if ($batchName === '') {
        $errors[$prefix . '[batch_name]'] =
            'Batch Name is required.';
    } elseif (mb_strlen($batchName) > 150) {
        $errors[$prefix . '[batch_name]'] =
            'Batch Name cannot exceed 150 characters.';
    }

    $startDate =
        batch_valid_date(
            $row['start_date'] ?? ''
        );

    if ($startDate === null) {
        $errors[$prefix . '[start_date]'] =
            'Enter a valid Start Date.';
    }

    if (
        $old &&
        (int)($old['attendance_session_count'] ?? 0) > 0 &&
        $startDate !== null &&
        $startDate !== (string)$old['start_date']
    ) {
        $errors[$prefix . '[start_date]'] =
            'Start Date cannot be changed because Attendance Sessions already exist for this Batch.';
    }

    $endDate = null;

    if (
        $old &&
        (int)($old['attendance_session_count'] ?? 0) > 0
    ) {
        $endDate =
            (string)$old['end_date'];
    } elseif (
        $startDate !== null &&
        $course
    ) {
        $endDate =
            batch_calculate_end_date(
                $startDate,
                (int)$course['duration_value'],
                (string)$course['duration_unit']
            );

        if ($endDate === null) {
            $errors[$prefix . '[end_date]'] =
                'Unable to calculate End Date from the selected Course duration.';
        }
    }

    $workingDays =
        batch_normalize_working_days(
            $row['working_days'] ?? []
        );

    if (
        $workingDays === false ||
        $workingDays === []
    ) {
        $errors[$prefix . '[working_days]'] =
            'Select at least one Working Day.';

        $workingDays = [];
    }

    $maximumStudents =
        batch_positive_int_or_null(
            $row['maximum_students'] ?? null
        );

    if ($maximumStudents === false) {
        $errors[$prefix . '[maximum_students]'] =
            'Maximum Students must be a whole number greater than zero.';

        $maximumStudents = null;
    }

    if (
        $maximumStudents === null &&
        $course &&
        $course['maximum_students'] !== null
    ) {
        $maximumStudents =
            (int)$course['maximum_students'];
    }

    $activeAdmissionCount =
        $old
            ? (int)($old['active_admission_count'] ?? 0)
            : 0;

    if (
        $maximumStudents !== null &&
        $activeAdmissionCount > 0 &&
        $maximumStudents < $activeAdmissionCount
    ) {
        $errors[$prefix . '[maximum_students]'] =
            'Maximum Students cannot be less than current active admissions (' .
            $activeAdmissionCount .
            ').';
    }

    $status =
        (int)($row['status'] ?? 1);

    if (!in_array($status, [0,1], true)) {
        $errors[$prefix . '[status]'] =
            'Select a valid Status.';
    }

    $notes =
        trim((string)($row['notes'] ?? ''));

    if (mb_strlen($notes) > 255) {
        $errors[$prefix . '[notes]'] =
            'Notes cannot exceed 255 characters.';
    }

    if ($errors !== []) {
        return [
            'errors' => $errors
        ];
    }

    return [
        'errors' => [],
        'data' => [
            'ref' =>
                trim((string)($row['ref'] ?? '')),
            'course_id' =>
                $courseId,
            'batch_name' =>
                $batchName,
            'start_date' =>
                $startDate,
            'end_date' =>
                $endDate,
            'working_days' =>
                implode(',', $workingDays),
            'maximum_students' =>
                $maximumStudents,
            'status' =>
                $status,
            'notes' =>
                $notes === ''
                    ? null
                    : $notes,
        ]
    ];
}

$method = request_method();
batch_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('batch-form.php', ACTION_VIEW);
    $ctx = batch_tenant_context($access['user']);

    $nextBatchNumber = batch_next_number(db(), (int)$ctx['branch_id']);

    json_success('Batch form options loaded.', [
        'allowed_actions' => $access['actions'],
        'courses' => batch_course_options((int)$ctx['branch_id'], [], true),
        'next_batch_number' => $nextBatchNumber,
        'next_batch_code' => batch_code_from_number($nextBatchNumber),
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('batch-form.php', ACTION_VIEW);
    $ctx = batch_tenant_context($access['user']);
    $id = batch_ref_to_id($_GET['ref'] ?? '');
    $batch = batch_record($ctx, $id);

    $nextBatchNumber = batch_next_number(db(), (int)$ctx['branch_id']);

    json_success('Batch loaded.', [
        'batch' => $batch,
        'allowed_actions' => $access['actions'],
        'courses' => batch_course_options((int)$ctx['branch_id'], [(int)$batch['course_id']], true),
        'next_batch_number' => $nextBatchNumber,
        'next_batch_code' => batch_code_from_number($nextBatchNumber),
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('batch-list.php', ACTION_VIEW);
    $ctx = batch_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $formMenu = menu_by_path('batch-form.php');
    $formActions = $formMenu ? effective_actions_for_menu($access['user'], $formMenu) : [];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0 ? 100000 : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $courseId = isset($_GET['course_id']) && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : 0;
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : -1;

    $where = ['b.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(b.batch_code LIKE :s_batch_code OR b.batch_name LIKE :s_batch_name OR b.notes LIKE :s_notes OR c.course_code LIKE :s_course_code OR c.course_name LIKE :s_course_name)';
        $params += [
            ':s_batch_code' => $like,
            ':s_batch_name' => $like,
            ':s_notes' => $like,
            ':s_course_code' => $like,
            ':s_course_name' => $like,
        ];
    }

    if ($courseId > 0) {
        $where[] = 'b.course_id=:course_id';
        $params[':course_id'] = $courseId;
    }

    if (in_array($status, [0,1], true)) {
        $where[] = 'b.status=:status';
        $params[':status'] = $status;
    }

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_batches WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_batches b
         INNER JOIN college_courses c ON c.id=b.course_id AND c.branch_id=b.branch_id
         WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN b.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN b.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(SUM(COALESCE(b.maximum_students,0)),0) AS total_capacity
         FROM college_batches b
         INNER JOIN college_courses c
                 ON c.id=b.course_id
                AND c.branch_id=b.branch_id
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id',':course_id',':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'b.batch_code','c.course_name','b.batch_name','b.start_date','b.end_date',
        'b.working_days','b.maximum_students','b.status','b.created_at'
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 3);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = $orderColumns[$orderIndex] ?? 'b.start_date';

    $sql =
        'SELECT
            b.id,b.course_id,b.batch_code,b.batch_name,b.start_date,b.end_date,b.working_days,
            b.maximum_students,b.notes,b.status,b.created_at,c.course_code,c.course_name
         FROM college_batches b
         INNER JOIN college_courses c ON c.id=b.course_id AND c.branch_id=b.branch_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $orderBy . ' ' . $orderDir . ',b.id DESC
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $isInt = in_array($key, [':branch_id',':course_id',':status'], true);
        $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['course_id'] = (int)$row['course_id'];
        $row['maximum_students'] = $row['maximum_students'] === null ? null : (int)$row['maximum_students'];
        $row['status'] = (int)$row['status'];
        $row['course_label'] = $row['course_code'] . ' - ' . $row['course_name'];
        $row['working_days_label'] = batch_working_days_label((string)($row['working_days'] ?? ''));
        $row['status_label'] = $row['status'] === 1 ? 'Active' : 'Inactive';
        $row['ref'] = encryptReference('college_batch', (int)$row['id']);
        $row['view_url'] = 'batch-form.php?ref=' . rawurlencode($row['ref']) . '&view=1';
        $row['edit_url'] = 'batch-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Batches loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_count' => (int)($summary['total_count'] ?? 0),
            'active_count' => (int)($summary['active_count'] ?? 0),
            'inactive_count' => (int)($summary['inactive_count'] ?? 0),
            'total_capacity' => (int)round((float)($summary['total_capacity'] ?? 0)),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
        'courses' => batch_course_options($branchId, [], false),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'delete') {
        $access = require_permission('batch-form.php', 4);
        $ctx = batch_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = batch_ref_to_id($data['ref'] ?? '');
        $pdo = db();

        $old = batch_record($ctx, $id);
        $dependencies = batch_dependencies($pdo, $id);

        if ($dependencies !== []) {
            $parts = [];
            foreach ($dependencies as $label => $count) {
                $parts[] = $label . ': ' . $count;
            }
            json_error(
                'This Batch is already in use (' . implode(', ', $parts) . '). Make it Inactive instead of deleting it.',
                409
            );
        }

        $stmt = $pdo->prepare('DELETE FROM college_batches WHERE id=:id AND branch_id=:branch_id');
        $stmt->execute([':id' => $id, ':branch_id' => $branchId]);

        audit_log($userId, 4, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]);

        json_success('Batch deleted successfully.');
    }

    if ($action !== 'save') {
        json_error('Unsupported Batch action.', 404);
    }

    $rowsRaw = isset($data['batches']) && is_array($data['batches']) ? array_values($data['batches']) : [];
    $removedRefs = isset($data['removed_batches']) && is_array($data['removed_batches'])
        ? array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $data['removed_batches']), static fn(string $v): bool => $v !== ''))
        : [];

    if ($rowsRaw === [] && $removedRefs === []) {
        json_error('Batch validation failed.', 422, ['batches' => 'Add at least one Batch row.']);
    }

    $viewAccess = require_permission('batch-form.php', ACTION_VIEW);
    $ctx = batch_tenant_context($viewAccess['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$viewAccess['user']['id'];

    $hasNew = false;
    $hasExisting = false;
    foreach ($rowsRaw as $row) {
        $ref = is_array($row) ? trim((string)($row['ref'] ?? '')) : '';
        if ($ref === '') $hasNew = true; else $hasExisting = true;
    }

    if ($hasNew) require_permission('batch-form.php', ACTION_CREATE);
    if ($hasExisting) require_permission('batch-form.php', ACTION_UPDATE);
    if ($removedRefs !== []) require_permission('batch-form.php', 4);

    $errors = [];
    $cleanRows = [];
    $oldRows = [];

    foreach ($rowsRaw as $index => $row) {
        if (!is_array($row)) {
            $errors['batches[' . $index . '][course_id]'] = 'Invalid Batch row.';
            continue;
        }

        $old = null;
        $ref = trim((string)($row['ref'] ?? ''));
        if ($ref !== '') {
            try {
                $id = decryptReference($ref, 'college_batch');
                $old = batch_record($ctx, $id);
                $oldRows[$index] = $old;
            } catch (Throwable $e) {
                $errors['batches[' . $index . '][course_id]'] = 'Invalid Batch reference.';
                continue;
            }
        }

        $validated = batch_validate_row($row, $index, $branchId, $old);
        if ($validated['errors'] !== []) {
            $errors += $validated['errors'];
            continue;
        }

        $cleanRows[$index] = $validated['data'];
    }

    $seenExactRows = [];

    foreach ($cleanRows as $index => $row) {
        $exactKey =
            (string)$row['course_id'] .
            '|' .
            mb_strtolower((string)$row['batch_name']) .
            '|' .
            (string)$row['start_date'] .
            '|' .
            (string)$row['end_date'];

        if (isset($seenExactRows[$exactKey])) {
            $errors[
                'batches[' .
                $index .
                '][batch_name]'
            ] =
                'This exact Course Batch is already entered above.';
        } else {
            $seenExactRows[$exactKey] =
                $index;
        }
    }

    if ($errors !== []) {
        json_error('Batch validation failed.', 422, $errors);
    }

    $pdo = db();
    $created = [];
    $updated = [];
    $deleted = [];

    $pdo->beginTransaction();

    try {
        /*
         * Lock the branch row before generating Batch Codes.
         * This serializes BATxxxx generation for this branch and reduces
         * duplicate-code races when two users save batches at the same time.
         */
        $branchLock = $pdo->prepare(
            'SELECT id
             FROM branches
             WHERE id=:branch_id
             LIMIT 1
             FOR UPDATE'
        );
        $branchLock->execute([':branch_id' => $branchId]);

        if (!$branchLock->fetchColumn()) {
            throw new RuntimeException('Branch is not available.');
        }

        $nextNumber = batch_next_number($pdo, $branchId);

        foreach ($removedRefs as $removeIndex => $removeRef) {
            $id = batch_ref_to_id($removeRef, 'removed_batches[' . $removeIndex . ']');
            $old = batch_record($ctx, $id);
            $dependencies = batch_dependencies($pdo, $id);

            if ($dependencies !== []) {
                json_error(
                    'Batch "' . (string)$old['batch_code'] . '" is already in use. Make it Inactive instead of removing it.',
                    409
                );
            }

            $stmt = $pdo->prepare('DELETE FROM college_batches WHERE id=:id AND branch_id=:branch_id');
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
            $deleted[] = ['id' => $id, 'old' => $old];
        }

        foreach ($cleanRows as $index => $row) {
            $ref = $row['ref'];
            $existingId = 0;

            if ($ref !== '') {
                $existingId =
                    batch_ref_to_id(
                        $ref,
                        'batches[' .
                        $index .
                        '][ref]'
                    );
            }

            if (
                batch_duplicate_exists(
                    $pdo,
                    $branchId,
                    (int)$row['course_id'],
                    (string)$row['batch_name'],
                    (string)$row['start_date'],
                    (string)$row['end_date'],
                    $existingId
                )
            ) {
                throw new RuntimeException(
                    'DUPLICATE_BATCH_ROW:' .
                    $index
                );
            }

            if ($ref !== '') {
                $id = $existingId;
                $old = $oldRows[$index] ?? batch_record($ctx, $id);

                $stmt = $pdo->prepare(
                    'UPDATE college_batches
                     SET course_id=:course_id,
                         batch_name=:batch_name,
                         start_date=:start_date,
                         end_date=:end_date,
                         working_days=:working_days,
                         maximum_students=:maximum_students,
                         notes=:notes,
                         status=:status,
                         updated_at=NOW()
                     WHERE id=:id AND branch_id=:branch_id'
                );
                $stmt->execute([
                    ':course_id' => $row['course_id'],
                    ':batch_name' => $row['batch_name'],
                    ':start_date' => $row['start_date'],
                    ':end_date' => $row['end_date'],
                    ':working_days' => $row['working_days'],
                    ':maximum_students' => $row['maximum_students'],
                    ':notes' => $row['notes'],
                    ':status' => $row['status'],
                    ':id' => $id,
                    ':branch_id' => $branchId,
                ]);

                $updated[] = ['id' => $id, 'old' => $old, 'new' => $row];
                continue;
            }

            $batchCode = batch_code_from_number($nextNumber++);

            $stmt = $pdo->prepare(
                'INSERT INTO college_batches
                 (branch_id,course_id,batch_code,batch_name,start_date,end_date,working_days,maximum_students,notes,status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:course_id,:batch_code,:batch_name,:start_date,:end_date,:working_days,:maximum_students,:notes,:status,:created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':course_id' => $row['course_id'],
                ':batch_code' => $batchCode,
                ':batch_name' => $row['batch_name'],
                ':start_date' => $row['start_date'],
                ':end_date' => $row['end_date'],
                ':working_days' => $row['working_days'],
                ':maximum_students' => $row['maximum_students'],
                ':notes' => $row['notes'],
                ':status' => $row['status'],
                ':created_by' => $userId,
            ]);

            $created[] = [
                'id' => (int)$pdo->lastInsertId(),
                'new' => $row + ['batch_code' => $batchCode],
            ];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $e instanceof RuntimeException &&
            str_starts_with(
                $e->getMessage(),
                'DUPLICATE_BATCH_ROW:'
            )
        ) {
            $rowIndex =
                (int)substr(
                    $e->getMessage(),
                    strlen('DUPLICATE_BATCH_ROW:')
                );

            json_error(
                'Batch validation failed.',
                409,
                [
                    'batches[' .
                    $rowIndex .
                    '][batch_name]' =>
                        'This exact Course Batch already exists.'
                ]
            );
        }

        if (
            $e instanceof PDOException &&
            $e->getCode() === '23000'
        ) {
            json_error(
                'Batch could not be saved because a duplicate or linked value already exists. Please try again.',
                409
            );
        }

        throw $e;
    }

    $menuId = (int)$viewAccess['menu']['id'];

    foreach ($created as $item) {
        audit_log($userId, ACTION_CREATE, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => $menuId,
            'record_id' => (int)$item['id'],
            'new_data' => $item['new'],
        ]);
    }

    foreach ($updated as $item) {
        audit_log($userId, ACTION_UPDATE, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => $menuId,
            'record_id' => (int)$item['id'],
            'old_data' => $item['old'],
            'new_data' => $item['new'],
        ]);
    }

    foreach ($deleted as $item) {
        audit_log($userId, 4, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => $menuId,
            'record_id' => (int)$item['id'],
            'old_data' => $item['old'],
        ]);
    }

    json_success('Batches saved successfully.', [
        'created_count' => count($created),
        'updated_count' => count($updated),
        'deleted_count' => count($deleted),
    ]);
}

json_error('Unsupported request.', 404);
