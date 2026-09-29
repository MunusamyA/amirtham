<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/college-fee-payment-service.php';

/**
 * AMIRTHAM - Community College Student Admission API
 *
 * GET  ?options=1      Form options
 * GET  ?ref=<ref>      Single Admission for View/Edit
 * GET  ?datatable=1    Admission DataTable
 * POST action=save     Admission + Fee Plan + Admission Payment
 * POST action=delete   Delete when no downstream activity exists
 */

function admission_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Student Admission is available only for tenant users.', 403);
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

function admission_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $tables = [
        'college_students',
        'college_courses',
        'college_batches',
        'college_admissions',
        'college_fee_structures',
        'college_fee_structure_items',
        'college_student_fee_plans',
        'college_student_fee_plan_items',
        'college_fee_installments',
        'college_fee_receipts',
        'college_fee_receipt_allocations',
        'college_fee_receipt_payment_details',
        'college_student_attendance',
        'accounts',
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
                'Student Admission database setup is incomplete.',
                500,
                ['schema' => 'Missing table: ' . $table . '. Run sql/student-admission-upgrade.sql.']
            );
        }
    }

    $required = [
        'college_admissions' => [
            'id','branch_id','admission_no','student_id','course_id','batch_id',
            'admission_date','completion_date','admission_state','remarks','status',
            'created_by','created_at','updated_at'
        ],
        'college_student_fee_plans' => [
            'id','branch_id','admission_id','fee_structure_id','plan_date',
            'gross_amount','discount_amount','scholarship_amount','late_fee_amount',
            'net_payable','installment_count','payment_type','first_due_date',
            'notes','status','created_by','created_at','updated_at'
        ],
        'college_student_fee_plan_items' => [
            'id','student_fee_plan_id','branch_id','source_fee_structure_item_id',
            'fee_head_snapshot','amount','mandatory_snapshot','sort_order','status',
            'created_by','created_at','updated_at'
        ],
        'college_fee_receipts' => [
            'id','branch_id','receipt_no','admission_id','student_fee_plan_id',
            'receipt_date','amount','receipt_type','payment_against',
            'target_installment_id','payment_mode','payment_reference','notes',
            'posting_status','reversed_at','reversed_by','status','created_by',
            'created_at','updated_at'
        ],
        'college_fee_receipt_payment_details' => [
            'id','fee_receipt_id','branch_id','payment_mode','account_id','amount',
            'reference_no','cheque_no','cheque_date','status','created_by',
            'created_at','updated_at'
        ],
    ];

    foreach ($required as $table => $columns) {
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = db()->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=?
               AND COLUMN_NAME IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$table], $columns));

        $found = array_map(
            'strtolower',
            array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')
        );
        $missing = array_values(array_diff($columns, $found));

        if ($missing !== []) {
            json_error(
                'Student Admission database structure is incomplete.',
                500,
                [
                    'schema' => 'Missing ' . $table . ' columns: ' .
                        implode(', ', $missing) .
                        '. Run sql/student-admission-upgrade.sql.'
                ]
            );
        }
    }

    $checked = true;
}

function admission_ref_to_id($value, string $field = 'ref'): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Admission reference is required.', 422, [$field => 'Admission reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'college_admission');
    } catch (Throwable $e) {
        json_error('Invalid Admission reference.', 422, [$field => 'Invalid Admission reference.']);
    }

    return 0;
}

function admission_valid_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return null;
    return $value;
}

function admission_decimal($value, bool $allowZero = true): float|false
{
    $text = trim((string)$value);
    if ($text === '') $text = '0';

    if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $text)) {
        return false;
    }

    $number = round((float)$text, 2);
    if ($number < 0 || (!$allowZero && $number <= 0)) return false;
    return $number;
}

function admission_next_number(PDO $pdo, int $branchId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(admission_no,4) AS UNSIGNED)),0)
         FROM college_admissions
         WHERE branch_id=:branch_id
           AND admission_no REGEXP '^ADM[0-9]+$'"
    );
    $stmt->execute([':branch_id' => $branchId]);
    return ((int)$stmt->fetchColumn()) + 1;
}

function admission_code(int $number): string
{
    return 'ADM' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function admission_next_receipt_number(PDO $pdo, int $branchId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(receipt_no,4) AS UNSIGNED)),0)
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND receipt_no REGEXP '^FRC[0-9]+$'"
    );
    $stmt->execute([':branch_id' => $branchId]);
    return ((int)$stmt->fetchColumn()) + 1;
}

function admission_receipt_code(int $number): string
{
    return 'FRC' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}


function admission_next_student_number(PDO $pdo, int $branchId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(student_code,4) AS UNSIGNED)),0)
         FROM college_students
         WHERE branch_id=:branch_id
           AND student_code REGEXP '^STU[0-9]+$'"
    );
    $stmt->execute([':branch_id' => $branchId]);

    return ((int)$stmt->fetchColumn()) + 1;
}

function admission_student_code(int $number): string
{
    return 'STU' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function admission_validate_new_student(array $input, int $branchId): array
{
    $errors = [];

    $name = trim((string)($input['student_name'] ?? ''));
    if ($name === '') {
        $errors['student_name'] = 'Student Name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors['student_name'] = 'Student Name cannot exceed 150 characters.';
    }

    $mobile = preg_replace('/\D+/', '', (string)($input['mobile'] ?? ''));
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors['student_mobile'] = 'Enter a valid 10 digit Mobile Number.';
    }

    $dobText = trim((string)($input['date_of_birth'] ?? ''));
    $dob = $dobText === '' ? null : admission_valid_date($dobText);
    if ($dobText !== '' && $dob === null) {
        $errors['student_dob'] = 'Enter a valid Date of Birth.';
    }

    $gender = trim((string)($input['gender'] ?? ''));
    if ($gender !== '' && !in_array($gender, ['Male','Female','Other'], true)) {
        $errors['student_gender'] = 'Select a valid Gender.';
    }

    $email = trim((string)($input['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['student_email'] = 'Enter a valid Email Address.';
    } elseif (mb_strlen($email) > 190) {
        $errors['student_email'] = 'Email cannot exceed 190 characters.';
    }

    $address = trim((string)($input['address'] ?? ''));
    $guardianName = trim((string)($input['guardian_name'] ?? ''));
    $guardianMobile = preg_replace('/\D+/', '', (string)($input['guardian_mobile'] ?? ''));
    $qualification = trim((string)($input['qualification'] ?? ''));
    $identityNumber = trim((string)($input['identity_number'] ?? ''));

    if (mb_strlen($guardianName) > 150) {
        $errors['guardian_name'] = 'Guardian Name cannot exceed 150 characters.';
    }
    if ($guardianMobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $guardianMobile)) {
        $errors['guardian_mobile'] = 'Enter a valid 10 digit Guardian Mobile Number.';
    }
    if (mb_strlen($qualification) > 150) {
        $errors['qualification'] = 'Qualification cannot exceed 150 characters.';
    }
    if (mb_strlen($identityNumber) > 100) {
        $errors['identity_number'] = 'Identity Number cannot exceed 100 characters.';
    }

    if ($errors === [] && $name !== '' && $mobile !== '') {
        $stmt = db()->prepare(
            'SELECT student_code
             FROM college_students
             WHERE branch_id=:branch_id
               AND LOWER(TRIM(student_name))=LOWER(:student_name)
               AND mobile=:mobile
             LIMIT 1'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':student_name' => $name,
            ':mobile' => $mobile,
        ]);

        $existingCode = $stmt->fetchColumn();
        if ($existingCode !== false) {
            $errors['student_name'] =
                'Student already exists (' . (string)$existingCode .
                '). Choose Existing Student.';
        }
    }

    return [
        'errors' => $errors,
        'data' => [
            'student_name' => $name,
            'date_of_birth' => $dob,
            'gender' => $gender === '' ? null : $gender,
            'mobile' => $mobile === '' ? null : $mobile,
            'email' => $email === '' ? null : $email,
            'address' => $address === '' ? null : $address,
            'guardian_name' => $guardianName === '' ? null : $guardianName,
            'guardian_mobile' => $guardianMobile === '' ? null : $guardianMobile,
            'qualification' => $qualification === '' ? null : $qualification,
            'identity_number' => $identityNumber === '' ? null : $identityNumber,
        ],
    ];
}

function admission_create_student(
    PDO $pdo,
    int $branchId,
    int $userId,
    array $student
): int {
    if (!empty($student['mobile'])) {
        $stmt = $pdo->prepare(
            'SELECT student_code
             FROM college_students
             WHERE branch_id=:branch_id
               AND LOWER(TRIM(student_name))=LOWER(:student_name)
               AND mobile=:mobile
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':student_name' => $student['student_name'],
            ':mobile' => $student['mobile'],
        ]);

        $existingCode = $stmt->fetchColumn();
        if ($existingCode !== false) {
            throw new DomainException(
                'Student already exists (' . (string)$existingCode .
                '). Choose Existing Student.'
            );
        }
    }

    $studentCode = admission_student_code(
        admission_next_student_number($pdo, $branchId)
    );

    $stmt = $pdo->prepare(
        'INSERT INTO college_students
         (
            branch_id,user_id,student_code,student_name,date_of_birth,gender,
            mobile,email,address,guardian_name,guardian_mobile,qualification,
            identity_number,media_files,status,created_by,created_at,updated_at
         )
         VALUES
         (
            :branch_id,NULL,:student_code,:student_name,:date_of_birth,:gender,
            :mobile,:email,:address,:guardian_name,:guardian_mobile,:qualification,
            :identity_number,NULL,1,:created_by,NOW(),NOW()
         )'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':student_code' => $studentCode,
        ':student_name' => $student['student_name'],
        ':date_of_birth' => $student['date_of_birth'],
        ':gender' => $student['gender'],
        ':mobile' => $student['mobile'],
        ':email' => $student['email'],
        ':address' => $student['address'],
        ':guardian_name' => $student['guardian_name'],
        ':guardian_mobile' => $student['guardian_mobile'],
        ':qualification' => $student['qualification'],
        ':identity_number' => $student['identity_number'],
        ':created_by' => $userId,
    ]);

    return (int)$pdo->lastInsertId();
}

function admission_student_options(int $branchId, array $includeIds = []): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), fn($id) => $id > 0)));

    if ($includeIds === []) {
        $stmt = db()->prepare(
            'SELECT id,student_code,student_name,mobile,status
             FROM college_students
             WHERE branch_id=:branch_id AND status=1
             ORDER BY student_name,student_code'
        );
        $stmt->execute([':branch_id' => $branchId]);
    } else {
        $marks = implode(',', array_fill(0, count($includeIds), '?'));
        $stmt = db()->prepare(
            'SELECT id,student_code,student_name,mobile,status
             FROM college_students
             WHERE branch_id=? AND (status=1 OR id IN (' . $marks . '))
             ORDER BY student_name,student_code'
        );
        $stmt->execute(array_merge([$branchId], $includeIds));
    }

    return array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'status' => (int)$row['status'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admission_course_options(int $branchId, array $includeIds = []): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), fn($id) => $id > 0)));

    if ($includeIds === []) {
        $stmt = db()->prepare(
            'SELECT id,course_code,course_name,maximum_students,status
             FROM college_courses
             WHERE branch_id=:branch_id AND status=1
             ORDER BY course_name,course_code'
        );
        $stmt->execute([':branch_id' => $branchId]);
    } else {
        $marks = implode(',', array_fill(0, count($includeIds), '?'));
        $stmt = db()->prepare(
            'SELECT id,course_code,course_name,maximum_students,status
             FROM college_courses
             WHERE branch_id=? AND (status=1 OR id IN (' . $marks . '))
             ORDER BY course_name,course_code'
        );
        $stmt->execute(array_merge([$branchId], $includeIds));
    }

    return array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'course_code' => (string)$row['course_code'],
            'course_name' => (string)$row['course_name'],
            'maximum_students' => $row['maximum_students'] === null ? null : (int)$row['maximum_students'],
            'status' => (int)$row['status'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admission_batch_options(int $branchId, array $includeIds = []): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), fn($id) => $id > 0)));

    if ($includeIds === []) {
        $stmt = db()->prepare(
            'SELECT id,course_id,batch_code,batch_name,start_date,end_date,maximum_students,status
             FROM college_batches
             WHERE branch_id=:branch_id AND status=1
             ORDER BY start_date DESC,batch_name'
        );
        $stmt->execute([':branch_id' => $branchId]);
    } else {
        $marks = implode(',', array_fill(0, count($includeIds), '?'));
        $stmt = db()->prepare(
            'SELECT id,course_id,batch_code,batch_name,start_date,end_date,maximum_students,status
             FROM college_batches
             WHERE branch_id=? AND (status=1 OR id IN (' . $marks . '))
             ORDER BY start_date DESC,batch_name'
        );
        $stmt->execute(array_merge([$branchId], $includeIds));
    }

    return array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'course_id' => (int)$row['course_id'],
            'batch_code' => (string)$row['batch_code'],
            'batch_name' => (string)$row['batch_name'],
            'start_date' => (string)$row['start_date'],
            'end_date' => (string)($row['end_date'] ?? ''),
            'maximum_students' => $row['maximum_students'] === null ? null : (int)$row['maximum_students'],
            'status' => (int)$row['status'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admission_fee_options(int $branchId, array $includeStructureIds = []): array
{
    $includeStructureIds = array_values(array_unique(array_filter(array_map('intval', $includeStructureIds), fn($id) => $id > 0)));

    if ($includeStructureIds === []) {
        $stmt = db()->prepare(
            'SELECT id,course_id,structure_code,structure_name,effective_from,total_amount,
                    default_installment_count,status
             FROM college_fee_structures
             WHERE branch_id=:branch_id AND status=1
             ORDER BY course_id,effective_from DESC,structure_name'
        );
        $stmt->execute([':branch_id' => $branchId]);
    } else {
        $marks = implode(',', array_fill(0, count($includeStructureIds), '?'));
        $stmt = db()->prepare(
            'SELECT id,course_id,structure_code,structure_name,effective_from,total_amount,
                    default_installment_count,status
             FROM college_fee_structures
             WHERE branch_id=? AND (status=1 OR id IN (' . $marks . '))
             ORDER BY course_id,effective_from DESC,structure_name'
        );
        $stmt->execute(array_merge([$branchId], $includeStructureIds));
    }

    $structures = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'course_id' => (int)$row['course_id'],
            'structure_code' => (string)$row['structure_code'],
            'structure_name' => (string)$row['structure_name'],
            'effective_from' => (string)$row['effective_from'],
            'total_amount' => (float)$row['total_amount'],
            'default_installment_count' => (int)$row['default_installment_count'],
            'status' => (int)$row['status'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $itemStmt = db()->prepare(
        'SELECT id,fee_structure_id,fee_head,amount,mandatory,sort_order,status
         FROM college_fee_structure_items
         WHERE branch_id=:branch_id AND status=1
         ORDER BY fee_structure_id,sort_order,id'
    );
    $itemStmt->execute([':branch_id' => $branchId]);

    $items = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'fee_structure_id' => (int)$row['fee_structure_id'],
            'fee_head' => (string)$row['fee_head'],
            'amount' => (float)$row['amount'],
            'mandatory' => (int)$row['mandatory'],
            'sort_order' => (int)$row['sort_order'],
            'status' => (int)$row['status'],
        ];
    }, $itemStmt->fetchAll(PDO::FETCH_ASSOC));

    return ['structures' => $structures, 'items' => $items];
}

function admission_account_options(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,bank_name,account_number,upi_id
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    return array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => (int)$row['account_type'],
            'bank_name' => (string)($row['bank_name'] ?? ''),
            'account_number' => (string)($row['account_number'] ?? ''),
            'upi_id' => (string)($row['upi_id'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admission_record(array $ctx, int $id): array
{
    $stmt = db()->prepare(
        'SELECT a.*,s.student_code,s.student_name,s.mobile,s.date_of_birth,s.gender,s.email,
                s.address,s.guardian_name,s.guardian_mobile,s.qualification,s.identity_number,
                c.course_code,c.course_name,
                b.batch_code,b.batch_name,b.start_date AS batch_start_date,b.end_date AS batch_end_date,
                u.name AS created_by_name
         FROM college_admissions a
         INNER JOIN college_students s ON s.id=a.student_id AND s.branch_id=a.branch_id
         INNER JOIN college_courses c ON c.id=a.course_id AND c.branch_id=a.branch_id
         INNER JOIN college_batches b ON b.id=a.batch_id AND b.branch_id=a.branch_id
         LEFT JOIN users u ON u.id=a.created_by
         WHERE a.id=:id AND a.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_error('Admission was not found.', 404);

    $row['id'] = (int)$row['id'];
    $row['student_id'] = (int)$row['student_id'];
    $row['course_id'] = (int)$row['course_id'];
    $row['batch_id'] = (int)$row['batch_id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('college_admission', (int)$row['id']);
    return $row;
}

function admission_fee_plan(int $branchId, int $admissionId): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM college_student_fee_plans
         WHERE branch_id=:branch_id AND admission_id=:admission_id
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId, ':admission_id' => $admissionId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) return null;

    $plan['id'] = (int)$plan['id'];
    $plan['fee_structure_id'] = $plan['fee_structure_id'] === null ? null : (int)$plan['fee_structure_id'];
    $plan['gross_amount'] = (float)$plan['gross_amount'];
    $plan['net_payable'] = (float)$plan['net_payable'];
    $plan['installment_count'] = (int)$plan['installment_count'];

    $itemStmt = db()->prepare(
        'SELECT *
         FROM college_student_fee_plan_items
         WHERE branch_id=:branch_id AND student_fee_plan_id=:plan_id AND status=1
         ORDER BY sort_order,id'
    );
    $itemStmt->execute([':branch_id' => $branchId, ':plan_id' => (int)$plan['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['source_fee_structure_item_id'] = $item['source_fee_structure_item_id'] === null
            ? null : (int)$item['source_fee_structure_item_id'];
        $item['amount'] = (float)$item['amount'];
        $item['mandatory_snapshot'] = (int)$item['mandatory_snapshot'];
        $item['sort_order'] = (int)$item['sort_order'];
    }
    unset($item);

    $installmentStmt = db()->prepare(
        'SELECT *
         FROM college_fee_installments
         WHERE branch_id=:branch_id AND student_fee_plan_id=:plan_id AND status=1
         ORDER BY installment_number'
    );
    $installmentStmt->execute([':branch_id' => $branchId, ':plan_id' => (int)$plan['id']]);
    $installments = $installmentStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($installments as &$installment) {
        $installment['id'] = (int)$installment['id'];
        $installment['installment_number'] = (int)$installment['installment_number'];
        $installment['due_amount'] = (float)$installment['due_amount'];
        $installment['waived_amount'] = (float)$installment['waived_amount'];
    }
    unset($installment);

    $plan['items'] = $items;
    $plan['installments'] = $installments;
    return $plan;
}

function admission_receipt(int $branchId, int $admissionId, int $planId): ?array
{
    $stmt = db()->prepare(
        "SELECT *
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id
           AND receipt_type='admission'
           AND status=1
         ORDER BY id
         LIMIT 1"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId,
    ]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) return null;

    $receipt['id'] = (int)$receipt['id'];
    $receipt['ref'] = encryptReference(
        'college_fee_receipt',
        (int)$receipt['id']
    );
    $receipt['amount'] = (float)$receipt['amount'];

    $detailStmt = db()->prepare(
        'SELECT d.*,a.account_code,a.account_name,a.account_type
         FROM college_fee_receipt_payment_details d
         INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
         WHERE d.branch_id=:branch_id AND d.fee_receipt_id=:receipt_id AND d.status=1
         ORDER BY d.payment_mode'
    );
    $detailStmt->execute([':branch_id' => $branchId, ':receipt_id' => (int)$receipt['id']]);
    $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($details as &$detail) {
        $detail['id'] = (int)$detail['id'];
        $detail['payment_mode'] = (int)$detail['payment_mode'];
        $detail['account_id'] = (int)$detail['account_id'];
        $detail['amount'] = (float)$detail['amount'];
    }
    unset($detail);

    $receipt['payment_details'] = $details;
    return $receipt;
}

function admission_usage(int $branchId, int $admissionId, ?int $admissionReceiptId = null): array
{
    $attendance = db()->prepare(
        'SELECT COUNT(*)
         FROM college_student_attendance
         WHERE branch_id=:branch_id AND admission_id=:admission_id AND status=1'
    );
    $attendance->execute([':branch_id' => $branchId, ':admission_id' => $admissionId]);

    $sql =
        'SELECT COUNT(*)
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND status=1';
    $params = [':branch_id' => $branchId, ':admission_id' => $admissionId];

    if ($admissionReceiptId !== null) {
        $sql .= ' AND id<>:admission_receipt_id';
        $params[':admission_receipt_id'] = $admissionReceiptId;
    }

    $later = db()->prepare($sql);
    $later->execute($params);

    return [
        'attendance_count' => (int)$attendance->fetchColumn(),
        'later_receipt_count' => (int)$later->fetchColumn(),
    ];
}

function admission_validate_account(int $branchId, int $accountId, int $paymentMode): bool
{
    $stmt = db()->prepare(
        'SELECT account_type
         FROM accounts
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $type = $stmt->fetchColumn();
    if ($type === false) return false;

    return $paymentMode === 1 ? (int)$type === 1 : (int)$type === 2;
}

function admission_validate_payload(
    array $input,
    int $branchId,
    ?array $oldAdmission,
    ?array $oldPlan
): array {
    $errors = [];

    $studentMode = strtolower(trim((string)($input['student_mode'] ?? 'new')));
    if (!in_array($studentMode, ['new','existing'], true)) {
        $studentMode = 'new';
    }

    if ($oldAdmission) {
        $studentMode = 'existing';
    }

    $studentId = 0;
    $newStudent = null;

    if ($studentMode === 'existing') {
        $studentText = trim((string)($input['student_id'] ?? ''));
        $studentId = preg_match('/^[1-9][0-9]*$/', $studentText)
            ? (int)$studentText
            : 0;

        if ($studentId < 1) {
            $errors['student_id'] = 'Existing Student is required.';
        } else {
            $stmt = db()->prepare(
                'SELECT id,status
                 FROM college_students
                 WHERE id=:id AND branch_id=:branch_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':id' => $studentId,
                ':branch_id' => $branchId
            ]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $errors['student_id'] = 'Selected Student is invalid.';
            } elseif (
                (int)$student['status'] !== 1 &&
                (!$oldAdmission || (int)$oldAdmission['student_id'] !== $studentId)
            ) {
                $errors['student_id'] = 'Select an active Student.';
            }
        }
    } else {
        $studentValidation = admission_validate_new_student(
            is_array($input['new_student'] ?? null)
                ? $input['new_student']
                : [],
            $branchId
        );

        if ($studentValidation['errors'] !== []) {
            $errors = array_merge($errors, $studentValidation['errors']);
        }

        $newStudent = $studentValidation['data'];
    }

    $courseText = trim((string)($input['course_id'] ?? ''));
    $courseId = preg_match('/^[1-9][0-9]*$/', $courseText) ? (int)$courseText : 0;
    if ($courseId < 1) $errors['course_id'] = 'Course is required.';

    $batchText = trim((string)($input['batch_id'] ?? ''));
    $batchId = preg_match('/^[1-9][0-9]*$/', $batchText) ? (int)$batchText : 0;
    if ($batchId < 1) $errors['batch_id'] = 'Batch is required.';

    $admissionDate = admission_valid_date($input['admission_date'] ?? '');
    if ($admissionDate === null) $errors['admission_date'] = 'Enter a valid Admission Date.';

    $admissionState = strtolower(trim((string)($input['admission_state'] ?? 'active')));
    if (!in_array($admissionState, ['active','completed','discontinued','cancelled'], true)) {
        $errors['admission_state'] = 'Select a valid Admission State.';
    }

    $status = (int)($input['status'] ?? 1);
    if (!in_array($status, [0,1], true)) $errors['status'] = 'Select a valid Status.';

    $remarks = trim((string)($input['remarks'] ?? ''));
    if (mb_strlen($remarks) > 255) $errors['remarks'] = 'Remarks cannot exceed 255 characters.';

    if ($studentId > 0) {
        $stmt = db()->prepare(
            'SELECT id,status
             FROM college_students
             WHERE id=:id AND branch_id=:branch_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $studentId, ':branch_id' => $branchId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $errors['student_id'] = 'Selected Student is invalid.';
        } elseif (
            (int)$student['status'] !== 1 &&
            (!$oldAdmission || (int)$oldAdmission['student_id'] !== $studentId)
        ) {
            $errors['student_id'] = 'Select an active Student.';
        }
    }

    if ($courseId > 0) {
        $stmt = db()->prepare(
            'SELECT id,status
             FROM college_courses
             WHERE id=:id AND branch_id=:branch_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $courseId, ':branch_id' => $branchId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            $errors['course_id'] = 'Selected Course is invalid.';
        } elseif (
            (int)$course['status'] !== 1 &&
            (!$oldAdmission || (int)$oldAdmission['course_id'] !== $courseId)
        ) {
            $errors['course_id'] = 'Select an active Course.';
        }
    }

    $batch = null;
    if ($batchId > 0) {
        $stmt = db()->prepare(
            'SELECT *
             FROM college_batches
             WHERE id=:id AND branch_id=:branch_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $batchId, ':branch_id' => $branchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            $errors['batch_id'] = 'Selected Batch is invalid.';
        } else {
            if ((int)$batch['course_id'] !== $courseId) {
                $errors['batch_id'] = 'Selected Batch does not belong to the selected Course.';
            }

            if (
                (int)$batch['status'] !== 1 &&
                (!$oldAdmission || (int)$oldAdmission['batch_id'] !== $batchId)
            ) {
                $errors['batch_id'] = 'Select an active Batch.';
            }

            if ($admissionDate !== null) {
                if ($batch['start_date'] && $admissionDate < (string)$batch['start_date']) {
                    $errors['admission_date'] = 'Admission Date cannot be before Batch Start Date.';
                }
                if ($batch['end_date'] && $admissionDate > (string)$batch['end_date']) {
                    $errors['admission_date'] = 'Admission Date cannot be after Batch End Date.';
                }
            }
        }
    }

    if ($studentMode === 'existing' && $studentId > 0 && $batchId > 0) {
        $sql =
            'SELECT id
             FROM college_admissions
             WHERE student_id=:student_id AND batch_id=:batch_id';
        $params = [':student_id' => $studentId, ':batch_id' => $batchId];

        if ($oldAdmission) {
            $sql .= ' AND id<>:id';
            $params[':id'] = (int)$oldAdmission['id'];
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors['student_id'] = 'This Student is already admitted to the selected Batch.';
        }
    }

    if ($batch) {
        $capacity = $batch['maximum_students'] === null ? null : (int)$batch['maximum_students'];
        if ($capacity !== null && $capacity > 0) {
            $sql =
                "SELECT COUNT(*)
                 FROM college_admissions
                 WHERE branch_id=:branch_id
                   AND batch_id=:batch_id
                   AND status=1
                   AND admission_state='active'";
            $params = [':branch_id' => $branchId, ':batch_id' => $batchId];

            if ($oldAdmission) {
                $sql .= ' AND id<>:id';
                $params[':id'] = (int)$oldAdmission['id'];
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $activeCount = (int)$stmt->fetchColumn();

            if ($activeCount >= $capacity) {
                $errors['batch_id'] = 'Selected Batch is full. Maximum Students: ' . $capacity . '.';
            }
        }
    }

    $structureText = trim((string)($input['fee_structure_id'] ?? ''));
    $feeStructureId = preg_match('/^[1-9][0-9]*$/', $structureText)
        ? (int)$structureText : 0;
    if ($feeStructureId < 1) $errors['fee_structure_id'] = 'Fee Structure is required.';

    $structure = null;
    if ($feeStructureId > 0) {
        $stmt = db()->prepare(
            'SELECT *
             FROM college_fee_structures
             WHERE id=:id AND branch_id=:branch_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $feeStructureId, ':branch_id' => $branchId]);
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$structure) {
            $errors['fee_structure_id'] = 'Selected Fee Structure is invalid.';
        } else {
            if ((int)$structure['course_id'] !== $courseId) {
                $errors['fee_structure_id'] = 'Fee Structure does not belong to the selected Course.';
            }

            $isOldStructure = $oldPlan && (int)$oldPlan['fee_structure_id'] === $feeStructureId;
            if ((int)$structure['status'] !== 1 && !$isOldStructure) {
                $errors['fee_structure_id'] = 'Select an active Fee Structure.';
            }

            if (
                $admissionDate !== null &&
                $structure['effective_from'] &&
                (string)$structure['effective_from'] > $admissionDate &&
                !$isOldStructure
            ) {
                $errors['fee_structure_id'] = 'Fee Structure is not effective on the Admission Date.';
            }
        }
    }

    $selectedIdsRaw = is_array($input['selected_fee_item_ids'] ?? null)
        ? $input['selected_fee_item_ids'] : [];
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIdsRaw), fn($id) => $id > 0)));

    $selectedItems = [];
    $grossAmount = 0.0;

    if ($feeStructureId > 0) {
        $stmt = db()->prepare(
            'SELECT id,fee_head,amount,mandatory,sort_order
             FROM college_fee_structure_items
             WHERE fee_structure_id=:fee_structure_id
               AND branch_id=:branch_id
               AND status=1
             ORDER BY sort_order,id'
        );
        $stmt->execute([':fee_structure_id' => $feeStructureId, ':branch_id' => $branchId]);
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($available as $item) {
            $id = (int)$item['id'];
            $mandatory = (int)$item['mandatory'] === 1;
            $selected = $mandatory || in_array($id, $selectedIds, true);

            if ($selected) {
                $amount = round((float)$item['amount'], 2);
                $grossAmount += $amount;
                $selectedItems[] = [
                    'id' => $id,
                    'fee_head' => (string)$item['fee_head'],
                    'amount' => $amount,
                    'mandatory' => $mandatory ? 1 : 0,
                    'sort_order' => (int)$item['sort_order'],
                ];
            }
        }
    }

    $grossAmount = round($grossAmount, 2);
    if ($selectedItems === []) {
        $errors['selected_fee_item_ids'] = 'Select at least one Fee item.';
    }

    $paymentRowsRaw = is_array($input['payment_rows'] ?? null)
        ? $input['payment_rows'] : [];
    $paymentRows = [];
    $paymentTotal = 0.0;
    $seenModes = [];

    foreach ($paymentRowsRaw as $index => $row) {
        if (!is_array($row)) continue;

        $mode = (int)($row['payment_mode'] ?? 0);
        if (!in_array($mode, [1,2,3,4], true)) continue;

        if (isset($seenModes[$mode])) {
            $errors['payment_rows'] = 'Each Payment Mode can appear only once.';
            continue;
        }
        $seenModes[$mode] = true;

        $amount = admission_decimal($row['amount'] ?? '0');
        if ($amount === false) {
            $errors['payment_rows[' . $index . '][amount]'] = 'Enter a valid Payment Amount.';
            continue;
        }
        if ($amount <= 0) continue;

        $accountText = trim((string)($row['account_id'] ?? ''));
        $accountId = preg_match('/^[1-9][0-9]*$/', $accountText) ? (int)$accountText : 0;
        if ($accountId < 1 || !admission_validate_account($branchId, $accountId, $mode)) {
            $errors['payment_rows[' . $index . '][account_id]'] =
                'Select a valid Account for this Payment Mode.';
        }

        $referenceNo = trim((string)($row['reference_no'] ?? ''));
        $chequeNo = trim((string)($row['cheque_no'] ?? ''));
        $chequeDateText = trim((string)($row['cheque_date'] ?? ''));
        $chequeDate = $chequeDateText === '' ? null : admission_valid_date($chequeDateText);

        if ($mode === 2 && $referenceNo === '') {
            $errors['payment_rows[' . $index . '][reference_no]'] = 'UPI Reference No is required.';
        }
        if ($mode === 3 && $referenceNo === '') {
            $errors['payment_rows[' . $index . '][reference_no]'] = 'Bank Reference No is required.';
        }
        if ($mode === 4) {
            if ($chequeNo === '') {
                $errors['payment_rows[' . $index . '][cheque_no]'] = 'Cheque No is required.';
            }
            if ($chequeDate === null) {
                $errors['payment_rows[' . $index . '][cheque_date]'] = 'Cheque Date is required.';
            }
        }

        if (mb_strlen($referenceNo) > 150) {
            $errors['payment_rows[' . $index . '][reference_no]'] = 'Reference No cannot exceed 150 characters.';
        }
        if (mb_strlen($chequeNo) > 100) {
            $errors['payment_rows[' . $index . '][cheque_no]'] = 'Cheque No cannot exceed 100 characters.';
        }

        $paymentRows[] = [
            'payment_mode' => $mode,
            'account_id' => $accountId,
            'amount' => round((float)$amount, 2),
            'reference_no' => $referenceNo === '' ? null : $referenceNo,
            'cheque_no' => $chequeNo === '' ? null : $chequeNo,
            'cheque_date' => $chequeDate,
        ];
        $paymentTotal += (float)$amount;
    }

    $paymentTotal = round($paymentTotal, 2);
    if ($paymentTotal > $grossAmount + 0.009) {
        $errors['payment_rows'] = 'Admission Payment cannot exceed Total Fee.';
    }

    $balance = round(max(0, $grossAmount - $paymentTotal), 2);
    $paymentType = strtolower(trim((string)($input['payment_type'] ?? 'emi')));

    if ($balance <= 0.009) {
        $paymentType = 'full';
    } elseif ($paymentType !== 'emi') {
        $errors['payment_type'] = 'Select EMI because a Remaining Balance is pending.';
    }

    $countText = trim((string)($input['installment_count'] ?? '0'));
    $installmentCount = preg_match('/^[0-9]+$/', $countText) ? (int)$countText : 0;
    $firstDueDateText = trim((string)($input['first_due_date'] ?? ''));
    $firstDueDate = $firstDueDateText === '' ? null : admission_valid_date($firstDueDateText);

    if ($balance > 0.009) {
        if ($installmentCount < 1 || $installmentCount > 120) {
            $errors['installment_count'] = 'EMI Count must be between 1 and 120.';
        }
        if ($firstDueDate === null) {
            $errors['first_due_date'] = 'First EMI Due Date is required.';
        }
    } else {
        $installmentCount = 0;
        $firstDueDate = null;
    }

    if ($errors !== []) return ['errors' => $errors];

    return [
        'errors' => [],
        'data' => [
            'student_mode' => $studentMode,
            'student_id' => $studentId,
            'new_student' => $newStudent,
            'course_id' => $courseId,
            'batch_id' => $batchId,
            'admission_date' => $admissionDate,
            'completion_date' => $batch && $batch['end_date'] ? (string)$batch['end_date'] : null,
            'admission_state' => $admissionState,
            'status' => $status,
            'remarks' => $remarks === '' ? null : $remarks,
            'fee_structure_id' => $feeStructureId,
            'selected_items' => $selectedItems,
            'gross_amount' => $grossAmount,
            'net_payable' => $grossAmount,
            'payment_rows' => $paymentRows,
            'admission_payment_total' => $paymentTotal,
            'balance_amount' => $balance,
            'payment_type' => $paymentType,
            'installment_count' => $installmentCount,
            'first_due_date' => $firstDueDate,
        ],
    ];
}

function admission_add_months_clamped(string $dateText, int $months): string
{
    $date = new DateTimeImmutable($dateText);
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');

    $total = ($year * 12) + ($month - 1) + $months;
    $targetYear = intdiv($total, 12);
    $targetMonth = ($total % 12) + 1;
    $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth));
    $lastDay = (int)$monthStart->format('t');
    $targetDay = min($day, $lastDay);

    return sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $targetDay);
}

function admission_generate_installments(float $balance, int $count, ?string $firstDueDate): array
{
    if ($balance <= 0 || $count < 1 || !$firstDueDate) return [];

    $cents = (int)round($balance * 100);
    $base = intdiv($cents, $count);
    $allocated = 0;
    $rows = [];

    for ($i = 1; $i <= $count; $i++) {
        $amountCents = $i === $count ? $cents - $allocated : $base;
        $allocated += $amountCents;

        $rows[] = [
            'installment_number' => $i,
            'due_date' => admission_add_months_clamped($firstDueDate, $i - 1),
            'due_amount' => round($amountCents / 100, 2),
        ];
    }

    return $rows;
}

function admission_financial_signature(int $branchId, int $admissionId): string
{
    $plan = admission_fee_plan($branchId, $admissionId);

    if (!$plan) {
        return '';
    }

    $selected = array_map(
        fn($row) =>
            (int)$row['source_fee_structure_item_id'],
        $plan['items'] ?? []
    );

    sort($selected);

    return json_encode([
        'fee_structure_id' =>
            (int)$plan['fee_structure_id'],
        'selected' =>
            $selected,
        'gross' =>
            round(
                (float)$plan['gross_amount'],
                2
            ),
        'payment_type' =>
            (string)$plan['payment_type'],
        'installment_count' =>
            (int)$plan['installment_count'],
        'first_due_date' =>
            (string)(
                $plan['first_due_date'] ??
                ''
            ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function admission_payload_signature(array $data): string
{
    $selected = array_map(
        fn($row) =>
            (int)$row['id'],
        $data['selected_items']
    );

    sort($selected);

    return json_encode([
        'fee_structure_id' =>
            (int)$data['fee_structure_id'],
        'selected' =>
            $selected,
        'gross' =>
            round(
                (float)$data['gross_amount'],
                2
            ),
        'payment_type' =>
            (string)$data['payment_type'],
        'installment_count' =>
            (int)$data['installment_count'],
        'first_due_date' =>
            (string)(
                $data['first_due_date'] ??
                ''
            ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function admission_delete_financials(PDO $pdo, int $branchId, int $admissionId): void
{
    $plan = admission_fee_plan($branchId, $admissionId);
    if (!$plan) return;

    $planId = (int)$plan['id'];

    $receiptStmt = $pdo->prepare(
        'SELECT id
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id'
    );
    $receiptStmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId,
    ]);
    $receiptIds = array_map('intval', $receiptStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($receiptIds as $receiptId) {
        $stmt = $pdo->prepare(
            'DELETE FROM college_fee_receipt_allocations
             WHERE branch_id=:branch_id AND fee_receipt_id=:receipt_id'
        );
        $stmt->execute([':branch_id' => $branchId, ':receipt_id' => $receiptId]);

        $stmt = $pdo->prepare(
            'DELETE FROM college_fee_receipt_payment_details
             WHERE branch_id=:branch_id AND fee_receipt_id=:receipt_id'
        );
        $stmt->execute([':branch_id' => $branchId, ':receipt_id' => $receiptId]);
    }

    $stmt = $pdo->prepare(
        'DELETE FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId,
    ]);

    $stmt = $pdo->prepare(
        'DELETE FROM college_fee_installments
         WHERE branch_id=:branch_id AND student_fee_plan_id=:plan_id'
    );
    $stmt->execute([':branch_id' => $branchId, ':plan_id' => $planId]);

    $stmt = $pdo->prepare(
        'DELETE FROM college_student_fee_plan_items
         WHERE branch_id=:branch_id AND student_fee_plan_id=:plan_id'
    );
    $stmt->execute([':branch_id' => $branchId, ':plan_id' => $planId]);

    $stmt = $pdo->prepare(
        'DELETE FROM college_student_fee_plans
         WHERE branch_id=:branch_id AND id=:plan_id'
    );
    $stmt->execute([':branch_id' => $branchId, ':plan_id' => $planId]);
}

function admission_create_financials(
    PDO $pdo,
    int $branchId,
    int $userId,
    int $admissionId,
    array $data
): array {
    $planStmt = $pdo->prepare(
        'INSERT INTO college_student_fee_plans
         (
            branch_id,admission_id,fee_structure_id,plan_date,gross_amount,
            discount_amount,scholarship_amount,late_fee_amount,net_payable,
            installment_count,payment_type,first_due_date,notes,status,
            created_by,created_at,updated_at
         )
         VALUES
         (
            :branch_id,:admission_id,:fee_structure_id,:plan_date,:gross_amount,
            0,0,0,:net_payable,:installment_count,:payment_type,:first_due_date,
            NULL,1,:created_by,NOW(),NOW()
         )'
    );
    $planStmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':fee_structure_id' => $data['fee_structure_id'],
        ':plan_date' => $data['admission_date'],
        ':gross_amount' => $data['gross_amount'],
        ':net_payable' => $data['net_payable'],
        ':installment_count' => $data['installment_count'],
        ':payment_type' => $data['payment_type'],
        ':first_due_date' => $data['first_due_date'],
        ':created_by' => $userId,
    ]);
    $planId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO college_student_fee_plan_items
         (
            student_fee_plan_id,branch_id,source_fee_structure_item_id,
            fee_head_snapshot,amount,mandatory_snapshot,sort_order,status,
            created_by,created_at,updated_at
         )
         VALUES
         (
            :student_fee_plan_id,:branch_id,:source_fee_structure_item_id,
            :fee_head_snapshot,:amount,:mandatory_snapshot,:sort_order,1,
            :created_by,NOW(),NOW()
         )'
    );

    foreach ($data['selected_items'] as $item) {
        $itemStmt->execute([
            ':student_fee_plan_id' => $planId,
            ':branch_id' => $branchId,
            ':source_fee_structure_item_id' => $item['id'],
            ':fee_head_snapshot' => $item['fee_head'],
            ':amount' => $item['amount'],
            ':mandatory_snapshot' => $item['mandatory'],
            ':sort_order' => $item['sort_order'],
            ':created_by' => $userId,
        ]);
    }

    $paymentResult = cfp_save_admission_receipt(
        $pdo,
        $branchId,
        $admissionId,
        $planId,
        (string)$data['admission_date'],
        $data['payment_rows'],
        $userId,
        'Payment collected during Student Admission'
    );

    return [
        'plan_id' => $planId,
        'receipt_id' =>
            $paymentResult['receipt_id'],
    ];
}

$method = request_method();
admission_require_schema();

if ($method === 'GET' && isset($_GET['fee_options'])) {
    $access = require_permission('admission-form.php', ACTION_VIEW);
    $ctx = admission_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $courseId = (int)($_GET['course_id'] ?? 0);
    $admissionDateText = trim((string)($_GET['admission_date'] ?? ''));
    $admissionDate = $admissionDateText === ''
        ? null
        : admission_valid_date($admissionDateText);

    $includeStructureId = (int)($_GET['include_structure_id'] ?? 0);

    if ($courseId < 1) {
        json_success('Fee Structure options loaded.', [
            'fee_structures' => [],
            'fee_items' => [],
        ]);
    }

    if ($admissionDateText !== '' && $admissionDate === null) {
        json_error(
            'Enter a valid Admission Date before selecting Fee Structure.',
            422,
            ['admission_date' => 'Enter a valid Admission Date.']
        );
    }

    $includeIds = $includeStructureId > 0
        ? [$includeStructureId]
        : [];

    $fees = admission_fee_options($branchId, $includeIds);

    $structures = array_values(array_filter(
        $fees['structures'],
        function(array $row) use (
            $courseId,
            $admissionDate,
            $includeStructureId
        ): bool {
            if ((int)$row['course_id'] !== $courseId) {
                return false;
            }

            if ((int)$row['id'] === $includeStructureId) {
                return true;
            }

            if ((int)$row['status'] !== 1) {
                return false;
            }

            if (
                $admissionDate !== null &&
                (string)$row['effective_from'] > $admissionDate
            ) {
                return false;
            }

            return true;
        }
    ));

    $structureIds = array_map(
        static fn(array $row): int => (int)$row['id'],
        $structures
    );

    $items = array_values(array_filter(
        $fees['items'],
        static fn(array $row): bool =>
            in_array((int)$row['fee_structure_id'], $structureIds, true)
    ));

    json_success('Fee Structure options loaded.', [
        'fee_structures' => $structures,
        'fee_items' => $items,
    ]);
}

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('admission-form.php', ACTION_VIEW);
    $ctx = admission_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $fees = admission_fee_options($branchId);

    json_success('Admission form options loaded.', [
        'allowed_actions' => $access['actions'],
        'students' => admission_student_options($branchId),
        'courses' => admission_course_options($branchId),
        'batches' => admission_batch_options($branchId),
        'fee_structures' => $fees['structures'],
        'fee_items' => $fees['items'],
        'accounts' => admission_account_options($branchId),
        'next_admission_no' => admission_code(admission_next_number(db(), $branchId)),
        'today' => date('Y-m-d'),
        'student_mode_default' => 'new',
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('admission-form.php', ACTION_VIEW);
    $ctx = admission_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $id = admission_ref_to_id($_GET['ref'] ?? '');
    $admission = admission_record($ctx, $id);
    $plan = admission_fee_plan($branchId, $id);

    if (!$plan) {
        json_error('Admission Fee Plan is missing.', 500);
    }

    $receipt = admission_receipt($branchId, $id, (int)$plan['id']);
    $fees = admission_fee_options($branchId, [(int)$plan['fee_structure_id']]);

    json_success('Admission loaded.', [
        'allowed_actions' => $access['actions'],
        'admission' => $admission,
        'fee_plan' => $plan,
        'fee_plan_items' => $plan['items'],
        'installments' => $plan['installments'],
        'admission_receipt' => $receipt ?: ['payment_details' => []],
        'students' => admission_student_options($branchId, [(int)$admission['student_id']]),
        'courses' => admission_course_options($branchId, [(int)$admission['course_id']]),
        'batches' => admission_batch_options($branchId, [(int)$admission['batch_id']]),
        'fee_structures' => $fees['structures'],
        'fee_items' => $fees['items'],
        'accounts' => admission_account_options($branchId),
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('admission-list.php', ACTION_VIEW);
    $ctx = admission_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $formMenu = menu_by_path('admission-form.php');
    $formActions = $formMenu
        ? effective_actions_for_menu($access['user'], $formMenu)
        : [];

    $paymentMenu = menu_by_path('student-payment.php');
    $paymentActions = $paymentMenu
        ? effective_actions_for_menu($access['user'], $paymentMenu)
        : [];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0 ? 100000 : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $courseFilter = (int)($_GET['course_id'] ?? 0);
    $stateFilter = trim((string)($_GET['admission_state'] ?? ''));
    $paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
    $statusRaw = trim((string)($_GET['status'] ?? ''));

    $where = ['a.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($courseFilter > 0) {
        $where[] = 'a.course_id=:course_id';
        $params[':course_id'] = $courseFilter;
    }

    if ($stateFilter !== '') {
        $where[] = 'a.admission_state=:admission_state';
        $params[':admission_state'] = $stateFilter;
    }

    if ($statusRaw === '0' || $statusRaw === '1') {
        $where[] = 'a.status=:status';
        $params[':status'] = (int)$statusRaw;
    }

    if ($paymentStatus === 'paid') {
        $where[] =
            'GREATEST(COALESCE(fp.net_payable,0)-COALESCE(pay.total_paid,0),0) <= 0.009';
    } elseif ($paymentStatus === 'partial') {
        $where[] =
            'COALESCE(pay.total_paid,0) > 0.009
             AND GREATEST(COALESCE(fp.net_payable,0)-COALESCE(pay.total_paid,0),0) > 0.009';
    } elseif ($paymentStatus === 'unpaid') {
        $where[] = 'COALESCE(pay.total_paid,0) <= 0.009';
    }

    if ($search !== '') {
        $like = '%' . $search . '%';

        $where[] =
            '(a.admission_no LIKE :s_admission
              OR s.student_code LIKE :s_student_code
              OR s.student_name LIKE :s_student_name
              OR s.mobile LIKE :s_mobile
              OR c.course_code LIKE :s_course_code
              OR c.course_name LIKE :s_course_name
              OR b.batch_code LIKE :s_batch_code
              OR b.batch_name LIKE :s_batch_name)';

        $params[':s_admission'] = $like;
        $params[':s_student_code'] = $like;
        $params[':s_student_name'] = $like;
        $params[':s_mobile'] = $like;
        $params[':s_course_code'] = $like;
        $params[':s_course_name'] = $like;
        $params[':s_batch_code'] = $like;
        $params[':s_batch_name'] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $paymentJoin =
        "LEFT JOIN (
            SELECT
                branch_id,
                admission_id,
                SUM(
                    CASE
                        WHEN status=1
                         AND posting_status=1
                         AND reversed_at IS NULL
                        THEN amount ELSE 0
                    END
                ) AS total_paid,
                SUM(
                    CASE
                        WHEN receipt_type='admission'
                         AND status=1
                         AND posting_status=1
                         AND reversed_at IS NULL
                        THEN amount ELSE 0
                    END
                ) AS admission_payment,
                MAX(
                    CASE
                        WHEN receipt_type='admission'
                         AND status=1
                         AND posting_status=1
                         AND reversed_at IS NULL
                         AND amount > 0.009
                        THEN id ELSE NULL
                    END
                ) AS admission_receipt_id
            FROM college_fee_receipts
            GROUP BY branch_id,admission_id
        ) pay
          ON pay.branch_id=a.branch_id
         AND pay.admission_id=a.id";

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_admissions
         WHERE branch_id=:branch_id'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_admissions a
         INNER JOIN college_students s
                 ON s.id=a.student_id
                AND s.branch_id=a.branch_id
         INNER JOIN college_courses c
                 ON c.id=a.course_id
                AND c.branch_id=a.branch_id
         INNER JOIN college_batches b
                 ON b.id=a.batch_id
                AND b.branch_id=a.branch_id
         LEFT JOIN college_student_fee_plans fp
                ON fp.admission_id=a.id
               AND fp.branch_id=a.branch_id
               AND fp.status=1
         ' . $paymentJoin . '
         WHERE ' . $whereSql
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_admissions,
            COALESCE(SUM(
                CASE
                    WHEN a.admission_state=\'active\' AND a.status=1
                    THEN 1 ELSE 0
                END
            ),0) AS active_admissions,
            COALESCE(SUM(COALESCE(pay.total_paid,0)),0) AS total_paid,
            COALESCE(SUM(
                GREATEST(
                    COALESCE(fp.net_payable,0)-COALESCE(pay.total_paid,0),
                    0
                )
            ),0) AS balance_due
         FROM college_admissions a
         INNER JOIN college_students s
                 ON s.id=a.student_id
                AND s.branch_id=a.branch_id
         INNER JOIN college_courses c
                 ON c.id=a.course_id
                AND c.branch_id=a.branch_id
         INNER JOIN college_batches b
                 ON b.id=a.batch_id
                AND b.branch_id=a.branch_id
         LEFT JOIN college_student_fee_plans fp
                ON fp.admission_id=a.id
               AND fp.branch_id=a.branch_id
               AND fp.status=1
         ' . $paymentJoin . '
         WHERE ' . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'a.admission_no',
        's.student_name',
        's.mobile',
        'c.course_name',
        'b.batch_name',
        'a.admission_date',
        'fp.net_payable',
        'pay.admission_payment',
        'pay.total_paid',
        'balance_amount',
        'fp.payment_type',
        'a.admission_state',
        'a.status',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 5);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc'
        ? 'ASC'
        : 'DESC';
    $orderColumn = $orderColumns[$orderIndex] ?? 'a.admission_date';

    $sql =
        'SELECT
            a.id,a.admission_no,a.student_id,a.course_id,a.batch_id,
            a.admission_date,a.admission_state,a.status,
            s.student_code,s.student_name,s.mobile,
            c.course_code,c.course_name,
            b.batch_code,b.batch_name,
            COALESCE(fp.net_payable,0) AS net_payable,
            COALESCE(pay.admission_payment,0) AS admission_payment,
            COALESCE(pay.admission_receipt_id,0) AS admission_receipt_id,
            COALESCE(pay.total_paid,0) AS total_paid,
            GREATEST(
                COALESCE(fp.net_payable,0)-COALESCE(pay.total_paid,0),
                0
            ) AS balance_amount,
            COALESCE(fp.payment_type,\'\') AS payment_type,
            COALESCE(fp.installment_count,0) AS installment_count
         FROM college_admissions a
         INNER JOIN college_students s
                 ON s.id=a.student_id
                AND s.branch_id=a.branch_id
         INNER JOIN college_courses c
                 ON c.id=a.course_id
                AND c.branch_id=a.branch_id
         INNER JOIN college_batches b
                 ON b.id=a.batch_id
                AND b.branch_id=a.branch_id
         LEFT JOIN college_student_fee_plans fp
                ON fp.admission_id=a.id
               AND fp.branch_id=a.branch_id
               AND fp.status=1
         ' . $paymentJoin . '
         WHERE ' . $whereSql . '
         ORDER BY ' . $orderColumn . ' ' . $orderDir . '
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $data = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $state = (string)$row['admission_state'];
        $netPayable = round((float)$row['net_payable'], 2);
        $admissionPayment = round((float)$row['admission_payment'], 2);
        $totalPaid = round((float)$row['total_paid'], 2);
        $balanceAmount = round((float)$row['balance_amount'], 2);

        $paymentStatusLabel = $totalPaid <= 0.009
            ? 'Unpaid'
            : ($balanceAmount <= 0.009 ? 'Paid' : 'Partially Paid');

        $paymentType = strtolower((string)$row['payment_type']);
        $paymentPlanLabel = $paymentType === 'full'
            ? 'Full Payment'
            : (
                $paymentType === 'emi'
                    ? 'EMI - ' . max(0, (int)$row['installment_count'])
                    : '-'
            );

        $ref = encryptReference('college_admission', (int)$row['id']);

        $admissionReceiptId = (int)($row['admission_receipt_id'] ?? 0);
        $admissionReceiptRef = $admissionReceiptId > 0
            ? encryptReference('college_fee_receipt', $admissionReceiptId)
            : null;

        $data[] = [
            'ref' => $ref,
            'admission_no' => (string)$row['admission_no'],
            'student_label' =>
                (string)$row['student_code'] . ' - ' . (string)$row['student_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'course_label' =>
                (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' =>
                (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'admission_date' => (string)$row['admission_date'],
            'net_payable' => $netPayable,
            'admission_payment' => $admissionPayment,
            'total_paid' => $totalPaid,
            'balance_amount' => $balanceAmount,
            'payment_plan_label' => $paymentPlanLabel,
            'payment_status_label' => $paymentStatusLabel,
            'admission_state' => $state,
            'admission_state_label' => match ($state) {
                'completed' => 'Completed',
                'discontinued' => 'Discontinued',
                'cancelled' => 'Cancelled',
                default => 'Active',
            },
            'status' => (int)$row['status'],
            'view_url' => 'admission-form.php?view=1&ref=' . rawurlencode($ref),
            'edit_url' => 'admission-form.php?ref=' . rawurlencode($ref),
            'payment_url' => 'student-payment.php?ref=' . rawurlencode($ref),
            'receipt_ref' => $admissionReceiptRef,
            'receipt_url' => $admissionReceiptRef
                ? 'student-fee-receipt.php?ref=' . rawurlencode($admissionReceiptRef)
                : null,
        ];
    }

    json_success('Admissions loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ],
        'summary' => [
            'total_admissions' => (int)($summary['total_admissions'] ?? 0),
            'active_admissions' => (int)($summary['active_admissions'] ?? 0),
            'total_paid' => (float)($summary['total_paid'] ?? 0),
            'balance_due' => (float)($summary['balance_due'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
        'payment_actions' => $paymentActions,
        'courses' => admission_course_options($branchId),
    ]);
}

if ($method === 'POST') {
    $input = request_data();
    $action = strtolower(trim((string)($input['action'] ?? 'save')));

    if ($action === 'delete') {
        $access = require_permission('admission-form.php', 4);
        $ctx = admission_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $id = admission_ref_to_id($input['ref'] ?? '');
        $admission = admission_record($ctx, $id);
        $plan = admission_fee_plan($branchId, $id);
        $receipt = $plan
            ? admission_receipt($branchId, $id, (int)$plan['id'])
            : null;

        $usage = admission_usage(
            $branchId,
            $id,
            $receipt ? (int)$receipt['id'] : null
        );

        if ($usage['attendance_count'] > 0 || $usage['later_receipt_count'] > 0) {
            json_error(
                'Admission cannot be deleted because Attendance or later Student Payments already exist.',
                409
            );
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            admission_delete_financials($pdo, $branchId, $id);

            $stmt = $pdo->prepare(
                'DELETE FROM college_admissions
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        json_success('Admission deleted successfully.');
    }

    if ($action !== 'save') {
        json_error('Unsupported action.', 405);
    }

    $viewAccess = require_permission('admission-form.php', ACTION_VIEW);
    $ctx = admission_context($viewAccess['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$viewAccess['user']['id'];
    $ref = trim((string)($input['ref'] ?? ''));

    $oldAdmission = null;
    $oldPlan = null;
    $admissionId = 0;

    if ($ref === '') {
        require_permission('admission-form.php', ACTION_CREATE);
    } else {
        require_permission('admission-form.php', ACTION_UPDATE);
        $admissionId = admission_ref_to_id($ref);
        $oldAdmission = admission_record($ctx, $admissionId);
        $oldPlan = admission_fee_plan($branchId, $admissionId);
    }

    $validated = admission_validate_payload($input, $branchId, $oldAdmission, $oldPlan);
    if ($validated['errors'] !== []) {
        json_error('Admission validation failed.', 422, $validated['errors']);
    }
    $data = $validated['data'];

    $existingReceipt = null;
    $usage = ['attendance_count' => 0, 'later_receipt_count' => 0];

    if ($oldAdmission) {
        if (!$oldPlan) {
            json_error('Admission Fee Plan is missing.', 500);
        }

        $existingReceipt = admission_receipt($branchId, $admissionId, (int)$oldPlan['id']);
        $usage = admission_usage(
            $branchId,
            $admissionId,
            $existingReceipt ? (int)$existingReceipt['id'] : null
        );

        if (
            ($usage['attendance_count'] > 0 || $usage['later_receipt_count'] > 0) &&
            (
                (int)$oldAdmission['student_id'] !== (int)$data['student_id'] ||
                (int)$oldAdmission['course_id'] !== (int)$data['course_id'] ||
                (int)$oldAdmission['batch_id'] !== (int)$data['batch_id'] ||
                (string)$oldAdmission['admission_date'] !== (string)$data['admission_date']
            )
        ) {
            json_error(
                'Student, Course, Batch and Admission Date cannot be changed after Attendance or Student Payments exist.',
                409
            );
        }

        if ($usage['later_receipt_count'] > 0) {
            $oldSignature = admission_financial_signature($branchId, $admissionId);
            $newSignature = admission_payload_signature($data);

            if ($oldSignature !== $newSignature) {
                json_error(
                    'Fee Structure, selected Fee items and EMI Plan cannot be changed after later Student Payments exist. Admission Payment details can still be edited and will recalculate all balances.',
                    409
                );
            }
        }
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
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

        if (!$oldAdmission && $data['student_mode'] === 'new') {
            $data['student_id'] = admission_create_student(
                $pdo,
                $branchId,
                $userId,
                $data['new_student']
            );
        }

        if (!$oldAdmission) {
            $admissionNo = admission_code(admission_next_number($pdo, $branchId));

            $stmt = $pdo->prepare(
                'INSERT INTO college_admissions
                 (
                    branch_id,admission_no,student_id,course_id,batch_id,
                    admission_date,completion_date,admission_state,remarks,status,
                    created_by,created_at,updated_at
                 )
                 VALUES
                 (
                    :branch_id,:admission_no,:student_id,:course_id,:batch_id,
                    :admission_date,:completion_date,:admission_state,:remarks,:status,
                    :created_by,NOW(),NOW()
                 )'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':admission_no' => $admissionNo,
                ':student_id' => $data['student_id'],
                ':course_id' => $data['course_id'],
                ':batch_id' => $data['batch_id'],
                ':admission_date' => $data['admission_date'],
                ':completion_date' => $data['completion_date'],
                ':admission_state' => $data['admission_state'],
                ':remarks' => $data['remarks'],
                ':status' => $data['status'],
                ':created_by' => $userId,
            ]);
            $admissionId = (int)$pdo->lastInsertId();

            admission_create_financials($pdo, $branchId, $userId, $admissionId, $data);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE college_admissions
                 SET student_id=:student_id,
                     course_id=:course_id,
                     batch_id=:batch_id,
                     admission_date=:admission_date,
                     completion_date=:completion_date,
                     admission_state=:admission_state,
                     remarks=:remarks,
                     status=:status,
                     updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':student_id' => $data['student_id'],
                ':course_id' => $data['course_id'],
                ':batch_id' => $data['batch_id'],
                ':admission_date' => $data['admission_date'],
                ':completion_date' => $data['completion_date'],
                ':admission_state' => $data['admission_state'],
                ':remarks' => $data['remarks'],
                ':status' => $data['status'],
                ':id' => $admissionId,
                ':branch_id' => $branchId,
            ]);

            if ($usage['later_receipt_count'] === 0) {
                admission_delete_financials(
                    $pdo,
                    $branchId,
                    $admissionId
                );

                admission_create_financials(
                    $pdo,
                    $branchId,
                    $userId,
                    $admissionId,
                    $data
                );
            } else {
                cfp_save_admission_receipt(
                    $pdo,
                    $branchId,
                    $admissionId,
                    (int)$oldPlan['id'],
                    (string)$data['admission_date'],
                    $data['payment_rows'],
                    $userId,
                    'Payment collected during Student Admission'
                );
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        if ($e instanceof DomainException) {
            json_error($e->getMessage(), 409);
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error(
                'Admission could not be saved because a duplicate or linked value already exists.',
                409
            );
        }

        throw $e;
    }

    $savedPlan = admission_fee_plan($branchId, $admissionId);
    $savedReceipt = $savedPlan
        ? admission_receipt($branchId, $admissionId, (int)$savedPlan['id'])
        : null;

    $savedReceiptRef = $savedReceipt
        ? encryptReference('college_fee_receipt', (int)$savedReceipt['id'])
        : null;

    json_success(
        $oldAdmission ? 'Admission updated successfully.' : 'Admission created successfully.',
        [
            'ref' => encryptReference('college_admission', $admissionId),
            'receipt_ref' => $savedReceiptRef,
            'receipt_url' => $savedReceiptRef
                ? 'student-fee-receipt.php?ref=' . rawurlencode($savedReceiptRef)
                : null,
        ]
    );
}

json_error('Unsupported request.', 405);
