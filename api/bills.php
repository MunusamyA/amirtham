<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM Clinic - Consultation Based Billing API
 *
 * Billing starts from Patient, but every Bill is tied to ONE Consultation.
 * Diagnosis does not directly control billing.
 *
 * Pending bill sources:
 * - CONSULTATION : clinic_consultations.id + consultation_fee
 * - MEDICINE     : clinic_consultation_prescriptions.id
 * - TREATMENT    : clinic_patient_treatment_progress.id (Completed only)
 * - LAB          : clinic_lab_tests.id (optional add-on)
 */

function bill_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error(
            'Clinic Billing is available only for tenant users.',
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
        ':branch_id' => $branchId,
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

function bill_require_table(
    string $table,
    array $columns
): void {
    try {
        $stmt = db()->query(
            'SHOW COLUMNS FROM `' .
            str_replace('`', '``', $table) .
            '`'
        );

        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    } catch (Throwable $e) {
        json_error(
            'Clinic Billing database setup is incomplete.',
            500,
            [
                'schema' =>
                    'Missing table: ' .
                    $table .
                    '. Run clinic-patient-billing-upgrade.php once.'
            ]
        );
    }

    $found =
        array_map(
            'strtolower',
            array_column(
                $rows,
                'Field'
            )
        );

    $missing =
        array_values(
            array_diff(
                $columns,
                $found
            )
        );

    if ($missing !== []) {
        json_error(
            'Clinic Billing database structure is incomplete.',
            500,
            [
                'schema' =>
                    'Missing ' .
                    $table .
                    ' columns: ' .
                    implode(', ', $missing) .
                    '. Run clinic-patient-billing-upgrade.php once.'
            ]
        );
    }
}

function bill_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    bill_require_table(
        'clinic_patients',
        [
            'id',
            'branch_id',
            'patient_code',
            'patient_name',
            'mobile',
            'date_of_birth',
            'gender',
            'status',
        ]
    );

    bill_require_table(
        'clinic_consultations',
        [
            'id',
            'branch_id',
            'consultation_no',
            'patient_id',
            'consultation_fee',
            'visit_date',
            'status',
        ]
    );

    bill_require_table(
        'clinic_consultation_prescriptions',
        [
            'id',
            'branch_id',
            'consultation_id',
            'medicine_id',
            'quantity',
            'unit_price',
            'amount',
            'status',
        ]
    );

    bill_require_table(
        'clinic_medicines',
        [
            'id',
            'branch_id',
            'medicine_code',
            'medicine_name',
            'selling_price',
            'status',
        ]
    );

    bill_require_table(
        'clinic_patient_treatment_plans',
        [
            'id',
            'branch_id',
            'consultation_id',
            'status',
        ]
    );

    bill_require_table(
        'clinic_patient_treatment_days',
        [
            'id',
            'branch_id',
            'plan_id',
            'day_number',
            'treatment_procedure_id',
            'status',
        ]
    );

    bill_require_table(
        'clinic_patient_treatment_progress',
        [
            'id',
            'branch_id',
            'treatment_day_id',
            'treatment_date',
            'treatment_status',
        ]
    );

    bill_require_table(
        'clinic_treatment_procedures',
        [
            'id',
            'branch_id',
            'procedure_code',
            'procedure_name',
            'price',
            'status',
        ]
    );

    bill_require_table(
        'clinic_lab_tests',
        [
            'id',
            'branch_id',
            'lab_test_code',
            'lab_test_name',
            'price',
            'status',
        ]
    );

    bill_require_table(
        'clinic_bills',
        [
            'id',
            'branch_id',
            'bill_no',
            'patient_id',
            'consultation_id',
            'bill_date',
            'subtotal',
            'discount_amount',
            'grand_total',
            'paid_amount',
            'balance_amount',
            'payment_status',
            'notes',
            'status',
            'created_by',
            'created_at',
            'updated_at',
        ]
    );

    bill_require_table(
        'clinic_bill_items',
        [
            'id',
            'branch_id',
            'bill_id',
            'item_type',
            'reference_id',
            'description',
            'quantity',
            'unit_price',
            'amount',
            'status',
            'created_by',
            'created_at',
            'updated_at',
        ]
    );

    bill_require_table(
        'accounts',
        [
            'id',
            'branch_id',
            'account_code',
            'account_name',
            'account_type',
            'status',
        ]
    );

    bill_require_table(
        'clinic_bill_payments',
        [
            'id',
            'branch_id',
            'bill_id',
            'payment_mode',
            'account_id',
            'account_name',
            'amount',
            'reference_no',
            'payment_date',
            'sort_order',
            'status',
            'created_by',
            'created_at',
            'updated_at',
        ]
    );

    $checked = true;
}

function bill_ref_to_id($value): int
{
    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        json_error(
            'Bill reference is required.',
            422
        );
    }

    try {
        return decryptReference(
            trim($value),
            'clinic_bill'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Bill reference.',
            422
        );
    }
}

function bill_patient_ref_to_id($value): int
{
    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        json_error(
            'Patient is required.',
            422,
            [
                'patient_ref' =>
                    'Patient is required.'
            ]
        );
    }

    try {
        return decryptReference(
            trim($value),
            'clinic_patient'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Patient reference.',
            422,
            [
                'patient_ref' =>
                    'Select a valid Patient.'
            ]
        );
    }
}

function bill_consultation_ref_to_id(
    $value,
    bool $required = true
): int {
    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        if ($required) {
            json_error(
                'Consultation is required.',
                422,
                [
                    'consultation_ref' =>
                        'Consultation is required.'
                ]
            );
        }

        return 0;
    }

    try {
        return decryptReference(
            trim($value),
            'clinic_consultation'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Consultation reference.',
            422,
            [
                'consultation_ref' =>
                    'Select a valid Consultation.'
            ]
        );
    }
}

function bill_consultation(
    array $ctx,
    int $consultationId
): array {
    $stmt = db()->prepare(
        'SELECT
            c.id,
            c.consultation_no,
            c.patient_id,
            c.visit_date,
            c.visit_time,
            c.consultant_name,
            c.consultation_fee,
            c.consultation_status,
            c.status,
            p.patient_code,
            p.patient_name,
            p.mobile,
            p.gender,
            p.date_of_birth,
            CASE
                WHEN p.date_of_birth IS NULL
                    THEN NULL
                ELSE TIMESTAMPDIFF(
                    YEAR,
                    p.date_of_birth,
                    CURDATE()
                )
            END AS age
         FROM clinic_consultations c
         INNER JOIN clinic_patients p
                 ON p.id=c.patient_id
                AND p.branch_id=c.branch_id
         WHERE c.id=:id
           AND c.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $consultationId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Consultation was not found.',
            404
        );
    }

    $row['id'] = (int)$row['id'];
    $row['patient_id'] = (int)$row['patient_id'];
    $row['status'] = (int)$row['status'];
    $row['age'] =
        $row['age'] === null
            ? null
            : (int)$row['age'];

    $row['ref'] =
        encryptReference(
            'clinic_consultation',
            (int)$row['id']
        );

    $row['patient_ref'] =
        encryptReference(
            'clinic_patient',
            (int)$row['patient_id']
        );

    if (!empty($row['visit_time'])) {
        $row['visit_time'] =
            substr(
                (string)$row['visit_time'],
                0,
                5
            );
    }

    return $row;
}

function bill_pending_consultation_options(
    array $ctx,
    int $patientId
): array {
    $stmt = db()->prepare(
        'SELECT
            c.id,
            c.consultation_no,
            c.visit_date,
            c.visit_time,
            c.consultant_name,
            c.consultation_status
         FROM clinic_consultations c
         WHERE c.branch_id=:branch_id
           AND c.patient_id=:patient_id
           AND c.status=1
           AND NOT EXISTS
           (
               SELECT 1
               FROM clinic_bills b
               WHERE b.branch_id=c.branch_id
                 AND b.consultation_id=c.id
                 AND b.status=1
           )
         ORDER BY
            c.visit_date DESC,
            c.visit_time DESC,
            c.id DESC'
    );

    $stmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
        ':patient_id' => $patientId,
    ]);

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $time =
            !empty($row['visit_time'])
                ? substr(
                    (string)$row['visit_time'],
                    0,
                    5
                )
                : '';

        $label =
            (string)$row['consultation_no'] .
            ' - ' .
            (string)$row['visit_date'];

        if ($time !== '') {
            $label .= ' ' . $time;
        }

        if (!empty($row['consultant_name'])) {
            $label .=
                ' - ' .
                (string)$row['consultant_name'];
        }

        $rows[] = [
            'ref' =>
                encryptReference(
                    'clinic_consultation',
                    (int)$row['id']
                ),
            'consultation_no' =>
                (string)$row['consultation_no'],
            'visit_date' =>
                $row['visit_date'],
            'visit_time' =>
                $time,
            'consultant_name' =>
                $row['consultant_name'],
            'consultation_status' =>
                (string)$row['consultation_status'],
            'label' =>
                $label,
        ];
    }

    return $rows;
}

function bill_source_ref_to_id(
    string $type,
    $value
): int {
    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        json_error(
            'Billing source reference is required.',
            422
        );
    }

    $map = [
        'CONSULTATION' =>
            'clinic_consultation',

        'MEDICINE' =>
            'clinic_prescription_item',

        'TREATMENT' =>
            'clinic_treatment_progress',

        'LAB' =>
            'clinic_lab_test',
    ];

    if (!isset($map[$type])) {
        json_error(
            'Invalid Billing item type.',
            422
        );
    }

    try {
        return decryptReference(
            trim($value),
            $map[$type]
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Billing source reference.',
            422
        );
    }
}

function bill_money(
    $value,
    string $field
): string {
    $raw =
        trim(
            (string)$value
        );

    if (
        $raw === '' ||
        !is_numeric($raw)
    ) {
        json_error(
            'Billing validation failed.',
            422,
            [
                $field =>
                    'Enter a valid amount.'
            ]
        );
    }

    $amount =
        round(
            (float)$raw,
            2
        );

    if ($amount < 0) {
        json_error(
            'Billing validation failed.',
            422,
            [
                $field =>
                    'Amount cannot be negative.'
            ]
        );
    }

    return number_format(
        $amount,
        2,
        '.',
        ''
    );
}

function bill_quantity(
    $value,
    string $field
): string {
    $raw =
        trim(
            (string)$value
        );

    if (
        $raw === '' ||
        !is_numeric($raw)
    ) {
        json_error(
            'Billing validation failed.',
            422,
            [
                $field =>
                    'Enter a valid Quantity.'
            ]
        );
    }

    $qty =
        round(
            (float)$raw,
            3
        );

    if ($qty <= 0) {
        json_error(
            'Billing validation failed.',
            422,
            [
                $field =>
                    'Quantity must be greater than zero.'
            ]
        );
    }

    return number_format(
        $qty,
        3,
        '.',
        ''
    );
}

function bill_valid_date($value): ?string
{
    $value =
        trim(
            (string)$value
        );

    if ($value === '') {
        return null;
    }

    $date =
        DateTime::createFromFormat(
            'Y-m-d',
            $value
        );

    if (
        !$date ||
        $date->format('Y-m-d') !==
        $value
    ) {
        return null;
    }

    return $value;
}

function bill_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT bill_no
         FROM clinic_bills
         WHERE branch_id=:branch_id
           AND bill_no REGEXP '^BIL[0-9]+$'
         ORDER BY
            CAST(
                SUBSTRING(
                    bill_no,
                    4
                )
                AS UNSIGNED
            ) DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':branch_id' =>
            $branchId,
    ]);

    $last =
        (string)(
            $stmt->fetchColumn() ?:
            ''
        );

    $next = 1;

    if (
        $last !== '' &&
        preg_match(
            '/^BIL([0-9]+)$/i',
            $last,
            $match
        )
    ) {
        $next =
            ((int)$match[1]) + 1;
    }

    return
        'BIL' .
        str_pad(
            (string)$next,
            5,
            '0',
            STR_PAD_LEFT
        );
}

function bill_patient(
    array $ctx,
    int $patientId
): array {
    $stmt = db()->prepare(
        'SELECT
            p.id,
            p.patient_code,
            p.patient_name,
            p.mobile,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.status,
            CASE
                WHEN p.date_of_birth IS NULL
                    THEN NULL
                ELSE TIMESTAMPDIFF(
                    YEAR,
                    p.date_of_birth,
                    CURDATE()
                )
            END AS age
         FROM clinic_patients p
         WHERE p.id=:id
           AND p.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' =>
            $patientId,

        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        json_error(
            'Patient was not found.',
            404
        );
    }

    $row['id'] =
        (int)$row['id'];

    $row['status'] =
        (int)$row['status'];

    $row['age'] =
        $row['age'] === null
            ? null
            : (int)$row['age'];

    $row['ref'] =
        encryptReference(
            'clinic_patient',
            (int)$row['id']
        );

    return $row;
}

function bill_patient_options(
    array $ctx,
    int $includeId = 0
): array {
    $sql =
        'SELECT
            id,
            patient_code,
            patient_name,
            mobile,
            status
         FROM clinic_patients
         WHERE branch_id=:branch_id
           AND (status=1';

    $params = [
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ];

    if ($includeId > 0) {
        $sql .= ' OR id=:include_id';
        $params[':include_id'] =
            $includeId;
    }

    $sql .= ')
         ORDER BY patient_name,id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $rows[] = [
            'ref' =>
                encryptReference(
                    'clinic_patient',
                    (int)$row['id']
                ),

            'patient_code' =>
                (string)$row['patient_code'],

            'patient_name' =>
                (string)$row['patient_name'],

            'mobile' =>
                (string)($row['mobile'] ?? ''),

            'label' =>
                (string)$row['patient_code'] .
                ' - ' .
                (string)$row['patient_name'],
        ];
    }

    return $rows;
}

function bill_lab_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            lab_test_code,
            lab_test_name,
            price
         FROM clinic_lab_tests
         WHERE branch_id=:branch_id
           AND status=1
         ORDER BY lab_test_name,id'
    );

    $stmt->execute([
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $rows[] = [
            'ref' =>
                encryptReference(
                    'clinic_lab_test',
                    (int)$row['id']
                ),

            'lab_test_code' =>
                (string)$row['lab_test_code'],

            'lab_test_name' =>
                (string)$row['lab_test_name'],

            'price' =>
                number_format(
                    (float)$row['price'],
                    2,
                    '.',
                    ''
                ),

            'label' =>
                (string)$row['lab_test_code'] .
                ' - ' .
                (string)$row['lab_test_name'],
        ];
    }

    return $rows;
}

function bill_account_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            account_code,
            account_name,
            account_type,
            bank_name,
            account_number,
            upi_id
         FROM accounts
         WHERE branch_id=:branch_id
           AND status=1
         ORDER BY account_type,account_name,id'
    );

    $stmt->execute([
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $result = [
        'cash' => [],
        'bank' => [],
    ];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $label =
            trim(
                (string)$row['account_code']
            ) .
            ' - ' .
            trim(
                (string)$row['account_name']
            );

        $item = [
            'id' =>
                (int)$row['id'],
            'account_code' =>
                (string)$row['account_code'],
            'account_name' =>
                (string)$row['account_name'],
            'label' =>
                $label,
        ];

        if ((int)$row['account_type'] === 1) {
            $result['cash'][] = $item;
        } else {
            $result['bank'][] = $item;
        }
    }

    return $result;
}

function bill_account_id($value): ?int
{
    $raw = trim((string)$value);

    if ($raw === '') {
        return null;
    }

    if (!ctype_digit($raw)) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'payment_breakdown_json' =>
                    'Select a valid Account.'
            ]
        );
    }

    return (int)$raw;
}

function bill_account_snapshot(
    array $ctx,
    ?int $accountId,
    string $mode
): array {
    if ($accountId === null || $accountId < 1) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'payment_breakdown_json' =>
                    'Account is required for ' . $mode . ' payment.'
            ]
        );
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            account_name,
            account_code,
            account_type
         FROM accounts
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':id' =>
            $accountId,
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'payment_breakdown_json' =>
                    'Selected Account is invalid.'
            ]
        );
    }

    $type = (int)$row['account_type'];

    if ($mode === 'CASH' && $type !== 1) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'payment_breakdown_json' =>
                    'Cash mode requires a Cash Account.'
            ]
        );
    }

    if (in_array($mode, ['UPI','BANK','CHEQUE'], true) && $type !== 2) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'payment_breakdown_json' =>
                    $mode . ' mode requires a Bank Account.'
            ]
        );
    }

    return [
        'account_id' => (int)$row['id'],
        'account_name' => trim((string)$row['account_code']) . ' - ' . trim((string)$row['account_name']),
    ];
}

function bill_clean_payments(
    array $ctx,
    $raw
): array {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            json_error(
                'Billing payment details are invalid.',
                422
            );
        }

        $raw = $decoded;
    }

    if (!is_array($raw)) {
        $raw = [];
    }

    $allowedModes = ['CASH','UPI','BANK','CHEQUE'];
    $rows = [];
    $seen = [];

    foreach (array_values($raw) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $mode = strtoupper(trim((string)($entry['mode'] ?? '')));

        if (!in_array($mode, $allowedModes, true) || isset($seen[$mode])) {
            continue;
        }

        $seen[$mode] = true;

        $amount = round((float)((string)($entry['amount'] ?? '0')), 2);

        if ($amount < 0) {
            json_error(
                'Billing validation failed.',
                422,
                [
                    'payment_breakdown_json' =>
                        'Payment amount cannot be negative.'
                ]
            );
        }

        if ($amount <= 0) {
            continue;
        }

        $account = bill_account_snapshot(
            $ctx,
            bill_account_id($entry['account_id'] ?? ''),
            $mode
        );

        $referenceNo = trim((string)($entry['reference_no'] ?? ''));
        $paymentDate = bill_valid_date($entry['payment_date'] ?? '');

        if (in_array($mode, ['UPI','BANK','CHEQUE'], true) && $referenceNo === '') {
            json_error(
                'Billing validation failed.',
                422,
                [
                    'payment_breakdown_json' =>
                        'Reference No is required for ' . $mode . ' payment.'
                ]
            );
        }

        if ($mode === 'CHEQUE' && !$paymentDate) {
            json_error(
                'Billing validation failed.',
                422,
                [
                    'payment_breakdown_json' =>
                        'Cheque Date is required.'
                ]
            );
        }

        $rows[] = [
            'mode' => $mode,
            'account_id' => $account['account_id'],
            'account_name' => $account['account_name'],
            'amount' => number_format($amount, 2, '.', ''),
            'reference_no' => $referenceNo === '' ? null : mb_substr($referenceNo, 0, 150),
            'payment_date' => $paymentDate,
        ];
    }

    return $rows;
}

function bill_is_source_billed(
    int $branchId,
    string $type,
    int $referenceId,
    int $excludeBillId = 0
): bool {
    $sql =
        'SELECT bi.id
         FROM clinic_bill_items bi
         INNER JOIN clinic_bills b
                 ON b.id=bi.bill_id
                AND b.branch_id=bi.branch_id
         WHERE bi.branch_id=:branch_id
           AND bi.item_type=:item_type
           AND bi.reference_id=:reference_id
           AND bi.status=1
           AND b.status=1';

    $params = [
        ':branch_id' =>
            $branchId,

        ':item_type' =>
            $type,

        ':reference_id' =>
            $referenceId,
    ];

    if ($excludeBillId > 0) {
        $sql .= ' AND b.id<>:exclude_bill_id';

        $params[':exclude_bill_id'] =
            $excludeBillId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetchColumn();
}

function bill_pending_sources(
    array $ctx,
    int $consultationId
): array {
    $branchId =
        (int)$ctx['branch_id'];

    $consultation =
        bill_consultation(
            $ctx,
            $consultationId
        );

    $patientId =
        (int)$consultation['patient_id'];

    $patient =
        bill_patient(
            $ctx,
            $patientId
        );

    $items = [];

    /*
     * Consultation Fee.
     */
    $stmt = db()->prepare(
        'SELECT
            c.id,
            c.consultation_no,
            c.visit_date,
            c.consultation_fee
         FROM clinic_consultations c
         WHERE c.branch_id=:branch_id
           AND c.id=:consultation_id
           AND c.status=1
           AND c.consultation_fee>0
         ORDER BY c.visit_date,c.id'
    );

    $stmt->execute([
        ':branch_id' =>
            $branchId,

        ':consultation_id' =>
            $consultationId,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $id =
            (int)$row['id'];

        if (
            bill_is_source_billed(
                $branchId,
                'CONSULTATION',
                $id
            )
        ) {
            continue;
        }

        $fee =
            number_format(
                (float)$row['consultation_fee'],
                2,
                '.',
                ''
            );

        $items[] = [
            'item_type' =>
                'CONSULTATION',

            'source_ref' =>
                encryptReference(
                    'clinic_consultation',
                    $id
                ),

            'source_code' =>
                (string)$row['consultation_no'],

            'source_date' =>
                $row['visit_date'],

            'description' =>
                (string)$row['consultation_no'] .
                ' - Consultation Fee',

            'quantity' =>
                '1.000',

            'unit_price' =>
                $fee,

            'amount' =>
                $fee,
        ];
    }

    /*
     * Prescription Medicine snapshot charges.
     */
    $stmt = db()->prepare(
        'SELECT
            cp.id,
            cp.quantity,
            cp.unit_price,
            cp.amount,
            c.consultation_no,
            c.visit_date,
            m.medicine_code,
            m.medicine_name
         FROM clinic_consultation_prescriptions cp
         INNER JOIN clinic_consultations c
                 ON c.id=cp.consultation_id
                AND c.branch_id=cp.branch_id
         INNER JOIN clinic_medicines m
                 ON m.id=cp.medicine_id
                AND m.branch_id=cp.branch_id
         WHERE cp.branch_id=:branch_id
           AND c.id=:consultation_id
           AND cp.status=1
           AND c.status=1
         ORDER BY c.visit_date,c.id,cp.id'
    );

    $stmt->execute([
        ':branch_id' =>
            $branchId,

        ':consultation_id' =>
            $consultationId,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $id =
            (int)$row['id'];

        if (
            bill_is_source_billed(
                $branchId,
                'MEDICINE',
                $id
            )
        ) {
            continue;
        }

        $items[] = [
            'item_type' =>
                'MEDICINE',

            'source_ref' =>
                encryptReference(
                    'clinic_prescription_item',
                    $id
                ),

            'source_code' =>
                (string)$row['medicine_code'],

            'source_date' =>
                $row['visit_date'],

            'description' =>
                (string)$row['consultation_no'] .
                ' - ' .
                (string)$row['medicine_code'] .
                ' - ' .
                (string)$row['medicine_name'],

            'quantity' =>
                number_format(
                    (float)$row['quantity'],
                    3,
                    '.',
                    ''
                ),

            'unit_price' =>
                number_format(
                    (float)$row['unit_price'],
                    2,
                    '.',
                    ''
                ),

            'amount' =>
                number_format(
                    (float)$row['amount'],
                    2,
                    '.',
                    ''
                ),
        ];
    }

    /*
     * Only actual COMPLETED Treatment Progress is billable.
     */
    $stmt = db()->prepare(
        'SELECT
            pr.id,
            pr.treatment_date,
            td.day_number,
            c.consultation_no,
            proc.procedure_code,
            proc.procedure_name,
            proc.price
         FROM clinic_patient_treatment_progress pr
         INNER JOIN clinic_patient_treatment_days td
                 ON td.id=pr.treatment_day_id
                AND td.branch_id=pr.branch_id
         INNER JOIN clinic_patient_treatment_plans tp
                 ON tp.id=td.plan_id
                AND tp.branch_id=td.branch_id
         INNER JOIN clinic_consultations c
                 ON c.id=tp.consultation_id
                AND c.branch_id=tp.branch_id
         INNER JOIN clinic_treatment_procedures proc
                 ON proc.id=td.treatment_procedure_id
                AND proc.branch_id=td.branch_id
         WHERE pr.branch_id=:branch_id
           AND c.id=:consultation_id
           AND pr.treatment_status=\'Completed\'
           AND c.status=1
           AND tp.status=1
         ORDER BY
            pr.treatment_date,
            c.id,
            td.day_number,
            pr.id'
    );

    $stmt->execute([
        ':branch_id' =>
            $branchId,

        ':consultation_id' =>
            $consultationId,
    ]);

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $id =
            (int)$row['id'];

        if (
            bill_is_source_billed(
                $branchId,
                'TREATMENT',
                $id
            )
        ) {
            continue;
        }

        $price =
            number_format(
                (float)$row['price'],
                2,
                '.',
                ''
            );

        $items[] = [
            'item_type' =>
                'TREATMENT',

            'source_ref' =>
                encryptReference(
                    'clinic_treatment_progress',
                    $id
                ),

            'source_code' =>
                (string)$row['procedure_code'],

            'source_date' =>
                $row['treatment_date'],

            'description' =>
                (string)$row['consultation_no'] .
                ' - Day ' .
                (int)$row['day_number'] .
                ' - ' .
                (string)$row['procedure_code'] .
                ' - ' .
                (string)$row['procedure_name'],

            'quantity' =>
                '1.000',

            'unit_price' =>
                $price,

            'amount' =>
                $price,
        ];
    }

    return [
        'patient' =>
            $patient,

        'consultation' =>
            $consultation,

        'items' =>
            $items,

        'pending_total' =>
            number_format(
                array_sum(
                    array_map(
                        static function (
                            array $item
                        ): float {
                            return
                                (float)$item['amount'];
                        },
                        $items
                    )
                ),
                2,
                '.',
                ''
            ),
    ];
}

function bill_existing_item_snapshot(
    int $branchId,
    int $billId,
    string $type,
    int $referenceId
): ?array {
    if ($billId < 1) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT
            item_type,
            reference_id,
            description,
            quantity,
            unit_price,
            amount
         FROM clinic_bill_items
         WHERE branch_id=:branch_id
           AND bill_id=:bill_id
           AND item_type=:item_type
           AND reference_id=:reference_id
           AND status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':branch_id' =>
            $branchId,

        ':bill_id' =>
            $billId,

        ':item_type' =>
            $type,

        ':reference_id' =>
            $referenceId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        return null;
    }

    return [
        'item_type' =>
            (string)$row['item_type'],

        'reference_id' =>
            (int)$row['reference_id'],

        'description' =>
            (string)$row['description'],

        'quantity' =>
            number_format(
                (float)$row['quantity'],
                3,
                '.',
                ''
            ),

        'unit_price' =>
            number_format(
                (float)$row['unit_price'],
                2,
                '.',
                ''
            ),

        'amount' =>
            number_format(
                (float)$row['amount'],
                2,
                '.',
                ''
            ),
    ];
}

function bill_validate_source_item(
    array $ctx,
    int $patientId,
    int $consultationId,
    string $type,
    int $sourceId,
    string $quantity,
    int $excludeBillId = 0
): array {
    $branchId =
        (int)$ctx['branch_id'];

    $existingSnapshot =
        bill_existing_item_snapshot(
            $branchId,
            $excludeBillId,
            $type,
            $sourceId
        );

    if ($existingSnapshot !== null) {
        if ($type === 'LAB') {
            $qty =
                bill_quantity(
                    $quantity,
                    'lab_quantity'
                );

            $existingSnapshot['quantity'] =
                $qty;

            $existingSnapshot['amount'] =
                number_format(
                    (float)$qty *
                    (float)$existingSnapshot['unit_price'],
                    2,
                    '.',
                    ''
                );
        }

        return $existingSnapshot;
    }

    if ($type === 'CONSULTATION') {
        $stmt = db()->prepare(
            'SELECT
                id,
                consultation_no,
                consultation_fee
             FROM clinic_consultations
             WHERE id=:id
               AND branch_id=:branch_id
               AND patient_id=:patient_id
               AND id=:consultation_id
               AND status=1
             LIMIT 1'
        );

        $stmt->execute([
            ':id' =>
                $sourceId,

            ':branch_id' =>
                $branchId,

            ':patient_id' =>
                $patientId,

            ':consultation_id' =>
                $consultationId,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            json_error(
                'Selected Consultation charge is invalid.',
                422
            );
        }

        if (
            bill_is_source_billed(
                $branchId,
                'CONSULTATION',
                $sourceId,
                $excludeBillId
            )
        ) {
            json_error(
                'A selected Consultation Fee has already been billed.',
                409
            );
        }

        $amount =
            number_format(
                (float)$row['consultation_fee'],
                2,
                '.',
                ''
            );

        return [
            'item_type' =>
                'CONSULTATION',

            'reference_id' =>
                $sourceId,

            'description' =>
                (string)$row['consultation_no'] .
                ' - Consultation Fee',

            'quantity' =>
                '1.000',

            'unit_price' =>
                $amount,

            'amount' =>
                $amount,
        ];
    }

    if ($type === 'MEDICINE') {
        $stmt = db()->prepare(
            'SELECT
                cp.id,
                cp.quantity,
                cp.unit_price,
                cp.amount,
                c.consultation_no,
                m.medicine_code,
                m.medicine_name
             FROM clinic_consultation_prescriptions cp
             INNER JOIN clinic_consultations c
                     ON c.id=cp.consultation_id
                    AND c.branch_id=cp.branch_id
             INNER JOIN clinic_medicines m
                     ON m.id=cp.medicine_id
                    AND m.branch_id=cp.branch_id
             WHERE cp.id=:id
               AND cp.branch_id=:branch_id
               AND c.patient_id=:patient_id
               AND c.id=:consultation_id
               AND cp.status=1
               AND c.status=1
             LIMIT 1'
        );

        $stmt->execute([
            ':id' =>
                $sourceId,

            ':branch_id' =>
                $branchId,

            ':patient_id' =>
                $patientId,

            ':consultation_id' =>
                $consultationId,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            json_error(
                'Selected Medicine charge is invalid.',
                422
            );
        }

        if (
            bill_is_source_billed(
                $branchId,
                'MEDICINE',
                $sourceId,
                $excludeBillId
            )
        ) {
            json_error(
                'A selected Medicine charge has already been billed.',
                409
            );
        }

        return [
            'item_type' =>
                'MEDICINE',

            'reference_id' =>
                $sourceId,

            'description' =>
                (string)$row['consultation_no'] .
                ' - ' .
                (string)$row['medicine_code'] .
                ' - ' .
                (string)$row['medicine_name'],

            'quantity' =>
                number_format(
                    (float)$row['quantity'],
                    3,
                    '.',
                    ''
                ),

            'unit_price' =>
                number_format(
                    (float)$row['unit_price'],
                    2,
                    '.',
                    ''
                ),

            'amount' =>
                number_format(
                    (float)$row['amount'],
                    2,
                    '.',
                    ''
                ),
        ];
    }

    if ($type === 'TREATMENT') {
        $stmt = db()->prepare(
            'SELECT
                pr.id,
                td.day_number,
                c.consultation_no,
                proc.procedure_code,
                proc.procedure_name,
                proc.price
             FROM clinic_patient_treatment_progress pr
             INNER JOIN clinic_patient_treatment_days td
                     ON td.id=pr.treatment_day_id
                    AND td.branch_id=pr.branch_id
             INNER JOIN clinic_patient_treatment_plans tp
                     ON tp.id=td.plan_id
                    AND tp.branch_id=td.branch_id
             INNER JOIN clinic_consultations c
                     ON c.id=tp.consultation_id
                    AND c.branch_id=tp.branch_id
             INNER JOIN clinic_treatment_procedures proc
                     ON proc.id=td.treatment_procedure_id
                    AND proc.branch_id=td.branch_id
             WHERE pr.id=:id
               AND pr.branch_id=:branch_id
               AND c.patient_id=:patient_id
               AND c.id=:consultation_id
               AND pr.treatment_status=\'Completed\'
               AND c.status=1
               AND tp.status=1
             LIMIT 1'
        );

        $stmt->execute([
            ':id' =>
                $sourceId,

            ':branch_id' =>
                $branchId,

            ':patient_id' =>
                $patientId,

            ':consultation_id' =>
                $consultationId,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            json_error(
                'Selected Treatment charge is invalid or not Completed.',
                422
            );
        }

        if (
            bill_is_source_billed(
                $branchId,
                'TREATMENT',
                $sourceId,
                $excludeBillId
            )
        ) {
            json_error(
                'A selected Treatment charge has already been billed.',
                409
            );
        }

        $price =
            number_format(
                (float)$row['price'],
                2,
                '.',
                ''
            );

        return [
            'item_type' =>
                'TREATMENT',

            'reference_id' =>
                $sourceId,

            'description' =>
                (string)$row['consultation_no'] .
                ' - Day ' .
                (int)$row['day_number'] .
                ' - ' .
                (string)$row['procedure_code'] .
                ' - ' .
                (string)$row['procedure_name'],

            'quantity' =>
                '1.000',

            'unit_price' =>
                $price,

            'amount' =>
                $price,
        ];
    }

    if ($type === 'LAB') {
        $stmt = db()->prepare(
            'SELECT
                id,
                lab_test_code,
                lab_test_name,
                price
             FROM clinic_lab_tests
             WHERE id=:id
               AND branch_id=:branch_id
               AND status=1
             LIMIT 1'
        );

        $stmt->execute([
            ':id' =>
                $sourceId,

            ':branch_id' =>
                $branchId,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            json_error(
                'Selected Lab Test is invalid or inactive.',
                422
            );
        }

        $qty =
            bill_quantity(
                $quantity,
                'lab_quantity'
            );

        $price =
            number_format(
                (float)$row['price'],
                2,
                '.',
                ''
            );

        $amount =
            number_format(
                (float)$qty *
                (float)$price,
                2,
                '.',
                ''
            );

        return [
            'item_type' =>
                'LAB',

            'reference_id' =>
                $sourceId,

            'description' =>
                (string)$row['lab_test_code'] .
                ' - ' .
                (string)$row['lab_test_name'],

            'quantity' =>
                $qty,

            'unit_price' =>
                $price,

            'amount' =>
                $amount,
        ];
    }

    json_error(
        'Invalid Billing item type.',
        422
    );
}

function bill_clean_items(
    array $ctx,
    int $patientId,
    int $consultationId,
    $raw,
    int $excludeBillId = 0
): array {
    if (is_string($raw)) {
        $decoded =
            json_decode(
                $raw,
                true
            );

        if (
            json_last_error() !==
            JSON_ERROR_NONE
        ) {
            json_error(
                'Billing items are invalid.',
                422
            );
        }

        $raw = $decoded;
    }

    if (!is_array($raw)) {
        $raw = [];
    }

    $items = [];
    $seen = [];

    foreach (
        array_values($raw) as $index => $row
    ) {
        if (!is_array($row)) {
            continue;
        }

        $type =
            strtoupper(
                trim(
                    (string)(
                        $row['item_type'] ??
                        ''
                    )
                )
            );

        if (
            !in_array(
                $type,
                [
                    'CONSULTATION',
                    'MEDICINE',
                    'TREATMENT',
                    'LAB',
                ],
                true
            )
        ) {
            continue;
        }

        $sourceId =
            bill_source_ref_to_id(
                $type,
                $row['source_ref'] ??
                ''
            );

        /*
         * CONSULTATION / MEDICINE / TREATMENT sources can be
         * billed only once. A Lab master can legitimately be
         * used repeatedly, but only once per current bill row.
         */
        $key =
            $type .
            ':' .
            $sourceId;

        if (isset($seen[$key])) {
            json_error(
                'The same Billing item was added more than once.',
                422
            );
        }

        $seen[$key] = true;

        $items[] =
            bill_validate_source_item(
                $ctx,
                $patientId,
                $consultationId,
                $type,
                $sourceId,
                (string)(
                    $row['quantity'] ??
                    '1'
                ),
                $excludeBillId
            );
    }

    if ($items === []) {
        json_error(
            'Add at least one Billing item.',
            422,
            [
                'bill_items' =>
                    'Add at least one Consultation, Medicine, Treatment or Lab item.'
            ]
        );
    }

    return $items;
}

function bill_record(
    array $ctx,
    int $billId
): array {
    $stmt = db()->prepare(
        'SELECT
            b.id,
            b.bill_no,
            b.patient_id,
            b.consultation_id,
            b.bill_date,
            b.subtotal,
            b.discount_amount,
            b.grand_total,
            b.paid_amount,
            b.balance_amount,
            b.payment_status,
            b.notes,
            b.status,
            b.created_by,
            b.created_at,
            b.updated_at,

            c.consultation_no,
            c.visit_date AS consultation_visit_date,
            c.consultation_status,

            p.patient_code,
            p.patient_name,
            p.mobile,
            p.gender,
            p.date_of_birth,
            p.blood_group,

            CASE
                WHEN p.date_of_birth IS NULL
                    THEN NULL
                ELSE TIMESTAMPDIFF(
                    YEAR,
                    p.date_of_birth,
                    CURDATE()
                )
            END AS age,

            u.name AS created_by_name

         FROM clinic_bills b
         INNER JOIN clinic_patients p
                 ON p.id=b.patient_id
                AND p.branch_id=b.branch_id
         LEFT JOIN clinic_consultations c
                ON c.id=b.consultation_id
               AND c.branch_id=b.branch_id
         LEFT JOIN users u
                ON u.id=b.created_by
         WHERE b.id=:id
           AND b.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' =>
            $billId,

        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        json_error(
            'Bill was not found.',
            404
        );
    }

    $row['id'] =
        (int)$row['id'];

    $row['patient_id'] =
        (int)$row['patient_id'];

    $row['consultation_id'] =
        $row['consultation_id'] === null
            ? null
            : (int)$row['consultation_id'];

    $row['consultation_ref'] =
        $row['consultation_id'] !== null
            ? encryptReference(
                'clinic_consultation',
                (int)$row['consultation_id']
            )
            : null;

    $row['status'] =
        (int)$row['status'];

    $row['age'] =
        $row['age'] === null
            ? null
            : (int)$row['age'];

    foreach (
        [
            'subtotal',
            'discount_amount',
            'grand_total',
            'paid_amount',
            'balance_amount',
        ] as $moneyField
    ) {
        $row[$moneyField] =
            number_format(
                (float)$row[$moneyField],
                2,
                '.',
                ''
            );
    }

    $row['ref'] =
        encryptReference(
            'clinic_bill',
            (int)$row['id']
        );

    $row['patient_ref'] =
        encryptReference(
            'clinic_patient',
            (int)$row['patient_id']
        );

    $itemStmt = db()->prepare(
        'SELECT
            id,
            item_type,
            reference_id,
            description,
            quantity,
            unit_price,
            amount
         FROM clinic_bill_items
         WHERE branch_id=:branch_id
           AND bill_id=:bill_id
           AND status=1
         ORDER BY id'
    );

    $itemStmt->execute([
        ':branch_id' =>
            (int)$ctx['branch_id'],

        ':bill_id' =>
            $billId,
    ]);

    $items = [];

    foreach (
        $itemStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $item
    ) {
        $type =
            (string)$item['item_type'];

        $sourceRef = '';

        if (
            $item['reference_id'] !== null
        ) {
            $referenceId =
                (int)$item['reference_id'];

            $tagMap = [
                'CONSULTATION' =>
                    'clinic_consultation',

                'MEDICINE' =>
                    'clinic_prescription_item',

                'TREATMENT' =>
                    'clinic_treatment_progress',

                'LAB' =>
                    'clinic_lab_test',
            ];

            if (
                isset(
                    $tagMap[$type]
                )
            ) {
                $sourceRef =
                    encryptReference(
                        $tagMap[$type],
                        $referenceId
                    );
            }
        }

        $sourceCode = '';

        if (
            $type === 'LAB' &&
            $item['reference_id'] !== null
        ) {
            $codeStmt =
                db()->prepare(
                    'SELECT lab_test_code
                     FROM clinic_lab_tests
                     WHERE id=:id
                       AND branch_id=:branch_id
                     LIMIT 1'
                );

            $codeStmt->execute([
                ':id' =>
                    (int)$item['reference_id'],

                ':branch_id' =>
                    (int)$ctx['branch_id'],
            ]);

            $sourceCode =
                (string)(
                    $codeStmt->fetchColumn() ?:
                    ''
                );
        }

        $items[] = [
            'item_type' =>
                $type,

            'source_ref' =>
                $sourceRef,

            'source_code' =>
                $sourceCode,

            'description' =>
                (string)$item['description'],

            'quantity' =>
                number_format(
                    (float)$item['quantity'],
                    3,
                    '.',
                    ''
                ),

            'unit_price' =>
                number_format(
                    (float)$item['unit_price'],
                    2,
                    '.',
                    ''
                ),

            'amount' =>
                number_format(
                    (float)$item['amount'],
                    2,
                    '.',
                    ''
                ),
        ];
    }

    $row['items'] =
        $items;

    $paymentStmt = db()->prepare(
        'SELECT
            payment_mode,
            account_id,
            account_name,
            amount,
            reference_no,
            payment_date
         FROM clinic_bill_payments
         WHERE branch_id=:branch_id
           AND bill_id=:bill_id
           AND status=1
         ORDER BY sort_order,id'
    );

    $paymentStmt->execute([
        ':branch_id' =>
            (int)$ctx['branch_id'],
        ':bill_id' =>
            $billId,
    ]);

    $payments = [];

    foreach (
        $paymentStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $item
    ) {
        $payments[] = [
            'mode' =>
                (string)$item['payment_mode'],
            'account_id' =>
                $item['account_id'] === null
                    ? ''
                    : (string)((int)$item['account_id']),
            'account_name' =>
                (string)($item['account_name'] ?? ''),
            'amount' =>
                number_format(
                    (float)$item['amount'],
                    2,
                    '.',
                    ''
                ),
            'reference_no' =>
                (string)($item['reference_no'] ?? ''),
            'payment_date' =>
                $item['payment_date'],
        ];
    }

    $row['payments'] =
        $payments;

    return $row;
}

$method = request_method();

bill_require_schema();

/*
 * Options.
 */
if (
    $method === 'GET' &&
    isset($_GET['options'])
) {
    $access =
        require_permission(
            'bill-list.php',
            ACTION_VIEW
        );

    $ctx =
        bill_context(
            $access['user']
        );

    json_success(
        'Billing options loaded.',
        [
            'allowed_actions' =>
                $access['actions'],

            'next_bill_no' =>
                bill_generate_no(
                    (int)$ctx['branch_id']
                ),

            'patients' =>
                bill_patient_options(
                    $ctx
                ),

            'lab_tests' =>
                bill_lab_options(
                    $ctx
                ),

            'payment_accounts' =>
                bill_account_options(
                    $ctx
                ),

            'branch' =>
                $ctx,
        ]
    );
}

/*
 * Pending Consultations for selected Patient.
 *
 * 0 pending  -> Billing page may start a new Consultation.
 * 1 pending  -> Billing page auto-selects it.
 * 2+ pending -> Billing page asks user to select.
 */
if (
    $method === 'GET' &&
    isset($_GET['consultations'])
) {
    $access =
        require_permission(
            'bill-list.php',
            ACTION_VIEW
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $patientId =
        bill_patient_ref_to_id(
            $_GET['patient_ref'] ?? ''
        );

    $rows =
        bill_pending_consultation_options(
            $ctx,
            $patientId
        );

    json_success(
        'Pending Consultations loaded.',
        [
            'patient' =>
                bill_patient(
                    $ctx,
                    $patientId
                ),
            'consultations' =>
                $rows,
            'count' =>
                count($rows),
        ]
    );
}

/*
 * Consultation pending charges.
 */
if (
    $method === 'GET' &&
    isset($_GET['source'])
) {
    $access =
        require_permission(
            'bill-list.php',
            ACTION_VIEW
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $consultationId =
        bill_consultation_ref_to_id(
            $_GET['consultation_ref'] ??
            ''
        );

    json_success(
        'Consultation pending Billing charges loaded.',
        bill_pending_sources(
            $ctx,
            $consultationId
        ) + [
            'lab_tests' =>
                bill_lab_options(
                    $ctx
                ),
            'payment_accounts' =>
                bill_account_options(
                    $ctx
                )
        ]
    );
}

/*
 * Print.
 */
if (
    $method === 'GET' &&
    isset($_GET['print']) &&
    isset($_GET['ref'])
) {
    $access =
        require_permission(
            'bill-list.php',
            6
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $id =
        bill_ref_to_id(
            $_GET['ref'] ??
            ''
        );

    json_success(
        'Bill print data loaded.',
        [
            'record' =>
                bill_record(
                    $ctx,
                    $id
                ),

            'allowed_actions' =>
                $access['actions'],

            'branch' =>
                $ctx,
        ]
    );
}

/*
 * Single Bill.
 */
if (
    $method === 'GET' &&
    isset($_GET['ref'])
) {
    $access =
        require_permission(
            'bill-list.php',
            ACTION_VIEW
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $id =
        bill_ref_to_id(
            $_GET['ref'] ??
            ''
        );

    $record =
        bill_record(
            $ctx,
            $id
        );

    json_success(
        'Bill loaded.',
        [
            'record' =>
                $record,

            'allowed_actions' =>
                $access['actions'],

            'patients' =>
                bill_patient_options(
                    $ctx,
                    (int)$record['patient_id']
                ),

            'consultations' =>
                $record['consultation_id']
                    ? [
                        [
                            'ref' =>
                                $record['consultation_ref'],
                            'consultation_no' =>
                                $record['consultation_no'],
                            'visit_date' =>
                                $record['consultation_visit_date'],
                            'consultation_status' =>
                                $record['consultation_status'],
                            'label' =>
                                (string)$record['consultation_no'] .
                                ' - ' .
                                (string)$record['consultation_visit_date'],
                        ]
                    ]
                    : [],

            'lab_tests' =>
                bill_lab_options(
                    $ctx
                ),

            'payment_accounts' =>
                bill_account_options(
                    $ctx
                ),

            'branch' =>
                $ctx,
        ]
    );
}

/*
 * DataTable.
 */
if (
    $method === 'GET' &&
    isset($_GET['datatable'])
) {
    $access =
        require_permission(
            'bill-list.php',
            ACTION_VIEW
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $draw =
        max(
            0,
            (int)(
                $_GET['draw'] ??
                0
            )
        );

    $start =
        max(
            0,
            (int)(
                $_GET['start'] ??
                0
            )
        );

    $lengthRaw =
        (int)(
            $_GET['length'] ??
            10
        );

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
                $_GET['search']['value'] ??
                ''
            )
        );

    $where = [
        'b.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' =>
            $branchId,
    ];

    if ($search !== '') {
        $where[] =
            '(b.bill_no LIKE :s1
              OR p.patient_code LIKE :s2
              OR p.patient_name LIKE :s3
              OR p.mobile LIKE :s4
              OR b.payment_status LIKE :s5)';

        $like =
            '%' .
            $search .
            '%';

        foreach (
            [
                ':s1',
                ':s2',
                ':s3',
                ':s4',
                ':s5',
            ] as $key
        ) {
            $params[$key] =
                $like;
        }
    }

    $paymentStatusFilter =
        trim(
            (string)(
                $_GET['payment_status'] ??
                ''
            )
        );

    if (
        in_array(
            $paymentStatusFilter,
            [
                'Paid',
                'Partially Paid',
                'Unpaid',
            ],
            true
        )
    ) {
        $where[] =
            'b.payment_status=:payment_status';

        $params[':payment_status'] =
            $paymentStatusFilter;
    }

    $dateFromFilter =
        bill_valid_date(
            $_GET['date_from'] ??
            ''
        );

    if ($dateFromFilter) {
        $where[] =
            'b.bill_date>=:date_from';

        $params[':date_from'] =
            $dateFromFilter;
    }

    $dateToFilter =
        bill_valid_date(
            $_GET['date_to'] ??
            ''
        );

    if ($dateToFilter) {
        $where[] =
            'b.bill_date<=:date_to';

        $params[':date_to'] =
            $dateToFilter;
    }

    $totalStmt =
        db()->prepare(
            'SELECT COUNT(*)
             FROM clinic_bills
             WHERE branch_id=:branch_id'
        );

    $totalStmt->execute([
        ':branch_id' =>
            $branchId,
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $from =
        ' FROM clinic_bills b
          INNER JOIN clinic_patients p
                  ON p.id=b.patient_id
                 AND p.branch_id=b.branch_id ';

    $countStmt =
        db()->prepare(
            'SELECT COUNT(*)' .
            $from .
            ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
        );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $summaryStmt =
        db()->prepare(
            'SELECT
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.grand_total),0) AS grand_total,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount' .
            $from .
            ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
        );

    foreach (
        $params as $key => $value
    ) {
        $summaryStmt->bindValue(
            $key,
            $value,
            $key === ':branch_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();

    $summary =
        $summaryStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $orderColumns = [
        'b.bill_no',
        'b.bill_date',
        'p.patient_name',
        'b.grand_total',
        'b.paid_amount',
        'b.balance_amount',
        'b.payment_status',
        'b.created_at',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column'] ??
            1
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

    $orderBy =
        $orderColumns[$orderIndex] ??
        'b.bill_date';

    $stmt =
        db()->prepare(
            'SELECT
                b.id,
                b.bill_no,
                b.bill_date,
                b.grand_total,
                b.paid_amount,
                b.balance_amount,
                b.payment_status,
                b.status,
                b.created_at,
                p.patient_code,
                p.patient_name' .
            $from .
            ' WHERE ' .
            implode(
                ' AND ',
                $where
            ) .
            ' ORDER BY ' .
            $orderBy .
            ' ' .
            $orderDir .
            ',b.id DESC
             LIMIT :start,:length'
        );

    foreach (
        $params as $key => $value
    ) {
        $stmt->bindValue(
            $key,
            $value,
            $key === ':branch_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
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

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $ref =
            encryptReference(
                'clinic_bill',
                (int)$row['id']
            );

        $rows[] = [
            'ref' =>
                $ref,

            'bill_no' =>
                (string)$row['bill_no'],

            'bill_date' =>
                $row['bill_date'],

            'patient_code' =>
                (string)$row['patient_code'],

            'patient_name' =>
                (string)$row['patient_name'],

            'grand_total' =>
                number_format(
                    (float)$row['grand_total'],
                    2,
                    '.',
                    ''
                ),

            'paid_amount' =>
                number_format(
                    (float)$row['paid_amount'],
                    2,
                    '.',
                    ''
                ),

            'balance_amount' =>
                number_format(
                    (float)$row['balance_amount'],
                    2,
                    '.',
                    ''
                ),

            'payment_status' =>
                (string)$row['payment_status'],

            'status' =>
                (int)$row['status'],

            'created_at' =>
                $row['created_at'],

            'view_url' =>
                'bill-form.php?ref=' .
                rawurlencode($ref) .
                '&view=1',

            'edit_url' =>
                'bill-form.php?ref=' .
                rawurlencode($ref),

            'print_url' =>
                'bill-print.php?ref=' .
                rawurlencode($ref),
        ];
    }

    json_success(
        'Bill list loaded.',
        [
            'datatable' => [
                'draw' =>
                    $draw,

                'recordsTotal' =>
                    $recordsTotal,

                'recordsFiltered' =>
                    $recordsFiltered,

                'data' =>
                    $rows,
            ],

            'summary' => [
                'bill_count' =>
                    (int)(
                        $summary['bill_count'] ??
                        0
                    ),

                'grand_total' =>
                    (float)(
                        $summary['grand_total'] ??
                        0
                    ),

                'paid_amount' =>
                    (float)(
                        $summary['paid_amount'] ??
                        0
                    ),

                'balance_amount' =>
                    (float)(
                        $summary['balance_amount'] ??
                        0
                    ),
            ],

            'list_actions' =>
                $access['actions'],

            'form_actions' =>
                $access['actions'],
        ]
    );
}

/*
 * Save / deactivate.
 */
if ($method === 'POST') {
    $data =
        request_data();

    $action =
        strtolower(
            trim(
                (string)(
                    $data['action'] ??
                    'save'
                )
            )
        );

    if ($action === 'delete') {
        $access =
            require_permission(
                'bill-list.php',
                4
            );

        $ctx =
            bill_context(
                $access['user']
            );

        $id =
            bill_ref_to_id(
                $data['ref'] ??
                ''
            );

        $old =
            bill_record(
                $ctx,
                $id
            );

        db()->prepare(
            'UPDATE clinic_bills
             SET
                status=0,
                updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':id' =>
                $id,

            ':branch_id' =>
                (int)$ctx['branch_id'],
        ]);

        audit_log(
            (int)$access['user']['id'],
            4,
            [
                'company_id' =>
                    (int)$ctx['company_id'],

                'branch_id' =>
                    (int)$ctx['branch_id'],

                'menu_id' =>
                    (int)$access['menu']['id'],

                'record_id' =>
                    $id,

                'old_data' =>
                    $old,
            ]
        );

        json_success(
            'Bill deactivated successfully.'
        );
    }

    if ($action !== 'save') {
        json_error(
            'Unsupported Billing action.',
            404
        );
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access =
        require_permission(
            'bill-list.php',
            $isUpdate
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $ctx =
        bill_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $billId =
        $isUpdate
            ? bill_ref_to_id(
                $data['ref'] ??
                ''
            )
            : 0;

    $consultationId =
        bill_consultation_ref_to_id(
            $data['consultation_ref'] ??
            ''
        );

    $consultation =
        bill_consultation(
            $ctx,
            $consultationId
        );

    if ((int)$consultation['status'] !== 1) {
        json_error(
            'Selected Consultation is inactive.',
            422,
            [
                'consultation_ref' =>
                    'Select an active Consultation.'
            ]
        );
    }

    if (
        (string)$consultation['consultation_status'] !==
        'Completed'
    ) {
        json_error(
            'Complete the Consultation before creating the Bill.',
            422,
            [
                'consultation_ref' =>
                    'Consultation must be Completed before Billing.'
            ]
        );
    }

    $duplicateSql =
        'SELECT id
         FROM clinic_bills
         WHERE branch_id=:branch_id
           AND consultation_id=:consultation_id
           AND status=1';

    $duplicateParams = [
        ':branch_id' => $branchId,
        ':consultation_id' => $consultationId,
    ];

    if ($billId > 0) {
        $duplicateSql .= ' AND id<>:bill_id';
        $duplicateParams[':bill_id'] = $billId;
    }

    $duplicateSql .= ' LIMIT 1';

    $duplicateStmt =
        db()->prepare(
            $duplicateSql
        );

    $duplicateStmt->execute(
        $duplicateParams
    );

    if ($duplicateStmt->fetchColumn()) {
        json_error(
            'A Bill already exists for this Consultation.',
            409,
            [
                'consultation_ref' =>
                    'This Consultation already has an active Bill.'
            ]
        );
    }

    $patientId =
        (int)$consultation['patient_id'];

    /*
     * If the frontend also submits patient_ref, validate it belongs
     * to the selected Consultation instead of trusting it.
     */
    if (
        isset($data['patient_ref']) &&
        is_string($data['patient_ref']) &&
        trim($data['patient_ref']) !== ''
    ) {
        $submittedPatientId =
            bill_patient_ref_to_id(
                $data['patient_ref']
            );

        if ($submittedPatientId !== $patientId) {
            json_error(
                'Selected Patient does not match the Consultation.',
                422,
                [
                    'patient_ref' =>
                        'Patient and Consultation do not match.'
                ]
            );
        }
    }

    bill_patient(
        $ctx,
        $patientId
    );

    if ($isUpdate) {
        $existing =
            bill_record(
                $ctx,
                $billId
            );

        if (
            (int)$existing['patient_id'] !==
            $patientId
        ) {
            json_error(
                'Patient cannot be changed after a Bill is created.',
                422,
                [
                    'patient_ref' =>
                        'Patient cannot be changed for an existing Bill.'
                ]
            );
        }

        if (
            (int)($existing['consultation_id'] ?? 0) > 0 &&
            (int)$existing['consultation_id'] !==
            $consultationId
        ) {
            json_error(
                'Consultation cannot be changed after a Bill is created.',
                422,
                [
                    'consultation_ref' =>
                        'Consultation cannot be changed for an existing Bill.'
                ]
            );
        }
    }

    $billDate =
        bill_valid_date(
            $data['bill_date'] ??
            ''
        );

    if (!$billDate) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'bill_date' =>
                    'Enter a valid Bill Date.'
            ]
        );
    }

    $discount =
        bill_money(
            $data['discount_amount'] ??
            '0',
            'discount_amount'
        );

    $payments =
        bill_clean_payments(
            $ctx,
            $data['payment_breakdown_json'] ??
            []
        );

    $notes =
        trim(
            (string)(
                $data['notes'] ??
                ''
            )
        );

    $notes =
        $notes === ''
            ? null
            : mb_substr(
                $notes,
                0,
                5000
            );

    $items =
        bill_clean_items(
            $ctx,
            $patientId,
            $consultationId,
            $data['bill_items_json'] ??
            [],
            $billId
        );

    $subtotal = 0.0;

    foreach ($items as $item) {
        $subtotal +=
            (float)$item['amount'];
    }

    $subtotal =
        round(
            $subtotal,
            2
        );

    $grand =
        max(
            0,
            round(
                $subtotal -
                (float)$discount,
                2
            )
        );

    if (
        (float)$discount >
        $subtotal + 0.0001
    ) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'discount_amount' =>
                    'Discount cannot exceed Subtotal.'
            ]
        );
    }

    $paidTotal = 0.0;

    foreach ($payments as $payment) {
        $paidTotal +=
            (float)$payment['amount'];
    }

    $paid =
        number_format(
            round($paidTotal, 2),
            2,
            '.',
            ''
        );

    if (
        (float)$paid >
        $grand + 0.0001
    ) {
        json_error(
            'Billing validation failed.',
            422,
            [
                'paid_amount' =>
                    'Paid Amount cannot exceed Grand Total.'
            ]
        );
    }

    $balance =
        max(
            0,
            round(
                $grand -
                (float)$paid,
                2
            )
        );

    $paymentStatus =
        $balance <= 0.0001
            ? 'Paid'
            : (
                (float)$paid > 0
                    ? 'Partially Paid'
                    : 'Unpaid'
            );

    $subtotalString =
        number_format(
            $subtotal,
            2,
            '.',
            ''
        );

    $grandString =
        number_format(
            $grand,
            2,
            '.',
            ''
        );

    $balanceString =
        number_format(
            $balance,
            2,
            '.',
            ''
        );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $old = null;

        if ($isUpdate) {
            $old =
                bill_record(
                    $ctx,
                    $billId
                );

            $stmt =
                $pdo->prepare(
                    'UPDATE clinic_bills
                     SET
                        patient_id=:patient_id,
                        consultation_id=:consultation_id,
                        bill_date=:bill_date,
                        subtotal=:subtotal,
                        discount_amount=:discount,
                        grand_total=:grand,
                        paid_amount=:paid,
                        balance_amount=:balance,
                        payment_status=:payment_status,
                        notes=:notes,
                        status=1,
                        updated_at=NOW()
                     WHERE id=:id
                       AND branch_id=:branch_id'
                );

            $stmt->execute([
                ':patient_id' =>
                    $patientId,

                ':consultation_id' =>
                    $consultationId,

                ':bill_date' =>
                    $billDate,

                ':subtotal' =>
                    $subtotalString,

                ':discount' =>
                    $discount,

                ':grand' =>
                    $grandString,

                ':paid' =>
                    $paid,

                ':balance' =>
                    $balanceString,

                ':payment_status' =>
                    $paymentStatus,

                ':notes' =>
                    $notes,

                ':id' =>
                    $billId,

                ':branch_id' =>
                    $branchId,
            ]);
        } else {
            $billNo =
                bill_generate_no(
                    $branchId
                );

            $stmt =
                $pdo->prepare(
                    'INSERT INTO clinic_bills
                    (
                        branch_id,
                        bill_no,
                        patient_id,
                        consultation_id,
                        bill_date,
                        subtotal,
                        discount_amount,
                        grand_total,
                        paid_amount,
                        balance_amount,
                        payment_status,
                        notes,
                        status,
                        created_by,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :branch_id,
                        :bill_no,
                        :patient_id,
                        :consultation_id,
                        :bill_date,
                        :subtotal,
                        :discount,
                        :grand,
                        :paid,
                        :balance,
                        :payment_status,
                        :notes,
                        1,
                        :created_by,
                        NOW(),
                        NOW()
                    )'
                );

            $stmt->execute([
                ':branch_id' =>
                    $branchId,

                ':bill_no' =>
                    $billNo,

                ':patient_id' =>
                    $patientId,

                ':consultation_id' =>
                    $consultationId,

                ':bill_date' =>
                    $billDate,

                ':subtotal' =>
                    $subtotalString,

                ':discount' =>
                    $discount,

                ':grand' =>
                    $grandString,

                ':paid' =>
                    $paid,

                ':balance' =>
                    $balanceString,

                ':payment_status' =>
                    $paymentStatus,

                ':notes' =>
                    $notes,

                ':created_by' =>
                    $userId,
            ]);

            $billId =
                (int)$pdo->lastInsertId();
        }

        $pdo->prepare(
            'DELETE FROM clinic_bill_items
             WHERE branch_id=:branch_id
               AND bill_id=:bill_id'
        )->execute([
            ':branch_id' =>
                $branchId,

            ':bill_id' =>
                $billId,
        ]);

        $pdo->prepare(
            'DELETE FROM clinic_bill_payments
             WHERE branch_id=:branch_id
               AND bill_id=:bill_id'
        )->execute([
            ':branch_id' =>
                $branchId,

            ':bill_id' =>
                $billId,
        ]);

        $insert =
            $pdo->prepare(
                'INSERT INTO clinic_bill_items
                (
                    branch_id,
                    bill_id,
                    item_type,
                    reference_id,
                    description,
                    quantity,
                    unit_price,
                    amount,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :branch_id,
                    :bill_id,
                    :item_type,
                    :reference_id,
                    :description,
                    :quantity,
                    :unit_price,
                    :amount,
                    1,
                    :created_by,
                    NOW(),
                    NOW()
                )'
            );

        foreach ($items as $item) {
            $insert->execute([
                ':branch_id' =>
                    $branchId,

                ':bill_id' =>
                    $billId,

                ':item_type' =>
                    $item['item_type'],

                ':reference_id' =>
                    $item['reference_id'],

                ':description' =>
                    $item['description'],

                ':quantity' =>
                    $item['quantity'],

                ':unit_price' =>
                    $item['unit_price'],

                ':amount' =>
                    $item['amount'],

                ':created_by' =>
                    $userId,
            ]);
        }

        $paymentInsert =
            $pdo->prepare(
                'INSERT INTO clinic_bill_payments
                (
                    branch_id,
                    bill_id,
                    payment_mode,
                    account_id,
                    account_name,
                    amount,
                    reference_no,
                    payment_date,
                    sort_order,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :branch_id,
                    :bill_id,
                    :payment_mode,
                    :account_id,
                    :account_name,
                    :amount,
                    :reference_no,
                    :payment_date,
                    :sort_order,
                    1,
                    :created_by,
                    NOW(),
                    NOW()
                )'
            );

        foreach (array_values($payments) as $index => $payment) {
            $paymentInsert->execute([
                ':branch_id' =>
                    $branchId,
                ':bill_id' =>
                    $billId,
                ':payment_mode' =>
                    $payment['mode'],
                ':account_id' =>
                    $payment['account_id'],
                ':account_name' =>
                    $payment['account_name'],
                ':amount' =>
                    $payment['amount'],
                ':reference_no' =>
                    $payment['reference_no'],
                ':payment_date' =>
                    $payment['payment_date'],
                ':sort_order' =>
                    $index + 1,
                ':created_by' =>
                    $userId,
            ]);
        }

        audit_log(
            $userId,
            $isUpdate ? 3 : 2,
            [
                'company_id' =>
                    (int)$ctx['company_id'],

                'branch_id' =>
                    $branchId,

                'menu_id' =>
                    (int)$access['menu']['id'],

                'record_id' =>
                    $billId,

                'old_data' =>
                    $old,

                'new_data' => [
                    'patient_id' =>
                        $patientId,

                    'consultation_id' =>
                        $consultationId,

                    'subtotal' =>
                        $subtotalString,

                    'discount_amount' =>
                        $discount,

                    'grand_total' =>
                        $grandString,

                    'paid_amount' =>
                        $paid,

                    'balance_amount' =>
                        $balanceString,

                    'payment_status' =>
                        $paymentStatus,

                    'items' =>
                        $items,

                    'payments' =>
                        $payments,
                ],
            ]
        );

        $pdo->commit();

        json_success(
            $isUpdate
                ? 'Bill updated successfully.'
                : 'Bill saved successfully.',
            [
                'ref' =>
                    encryptReference(
                        'clinic_bill',
                        $billId
                    ),

                'record' =>
                    bill_record(
                        $ctx,
                        $billId
                    ),
            ]
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_error(
            'Unable to save Bill.',
            500,
            [
                'database' =>
                    $e->getMessage()
            ]
        );
    }
}

json_error(
    'Method not allowed.',
    405
);
