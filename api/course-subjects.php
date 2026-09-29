<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function subject_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Course Subject management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
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

function subject_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    foreach (['college_courses','college_course_subjects'] as $tableName) {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        );
        $stmt->execute([':table_name' => $tableName]);
        if ((int)$stmt->fetchColumn() !== 1) {
            json_error('Course Subject database update is required.', 500, [
                'schema' => 'Missing table: ' . $tableName . '. Run sql/course-subject-master.sql first.'
            ]);
        }
    }

    $required = [
        'id','branch_id','course_id','subject_code','subject_name','subject_type',
        'maximum_marks','pass_marks','mandatory','sort_order','description','status',
        'created_by','created_at','updated_at'
    ];

    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge(['college_course_subjects'], $required));
    $found = array_map('strtolower', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
    $missing = array_values(array_diff($required, $found));

    if ($missing !== []) {
        json_error('Course Subject database structure is incomplete.', 500, [
            'schema' => 'Missing college_course_subjects columns: ' . implode(', ', $missing)
        ]);
    }

    $checked = true;
}

function subject_ref_to_id($value, string $field = 'ref'): int
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

function subject_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : mb_substr($value, 0, $maxLength);
}

function subject_nonnegative_int_value($value): ?int
{
    if ($value === '' || $value === null) return 0;
    $text = trim((string)$value);
    return preg_match('/^[0-9]+$/', $text) ? (int)$text : null;
}

function subject_decimal_value($value): ?float
{
    if ($value === '' || $value === null) return null;
    if (!is_numeric($value)) return INF;
    return round((float)$value, 2);
}

function subject_course_options(int $branchId, ?int $includeId = null, bool $activeOnly = true): array
{
    $sql = 'SELECT id,course_code,course_name,status FROM college_courses WHERE branch_id=:branch_id';
    $params = [':branch_id' => $branchId];

    if ($activeOnly) {
        if ($includeId && $includeId > 0) {
            $sql .= ' AND (status=1 OR id=:include_id)';
            $params[':include_id'] = $includeId;
        } else {
            $sql .= ' AND status=1';
        }
    }

    $sql .= ' ORDER BY course_name,course_code';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'course_code' => (string)$row['course_code'],
            'course_name' => (string)$row['course_name'],
            'status' => (int)$row['status'],
        ];
    }
    return $rows;
}

function subject_course_record(int $branchId, int $courseId): array
{
    $stmt = db()->prepare(
        'SELECT id,course_code,course_name,status
         FROM college_courses
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $courseId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Course Subject validation failed.', 422, [
            'course_id' => 'Selected Course is not available in the current branch.'
        ]);
    }

    return [
        'id' => (int)$row['id'],
        'course_code' => (string)$row['course_code'],
        'course_name' => (string)$row['course_name'],
        'status' => (int)$row['status'],
    ];
}

function subject_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT subject_code FROM college_course_subjects
         WHERE branch_id=:branch_id AND subject_code REGEXP '^SUB[0-9]+$'
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

function subject_type_label(int $type): string
{
    return [1 => 'Theory', 2 => 'Practical', 3 => 'Theory + Practical'][$type] ?? 'Unknown';
}

function subject_validate_row(array $row, int $index): array
{
    $prefix = 'subjects[' . $index . ']';
    $errors = [];

    $name = trim((string)($row['subject_name'] ?? ''));
    if ($name === '') {
        $errors[$prefix . '[subject_name]'] = 'Subject / Module Name is required.';
    } elseif (mb_strlen($name) > 180) {
        $errors[$prefix . '[subject_name]'] = 'Subject / Module Name cannot exceed 180 characters.';
    }

    $type = (int)($row['subject_type'] ?? 0);
    if (!in_array($type, [1,2,3], true)) {
        $errors[$prefix . '[subject_type]'] = 'Select Theory, Practical or Theory + Practical.';
    }

    $maximum = subject_decimal_value($row['maximum_marks'] ?? null);
    $pass = subject_decimal_value($row['pass_marks'] ?? null);

    if ($maximum === INF || ($maximum !== null && $maximum < 0)) {
        $errors[$prefix . '[maximum_marks]'] = 'Enter valid Maximum Marks.';
    }
    if ($pass === INF || ($pass !== null && $pass < 0)) {
        $errors[$prefix . '[pass_marks]'] = 'Enter valid Pass Marks.';
    }

    if ($maximum !== INF && $pass !== INF) {
        if (($maximum === null) !== ($pass === null)) {
            if ($maximum === null) {
                $errors[$prefix . '[maximum_marks]'] = 'Enter Maximum Marks when Pass Marks is entered.';
            }
            if ($pass === null) {
                $errors[$prefix . '[pass_marks]'] = 'Enter Pass Marks when Maximum Marks is entered.';
            }
        }
        if ($maximum !== null && $maximum <= 0) {
            $errors[$prefix . '[maximum_marks]'] = 'Maximum Marks must be greater than zero.';
        }
        if ($maximum !== null && $pass !== null && $pass > $maximum) {
            $errors[$prefix . '[pass_marks]'] = 'Pass Marks cannot be greater than Maximum Marks.';
        }
    }

    $mandatory = (int)($row['mandatory'] ?? 1);
    if (!in_array($mandatory, [0,1], true)) {
        $errors[$prefix . '[mandatory]'] = 'Select Yes or No for Mandatory.';
    }

    $sortOrder = subject_nonnegative_int_value($row['sort_order'] ?? 0);
    if ($sortOrder === null) {
        $errors[$prefix . '[sort_order]'] = 'Sort Order must be a whole number and cannot be negative.';
    }

    $status = (int)($row['status'] ?? 1);
    if (!in_array($status, [0,1], true)) {
        $errors[$prefix . '[status]'] = 'Select a valid Status.';
    }

    return [
        'errors' => $errors,
        'clean' => [
            'subject_name' => $name,
            'subject_type' => $type,
            'maximum_marks' => $maximum === INF ? null : $maximum,
            'pass_marks' => $pass === INF ? null : $pass,
            'mandatory' => $mandatory,
            'sort_order' => $sortOrder ?? 0,
            'description' => subject_nullable_text($row['description'] ?? null, 5000),
            'status' => $status,
        ],
    ];
}

function subject_assert_unique(int $branchId, int $courseId, string $subjectName, int $excludeId = 0): bool
{
    $sql =
        'SELECT id FROM college_course_subjects
         WHERE branch_id=:branch_id AND course_id=:course_id AND subject_name=:subject_name';
    $params = [
        ':branch_id' => $branchId,
        ':course_id' => $courseId,
        ':subject_name' => $subjectName,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function subject_record(array $ctx, int $id): array
{
    $stmt = db()->prepare(
        'SELECT s.id,s.branch_id,s.course_id,s.subject_code,s.subject_name,s.subject_type,
                s.maximum_marks,s.pass_marks,s.mandatory,s.sort_order,s.description,s.status,
                s.created_by,s.created_at,s.updated_at,c.course_code,c.course_name,
                u.name AS created_by_name
         FROM college_course_subjects s
         INNER JOIN college_courses c ON c.id=s.course_id AND c.branch_id=s.branch_id
         LEFT JOIN users u ON u.id=s.created_by
         WHERE s.id=:id AND s.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => (int)$ctx['branch_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Course Subject was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['course_id'] = (int)$row['course_id'];
    $row['subject_type'] = (int)$row['subject_type'];
    $row['maximum_marks'] = $row['maximum_marks'] === null ? null : (float)$row['maximum_marks'];
    $row['pass_marks'] = $row['pass_marks'] === null ? null : (float)$row['pass_marks'];
    $row['mandatory'] = (int)$row['mandatory'];
    $row['sort_order'] = (int)$row['sort_order'];
    $row['status'] = (int)$row['status'];
    $row['subject_type_label'] = subject_type_label($row['subject_type']);
    $row['ref'] = encryptReference('college_course_subject', (int)$row['id']);
    return $row;
}

function subject_dependency_counts(PDO $pdo, int $subjectId): array
{
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='subject_id'
           AND TABLE_NAME LIKE 'college\\_%'
           AND TABLE_NAME<>'college_course_subjects'"
    );
    $stmt->execute();

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        $tableName = (string)$tableName;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) continue;

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $tableName . '` WHERE subject_id=:subject_id');
        $countStmt->execute([':subject_id' => $subjectId]);
        $count = (int)$countStmt->fetchColumn();
        if ($count > 0) $counts[$tableName] = $count;
    }
    return $counts;
}

$method = request_method();
subject_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('course-subject-form.php', ACTION_VIEW);
    $ctx = subject_tenant_context($access['user']);

    json_success('Course Subject form options loaded.', [
        'allowed_actions' => $access['actions'],
        'courses' => subject_course_options((int)$ctx['branch_id'], null, true),
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('course-subject-form.php', ACTION_VIEW);
    $ctx = subject_tenant_context($access['user']);
    $subject = subject_record($ctx, subject_ref_to_id($_GET['ref'] ?? ''));

    json_success('Course Subject loaded.', [
        'subject' => $subject,
        'allowed_actions' => $access['actions'],
        'courses' => subject_course_options((int)$ctx['branch_id'], (int)$subject['course_id'], true),
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('course-subject-list.php', ACTION_VIEW);
    $ctx = subject_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $formActions = effective_actions_for_menu($access['user'], menu_by_path('course-subject-form.php'));

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0 ? 100000 : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $courseId = isset($_GET['course_id']) && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : 0;
    $subjectType = isset($_GET['subject_type']) && $_GET['subject_type'] !== '' ? (int)$_GET['subject_type'] : 0;
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : -1;

    $where = ['s.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(s.subject_code LIKE :s_code OR s.subject_name LIKE :s_name OR s.description LIKE :s_description OR c.course_code LIKE :s_course_code OR c.course_name LIKE :s_course_name)';
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

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_course_subjects WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*) FROM college_course_subjects s
         INNER JOIN college_courses c ON c.id=s.course_id AND c.branch_id=s.branch_id
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
        's.subject_code','c.course_name','s.subject_name','s.subject_type',
        's.maximum_marks','s.pass_marks','s.mandatory','s.sort_order','s.status','s.created_at'
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 7);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = $orderColumns[$orderIndex] ?? 's.sort_order';

    $sql =
        'SELECT s.id,s.subject_code,s.subject_name,s.subject_type,s.maximum_marks,s.pass_marks,
                s.mandatory,s.sort_order,s.status,s.created_at,
                c.id AS course_id,c.course_code,c.course_name
         FROM college_course_subjects s
         INNER JOIN college_courses c ON c.id=s.course_id AND c.branch_id=s.branch_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $orderBy . ' ' . $orderDir . ',c.course_name ASC,s.sort_order ASC,s.subject_name ASC,s.id DESC
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $isInt = in_array($key, [':branch_id',':course_id',':subject_type',':status'], true);
        $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['course_id'] = (int)$row['course_id'];
        $row['subject_type'] = (int)$row['subject_type'];
        $row['maximum_marks'] = $row['maximum_marks'] === null ? null : (float)$row['maximum_marks'];
        $row['pass_marks'] = $row['pass_marks'] === null ? null : (float)$row['pass_marks'];
        $row['mandatory'] = (int)$row['mandatory'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['status'] = (int)$row['status'];
        $row['course_label'] = $row['course_code'] . ' - ' . $row['course_name'];
        $row['subject_type_label'] = subject_type_label($row['subject_type']);
        $row['mandatory_label'] = $row['mandatory'] === 1 ? 'Yes' : 'No';
        $row['status_label'] = $row['status'] === 1 ? 'Active' : 'Inactive';
        $row['ref'] = encryptReference('college_course_subject', (int)$row['id']);
        $row['view_url'] = 'course-subject-form.php?ref=' . rawurlencode($row['ref']) . '&view=1';
        $row['edit_url'] = 'course-subject-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
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
        'courses' => subject_course_options($branchId, null, false),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'delete') {
        $access = require_permission('course-subject-form.php', 4);
        $ctx = subject_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = subject_ref_to_id($data['ref'] ?? '');
        $pdo = db();

        $old = subject_record($ctx, $id);
        $dependencies = subject_dependency_counts($pdo, $id);
        if ($dependencies !== []) {
            $labels = [];
            foreach ($dependencies as $table => $count) $labels[] = $table . ' (' . $count . ')';
            json_error(
                'This Subject / Module is already in use. Make it Inactive instead of deleting it.',
                409,
                ['dependency' => implode(', ', $labels)]
            );
        }

        $stmt = $pdo->prepare('DELETE FROM college_course_subjects WHERE id=:id AND branch_id=:branch_id');
        $stmt->execute([':id' => $id, ':branch_id' => $branchId]);

        audit_log($userId, 4, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]);

        json_success('Course Subject deleted successfully.');
    }

    $subjects = $data['subjects'] ?? null;
    if (!is_array($subjects) || $subjects === []) {
        json_error('Course Subject validation failed.', 422, [
            'subjects' => 'Add at least one Subject / Module.'
        ]);
    }

    $hasCreate = false;
    $hasUpdate = false;
    foreach ($subjects as $row) {
        $rowRef = is_array($row) ? trim((string)($row['ref'] ?? '')) : '';
        if ($rowRef === '') $hasCreate = true;
        else $hasUpdate = true;
    }

    $access = require_permission(
        'course-subject-form.php',
        $hasUpdate ? ACTION_UPDATE : ACTION_CREATE
    );
    if ($hasCreate && $hasUpdate) {
        require_permission('course-subject-form.php', ACTION_CREATE);
    }

    $ctx = subject_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];

    $courseIdValue = subject_nonnegative_int_value($data['course_id'] ?? '');
    if ($courseIdValue === null || $courseIdValue < 1) {
        json_error('Course Subject validation failed.', 422, ['course_id' => 'Select a Course.']);
    }
    $courseId = (int)$courseIdValue;
    $course = subject_course_record($branchId, $courseId);

    $errors = [];
    $cleanRows = [];
    $existingRows = [];
    $seenNames = [];

    foreach (array_values($subjects) as $index => $row) {
        if (!is_array($row)) {
            $errors['subjects[' . $index . '][subject_name]'] = 'Invalid Subject row.';
            continue;
        }

        $validated = subject_validate_row($row, $index);
        $errors += $validated['errors'];
        $clean = $validated['clean'];
        $rowRef = trim((string)($row['ref'] ?? ''));
        $rowId = 0;
        $old = null;

        if ($rowRef !== '') {
            try {
                $rowId = decryptReference($rowRef, 'college_course_subject');
            } catch (Throwable $e) {
                $errors['subjects[' . $index . '][ref]'] = 'Invalid Course Subject reference.';
                $rowId = 0;
            }

            if ($rowId > 0) {
                $stmt = db()->prepare(
                    'SELECT * FROM college_course_subjects WHERE id=:id AND branch_id=:branch_id LIMIT 1'
                );
                $stmt->execute([':id' => $rowId, ':branch_id' => $branchId]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    $errors['subjects[' . $index . '][ref]'] = 'Course Subject was not found.';
                } else {
                    $existingRows[$index] = $old;
                }
            }
        }

        $nameKey = mb_strtolower(trim((string)$clean['subject_name']));
        if ($nameKey !== '') {
            if (isset($seenNames[$nameKey])) {
                $errors['subjects[' . $index . '][subject_name]'] =
                    'This Subject / Module is already entered in Subject ' . ($seenNames[$nameKey] + 1) . '.';
            } else {
                $seenNames[$nameKey] = $index;
            }
        }

        if ($clean['subject_name'] !== '' && subject_assert_unique($branchId, $courseId, $clean['subject_name'], $rowId)) {
            $errors['subjects[' . $index . '][subject_name]'] =
                'This Subject / Module already exists under the selected Course.';
        }

        $clean['id'] = $rowId;
        $clean['ref'] = $rowRef;
        $cleanRows[$index] = $clean;
    }

    if ((int)$course['status'] !== 1) {
        if ($hasCreate) {
            $errors['course_id'] = 'New Subjects can be added only to an active Course.';
        } else {
            foreach ($existingRows as $old) {
                if ((int)$old['course_id'] !== $courseId) {
                    $errors['course_id'] = 'Select an active Course to move this Subject.';
                    break;
                }
            }
        }
    }

    if ($errors !== []) {
        json_error('Course Subject validation failed.', 422, $errors);
    }

    $pdo = db();
    $createdIds = [];
    $updatedIds = [];
    $auditRows = [];

    $pdo->beginTransaction();
    try {
        foreach ($cleanRows as $index => $clean) {
            if ((int)$clean['id'] > 0) {
                $id = (int)$clean['id'];
                $old = $existingRows[$index];

                $stmt = $pdo->prepare(
                    'UPDATE college_course_subjects
                     SET course_id=:course_id,
                         subject_name=:subject_name,
                         subject_type=:subject_type,
                         maximum_marks=:maximum_marks,
                         pass_marks=:pass_marks,
                         mandatory=:mandatory,
                         sort_order=:sort_order,
                         description=:description,
                         status=:status,
                         updated_at=NOW()
                     WHERE id=:id AND branch_id=:branch_id'
                );
                $stmt->execute([
                    ':course_id' => $courseId,
                    ':subject_name' => $clean['subject_name'],
                    ':subject_type' => $clean['subject_type'],
                    ':maximum_marks' => $clean['maximum_marks'],
                    ':pass_marks' => $clean['pass_marks'],
                    ':mandatory' => $clean['mandatory'],
                    ':sort_order' => $clean['sort_order'],
                    ':description' => $clean['description'],
                    ':status' => $clean['status'],
                    ':id' => $id,
                    ':branch_id' => $branchId,
                ]);

                $updatedIds[] = $id;
                $auditRows[] = ['action' => ACTION_UPDATE, 'id' => $id, 'old' => $old, 'new' => $clean + ['course_id' => $courseId]];
            } else {
                $subjectCode = subject_generate_code($branchId);
                $stmt = $pdo->prepare(
                    'INSERT INTO college_course_subjects
                     (branch_id,course_id,subject_code,subject_name,subject_type,
                      maximum_marks,pass_marks,mandatory,sort_order,description,status,
                      created_by,created_at,updated_at)
                     VALUES
                     (:branch_id,:course_id,:subject_code,:subject_name,:subject_type,
                      :maximum_marks,:pass_marks,:mandatory,:sort_order,:description,:status,
                      :created_by,NOW(),NOW())'
                );
                $stmt->execute([
                    ':branch_id' => $branchId,
                    ':course_id' => $courseId,
                    ':subject_code' => $subjectCode,
                    ':subject_name' => $clean['subject_name'],
                    ':subject_type' => $clean['subject_type'],
                    ':maximum_marks' => $clean['maximum_marks'],
                    ':pass_marks' => $clean['pass_marks'],
                    ':mandatory' => $clean['mandatory'],
                    ':sort_order' => $clean['sort_order'],
                    ':description' => $clean['description'],
                    ':status' => $clean['status'],
                    ':created_by' => $userId,
                ]);

                $id = (int)$pdo->lastInsertId();
                $createdIds[] = $id;
                $auditRows[] = ['action' => ACTION_CREATE, 'id' => $id, 'old' => null, 'new' => $clean + ['course_id' => $courseId, 'subject_code' => $subjectCode]];
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error('A duplicate Subject / Module or Subject Code already exists.', 409);
        }
        throw $e;
    }

    foreach ($auditRows as $audit) {
        $payload = [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => (int)$audit['id'],
            'new_data' => $audit['new'],
        ];
        if ($audit['old'] !== null) $payload['old_data'] = $audit['old'];
        audit_log($userId, (int)$audit['action'], $payload);
    }

    $createdCount = count($createdIds);
    $updatedCount = count($updatedIds);
    $message = [];
    if ($createdCount > 0) $message[] = $createdCount . ' Subject' . ($createdCount === 1 ? '' : 's') . ' created';
    if ($updatedCount > 0) $message[] = $updatedCount . ' Subject' . ($updatedCount === 1 ? '' : 's') . ' updated';

    json_success(($message ? implode(' and ', $message) : 'Course Subjects saved') . ' successfully.', [
        'created_count' => $createdCount,
        'updated_count' => $updatedCount,
    ]);
}

json_error('Unsupported request.', 404);
