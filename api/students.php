<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Student Master + Student Profile API
 *
 * GET  ?options=1                List filter options
 * GET  ?ref=<student-ref>        Student Form record
 * GET  ?profile=1&ref=<ref>      Complete Student Profile
 * GET  ?datatable=1              Student DataTable
 * POST action=save               Create / Update Student
 *
 * Student Code is system-generated and immutable.
 * No separate Student Profile tables are created.
 */

function student_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Student Master is available only for tenant users.', 403);
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

function student_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $tables = [
        'college_students',
        'college_admissions',
        'college_courses',
        'college_batches',
        'college_student_fee_plans',
        'college_fee_installments',
        'college_fee_receipts',
        'college_fee_receipt_payment_details',
        'college_fee_receipt_allocations',
        'college_student_attendance',
        'college_attendance_sessions',
        'college_course_subjects',
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
                'Student Master database setup is incomplete.',
                500,
                ['schema' => 'Missing table: ' . $table . '.']
            );
        }
    }

    $required = [
        'college_students' => [
            'id','branch_id','user_id','student_code','student_name','date_of_birth',
            'gender','mobile','email','address','guardian_name','guardian_mobile',
            'qualification','identity_number','media_files','status','created_by',
            'created_at','updated_at'
        ],
        'college_admissions' => [
            'id','branch_id','admission_no','student_id','course_id','batch_id',
            'admission_date','completion_date','admission_state','remarks','status'
        ],
        'college_student_fee_plans' => [
            'id','branch_id','admission_id','net_payable','installment_count',
            'payment_type','first_due_date','status'
        ],
        'college_fee_receipts' => [
            'id','branch_id','receipt_no','admission_id','student_fee_plan_id',
            'receipt_date','amount','receipt_type','payment_against',
            'target_installment_id','posting_status','reversed_at','status'
        ],
        'college_attendance_sessions' => [
            'id','branch_id','batch_id','attendance_type','subject_id',
            'attendance_date','status'
        ],
        'college_student_attendance' => [
            'id','attendance_session_id','branch_id','admission_id',
            'attendance_code','remarks','status'
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
                'Student Master database structure is incomplete.',
                500,
                ['schema' => 'Missing ' . $table . ' columns: ' . implode(', ', $missing) . '.']
            );
        }
    }

    $checked = true;
}

function student_ref_to_id($value, string $field = 'ref'): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Student reference is required.', 422, [$field => 'Student reference is required.']);
    }

    try {
        $id = decryptReference(trim($value), 'college_student');
    } catch (Throwable $e) {
        json_error('Invalid Student reference.', 422, [$field => 'Invalid Student reference.']);
    }

    if ((int)$id < 1) {
        json_error('Invalid Student reference.', 422, [$field => 'Invalid Student reference.']);
    }

    return (int)$id;
}

function student_valid_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return null;
    return $value;
}

function student_next_number(PDO $pdo, int $branchId): int
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

function student_code(int $number): string
{
    return 'STU' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function student_validate(array $input, int $branchId, int $excludeId = 0): array
{
    $errors = [];

    $name = trim((string)($input['student_name'] ?? ''));
    if ($name === '') {
        $errors['student_name'] = 'Student Name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors['student_name'] = 'Student Name cannot exceed 150 characters.';
    }

    $dobText = trim((string)($input['date_of_birth'] ?? ''));
    $dob = $dobText === '' ? null : student_valid_date($dobText);
    if ($dobText !== '' && $dob === null) {
        $errors['date_of_birth'] = 'Enter a valid Date of Birth.';
    } elseif ($dob !== null && $dob > date('Y-m-d')) {
        $errors['date_of_birth'] = 'Date of Birth cannot be in the future.';
    }

    $gender = trim((string)($input['gender'] ?? ''));
    if ($gender !== '' && !in_array($gender, ['Male','Female','Other'], true)) {
        $errors['gender'] = 'Select a valid Gender.';
    }

    $mobile = preg_replace('/\D+/', '', (string)($input['mobile'] ?? ''));
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid 10 digit Mobile Number.';
    }

    $email = trim((string)($input['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid Email Address.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'Email cannot exceed 190 characters.';
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

    $status = (int)($input['status'] ?? 1);
    if (!in_array($status, [0,1], true)) {
        $errors['status'] = 'Select a valid Status.';
    }

    if ($errors === [] && $name !== '' && $mobile !== '') {
        $sql =
            'SELECT student_code
             FROM college_students
             WHERE branch_id=:branch_id
               AND LOWER(TRIM(student_name))=LOWER(:student_name)
               AND mobile=:mobile';

        $params = [
            ':branch_id' => $branchId,
            ':student_name' => $name,
            ':mobile' => $mobile,
        ];

        if ($excludeId > 0) {
            $sql .= ' AND id<>:exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $existingCode = $stmt->fetchColumn();
        if ($existingCode !== false) {
            $errors['student_name'] = 'Student already exists (' . (string)$existingCode . ').';
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
            'status' => $status,
        ],
    ];
}

function student_record(int $branchId, int $studentId): ?array
{
    $stmt = db()->prepare(
        'SELECT id,branch_id,student_code,student_name,date_of_birth,gender,mobile,email,
                address,guardian_name,guardian_mobile,qualification,identity_number,status,
                created_at,updated_at
         FROM college_students
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $studentId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return null;

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('college_student', (int)$row['id']);
    unset($row['id']);
    return $row;
}

function student_list_options(int $branchId): array
{
    $courseStmt = db()->prepare(
        'SELECT id,course_code,course_name,status
         FROM college_courses
         WHERE branch_id=:branch_id
         ORDER BY status DESC,course_name,course_code'
    );
    $courseStmt->execute([':branch_id' => $branchId]);

    $batchStmt = db()->prepare(
        'SELECT id,course_id,batch_code,batch_name,status
         FROM college_batches
         WHERE branch_id=:branch_id
         ORDER BY status DESC,start_date DESC,batch_name,batch_code'
    );
    $batchStmt->execute([':branch_id' => $branchId]);

    return [
        'courses' => $courseStmt->fetchAll(PDO::FETCH_ASSOC),
        'batches' => $batchStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function student_payment_modes(int $branchId, int $receiptId): string
{
    $stmt = db()->prepare(
        "SELECT GROUP_CONCAT(
                    CASE payment_mode
                        WHEN 1 THEN 'Cash'
                        WHEN 2 THEN 'UPI'
                        WHEN 3 THEN 'Bank'
                        WHEN 4 THEN 'Cheque'
                        ELSE 'Other'
                    END
                    ORDER BY payment_mode
                    SEPARATOR ' + '
                )
         FROM college_fee_receipt_payment_details
         WHERE branch_id=:branch_id
           AND fee_receipt_id=:receipt_id
           AND status=1"
    );
    $stmt->execute([':branch_id' => $branchId, ':receipt_id' => $receiptId]);
    $label = trim((string)$stmt->fetchColumn());
    return $label === '' ? '-' : $label;
}

function student_payment_allocations(int $branchId, int $receiptId): string
{
    $stmt = db()->prepare(
        "SELECT GROUP_CONCAT(
                    CONCAT('EMI ',ins.installment_number,' - ',FORMAT(al.allocated_amount,2))
                    ORDER BY ins.installment_number
                    SEPARATOR ', '
                )
         FROM college_fee_receipt_allocations al
         INNER JOIN college_fee_installments ins
                 ON ins.id=al.fee_installment_id
                AND ins.branch_id=al.branch_id
         WHERE al.branch_id=:branch_id
           AND al.fee_receipt_id=:receipt_id
           AND al.status=1"
    );
    $stmt->execute([':branch_id' => $branchId, ':receipt_id' => $receiptId]);
    $label = trim((string)$stmt->fetchColumn());
    return $label === '' ? '-' : $label;
}

function student_profile(int $branchId, int $studentId): array
{
    $student = student_record($branchId, $studentId);
    if (!$student) {
        json_error('Student was not found.', 404);
    }

    $stmt = db()->prepare(
        "SELECT a.id,a.admission_no,a.admission_date,a.completion_date,a.admission_state,
                a.remarks AS admission_remarks,a.status AS admission_status,a.course_id,a.batch_id,
                c.course_code,c.course_name,b.batch_code,b.batch_name,
                p.id AS plan_id,p.net_payable,p.installment_count,p.payment_type,p.first_due_date,
                p.status AS plan_status
         FROM college_admissions a
         INNER JOIN college_courses c ON c.id=a.course_id AND c.branch_id=a.branch_id
         INNER JOIN college_batches b ON b.id=a.batch_id AND b.branch_id=a.branch_id
         LEFT JOIN college_student_fee_plans p ON p.admission_id=a.id AND p.branch_id=a.branch_id
         WHERE a.branch_id=:branch_id
           AND a.student_id=:student_id
         ORDER BY CASE WHEN a.admission_state='active' THEN 0 ELSE 1 END,
                  a.admission_date DESC,a.id DESC"
    );
    $stmt->execute([':branch_id' => $branchId, ':student_id' => $studentId]);

    $admissions = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $admission) {
        $admissionId = (int)$admission['id'];
        $planId = $admission['plan_id'] === null ? 0 : (int)$admission['plan_id'];
        $netPayable = round((float)($admission['net_payable'] ?? 0), 2);

        $receiptStmt = db()->prepare(
            "SELECT id,receipt_no,receipt_date,amount,receipt_type,payment_against,
                    target_installment_id,notes,created_at
             FROM college_fee_receipts
             WHERE branch_id=:branch_id
               AND admission_id=:admission_id
               AND status=1
               AND posting_status=1
               AND reversed_at IS NULL
             ORDER BY receipt_date,id"
        );
        $receiptStmt->execute([':branch_id' => $branchId, ':admission_id' => $admissionId]);
        $receiptRows = $receiptStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaid = 0.0;
        $admissionPayment = 0.0;
        $laterPayments = 0.0;
        $runningBalance = $netPayable;
        $payments = [];

        foreach ($receiptRows as $receipt) {
            $receiptId = (int)$receipt['id'];
            $amount = round((float)$receipt['amount'], 2);
            $totalPaid = round($totalPaid + $amount, 2);

            if ((string)$receipt['receipt_type'] === 'admission') {
                $admissionPayment = round($admissionPayment + $amount, 2);
            } else {
                $laterPayments = round($laterPayments + $amount, 2);
            }

            $runningBalance = round(max(0, $runningBalance - $amount), 2);

            $payments[] = [
                'ref' => encryptReference('college_fee_receipt', $receiptId),
                'receipt_no' => (string)$receipt['receipt_no'],
                'receipt_date' => (string)$receipt['receipt_date'],
                'receipt_type' => (string)$receipt['receipt_type'],
                'payment_against' => (string)($receipt['payment_against'] ?? ''),
                'amount' => $amount,
                'payment_mode_label' => student_payment_modes($branchId, $receiptId),
                'allocation_label' => student_payment_allocations($branchId, $receiptId),
                'balance_after' => $runningBalance,
                'notes' => (string)($receipt['notes'] ?? ''),
                'created_at' => (string)$receipt['created_at'],
            ];
        }
        $payments = array_reverse($payments);

        $installments = [];
        if ($planId > 0) {
            $installmentStmt = db()->prepare(
                "SELECT ins.id,ins.installment_number,ins.due_date,ins.due_amount,ins.waived_amount,
                        ins.remarks,
                        COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN al.allocated_amount ELSE 0 END),0) AS paid_amount
                 FROM college_fee_installments ins
                 LEFT JOIN college_fee_receipt_allocations al
                        ON al.fee_installment_id=ins.id
                       AND al.branch_id=ins.branch_id
                       AND al.status=1
                 LEFT JOIN college_fee_receipts r
                        ON r.id=al.fee_receipt_id
                       AND r.branch_id=al.branch_id
                       AND r.status=1
                       AND r.posting_status=1
                       AND r.reversed_at IS NULL
                 WHERE ins.branch_id=:branch_id
                   AND ins.student_fee_plan_id=:plan_id
                   AND ins.status=1
                 GROUP BY ins.id,ins.installment_number,ins.due_date,ins.due_amount,ins.waived_amount,ins.remarks
                 ORDER BY ins.installment_number,ins.id"
            );
            $installmentStmt->execute([':branch_id' => $branchId, ':plan_id' => $planId]);

            foreach ($installmentStmt->fetchAll(PDO::FETCH_ASSOC) as $installment) {
                $due = round(max(0, (float)$installment['due_amount'] - (float)$installment['waived_amount']), 2);
                $paid = round((float)$installment['paid_amount'], 2);
                $balance = round(max(0, $due - $paid), 2);

                if ($balance <= 0.009) {
                    $statusLabel = 'Paid';
                } elseif ($paid > 0.009) {
                    $statusLabel = 'Partially Paid';
                } elseif ((string)$installment['due_date'] < date('Y-m-d')) {
                    $statusLabel = 'Overdue';
                } else {
                    $statusLabel = 'Pending';
                }

                $installments[] = [
                    'ref' => encryptReference('college_fee_installment', (int)$installment['id']),
                    'installment_number' => (int)$installment['installment_number'],
                    'due_date' => (string)$installment['due_date'],
                    'due_amount' => $due,
                    'paid_amount' => $paid,
                    'balance_amount' => $balance,
                    'status_label' => $statusLabel,
                    'remarks' => (string)($installment['remarks'] ?? ''),
                ];
            }
        }

        $attendanceStmt = db()->prepare(
            "SELECT ses.attendance_type,
                    COUNT(sa.id) AS total_count,
                    SUM(CASE WHEN sa.attendance_code='P' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN sa.attendance_code='A' THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN sa.attendance_code='L' THEN 1 ELSE 0 END) AS leave_count,
                    SUM(CASE WHEN sa.attendance_code='LT' THEN 1 ELSE 0 END) AS late_count
             FROM college_student_attendance sa
             INNER JOIN college_attendance_sessions ses
                     ON ses.id=sa.attendance_session_id
                    AND ses.branch_id=sa.branch_id
             WHERE sa.branch_id=:branch_id
               AND sa.admission_id=:admission_id
               AND sa.status=1
               AND ses.status=1
             GROUP BY ses.attendance_type"
        );
        $attendanceStmt->execute([':branch_id' => $branchId, ':admission_id' => $admissionId]);

        $attendanceSummary = [
            'regular' => ['total'=>0,'present'=>0,'absent'=>0,'leave'=>0,'late'=>0,'percentage'=>0.0],
            'practical' => ['total'=>0,'present'=>0,'absent'=>0,'leave'=>0,'late'=>0,'percentage'=>0.0],
        ];

        foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $attendance) {
            $key = (int)$attendance['attendance_type'] === 2 ? 'practical' : 'regular';
            $total = (int)$attendance['total_count'];
            $present = (int)$attendance['present_count'];

            $attendanceSummary[$key] = [
                'total' => $total,
                'present' => $present,
                'absent' => (int)$attendance['absent_count'],
                'leave' => (int)$attendance['leave_count'],
                'late' => (int)$attendance['late_count'],
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0.0,
            ];
        }

        $attendanceHistoryStmt = db()->prepare(
            'SELECT ses.attendance_date,ses.attendance_type,sa.attendance_code,sa.remarks,
                    cs.subject_code,cs.subject_name
             FROM college_student_attendance sa
             INNER JOIN college_attendance_sessions ses
                     ON ses.id=sa.attendance_session_id
                    AND ses.branch_id=sa.branch_id
             LEFT JOIN college_course_subjects cs
                    ON cs.id=ses.subject_id
                   AND cs.branch_id=ses.branch_id
             WHERE sa.branch_id=:branch_id
               AND sa.admission_id=:admission_id
               AND sa.status=1
               AND ses.status=1
             ORDER BY ses.attendance_date DESC,ses.id DESC
             LIMIT 50'
        );
        $attendanceHistoryStmt->execute([':branch_id' => $branchId, ':admission_id' => $admissionId]);

        $attendanceHistory = [];
        foreach ($attendanceHistoryStmt->fetchAll(PDO::FETCH_ASSOC) as $attendanceRow) {
            $attendanceCode = (string)$attendanceRow['attendance_code'];
            $attendanceHistory[] = [
                'attendance_date' => (string)$attendanceRow['attendance_date'],
                'attendance_type' => (int)$attendanceRow['attendance_type'],
                'attendance_type_label' => (int)$attendanceRow['attendance_type'] === 2 ? 'Practical' : 'Regular',
                'subject_label' => $attendanceRow['subject_name'] === null
                    ? '-'
                    : ((string)$attendanceRow['subject_code'] . ' - ' . (string)$attendanceRow['subject_name']),
                'attendance_code' => $attendanceCode,
                'attendance_label' => match ($attendanceCode) {
                    'P' => 'Present',
                    'A' => 'Absent',
                    'L' => 'Leave',
                    'LT' => 'Late',
                    default => 'Unknown',
                },
                'remarks' => (string)($attendanceRow['remarks'] ?? ''),
            ];
        }

        $admissions[] = [
            'ref' => encryptReference('college_admission', $admissionId),
            'admission_no' => (string)$admission['admission_no'],
            'admission_date' => (string)$admission['admission_date'],
            'completion_date' => (string)($admission['completion_date'] ?? ''),
            'admission_state' => (string)$admission['admission_state'],
            'admission_status' => (int)$admission['admission_status'],
            'admission_remarks' => (string)($admission['admission_remarks'] ?? ''),
            'course_code' => (string)$admission['course_code'],
            'course_name' => (string)$admission['course_name'],
            'batch_code' => (string)$admission['batch_code'],
            'batch_name' => (string)$admission['batch_name'],
            'fee' => [
                'net_payable' => $netPayable,
                'admission_payment' => $admissionPayment,
                'later_payments' => $laterPayments,
                'total_paid' => $totalPaid,
                'balance' => round(max(0, $netPayable - $totalPaid), 2),
                'installment_count' => (int)($admission['installment_count'] ?? 0),
                'payment_type' => (string)($admission['payment_type'] ?? ''),
                'first_due_date' => (string)($admission['first_due_date'] ?? ''),
            ],
            'installments' => $installments,
            'payments' => $payments,
            'attendance_summary' => $attendanceSummary,
            'attendance_history' => $attendanceHistory,
        ];
    }

    return ['student' => $student, 'admissions' => $admissions];
}

student_require_schema();
$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('student-list.php', ACTION_VIEW);
    $ctx = student_context($access['user']);

    json_success('Student options loaded.', [
        'allowed_actions' => $access['actions'],
        'options' => student_list_options((int)$ctx['branch_id']),
    ]);
}

if ($method === 'GET' && isset($_GET['profile'])) {
    $access = require_permission('student-list.php', ACTION_VIEW);
    $ctx = student_context($access['user']);
    $studentId = student_ref_to_id($_GET['ref'] ?? '');

    json_success('Student Profile loaded.', [
        'allowed_actions' => $access['actions'],
        'profile' => student_profile((int)$ctx['branch_id'], $studentId),
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('student-list.php', ACTION_VIEW);
    $ctx = student_context($access['user']);
    $studentId = student_ref_to_id($_GET['ref']);
    $student = student_record((int)$ctx['branch_id'], $studentId);

    if (!$student) {
        json_error('Student was not found.', 404);
    }

    json_success('Student loaded.', [
        'allowed_actions' => $access['actions'],
        'student' => $student,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('student-list.php', ACTION_VIEW);
    $ctx = student_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $courseId = (int)($_GET['course_id'] ?? 0);
    $batchId = (int)($_GET['batch_id'] ?? 0);
    $statusText = trim((string)($_GET['status'] ?? ''));

    $where = ['s.branch_id=?'];
    $params = [$branchId];

    if ($courseId > 0) {
        $where[] = 'a.course_id=?';
        $params[] = $courseId;
    }

    if ($batchId > 0) {
        $where[] = 'a.batch_id=?';
        $params[] = $batchId;
    }

    if ($statusText === '0' || $statusText === '1') {
        $where[] = 's.status=?';
        $params[] = (int)$statusText;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] =
            '(s.student_code LIKE ?
              OR s.student_name LIKE ?
              OR s.mobile LIKE ?
              OR s.email LIKE ?
              OR a.admission_no LIKE ?
              OR c.course_code LIKE ?
              OR c.course_name LIKE ?
              OR b.batch_code LIKE ?
              OR b.batch_name LIKE ?)';
        for ($i = 0; $i < 9; $i++) $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $baseFrom =
        " FROM college_students s
          LEFT JOIN college_admissions a
                 ON a.id=(
                    SELECT a2.id
                    FROM college_admissions a2
                    WHERE a2.branch_id=s.branch_id
                      AND a2.student_id=s.id
                    ORDER BY CASE WHEN a2.admission_state='active' THEN 0 ELSE 1 END,
                             a2.admission_date DESC,a2.id DESC
                    LIMIT 1
                 )
          LEFT JOIN college_courses c
                 ON c.id=a.course_id AND c.branch_id=a.branch_id
          LEFT JOIN college_batches b
                 ON b.id=a.batch_id AND b.branch_id=a.branch_id
          LEFT JOIN college_student_fee_plans p
                 ON p.admission_id=a.id AND p.branch_id=a.branch_id
          LEFT JOIN (
              SELECT branch_id,admission_id,SUM(amount) AS total_paid
              FROM college_fee_receipts
              WHERE status=1
                AND posting_status=1
                AND reversed_at IS NULL
              GROUP BY branch_id,admission_id
          ) pay
                 ON pay.branch_id=a.branch_id AND pay.admission_id=a.id
          LEFT JOIN (
              SELECT sa.branch_id,sa.admission_id,
                     COUNT(sa.id) AS total_attendance,
                     SUM(CASE WHEN sa.attendance_code='P' THEN 1 ELSE 0 END) AS present_attendance
              FROM college_student_attendance sa
              INNER JOIN college_attendance_sessions ses
                      ON ses.id=sa.attendance_session_id
                     AND ses.branch_id=sa.branch_id
              WHERE sa.status=1 AND ses.status=1
              GROUP BY sa.branch_id,sa.admission_id
          ) att
                 ON att.branch_id=a.branch_id AND att.admission_id=a.id";

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_students WHERE branch_id=:branch_id');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $baseFrom . ' WHERE ' . $whereSql);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS student_count,
            COALESCE(SUM(CASE WHEN s.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(COALESCE(pay.total_paid,0)),0) AS total_paid,
            COALESCE(
                SUM(
                    GREATEST(
                        0,
                        COALESCE(p.net_payable,0)-COALESCE(pay.total_paid,0)
                    )
                ),
                0
            ) AS balance_amount' .
        $baseFrom .
        ' WHERE ' . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        's.student_code','s.student_name','s.mobile','c.course_name','b.batch_name',
        'a.admission_no','a.admission_date','a.admission_state','p.net_payable',
        'pay.total_paid','(COALESCE(p.net_payable,0)-COALESCE(pay.total_paid,0))',
        'att.present_attendance','s.status'
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $orderColumns[$orderIndex] ?? 's.student_code';

    $dataStmt = db()->prepare(
        'SELECT s.id,s.student_code,s.student_name,s.mobile,s.status,
                a.admission_no,a.admission_date,a.admission_state,
                c.course_code,c.course_name,b.batch_code,b.batch_name,
                COALESCE(p.net_payable,0) AS net_payable,
                COALESCE(pay.total_paid,0) AS total_paid,
                GREATEST(0,COALESCE(p.net_payable,0)-COALESCE(pay.total_paid,0)) AS balance_amount,
                CASE
                    WHEN COALESCE(att.total_attendance,0)=0 THEN 0
                    ELSE ROUND((COALESCE(att.present_attendance,0)/att.total_attendance)*100,2)
                END AS attendance_percentage' .
        $baseFrom .
        ' WHERE ' . $whereSql .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',s.id ' . $orderDir .
        ' LIMIT ' . $start . ',' . $length
    );
    $dataStmt->execute($params);

    $rows = [];
    foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref = encryptReference('college_student', (int)$row['id']);
        $rows[] = [
            'ref' => $ref,
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'course_label' => $row['course_name'] === null
                ? '-'
                : ((string)$row['course_code'] . ' - ' . (string)$row['course_name']),
            'batch_label' => $row['batch_name'] === null
                ? '-'
                : ((string)$row['batch_code'] . ' - ' . (string)$row['batch_name']),
            'admission_no' => (string)($row['admission_no'] ?? '-'),
            'admission_date' => (string)($row['admission_date'] ?? ''),
            'admission_state' => (string)($row['admission_state'] ?? '-'),
            'net_payable' => round((float)$row['net_payable'], 2),
            'total_paid' => round((float)$row['total_paid'], 2),
            'balance_amount' => round((float)$row['balance_amount'], 2),
            'attendance_percentage' => round((float)$row['attendance_percentage'], 2),
            'status' => (int)$row['status'],
            'profile_url' => 'student-profile.php?ref=' . rawurlencode($ref),
            'edit_url' => 'student-form.php?ref=' . rawurlencode($ref),
        ];
    }

    json_success('Student list loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'student_count' => (int)($summary['student_count'] ?? 0),
            'active_count' => (int)($summary['active_count'] ?? 0),
            'total_paid' => (float)($summary['total_paid'] ?? 0),
            'balance_amount' => (float)($summary['balance_amount'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'options' => student_list_options($branchId),
    ]);
}

if ($method === 'POST') {
    $input = request_data();
    $action = strtolower(trim((string)($input['action'] ?? 'save')));

    if ($action !== 'save') {
        json_error('Unsupported action.', 405);
    }

    $ref = trim((string)($input['ref'] ?? ''));
    $studentId = $ref === '' ? 0 : student_ref_to_id($ref);

    $access = require_permission(
        'student-list.php',
        $studentId > 0 ? ACTION_UPDATE : ACTION_CREATE
    );
    $ctx = student_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];

    $validation = student_validate($input, $branchId, $studentId);
    if ($validation['errors'] !== []) {
        json_error('Student validation failed.', 422, $validation['errors']);
    }
    $student = $validation['data'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($studentId === 0) {
            $branchLock = $pdo->prepare(
                'SELECT id FROM branches WHERE id=:branch_id LIMIT 1 FOR UPDATE'
            );
            $branchLock->execute([':branch_id' => $branchId]);

            if (!$branchLock->fetchColumn()) {
                throw new DomainException('Your Branch is unavailable.');
            }

            if (!empty($student['mobile'])) {
                $duplicate = $pdo->prepare(
                    'SELECT student_code
                     FROM college_students
                     WHERE branch_id=:branch_id
                       AND LOWER(TRIM(student_name))=LOWER(:student_name)
                       AND mobile=:mobile
                     LIMIT 1
                     FOR UPDATE'
                );
                $duplicate->execute([
                    ':branch_id' => $branchId,
                    ':student_name' => $student['student_name'],
                    ':mobile' => $student['mobile'],
                ]);

                $existingCode = $duplicate->fetchColumn();
                if ($existingCode !== false) {
                    throw new DomainException('Student already exists (' . (string)$existingCode . ').');
                }
            }

            $code = student_code(student_next_number($pdo, $branchId));
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
                    :identity_number,NULL,:status,:created_by,NOW(),NOW()
                 )'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':student_code' => $code,
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
                ':status' => $student['status'],
                ':created_by' => $userId,
            ]);
            $studentId = (int)$pdo->lastInsertId();
        } else {
            $lock = $pdo->prepare(
                'SELECT id,student_code
                 FROM college_students
                 WHERE id=:id AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $lock->execute([':id' => $studentId, ':branch_id' => $branchId]);

            if (!$lock->fetch()) {
                throw new DomainException('Student was not found.');
            }

            if (!empty($student['mobile'])) {
                $duplicate = $pdo->prepare(
                    'SELECT student_code
                     FROM college_students
                     WHERE branch_id=:branch_id
                       AND LOWER(TRIM(student_name))=LOWER(:student_name)
                       AND mobile=:mobile
                       AND id<>:student_id
                     LIMIT 1
                     FOR UPDATE'
                );
                $duplicate->execute([
                    ':branch_id' => $branchId,
                    ':student_name' => $student['student_name'],
                    ':mobile' => $student['mobile'],
                    ':student_id' => $studentId,
                ]);

                $existingCode = $duplicate->fetchColumn();
                if ($existingCode !== false) {
                    throw new DomainException('Student already exists (' . (string)$existingCode . ').');
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE college_students
                 SET student_name=:student_name,
                     date_of_birth=:date_of_birth,
                     gender=:gender,
                     mobile=:mobile,
                     email=:email,
                     address=:address,
                     guardian_name=:guardian_name,
                     guardian_mobile=:guardian_mobile,
                     qualification=:qualification,
                     identity_number=:identity_number,
                     status=:status,
                     updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
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
                ':status' => $student['status'],
                ':id' => $studentId,
                ':branch_id' => $branchId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        if ($e instanceof DomainException) {
            json_error($e->getMessage(), 409);
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            json_error('Student could not be saved because a duplicate value already exists.', 409);
        }

        throw $e;
    }

    $saved = student_record($branchId, $studentId);
    json_success(
        $ref === '' ? 'Student created successfully.' : 'Student updated successfully.',
        ['student' => $saved]
    );
}

json_error('Unsupported request.', 405);
