<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Community College Fee Due / Outstanding Report
 *
 * Read-only report.
 *
 * Total Fee:
 *   college_student_fee_plans.net_payable
 *
 * Total Paid:
 *   Valid posted and non-reversed receipts.
 *
 * Outstanding:
 *   Total Fee - Total Paid
 *
 * EMI Paid:
 *   Valid receipt allocations only.
 *
 * Overdue:
 *   Unpaid / partially paid EMI amount where due_date < today.
 *
 * Payment Status:
 *   Paid
 *   Partially Paid
 *   Pending
 *   Overdue
 */

function fdr_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error(
            'Fee Due Report is available only for tenant users.',
            403
        );
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error(
            'No active branch is assigned to your account.',
            403
        );
    }

    $stmt = db()->prepare(
        'SELECT
            b.id AS branch_id,
            b.company_id,
            b.branch_name,
            c.company_name
         FROM branches b
         INNER JOIN companies c
                 ON c.id=b.company_id
         WHERE b.id=:branch_id
           AND b.status=1
           AND c.status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Your assigned tenant branch is invalid or inactive.',
            403
        );
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function fdr_require_schema(): void
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
    ];

    foreach ($tables as $table) {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=:table_name'
        );

        $stmt->execute([
            ':table_name' => $table
        ]);

        if ((int)$stmt->fetchColumn() !== 1) {
            json_error(
                'Fee Due Report database setup is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing table: ' .
                        $table .
                        '.'
                ]
            );
        }
    }

    $required = [
        'college_admissions' => [
            'id',
            'branch_id',
            'admission_no',
            'student_id',
            'course_id',
            'batch_id',
            'admission_date',
            'admission_state',
            'status',
        ],
        'college_student_fee_plans' => [
            'id',
            'branch_id',
            'admission_id',
            'net_payable',
            'installment_count',
            'payment_type',
            'first_due_date',
            'status',
        ],
        'college_fee_installments' => [
            'id',
            'student_fee_plan_id',
            'branch_id',
            'installment_number',
            'due_date',
            'due_amount',
            'waived_amount',
            'status',
        ],
        'college_fee_receipts' => [
            'id',
            'branch_id',
            'admission_id',
            'student_fee_plan_id',
            'receipt_date',
            'amount',
            'receipt_type',
            'payment_against',
            'posting_status',
            'reversed_at',
            'status',
        ],
        'college_fee_receipt_allocations' => [
            'id',
            'fee_receipt_id',
            'fee_installment_id',
            'branch_id',
            'allocated_amount',
            'status',
        ],
    ];

    foreach ($required as $table => $columns) {
        $marks = implode(
            ',',
            array_fill(
                0,
                count($columns),
                '?'
            )
        );

        $stmt = db()->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=?
               AND COLUMN_NAME IN (' .
            $marks .
            ')'
        );

        $stmt->execute(
            array_merge(
                [$table],
                $columns
            )
        );

        $found = array_map(
            'strtolower',
            array_column(
                $stmt->fetchAll(PDO::FETCH_ASSOC),
                'COLUMN_NAME'
            )
        );

        $missing = array_values(
            array_diff(
                $columns,
                $found
            )
        );

        if ($missing !== []) {
            json_error(
                'Fee Due Report database structure is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing ' .
                        $table .
                        ' columns: ' .
                        implode(', ', $missing) .
                        '.'
                ]
            );
        }
    }

    $checked = true;
}

function fdr_valid_date($value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $value;
}

function fdr_options(int $branchId): array
{
    $courseStmt = db()->prepare(
        'SELECT
            id,
            course_code,
            course_name,
            status
         FROM college_courses
         WHERE branch_id=:branch_id
         ORDER BY
            status DESC,
            course_name,
            course_code'
    );

    $courseStmt->execute([
        ':branch_id' => $branchId
    ]);

    $batchStmt = db()->prepare(
        'SELECT
            id,
            course_id,
            batch_code,
            batch_name,
            status
         FROM college_batches
         WHERE branch_id=:branch_id
         ORDER BY
            status DESC,
            start_date DESC,
            batch_name,
            batch_code'
    );

    $batchStmt->execute([
        ':branch_id' => $branchId
    ]);

    return [
        'courses' =>
            $courseStmt->fetchAll(
                PDO::FETCH_ASSOC
            ),
        'batches' =>
            $batchStmt->fetchAll(
                PDO::FETCH_ASSOC
            ),
    ];
}

/**
 * The branch_id parameter appears exactly once in this base query.
 * Outer filters use positional placeholders, avoiding duplicate named
 * placeholders and SQLSTATE[HY093] issues.
 */
function fdr_base_sql(): string
{
    return "
        SELECT
            p.id AS plan_id,
            a.id AS admission_id,
            s.id AS student_id,

            s.student_code,
            s.student_name,
            s.mobile,

            a.admission_no,
            a.admission_date,
            a.admission_state,

            c.id AS course_id,
            c.course_code,
            c.course_name,

            b.id AS batch_id,
            b.batch_code,
            b.batch_name,

            ROUND(
                p.net_payable,
                2
            ) AS total_fee,

            ROUND(
                COALESCE(
                    pay.total_paid,
                    0
                ),
                2
            ) AS total_paid,

            ROUND(
                GREATEST(
                    p.net_payable -
                    COALESCE(
                        pay.total_paid,
                        0
                    ),
                    0
                ),
                2
            ) AS outstanding_amount,

            ROUND(
                COALESCE(
                    emi.overdue_amount,
                    0
                ),
                2
            ) AS overdue_amount,

            NULLIF(
                emi.next_installment_number,
                ''
            ) AS next_installment_number,

            NULLIF(
                emi.next_due_date,
                ''
            ) AS next_due_date,

            ROUND(
                COALESCE(
                    NULLIF(
                        emi.next_due_amount,
                        ''
                    ),
                    0
                ),
                2
            ) AS next_due_amount,

            CASE
                WHEN
                    GREATEST(
                        p.net_payable -
                        COALESCE(
                            pay.total_paid,
                            0
                        ),
                        0
                    ) <= 0.009
                THEN 'Paid'

                WHEN
                    COALESCE(
                        emi.overdue_amount,
                        0
                    ) > 0.009
                THEN 'Overdue'

                WHEN
                    COALESCE(
                        pay.total_paid,
                        0
                    ) > 0.009
                THEN 'Partially Paid'

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
            SELECT
                branch_id,
                admission_id,
                ROUND(
                    SUM(amount),
                    2
                ) AS total_paid
            FROM college_fee_receipts
            WHERE status=1
              AND posting_status=1
              AND reversed_at IS NULL
            GROUP BY
                branch_id,
                admission_id
        ) pay
               ON pay.branch_id=p.branch_id
              AND pay.admission_id=a.id

        LEFT JOIN (
            SELECT
                x.branch_id,
                x.student_fee_plan_id,

                ROUND(
                    SUM(
                        CASE
                            WHEN
                                x.outstanding_amount >
                                0.009
                                AND x.due_date <
                                CURDATE()
                            THEN x.outstanding_amount
                            ELSE 0
                        END
                    ),
                    2
                ) AS overdue_amount,

                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        CASE
                            WHEN
                                x.outstanding_amount >
                                0.009
                            THEN x.installment_number
                            ELSE NULL
                        END
                        ORDER BY
                            x.due_date,
                            x.installment_number,
                            x.installment_id
                        SEPARATOR ','
                    ),
                    ',',
                    1
                ) AS next_installment_number,

                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        CASE
                            WHEN
                                x.outstanding_amount >
                                0.009
                            THEN DATE_FORMAT(
                                x.due_date,
                                '%Y-%m-%d'
                            )
                            ELSE NULL
                        END
                        ORDER BY
                            x.due_date,
                            x.installment_number,
                            x.installment_id
                        SEPARATOR ','
                    ),
                    ',',
                    1
                ) AS next_due_date,

                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        CASE
                            WHEN
                                x.outstanding_amount >
                                0.009
                            THEN CAST(
                                x.outstanding_amount
                                AS CHAR
                            )
                            ELSE NULL
                        END
                        ORDER BY
                            x.due_date,
                            x.installment_number,
                            x.installment_id
                        SEPARATOR ','
                    ),
                    ',',
                    1
                ) AS next_due_amount

            FROM (
                SELECT
                    i.id AS installment_id,
                    i.branch_id,
                    i.student_fee_plan_id,
                    i.installment_number,
                    i.due_date,

                    ROUND(
                        GREATEST(
                            (
                                i.due_amount -
                                i.waived_amount
                            ) -
                            COALESCE(
                                SUM(
                                    CASE
                                        WHEN r.id IS NOT NULL
                                        THEN al.allocated_amount
                                        ELSE 0
                                    END
                                ),
                                0
                            ),
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
                    i.id,
                    i.branch_id,
                    i.student_fee_plan_id,
                    i.installment_number,
                    i.due_date,
                    i.due_amount,
                    i.waived_amount
            ) x

            GROUP BY
                x.branch_id,
                x.student_fee_plan_id
        ) emi
               ON emi.branch_id=p.branch_id
              AND emi.student_fee_plan_id=p.id

        WHERE p.branch_id=?
          AND p.status=1
          AND a.status=1
          AND a.admission_state<>'cancelled'
    ";
}

function fdr_filters(): array
{
    $where = [];
    $params = [];

    $search =
        trim(
            (string)(
                $_GET['search']['value'] ??
                ''
            )
        );

    $courseId =
        (int)(
            $_GET['course_id'] ??
            0
        );

    $batchId =
        (int)(
            $_GET['batch_id'] ??
            0
        );

    $paymentStatus =
        trim(
            (string)(
                $_GET['payment_status'] ??
                ''
            )
        );

    $dueFromText =
        trim(
            (string)(
                $_GET['due_from'] ??
                ''
            )
        );

    $dueToText =
        trim(
            (string)(
                $_GET['due_to'] ??
                ''
            )
        );

    $dueFrom =
        $dueFromText === ''
            ? null
            : fdr_valid_date(
                $dueFromText
            );

    $dueTo =
        $dueToText === ''
            ? null
            : fdr_valid_date(
                $dueToText
            );

    if (
        $dueFromText !== '' &&
        $dueFrom === null
    ) {
        json_error(
            'Enter a valid Due From Date.',
            422,
            [
                'due_from' =>
                    'Enter a valid Due From Date.'
            ]
        );
    }

    if (
        $dueToText !== '' &&
        $dueTo === null
    ) {
        json_error(
            'Enter a valid Due To Date.',
            422,
            [
                'due_to' =>
                    'Enter a valid Due To Date.'
            ]
        );
    }

    if (
        $dueFrom !== null &&
        $dueTo !== null &&
        $dueFrom > $dueTo
    ) {
        json_error(
            'Due From Date cannot be after Due To Date.',
            422,
            [
                'due_from' =>
                    'Due From Date cannot be after Due To Date.'
            ]
        );
    }

    if ($courseId > 0) {
        $where[] =
            'report_row.course_id=?';

        $params[] =
            $courseId;
    }

    if ($batchId > 0) {
        $where[] =
            'report_row.batch_id=?';

        $params[] =
            $batchId;
    }

    $validStatuses = [
        'Paid',
        'Partially Paid',
        'Pending',
        'Overdue',
    ];

    if (
        $paymentStatus !== '' &&
        in_array(
            $paymentStatus,
            $validStatuses,
            true
        )
    ) {
        $where[] =
            'report_row.payment_status=?';

        $params[] =
            $paymentStatus;
    }

    if ($dueFrom !== null) {
        $where[] =
            'report_row.next_due_date IS NOT NULL';

        $where[] =
            'report_row.next_due_date>=?';

        $params[] =
            $dueFrom;
    }

    if ($dueTo !== null) {
        $where[] =
            'report_row.next_due_date IS NOT NULL';

        $where[] =
            'report_row.next_due_date<=?';

        $params[] =
            $dueTo;
    }

    if ($search !== '') {
        $like =
            '%' . $search . '%';

        $where[] =
            '(
                report_row.student_code LIKE ?
                OR report_row.student_name LIKE ?
                OR report_row.mobile LIKE ?
                OR report_row.admission_no LIKE ?
                OR report_row.course_code LIKE ?
                OR report_row.course_name LIKE ?
                OR report_row.batch_code LIKE ?
                OR report_row.batch_name LIKE ?
            )';

        for ($i = 0; $i < 8; $i++) {
            $params[] =
                $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

fdr_require_schema();

$method = request_method();

if (
    $method === 'GET' &&
    isset($_GET['options'])
) {
    $access =
        require_permission(
            'fee-due-report.php',
            ACTION_VIEW
        );

    $ctx =
        fdr_context(
            $access['user']
        );

    json_success(
        'Fee Due Report options loaded.',
        [
            'allowed_actions' =>
                $access['actions'],
            'today' =>
                date('Y-m-d'),
            'options' =>
                fdr_options(
                    (int)$ctx['branch_id']
                ),
        ]
    );
}

if (
    $method === 'GET' &&
    isset($_GET['datatable'])
) {
    $access =
        require_permission(
            'fee-due-report.php',
            ACTION_VIEW
        );

    $ctx =
        fdr_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $draw =
        max(
            0,
            (int)($_GET['draw'] ?? 0)
        );

    $start =
        max(
            0,
            (int)($_GET['start'] ?? 0)
        );

    $requestedLength =
        (int)(
            $_GET['length'] ??
            10
        );

    $length =
        $requestedLength < 0
            ? 100000
            : min(
                100000,
                max(
                    1,
                    $requestedLength
                )
            );

    $filters =
        fdr_filters();

    $outerWhere =
        $filters['where'] === []
            ? ''
            : (
                ' WHERE ' .
                implode(
                    ' AND ',
                    $filters['where']
                )
            );

    $baseSql =
        fdr_base_sql();

    $totalSql =
        'SELECT COUNT(*)
         FROM (' .
        $baseSql .
        ') report_row';

    $totalStmt =
        db()->prepare(
            $totalSql
        );

    $totalStmt->execute([
        $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $filteredParams =
        array_merge(
            [$branchId],
            $filters['params']
        );

    $countSql =
        'SELECT COUNT(*)
         FROM (' .
        $baseSql .
        ') report_row' .
        $outerWhere;

    $countStmt =
        db()->prepare(
            $countSql
        );

    $countStmt->execute(
        $filteredParams
    );

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $summarySql =
        'SELECT
            COUNT(*) AS student_count,
            ROUND(
                COALESCE(
                    SUM(
                        report_row.total_fee
                    ),
                    0
                ),
                2
            ) AS total_fee,
            ROUND(
                COALESCE(
                    SUM(
                        report_row.total_paid
                    ),
                    0
                ),
                2
            ) AS total_paid,
            ROUND(
                COALESCE(
                    SUM(
                        report_row.outstanding_amount
                    ),
                    0
                ),
                2
            ) AS total_outstanding,
            ROUND(
                COALESCE(
                    SUM(
                        report_row.overdue_amount
                    ),
                    0
                ),
                2
            ) AS total_overdue
         FROM (' .
        $baseSql .
        ') report_row' .
        $outerWhere;

    $summaryStmt =
        db()->prepare(
            $summarySql
        );

    $summaryStmt->execute(
        $filteredParams
    );

    $summary =
        $summaryStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $orderColumns = [
        'report_row.student_code',
        'report_row.student_name',
        'report_row.admission_no',
        'report_row.course_name',
        'report_row.batch_name',
        'report_row.mobile',
        'report_row.total_fee',
        'report_row.total_paid',
        'report_row.outstanding_amount',
        'report_row.next_installment_number',
        'report_row.next_due_date',
        'report_row.next_due_amount',
        'report_row.overdue_amount',
        'report_row.payment_status',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column'] ??
            12
        );

    $orderDir =
        strtolower(
            (string)(
                $_GET['order'][0]['dir'] ??
                'desc'
            )
        ) === 'asc'
            ? 'ASC'
            : 'DESC';

    $orderColumn =
        $orderColumns[$orderIndex] ??
        'report_row.overdue_amount';

    $dataSql =
        'SELECT report_row.*
         FROM (' .
        $baseSql .
        ') report_row' .
        $outerWhere .
        ' ORDER BY ' .
        $orderColumn .
        ' ' .
        $orderDir .
        ',
        report_row.next_due_date ASC,
        report_row.student_name ASC,
        report_row.student_code ASC
        LIMIT ' .
        $start .
        ',' .
        $length;

    $dataStmt =
        db()->prepare(
            $dataSql
        );

    $dataStmt->execute(
        $filteredParams
    );

    $rows = [];

    foreach (
        $dataStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $studentRef =
            encryptReference(
                'college_student',
                (int)$row['student_id']
            );

        $admissionRef =
            encryptReference(
                'college_admission',
                (int)$row['admission_id']
            );

        $nextEmiNo =
            trim(
                (string)(
                    $row[
                        'next_installment_number'
                    ] ?? ''
                )
            );

        $rows[] = [
            'student_code' =>
                (string)$row[
                    'student_code'
                ],
            'student_name' =>
                (string)$row[
                    'student_name'
                ],
            'admission_no' =>
                (string)$row[
                    'admission_no'
                ],
            'course_label' =>
                (string)$row[
                    'course_code'
                ] .
                ' - ' .
                (string)$row[
                    'course_name'
                ],
            'batch_label' =>
                (string)$row[
                    'batch_code'
                ] .
                ' - ' .
                (string)$row[
                    'batch_name'
                ],
            'mobile' =>
                (string)(
                    $row['mobile'] ??
                    ''
                ),
            'total_fee' =>
                round(
                    (float)$row[
                        'total_fee'
                    ],
                    2
                ),
            'total_paid' =>
                round(
                    (float)$row[
                        'total_paid'
                    ],
                    2
                ),
            'outstanding_amount' =>
                round(
                    (float)$row[
                        'outstanding_amount'
                    ],
                    2
                ),
            'next_emi_no' =>
                $nextEmiNo === ''
                    ? null
                    : (int)$nextEmiNo,
            'next_due_date' =>
                (string)(
                    $row[
                        'next_due_date'
                    ] ?? ''
                ),
            'next_due_amount' =>
                round(
                    (float)$row[
                        'next_due_amount'
                    ],
                    2
                ),
            'overdue_amount' =>
                round(
                    (float)$row[
                        'overdue_amount'
                    ],
                    2
                ),
            'payment_status' =>
                (string)$row[
                    'payment_status'
                ],
            'profile_url' =>
                'student-profile.php?ref=' .
                rawurlencode(
                    $studentRef
                ),
            'payment_url' =>
                'student-payment.php?ref=' .
                rawurlencode(
                    $admissionRef
                ),
        ];
    }

    json_success(
        'Fee Due Report loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' =>
                    $recordsTotal,
                'recordsFiltered' =>
                    $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'student_count' =>
                    (int)(
                        $summary[
                            'student_count'
                        ] ?? 0
                    ),
                'total_fee' =>
                    round(
                        (float)(
                            $summary[
                                'total_fee'
                            ] ?? 0
                        ),
                        2
                    ),
                'total_paid' =>
                    round(
                        (float)(
                            $summary[
                                'total_paid'
                            ] ?? 0
                        ),
                        2
                    ),
                'total_outstanding' =>
                    round(
                        (float)(
                            $summary[
                                'total_outstanding'
                            ] ?? 0
                        ),
                        2
                    ),
                'total_overdue' =>
                    round(
                        (float)(
                            $summary[
                                'total_overdue'
                            ] ?? 0
                        ),
                        2
                    ),
            ],
            'allowed_actions' =>
                $access['actions'],
            'options' =>
                fdr_options(
                    $branchId
                ),
        ]
    );
}

json_error(
    'Unsupported request.',
    405
);
