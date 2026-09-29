<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Student Payment API
 *
 * GET  ?admission_ref=<ref>  Payment page context
 * Payment mutations are handled by api/college-fee-payments.php.
 *
 * Rules:
 * - Admission Payment is not allocated to EMI rows.
 * - Overall Outstanding allocates FIFO.
 * - Particular EMI allocates only to selected EMI.
 * - Update/Delete physically changes the receipt and replays all later
 *   payment allocations chronologically.
 */

function sp_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Student Payment is available only for tenant users.', 403);
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

function sp_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $tables = [
        'college_admissions',
        'college_students',
        'college_courses',
        'college_batches',
        'college_student_fee_plans',
        'college_fee_installments',
        'college_fee_receipts',
        'college_fee_receipt_allocations',
        'college_fee_receipt_payment_details',
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
                'Student Payment database setup is incomplete.',
                500,
                ['schema' => 'Missing table: ' . $table . '.']
            );
        }
    }

    $required = [
        'college_fee_receipts' => [
            'id','branch_id','receipt_no','admission_id','student_fee_plan_id',
            'receipt_date','amount','receipt_type','payment_against',
            'target_installment_id','payment_mode','posting_status','reversed_at',
            'status','created_by','created_at','updated_at'
        ],
        'college_fee_receipt_payment_details' => [
            'id','fee_receipt_id','branch_id','payment_mode','account_id','amount',
            'reference_no','cheque_no','cheque_date','status','created_by',
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
                'Student Payment database structure is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing ' . $table . ' columns: ' .
                        implode(', ', $missing) . '.'
                ]
            );
        }
    }

    $checked = true;
}

function sp_decode_ref($value, string $type, string $label): int
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($label . ' reference is required.');
    }

    try {
        $id = decryptReference(trim($value), $type);
    } catch (Throwable $e) {
        throw new InvalidArgumentException('Invalid ' . $label . ' reference.');
    }

    if ((int)$id < 1) {
        throw new InvalidArgumentException('Invalid ' . $label . ' reference.');
    }

    return (int)$id;
}

function sp_valid_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value
        ? $value
        : null;
}

function sp_decimal($value): float|false
{
    $text = trim((string)$value);
    if ($text === '') $text = '0';

    if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $text)) {
        return false;
    }

    $number = round((float)$text, 2);

    return $number < 0 ? false : $number;
}

function sp_next_receipt_number(PDO $pdo, int $branchId): int
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

function sp_receipt_code(int $number): string
{
    return 'FRC' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function sp_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,bank_name,account_number,upi_id
         FROM accounts
         WHERE branch_id=:branch_id
           AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    return array_map(function(array $row): array {
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

function sp_validate_account(int $branchId, int $accountId, int $mode): bool
{
    $stmt = db()->prepare(
        'SELECT account_type
         FROM accounts
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $accountId,
        ':branch_id' => $branchId
    ]);

    $type = $stmt->fetchColumn();
    if ($type === false) return false;

    return $mode === 1
        ? (int)$type === 1
        : (int)$type === 2;
}

function sp_admission(int $branchId, int $admissionId): array
{
    $stmt = db()->prepare(
        'SELECT
            a.id,a.admission_no,a.admission_date,a.admission_state,a.status,
            a.student_id,a.course_id,a.batch_id,
            s.student_code,s.student_name,s.mobile,
            c.course_code,c.course_name,
            b.batch_code,b.batch_name,
            fp.id AS plan_id,fp.net_payable,fp.payment_type,fp.installment_count
         FROM college_admissions a
         INNER JOIN college_students s
                 ON s.id=a.student_id AND s.branch_id=a.branch_id
         INNER JOIN college_courses c
                 ON c.id=a.course_id AND c.branch_id=a.branch_id
         INNER JOIN college_batches b
                 ON b.id=a.batch_id AND b.branch_id=a.branch_id
         INNER JOIN college_student_fee_plans fp
                 ON fp.admission_id=a.id
                AND fp.branch_id=a.branch_id
                AND fp.status=1
         WHERE a.id=:id
           AND a.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $admissionId,
        ':branch_id' => $branchId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Admission or Student Fee Plan was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['student_id'] = (int)$row['student_id'];
    $row['course_id'] = (int)$row['course_id'];
    $row['batch_id'] = (int)$row['batch_id'];
    $row['plan_id'] = (int)$row['plan_id'];
    $row['net_payable'] = round((float)$row['net_payable'], 2);
    $row['installment_count'] = (int)$row['installment_count'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('college_admission', (int)$row['id']);

    return $row;
}

function sp_payment_details(int $branchId, int $receiptId): array
{
    $stmt = db()->prepare(
        'SELECT d.*,a.account_code,a.account_name,a.account_type
         FROM college_fee_receipt_payment_details d
         INNER JOIN accounts a
                 ON a.id=d.account_id
                AND a.branch_id=d.branch_id
         WHERE d.branch_id=:branch_id
           AND d.fee_receipt_id=:receipt_id
           AND d.status=1
         ORDER BY d.payment_mode'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':receipt_id' => $receiptId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['payment_mode'] = (int)$row['payment_mode'];
        $row['account_id'] = (int)$row['account_id'];
        $row['amount'] = round((float)$row['amount'], 2);
    }
    unset($row);

    return $rows;
}

function sp_installments(int $branchId, int $planId): array
{
    $stmt = db()->prepare(
        'SELECT
            i.id,
            i.installment_number,
            i.due_date,
            i.due_amount,
            i.waived_amount,
            COALESCE(SUM(
                CASE
                    WHEN r.id IS NOT NULL
                     AND r.status=1
                     AND r.posting_status=1
                     AND r.reversed_at IS NULL
                    THEN a.allocated_amount
                    ELSE 0
                END
            ),0) AS paid_amount
         FROM college_fee_installments i
         LEFT JOIN college_fee_receipt_allocations a
                ON a.fee_installment_id=i.id
               AND a.branch_id=i.branch_id
               AND a.status=1
         LEFT JOIN college_fee_receipts r
                ON r.id=a.fee_receipt_id
               AND r.branch_id=a.branch_id
         WHERE i.branch_id=:branch_id
           AND i.student_fee_plan_id=:plan_id
           AND i.status=1
         GROUP BY
            i.id,i.installment_number,i.due_date,i.due_amount,i.waived_amount
         ORDER BY i.installment_number,i.due_date,i.id'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':plan_id' => $planId
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $due = round((float)$row['due_amount'], 2);
        $waived = round((float)$row['waived_amount'], 2);
        $paid = round((float)$row['paid_amount'], 2);
        $outstanding = round(max(0, $due - $waived - $paid), 2);

        $rows[] = [
            'id' => (int)$row['id'],
            'ref' => encryptReference(
                'college_fee_installment',
                (int)$row['id']
            ),
            'installment_number' => (int)$row['installment_number'],
            'due_date' => (string)$row['due_date'],
            'due_amount' => $due,
            'waived_amount' => $waived,
            'paid_amount' => $paid,
            'outstanding_amount' => $outstanding,
            'status_label' => $outstanding <= 0.009
                ? 'Paid'
                : ($paid > 0.009 ? 'Partially Paid' : 'Unpaid'),
        ];
    }

    return $rows;
}

function sp_summary(
    int $branchId,
    int $admissionId,
    int $planId,
    float $netPayable
): array {
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(
                CASE
                    WHEN receipt_type='admission'
                     AND status=1
                     AND posting_status=1
                     AND reversed_at IS NULL
                    THEN amount ELSE 0
                END
            ),0) AS admission_payment,
            COALESCE(SUM(
                CASE
                    WHEN receipt_type='payment'
                     AND status=1
                     AND posting_status=1
                     AND reversed_at IS NULL
                    THEN amount ELSE 0
                END
            ),0) AS later_payments
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $admissionPayment = round((float)($row['admission_payment'] ?? 0), 2);
    $laterPayments = round((float)($row['later_payments'] ?? 0), 2);
    $totalPaid = round($admissionPayment + $laterPayments, 2);
    $balance = round(max(0, $netPayable - $totalPaid), 2);
    $installments = sp_installments($branchId, $planId);

    $emiOutstanding = round(
        array_sum(array_column($installments, 'outstanding_amount')),
        2
    );

    return [
        'total_fee' => round($netPayable, 2),
        'admission_payment' => $admissionPayment,
        'later_payments' => $laterPayments,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'emi_outstanding' => $emiOutstanding,
        'installments' => $installments,
    ];
}


function sp_receipt_allocations(int $branchId, int $receiptId): array
{
    $stmt = db()->prepare(
        'SELECT
            i.installment_number,
            a.allocated_amount
         FROM college_fee_receipt_allocations a
         INNER JOIN college_fee_installments i
                 ON i.id=a.fee_installment_id
                AND i.branch_id=a.branch_id
         WHERE a.branch_id=:branch_id
           AND a.fee_receipt_id=:receipt_id
           AND a.status=1
         ORDER BY i.installment_number,i.id'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':receipt_id' => $receiptId
    ]);

    return array_map(
        static function(array $row): array {
            return [
                'installment_number' => (int)$row['installment_number'],
                'allocated_amount' => round((float)$row['allocated_amount'], 2),
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

function sp_history(
    int $branchId,
    int $admissionId,
    int $planId,
    float $netPayable
): array {
    $admissionStmt = db()->prepare(
        "SELECT COALESCE(SUM(amount),0)
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id
           AND receipt_type='admission'
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL"
    );
    $admissionStmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId
    ]);

    $admissionPayment = round((float)$admissionStmt->fetchColumn(), 2);
    $runningBalance = round(max(0, $netPayable - $admissionPayment), 2);

    $stmt = db()->prepare(
        "SELECT
            id,receipt_no,receipt_date,amount,payment_against,
            target_installment_id,payment_mode,notes,created_at
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id
           AND receipt_type='payment'
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL
         ORDER BY receipt_date,id"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $receiptId = (int)$row['id'];
        $amount = round((float)$row['amount'], 2);

        $details = sp_payment_details($branchId, $receiptId);

        $modes = array_map(function(array $detail): string {
            return match ((int)$detail['payment_mode']) {
                1 => 'Cash',
                2 => 'UPI',
                3 => 'Bank',
                4 => 'Cheque',
                default => 'Other',
            };
        }, $details);

        $allocations = sp_receipt_allocations($branchId, $receiptId);

        $allocationLabels = array_map(
            static function(array $allocation): string {
                return 'EMI ' .
                    $allocation['installment_number'] .
                    ' - ₹' .
                    number_format(
                        (float)$allocation['allocated_amount'],
                        2,
                        '.',
                        ','
                    );
            },
            $allocations
        );

        $runningBalance = round(
            max(0, $runningBalance - $amount),
            2
        );

        $rows[] = [
            'ref' => encryptReference(
                'college_fee_receipt',
                $receiptId
            ),
            'receipt_no' => (string)$row['receipt_no'],
            'receipt_date' => (string)$row['receipt_date'],
            'amount' => $amount,
            'balance_after' => $runningBalance,
            'payment_against' => (string)$row['payment_against'],
            'target_installment_ref' => $row['target_installment_id']
                ? encryptReference(
                    'college_fee_installment',
                    (int)$row['target_installment_id']
                )
                : null,
            'payment_mode_label' => implode(' + ', $modes),
            'allocation_label' => $allocationLabels === []
                ? '-'
                : implode(', ', $allocationLabels),
            'allocations' => $allocations,
            'notes' => (string)($row['notes'] ?? ''),
            'created_at' => (string)$row['created_at'],
            'payment_details' => $details,
        ];
    }

    return array_reverse($rows);
}


function sp_direct_options(int $branchId): array
{
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
                        THEN amount
                        ELSE 0
                    END
                ) AS total_paid
            FROM college_fee_receipts
            GROUP BY branch_id,admission_id
        ) pay
          ON pay.branch_id=a.branch_id
         AND pay.admission_id=a.id";

    $sql =
        'SELECT
            a.id AS admission_id,
            a.admission_no,
            a.student_id,
            a.admission_date,
            a.admission_state,
            a.status AS admission_status,
            s.student_code,
            s.student_name,
            s.mobile,
            c.course_code,
            c.course_name,
            b.batch_code,
            b.batch_name,
            fp.id AS plan_id,
            fp.net_payable,
            COALESCE(pay.total_paid,0) AS total_paid,
            GREATEST(
                COALESCE(fp.net_payable,0) -
                COALESCE(pay.total_paid,0),
                0
            ) AS balance_amount
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
         INNER JOIN college_student_fee_plans fp
                 ON fp.admission_id=a.id
                AND fp.branch_id=a.branch_id
                AND fp.status=1
         ' . $paymentJoin . '
         WHERE a.branch_id=:branch_id
           AND a.status=1
         ORDER BY
            s.student_name,
            a.admission_date DESC,
            a.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    $admissions = [];
    $students = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentId = (int)$row['student_id'];

        if (!isset($students[$studentId])) {
            $students[$studentId] = [
                'id' => $studentId,
                'student_code' =>
                    (string)$row['student_code'],
                'student_name' =>
                    (string)$row['student_name'],
                'mobile' =>
                    (string)($row['mobile'] ?? ''),
            ];
        }

        $admissionId =
            (int)$row['admission_id'];

        $admissions[] = [
            'id' => $admissionId,
            'ref' =>
                encryptReference(
                    'college_admission',
                    $admissionId
                ),
            'student_id' =>
                $studentId,
            'admission_no' =>
                (string)$row['admission_no'],
            'admission_date' =>
                (string)$row['admission_date'],
            'admission_state' =>
                (string)$row['admission_state'],
            'course_label' =>
                (string)$row['course_code'] .
                ' - ' .
                (string)$row['course_name'],
            'batch_label' =>
                (string)$row['batch_code'] .
                ' - ' .
                (string)$row['batch_name'],
            'net_payable' =>
                round(
                    (float)$row['net_payable'],
                    2
                ),
            'total_paid' =>
                round(
                    (float)$row['total_paid'],
                    2
                ),
            'balance_amount' =>
                round(
                    (float)$row['balance_amount'],
                    2
                ),
        ];
    }

    return [
        'students' =>
            array_values($students),
        'admissions' =>
            $admissions,
    ];
}

$method = request_method();
sp_require_schema();


if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('student-payment-list.php', ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $formMenu = menu_by_path('student-payment.php');
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
    $against = strtolower(trim((string)($_GET['payment_against'] ?? '')));
    $dateFrom = sp_valid_date($_GET['date_from'] ?? '');
    $dateTo = sp_valid_date($_GET['date_to'] ?? '');

    $where = [
        'r.branch_id=:branch_id',
        "r.receipt_type='payment'",
        'r.status=1',
        'r.posting_status=1',
        'r.reversed_at IS NULL',
    ];
    $params = [':branch_id' => $branchId];

    if (in_array($against, ['overall','emi'], true)) {
        $where[] = 'r.payment_against=:payment_against';
        $params[':payment_against'] = $against;
    }

    if ($dateFrom !== null) {
        $where[] = 'r.receipt_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== null) {
        $where[] = 'r.receipt_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    if ($search !== '') {
        $where[] =
            '(r.receipt_no LIKE :search_receipt
              OR a.admission_no LIKE :search_admission
              OR s.student_code LIKE :search_student_code
              OR s.student_name LIKE :search_student_name
              OR s.mobile LIKE :search_mobile
              OR c.course_code LIKE :search_course_code
              OR c.course_name LIKE :search_course_name
              OR b.batch_code LIKE :search_batch_code
              OR b.batch_name LIKE :search_batch_name)';

        $needle = '%' . $search . '%';
        $params[':search_receipt'] = $needle;
        $params[':search_admission'] = $needle;
        $params[':search_student_code'] = $needle;
        $params[':search_student_name'] = $needle;
        $params[':search_mobile'] = $needle;
        $params[':search_course_code'] = $needle;
        $params[':search_course_name'] = $needle;
        $params[':search_batch_code'] = $needle;
        $params[':search_batch_name'] = $needle;
    }

    $whereSql = implode(' AND ', $where);

    $detailJoin =
        "LEFT JOIN (
            SELECT
                fee_receipt_id,
                GROUP_CONCAT(
                    CASE payment_mode
                        WHEN 1 THEN 'Cash'
                        WHEN 2 THEN 'UPI'
                        WHEN 3 THEN 'Bank'
                        WHEN 4 THEN 'Cheque'
                        ELSE 'Other'
                    END
                    ORDER BY payment_mode
                    SEPARATOR ' + '
                ) AS mode_label
            FROM college_fee_receipt_payment_details
            WHERE status=1
            GROUP BY fee_receipt_id
        ) pd ON pd.fee_receipt_id=r.id";

    $allocationJoin =
        "LEFT JOIN (
            SELECT
                al.fee_receipt_id,
                GROUP_CONCAT(
                    CONCAT(
                        'EMI ',
                        ins.installment_number,
                        ' - ₹',
                        FORMAT(al.allocated_amount,2)
                    )
                    ORDER BY ins.installment_number
                    SEPARATOR ', '
                ) AS allocation_label
            FROM college_fee_receipt_allocations al
            INNER JOIN college_fee_installments ins
                    ON ins.id=al.fee_installment_id
                   AND ins.branch_id=al.branch_id
            WHERE al.status=1
            GROUP BY al.fee_receipt_id
        ) alloc ON alloc.fee_receipt_id=r.id";

    $admissionPayJoin =
        "LEFT JOIN (
            SELECT
                branch_id,
                admission_id,
                student_fee_plan_id,
                SUM(amount) AS admission_payment
            FROM college_fee_receipts
            WHERE receipt_type='admission'
              AND status=1
              AND posting_status=1
              AND reversed_at IS NULL
            GROUP BY branch_id,admission_id,student_fee_plan_id
        ) ap
          ON ap.branch_id=r.branch_id
         AND ap.admission_id=r.admission_id
         AND ap.student_fee_plan_id=r.student_fee_plan_id";

    $baseFrom =
        ' FROM college_fee_receipts r
          INNER JOIN college_admissions a
                  ON a.id=r.admission_id
                 AND a.branch_id=r.branch_id
          INNER JOIN college_students s
                  ON s.id=a.student_id
                 AND s.branch_id=a.branch_id
          INNER JOIN college_courses c
                  ON c.id=a.course_id
                 AND c.branch_id=a.branch_id
          INNER JOIN college_batches b
                  ON b.id=a.batch_id
                 AND b.branch_id=a.branch_id
          INNER JOIN college_student_fee_plans fp
                  ON fp.id=r.student_fee_plan_id
                 AND fp.branch_id=r.branch_id
                 AND fp.admission_id=r.admission_id
          ' . $detailJoin . '
          ' . $allocationJoin . '
          ' . $admissionPayJoin;

    $totalStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND receipt_type='payment'
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL"
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        ' WHERE ' . $whereSql
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS payment_count,
            COALESCE(SUM(r.amount), 0) AS amount_received,
            COUNT(DISTINCT r.admission_id) AS admission_count
         ' . $baseFrom . '
         WHERE ' . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'r.receipt_no',
        'r.receipt_date',
        's.student_name',
        'a.admission_no',
        'c.course_name',
        'b.batch_name',
        'r.payment_against',
        'allocation_label',
        'mode_label',
        'r.amount',
        'balance_after',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir =
        strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc'
            ? 'ASC'
            : 'DESC';

    $orderColumn = $orderColumns[$orderIndex] ?? 'r.receipt_date';

    $sql =
        'SELECT
            r.id,
            r.receipt_no,
            r.receipt_date,
            r.amount,
            r.payment_against,
            r.admission_id,
            r.student_fee_plan_id,
            a.admission_no,
            s.student_code,
            s.student_name,
            s.mobile,
            c.course_code,
            c.course_name,
            b.batch_code,
            b.batch_name,
            COALESCE(pd.mode_label,\'-\') AS mode_label,
            COALESCE(alloc.allocation_label,\'-\') AS allocation_label,
            GREATEST(
                fp.net_payable
                - COALESCE(ap.admission_payment,0)
                - COALESCE(
                    (
                        SELECT SUM(r2.amount)
                        FROM college_fee_receipts r2
                        WHERE r2.branch_id=r.branch_id
                          AND r2.admission_id=r.admission_id
                          AND r2.student_fee_plan_id=r.student_fee_plan_id
                          AND r2.receipt_type=\'payment\'
                          AND r2.status=1
                          AND r2.posting_status=1
                          AND r2.reversed_at IS NULL
                          AND (
                                r2.receipt_date < r.receipt_date
                                OR (
                                    r2.receipt_date = r.receipt_date
                                    AND r2.id <= r.id
                                )
                              )
                    ),
                    0
                ),
                0
            ) AS balance_after' .
        $baseFrom .
        ' WHERE ' . $whereSql . '
          ORDER BY ' . $orderColumn . ' ' . $orderDir . ',r.id ' . $orderDir . '
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
        $receiptId = (int)$row['id'];
        $admissionId = (int)$row['admission_id'];

        $receiptRef = encryptReference(
            'college_fee_receipt',
            $receiptId
        );

        $admissionRef = encryptReference(
            'college_admission',
            $admissionId
        );

        $data[] = [
            'ref' => $receiptRef,
            'receipt_ref' => $receiptRef,
            'receipt_url' =>
                'student-fee-receipt.php?ref=' .
                rawurlencode($receiptRef),
            'receipt_no' => (string)$row['receipt_no'],
            'receipt_date' => (string)$row['receipt_date'],
            'student_label' =>
                (string)$row['student_code'] .
                ' - ' .
                (string)$row['student_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'admission_no' => (string)$row['admission_no'],
            'course_label' =>
                (string)$row['course_code'] .
                ' - ' .
                (string)$row['course_name'],
            'batch_label' =>
                (string)$row['batch_code'] .
                ' - ' .
                (string)$row['batch_name'],
            'payment_against' => (string)$row['payment_against'],
            'payment_against_label' =>
                (string)$row['payment_against'] === 'emi'
                    ? 'Particular EMI'
                    : 'Overall Outstanding',
            'allocation_label' => (string)$row['allocation_label'],
            'mode_label' => (string)$row['mode_label'],
            'amount' => round((float)$row['amount'], 2),
            'balance_after' => round((float)$row['balance_after'], 2),
            'view_url' =>
                'student-payment.php?ref=' .
                rawurlencode($admissionRef) .
                '&payment_ref=' .
                rawurlencode($receiptRef) .
                '&view_payment=1',
            'edit_url' =>
                'student-payment.php?ref=' .
                rawurlencode($admissionRef) .
                '&payment_ref=' .
                rawurlencode($receiptRef),
            'payment_url' =>
                'student-payment.php?ref=' .
                rawurlencode($admissionRef),
        ];
    }

    json_success('Student Payments loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ],
        'summary' => [
            'payment_count' => (int)($summary['payment_count'] ?? 0),
            'amount_received' => (float)($summary['amount_received'] ?? 0),
            'admission_count' => (int)($summary['admission_count'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
    ]);
}

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('student-payment.php', ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $options = sp_direct_options($branchId);

    json_success('Student Payment options loaded.', [
        'allowed_actions' => $access['actions'],
        'students' => $options['students'],
        'admissions' => $options['admissions'],
        'today' => date('Y-m-d'),
    ]);
}

if ($method === 'GET' && isset($_GET['admission_ref'])) {
    $access = require_permission('student-payment.php', ACTION_VIEW);
    $ctx = sp_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    try {
        $admissionId = sp_decode_ref(
            $_GET['admission_ref'] ?? '',
            'college_admission',
            'Admission'
        );
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    }

    $admission = sp_admission($branchId, $admissionId);

    $summary = sp_summary(
        $branchId,
        $admissionId,
        (int)$admission['plan_id'],
        (float)$admission['net_payable']
    );

    json_success('Student Payment loaded.', [
        'allowed_actions' => $access['actions'],
        'admission' => $admission,
        'summary' => $summary,
        'installments' => $summary['installments'],
        'payments' => sp_history(
            $branchId,
            $admissionId,
            (int)$admission['plan_id'],
            (float)$admission['net_payable']
        ),
        'accounts' => sp_accounts($branchId),
        'today' => date('Y-m-d'),
    ]);
}

json_error('Unsupported request.', 405);
