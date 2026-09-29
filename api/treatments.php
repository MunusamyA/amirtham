<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Patient Treatment / Treatment Progress API
 *
 * Progress table stores ONLY:
 * - treatment_day_id
 * - treatment_date
 * - treatment_time
 * - treatment_status
 * - treatment_notes
 * - audit/common fields
 *
 * Pending is derived when no progress row exists.
 */

function treatment_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Patient Treatment is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT
            b.id AS branch_id,
            b.company_id,
            b.branch_name,
            c.company_name
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

function treatment_require_table(string $table, array $required): void
{
    try {
        $stmt = db()->query('SHOW COLUMNS FROM `' . $table . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        json_error(
            'Patient Treatment database setup is incomplete.',
            500,
            ['schema' => 'Missing table ' . $table . '. Run the current Clinic database migration.']
        );
    }

    $found = array_map(
        'strtolower',
        array_column($rows, 'Field')
    );

    $missing = array_values(
        array_diff($required, $found)
    );

    if ($missing !== []) {
        json_error(
            'Patient Treatment database structure is incomplete.',
            500,
            [
                'schema' =>
                    'Missing ' . $table . ' columns: ' .
                    implode(', ', $missing) .
                    '. Run the current Clinic database migration.'
            ]
        );
    }
}

function treatment_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    treatment_require_table('clinic_treatment_procedures', [
        'id',
        'branch_id',
        'procedure_code',
        'procedure_name',
        'status',
    ]);

    treatment_require_table('clinic_patient_treatment_days', [
        'id',
        'branch_id',
        'plan_id',
        'day_number',
        'treatment_procedure_id',
        'instructions',
        'notes',
        'status',
    ]);

    treatment_require_table('clinic_patient_treatment_progress', [
        'id',
        'branch_id',
        'treatment_day_id',
        'treatment_date',
        'treatment_time',
        'treatment_status',
        'treatment_notes',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    $checked = true;
}

function treatment_day_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Treatment Day is required.', 422, [
            'treatment_day_ref' => 'Treatment Day is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'clinic_treatment_day');
    } catch (Throwable $e) {
        json_error('Invalid Treatment Day reference.', 422, [
            'treatment_day_ref' => 'Invalid Treatment Day reference.',
        ]);
    }
}

function treatment_patient_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }

    try {
        return decryptReference(trim($value), 'clinic_patient');
    } catch (Throwable $e) {
        json_error('Invalid Patient reference.', 422, [
            'patient_ref' => 'Invalid Patient reference.',
        ]);
    }
}

function treatment_consultation_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }

    try {
        return decryptReference(trim($value), 'clinic_consultation');
    } catch (Throwable $e) {
        json_error('Invalid Consultation reference.', 422);
    }
}

function treatment_valid_date($value): ?string
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

function treatment_valid_time($value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return null;
    }

    return $value . ':00';
}

function treatment_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    return mb_substr($value, 0, $maxLength);
}

function treatment_day_record(array $ctx, int $dayId): array
{
    $stmt = db()->prepare(
        'SELECT
            td.id,
            td.day_number,
            td.treatment_procedure_id,
            proc.procedure_code,
            proc.procedure_name,
            td.instructions,
            td.notes AS day_notes,
            td.status AS day_status,

            tp.id AS plan_id,
            tp.plan_name,

            c.id AS consultation_id,
            c.consultation_no,
            c.visit_date,
            c.patient_id,
            c.diagnosis_id,

            p.patient_code,
            p.patient_name,
            p.mobile,
            p.gender,
            p.date_of_birth,

            CASE
                WHEN p.date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
            END AS age,

            d.diagnosis_code,
            d.diagnosis_name,

            pr.id AS progress_id,
            pr.treatment_date,
            pr.treatment_time,
            pr.treatment_status,
            pr.treatment_notes,
            pr.created_by AS progress_created_by,
            pr.created_at AS progress_created_at,
            pr.updated_at AS progress_updated_at,

            u.name AS progress_created_by_name

         FROM clinic_patient_treatment_days td

         INNER JOIN clinic_treatment_procedures proc
                 ON proc.id=td.treatment_procedure_id
                AND proc.branch_id=td.branch_id

         INNER JOIN clinic_patient_treatment_plans tp
                 ON tp.id=td.plan_id
                AND tp.branch_id=td.branch_id

         INNER JOIN clinic_consultations c
                 ON c.id=tp.consultation_id
                AND c.branch_id=td.branch_id

         INNER JOIN clinic_patients p
                 ON p.id=c.patient_id
                AND p.branch_id=td.branch_id

         LEFT JOIN clinic_diagnoses d
                ON d.id=c.diagnosis_id
               AND d.branch_id=td.branch_id

         LEFT JOIN clinic_patient_treatment_progress pr
                ON pr.treatment_day_id=td.id
               AND pr.branch_id=td.branch_id

         LEFT JOIN users u
                ON u.id=pr.created_by

         WHERE td.id=:day_id
           AND td.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':day_id' => $dayId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Treatment Day was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['plan_id'] = (int)$row['plan_id'];
    $row['consultation_id'] = (int)$row['consultation_id'];
    $row['patient_id'] = (int)$row['patient_id'];
    $row['diagnosis_id'] =
        $row['diagnosis_id'] === null
            ? null
            : (int)$row['diagnosis_id'];
    $row['progress_id'] =
        $row['progress_id'] === null
            ? null
            : (int)$row['progress_id'];
    $row['day_number'] = (int)$row['day_number'];
    $row['treatment_procedure_id'] =
        (int)$row['treatment_procedure_id'];
    $row['day_status'] = (int)$row['day_status'];

    $row['treatment_procedure_ref'] =
        encryptReference(
            'clinic_treatment_procedure',
            (int)$row['treatment_procedure_id']
        );
    $row['age'] =
        $row['age'] === null
            ? null
            : (int)$row['age'];

    $row['treatment_day_ref'] =
        encryptReference(
            'clinic_treatment_day',
            (int)$row['id']
        );

    $row['patient_ref'] =
        encryptReference(
            'clinic_patient',
            (int)$row['patient_id']
        );

    $row['consultation_ref'] =
        encryptReference(
            'clinic_consultation',
            (int)$row['consultation_id']
        );

    $row['treatment_basis'] =
        $row['diagnosis_id'] === null
            ? 'General'
            : 'Diagnosis Treatment';

    $row['progress_status'] =
        $row['progress_id'] === null
            ? 'Pending'
            : (string)$row['treatment_status'];

    if (!empty($row['treatment_time'])) {
        $row['treatment_time'] =
            substr((string)$row['treatment_time'], 0, 5);
    }

    return $row;
}

function treatment_patient_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT
            p.id,
            p.patient_code,
            p.patient_name,
            p.mobile
         FROM clinic_patient_treatment_days td
         INNER JOIN clinic_patient_treatment_plans tp
                 ON tp.id=td.plan_id
                AND tp.branch_id=td.branch_id
         INNER JOIN clinic_consultations c
                 ON c.id=tp.consultation_id
                AND c.branch_id=td.branch_id
         INNER JOIN clinic_patients p
                 ON p.id=c.patient_id
                AND p.branch_id=td.branch_id
         WHERE td.branch_id=:branch_id
           AND td.status=1
           AND tp.status=1
           AND c.status=1
         ORDER BY p.patient_name,p.id'
    );

    $stmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
                $row['mobile'],
            'label' =>
                (string)$row['patient_code'] .
                ' - ' .
                (string)$row['patient_name'] .
                (
                    !empty($row['mobile'])
                        ? ' - ' . (string)$row['mobile']
                        : ''
                ),
        ];
    }

    return $rows;
}

function treatment_day_options(
    array $ctx,
    int $patientId = 0
): array {
    $sql =
        'SELECT
            td.id,
            td.day_number,
            td.treatment_procedure_id,
            proc.procedure_code,
            proc.procedure_name,
            td.instructions,
            td.status AS day_status,

            tp.plan_name,

            c.id AS consultation_id,
            c.consultation_no,
            c.patient_id,
            c.diagnosis_id,

            p.patient_code,
            p.patient_name,

            d.diagnosis_name,

            pr.id AS progress_id,
            pr.treatment_status

         FROM clinic_patient_treatment_days td

         INNER JOIN clinic_treatment_procedures proc
                 ON proc.id=td.treatment_procedure_id
                AND proc.branch_id=td.branch_id

         INNER JOIN clinic_patient_treatment_plans tp
                 ON tp.id=td.plan_id
                AND tp.branch_id=td.branch_id

         INNER JOIN clinic_consultations c
                 ON c.id=tp.consultation_id
                AND c.branch_id=td.branch_id

         INNER JOIN clinic_patients p
                 ON p.id=c.patient_id
                AND p.branch_id=td.branch_id

         LEFT JOIN clinic_diagnoses d
                ON d.id=c.diagnosis_id
               AND d.branch_id=td.branch_id

         LEFT JOIN clinic_patient_treatment_progress pr
                ON pr.treatment_day_id=td.id
               AND pr.branch_id=td.branch_id

         WHERE td.branch_id=:branch_id
           AND td.status=1
           AND tp.status=1
           AND c.status=1';

    $params = [
        ':branch_id' => (int)$ctx['branch_id'],
    ];

    if ($patientId > 0) {
        $sql .= ' AND c.patient_id=:patient_id';
        $params[':patient_id'] = $patientId;
    }

    $sql .= '
         ORDER BY
            p.patient_name,
            c.visit_date DESC,
            c.id DESC,
            td.day_number,
            td.id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status =
            $row['progress_id'] === null
                ? 'Pending'
                : (string)$row['treatment_status'];

        $rows[] = [
            'ref' =>
                encryptReference(
                    'clinic_treatment_day',
                    (int)$row['id']
                ),
            'patient_ref' =>
                encryptReference(
                    'clinic_patient',
                    (int)$row['patient_id']
                ),
            'consultation_ref' =>
                encryptReference(
                    'clinic_consultation',
                    (int)$row['consultation_id']
                ),
            'patient_code' =>
                (string)$row['patient_code'],
            'patient_name' =>
                (string)$row['patient_name'],
            'consultation_no' =>
                (string)$row['consultation_no'],
            'diagnosis_name' =>
                $row['diagnosis_name'],
            'plan_name' =>
                (string)$row['plan_name'],
            'treatment_basis' =>
                $row['diagnosis_id'] === null
                    ? 'General'
                    : 'Diagnosis Treatment',
            'day_number' =>
                (int)$row['day_number'],
            'treatment_procedure_ref' =>
                encryptReference(
                    'clinic_treatment_procedure',
                    (int)$row['treatment_procedure_id']
                ),
            'procedure_code' =>
                (string)$row['procedure_code'],
            'procedure_name' =>
                (string)$row['procedure_name'],
            'instructions' =>
                $row['instructions'],
            'progress_status' =>
                $status,
            'label' =>
                (string)$row['patient_code'] .
                ' - ' .
                (string)$row['patient_name'] .
                ' / ' .
                (string)$row['consultation_no'] .
                ' / Day ' .
                (int)$row['day_number'] .
                ' - ' .
                (string)$row['procedure_name'] .
                ' [' .
                $status .
                ']',
        ];
    }

    return $rows;
}

$method = request_method();
treatment_require_schema();

/* Form / filter options */
if ($method === 'GET' && isset($_GET['options'])) {
    $access =
        require_permission(
            'treatment-list.php',
            ACTION_VIEW
        );

    $ctx =
        treatment_context(
            $access['user']
        );

    $patientId =
        treatment_patient_ref_to_id(
            $_GET['patient_ref'] ?? ''
        );

    json_success(
        'Patient Treatment options loaded.',
        [
            'allowed_actions' =>
                $access['actions'],

            'patients' =>
                treatment_patient_options(
                    $ctx
                ),

            'treatment_days' =>
                treatment_day_options(
                    $ctx,
                    $patientId
                ),

            'progress_statuses' => [
                'Pending',
                'In Progress',
                'Completed',
                'Skipped',
            ],

            'branch' => $ctx,
        ]
    );
}

/* One Treatment Day / Progress */
if ($method === 'GET' && isset($_GET['day_ref'])) {
    $access =
        require_permission(
            'treatment-list.php',
            ACTION_VIEW
        );

    $ctx =
        treatment_context(
            $access['user']
        );

    $dayId =
        treatment_day_ref_to_id(
            $_GET['day_ref'] ?? ''
        );

    json_success(
        'Treatment Day loaded.',
        [
            'record' =>
                treatment_day_record(
                    $ctx,
                    $dayId
                ),

            'allowed_actions' =>
                $access['actions'],

            'patients' =>
                treatment_patient_options(
                    $ctx
                ),

            'treatment_days' =>
                treatment_day_options(
                    $ctx
                ),
        ]
    );
}

/* DataTable */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access =
        require_permission(
            'treatment-list.php',
            ACTION_VIEW
        );

    $ctx =
        treatment_context(
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
                $_GET['search']['value'] ??
                ''
            )
        );

    $patientId =
        treatment_patient_ref_to_id(
            $_GET['patient_ref'] ?? ''
        );

    $consultationId =
        treatment_consultation_ref_to_id(
            $_GET['consultation_ref'] ?? ''
        );

    $progressStatus =
        trim(
            (string)(
                $_GET['progress_status'] ??
                ''
            )
        );

    $treatmentBasis =
        strtolower(
            trim(
                (string)(
                    $_GET['treatment_basis'] ??
                    ''
                )
            )
        );

    $where = [
        'td.branch_id=:branch_id',
        'td.status=1',
        'tp.status=1',
        'c.status=1',
    ];

    $params = [
        ':branch_id' => $branchId,
    ];

    if ($patientId > 0) {
        $where[] = 'c.patient_id=:patient_id';
        $params[':patient_id'] = $patientId;
    }

    if ($consultationId > 0) {
        $where[] = 'c.id=:consultation_id';
        $params[':consultation_id'] = $consultationId;
    }

    if ($treatmentBasis === 'protocol') {
        $where[] = 'c.diagnosis_id IS NOT NULL';
    } elseif ($treatmentBasis === 'general') {
        $where[] = 'c.diagnosis_id IS NULL';
    }

    if ($progressStatus === 'Pending') {
        $where[] = 'pr.id IS NULL';
    } elseif (
        in_array(
            $progressStatus,
            [
                'In Progress',
                'Completed',
                'Skipped',
            ],
            true
        )
    ) {
        $where[] = 'pr.treatment_status=:progress_status';
        $params[':progress_status'] = $progressStatus;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';

        $where[] =
            '(p.patient_code LIKE :s1
              OR p.patient_name LIKE :s2
              OR c.consultation_no LIKE :s3
              OR d.diagnosis_name LIKE :s4
              OR tp.plan_name LIKE :s5
              OR proc.procedure_name LIKE :s6)';

        $params += [
            ':s1' => $like,
            ':s2' => $like,
            ':s3' => $like,
            ':s4' => $like,
            ':s5' => $like,
            ':s6' => $like,
        ];
    }

    $from =
        ' FROM clinic_patient_treatment_days td
          INNER JOIN clinic_treatment_procedures proc
                  ON proc.id=td.treatment_procedure_id
                 AND proc.branch_id=td.branch_id
          INNER JOIN clinic_patient_treatment_plans tp
                  ON tp.id=td.plan_id
                 AND tp.branch_id=td.branch_id
          INNER JOIN clinic_consultations c
                  ON c.id=tp.consultation_id
                 AND c.branch_id=td.branch_id
          INNER JOIN clinic_patients p
                  ON p.id=c.patient_id
                 AND p.branch_id=td.branch_id
          LEFT JOIN clinic_diagnoses d
                 ON d.id=c.diagnosis_id
                AND d.branch_id=td.branch_id
          LEFT JOIN clinic_patient_treatment_progress pr
                 ON pr.treatment_day_id=td.id
                AND pr.branch_id=td.branch_id ';

    $totalStmt =
        db()->prepare(
            'SELECT COUNT(*) ' .
            $from .
            ' WHERE
                td.branch_id=:branch_id
                AND td.status=1
                AND tp.status=1
                AND c.status=1'
        );

    $totalStmt->execute([
        ':branch_id' => $branchId,
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $countStmt =
        db()->prepare(
            'SELECT COUNT(*) ' .
            $from .
            ' WHERE ' .
            implode(' AND ', $where)
        );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $orderColumns = [
        'p.patient_name',
        'c.consultation_no',
        'td.day_number',
        'proc.procedure_name',
        'pr.treatment_date',
        'pr.treatment_status',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column'] ??
            0
        );

    $orderDir =
        strtolower(
            (string)(
                $_GET['order'][0]['dir'] ??
                'asc'
            )
        ) === 'desc'
            ? 'DESC'
            : 'ASC';

    $orderBy =
        $orderColumns[$orderIndex] ??
        'p.patient_name';

    $sql =
        'SELECT
            td.id,
            td.day_number,
            td.treatment_procedure_id,
            proc.procedure_code,
            proc.procedure_name,
            td.instructions,

            tp.plan_name,

            c.id AS consultation_id,
            c.consultation_no,
            c.diagnosis_id,

            p.patient_code,
            p.patient_name,
            p.mobile,

            d.diagnosis_code,
            d.diagnosis_name,

            pr.id AS progress_id,
            pr.treatment_date,
            pr.treatment_time,
            pr.treatment_status,
            pr.treatment_notes

         ' .
         $from .
         ' WHERE ' .
         implode(' AND ', $where) .
         ' ORDER BY ' .
         $orderBy .
         ' ' .
         $orderDir .
         ',c.id DESC,td.day_number,td.id
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array(
                $key,
                [
                    ':branch_id',
                    ':patient_id',
                    ':consultation_id',
                ],
                true
            )
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
        $dayRef =
            encryptReference(
                'clinic_treatment_day',
                (int)$row['id']
            );

        $rows[] = [
            'treatment_day_ref' =>
                $dayRef,

            'consultation_ref' =>
                encryptReference(
                    'clinic_consultation',
                    (int)$row['consultation_id']
                ),

            'patient_code' =>
                (string)$row['patient_code'],

            'patient_name' =>
                (string)$row['patient_name'],

            'mobile' =>
                $row['mobile'],

            'consultation_no' =>
                (string)$row['consultation_no'],

            'diagnosis_code' =>
                $row['diagnosis_code'],

            'diagnosis_name' =>
                $row['diagnosis_name'],

            'treatment_basis' =>
                $row['diagnosis_id'] === null
                    ? 'General'
                    : 'Diagnosis Treatment',

            /*
             * Compatibility aliases for older frontend code.
             * Protocol table has been removed, so use Diagnosis values.
             */
            'protocol_code' =>
                $row['diagnosis_code'],

            'protocol_name' =>
                $row['diagnosis_name'],

            'plan_name' =>
                (string)$row['plan_name'],

            'day_number' =>
                (int)$row['day_number'],

            'treatment_procedure_ref' =>
                encryptReference(
                    'clinic_treatment_procedure',
                    (int)$row['treatment_procedure_id']
                ),

            'procedure_code' =>
                (string)$row['procedure_code'],

            'procedure_name' =>
                (string)$row['procedure_name'],

            'instructions' =>
                $row['instructions'],

            'treatment_date' =>
                $row['treatment_date'],

            'treatment_time' =>
                !empty($row['treatment_time'])
                    ? substr(
                        (string)$row['treatment_time'],
                        0,
                        5
                    )
                    : null,

            'progress_status' =>
                $row['progress_id'] === null
                    ? 'Pending'
                    : (string)$row['treatment_status'],

            'treatment_notes' =>
                $row['treatment_notes'],

            'form_url' =>
                'treatment-form.php?day_ref=' .
                rawurlencode(
                    $dayRef
                ),
        ];
    }

    json_success(
        'Patient Treatment list loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],

            'list_actions' =>
                $access['actions'],

            'form_actions' =>
                $access['actions'],
        ]
    );
}

/* Save Progress */
if ($method === 'POST') {
    $data = request_data();

    $dayId =
        treatment_day_ref_to_id(
            $data['treatment_day_ref'] ??
            ''
        );

    $baseAccess =
        require_permission(
            'treatment-list.php',
            ACTION_VIEW
        );

    $ctx =
        treatment_context(
            $baseAccess['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $day =
        treatment_day_record(
            $ctx,
            $dayId
        );

    $existingId =
        (int)(
            $day['progress_id'] ??
            0
        );

    $access =
        require_permission(
            'treatment-list.php',
            $existingId > 0
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $userId =
        (int)$access['user']['id'];

    $dateRaw =
        trim(
            (string)(
                $data['treatment_date'] ??
                ''
            )
        );

    $date =
        treatment_valid_date(
            $dateRaw
        );

    if ($date === null) {
        json_error(
            'Treatment Progress validation failed.',
            422,
            [
                'treatment_date' =>
                    'Enter a valid Treatment Date.'
            ]
        );
    }

    $timeRaw =
        trim(
            (string)(
                $data['treatment_time'] ??
                ''
            )
        );

    $time =
        treatment_valid_time(
            $timeRaw
        );

    if ($time === null) {
        json_error(
            'Treatment Progress validation failed.',
            422,
            [
                'treatment_time' =>
                    'Enter a valid Treatment Time.'
            ]
        );
    }

    $status =
        trim(
            (string)(
                $data['treatment_status'] ??
                ''
            )
        );

    if (
        !in_array(
            $status,
            [
                'In Progress',
                'Completed',
                'Skipped',
            ],
            true
        )
    ) {
        json_error(
            'Treatment Progress validation failed.',
            422,
            [
                'treatment_status' =>
                    'Select a valid Treatment Status.'
            ]
        );
    }

    $notes =
        treatment_nullable_text(
            $data['treatment_notes'] ??
            null,
            5000
        );

    if ($existingId > 0) {
        $stmt =
            db()->prepare(
                'UPDATE clinic_patient_treatment_progress
                 SET
                    treatment_date=:treatment_date,
                    treatment_time=:treatment_time,
                    treatment_status=:treatment_status,
                    treatment_notes=:treatment_notes,
                    updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

        $stmt->execute([
            ':treatment_date' => $date,
            ':treatment_time' => $time,
            ':treatment_status' => $status,
            ':treatment_notes' => $notes,
            ':id' => $existingId,
            ':branch_id' => $branchId,
        ]);

        audit_log(
            $userId,
            3,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $existingId,
                'new_data' => [
                    'treatment_day_id' => $dayId,
                    'treatment_date' => $date,
                    'treatment_time' => $time,
                    'treatment_status' => $status,
                    'treatment_notes' => $notes,
                ],
            ]
        );

        json_success(
            'Treatment Progress updated successfully.'
        );
    }

    $stmt =
        db()->prepare(
            'INSERT INTO clinic_patient_treatment_progress
            (
                branch_id,
                treatment_day_id,
                treatment_date,
                treatment_time,
                treatment_status,
                treatment_notes,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                :branch_id,
                :treatment_day_id,
                :treatment_date,
                :treatment_time,
                :treatment_status,
                :treatment_notes,
                :created_by,
                NOW(),
                NOW()
            )'
        );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':treatment_day_id' => $dayId,
        ':treatment_date' => $date,
        ':treatment_time' => $time,
        ':treatment_status' => $status,
        ':treatment_notes' => $notes,
        ':created_by' => $userId,
    ]);

    $progressId =
        (int)db()->lastInsertId();

    audit_log(
        $userId,
        2,
        [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $progressId,
            'new_data' => [
                'treatment_day_id' => $dayId,
                'treatment_date' => $date,
                'treatment_time' => $time,
                'treatment_status' => $status,
                'treatment_notes' => $notes,
            ],
        ]
    );

    json_success(
        'Treatment Progress saved successfully.'
    );
}

json_error('Method not allowed.', 405);