<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Reports
 *
 * Single read-only API for:
 * - student_admission
 * - fee_collection
 * - fee_due
 * - payment_history
 * - attendance
 * - batch_strength
 */

function ccr_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Community College reports are available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
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

function ccr_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $tables = [
        'college_students',
        'college_admissions',
        'college_courses',
        'college_batches',
        'college_student_fee_plans',
        'college_fee_installments',
        'college_fee_receipts',
        'college_fee_receipt_allocations',
        'college_fee_receipt_payment_details',
        'college_attendance_sessions',
        'college_student_attendance',
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
                'Community College report database setup is incomplete.',
                500,
                ['schema' => 'Missing table: ' . $table . '.']
            );
        }
    }

    $checked = true;
}

function ccr_date($value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

function ccr_options(int $branchId): array
{
    $courseStmt = db()->prepare(
        'SELECT id,course_code,course_name,status
         FROM college_courses
         WHERE branch_id=:branch_id
         ORDER BY status DESC,course_name,course_code'
    );
    $courseStmt->execute([':branch_id' => $branchId]);

    $batchStmt = db()->prepare(
        'SELECT id,course_id,batch_code,batch_name,start_date,end_date,status
         FROM college_batches
         WHERE branch_id=:branch_id
         ORDER BY status DESC,start_date DESC,batch_name,batch_code'
    );
    $batchStmt->execute([':branch_id' => $branchId]);

    $subjectStmt = db()->prepare(
        'SELECT id,course_id,subject_code,subject_name,subject_type,status
         FROM college_course_subjects
         WHERE branch_id=:branch_id
         ORDER BY status DESC,course_id,sort_order,subject_name,id'
    );
    $subjectStmt->execute([':branch_id' => $branchId]);

    return [
        'courses' => $courseStmt->fetchAll(PDO::FETCH_ASSOC),
        'batches' => $batchStmt->fetchAll(PDO::FETCH_ASSOC),
        'subjects' => $subjectStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function ccr_request_filters(): array
{
    $dateFromText = trim((string)($_GET['date_from'] ?? ''));
    $dateToText = trim((string)($_GET['date_to'] ?? ''));

    $dateFrom = $dateFromText === '' ? null : ccr_date($dateFromText);
    $dateTo = $dateToText === '' ? null : ccr_date($dateToText);

    if ($dateFromText !== '' && $dateFrom === null) {
        json_error('Invalid From Date.', 422, ['date_from' => 'Invalid From Date.']);
    }

    if ($dateToText !== '' && $dateTo === null) {
        json_error('Invalid To Date.', 422, ['date_to' => 'Invalid To Date.']);
    }

    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        json_error('From Date cannot be after To Date.', 422, [
            'date_from' => 'From Date cannot be after To Date.'
        ]);
    }

    return [
        'search' => trim((string)($_GET['search']['value'] ?? '')),
        'course_id' => (int)($_GET['course_id'] ?? 0),
        'batch_id' => (int)($_GET['batch_id'] ?? 0),
        'attendance_type' => (int)($_GET['attendance_type'] ?? 0),
        'subject_id' => (int)($_GET['subject_id'] ?? 0),
        'attendance_code' => trim((string)($_GET['attendance_code'] ?? '')),
        'payment_status' => trim((string)($_GET['payment_status'] ?? '')),
        'admission_state' => trim((string)($_GET['admission_state'] ?? '')),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
}

function ccr_datatable_meta(): array
{
    return [
        'draw' => max(0, (int)($_GET['draw'] ?? 0)),
        'start' => max(0, (int)($_GET['start'] ?? 0)),
        'length' => min(100000, max(1, (int)($_GET['length'] ?? 10))),
        'order_column' => (int)($_GET['order'][0]['column'] ?? 0),
        'order_dir' => strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC',
    ];
}

function ccr_student_admission(int $branchId): array
{
    $f = ccr_request_filters();
    $m = ccr_datatable_meta();

    $where = ['a.branch_id=?', 'a.status=1'];
    $params = [$branchId];

    if ($f['course_id'] > 0) {
        $where[] = 'a.course_id=?';
        $params[] = $f['course_id'];
    }

    if ($f['batch_id'] > 0) {
        $where[] = 'a.batch_id=?';
        $params[] = $f['batch_id'];
    }

    if ($f['admission_state'] !== '') {
        $where[] = 'a.admission_state=?';
        $params[] = $f['admission_state'];
    }

    if ($f['date_from']) {
        $where[] = 'a.admission_date>=?';
        $params[] = $f['date_from'];
    }

    if ($f['date_to']) {
        $where[] = 'a.admission_date<=?';
        $params[] = $f['date_to'];
    }

    if ($f['search'] !== '') {
        $like = '%' . $f['search'] . '%';
        $where[] = '(s.student_code LIKE ? OR s.student_name LIKE ? OR s.mobile LIKE ? OR a.admission_no LIKE ? OR c.course_name LIKE ? OR b.batch_name LIKE ?)';
        for ($i=0;$i<6;$i++) $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $base = "
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
        LEFT JOIN college_student_fee_plans p
               ON p.admission_id=a.id
              AND p.branch_id=a.branch_id
              AND p.status=1
        LEFT JOIN (
            SELECT branch_id,admission_id,SUM(amount) AS total_paid
            FROM college_fee_receipts
            WHERE status=1
              AND posting_status=1
              AND reversed_at IS NULL
            GROUP BY branch_id,admission_id
        ) pay
               ON pay.branch_id=a.branch_id
              AND pay.admission_id=a.id
    ";

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_admissions WHERE branch_id=? AND status=1');
    $totalStmt->execute([$branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*) ' . $base . ' WHERE ' . $whereSql);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT COUNT(*) AS admissions,
                COUNT(DISTINCT a.student_id) AS students,
                COALESCE(SUM(p.net_payable),0) AS total_fee,
                COALESCE(SUM(pay.total_paid),0) AS total_paid
         ' . $base . '
         WHERE ' . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        's.student_code','s.student_name','a.admission_no','a.admission_date',
        'c.course_name','b.batch_name','a.admission_state','p.net_payable','pay.total_paid'
    ];
    $order = $orderColumns[$m['order_column']] ?? 'a.admission_date';

    $sql = 'SELECT
                s.id AS student_id,
                a.id AS admission_id,
                s.student_code,
                s.student_name,
                s.mobile,
                a.admission_no,
                a.admission_date,
                a.completion_date,
                a.admission_state,
                c.course_code,
                c.course_name,
                b.batch_code,
                b.batch_name,
                COALESCE(p.net_payable,0) AS total_fee,
                COALESCE(pay.total_paid,0) AS total_paid,
                GREATEST(COALESCE(p.net_payable,0)-COALESCE(pay.total_paid,0),0) AS balance
            ' . $base . '
            WHERE ' . $whereSql . '
            ORDER BY ' . $order . ' ' . $m['order_dir'] . ',a.id DESC
            LIMIT ' . $m['start'] . ',' . $m['length'];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentRef = encryptReference('college_student', (int)$row['student_id']);
        $admissionRef = encryptReference('college_admission', (int)$row['admission_id']);

        $rows[] = [
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'admission_no' => (string)$row['admission_no'],
            'admission_date' => (string)$row['admission_date'],
            'completion_date' => (string)($row['completion_date'] ?? ''),
            'admission_state' => (string)$row['admission_state'],
            'course_label' => (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' => (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'total_fee' => round((float)$row['total_fee'],2),
            'total_paid' => round((float)$row['total_paid'],2),
            'balance' => round((float)$row['balance'],2),
            'profile_url' => 'student-profile.php?ref=' . rawurlencode($studentRef),
            'payment_url' => 'student-payment.php?ref=' . rawurlencode($admissionRef),
        ];
    }

    return [
        'datatable' => [
            'draw' => $m['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'students' => (int)($summary['students'] ?? 0),
            'admissions' => (int)($summary['admissions'] ?? 0),
            'total_fee' => round((float)($summary['total_fee'] ?? 0),2),
            'total_paid' => round((float)($summary['total_paid'] ?? 0),2),
            'balance' => round(max(0,(float)($summary['total_fee'] ?? 0)-(float)($summary['total_paid'] ?? 0)),2),
        ],
    ];
}

function ccr_fee_collection(int $branchId): array
{
    $f = ccr_request_filters();
    $m = ccr_datatable_meta();

    $where = [
        'r.branch_id=?',
        'r.status=1',
        'r.posting_status=1',
        'r.reversed_at IS NULL'
    ];
    $params = [$branchId];

    if ($f['course_id'] > 0) {
        $where[] = 'a.course_id=?';
        $params[] = $f['course_id'];
    }

    if ($f['batch_id'] > 0) {
        $where[] = 'a.batch_id=?';
        $params[] = $f['batch_id'];
    }

    if ($f['date_from']) {
        $where[] = 'r.receipt_date>=?';
        $params[] = $f['date_from'];
    }

    if ($f['date_to']) {
        $where[] = 'r.receipt_date<=?';
        $params[] = $f['date_to'];
    }

    if ($f['search'] !== '') {
        $like = '%' . $f['search'] . '%';
        $where[] = '(r.receipt_no LIKE ? OR s.student_code LIKE ? OR s.student_name LIKE ? OR a.admission_no LIKE ? OR c.course_name LIKE ? OR b.batch_name LIKE ?)';
        for ($i=0;$i<6;$i++) $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $base = "
        FROM college_fee_receipts r
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
        LEFT JOIN (
            SELECT
                branch_id,
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
            GROUP BY branch_id,fee_receipt_id
        ) pd
               ON pd.branch_id=r.branch_id
              AND pd.fee_receipt_id=r.id
    ";

    $totalStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM college_fee_receipts
         WHERE branch_id=?
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL"
    );
    $totalStmt->execute([$branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*) ' . $base . ' WHERE ' . $whereSql);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        "SELECT
            COUNT(*) AS receipts,
            COUNT(DISTINCT a.student_id) AS students,
            COALESCE(SUM(r.amount),0) AS collected,
            COALESCE(SUM(CASE WHEN r.receipt_type='admission' THEN r.amount ELSE 0 END),0) AS admission_collection,
            COALESCE(SUM(CASE WHEN r.receipt_type='payment' THEN r.amount ELSE 0 END),0) AS later_collection
         " . $base . "
         WHERE " . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'r.receipt_date','r.receipt_no','s.student_name','a.admission_no',
        'c.course_name','b.batch_name','r.receipt_type','r.payment_against',
        'pd.mode_label','r.amount'
    ];
    $order = $orderColumns[$m['order_column']] ?? 'r.receipt_date';

    $sql = 'SELECT
                r.id AS receipt_id,
                a.id AS admission_id,
                s.id AS student_id,
                r.receipt_no,
                r.receipt_date,
                r.receipt_type,
                r.payment_against,
                r.amount,
                r.notes,
                pd.mode_label,
                s.student_code,
                s.student_name,
                a.admission_no,
                c.course_code,
                c.course_name,
                b.batch_code,
                b.batch_name
            ' . $base . '
            WHERE ' . $whereSql . '
            ORDER BY ' . $order . ' ' . $m['order_dir'] . ',r.id DESC
            LIMIT ' . $m['start'] . ',' . $m['length'];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentRef = encryptReference('college_student', (int)$row['student_id']);
        $admissionRef = encryptReference('college_admission', (int)$row['admission_id']);
        $receiptRef = encryptReference('college_fee_receipt', (int)$row['receipt_id']);

        $rows[] = [
            'receipt_no' => (string)$row['receipt_no'],
            'receipt_date' => (string)$row['receipt_date'],
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'admission_no' => (string)$row['admission_no'],
            'course_label' => (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' => (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'receipt_type' => (string)$row['receipt_type'],
            'payment_against' => (string)($row['payment_against'] ?? ''),
            'payment_mode_label' => (string)($row['mode_label'] ?? '-'),
            'amount' => round((float)$row['amount'],2),
            'notes' => (string)($row['notes'] ?? ''),
            'profile_url' => 'student-profile.php?ref=' . rawurlencode($studentRef),
            'payment_url' => 'student-payment.php?ref=' . rawurlencode($admissionRef),
            'view_payment_url' => 'student-payment.php?ref=' . rawurlencode($admissionRef) . '&payment_ref=' . rawurlencode($receiptRef) . '&view_payment=1',
        ];
    }

    return [
        'datatable' => [
            'draw' => $m['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'students' => (int)($summary['students'] ?? 0),
            'receipts' => (int)($summary['receipts'] ?? 0),
            'collected' => round((float)($summary['collected'] ?? 0),2),
            'admission_collection' => round((float)($summary['admission_collection'] ?? 0),2),
            'later_collection' => round((float)($summary['later_collection'] ?? 0),2),
        ],
    ];
}

function ccr_fee_due(int $branchId): array
{
    $f = ccr_request_filters();
    $m = ccr_datatable_meta();

    $base = "
        SELECT
            p.id AS plan_id,
            a.id AS admission_id,
            s.id AS student_id,
            s.student_code,
            s.student_name,
            s.mobile,
            a.admission_no,
            c.id AS course_id,
            c.course_code,
            c.course_name,
            b.id AS batch_id,
            b.batch_code,
            b.batch_name,
            ROUND(p.net_payable,2) AS total_fee,
            ROUND(COALESCE(pay.total_paid,0),2) AS total_paid,
            ROUND(GREATEST(p.net_payable-COALESCE(pay.total_paid,0),0),2) AS outstanding_amount,
            ROUND(COALESCE(emi.overdue_amount,0),2) AS overdue_amount,
            emi.next_installment_number,
            emi.next_due_date,
            ROUND(COALESCE(emi.next_due_amount,0),2) AS next_due_amount,
            CASE
                WHEN GREATEST(p.net_payable-COALESCE(pay.total_paid,0),0)<=0.009 THEN 'Paid'
                WHEN COALESCE(emi.overdue_amount,0)>0.009 THEN 'Overdue'
                WHEN COALESCE(pay.total_paid,0)>0.009 THEN 'Partially Paid'
                ELSE 'Pending'
            END AS payment_status
        FROM college_student_fee_plans p
        INNER JOIN college_admissions a
                ON a.id=p.admission_id
               AND a.branch_id=p.branch_id
        INNER JOIN college_students s
                ON s.id=a.student_id
               AND s.branch_id=a.branch_id
        INNER JOIN college_courses c
                ON c.id=a.course_id
               AND c.branch_id=a.branch_id
        INNER JOIN college_batches b
                ON b.id=a.batch_id
               AND b.branch_id=a.branch_id
        LEFT JOIN (
            SELECT branch_id,admission_id,SUM(amount) AS total_paid
            FROM college_fee_receipts
            WHERE status=1
              AND posting_status=1
              AND reversed_at IS NULL
            GROUP BY branch_id,admission_id
        ) pay
               ON pay.branch_id=p.branch_id
              AND pay.admission_id=a.id
        LEFT JOIN (
            SELECT
                x.branch_id,
                x.student_fee_plan_id,
                ROUND(SUM(CASE WHEN x.outstanding_amount>0.009 AND x.due_date<CURDATE() THEN x.outstanding_amount ELSE 0 END),2) AS overdue_amount,
                SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN x.outstanding_amount>0.009 THEN x.installment_number ELSE NULL END ORDER BY x.due_date,x.installment_number,x.installment_id SEPARATOR ','),',',1) AS next_installment_number,
                SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN x.outstanding_amount>0.009 THEN DATE_FORMAT(x.due_date,'%Y-%m-%d') ELSE NULL END ORDER BY x.due_date,x.installment_number,x.installment_id SEPARATOR ','),',',1) AS next_due_date,
                SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN x.outstanding_amount>0.009 THEN CAST(x.outstanding_amount AS CHAR) ELSE NULL END ORDER BY x.due_date,x.installment_number,x.installment_id SEPARATOR ','),',',1) AS next_due_amount
            FROM (
                SELECT
                    i.id AS installment_id,
                    i.branch_id,
                    i.student_fee_plan_id,
                    i.installment_number,
                    i.due_date,
                    ROUND(
                        GREATEST(
                            (i.due_amount-i.waived_amount) -
                            COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN al.allocated_amount ELSE 0 END),0),
                            0
                        ),
                        2
                    ) AS outstanding_amount
                FROM college_fee_installments i
                LEFT JOIN college_fee_receipt_allocations al
                       ON al.fee_installment_id=i.id
                      AND al.branch_id=i.branch_id
                      AND al.status=1
                LEFT JOIN college_fee_receipts r
                       ON r.id=al.fee_receipt_id
                      AND r.branch_id=al.branch_id
                      AND r.status=1
                      AND r.posting_status=1
                      AND r.reversed_at IS NULL
                WHERE i.status=1
                GROUP BY
                    i.id,i.branch_id,i.student_fee_plan_id,i.installment_number,
                    i.due_date,i.due_amount,i.waived_amount
            ) x
            GROUP BY x.branch_id,x.student_fee_plan_id
        ) emi
               ON emi.branch_id=p.branch_id
              AND emi.student_fee_plan_id=p.id
        WHERE p.branch_id=?
          AND p.status=1
          AND a.status=1
          AND a.admission_state<>'cancelled'
    ";

    $where = [];
    $params = [$branchId];

    if ($f['course_id'] > 0) {
        $where[] = 'r.course_id=?';
        $params[] = $f['course_id'];
    }

    if ($f['batch_id'] > 0) {
        $where[] = 'r.batch_id=?';
        $params[] = $f['batch_id'];
    }

    if ($f['payment_status'] !== '') {
        $where[] = 'r.payment_status=?';
        $params[] = $f['payment_status'];
    }

    if ($f['date_from']) {
        $where[] = 'r.next_due_date IS NOT NULL AND r.next_due_date>=?';
        $params[] = $f['date_from'];
    }

    if ($f['date_to']) {
        $where[] = 'r.next_due_date IS NOT NULL AND r.next_due_date<=?';
        $params[] = $f['date_to'];
    }

    if ($f['search'] !== '') {
        $like = '%' . $f['search'] . '%';
        $where[] = '(r.student_code LIKE ? OR r.student_name LIKE ? OR r.mobile LIKE ? OR r.admission_no LIKE ? OR r.course_name LIKE ? OR r.batch_name LIKE ?)';
        for ($i=0;$i<6;$i++) $params[] = $like;
    }

    $outer = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM (' . $base . ') r');
    $totalStmt->execute([$branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*) FROM (' . $base . ') r' . $outer);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS students,
            COALESCE(SUM(total_fee),0) AS total_fee,
            COALESCE(SUM(total_paid),0) AS total_paid,
            COALESCE(SUM(outstanding_amount),0) AS outstanding,
            COALESCE(SUM(overdue_amount),0) AS overdue
         FROM (' . $base . ') r' . $outer
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'r.student_code','r.student_name','r.admission_no','r.course_name','r.batch_name',
        'r.mobile','r.total_fee','r.total_paid','r.outstanding_amount','r.next_installment_number',
        'r.next_due_date','r.next_due_amount','r.overdue_amount','r.payment_status'
    ];
    $order = $orderColumns[$m['order_column']] ?? 'r.overdue_amount';

    $sql = 'SELECT r.* FROM (' . $base . ') r' . $outer .
           ' ORDER BY ' . $order . ' ' . $m['order_dir'] . ',r.next_due_date ASC,r.student_name ASC
             LIMIT ' . $m['start'] . ',' . $m['length'];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentRef = encryptReference('college_student', (int)$row['student_id']);
        $admissionRef = encryptReference('college_admission', (int)$row['admission_id']);

        $rows[] = [
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'admission_no' => (string)$row['admission_no'],
            'course_label' => (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' => (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'total_fee' => round((float)$row['total_fee'],2),
            'total_paid' => round((float)$row['total_paid'],2),
            'outstanding_amount' => round((float)$row['outstanding_amount'],2),
            'next_emi_no' => ($row['next_installment_number'] ?? '') === '' ? null : (int)$row['next_installment_number'],
            'next_due_date' => (string)($row['next_due_date'] ?? ''),
            'next_due_amount' => round((float)($row['next_due_amount'] ?? 0),2),
            'overdue_amount' => round((float)$row['overdue_amount'],2),
            'payment_status' => (string)$row['payment_status'],
            'profile_url' => 'student-profile.php?ref=' . rawurlencode($studentRef),
            'payment_url' => 'student-payment.php?ref=' . rawurlencode($admissionRef),
        ];
    }

    return [
        'datatable' => [
            'draw' => $m['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'students' => (int)($summary['students'] ?? 0),
            'total_fee' => round((float)($summary['total_fee'] ?? 0),2),
            'total_paid' => round((float)($summary['total_paid'] ?? 0),2),
            'outstanding' => round((float)($summary['outstanding'] ?? 0),2),
            'overdue' => round((float)($summary['overdue'] ?? 0),2),
        ],
    ];
}

function ccr_payment_history(int $branchId): array
{
    return ccr_fee_collection($branchId);
}

function ccr_attendance(int $branchId): array
{
    $f = ccr_request_filters();
    $m = ccr_datatable_meta();

    $where = [
        'sa.branch_id=?',
        'sa.status=1',
        'ses.status=1'
    ];
    $params = [$branchId];

    if ($f['course_id'] > 0) {
        $where[] = 'a.course_id=?';
        $params[] = $f['course_id'];
    }

    if ($f['batch_id'] > 0) {
        $where[] = 'a.batch_id=?';
        $params[] = $f['batch_id'];
    }

    if (in_array($f['attendance_type'], [1,2], true)) {
        $where[] = 'ses.attendance_type=?';
        $params[] = $f['attendance_type'];
    }

    if ($f['subject_id'] > 0) {
        $where[] = 'ses.subject_id=?';
        $params[] = $f['subject_id'];
    }

    if (in_array($f['attendance_code'], ['P','A','L','LT'], true)) {
        $where[] = 'sa.attendance_code=?';
        $params[] = $f['attendance_code'];
    }

    if ($f['date_from']) {
        $where[] = 'ses.attendance_date>=?';
        $params[] = $f['date_from'];
    }

    if ($f['date_to']) {
        $where[] = 'ses.attendance_date<=?';
        $params[] = $f['date_to'];
    }

    if ($f['search'] !== '') {
        $like = '%' . $f['search'] . '%';
        $where[] = '(s.student_code LIKE ? OR s.student_name LIKE ? OR c.course_name LIKE ? OR b.batch_name LIKE ? OR cs.subject_name LIKE ?)';
        for ($i=0;$i<5;$i++) $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $base = "
        FROM college_student_attendance sa
        INNER JOIN college_attendance_sessions ses
                ON ses.id=sa.attendance_session_id
               AND ses.branch_id=sa.branch_id
        INNER JOIN college_admissions a
                ON a.id=sa.admission_id
               AND a.branch_id=sa.branch_id
        INNER JOIN college_students s
                ON s.id=a.student_id
               AND s.branch_id=a.branch_id
        INNER JOIN college_courses c
                ON c.id=a.course_id
               AND c.branch_id=a.branch_id
        INNER JOIN college_batches b
                ON b.id=a.batch_id
               AND b.branch_id=a.branch_id
        LEFT JOIN college_course_subjects cs
               ON cs.id=ses.subject_id
              AND cs.branch_id=ses.branch_id
    ";

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM college_student_attendance
         WHERE branch_id=? AND status=1'
    );
    $totalStmt->execute([$branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*) ' . $base . ' WHERE ' . $whereSql);
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        "SELECT
            COUNT(DISTINCT a.student_id) AS students,
            COUNT(*) AS total_records,
            SUM(CASE WHEN sa.attendance_code='P' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN sa.attendance_code='A' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN sa.attendance_code='L' THEN 1 ELSE 0 END) AS leave_count,
            SUM(CASE WHEN sa.attendance_code='LT' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN ses.attendance_type=1 THEN 1 ELSE 0 END) AS regular_total,
            SUM(CASE WHEN ses.attendance_type=1 AND sa.attendance_code='P' THEN 1 ELSE 0 END) AS regular_present,
            SUM(CASE WHEN ses.attendance_type=2 THEN 1 ELSE 0 END) AS practical_total,
            SUM(CASE WHEN ses.attendance_type=2 AND sa.attendance_code='P' THEN 1 ELSE 0 END) AS practical_present
         " . $base . "
         WHERE " . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'ses.attendance_date','s.student_code','s.student_name','c.course_name','b.batch_name',
        'ses.attendance_type','cs.subject_name','sa.attendance_code','sa.remarks'
    ];
    $order = $orderColumns[$m['order_column']] ?? 'ses.attendance_date';

    $sql = 'SELECT
                s.id AS student_id,
                ses.attendance_date,
                ses.attendance_type,
                sa.attendance_code,
                sa.remarks,
                s.student_code,
                s.student_name,
                c.course_code,
                c.course_name,
                b.batch_code,
                b.batch_name,
                cs.subject_code,
                cs.subject_name
            ' . $base . '
            WHERE ' . $whereSql . '
            ORDER BY ' . $order . ' ' . $m['order_dir'] . ',ses.id DESC,sa.id DESC
            LIMIT ' . $m['start'] . ',' . $m['length'];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentRef = encryptReference('college_student', (int)$row['student_id']);
        $code = (string)$row['attendance_code'];

        $rows[] = [
            'attendance_date' => (string)$row['attendance_date'],
            'student_code' => (string)$row['student_code'],
            'student_name' => (string)$row['student_name'],
            'course_label' => (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' => (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'attendance_type_label' => (int)$row['attendance_type'] === 2 ? 'Practical' : 'Regular',
            'subject_label' => $row['subject_name'] === null ? '-' : ((string)$row['subject_code'] . ' - ' . (string)$row['subject_name']),
            'attendance_code' => $code,
            'attendance_label' => match($code) {
                'P' => 'Present',
                'A' => 'Absent',
                'L' => 'Leave',
                'LT' => 'Late',
                default => 'Unknown',
            },
            'remarks' => (string)($row['remarks'] ?? ''),
            'profile_url' => 'student-profile.php?ref=' . rawurlencode($studentRef),
        ];
    }

    $regularTotal = (int)($summary['regular_total'] ?? 0);
    $regularPresent = (int)($summary['regular_present'] ?? 0);
    $practicalTotal = (int)($summary['practical_total'] ?? 0);
    $practicalPresent = (int)($summary['practical_present'] ?? 0);

    return [
        'datatable' => [
            'draw' => $m['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'students' => (int)($summary['students'] ?? 0),
            'present' => (int)($summary['present_count'] ?? 0),
            'absent' => (int)($summary['absent_count'] ?? 0),
            'leave' => (int)($summary['leave_count'] ?? 0),
            'late' => (int)($summary['late_count'] ?? 0),
            'regular_percentage' => $regularTotal > 0 ? round(($regularPresent/$regularTotal)*100,2) : 0.0,
            'practical_percentage' => $practicalTotal > 0 ? round(($practicalPresent/$practicalTotal)*100,2) : 0.0,
        ],
    ];
}

function ccr_batch_strength(int $branchId): array
{
    $f = ccr_request_filters();
    $m = ccr_datatable_meta();

    $where = ['b.branch_id=?'];
    $params = [$branchId];

    if ($f['course_id'] > 0) {
        $where[] = 'b.course_id=?';
        $params[] = $f['course_id'];
    }

    if ($f['batch_id'] > 0) {
        $where[] = 'b.id=?';
        $params[] = $f['batch_id'];
    }

    if ($f['search'] !== '') {
        $like = '%' . $f['search'] . '%';
        $where[] = '(c.course_code LIKE ? OR c.course_name LIKE ? OR b.batch_code LIKE ? OR b.batch_name LIKE ?)';
        for ($i=0;$i<4;$i++) $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $base = "
        FROM college_batches b
        INNER JOIN college_courses c
                ON c.id=b.course_id
               AND c.branch_id=b.branch_id
        LEFT JOIN college_admissions a
               ON a.batch_id=b.id
              AND a.branch_id=b.branch_id
              AND a.status=1
    ";

    $totalStmt = db()->prepare('SELECT COUNT(*) FROM college_batches WHERE branch_id=?');
    $totalStmt->execute([$branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM (
            SELECT b.id ' . $base . ' WHERE ' . $whereSql . ' GROUP BY b.id
         ) x'
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        "SELECT
            COUNT(DISTINCT b.id) AS batches,
            COUNT(DISTINCT b.course_id) AS courses,
            COUNT(DISTINCT CASE WHEN a.admission_state='active' THEN a.student_id END) AS active_students,
            COUNT(DISTINCT a.student_id) AS total_students
         " . $base . "
         WHERE " . $whereSql
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'c.course_name','b.batch_name','b.start_date','b.end_date',
        'total_students','active_students','completed_students','b.status'
    ];
    $order = $orderColumns[$m['order_column']] ?? 'c.course_name';

    $sql = "SELECT
                c.course_code,
                c.course_name,
                b.batch_code,
                b.batch_name,
                b.start_date,
                b.end_date,
                b.status,
                COUNT(DISTINCT a.student_id) AS total_students,
                COUNT(DISTINCT CASE WHEN a.admission_state='active' THEN a.student_id END) AS active_students,
                COUNT(DISTINCT CASE WHEN a.admission_state='completed' THEN a.student_id END) AS completed_students
            " . $base . "
            WHERE " . $whereSql . "
            GROUP BY
                b.id,c.course_code,c.course_name,b.batch_code,b.batch_name,
                b.start_date,b.end_date,b.status
            ORDER BY " . $order . " " . $m['order_dir'] . ",b.id DESC
            LIMIT " . $m['start'] . "," . $m['length'];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'course_label' => (string)$row['course_code'] . ' - ' . (string)$row['course_name'],
            'batch_label' => (string)$row['batch_code'] . ' - ' . (string)$row['batch_name'],
            'start_date' => (string)$row['start_date'],
            'end_date' => (string)($row['end_date'] ?? ''),
            'total_students' => (int)$row['total_students'],
            'active_students' => (int)$row['active_students'],
            'completed_students' => (int)$row['completed_students'],
            'status' => (int)$row['status'],
        ];
    }

    return [
        'datatable' => [
            'draw' => $m['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'courses' => (int)($summary['courses'] ?? 0),
            'batches' => (int)($summary['batches'] ?? 0),
            'active_students' => (int)($summary['active_students'] ?? 0),
            'total_students' => (int)($summary['total_students'] ?? 0),
        ],
    ];
}

ccr_require_schema();

$method = request_method();

if ($method !== 'GET') {
    json_error('Community College reports are read-only.', 405);
}

$report = trim((string)($_GET['report'] ?? ''));

$reportPages = [
    'student_admission' => 'student-admission-report.php',
    'fee_collection' => 'fee-collection-report.php',
    'fee_due' => 'fee-due-report.php',
    'payment_history' => 'payment-history-report.php',
    'attendance' => 'attendance-report.php',
    'batch_strength' => 'course-batch-strength-report.php',
];

if (!isset($reportPages[$report])) {
    json_error('Invalid Community College report.', 422);
}

$access = require_permission($reportPages[$report], ACTION_VIEW);
$ctx = ccr_context($access['user']);
$branchId = (int)$ctx['branch_id'];

if (isset($_GET['options'])) {
    json_success(
        'Community College report options loaded.',
        [
            'allowed_actions' => $access['actions'],
            'today' => date('Y-m-d'),
            'options' => ccr_options($branchId),
        ]
    );
}

$result = match($report) {
    'student_admission' => ccr_student_admission($branchId),
    'fee_collection' => ccr_fee_collection($branchId),
    'fee_due' => ccr_fee_due($branchId),
    'payment_history' => ccr_payment_history($branchId),
    'attendance' => ccr_attendance($branchId),
    'batch_strength' => ccr_batch_strength($branchId),
};

$result['allowed_actions'] = $access['actions'];
$result['options'] = ccr_options($branchId);

json_success('Community College report loaded.', $result);
