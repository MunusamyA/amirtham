<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Course Master API
 *
 * Tables used: college_courses, college_course_subjects
 * Actions:
 *   GET  ?options=1        Form options / next course code
 *   GET  ?ref=<encrypted>  Single course for View/Edit
 *   GET  ?datatable=1      Course list DataTable
 *   POST action=save       Create / Update Course + Subjects
 *   POST action=delete     Delete when there are no dependent records
 */

function course_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Course Master is available only for tenant users.', 403);
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

function course_require_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=:table_name'
    );
    $stmt->execute([':table_name' => 'college_courses']);

    if ((int)$stmt->fetchColumn() !== 1) {
        json_error('Course Master database table is missing: college_courses.', 500);
    }

    $requiredColumns = [
        'id','branch_id','course_code','course_name','duration_value','duration_unit',
        'eligibility','description','maximum_students','status','created_by','created_at','updated_at'
    ];

    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $columnStmt = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $placeholders . ')'
    );
    $columnStmt->execute(array_merge(['college_courses'], $requiredColumns));
    $found = array_map('strtolower', array_column($columnStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
    $missing = array_values(array_diff($requiredColumns, $found));

    if ($missing !== []) {
        json_error(
            'Course Master database structure is incomplete.',
            500,
            ['schema' => 'Missing college_courses columns: ' . implode(', ', $missing)]
        );
    }

    $checked = true;
}


function course_require_subject_schema(): void
{
    $tableStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=:table_name'
    );
    $tableStmt->execute([':table_name' => 'college_course_subjects']);

    if ((int)$tableStmt->fetchColumn() !== 1) {
        json_error(
            'Course Subject database table is missing: college_course_subjects.',
            500,
            ['schema' => 'Run the integrated Course Subject SQL first.']
        );
    }

    $required = [
        'id','branch_id','course_id','subject_code','subject_name','subject_type',
        'maximum_marks','pass_marks','mandatory','sort_order','description',
        'status','created_by','created_at','updated_at'
    ];

    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge(['college_course_subjects'], $required));

    $found = array_map(
        'strtolower',
        array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')
    );
    $missing = array_values(array_diff($required, $found));

    if ($missing !== []) {
        json_error(
            'Course Subject database structure is incomplete.',
            500,
            ['schema' => 'Missing college_course_subjects columns: ' . implode(', ', $missing)]
        );
    }
}

function course_subject_type_label(int $type): string
{
    return [
        1 => 'Theory',
        2 => 'Practical',
        3 => 'Theory + Practical',
    ][$type] ?? 'Unknown';
}

function course_subject_decimal($value)
{
    if ($value === '' || $value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $number = round((float)$value, 2);
    return $number < 0 ? false : $number;
}

function course_subject_nonnegative_int($value)
{
    if ($value === '' || $value === null) {
        return 0;
    }

    $text = trim((string)$value);
    return preg_match('/^[0-9]+$/', $text) ? (int)$text : false;
}

function course_subject_generate_code(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT subject_code
         FROM college_course_subjects
         WHERE branch_id=:branch_id
           AND subject_code REGEXP '^SUB[0-9]+$'
         ORDER BY CAST(SUBSTRING(subject_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^SUB([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'SUB' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function course_subject_ref_to_id($value, string $field): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Course Subject reference is required.', 422, [
            $field => 'Course Subject reference is required.'
        ]);
    }

    try {
        return decryptReference(trim($value), 'college_course_subject');
    } catch (Throwable $e) {
        json_error('Invalid Course Subject reference.', 422, [
            $field => 'Invalid Course Subject reference.'
        ]);
    }

    return 0;
}

function course_subject_rows(array $ctx, int $courseId): array
{
    $stmt = db()->prepare(
        'SELECT s.id,s.course_id,s.subject_code,s.subject_name,s.subject_type,
                s.maximum_marks,s.pass_marks,s.mandatory,s.sort_order,
                s.description,s.status,s.created_at,s.updated_at
         FROM college_course_subjects s
         WHERE s.branch_id=:branch_id
           AND s.course_id=:course_id
         ORDER BY s.sort_order,s.subject_name,s.id'
    );
    $stmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
        ':course_id' => $courseId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference(
                'college_course_subject',
                (int)$row['id']
            ),
            'course_id' => (int)$row['course_id'],
            'subject_code' => (string)$row['subject_code'],
            'subject_name' => (string)$row['subject_name'],
            'subject_type' => (int)$row['subject_type'],
            'subject_type_label' => course_subject_type_label(
                (int)$row['subject_type']
            ),
            'maximum_marks' =>
                $row['maximum_marks'] === null
                    ? null
                    : (float)$row['maximum_marks'],
            'pass_marks' =>
                $row['pass_marks'] === null
                    ? null
                    : (float)$row['pass_marks'],
            'mandatory' => (int)$row['mandatory'],
            'sort_order' => (int)$row['sort_order'],
            'description' => $row['description'],
            'status' => (int)$row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    return $rows;
}

function course_subject_validate_rows(array $subjects): array
{
    $errors = [];
    $cleanRows = [];
    $seenNames = [];

    foreach (array_values($subjects) as $index => $row) {
        $prefix = 'subjects[' . $index . ']';

        if (!is_array($row)) {
            $errors[$prefix . '[subject_name]'] = 'Invalid Subject row.';
            continue;
        }

        $name = trim((string)($row['subject_name'] ?? ''));
        if ($name === '') {
            $errors[$prefix . '[subject_name]'] =
                'Subject / Module Name is required.';
        } elseif (mb_strlen($name) > 180) {
            $errors[$prefix . '[subject_name]'] =
                'Subject / Module Name cannot exceed 180 characters.';
        }

        $nameKey = mb_strtolower($name);
        if ($nameKey !== '') {
            if (isset($seenNames[$nameKey])) {
                $errors[$prefix . '[subject_name]'] =
                    'This Subject / Module is already entered above.';
            } else {
                $seenNames[$nameKey] = $index;
            }
        }

        $type = (int)($row['subject_type'] ?? 0);
        if (!in_array($type, [1,2,3], true)) {
            $errors[$prefix . '[subject_type]'] =
                'Select Theory, Practical or Theory + Practical.';
        }

        $maximum = course_subject_decimal($row['maximum_marks'] ?? null);
        $pass = course_subject_decimal($row['pass_marks'] ?? null);

        if ($maximum === false) {
            $errors[$prefix . '[maximum_marks]'] =
                'Enter valid Maximum Marks.';
        }

        if ($pass === false) {
            $errors[$prefix . '[pass_marks]'] =
                'Enter valid Pass Marks.';
        }

        if ($maximum !== false && $pass !== false) {
            if (($maximum === null) !== ($pass === null)) {
                if ($maximum === null) {
                    $errors[$prefix . '[maximum_marks]'] =
                        'Enter Maximum Marks when Pass Marks is entered.';
                }
                if ($pass === null) {
                    $errors[$prefix . '[pass_marks]'] =
                        'Enter Pass Marks when Maximum Marks is entered.';
                }
            }

            if ($maximum !== null && $maximum <= 0) {
                $errors[$prefix . '[maximum_marks]'] =
                    'Maximum Marks must be greater than zero.';
            }

            if ($maximum !== null && $pass !== null && $pass > $maximum) {
                $errors[$prefix . '[pass_marks]'] =
                    'Pass Marks cannot be greater than Maximum Marks.';
            }
        }

        $mandatory = (int)($row['mandatory'] ?? 1);
        if (!in_array($mandatory, [0,1], true)) {
            $errors[$prefix . '[mandatory]'] =
                'Select Yes or No for Mandatory.';
        }

        $sortOrder = course_subject_nonnegative_int($row['sort_order'] ?? 0);
        if ($sortOrder === false) {
            $errors[$prefix . '[sort_order]'] =
                'Sort Order must be a whole number and cannot be negative.';
        }

        $status = (int)($row['status'] ?? 1);
        if (!in_array($status, [0,1], true)) {
            $errors[$prefix . '[status]'] = 'Select a valid Status.';
        }

        $cleanRows[$index] = [
            'ref' => trim((string)($row['ref'] ?? '')),
            'subject_name' => $name,
            'subject_type' => $type,
            'maximum_marks' => $maximum === false ? null : $maximum,
            'pass_marks' => $pass === false ? null : $pass,
            'mandatory' => $mandatory,
            'sort_order' => $sortOrder === false ? 0 : $sortOrder,
            'description' => course_nullable_text(
                $row['description'] ?? null,
                5000
            ),
            'status' => $status,
        ];
    }

    if ($errors !== []) {
        json_error('Course Subject validation failed.', 422, $errors);
    }

    return $cleanRows;
}

function course_subject_dependencies(PDO $pdo, int $subjectId): array
{
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND COLUMN_NAME='subject_id'
           AND TABLE_NAME LIKE 'college\\_%'
           AND TABLE_NAME<>'college_course_subjects'"
    );
    $stmt->execute();

    $counts = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        $tableName = (string)$tableName;

        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            continue;
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM `' . $tableName . '`
             WHERE subject_id=:subject_id'
        );
        $countStmt->execute([':subject_id' => $subjectId]);

        $count = (int)$countStmt->fetchColumn();
        if ($count > 0) {
            $counts[$tableName] = $count;
        }
    }

    return $counts;
}

function course_subject_assert_name_available(
    PDO $pdo,
    int $branchId,
    int $courseId,
    string $subjectName,
    int $excludeId = 0,
    array $ignoreIds = []
): bool {
    $sql =
        'SELECT id
         FROM college_course_subjects
         WHERE branch_id=:branch_id
           AND course_id=:course_id
           AND subject_name=:subject_name';

    $params = [
        ':branch_id' => $branchId,
        ':course_id' => $courseId,
        ':subject_name' => $subjectName,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $cleanIgnore = array_values(array_filter(
        array_map('intval', $ignoreIds),
        static fn(int $id): bool => $id > 0
    ));

    if ($cleanIgnore !== []) {
        $placeholders = [];
        foreach ($cleanIgnore as $index => $id) {
            $key = ':ignore_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $sql .= ' AND id NOT IN (' . implode(',', $placeholders) . ')';
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return !$stmt->fetchColumn();
}

function course_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Course reference is required.', 422, ['ref' => 'Course reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'college_course');
    } catch (Throwable $e) {
        json_error('Invalid Course reference.', 422, ['ref' => 'Invalid Course reference.']);
    }

    return 0;
}

function course_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $maxLength);
}

function course_positive_int($value, string $field, string $label, bool $nullable = false): ?int
{
    if ($value === '' || $value === null) {
        if ($nullable) {
            return null;
        }
        json_error('Course validation failed.', 422, [$field => $label . ' is required.']);
    }

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        json_error('Course validation failed.', 422, [$field => $label . ' must be a whole number.']);
    }

    $number = (int)$value;
    if ($number < 1) {
        json_error('Course validation failed.', 422, [$field => $label . ' must be greater than zero.']);
    }

    return $number;
}

function course_duration_unit($value): string
{
    $unit = strtolower(trim((string)$value));
    $allowed = ['days','weeks','months','years'];

    if (!in_array($unit, $allowed, true)) {
        json_error('Course validation failed.', 422, [
            'duration_unit' => 'Select Days, Weeks, Months or Years.'
        ]);
    }

    return $unit;
}

function course_clean_code($value): string
{
    $code = strtoupper(trim((string)$value));
    $code = preg_replace('/\s+/', '', $code) ?? '';

    if ($code === '') {
        json_error('Course validation failed.', 422, ['course_code' => 'Course Code is required.']);
    }

    if (mb_strlen($code) > 50) {
        json_error('Course validation failed.', 422, ['course_code' => 'Course Code cannot exceed 50 characters.']);
    }

    if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $code)) {
        json_error('Course validation failed.', 422, [
            'course_code' => 'Use letters, numbers, dot, hyphen, underscore or slash only.'
        ]);
    }

    return $code;
}

function course_clean_name($value): string
{
    $name = trim((string)$value);
    if ($name === '') {
        json_error('Course validation failed.', 422, ['course_name' => 'Course Name is required.']);
    }

    if (mb_strlen($name) > 180) {
        json_error('Course validation failed.', 422, ['course_name' => 'Course Name cannot exceed 180 characters.']);
    }

    return $name;
}

function course_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT course_code
         FROM college_courses
         WHERE branch_id=:branch_id
           AND course_code REGEXP '^CRS[0-9]+$'
         ORDER BY CAST(SUBSTRING(course_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^CRS([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'CRS' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function course_validate(array $data): array
{
    $courseCode = course_clean_code($data['course_code'] ?? '');
    $courseName = course_clean_name($data['course_name'] ?? '');
    $durationValue = course_positive_int($data['duration_value'] ?? null, 'duration_value', 'Duration');
    $durationUnit = course_duration_unit($data['duration_unit'] ?? '');
    $maximumStudents = course_positive_int(
        $data['maximum_students'] ?? null,
        'maximum_students',
        'Maximum Students',
        true
    );
    $eligibility = course_nullable_text($data['eligibility'] ?? null, 255);
    $description = course_nullable_text($data['description'] ?? null, 5000);

    $statusRaw = $data['status'] ?? 1;
    if (!in_array((int)$statusRaw, [0,1], true)) {
        json_error('Course validation failed.', 422, ['status' => 'Select a valid Status.']);
    }

    return [
        'course_code' => $courseCode,
        'course_name' => $courseName,
        'duration_value' => $durationValue,
        'duration_unit' => $durationUnit,
        'eligibility' => $eligibility,
        'description' => $description,
        'maximum_students' => $maximumStudents,
        'status' => (int)$statusRaw,
    ];
}

function course_assert_unique_code(int $branchId, string $courseCode, int $excludeId = 0): void
{
    $sql = 'SELECT id
            FROM college_courses
            WHERE branch_id=:branch_id
              AND course_code=:course_code';
    $params = [
        ':branch_id' => $branchId,
        ':course_code' => $courseCode,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Course validation failed.', 409, [
            'course_code' => 'This Course Code is already used in the current branch.'
        ]);
    }
}

function course_record(array $ctx, int $id): array
{
    $stmt = db()->prepare(
        'SELECT cc.id,cc.branch_id,cc.course_code,cc.course_name,
                cc.duration_value,cc.duration_unit,cc.eligibility,cc.description,
                cc.maximum_students,cc.status,cc.created_by,cc.created_at,cc.updated_at,
                u.name AS created_by_name
         FROM college_courses cc
         LEFT JOIN users u ON u.id=cc.created_by
         WHERE cc.id=:id
           AND cc.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Course was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['duration_value'] = (int)$row['duration_value'];
    $row['maximum_students'] = $row['maximum_students'] === null ? null : (int)$row['maximum_students'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('college_course', (int)$row['id']);

    return $row;
}

function course_dependency_counts(PDO $pdo, int $courseId): array
{
    $dependencies = [
        'batches' => ['table' => 'college_batches', 'label' => 'Batch'],
        'admissions' => ['table' => 'college_admissions', 'label' => 'Admission'],
        'fee_structures' => ['table' => 'college_fee_structures', 'label' => 'Fee Structure'],
    ];

    $counts = [];
    foreach ($dependencies as $key => $def) {
        $tableStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        );
        $tableStmt->execute([':table_name' => $def['table']]);
        if ((int)$tableStmt->fetchColumn() !== 1) {
            $counts[$key] = 0;
            continue;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $def['table'] . ' WHERE course_id=:course_id');
        $stmt->execute([':course_id' => $courseId]);
        $counts[$key] = (int)$stmt->fetchColumn();
    }

    return $counts;
}

$method = request_method();
course_require_schema();
course_require_subject_schema();

/* -------------------------------------------------------------------------
 * Form options
 * ---------------------------------------------------------------------- */
if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('course-form.php', ACTION_VIEW);
    $ctx = course_tenant_context($access['user']);

    json_success('Course form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_course_code' => course_generate_code((int)$ctx['branch_id']),
        'duration_units' => [
            ['value' => 'days', 'label' => 'Days'],
            ['value' => 'weeks', 'label' => 'Weeks'],
            ['value' => 'months', 'label' => 'Months'],
            ['value' => 'years', 'label' => 'Years'],
        ],
        'branch' => $ctx,
    ]);
}

/* -------------------------------------------------------------------------
 * Single Course - View / Edit
 * ---------------------------------------------------------------------- */
if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('course-form.php', ACTION_VIEW);
    $ctx = course_tenant_context($access['user']);
    $id = course_ref_to_id($_GET['ref'] ?? '');

    json_success('Course loaded.', [
        'course' => course_record($ctx, $id),
        'subjects' => course_subject_rows($ctx, $id),
        'allowed_actions' => $access['actions'],
        'branch' => $ctx,
    ]);
}

/* -------------------------------------------------------------------------
 * Subject List - Server-side DataTable
 * Uses the SAME api/courses.php endpoint.
 * Request: ?datatable=1&resource=subjects
 * ---------------------------------------------------------------------- */
if (
    $method === 'GET' &&
    isset($_GET['datatable']) &&
    strtolower(trim((string)($_GET['resource'] ?? ''))) === 'subjects'
) {
    $access = require_permission('course-subject-list.php', ACTION_VIEW);
    $ctx = course_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $formMenu = menu_by_path('course-form.php');
    $formActions = $formMenu
        ? effective_actions_for_menu($access['user'], $formMenu)
        : [];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));

    $search = trim((string)($_GET['search']['value'] ?? ''));

    $courseId =
        isset($_GET['course_id']) && $_GET['course_id'] !== ''
            ? (int)$_GET['course_id']
            : 0;

    $subjectType =
        isset($_GET['subject_type']) && $_GET['subject_type'] !== ''
            ? (int)$_GET['subject_type']
            : 0;

    $status =
        isset($_GET['status']) && $_GET['status'] !== ''
            ? (int)$_GET['status']
            : -1;

    $where = ['s.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';

        $where[] =
            '(s.subject_code LIKE :s_code
              OR s.subject_name LIKE :s_name
              OR s.description LIKE :s_description
              OR c.course_code LIKE :s_course_code
              OR c.course_name LIKE :s_course_name)';

        $params += [
            ':s_code' => $like,
            ':s_name' => $like,
            ':s_description' => $like,
            ':s_course_code' => $like,
            ':s_course_name' => $like,
        ];
    }

    if ($courseId > 0) {
        $where[] = 's.course_id=:course_id';
        $params[':course_id'] = $courseId;
    }

    if (in_array($subjectType, [1,2,3], true)) {
        $where[] = 's.subject_type=:subject_type';
        $params[':subject_type'] = $subjectType;
    }

    if (in_array($status, [0,1], true)) {
        $where[] = 's.status=:status';
        $params[':status'] = $status;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_course_subjects
         WHERE branch_id=:branch_id'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_course_subjects s
         INNER JOIN college_courses c
                 ON c.id=s.course_id
                AND c.branch_id=s.branch_id
         WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN s.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN s.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(SUM(CASE WHEN s.mandatory=1 THEN 1 ELSE 0 END),0) AS mandatory_count
         FROM college_course_subjects s
         INNER JOIN college_courses c
                 ON c.id=s.course_id
                AND c.branch_id=s.branch_id
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id',':course_id',':subject_type',':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        's.subject_code',
        'c.course_name',
        's.subject_name',
        's.subject_type',
        's.maximum_marks',
        's.pass_marks',
        's.mandatory',
        's.sort_order',
        's.status',
        's.created_at',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 7);
    $orderDir =
        strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
            ? 'DESC'
            : 'ASC';

    $orderBy = $orderColumns[$orderIndex] ?? 's.sort_order';

    $sql =
        'SELECT
            s.id,
            s.course_id,
            s.subject_code,
            s.subject_name,
            s.subject_type,
            s.maximum_marks,
            s.pass_marks,
            s.mandatory,
            s.sort_order,
            s.status,
            s.created_at,
            c.course_code,
            c.course_name
         FROM college_course_subjects s
         INNER JOIN college_courses c
                 ON c.id=s.course_id
                AND c.branch_id=s.branch_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $orderBy . ' ' . $orderDir . ',
                  c.course_name ASC,
                  s.sort_order ASC,
                  s.subject_name ASC,
                  s.id DESC
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $isInt = in_array(
            $key,
            [':branch_id', ':course_id', ':subject_type', ':status'],
            true
        );

        $stmt->bindValue(
            $key,
            $value,
            $isInt ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['course_id'] = (int)$row['course_id'];
        $row['subject_type'] = (int)$row['subject_type'];
        $row['maximum_marks'] =
            $row['maximum_marks'] === null
                ? null
                : (float)$row['maximum_marks'];
        $row['pass_marks'] =
            $row['pass_marks'] === null
                ? null
                : (float)$row['pass_marks'];
        $row['mandatory'] = (int)$row['mandatory'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['status'] = (int)$row['status'];

        $row['course_label'] =
            $row['course_code'] . ' - ' . $row['course_name'];

        $row['subject_type_label'] =
            course_subject_type_label((int)$row['subject_type']);

        $row['mandatory_label'] =
            (int)$row['mandatory'] === 1 ? 'Yes' : 'No';

        $row['status_label'] =
            (int)$row['status'] === 1 ? 'Active' : 'Inactive';

        $row['ref'] = encryptReference(
            'college_course_subject',
            (int)$row['id']
        );

        $courseRef = encryptReference(
            'college_course',
            (int)$row['course_id']
        );

        $row['view_url'] =
            'course-form.php?ref=' .
            rawurlencode($courseRef) .
            '&view=1#subjects';

        $row['edit_url'] =
            'course-form.php?ref=' .
            rawurlencode($courseRef) .
            '#subjects';

        unset($row['id']);
        $rows[] = $row;
    }

    $courseStmt = db()->prepare(
        'SELECT id,course_code,course_name,status
         FROM college_courses
         WHERE branch_id=:branch_id
         ORDER BY course_name,course_code'
    );
    $courseStmt->execute([':branch_id' => $branchId]);

    $courses = [];
    foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $course) {
        $courses[] = [
            'id' => (int)$course['id'],
            'course_code' => (string)$course['course_code'],
            'course_name' => (string)$course['course_name'],
            'status' => (int)$course['status'],
        ];
    }

    json_success('Course Subjects loaded.', [
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
            'mandatory_count' => (int)($summary['mandatory_count'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
        'courses' => $courses,
    ]);
}

/* -------------------------------------------------------------------------
 * Course List - Server-side DataTable
 * ---------------------------------------------------------------------- */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('course-list.php', ACTION_VIEW);
    $ctx = course_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $formActions = effective_actions_for_menu($access['user'], menu_by_path('course-form.php'));

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0 ? 100000 : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : -1;
    $durationUnit = strtolower(trim((string)($_GET['duration_unit'] ?? '')));

    $where = ['cc.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(cc.course_code LIKE :s_code
                     OR cc.course_name LIKE :s_name
                     OR cc.eligibility LIKE :s_eligibility
                     OR cc.description LIKE :s_description)';
        $params += [
            ':s_code' => $like,
            ':s_name' => $like,
            ':s_eligibility' => $like,
            ':s_description' => $like,
        ];
    }

    if (in_array($status, [0,1], true)) {
        $where[] = 'cc.status=:status';
        $params[':status'] = $status;
    }

    if (in_array($durationUnit, ['days','weeks','months','years'], true)) {
        $where[] = 'cc.duration_unit=:duration_unit';
        $params[':duration_unit'] = $durationUnit;
    }

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_courses WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countSql = 'SELECT COUNT(*)
                 FROM college_courses cc
                 WHERE ' . implode(' AND ', $where);
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN cc.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN cc.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(SUM(COALESCE(cc.maximum_students,0)),0) AS total_capacity
         FROM college_courses cc
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id',':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'cc.course_code',
        'cc.course_name',
        'cc.duration_value',
        'cc.eligibility',
        'cc.maximum_students',
        'cc.status',
        'cc.created_at',
    ];
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 1);
    $direction = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = $orderColumns[$orderColumn] ?? 'cc.course_name';

    $sql = 'SELECT cc.id,cc.course_code,cc.course_name,cc.duration_value,cc.duration_unit,
                   cc.eligibility,cc.maximum_students,cc.status,cc.created_at,
                   u.name AS created_by_name
            FROM college_courses cc
            LEFT JOIN users u ON u.id=cc.created_by
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . ' ' . $direction . ',cc.id DESC
            LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $isInt = in_array($key, [':branch_id', ':status'], true);
        $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['duration_value'] = (int)$row['duration_value'];
        $row['maximum_students'] = $row['maximum_students'] === null ? null : (int)$row['maximum_students'];
        $row['status'] = (int)$row['status'];
        $row['duration_label'] = $row['duration_value'] . ' ' . ucfirst((string)$row['duration_unit']);
        $row['status_label'] = (int)$row['status'] === 1 ? 'Active' : 'Inactive';
        $row['ref'] = encryptReference('college_course', (int)$row['id']);
        $row['view_url'] = 'course-form.php?ref=' . rawurlencode($row['ref']) . '&view=1';
        $row['edit_url'] = 'course-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Courses loaded.', [
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
    ]);
}

/* -------------------------------------------------------------------------
 * Create / Update / Delete
 * ---------------------------------------------------------------------- */
if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'delete_subject') {
        $access = require_permission('course-form.php', 4);
        $ctx = course_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $pdo = db();

        $subjectId = course_subject_ref_to_id(
            $data['ref'] ?? '',
            'ref'
        );

        $stmt = $pdo->prepare(
            'SELECT *
             FROM college_course_subjects
             WHERE id=:id
               AND branch_id=:branch_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $subjectId,
            ':branch_id' => $branchId,
        ]);

        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old) {
            json_error('Course Subject was not found.', 404);
        }

        $dependencies =
            course_subject_dependencies($pdo, $subjectId);

        if ($dependencies !== []) {
            json_error(
                'This Subject / Module is already in use. Make it Inactive instead of deleting it.',
                409
            );
        }

        $deleteStmt = $pdo->prepare(
            'DELETE FROM college_course_subjects
             WHERE id=:id
               AND branch_id=:branch_id'
        );
        $deleteStmt->execute([
            ':id' => $subjectId,
            ':branch_id' => $branchId,
        ]);

        audit_log($userId, 4, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $subjectId,
            'old_data' => $old,
        ]);

        json_success('Course Subject deleted successfully.');
    }

    if ($action === 'delete') {
        $access = require_permission('course-form.php', 4);
        $ctx = course_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = course_ref_to_id($data['ref'] ?? '');
        $pdo = db();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM college_courses
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                ':id' => $id,
                ':branch_id' => $branchId,
            ]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                json_error('Course was not found.', 404);
            }

            $deps = course_dependency_counts($pdo, $id);
            $blocked = [];

            if (($deps['batches'] ?? 0) > 0) {
                $blocked[] =
                    $deps['batches'] . ' Batch' .
                    ($deps['batches'] === 1 ? '' : 'es');
            }
            if (($deps['admissions'] ?? 0) > 0) {
                $blocked[] =
                    $deps['admissions'] . ' Admission' .
                    ($deps['admissions'] === 1 ? '' : 's');
            }
            if (($deps['fee_structures'] ?? 0) > 0) {
                $blocked[] =
                    $deps['fee_structures'] . ' Fee Structure' .
                    ($deps['fee_structures'] === 1 ? '' : 's');
            }

            $subjectStmt = $pdo->prepare(
                'SELECT id
                 FROM college_course_subjects
                 WHERE branch_id=:branch_id
                   AND course_id=:course_id'
            );
            $subjectStmt->execute([
                ':branch_id' => $branchId,
                ':course_id' => $id,
            ]);

            $subjectIds = array_map(
                'intval',
                $subjectStmt->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ($subjectIds as $subjectId) {
                $subjectDeps = course_subject_dependencies($pdo, $subjectId);
                if ($subjectDeps !== []) {
                    $blocked[] = 'Subject / Module already used in another College module';
                    break;
                }
            }

            if ($blocked !== []) {
                $pdo->rollBack();
                json_error(
                    'This Course cannot be deleted because it is already in use: ' .
                    implode(', ', array_unique($blocked)) .
                    '. Set the Course to Inactive instead.',
                    409
                );
            }

            $deleteSubjects = $pdo->prepare(
                'DELETE FROM college_course_subjects
                 WHERE branch_id=:branch_id
                   AND course_id=:course_id'
            );
            $deleteSubjects->execute([
                ':branch_id' => $branchId,
                ':course_id' => $id,
            ]);

            $deleteCourse = $pdo->prepare(
                'DELETE FROM college_courses
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );
            $deleteCourse->execute([
                ':id' => $id,
                ':branch_id' => $branchId,
            ]);

            $pdo->commit();

            audit_log($userId, 4, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
            ]);

            json_success('Course and its Subjects deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Course action.', 404);
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access = require_permission(
        'course-form.php',
        $isUpdate ? ACTION_UPDATE : ACTION_CREATE
    );

    $ctx = course_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $pdo = db();

    $subjectsRaw =
        isset($data['subjects']) && is_array($data['subjects'])
            ? $data['subjects']
            : [];

    $removedSubjectRefs =
        isset($data['removed_subjects']) && is_array($data['removed_subjects'])
            ? array_values(array_filter(
                array_map(
                    static fn($value): string => trim((string)$value),
                    $data['removed_subjects']
                ),
                static fn(string $value): bool => $value !== ''
            ))
            : [];

    if ($removedSubjectRefs !== []) {
        require_permission('course-form.php', 4);
    }

    $cleanSubjects = course_subject_validate_rows($subjectsRaw);

    /*
     * CREATE COURSE + SUBJECTS IN ONE TRANSACTION
     */
    if (!$isUpdate) {
        $cleanCourse = course_validate($data);
        course_assert_unique_code(
            $branchId,
            $cleanCourse['course_code']
        );

        if ($removedSubjectRefs !== []) {
            json_error(
                'New Course cannot contain removed Subject references.',
                422
            );
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO college_courses
                 (branch_id,course_code,course_name,duration_value,duration_unit,
                  eligibility,description,maximum_students,status,
                  created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:course_code,:course_name,:duration_value,:duration_unit,
                  :eligibility,:description,:maximum_students,:status,
                  :created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':course_code' => $cleanCourse['course_code'],
                ':course_name' => $cleanCourse['course_name'],
                ':duration_value' => $cleanCourse['duration_value'],
                ':duration_unit' => $cleanCourse['duration_unit'],
                ':eligibility' => $cleanCourse['eligibility'],
                ':description' => $cleanCourse['description'],
                ':maximum_students' => $cleanCourse['maximum_students'],
                ':status' => $cleanCourse['status'],
                ':created_by' => $userId,
            ]);

            $courseId = (int)$pdo->lastInsertId();

            $createdSubjects = [];

            foreach ($cleanSubjects as $index => $subject) {
                if ($subject['ref'] !== '') {
                    json_error(
                        'Invalid Subject reference while creating a new Course.',
                        422,
                        [
                            'subjects[' . $index . '][subject_name]' =>
                                'New Course can contain only new Subject rows.'
                        ]
                    );
                }

                if (!course_subject_assert_name_available(
                    $pdo,
                    $branchId,
                    $courseId,
                    $subject['subject_name']
                )) {
                    json_error(
                        'Course Subject validation failed.',
                        409,
                        [
                            'subjects[' . $index . '][subject_name]' =>
                                'This Subject / Module already exists under the Course.'
                        ]
                    );
                }

                $subjectCode =
                    course_subject_generate_code($pdo, $branchId);

                $subjectStmt = $pdo->prepare(
                    'INSERT INTO college_course_subjects
                     (branch_id,course_id,subject_code,subject_name,subject_type,
                      maximum_marks,pass_marks,mandatory,sort_order,description,
                      status,created_by,created_at,updated_at)
                     VALUES
                     (:branch_id,:course_id,:subject_code,:subject_name,:subject_type,
                      :maximum_marks,:pass_marks,:mandatory,:sort_order,:description,
                      :status,:created_by,NOW(),NOW())'
                );
                $subjectStmt->execute([
                    ':branch_id' => $branchId,
                    ':course_id' => $courseId,
                    ':subject_code' => $subjectCode,
                    ':subject_name' => $subject['subject_name'],
                    ':subject_type' => $subject['subject_type'],
                    ':maximum_marks' => $subject['maximum_marks'],
                    ':pass_marks' => $subject['pass_marks'],
                    ':mandatory' => $subject['mandatory'],
                    ':sort_order' => $subject['sort_order'],
                    ':description' => $subject['description'],
                    ':status' => $subject['status'],
                    ':created_by' => $userId,
                ]);

                $createdSubjects[] = [
                    'id' => (int)$pdo->lastInsertId(),
                    'subject_code' => $subjectCode,
                    'data' => $subject,
                ];
            }

            $pdo->commit();

            audit_log($userId, ACTION_CREATE, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $courseId,
                'new_data' => $cleanCourse,
            ]);

            foreach ($createdSubjects as $createdSubject) {
                audit_log($userId, ACTION_CREATE, [
                    'company_id' => (int)$ctx['company_id'],
                    'branch_id' => $branchId,
                    'menu_id' => (int)$access['menu']['id'],
                    'record_id' => (int)$createdSubject['id'],
                    'new_data' =>
                        $createdSubject['data'] +
                        [
                            'course_id' => $courseId,
                            'subject_code' =>
                                $createdSubject['subject_code']
                        ],
                ]);
            }

            json_success(
                'Course' .
                ($createdSubjects !== []
                    ? ' and ' .
                      count($createdSubjects) .
                      ' Subject' .
                      (count($createdSubjects) === 1 ? '' : 's')
                    : '') .
                ' created successfully.',
                [
                    'course' => course_record($ctx, $courseId),
                    'subjects' => course_subject_rows($ctx, $courseId),
                ],
                201
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                json_error(
                    'Course or Subject duplicate value already exists.',
                    409
                );
            }

            throw $e;
        }
    }

    /*
     * UPDATE COURSE + SUBJECTS IN ONE TRANSACTION
     */
    $courseId = course_ref_to_id($data['ref']);

    $pdo->beginTransaction();
    try {
        $oldCourseStmt = $pdo->prepare(
            'SELECT *
             FROM college_courses
             WHERE id=:id
               AND branch_id=:branch_id
             LIMIT 1
             FOR UPDATE'
        );
        $oldCourseStmt->execute([
            ':id' => $courseId,
            ':branch_id' => $branchId,
        ]);
        $oldCourse = $oldCourseStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldCourse) {
            json_error('Course was not found.', 404);
        }

        /*
         * Course Code is immutable after creation.
         */
        $data['course_code'] = (string)$oldCourse['course_code'];
        $cleanCourse = course_validate($data);

        course_assert_unique_code(
            $branchId,
            (string)$oldCourse['course_code'],
            $courseId
        );

        $courseUpdate = $pdo->prepare(
            'UPDATE college_courses
             SET course_name=:course_name,
                 duration_value=:duration_value,
                 duration_unit=:duration_unit,
                 eligibility=:eligibility,
                 description=:description,
                 maximum_students=:maximum_students,
                 status=:status,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        );
        $courseUpdate->execute([
            ':course_name' => $cleanCourse['course_name'],
            ':duration_value' => $cleanCourse['duration_value'],
            ':duration_unit' => $cleanCourse['duration_unit'],
            ':eligibility' => $cleanCourse['eligibility'],
            ':description' => $cleanCourse['description'],
            ':maximum_students' => $cleanCourse['maximum_students'],
            ':status' => $cleanCourse['status'],
            ':id' => $courseId,
            ':branch_id' => $branchId,
        ]);

        /*
         * Resolve Subject refs removed from the form.
         */
        $removeIds = [];
        foreach ($removedSubjectRefs as $removedIndex => $removedRef) {
            $subjectId = course_subject_ref_to_id(
                $removedRef,
                'removed_subjects[' . $removedIndex . ']'
            );

            $subjectStmt = $pdo->prepare(
                'SELECT *
                 FROM college_course_subjects
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND course_id=:course_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $subjectStmt->execute([
                ':id' => $subjectId,
                ':branch_id' => $branchId,
                ':course_id' => $courseId,
            ]);
            $oldSubject = $subjectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldSubject) {
                json_error(
                    'Removed Subject does not belong to this Course.',
                    422
                );
            }

            $dependencies =
                course_subject_dependencies($pdo, $subjectId);

            if ($dependencies !== []) {
                json_error(
                    'Subject "' .
                    (string)$oldSubject['subject_name'] .
                    '" is already in use. Make it Inactive instead of removing it.',
                    409
                );
            }

            $removeIds[] = $subjectId;
        }

        /*
         * Validate every subject row against this Course.
         */
        $resolvedRows = [];
        $subjectErrors = [];

        foreach ($cleanSubjects as $index => $subject) {
            $subjectId = 0;
            $oldSubject = null;

            if ($subject['ref'] !== '') {
                try {
                    $subjectId = decryptReference(
                        $subject['ref'],
                        'college_course_subject'
                    );
                } catch (Throwable $e) {
                    $subjectErrors[
                        'subjects[' . $index . '][subject_name]'
                    ] = 'Invalid Subject reference.';
                    continue;
                }

                $subjectStmt = $pdo->prepare(
                    'SELECT *
                     FROM college_course_subjects
                     WHERE id=:id
                       AND branch_id=:branch_id
                       AND course_id=:course_id
                     LIMIT 1
                     FOR UPDATE'
                );
                $subjectStmt->execute([
                    ':id' => $subjectId,
                    ':branch_id' => $branchId,
                    ':course_id' => $courseId,
                ]);

                $oldSubject = $subjectStmt->fetch(PDO::FETCH_ASSOC);

                if (!$oldSubject) {
                    $subjectErrors[
                        'subjects[' . $index . '][subject_name]'
                    ] = 'Subject / Module was not found under this Course.';
                    continue;
                }
            }

            if (!course_subject_assert_name_available(
                $pdo,
                $branchId,
                $courseId,
                $subject['subject_name'],
                $subjectId,
                $removeIds
            )) {
                $subjectErrors[
                    'subjects[' . $index . '][subject_name]'
                ] = 'This Subject / Module already exists under this Course.';
            }

            $resolvedRows[$index] = [
                'id' => $subjectId,
                'old' => $oldSubject,
                'data' => $subject,
            ];
        }

        if ($subjectErrors !== []) {
            json_error(
                'Course Subject validation failed.',
                409,
                $subjectErrors
            );
        }

        /*
         * Delete explicitly removed Subjects.
         */
        $deletedSubjects = [];
        foreach ($removeIds as $removeId) {
            $oldStmt = $pdo->prepare(
                'SELECT *
                 FROM college_course_subjects
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND course_id=:course_id
                 LIMIT 1'
            );
            $oldStmt->execute([
                ':id' => $removeId,
                ':branch_id' => $branchId,
                ':course_id' => $courseId,
            ]);
            $oldSubject = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $deleteStmt = $pdo->prepare(
                'DELETE FROM college_course_subjects
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND course_id=:course_id'
            );
            $deleteStmt->execute([
                ':id' => $removeId,
                ':branch_id' => $branchId,
                ':course_id' => $courseId,
            ]);

            if ($oldSubject) {
                $deletedSubjects[] = [
                    'id' => $removeId,
                    'old' => $oldSubject,
                ];
            }
        }

        /*
         * Insert / Update remaining Subject rows.
         */
        $createdSubjects = [];
        $updatedSubjects = [];

        foreach ($resolvedRows as $resolved) {
            $subjectId = (int)$resolved['id'];
            $subject = $resolved['data'];

            if ($subjectId > 0) {
                if (in_array($subjectId, $removeIds, true)) {
                    continue;
                }

                $updateSubject = $pdo->prepare(
                    'UPDATE college_course_subjects
                     SET subject_name=:subject_name,
                         subject_type=:subject_type,
                         maximum_marks=:maximum_marks,
                         pass_marks=:pass_marks,
                         mandatory=:mandatory,
                         sort_order=:sort_order,
                         description=:description,
                         status=:status,
                         updated_at=NOW()
                     WHERE id=:id
                       AND branch_id=:branch_id
                       AND course_id=:course_id'
                );
                $updateSubject->execute([
                    ':subject_name' => $subject['subject_name'],
                    ':subject_type' => $subject['subject_type'],
                    ':maximum_marks' => $subject['maximum_marks'],
                    ':pass_marks' => $subject['pass_marks'],
                    ':mandatory' => $subject['mandatory'],
                    ':sort_order' => $subject['sort_order'],
                    ':description' => $subject['description'],
                    ':status' => $subject['status'],
                    ':id' => $subjectId,
                    ':branch_id' => $branchId,
                    ':course_id' => $courseId,
                ]);

                $updatedSubjects[] = [
                    'id' => $subjectId,
                    'old' => $resolved['old'],
                    'new' => $subject,
                ];
            } else {
                $subjectCode =
                    course_subject_generate_code($pdo, $branchId);

                $insertSubject = $pdo->prepare(
                    'INSERT INTO college_course_subjects
                     (branch_id,course_id,subject_code,subject_name,subject_type,
                      maximum_marks,pass_marks,mandatory,sort_order,description,
                      status,created_by,created_at,updated_at)
                     VALUES
                     (:branch_id,:course_id,:subject_code,:subject_name,:subject_type,
                      :maximum_marks,:pass_marks,:mandatory,:sort_order,:description,
                      :status,:created_by,NOW(),NOW())'
                );
                $insertSubject->execute([
                    ':branch_id' => $branchId,
                    ':course_id' => $courseId,
                    ':subject_code' => $subjectCode,
                    ':subject_name' => $subject['subject_name'],
                    ':subject_type' => $subject['subject_type'],
                    ':maximum_marks' => $subject['maximum_marks'],
                    ':pass_marks' => $subject['pass_marks'],
                    ':mandatory' => $subject['mandatory'],
                    ':sort_order' => $subject['sort_order'],
                    ':description' => $subject['description'],
                    ':status' => $subject['status'],
                    ':created_by' => $userId,
                ]);

                $createdSubjects[] = [
                    'id' => (int)$pdo->lastInsertId(),
                    'subject_code' => $subjectCode,
                    'new' => $subject,
                ];
            }
        }

        $pdo->commit();

        audit_log($userId, ACTION_UPDATE, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $courseId,
            'old_data' => $oldCourse,
            'new_data' => $cleanCourse,
        ]);

        foreach ($createdSubjects as $createdSubject) {
            audit_log($userId, ACTION_CREATE, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => (int)$createdSubject['id'],
                'new_data' =>
                    $createdSubject['new'] +
                    [
                        'course_id' => $courseId,
                        'subject_code' =>
                            $createdSubject['subject_code']
                    ],
            ]);
        }

        foreach ($updatedSubjects as $updatedSubject) {
            audit_log($userId, ACTION_UPDATE, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => (int)$updatedSubject['id'],
                'old_data' => $updatedSubject['old'],
                'new_data' => $updatedSubject['new'],
            ]);
        }

        foreach ($deletedSubjects as $deletedSubject) {
            audit_log($userId, 4, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => (int)$deletedSubject['id'],
                'old_data' => $deletedSubject['old'],
            ]);
        }

        json_success(
            'Course and Subjects updated successfully.',
            [
                'course' => course_record($ctx, $courseId),
                'subjects' => course_subject_rows($ctx, $courseId),
            ]
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error(
                'Course or Subject duplicate value already exists.',
                409
            );
        }

        throw $e;
    }
}

json_error('Unsupported request.', 404);
