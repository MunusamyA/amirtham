<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Attendance API
 *
 * Attendance Type:
 * 1 = Regular Attendance
 * 2 = Practical Attendance
 *
 * Attendance Code:
 * P  = Present
 * A  = Absent
 * L  = Leave
 * LT = Late
 */

function attendance_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Attendance is available only for tenant users.', 403);
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

function attendance_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $tables = [
        'college_courses',
        'college_batches',
        'college_course_subjects',
        'college_students',
        'college_admissions',
        'college_attendance_sessions',
        'college_student_attendance',
    ];

    foreach ($tables as $table) {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=:table_name'
        );
        $stmt->execute([':table_name' => $table]);

        if ((int)$stmt->fetchColumn() !== 1) {
            json_error(
                'Attendance database setup is incomplete.',
                500,
                ['schema' => 'Missing table: ' . $table . '. Run sql/attendance-upgrade.sql.']
            );
        }
    }

    $required = [
        'college_attendance_sessions' => [
            'id','branch_id','batch_id','attendance_type','subject_id',
            'attendance_date','session_number','topic','status',
            'created_by','created_at','updated_at'
        ],
        'college_student_attendance' => [
            'id','attendance_session_id','branch_id','admission_id',
            'attendance_code','remarks','status','created_by',
            'created_at','updated_at'
        ],
    ];

    foreach ($required as $table => $columns) {
        $marks = implode(',', array_fill(0, count($columns), '?'));

        $stmt = db()->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=?
               AND COLUMN_NAME IN (' . $marks . ')'
        );

        $stmt->execute(array_merge([$table], $columns));

        $found = array_map(
            'strtolower',
            array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')
        );

        $missing = array_values(array_diff($columns, $found));

        if ($missing !== []) {
            json_error(
                'Attendance database structure is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing ' . $table . ' columns: ' .
                        implode(', ', $missing) .
                        '. Run sql/attendance-upgrade.sql.'
                ]
            );
        }
    }

    $checked = true;
}

function attendance_ref_to_id($value, string $field = 'ref'): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Attendance reference is required.',
            422,
            [$field => 'Attendance reference is required.']
        );
    }

    try {
        $id = decryptReference(trim($value), 'college_attendance_session');
    } catch (Throwable $e) {
        json_error(
            'Invalid Attendance reference.',
            422,
            [$field => 'Invalid Attendance reference.']
        );
    }

    if ((int)$id < 1) {
        json_error(
            'Invalid Attendance reference.',
            422,
            [$field => 'Invalid Attendance reference.']
        );
    }

    return (int)$id;
}

function attendance_admission_ref_to_id($value, string $field): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Student Admission reference is required.',
            422,
            [$field => 'Student Admission reference is required.']
        );
    }

    try {
        $id = decryptReference(trim($value), 'college_admission');
    } catch (Throwable $e) {
        json_error(
            'Invalid Student Admission reference.',
            422,
            [$field => 'Invalid Student Admission reference.']
        );
    }

    if ((int)$id < 1) {
        json_error(
            'Invalid Student Admission reference.',
            422,
            [$field => 'Invalid Student Admission reference.']
        );
    }

    return (int)$id;
}

function attendance_valid_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value)
        ? $value
        : null;
}

function attendance_options(int $branchId): array
{
    $courseStmt = db()->prepare(
        'SELECT id,course_code,course_name,status
         FROM college_courses
         WHERE branch_id=:branch_id
         ORDER BY status DESC,course_name,id'
    );
    $courseStmt->execute([':branch_id' => $branchId]);

    $batchStmt = db()->prepare(
        'SELECT id,course_id,batch_code,batch_name,start_date,end_date,status
         FROM college_batches
         WHERE branch_id=:branch_id
         ORDER BY status DESC,start_date DESC,batch_name,id'
    );
    $batchStmt->execute([':branch_id' => $branchId]);

    $subjectStmt = db()->prepare(
        'SELECT id,course_id,subject_code,subject_name,subject_type,status
         FROM college_course_subjects
         WHERE branch_id=:branch_id
           AND subject_type IN (2,3)
         ORDER BY status DESC,course_id,sort_order,subject_name,id'
    );
    $subjectStmt->execute([':branch_id' => $branchId]);

    return [
        'courses' => $courseStmt->fetchAll(PDO::FETCH_ASSOC),
        'batches' => $batchStmt->fetchAll(PDO::FETCH_ASSOC),
        'subjects' => $subjectStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function attendance_batch(int $branchId, int $batchId): ?array
{
    $stmt = db()->prepare(
        'SELECT
            b.id,b.course_id,b.batch_code,b.batch_name,b.start_date,b.end_date,b.status,
            c.course_code,c.course_name,c.status AS course_status
         FROM college_batches b
         INNER JOIN college_courses c
                 ON c.id=b.course_id
                AND c.branch_id=b.branch_id
         WHERE b.id=:batch_id
           AND b.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':batch_id' => $batchId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function attendance_validate_identity(
    int $branchId,
    int $courseId,
    int $batchId,
    int $attendanceType,
    ?int $subjectId,
    string $attendanceDate,
    bool $isNew
): array {
    $errors = [];

    if (!in_array($attendanceType, [1,2], true)) {
        $errors['attendance_type'] = 'Select a valid Attendance Type.';
    }

    $batch = attendance_batch($branchId, $batchId);

    if (!$batch) {
        $errors['batch_id'] = 'Selected Batch is invalid.';
        return [
            'errors' => $errors,
            'batch' => null,
            'subject' => null,
        ];
    }

    if ((int)$batch['course_id'] !== $courseId) {
        $errors['batch_id'] =
            'Selected Batch does not belong to the selected Course.';
    }

    if ($isNew) {
        if ((int)$batch['status'] !== 1) {
            $errors['batch_id'] = 'Select an active Batch.';
        }

        if ((int)$batch['course_status'] !== 1) {
            $errors['course_id'] = 'Select an active Course.';
        }
    }

    if ($attendanceDate < (string)$batch['start_date']) {
        $errors['attendance_date'] =
            'Attendance Date cannot be before Batch Start Date.';
    }

    if (
        !empty($batch['end_date']) &&
        $attendanceDate > (string)$batch['end_date']
    ) {
        $errors['attendance_date'] =
            'Attendance Date cannot be after Batch End Date.';
    }

    $subject = null;

    if ($attendanceType === 2) {
        if (!$subjectId) {
            $errors['subject_id'] =
                'Practical / Subject is required for Practical Attendance.';
        } else {
            $stmt = db()->prepare(
                'SELECT id,course_id,subject_code,subject_name,subject_type,status
                 FROM college_course_subjects
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1'
            );

            $stmt->execute([
                ':id' => $subjectId,
                ':branch_id' => $branchId,
            ]);

            $subject = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                $errors['subject_id'] =
                    'Selected Practical / Subject is invalid.';
            } else {
                if ((int)$subject['course_id'] !== $courseId) {
                    $errors['subject_id'] =
                        'Selected Practical / Subject does not belong to the selected Course.';
                }

                if (
                    !in_array(
                        (int)$subject['subject_type'],
                        [2,3],
                        true
                    )
                ) {
                    $errors['subject_id'] =
                        'Select a Practical or Theory + Practical Subject.';
                }

                if (
                    $isNew &&
                    (int)$subject['status'] !== 1
                ) {
                    $errors['subject_id'] =
                        'Select an active Practical / Subject.';
                }
            }
        }
    }

    return [
        'errors' => $errors,
        'batch' => $batch,
        'subject' => $subject,
    ];
}

function attendance_eligible_students(
    int $branchId,
    int $courseId,
    int $batchId,
    string $attendanceDate
): array {
    $stmt = db()->prepare(
        "SELECT
            a.id AS admission_id,
            a.admission_no,
            s.student_code,
            s.student_name,
            s.mobile
         FROM college_admissions a
         INNER JOIN college_students s
                 ON s.id=a.student_id
                AND s.branch_id=a.branch_id
         WHERE a.branch_id=:branch_id
           AND a.course_id=:course_id
           AND a.batch_id=:batch_id
           AND a.status=1
           AND a.admission_state='active'
           AND s.status=1
           AND a.admission_date<=:attendance_date_from
           AND (
                a.completion_date IS NULL
                OR a.completion_date>=:attendance_date_to
           )
         ORDER BY s.student_name,s.student_code,a.id"
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':course_id' => $courseId,
        ':batch_id' => $batchId,
        ':attendance_date_from' => $attendanceDate,
        ':attendance_date_to' => $attendanceDate,
    ]);

    return array_map(
        static function(array $row): array {
            $row['admission_ref'] =
                encryptReference(
                    'college_admission',
                    (int)$row['admission_id']
                );

            unset($row['admission_id']);
            return $row;
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

function attendance_session_record(
    int $branchId,
    int $sessionId
): ?array {
    $stmt = db()->prepare(
        'SELECT
            ses.id,ses.branch_id,ses.batch_id,ses.attendance_type,
            ses.subject_id,ses.attendance_date,ses.session_number,
            ses.topic,ses.status,
            b.course_id,b.batch_code,b.batch_name,
            c.course_code,c.course_name,
            cs.subject_code,cs.subject_name
         FROM college_attendance_sessions ses
         INNER JOIN college_batches b
                 ON b.id=ses.batch_id
                AND b.branch_id=ses.branch_id
         INNER JOIN college_courses c
                 ON c.id=b.course_id
                AND c.branch_id=b.branch_id
         LEFT JOIN college_course_subjects cs
                ON cs.id=ses.subject_id
               AND cs.branch_id=ses.branch_id
         WHERE ses.id=:id
           AND ses.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $sessionId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return null;

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['batch_id'] = (int)$row['batch_id'];
    $row['course_id'] = (int)$row['course_id'];
    $row['attendance_type'] = (int)$row['attendance_type'];
    $row['subject_id'] =
        $row['subject_id'] === null
            ? null
            : (int)$row['subject_id'];
    $row['session_number'] = (int)$row['session_number'];
    $row['status'] = (int)$row['status'];
    $row['ref'] =
        encryptReference(
            'college_attendance_session',
            (int)$row['id']
        );

    return $row;
}

function attendance_session_students(
    int $branchId,
    int $sessionId
): array {
    $stmt = db()->prepare(
        'SELECT
            sa.admission_id,
            sa.attendance_code,
            sa.remarks,
            a.admission_no,
            s.student_code,
            s.student_name,
            s.mobile
         FROM college_student_attendance sa
         INNER JOIN college_admissions a
                 ON a.id=sa.admission_id
                AND a.branch_id=sa.branch_id
         INNER JOIN college_students s
                 ON s.id=a.student_id
                AND s.branch_id=a.branch_id
         WHERE sa.branch_id=:branch_id
           AND sa.attendance_session_id=:session_id
           AND sa.status=1
         ORDER BY s.student_name,s.student_code,sa.id'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':session_id' => $sessionId,
    ]);

    return array_map(
        static function(array $row): array {
            $row['admission_ref'] =
                encryptReference(
                    'college_admission',
                    (int)$row['admission_id']
                );

            unset($row['admission_id']);
            return $row;
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

function attendance_validate_students(
    array $inputRows,
    array $allowedAdmissionIds
): array {
    $errors = [];
    $rows = [];
    $seen = [];

    foreach ($inputRows as $index => $input) {
        if (!is_array($input)) continue;

        $prefix = 'students[' . $index . ']';

        $admissionId =
            attendance_admission_ref_to_id(
                $input['admission_ref'] ?? '',
                $prefix . '[admission_ref]'
            );

        if (
            !in_array(
                $admissionId,
                $allowedAdmissionIds,
                true
            )
        ) {
            $errors[$prefix . '[admission_ref]'] =
                'Student does not belong to this Attendance Session.';
            continue;
        }

        if (isset($seen[$admissionId])) {
            $errors[$prefix . '[admission_ref]'] =
                'Student is duplicated in Attendance.';
            continue;
        }

        $seen[$admissionId] = true;

        $code =
            strtoupper(
                trim(
                    (string)(
                        $input['attendance_code'] ?? ''
                    )
                )
            );

        if (!in_array($code, ['P','A','L','LT'], true)) {
            $errors[$prefix . '[attendance_code]'] =
                'Select Present, Absent, Leave or Late.';
        }

        $remarks =
            trim(
                (string)(
                    $input['remarks'] ?? ''
                )
            );

        if (mb_strlen($remarks) > 255) {
            $errors[$prefix . '[remarks]'] =
                'Student Remarks cannot exceed 255 characters.';
        }

        $rows[] = [
            'admission_id' => $admissionId,
            'attendance_code' => $code,
            'remarks' => $remarks === '' ? null : $remarks,
        ];
    }

    $submittedIds =
        array_map(
            static fn(array $row): int =>
                (int)$row['admission_id'],
            $rows
        );

    sort($submittedIds);

    $expectedIds = $allowedAdmissionIds;
    sort($expectedIds);

    if ($errors === [] && $submittedIds !== $expectedIds) {
        $errors['students'] =
            'Attendance must be marked for every Student loaded in this session.';
    }

    return [
        'errors' => $errors,
        'rows' => $rows,
    ];
}

function attendance_duplicate_exists(
    int $branchId,
    int $batchId,
    int $attendanceType,
    ?int $subjectId,
    string $attendanceDate,
    int $excludeId = 0
): bool {
    $sql =
        'SELECT id
         FROM college_attendance_sessions
         WHERE branch_id=:branch_id
           AND batch_id=:batch_id
           AND attendance_type=:attendance_type
           AND attendance_date=:attendance_date';

    $params = [
        ':branch_id' => $branchId,
        ':batch_id' => $batchId,
        ':attendance_type' => $attendanceType,
        ':attendance_date' => $attendanceDate,
    ];

    if ($attendanceType === 1) {
        $sql .= ' AND subject_id IS NULL';
    } else {
        $sql .= ' AND subject_id=:subject_id';
        $params[':subject_id'] = $subjectId;
    }

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn() !== false;
}

function attendance_next_session_number(
    PDO $pdo,
    int $branchId,
    int $batchId,
    string $attendanceDate,
    int $attendanceType
): int {
    if ($attendanceType === 1) {
        return 1;
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(session_number),1)
         FROM college_attendance_sessions
         WHERE branch_id=:branch_id
           AND batch_id=:batch_id
           AND attendance_date=:attendance_date'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':batch_id' => $batchId,
        ':attendance_date' => $attendanceDate,
    ]);

    return max(
        2,
        ((int)$stmt->fetchColumn()) + 1
    );
}

function attendance_insert_details(
    PDO $pdo,
    int $branchId,
    int $sessionId,
    int $userId,
    array $rows
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO college_student_attendance
         (
            attendance_session_id,
            branch_id,
            admission_id,
            attendance_code,
            check_in_time,
            remarks,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :attendance_session_id,
            :branch_id,
            :admission_id,
            :attendance_code,
            NULL,
            :remarks,
            1,
            :created_by,
            NOW(),
            NOW()
         )'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            ':attendance_session_id' => $sessionId,
            ':branch_id' => $branchId,
            ':admission_id' => (int)$row['admission_id'],
            ':attendance_code' => (string)$row['attendance_code'],
            ':remarks' => $row['remarks'],
            ':created_by' => $userId,
        ]);
    }
}

attendance_require_schema();

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access =
        require_permission(
            'attendance-form.php',
            ACTION_VIEW
        );

    $ctx =
        attendance_context(
            $access['user']
        );

    json_success(
        'Attendance options loaded.',
        [
            'allowed_actions' => $access['actions'],
            'today' => date('Y-m-d'),
            'options' =>
                attendance_options(
                    (int)$ctx['branch_id']
                ),
        ]
    );
}

if ($method === 'GET' && isset($_GET['students'])) {
    $access =
        require_permission(
            'attendance-form.php',
            ACTION_VIEW
        );

    $ctx =
        attendance_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $courseId =
        (int)($_GET['course_id'] ?? 0);

    $batchId =
        (int)($_GET['batch_id'] ?? 0);

    $date =
        attendance_valid_date(
            $_GET['attendance_date'] ?? ''
        );

    if (
        $courseId < 1 ||
        $batchId < 1 ||
        $date === null
    ) {
        json_error(
            'Course, Batch and Attendance Date are required.',
            422
        );
    }

    $identity =
        attendance_validate_identity(
            $branchId,
            $courseId,
            $batchId,
            1,
            null,
            $date,
            true
        );

    if ($identity['errors'] !== []) {
        json_error(
            'Unable to load Students.',
            422,
            $identity['errors']
        );
    }

    json_success(
        'Students loaded.',
        [
            'students' =>
                attendance_eligible_students(
                    $branchId,
                    $courseId,
                    $batchId,
                    $date
                ),
        ]
    );
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access =
        require_permission(
            'attendance-form.php',
            ACTION_VIEW
        );

    $ctx =
        attendance_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $sessionId =
        attendance_ref_to_id(
            $_GET['ref']
        );

    $session =
        attendance_session_record(
            $branchId,
            $sessionId
        );

    if (!$session) {
        json_error(
            'Attendance record was not found.',
            404
        );
    }

    json_success(
        'Attendance loaded.',
        [
            'allowed_actions' => $access['actions'],
            'session' => $session,
            'students' =>
                attendance_session_students(
                    $branchId,
                    $sessionId
                ),
            'options' =>
                attendance_options(
                    $branchId
                ),
        ]
    );
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access =
        require_permission(
            'attendance-list.php',
            ACTION_VIEW
        );

    $ctx =
        attendance_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $formMenu =
        menu_by_path(
            'attendance-form.php'
        );

    $formActions =
        $formMenu
            ? effective_actions_for_menu(
                $access['user'],
                $formMenu
            )
            : [];

    $draw =
        max(0, (int)($_GET['draw'] ?? 0));

    $start =
        max(0, (int)($_GET['start'] ?? 0));

    $lengthRaw =
        (int)($_GET['length'] ?? 10);

    $length =
        $lengthRaw < 0
            ? 100000
            : max(
                1,
                min(
                    100000,
                    $lengthRaw
                )
            );

    $search =
        trim(
            (string)(
                $_GET['search']['value'] ?? ''
            )
        );

    $type =
        (int)($_GET['attendance_type'] ?? 0);

    $courseId =
        (int)($_GET['course_id'] ?? 0);

    $batchId =
        (int)($_GET['batch_id'] ?? 0);

    $statusText =
        trim(
            (string)(
                $_GET['status'] ?? ''
            )
        );

    $dateFrom =
        attendance_valid_date(
            $_GET['date_from'] ?? ''
        );

    $dateTo =
        attendance_valid_date(
            $_GET['date_to'] ?? ''
        );

    $where = [
        'ses.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' => $branchId
    ];

    if (in_array($type, [1,2], true)) {
        $where[] =
            'ses.attendance_type=:attendance_type';
        $params[':attendance_type'] =
            $type;
    }

    if ($courseId > 0) {
        $where[] =
            'b.course_id=:course_id';
        $params[':course_id'] =
            $courseId;
    }

    if ($batchId > 0) {
        $where[] =
            'ses.batch_id=:batch_id';
        $params[':batch_id'] =
            $batchId;
    }

    if (
        $statusText === '0' ||
        $statusText === '1'
    ) {
        $where[] =
            'ses.status=:status';
        $params[':status'] =
            (int)$statusText;
    }

    if ($dateFrom !== null) {
        $where[] =
            'ses.attendance_date>=:date_from';
        $params[':date_from'] =
            $dateFrom;
    }

    if ($dateTo !== null) {
        $where[] =
            'ses.attendance_date<=:date_to';
        $params[':date_to'] =
            $dateTo;
    }

    if ($search !== '') {
        $where[] =
            '(c.course_code LIKE :search_course_code
              OR c.course_name LIKE :search_course_name
              OR b.batch_code LIKE :search_batch_code
              OR b.batch_name LIKE :search_batch_name
              OR cs.subject_code LIKE :search_subject_code
              OR cs.subject_name LIKE :search_subject_name
              OR ses.topic LIKE :search_topic)';

        $searchValue = '%' . $search . '%';

        $params[':search_course_code'] = $searchValue;
        $params[':search_course_name'] = $searchValue;
        $params[':search_batch_code'] = $searchValue;
        $params[':search_batch_name'] = $searchValue;
        $params[':search_subject_code'] = $searchValue;
        $params[':search_subject_name'] = $searchValue;
        $params[':search_topic'] = $searchValue;
    }

    $whereSql =
        implode(' AND ', $where);

    $baseFrom =
        ' FROM college_attendance_sessions ses
          INNER JOIN college_batches b
                  ON b.id=ses.batch_id
                 AND b.branch_id=ses.branch_id
          INNER JOIN college_courses c
                  ON c.id=b.course_id
                 AND c.branch_id=b.branch_id
          LEFT JOIN college_course_subjects cs
                 ON cs.id=ses.subject_id
                AND cs.branch_id=ses.branch_id';

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_attendance_sessions
         WHERE branch_id=:branch_id'
    );

    $totalStmt->execute([
        ':branch_id' => $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        ' WHERE ' .
        $whereSql
    );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS session_count,
            COALESCE(SUM(x.present_count),0) AS present_count,
            COALESCE(SUM(x.absent_count),0) AS absent_count,
            CASE
                WHEN COALESCE(SUM(x.total_students),0)=0 THEN 0
                ELSE ROUND(
                    (
                        COALESCE(SUM(x.present_count),0) /
                        COALESCE(SUM(x.total_students),0)
                    ) * 100,
                    2
                )
            END AS attendance_percentage
         FROM (
            SELECT
                ses.id,
                COUNT(sa.id) AS total_students,
                SUM(CASE WHEN sa.attendance_code=\'P\' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN sa.attendance_code=\'A\' THEN 1 ELSE 0 END) AS absent_count
            ' .
            $baseFrom .
            ' LEFT JOIN college_student_attendance sa
                     ON sa.attendance_session_id=ses.id
                    AND sa.branch_id=ses.branch_id
                    AND sa.status=1
             WHERE ' .
            $whereSql .
            ' GROUP BY ses.id
         ) x'
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue($key, $value);
    }

    $summaryStmt->execute();
    $summary =
        $summaryStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $orderColumns = [
        'ses.attendance_date',
        'ses.attendance_type',
        'c.course_name',
        'b.batch_name',
        'cs.subject_name',
        'total_students',
        'present_count',
        'absent_count',
        'leave_count',
        'late_count',
        'attendance_percentage',
        'ses.status',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column'] ?? 0
        );

    $orderDir =
        strtolower(
            (string)(
                $_GET['order'][0]['dir'] ?? 'desc'
            )
        ) === 'asc'
            ? 'ASC'
            : 'DESC';

    $orderColumn =
        $orderColumns[$orderIndex] ??
        'ses.attendance_date';

    $sql =
        'SELECT
            ses.id,
            ses.attendance_date,
            ses.attendance_type,
            ses.subject_id,
            ses.status,
            c.course_code,
            c.course_name,
            b.batch_code,
            b.batch_name,
            cs.subject_code,
            cs.subject_name,
            COUNT(sa.id) AS total_students,
            SUM(CASE WHEN sa.attendance_code=\'P\' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN sa.attendance_code=\'A\' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN sa.attendance_code=\'L\' THEN 1 ELSE 0 END) AS leave_count,
            SUM(CASE WHEN sa.attendance_code=\'LT\' THEN 1 ELSE 0 END) AS late_count,
            CASE
                WHEN COUNT(sa.id)=0 THEN 0
                ELSE ROUND(
                    (
                        SUM(
                            CASE
                                WHEN sa.attendance_code=\'P\'
                                THEN 1 ELSE 0
                            END
                        ) / COUNT(sa.id)
                    ) * 100,
                    2
                )
            END AS attendance_percentage
         ' .
        $baseFrom .
        ' LEFT JOIN college_student_attendance sa
                 ON sa.attendance_session_id=ses.id
                AND sa.branch_id=ses.branch_id
                AND sa.status=1
         WHERE ' .
        $whereSql .
        ' GROUP BY
            ses.id,
            ses.attendance_date,
            ses.attendance_type,
            ses.subject_id,
            ses.status,
            c.course_code,
            c.course_name,
            b.batch_code,
            b.batch_name,
            cs.subject_code,
            cs.subject_name
          ORDER BY ' .
        $orderColumn .
        ' ' .
        $orderDir .
        ',ses.id ' .
        $orderDir .
        ' LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(
        ':start',
        $start,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':length',
        $length,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $data = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref =
            encryptReference(
                'college_attendance_session',
                (int)$row['id']
            );

        $data[] = [
            'ref' => $ref,
            'attendance_date' => (string)$row['attendance_date'],
            'attendance_type' => (int)$row['attendance_type'],
            'attendance_type_label' =>
                (int)$row['attendance_type'] === 2
                    ? 'Practical'
                    : 'Regular',
            'course_label' =>
                (string)$row['course_code'] .
                ' - ' .
                (string)$row['course_name'],
            'batch_label' =>
                (string)$row['batch_code'] .
                ' - ' .
                (string)$row['batch_name'],
            'subject_label' =>
                $row['subject_id'] === null
                    ? '-'
                    : (
                        (string)$row['subject_code'] .
                        ' - ' .
                        (string)$row['subject_name']
                    ),
            'total_students' => (int)$row['total_students'],
            'present_count' => (int)$row['present_count'],
            'absent_count' => (int)$row['absent_count'],
            'leave_count' => (int)$row['leave_count'],
            'late_count' => (int)$row['late_count'],
            'attendance_percentage' =>
                round(
                    (float)$row['attendance_percentage'],
                    2
                ),
            'status' => (int)$row['status'],
            'view_url' =>
                'attendance-form.php?ref=' .
                rawurlencode($ref) .
                '&view=1',
            'edit_url' =>
                'attendance-form.php?ref=' .
                rawurlencode($ref),
        ];
    }

    json_success(
        'Attendance list loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ],
            'summary' => [
                'session_count' =>
                    (int)($summary['session_count'] ?? 0),

                'present_count' =>
                    (int)($summary['present_count'] ?? 0),

                'absent_count' =>
                    (int)($summary['absent_count'] ?? 0),

                'attendance_percentage' =>
                    round(
                        (float)(
                            $summary['attendance_percentage'] ??
                            0
                        ),
                        2
                    ),
            ],
            'list_actions' => $access['actions'],
            'form_actions' => $formActions,
            'options' => attendance_options($branchId),
        ]
    );
}

if ($method === 'POST') {
    $input = request_data();

    $action =
        strtolower(
            trim(
                (string)(
                    $input['action'] ?? 'save'
                )
            )
        );

    if ($action === 'delete') {
        $access =
            require_permission(
                'attendance-form.php',
                4
            );

        $ctx =
            attendance_context(
                $access['user']
            );

        $branchId =
            (int)$ctx['branch_id'];

        $sessionId =
            attendance_ref_to_id(
                $input['ref'] ?? ''
            );

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM college_attendance_sessions
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $stmt->execute([
                ':id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            if (!$stmt->fetchColumn()) {
                throw new DomainException(
                    'Attendance record was not found.'
                );
            }

            $pdo->prepare(
                'DELETE FROM college_student_attendance
                 WHERE attendance_session_id=:session_id
                   AND branch_id=:branch_id'
            )->execute([
                ':session_id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            $pdo->prepare(
                'DELETE FROM college_attendance_sessions
                 WHERE id=:id
                   AND branch_id=:branch_id'
            )->execute([
                ':id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof DomainException) {
                json_error($e->getMessage(), 409);
            }

            throw $e;
        }

        json_success('Attendance deleted successfully.');
    }

    if ($action !== 'save') {
        json_error('Unsupported action.', 405);
    }

    $ref =
        trim(
            (string)(
                $input['ref'] ?? ''
            )
        );

    $sessionId =
        $ref === ''
            ? 0
            : attendance_ref_to_id($ref);

    $access =
        require_permission(
            'attendance-form.php',
            $sessionId > 0
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $ctx =
        attendance_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $attendanceType =
        (int)($input['attendance_type'] ?? 0);

    $courseId =
        (int)($input['course_id'] ?? 0);

    $batchId =
        (int)($input['batch_id'] ?? 0);

    $subjectText =
        trim(
            (string)(
                $input['subject_id'] ?? ''
            )
        );

    $subjectId =
        $subjectText === ''
            ? null
            : (int)$subjectText;

    $attendanceDate =
        attendance_valid_date(
            $input['attendance_date'] ?? ''
        );

    $topic =
        trim(
            (string)(
                $input['remarks'] ?? ''
            )
        );

    $status =
        (int)($input['status'] ?? 1);

    $errors = [];

    if ($courseId < 1) {
        $errors['course_id'] = 'Course is required.';
    }

    if ($batchId < 1) {
        $errors['batch_id'] = 'Batch is required.';
    }

    if ($attendanceDate === null) {
        $errors['attendance_date'] =
            'Enter a valid Attendance Date.';
    }

    if (mb_strlen($topic) > 255) {
        $errors['remarks'] =
            'Remarks cannot exceed 255 characters.';
    }

    if (!in_array($status, [0,1], true)) {
        $errors['status'] =
            'Select a valid Status.';
    }

    if ($errors !== []) {
        json_error(
            'Attendance validation failed.',
            422,
            $errors
        );
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $oldSession = null;

        if ($sessionId > 0) {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM college_attendance_sessions
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $stmt->execute([
                ':id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            $oldSession =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldSession) {
                throw new DomainException(
                    'Attendance record was not found.'
                );
            }

            $oldBatch =
                attendance_batch(
                    $branchId,
                    (int)$oldSession['batch_id']
                );

            if (!$oldBatch) {
                throw new DomainException(
                    'Attendance Batch is no longer available.'
                );
            }

            $oldSubjectId =
                $oldSession['subject_id'] === null
                    ? null
                    : (int)$oldSession['subject_id'];

            if (
                $attendanceType !==
                    (int)$oldSession['attendance_type'] ||
                $courseId !==
                    (int)$oldBatch['course_id'] ||
                $batchId !==
                    (int)$oldSession['batch_id'] ||
                $attendanceDate !==
                    (string)$oldSession['attendance_date'] ||
                (
                    $attendanceType === 2 &&
                    $subjectId !== $oldSubjectId
                )
            ) {
                throw new DomainException(
                    'Attendance Type, Course, Batch, Date and Practical / Subject cannot be changed after Attendance is saved.'
                );
            }
        }

        $identity =
            attendance_validate_identity(
                $branchId,
                $courseId,
                $batchId,
                $attendanceType,
                $subjectId,
                $attendanceDate,
                $sessionId === 0
            );

        if ($identity['errors'] !== []) {
            $pdo->rollBack();

            json_error(
                'Attendance validation failed.',
                422,
                $identity['errors']
            );
        }

        $batchLock =
            $pdo->prepare(
                'SELECT id
                 FROM college_batches
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );

        $batchLock->execute([
            ':id' => $batchId,
            ':branch_id' => $branchId,
        ]);

        if (!$batchLock->fetchColumn()) {
            throw new DomainException(
                'Selected Batch is unavailable.'
            );
        }

        if (
            attendance_duplicate_exists(
                $branchId,
                $batchId,
                $attendanceType,
                $subjectId,
                $attendanceDate,
                $sessionId
            )
        ) {
            throw new DomainException(
                $attendanceType === 2
                    ? 'Practical Attendance already exists for this Batch, Subject and Date.'
                    : 'Regular Attendance already exists for this Batch and Date.'
            );
        }

        if ($sessionId === 0) {
            $eligible =
                attendance_eligible_students(
                    $branchId,
                    $courseId,
                    $batchId,
                    $attendanceDate
                );

            if ($eligible === []) {
                throw new DomainException(
                    'No active admitted Students are available for this Batch and Date.'
                );
            }

            $allowedIds = [];

            foreach ($eligible as $student) {
                $allowedIds[] =
                    attendance_admission_ref_to_id(
                        $student['admission_ref'],
                        'students'
                    );
            }
        } else {
            $stmt = $pdo->prepare(
                'SELECT admission_id
                 FROM college_student_attendance
                 WHERE attendance_session_id=:session_id
                   AND branch_id=:branch_id
                   AND status=1
                 ORDER BY admission_id
                 FOR UPDATE'
            );

            $stmt->execute([
                ':session_id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            $allowedIds =
                array_map(
                    'intval',
                    $stmt->fetchAll(PDO::FETCH_COLUMN)
                );

            if ($allowedIds === []) {
                throw new DomainException(
                    'This Attendance Session has no Student records.'
                );
            }
        }

        $studentInput =
            is_array(
                $input['students'] ?? null
            )
                ? $input['students']
                : [];

        $studentValidation =
            attendance_validate_students(
                $studentInput,
                $allowedIds
            );

        if ($studentValidation['errors'] !== []) {
            $pdo->rollBack();

            json_error(
                'Student Attendance validation failed.',
                422,
                $studentValidation['errors']
            );
        }

        if ($sessionId === 0) {
            $sessionNumber =
                attendance_next_session_number(
                    $pdo,
                    $branchId,
                    $batchId,
                    $attendanceDate,
                    $attendanceType
                );

            $stmt = $pdo->prepare(
                'INSERT INTO college_attendance_sessions
                 (
                    branch_id,
                    batch_id,
                    attendance_type,
                    subject_id,
                    attendance_date,
                    session_number,
                    start_time,
                    end_time,
                    faculty_user_id,
                    topic,
                    status,
                    created_by,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    :branch_id,
                    :batch_id,
                    :attendance_type,
                    :subject_id,
                    :attendance_date,
                    :session_number,
                    NULL,
                    NULL,
                    NULL,
                    :topic,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                 )'
            );

            $stmt->execute([
                ':branch_id' => $branchId,
                ':batch_id' => $batchId,
                ':attendance_type' => $attendanceType,
                ':subject_id' =>
                    $attendanceType === 2
                        ? $subjectId
                        : null,
                ':attendance_date' => $attendanceDate,
                ':session_number' => $sessionNumber,
                ':topic' => $topic === '' ? null : $topic,
                ':status' => $status,
                ':created_by' => $userId,
            ]);

            $sessionId =
                (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare(
                'UPDATE college_attendance_sessions
                 SET topic=:topic,
                     status=:status,
                     updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

            $stmt->execute([
                ':topic' => $topic === '' ? null : $topic,
                ':status' => $status,
                ':id' => $sessionId,
                ':branch_id' => $branchId,
            ]);

            $pdo->prepare(
                'DELETE FROM college_student_attendance
                 WHERE attendance_session_id=:session_id
                   AND branch_id=:branch_id'
            )->execute([
                ':session_id' => $sessionId,
                ':branch_id' => $branchId,
            ]);
        }

        attendance_insert_details(
            $pdo,
            $branchId,
            $sessionId,
            $userId,
            $studentValidation['rows']
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof DomainException) {
            json_error($e->getMessage(), 409);
        }

        if (
            $e instanceof PDOException &&
            $e->getCode() === '23000'
        ) {
            json_error(
                'Attendance could not be saved because a duplicate or linked value already exists.',
                409
            );
        }

        throw $e;
    }

    json_success(
        $ref === ''
            ? 'Attendance saved successfully.'
            : 'Attendance updated successfully.',
        [
            'ref' =>
                encryptReference(
                    'college_attendance_session',
                    $sessionId
                ),
        ]
    );
}

json_error('Unsupported request.', 405);
