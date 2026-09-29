<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Clinic Consultation API
 *
 * Supports:
 * - Appointment-based Consultation
 * - Walk-in Consultation
 * - Diagnosis-based Treatment Plan
 * - General Treatment Plan
 *
 * Database rules:
 * - patient_id is required
 * - appointment_id is nullable (NULL = Walk-in)
 * - diagnosis_id is the direct source for Diagnosis-based Treatment
 * - diagnosis_id is NULL for General Treatment
 * - no Protocol master / protocol_id layer is used
 */

function consultation_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Clinic Consultation is available only for tenant users.', 403);
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

function consultation_require_table(string $table, array $required): void
{
    try {
        $stmt = db()->query('SHOW COLUMNS FROM `' . $table . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        json_error(
            'Clinic Consultation database setup is incomplete.',
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
            'Clinic Consultation database structure is incomplete.',
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

function consultation_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    consultation_require_table('clinic_consultations', [
        'id',
        'branch_id',
        'consultation_no',
        'patient_id',
        'appointment_id',
        'visit_date',
        'visit_time',
        'consultant_name',
        'chief_complaint',
        'symptoms',
        'clinical_notes',
        'diagnosis_id',
        'consultation_fee',
        'consultation_status',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    consultation_require_table('clinic_patient_treatment_plans', [
        'id',
        'branch_id',
        'consultation_id',
        'plan_name',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    consultation_require_table('clinic_diagnosis_treatment_days', [
        'id',
        'branch_id',
        'diagnosis_id',
        'day_number',
        'treatment_procedure_id',
        'instructions',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    consultation_require_table('clinic_treatment_procedures', [
        'id',
        'branch_id',
        'procedure_code',
        'procedure_name',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    consultation_require_table('clinic_patient_treatment_days', [
        'id',
        'branch_id',
        'plan_id',
        'day_number',
        'treatment_procedure_id',
        'instructions',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ]);

    consultation_require_table('clinic_medicines', [
        'id','branch_id','medicine_code','medicine_name','medicine_type',
        'unit','pack_size','selling_price','status','created_by','created_at','updated_at',
    ]);

    consultation_require_table('clinic_consultation_prescriptions', [
        'id','branch_id','consultation_id','medicine_id','quantity','unit_price','amount',
        'dosage','frequency','timing','anupana','duration','instructions','status',
        'created_by','created_at','updated_at',
    ]);

    $checked = true;
}

function consultation_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Consultation reference is required.', 422, [
            'ref' => 'Consultation reference is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'clinic_consultation');
    } catch (Throwable $e) {
        json_error('Invalid Consultation reference.', 422, [
            'ref' => 'Invalid Consultation reference.',
        ]);
    }
}

function consultation_patient_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Patient is required.', 422, [
            'patient_ref' => 'Patient is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'clinic_patient');
    } catch (Throwable $e) {
        json_error('Invalid Patient reference.', 422, [
            'patient_ref' => 'Invalid Patient reference.',
        ]);
    }
}

function consultation_appointment_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }

    try {
        return decryptReference(trim($value), 'clinic_appointment');
    } catch (Throwable $e) {
        json_error('Invalid Appointment reference.', 422, [
            'appointment_ref' => 'Invalid Appointment reference.',
        ]);
    }
}

function consultation_diagnosis_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }

    try {
        return decryptReference(trim($value), 'clinic_diagnosis');
    } catch (Throwable $e) {
        json_error('Invalid Diagnosis reference.', 422, [
            'diagnosis_ref' => 'Invalid Diagnosis reference.',
        ]);
    }
}

function consultation_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    return mb_substr($value, 0, $maxLength);
}

function consultation_valid_date($value): ?string
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

function consultation_valid_time($value): ?string
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

function consultation_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT consultation_no
         FROM clinic_consultations
         WHERE branch_id=:branch_id
           AND consultation_no REGEXP '^CNS[0-9]+$'
         ORDER BY CAST(SUBSTRING(consultation_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^CNS([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'CNS' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function consultation_patient(array $ctx, int $patientId, bool $requireActive = true): array
{
    $sql =
        'SELECT
            id,
            patient_code,
            patient_name,
            date_of_birth,
            gender,
            mobile,
            blood_group,
            status,
            CASE
                WHEN date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE())
            END AS age
         FROM clinic_patients
         WHERE id=:id
           AND branch_id=:branch_id';

    if ($requireActive) {
        $sql .= ' AND status=1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $patientId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Selected Patient was not found.', 422, [
            'patient_ref' => 'Select a valid active Patient.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    $row['age'] = $row['age'] === null ? null : (int)$row['age'];
    $row['ref'] = encryptReference('clinic_patient', (int)$row['id']);

    return $row;
}

function consultation_patient_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            patient_code,
            patient_name,
            date_of_birth,
            gender,
            mobile,
            blood_group,
            CASE
                WHEN date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE())
            END AS age
         FROM clinic_patients
         WHERE branch_id=:branch_id
           AND status=1
         ORDER BY patient_name,id'
    );

    $stmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('clinic_patient', (int)$row['id']),
            'patient_code' => (string)$row['patient_code'],
            'patient_name' => (string)$row['patient_name'],
            'date_of_birth' => $row['date_of_birth'],
            'age' => $row['age'] === null ? null : (int)$row['age'],
            'gender' => $row['gender'],
            'mobile' => $row['mobile'],
            'blood_group' => $row['blood_group'],
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

function consultation_appointment(array $ctx, int $appointmentId): array
{
    $stmt = db()->prepare(
        'SELECT
            a.id,
            a.appointment_no,
            a.patient_id,
            a.appointment_date,
            a.appointment_time,
            a.consultant_name,
            a.visit_type,
            a.appointment_status,
            a.status,
            p.patient_code,
            p.patient_name,
            p.date_of_birth,
            p.gender,
            p.mobile,
            p.blood_group,
            CASE
                WHEN p.date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
            END AS age
         FROM clinic_appointments a
         INNER JOIN clinic_patients p
                 ON p.id=a.patient_id
                AND p.branch_id=a.branch_id
         WHERE a.id=:id
           AND a.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $appointmentId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Selected Appointment was not found.', 422, [
            'appointment_ref' => 'Select a valid Appointment.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['patient_id'] = (int)$row['patient_id'];
    $row['status'] = (int)$row['status'];
    $row['age'] = $row['age'] === null ? null : (int)$row['age'];

    return $row;
}

function consultation_appointment_options(array $ctx, int $includeId = 0): array
{
    $sql =
        'SELECT
            a.id,
            a.appointment_no,
            a.patient_id,
            a.appointment_date,
            a.appointment_time,
            a.consultant_name,
            a.visit_type,
            a.appointment_status,
            a.status,
            p.patient_code,
            p.patient_name,
            p.date_of_birth,
            p.gender,
            p.mobile,
            CASE
                WHEN p.date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
            END AS age
         FROM clinic_appointments a
         INNER JOIN clinic_patients p
                 ON p.id=a.patient_id
                AND p.branch_id=a.branch_id
         LEFT JOIN clinic_consultations c
                ON c.appointment_id=a.id
               AND c.branch_id=a.branch_id
               AND c.status=1
         WHERE a.branch_id=:branch_id
           AND (
                (
                    a.status=1
                    AND a.appointment_status NOT IN (\'Cancelled\',\'No Show\')
                    AND c.id IS NULL
                )';

    $params = [
        ':branch_id' => (int)$ctx['branch_id'],
    ];

    if ($includeId > 0) {
        $sql .= ' OR a.id=:include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ')
         ORDER BY a.appointment_date DESC,a.appointment_time DESC,a.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('clinic_appointment', (int)$row['id']),
            'patient_ref' => encryptReference('clinic_patient', (int)$row['patient_id']),
            'appointment_no' => (string)$row['appointment_no'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' =>
                !empty($row['appointment_time'])
                    ? substr((string)$row['appointment_time'], 0, 5)
                    : null,
            'consultant_name' => $row['consultant_name'],
            'visit_type' => $row['visit_type'],
            'appointment_status' => $row['appointment_status'],
            'status' => (int)$row['status'],
            'patient_code' => (string)$row['patient_code'],
            'patient_name' => (string)$row['patient_name'],
            'mobile' => $row['mobile'],
            'age' => $row['age'] === null ? null : (int)$row['age'],
            'gender' => $row['gender'],
            'label' =>
                (string)$row['appointment_no'] .
                ' - ' .
                (string)$row['patient_code'] .
                ' - ' .
                (string)$row['patient_name'] .
                ' - ' .
                (string)$row['appointment_date'],
        ];
    }

    return $rows;
}

function consultation_diagnosis_options(array $ctx, int $includeId = 0): array
{
    $sql =
        'SELECT
            id,
            diagnosis_code,
            diagnosis_name,
            category,
            status
         FROM clinic_diagnoses
         WHERE branch_id=:branch_id
           AND (status=1';

    $params = [
        ':branch_id' => (int)$ctx['branch_id'],
    ];

    if ($includeId > 0) {
        $sql .= ' OR id=:include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ')
         ORDER BY diagnosis_name,id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('clinic_diagnosis', (int)$row['id']),
            'diagnosis_code' => (string)$row['diagnosis_code'],
            'diagnosis_name' => (string)$row['diagnosis_name'],
            'category' => $row['category'],
            'status' => (int)$row['status'],
            'label' =>
                (string)$row['diagnosis_code'] .
                ' - ' .
                (string)$row['diagnosis_name'],
        ];
    }

    return $rows;
}

function consultation_diagnosis(array $ctx, int $diagnosisId, bool $requireActive = true): array
{
    $sql =
        'SELECT
            id,
            diagnosis_code,
            diagnosis_name,
            category,
            status
         FROM clinic_diagnoses
         WHERE id=:id
           AND branch_id=:branch_id';

    if ($requireActive) {
        $sql .= ' AND status=1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $diagnosisId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Selected Diagnosis was not found.', 422, [
            'diagnosis_ref' => 'Select a valid active Diagnosis.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];

    return $row;
}

function consultation_treatment_procedure_options(
    array $ctx,
    int $includeId = 0
): array {
    $sql =
        'SELECT
            id,
            procedure_code,
            procedure_name,
            status
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id
           AND (status=1';

    $params = [
        ':branch_id' => (int)$ctx['branch_id'],
    ];

    if ($includeId > 0) {
        $sql .= ' OR id=:include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ')
         ORDER BY procedure_name,id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' =>
                encryptReference(
                    'clinic_treatment_procedure',
                    (int)$row['id']
                ),
            'procedure_code' =>
                (string)$row['procedure_code'],
            'procedure_name' =>
                (string)$row['procedure_name'],
            'status' =>
                (int)$row['status'],
            'label' =>
                (string)$row['procedure_code'] .
                ' - ' .
                (string)$row['procedure_name'],
        ];
    }

    return $rows;
}

function consultation_treatment_procedure_ref_to_id(
    array $ctx,
    $value
): int {
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Treatment / Procedure is required.',
            422
        );
    }

    try {
        $id = decryptReference(
            trim($value),
            'clinic_treatment_procedure'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Treatment / Procedure reference.',
            422
        );
    }

    $stmt = db()->prepare(
        'SELECT id
         FROM clinic_treatment_procedures
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    if (!$stmt->fetchColumn()) {
        json_error(
            'Selected Treatment / Procedure is invalid or inactive.',
            422
        );
    }

    return $id;
}

function consultation_treatment_plan_by_diagnosis(
    array $ctx,
    int $diagnosisId
): array {
    if ($diagnosisId < 1) {
        return [
            'treatment_plan' => null,
            'protocol' => null,
            'days' => [],
        ];
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            diagnosis_code,
            diagnosis_name,
            category,
            description,
            status
         FROM clinic_diagnoses
         WHERE id=:diagnosis_id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':diagnosis_id' => $diagnosisId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $diagnosis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$diagnosis) {
        return [
            'treatment_plan' => null,
            'protocol' => null,
            'days' => [],
        ];
    }

    $dayStmt = db()->prepare(
        'SELECT
            d.day_number,
            d.treatment_procedure_id,
            p.procedure_code,
            p.procedure_name,
            d.instructions,
            d.notes,
            d.status
         FROM clinic_diagnosis_treatment_days d
         INNER JOIN clinic_treatment_procedures p
                 ON p.id=d.treatment_procedure_id
                AND p.branch_id=d.branch_id
         WHERE d.branch_id=:branch_id
           AND d.diagnosis_id=:diagnosis_id
           AND d.status=1
         ORDER BY d.day_number,d.id'
    );

    $dayStmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
        ':diagnosis_id' => $diagnosisId,
    ]);

    $days = [];

    foreach ($dayStmt->fetchAll(PDO::FETCH_ASSOC) as $day) {
        $days[] = [
            'day_number' => (int)$day['day_number'],
            'treatment_procedure_ref' =>
                encryptReference(
                    'clinic_treatment_procedure',
                    (int)$day['treatment_procedure_id']
                ),
            'procedure_code' => (string)$day['procedure_code'],
            'procedure_name' => (string)$day['procedure_name'],
            'instructions' => $day['instructions'],
            'notes' => $day['notes'],
            'status' => (int)$day['status'],
        ];
    }

    if ($days === []) {
        return [
            'treatment_plan' => null,
            'protocol' => null,
            'days' => [],
        ];
    }

    $plan = [
        'id' => (int)$diagnosis['id'],
        'ref' =>
            encryptReference(
                'clinic_diagnosis',
                (int)$diagnosis['id']
            ),
        'diagnosis_code' => (string)$diagnosis['diagnosis_code'],
        'diagnosis_name' => (string)$diagnosis['diagnosis_name'],
        'category' => $diagnosis['category'],
        'duration_days' => count($days),
        'description' => $diagnosis['description'],
        'status' => (int)$diagnosis['status'],

        /*
         * Compatibility aliases for the existing Consultation Form.
         * These are API aliases only; there is no Protocol table anymore.
         */
        'protocol_code' => (string)$diagnosis['diagnosis_code'],
        'protocol_name' => (string)$diagnosis['diagnosis_name'],
    ];

    return [
        'treatment_plan' => $plan,
        'protocol' => $plan,
        'days' => $days,
    ];
}

function consultation_clean_days(array $ctx, $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($value)) {
        $value = [];
    }

    $clean = [];

    foreach (array_values($value) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $procedureRef =
            trim(
                (string)(
                    $row['treatment_procedure_ref'] ??
                    ''
                )
            );

        $treatmentProcedureId = 0;

        if ($procedureRef !== '') {
            $treatmentProcedureId =
                consultation_treatment_procedure_ref_to_id(
                    $ctx,
                    $procedureRef
                );
        }

        $instructions =
            consultation_nullable_text(
                $row['instructions'] ?? null,
                5000
            );

        $notes =
            consultation_nullable_text(
                $row['notes'] ?? null,
                5000
            );

        $status =
            (int)($row['status'] ?? 1);

        if (
            $procedureRef === '' &&
            $instructions === null &&
            $notes === null
        ) {
            continue;
        }

        if ($procedureRef === '' || $treatmentProcedureId < 1) {
            json_error(
                'Treatment Plan validation failed.',
                422,
                [
                    'treatment_days' =>
                        'Treatment / Procedure is required for Day ' .
                        ($index + 1) .
                        '.',
                ]
            );
        }


        if (!in_array($status, [0,1], true)) {
            $status = 1;
        }

        $clean[] = [
            'day_number' => count($clean) + 1,
            'treatment_procedure_id' =>
                $treatmentProcedureId,
            'treatment_procedure_ref' =>
                $procedureRef,
            'instructions' => $instructions,
            'notes' => $notes,
            'status' => $status,
        ];
    }

    return $clean;
}

function consultation_medicine_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT id,medicine_code,medicine_name,medicine_type,unit,pack_size,selling_price,status
         FROM clinic_medicines
         WHERE branch_id=:branch_id AND status=1
         ORDER BY medicine_name,id'
    );
    $stmt->execute([':branch_id'=>(int)$ctx['branch_id']]);
    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[]=[
            'ref'=>encryptReference('clinic_medicine',(int)$row['id']),
            'medicine_code'=>(string)$row['medicine_code'],
            'medicine_name'=>(string)$row['medicine_name'],
            'medicine_type'=>(string)$row['medicine_type'],
            'unit'=>$row['unit'],
            'pack_size'=>$row['pack_size'],
            'selling_price'=>number_format((float)$row['selling_price'],2,'.',''),
            'status'=>(int)$row['status'],
            'label'=>(string)$row['medicine_code'].' - '.(string)$row['medicine_name'],
        ];
    }
    return $rows;
}

function consultation_medicine_ref_to_record(array $ctx,$value): array
{
    if (!is_string($value) || trim($value)==='') {
        json_error('Medicine is required.',422,['prescription_items'=>'Medicine is required.']);
    }
    try {
        $id=decryptReference(trim($value),'clinic_medicine');
    } catch (Throwable $e) {
        json_error('Invalid Medicine reference.',422,['prescription_items'=>'Select a valid Medicine.']);
    }
    $stmt=db()->prepare(
        'SELECT id,medicine_code,medicine_name,medicine_type,unit,pack_size,selling_price,status
         FROM clinic_medicines
         WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>(int)$ctx['branch_id']]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Selected Medicine is invalid or inactive.',422,['prescription_items'=>'Select a valid active Medicine.']);
    $row['id']=(int)$row['id'];
    return $row;
}

function consultation_clean_prescriptions(array $ctx,$value): array
{
    if (is_string($value)) {
        $decoded=json_decode($value,true);
        $value=is_array($decoded)?$decoded:[];
    }
    if (!is_array($value)) $value=[];
    $clean=[];$seen=[];
    foreach (array_values($value) as $index=>$row) {
        if (!is_array($row)) continue;
        $medicineRef=trim((string)($row['medicine_ref'] ?? ''));
        $quantityRaw=trim((string)($row['quantity'] ?? '1'));
        $dosage=consultation_nullable_text($row['dosage'] ?? null,100);
        $frequency=consultation_nullable_text($row['frequency'] ?? null,100);
        $timing=consultation_nullable_text($row['timing'] ?? null,100);
        $anupana=consultation_nullable_text($row['anupana'] ?? null,150);
        $duration=consultation_nullable_text($row['duration'] ?? null,100);
        $instructions=consultation_nullable_text($row['instructions'] ?? null,5000);
        if ($medicineRef==='' && $dosage===null && $instructions===null) continue;
        $medicine=consultation_medicine_ref_to_record($ctx,$medicineRef);
        if (isset($seen[$medicine['id']])) {
            json_error('Prescription validation failed.',422,['prescription_items'=>'The same Medicine cannot be added more than once.']);
        }
        $seen[$medicine['id']]=true;
        if ($quantityRaw==='' || !is_numeric($quantityRaw) || (float)$quantityRaw<=0) {
            json_error('Prescription validation failed.',422,['prescription_items'=>'Enter a valid Quantity for prescription row '.($index+1).'.']);
        }
        $quantity=round((float)$quantityRaw,3);
        $unitPrice=round((float)$medicine['selling_price'],2);
        $amount=round($quantity*$unitPrice,2);
        $clean[]=[
            'medicine_id'=>(int)$medicine['id'],
            'medicine_ref'=>$medicineRef,
            'medicine_code'=>(string)$medicine['medicine_code'],
            'medicine_name'=>(string)$medicine['medicine_name'],
            'quantity'=>number_format($quantity,3,'.',''),
            'unit_price'=>number_format($unitPrice,2,'.',''),
            'amount'=>number_format($amount,2,'.',''),
            'dosage'=>$dosage,'frequency'=>$frequency,'timing'=>$timing,
            'anupana'=>$anupana,'duration'=>$duration,'instructions'=>$instructions,'status'=>1,
        ];
    }
    return $clean;
}

function consultation_money(
    $value,
    string $field
): string {
    $raw = trim((string)$value);

    if ($raw === '' || !is_numeric($raw)) {
        json_error(
            'Consultation validation failed.',
            422,
            [
                $field =>
                    'Enter a valid amount.'
            ]
        );
    }

    $amount = round((float)$raw, 2);

    if ($amount < 0) {
        json_error(
            'Consultation validation failed.',
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

function consultation_validate(array $data, array $ctx): array
{
    $errors = [];

    $visitSource =
        strtolower(
            trim(
                (string)(
                    $data['visit_source'] ??
                    'appointment'
                )
            )
        );

    if (!in_array($visitSource, ['appointment','walkin'], true)) {
        $visitSource = 'appointment';
    }

    $appointmentId = 0;
    $patientId = 0;
    $appointment = null;

    if ($visitSource === 'appointment') {
        $appointmentId =
            consultation_appointment_ref_to_id(
                $data['appointment_ref'] ?? ''
            );

        if ($appointmentId < 1) {
            $errors['appointment_ref'] =
                'Appointment is required for Appointment visit.';
        } else {
            $appointment =
                consultation_appointment(
                    $ctx,
                    $appointmentId
                );

            if ((int)$appointment['status'] !== 1) {
                $errors['appointment_ref'] =
                    'Selected Appointment is inactive.';
            }

            if (
                in_array(
                    (string)$appointment['appointment_status'],
                    ['Cancelled','No Show'],
                    true
                )
            ) {
                $errors['appointment_ref'] =
                    'Cancelled / No Show Appointment cannot be used for Consultation.';
            }

            $patientId =
                (int)$appointment['patient_id'];
        }
    } else {
        $patientId =
            consultation_patient_ref_to_id(
                $data['patient_ref'] ?? ''
            );
    }

    if ($patientId > 0) {
        consultation_patient(
            $ctx,
            $patientId,
            true
        );
    }

    $visitDateRaw =
        trim((string)($data['visit_date'] ?? ''));

    $visitDate =
        consultation_valid_date($visitDateRaw);

    if ($visitDateRaw === '') {
        $errors['visit_date'] =
            'Visit Date is required.';
    } elseif ($visitDate === null) {
        $errors['visit_date'] =
            'Enter a valid Visit Date.';
    }

    $visitTimeRaw =
        trim((string)($data['visit_time'] ?? ''));

    $visitTime =
        consultation_valid_time($visitTimeRaw);

    if ($visitTimeRaw === '') {
        $errors['visit_time'] =
            'Visit Time is required.';
    } elseif ($visitTime === null) {
        $errors['visit_time'] =
            'Enter a valid Visit Time.';
    }

    $consultantName =
        consultation_nullable_text(
            $data['consultant_name'] ?? null,
            150
        );

    $chiefComplaint =
        consultation_nullable_text(
            $data['chief_complaint'] ?? null,
            5000
        );

    $symptoms =
        consultation_nullable_text(
            $data['symptoms'] ?? null,
            5000
        );

    $clinicalNotes =
        consultation_nullable_text(
            $data['clinical_notes'] ?? null,
            5000
        );

    $treatmentBasis =
        strtolower(
            trim(
                (string)(
                    $data['treatment_basis'] ??
                    'protocol'
                )
            )
        );

    if (!in_array($treatmentBasis, ['protocol','general'], true)) {
        $treatmentBasis = 'protocol';
    }

    $diagnosisId =
        consultation_diagnosis_ref_to_id(
            $data['diagnosis_ref'] ?? ''
        );

    if ($treatmentBasis === 'protocol' && $diagnosisId < 1) {
        $errors['diagnosis_ref'] =
            'Diagnosis is required for Diagnosis Treatment.';
    }

    if ($diagnosisId > 0) {
        consultation_diagnosis(
            $ctx,
            $diagnosisId,
            true
        );
    }

    $treatmentPlan = null;

    if ($treatmentBasis === 'protocol' && $diagnosisId > 0) {
        $planData =
            consultation_treatment_plan_by_diagnosis(
                $ctx,
                $diagnosisId
            );

        $treatmentPlan =
            $planData['treatment_plan'];

        if (!$treatmentPlan) {
            $errors['diagnosis_ref'] =
                'Selected Diagnosis does not have an active Treatment / Procedure Plan.';
        }
    }

    /*
     * Direct-ID rule:
     * diagnosis_id set  = Diagnosis-based Treatment
     * diagnosis_id NULL = General Treatment
     */
    if ($treatmentBasis === 'general') {
        $diagnosisId = 0;
    }

    $planName =
        trim(
            (string)(
                $data['plan_name'] ??
                ''
            )
        );

    if ($planName === '') {
        $errors['plan_name'] =
            'Treatment Plan Name is required.';
    }

    $planName =
        mb_substr(
            $planName,
            0,
            180
        );

    $planNotes =
        consultation_nullable_text(
            $data['plan_notes'] ?? null,
            5000
        );

    $consultationFee =
        consultation_money(
            $data['consultation_fee'] ??
            '0.00',
            'consultation_fee'
        );

    if ((float)$consultationFee <= 0) {
        $errors['consultation_fee'] =
            'Consultation Fee must be greater than zero.';
    }

    $consultationStatus =
        trim(
            (string)(
                $data['consultation_status'] ??
                'In Progress'
            )
        );

    if (
        !in_array(
            $consultationStatus,
            [
                'In Progress',
                'Completed',
                'Follow-up Required',
            ],
            true
        )
    ) {
        $errors['consultation_status'] =
            'Select a valid Consultation Status.';
    }

    $status =
        (int)(
            $data['status'] ??
            1
        );

    if (!in_array($status, [0,1], true)) {
        $errors['status'] =
            'Select a valid Status.';
    }

    $days =
        consultation_clean_days(
            $ctx,
            $data['treatment_days_json'] ??
            $data['treatment_days'] ??
            []
        );

    $prescriptions =
        consultation_clean_prescriptions(
            $ctx,
            $data['prescription_items_json'] ??
            $data['prescription_items'] ??
            []
        );

    if ($days === []) {
        $errors['treatment_days'] =
            'Add at least one Treatment Plan day.';
    }

    if ($errors !== []) {
        json_error(
            'Consultation validation failed.',
            422,
            $errors
        );
    }

    return [
        'visit_source' => $visitSource,
        'patient_id' => $patientId,
        'appointment_id' =>
            $appointmentId > 0
                ? $appointmentId
                : null,
        'visit_date' => $visitDate,
        'visit_time' => $visitTime,
        'consultant_name' => $consultantName,
        'chief_complaint' => $chiefComplaint,
        'symptoms' => $symptoms,
        'clinical_notes' => $clinicalNotes,
        'diagnosis_id' =>
            $diagnosisId > 0
                ? $diagnosisId
                : null,
        'treatment_basis' => $treatmentBasis,
        'treatment_plan' => $treatmentPlan,
        /* Compatibility alias for existing frontend JavaScript. */
        'protocol' => $treatmentPlan,
        'plan_name' => $planName,
        'plan_notes' => $planNotes,
        'consultation_fee' => $consultationFee,
        'consultation_status' => $consultationStatus,
        'status' => $status,
        'days' => $days,
        'prescriptions' => $prescriptions,
    ];
}

function consultation_assert_appointment_available(
    int $branchId,
    ?int $appointmentId,
    int $excludeConsultationId = 0
): void {
    if (!$appointmentId || $appointmentId < 1) {
        return;
    }

    $sql =
        'SELECT id
         FROM clinic_consultations
         WHERE branch_id=:branch_id
           AND appointment_id=:appointment_id';

    $params = [
        ':branch_id' => $branchId,
        ':appointment_id' => $appointmentId,
    ];

    if ($excludeConsultationId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeConsultationId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error(
            'A Consultation already exists for this Appointment.',
            409,
            [
                'appointment_ref' =>
                    'This Appointment already has a Consultation.'
            ]
        );
    }
}

function consultation_record(array $ctx, int $consultationId): array
{
    $stmt = db()->prepare(
        'SELECT
            c.id,
            c.consultation_no,
            c.patient_id,
            c.appointment_id,
            c.visit_date,
            c.visit_time,
            c.consultant_name,
            c.chief_complaint,
            c.symptoms,
            c.clinical_notes,
            c.diagnosis_id,
            c.consultation_fee,
            c.consultation_status,
            c.status,
            c.created_by,
            c.created_at,
            c.updated_at,

            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.visit_type,

            p.patient_code,
            p.patient_name,
            p.date_of_birth,
            p.gender,
            p.mobile,
            p.blood_group,

            CASE
                WHEN p.date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
            END AS age,

            d.diagnosis_code,
            d.diagnosis_name,

            tp.id AS plan_id,
            tp.plan_name,
            tp.notes AS plan_notes,
            tp.status AS plan_status,

            c.diagnosis_id AS source_protocol_id,
            d.diagnosis_code AS source_protocol_code,
            d.diagnosis_name AS source_protocol_name,

            u.name AS created_by_name

         FROM clinic_consultations c

         INNER JOIN clinic_patients p
                 ON p.id=c.patient_id
                AND p.branch_id=c.branch_id

         LEFT JOIN clinic_appointments a
                ON a.id=c.appointment_id
               AND a.branch_id=c.branch_id

         LEFT JOIN clinic_diagnoses d
                ON d.id=c.diagnosis_id
               AND d.branch_id=c.branch_id

         LEFT JOIN clinic_patient_treatment_plans tp
                ON tp.consultation_id=c.id
               AND tp.branch_id=c.branch_id

         LEFT JOIN users u
                ON u.id=c.created_by

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
        json_error('Consultation was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['patient_id'] = (int)$row['patient_id'];
    $row['appointment_id'] =
        $row['appointment_id'] === null
            ? null
            : (int)$row['appointment_id'];
    $row['diagnosis_id'] =
        $row['diagnosis_id'] === null
            ? null
            : (int)$row['diagnosis_id'];
    $row['source_protocol_id'] =
        $row['source_protocol_id'] === null
            ? null
            : (int)$row['source_protocol_id'];
    $row['plan_id'] =
        $row['plan_id'] === null
            ? null
            : (int)$row['plan_id'];
    $row['status'] = (int)$row['status'];
    $row['consultation_fee'] =
        number_format(
            (float)($row['consultation_fee'] ?? 0),
            2,
            '.',
            ''
        );
    $row['plan_status'] =
        $row['plan_status'] === null
            ? 1
            : (int)$row['plan_status'];
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

    $row['appointment_ref'] =
        $row['appointment_id']
            ? encryptReference(
                'clinic_appointment',
                (int)$row['appointment_id']
            )
            : null;

    $row['diagnosis_ref'] =
        $row['diagnosis_id']
            ? encryptReference(
                'clinic_diagnosis',
                (int)$row['diagnosis_id']
            )
            : null;

    $row['visit_source'] =
        $row['appointment_id']
            ? 'appointment'
            : 'walkin';

    $row['treatment_basis'] =
        $row['diagnosis_id']
            ? 'protocol'
            : 'general';

    $row['visit_time'] =
        !empty($row['visit_time'])
            ? substr((string)$row['visit_time'], 0, 5)
            : null;

    $dayStmt = db()->prepare(
        'SELECT
            d.id,
            d.day_number,
            d.treatment_procedure_id,
            p.procedure_code,
            p.procedure_name,
            d.instructions,
            d.notes,
            d.status
         FROM clinic_patient_treatment_days d
         INNER JOIN clinic_treatment_procedures p
                 ON p.id=d.treatment_procedure_id
                AND p.branch_id=d.branch_id
         WHERE d.branch_id=:branch_id
           AND d.plan_id=:plan_id
         ORDER BY d.day_number,d.id'
    );

    $days = [];

    if ($row['plan_id']) {
        $dayStmt->execute([
            ':branch_id' => (int)$ctx['branch_id'],
            ':plan_id' => (int)$row['plan_id'],
        ]);

        foreach ($dayStmt->fetchAll(PDO::FETCH_ASSOC) as $day) {
            $days[] = [
                'ref' =>
                    encryptReference(
                        'clinic_treatment_day',
                        (int)$day['id']
                    ),
                'day_number' => (int)$day['day_number'],
                'treatment_procedure_ref' =>
                    encryptReference(
                        'clinic_treatment_procedure',
                        (int)$day['treatment_procedure_id']
                    ),
                'procedure_code' => (string)$day['procedure_code'],
                'procedure_name' => (string)$day['procedure_name'],
                'instructions' => $day['instructions'],
                'notes' => $day['notes'],
                'status' => (int)$day['status'],
            ];
        }
    }

    $row['duration_days'] = count($days);
    $row['days'] = $days;

    $prescriptionStmt = db()->prepare(
        'SELECT
            cp.id,cp.medicine_id,cp.quantity,cp.unit_price,cp.amount,
            cp.dosage,cp.frequency,cp.timing,cp.anupana,cp.duration,cp.instructions,cp.status,
            m.medicine_code,m.medicine_name,m.medicine_type,m.unit,m.pack_size
         FROM clinic_consultation_prescriptions cp
         INNER JOIN clinic_medicines m
                 ON m.id=cp.medicine_id
                AND m.branch_id=cp.branch_id
         WHERE cp.branch_id=:branch_id
           AND cp.consultation_id=:consultation_id
           AND cp.status=1
         ORDER BY cp.id'
    );
    $prescriptionStmt->execute([
        ':branch_id'=>(int)$ctx['branch_id'],
        ':consultation_id'=>$consultationId,
    ]);
    $prescriptions=[];
    foreach ($prescriptionStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $prescriptions[]=[
            'ref'=>encryptReference('clinic_prescription_item',(int)$item['id']),
            'medicine_ref'=>encryptReference('clinic_medicine',(int)$item['medicine_id']),
            'medicine_code'=>(string)$item['medicine_code'],
            'medicine_name'=>(string)$item['medicine_name'],
            'medicine_type'=>(string)$item['medicine_type'],
            'unit'=>$item['unit'],'pack_size'=>$item['pack_size'],
            'quantity'=>number_format((float)$item['quantity'],3,'.',''),
            'unit_price'=>number_format((float)$item['unit_price'],2,'.',''),
            'amount'=>number_format((float)$item['amount'],2,'.',''),
            'dosage'=>$item['dosage'],'frequency'=>$item['frequency'],'timing'=>$item['timing'],
            'anupana'=>$item['anupana'],'duration'=>$item['duration'],'instructions'=>$item['instructions'],
            'status'=>(int)$item['status'],
        ];
    }
    $row['prescriptions']=$prescriptions;
    $row['prescription_total']=number_format(array_sum(array_map(static function($x){return (float)$x['amount'];},$prescriptions)),2,'.','');

    return $row;
}

function consultation_sync_days(
    int $branchId,
    int $planId,
    int $userId,
    array $days
): void {
    /*
     * Keep Treatment Day IDs stable because Treatment Progress links to
     * clinic_patient_treatment_days.id.
     *
     * Existing Day N is updated in place.
     * New Day N is inserted.
     * Removed days with no progress are deleted.
     * Removed days with progress are retained as inactive for history.
     */
    $existingStmt = db()->prepare(
        'SELECT
            td.id,
            td.day_number,
            EXISTS(
                SELECT 1
                FROM clinic_patient_treatment_progress pr
                WHERE pr.branch_id=td.branch_id
                  AND pr.treatment_day_id=td.id
            ) AS has_progress
         FROM clinic_patient_treatment_days td
         WHERE td.branch_id=:branch_id
           AND td.plan_id=:plan_id
         ORDER BY td.day_number,td.id'
    );

    $existingStmt->execute([
        ':branch_id' => $branchId,
        ':plan_id' => $planId,
    ]);

    $existing = [];

    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int)$row['day_number']] = [
            'id' => (int)$row['id'],
            'has_progress' => (int)$row['has_progress'] === 1,
        ];
    }

    $update = db()->prepare(
        'UPDATE clinic_patient_treatment_days
         SET
            treatment_procedure_id=:treatment_procedure_id,
            instructions=:instructions,
            notes=:notes,
            status=:status,
            updated_at=NOW()
         WHERE id=:id
           AND branch_id=:branch_id'
    );

    $insert = db()->prepare(
        'INSERT INTO clinic_patient_treatment_days
        (
            branch_id,
            plan_id,
            day_number,
            treatment_procedure_id,
            instructions,
            notes,
            status,
            created_by,
            created_at,
            updated_at
        )
        VALUES
        (
            :branch_id,
            :plan_id,
            :day_number,
            :treatment_procedure_id,
            :instructions,
            :notes,
            :status,
            :created_by,
            NOW(),
            NOW()
        )'
    );

    $submittedDayNumbers = [];

    foreach ($days as $day) {
        $dayNumber = (int)$day['day_number'];
        $submittedDayNumbers[$dayNumber] = true;

        if (isset($existing[$dayNumber])) {
            $update->execute([
                ':treatment_procedure_id' =>
                    (int)$day['treatment_procedure_id'],
                ':instructions' => $day['instructions'],
                ':notes' => $day['notes'],
                ':status' => (int)$day['status'],
                ':id' => (int)$existing[$dayNumber]['id'],
                ':branch_id' => $branchId,
            ]);

            continue;
        }

        $insert->execute([
            ':branch_id' => $branchId,
            ':plan_id' => $planId,
            ':day_number' => $dayNumber,
            ':treatment_procedure_id' =>
                (int)$day['treatment_procedure_id'],
            ':instructions' => $day['instructions'],
            ':notes' => $day['notes'],
            ':status' => (int)$day['status'],
            ':created_by' => $userId,
        ]);
    }

    foreach ($existing as $dayNumber => $row) {
        if (isset($submittedDayNumbers[$dayNumber])) {
            continue;
        }

        if ($row['has_progress']) {
            db()->prepare(
                'UPDATE clinic_patient_treatment_days
                 SET status=0,
                     updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            )->execute([
                ':id' => (int)$row['id'],
                ':branch_id' => $branchId,
            ]);

            continue;
        }

        db()->prepare(
            'DELETE FROM clinic_patient_treatment_days
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':id' => (int)$row['id'],
            ':branch_id' => $branchId,
        ]);
    }
}

function consultation_sync_prescriptions(
    int $branchId,
    int $consultationId,
    int $userId,
    array $items
): void {
    /*
     * Keep Prescription IDs stable because Patient Billing links to
     * clinic_consultation_prescriptions.id.
     *
     * Medicine is unique per Consultation in current validation,
     * so medicine_id is the stable matching key.
     */
    $existingStmt = db()->prepare(
        'SELECT
            id,
            medicine_id,
            quantity,
            unit_price,
            amount,
            dosage,
            frequency,
            timing,
            anupana,
            duration,
            instructions,
            status
         FROM clinic_consultation_prescriptions
         WHERE branch_id=:branch_id
           AND consultation_id=:consultation_id'
    );

    $existingStmt->execute([
        ':branch_id' =>
            $branchId,

        ':consultation_id' =>
            $consultationId,
    ]);

    $existing = [];

    foreach (
        $existingStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $existing[
            (int)$row['medicine_id']
        ] = $row;
    }

    $isBilled =
        static function (
            int $prescriptionId
        ) use (
            $branchId
        ): bool {
            try {
                $stmt = db()->prepare(
                    "SELECT bi.id
                     FROM clinic_bill_items bi
                     INNER JOIN clinic_bills b
                             ON b.id=bi.bill_id
                            AND b.branch_id=bi.branch_id
                     WHERE bi.branch_id=:branch_id
                       AND bi.item_type='MEDICINE'
                       AND bi.reference_id=:reference_id
                       AND bi.status=1
                       AND b.status=1
                     LIMIT 1"
                );

                $stmt->execute([
                    ':branch_id' =>
                        $branchId,

                    ':reference_id' =>
                        $prescriptionId,
                ]);

                return
                    (bool)$stmt->fetchColumn();
            } catch (Throwable $e) {
                /*
                 * Billing module may not yet be installed.
                 */
                return false;
            }
        };

    $update =
        db()->prepare(
            'UPDATE clinic_consultation_prescriptions
             SET
                quantity=:quantity,
                unit_price=:unit_price,
                amount=:amount,
                dosage=:dosage,
                frequency=:frequency,
                timing=:timing,
                anupana=:anupana,
                duration=:duration,
                instructions=:instructions,
                status=1,
                updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        );

    $insert =
        db()->prepare(
            'INSERT INTO clinic_consultation_prescriptions
            (
                branch_id,
                consultation_id,
                medicine_id,
                quantity,
                unit_price,
                amount,
                dosage,
                frequency,
                timing,
                anupana,
                duration,
                instructions,
                status,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                :branch_id,
                :consultation_id,
                :medicine_id,
                :quantity,
                :unit_price,
                :amount,
                :dosage,
                :frequency,
                :timing,
                :anupana,
                :duration,
                :instructions,
                1,
                :created_by,
                NOW(),
                NOW()
            )'
        );

    $submitted = [];

    foreach ($items as $item) {
        $medicineId =
            (int)$item['medicine_id'];

        $submitted[$medicineId] =
            true;

        if (
            isset(
                $existing[
                    $medicineId
                ]
            )
        ) {
            $old =
                $existing[
                    $medicineId
                ];

            $billed =
                $isBilled(
                    (int)$old['id']
                );

            if ($billed) {
                $same =
                    abs(
                        (float)$old['quantity'] -
                        (float)$item['quantity']
                    ) < 0.0001
                    &&
                    abs(
                        (float)$old['unit_price'] -
                        (float)$item['unit_price']
                    ) < 0.0001
                    &&
                    abs(
                        (float)$old['amount'] -
                        (float)$item['amount']
                    ) < 0.0001
                    &&
                    trim(
                        (string)($old['dosage'] ?? '')
                    ) ===
                    trim(
                        (string)($item['dosage'] ?? '')
                    )
                    &&
                    trim(
                        (string)($old['frequency'] ?? '')
                    ) ===
                    trim(
                        (string)($item['frequency'] ?? '')
                    )
                    &&
                    trim(
                        (string)($old['timing'] ?? '')
                    ) ===
                    trim(
                        (string)($item['timing'] ?? '')
                    )
                    &&
                    trim(
                        (string)($old['anupana'] ?? '')
                    ) ===
                    trim(
                        (string)($item['anupana'] ?? '')
                    )
                    &&
                    trim(
                        (string)($old['duration'] ?? '')
                    ) ===
                    trim(
                        (string)($item['duration'] ?? '')
                    )
                    &&
                    trim(
                        (string)($old['instructions'] ?? '')
                    ) ===
                    trim(
                        (string)($item['instructions'] ?? '')
                    );

                if (!$same) {
                    json_error(
                        'Prescription cannot be changed after it has been billed.',
                        409,
                        [
                            'prescription_items' =>
                                'A billed Medicine row is locked. Deactivate the related Bill first if it must be changed.'
                        ]
                    );
                }

                continue;
            }

            $update->execute([
                ':quantity' =>
                    $item['quantity'],

                ':unit_price' =>
                    $item['unit_price'],

                ':amount' =>
                    $item['amount'],

                ':dosage' =>
                    $item['dosage'],

                ':frequency' =>
                    $item['frequency'],

                ':timing' =>
                    $item['timing'],

                ':anupana' =>
                    $item['anupana'],

                ':duration' =>
                    $item['duration'],

                ':instructions' =>
                    $item['instructions'],

                ':id' =>
                    (int)$old['id'],

                ':branch_id' =>
                    $branchId,
            ]);

            continue;
        }

        $insert->execute([
            ':branch_id' =>
                $branchId,

            ':consultation_id' =>
                $consultationId,

            ':medicine_id' =>
                $medicineId,

            ':quantity' =>
                $item['quantity'],

            ':unit_price' =>
                $item['unit_price'],

            ':amount' =>
                $item['amount'],

            ':dosage' =>
                $item['dosage'],

            ':frequency' =>
                $item['frequency'],

            ':timing' =>
                $item['timing'],

            ':anupana' =>
                $item['anupana'],

            ':duration' =>
                $item['duration'],

            ':instructions' =>
                $item['instructions'],

            ':created_by' =>
                $userId,
        ]);
    }

    foreach (
        $existing as $medicineId => $row
    ) {
        if (
            isset(
                $submitted[
                    $medicineId
                ]
            )
        ) {
            continue;
        }

        if (
            $isBilled(
                (int)$row['id']
            )
        ) {
            json_error(
                'Prescription cannot be removed after it has been billed.',
                409,
                [
                    'prescription_items' =>
                        'A billed Medicine row is locked. Deactivate the related Bill first if it must be removed.'
                ]
            );
        }

        db()->prepare(
            'DELETE FROM clinic_consultation_prescriptions
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':id' =>
                (int)$row['id'],

            ':branch_id' =>
                $branchId,
        ]);
    }
}

function consultation_update_appointment_status(
    int $branchId,
    ?int $appointmentId,
    string $consultationStatus
): void {
    if (!$appointmentId || $appointmentId < 1) {
        return;
    }

    $appointmentStatus =
        in_array(
            $consultationStatus,
            [
                'Completed',
                'Follow-up Required',
            ],
            true
        )
            ? 'Completed'
            : 'In Consultation';

    $stmt = db()->prepare(
        'UPDATE clinic_appointments
         SET appointment_status=:appointment_status,
             updated_at=NOW()
         WHERE id=:id
           AND branch_id=:branch_id'
    );

    $stmt->execute([
        ':appointment_status' => $appointmentStatus,
        ':id' => $appointmentId,
        ':branch_id' => $branchId,
    ]);
}

$method = request_method();
consultation_require_schema();

/* Form options */
if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('consultation-list.php', ACTION_VIEW);
    $ctx = consultation_context($access['user']);

    json_success('Consultation form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_consultation_no' =>
            consultation_generate_no(
                (int)$ctx['branch_id']
            ),
        'patients' =>
            consultation_patient_options($ctx),
        'appointments' =>
            consultation_appointment_options($ctx),
        'diagnoses' =>
            consultation_diagnosis_options($ctx),
        'treatment_procedures' =>
            consultation_treatment_procedure_options($ctx),
        'medicines' =>
            consultation_medicine_options($ctx),
        'branch' => $ctx,
    ]);
}

/* Diagnosis Treatment Plan dependency.
 * Keep ?protocol=1 for backward compatibility with the current form.
 */
if (
    $method === 'GET' &&
    (
        isset($_GET['treatment_plan']) ||
        isset($_GET['protocol'])
    )
) {
    $access = require_permission('consultation-list.php', ACTION_VIEW);
    $ctx = consultation_context($access['user']);

    $diagnosisId =
        consultation_diagnosis_ref_to_id(
            $_GET['diagnosis_ref'] ?? ''
        );

    if ($diagnosisId < 1) {
        json_success('Treatment Plan loaded.', [
            'treatment_plan' => null,
            'protocol' => null,
            'days' => [],
        ]);
    }

    consultation_diagnosis(
        $ctx,
        $diagnosisId,
        true
    );

    json_success(
        'Treatment Plan loaded.',
        consultation_treatment_plan_by_diagnosis(
            $ctx,
            $diagnosisId
        )
    );
}

/* Single Consultation */
if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('consultation-list.php', ACTION_VIEW);
    $ctx = consultation_context($access['user']);

    $id =
        consultation_ref_to_id(
            $_GET['ref'] ?? ''
        );

    $record =
        consultation_record(
            $ctx,
            $id
        );

    json_success('Consultation loaded.', [
        'record' => $record,
        'allowed_actions' => $access['actions'],
        'patients' =>
            consultation_patient_options($ctx),
        'appointments' =>
            consultation_appointment_options(
                $ctx,
                (int)($record['appointment_id'] ?? 0)
            ),
        'diagnoses' =>
            consultation_diagnosis_options(
                $ctx,
                (int)($record['diagnosis_id'] ?? 0)
            ),
        'treatment_procedures' =>
            consultation_treatment_procedure_options($ctx),
        'medicines' =>
            consultation_medicine_options($ctx),
        'branch' => $ctx,
    ]);
}

/* Consultation DataTable */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('consultation-list.php', ACTION_VIEW);
    $ctx = consultation_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length =
        $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

    $search =
        trim(
            (string)(
                $_GET['search']['value'] ??
                ''
            )
        );

    $visitSource =
        strtolower(
            trim(
                (string)(
                    $_GET['visit_source'] ??
                    ''
                )
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

    $statusFilter =
        trim(
            (string)(
                $_GET['consultation_status'] ??
                ''
            )
        );

    $where = [
        'c.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' => $branchId
    ];

    if ($search !== '') {
        $like = '%' . $search . '%';

        $where[] =
            '(c.consultation_no LIKE :s1
              OR p.patient_code LIKE :s2
              OR p.patient_name LIKE :s3
              OR p.mobile LIKE :s4
              OR d.diagnosis_name LIKE :s5
              OR tp.plan_name LIKE :s6
              OR c.consultant_name LIKE :s7)';

        $params += [
            ':s1' => $like,
            ':s2' => $like,
            ':s3' => $like,
            ':s4' => $like,
            ':s5' => $like,
            ':s6' => $like,
            ':s7' => $like,
        ];
    }

    if ($visitSource === 'appointment') {
        $where[] = 'c.appointment_id IS NOT NULL';
    } elseif ($visitSource === 'walkin') {
        $where[] = 'c.appointment_id IS NULL';
    }

    if ($treatmentBasis === 'protocol') {
        $where[] = 'c.diagnosis_id IS NOT NULL';
    } elseif ($treatmentBasis === 'general') {
        $where[] = 'c.diagnosis_id IS NULL';
    }

    if (
        in_array(
            $statusFilter,
            [
                'In Progress',
                'Completed',
                'Follow-up Required',
            ],
            true
        )
    ) {
        $where[] = 'c.consultation_status=:consultation_status';
        $params[':consultation_status'] = $statusFilter;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM clinic_consultations
         WHERE branch_id=:branch_id'
    );

    $totalStmt->execute([
        ':branch_id' => $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $from =
        ' FROM clinic_consultations c
          INNER JOIN clinic_patients p
                  ON p.id=c.patient_id
                 AND p.branch_id=c.branch_id
          LEFT JOIN clinic_appointments a
                 ON a.id=c.appointment_id
                AND a.branch_id=c.branch_id
          LEFT JOIN clinic_diagnoses d
                 ON d.id=c.diagnosis_id
                AND d.branch_id=c.branch_id
          LEFT JOIN clinic_patient_treatment_plans tp
                 ON tp.consultation_id=c.id
                AND tp.branch_id=c.branch_id ';

    $countStmt = db()->prepare(
        'SELECT COUNT(*) ' .
        $from .
        ' WHERE ' .
        implode(' AND ', $where)
    );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $orderColumns = [
        'c.consultation_no',
        'c.visit_date',
        'p.patient_name',
        'd.diagnosis_name',
        'c.consultation_status',
        'c.created_at',
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
        'c.visit_date';

    $sql =
        'SELECT
            c.id,
            c.consultation_no,
            c.visit_date,
            c.visit_time,
            c.consultant_name,
            c.appointment_id,
            c.consultation_status,
            c.status,
            c.created_at,

            a.appointment_no,

            p.patient_code,
            p.patient_name,
            p.mobile,

            d.diagnosis_code,
            d.diagnosis_name,

            c.diagnosis_id AS source_protocol_id,
            tp.plan_name,

            d.diagnosis_code AS protocol_code,
            d.diagnosis_name AS protocol_name,

            (
                SELECT COUNT(*)
                FROM clinic_patient_treatment_days td
                WHERE td.branch_id=c.branch_id
                  AND td.plan_id=tp.id
            ) AS duration_days

         ' .
         $from .
         ' WHERE ' .
         implode(' AND ', $where) .
         ' ORDER BY ' .
         $orderBy .
         ' ' .
         $orderDir .
         ',c.id DESC
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            $key === ':branch_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref =
            encryptReference(
                'clinic_consultation',
                (int)$row['id']
            );

        $rows[] = [
            'ref' => $ref,
            'consultation_no' => (string)$row['consultation_no'],
            'visit_date' => $row['visit_date'],
            'visit_time' =>
                !empty($row['visit_time'])
                    ? substr((string)$row['visit_time'], 0, 5)
                    : null,
            'consultant_name' => $row['consultant_name'],
            'visit_source' =>
                $row['appointment_id'] === null
                    ? 'Walk-in'
                    : 'Appointment',
            'appointment_no' => $row['appointment_no'],
            'patient_code' => (string)$row['patient_code'],
            'patient_name' => (string)$row['patient_name'],
            'mobile' => $row['mobile'],
            'diagnosis_code' => $row['diagnosis_code'],
            'diagnosis_name' => $row['diagnosis_name'],
            'treatment_basis' =>
                $row['source_protocol_id'] === null
                    ? 'General'
                    : 'Protocol',
            'protocol_code' => $row['protocol_code'],
            'protocol_name' => $row['protocol_name'],
            'plan_name' => $row['plan_name'],
            'duration_days' => (int)$row['duration_days'],
            'consultation_status' => (string)$row['consultation_status'],
            'status' => (int)$row['status'],
            'status_label' =>
                (int)$row['status'] === 1
                    ? 'Active'
                    : 'Inactive',
            'created_at' => $row['created_at'],
            'view_url' =>
                'consultation-form.php?ref=' .
                rawurlencode($ref) .
                '&view=1',
            'edit_url' =>
                'consultation-form.php?ref=' .
                rawurlencode($ref),
        ];
    }

    json_success('Consultation list loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $access['actions'],
    ]);
}

/* Save / Deactivate */
if ($method === 'POST') {
    $data = request_data();

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
                'consultation-list.php',
                4
            );

        $ctx =
            consultation_context(
                $access['user']
            );

        $id =
            consultation_ref_to_id(
                $data['ref'] ?? ''
            );

        $stmt = db()->prepare(
            'SELECT *
             FROM clinic_consultations
             WHERE id=:id
               AND branch_id=:branch_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':branch_id' => (int)$ctx['branch_id'],
        ]);

        $old =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$old) {
            json_error(
                'Consultation was not found.',
                404
            );
        }

        db()->prepare(
            'UPDATE clinic_consultations
             SET status=0,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':id' => $id,
            ':branch_id' => (int)$ctx['branch_id'],
        ]);

        audit_log(
            (int)$access['user']['id'],
            4,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => (int)$ctx['branch_id'],
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
            ]
        );

        json_success(
            'Consultation deactivated successfully.'
        );
    }

    if ($action !== 'save') {
        json_error(
            'Unsupported Consultation action.',
            404
        );
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access =
        require_permission(
            'consultation-list.php',
            $isUpdate
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $ctx =
        consultation_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $clean =
        consultation_validate(
            $data,
            $ctx
        );

    $consultationId =
        $isUpdate
            ? consultation_ref_to_id(
                $data['ref'] ?? ''
            )
            : 0;

    consultation_assert_appointment_available(
        $branchId,
        $clean['appointment_id'],
        $consultationId
    );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $old = null;

        if ($isUpdate) {
            $oldStmt = $pdo->prepare(
                'SELECT *
                 FROM clinic_consultations
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1'
            );

            $oldStmt->execute([
                ':id' => $consultationId,
                ':branch_id' => $branchId,
            ]);

            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new RuntimeException(
                    'Consultation was not found.'
                );
            }

            $stmt = $pdo->prepare(
                'UPDATE clinic_consultations
                 SET
                    patient_id=:patient_id,
                    appointment_id=:appointment_id,
                    visit_date=:visit_date,
                    visit_time=:visit_time,
                    consultant_name=:consultant_name,
                    chief_complaint=:chief_complaint,
                    symptoms=:symptoms,
                    clinical_notes=:clinical_notes,
                    diagnosis_id=:diagnosis_id,
                    consultation_fee=:consultation_fee,
                    consultation_status=:consultation_status,
                    status=:status,
                    updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

            $stmt->execute([
                ':patient_id' => $clean['patient_id'],
                ':appointment_id' => $clean['appointment_id'],
                ':visit_date' => $clean['visit_date'],
                ':visit_time' => $clean['visit_time'],
                ':consultant_name' => $clean['consultant_name'],
                ':chief_complaint' => $clean['chief_complaint'],
                ':symptoms' => $clean['symptoms'],
                ':clinical_notes' => $clean['clinical_notes'],
                ':diagnosis_id' => $clean['diagnosis_id'],
                ':consultation_fee' => $clean['consultation_fee'],
                ':consultation_status' => $clean['consultation_status'],
                ':status' => $clean['status'],
                ':id' => $consultationId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $consultationNo =
                consultation_generate_no(
                    $branchId
                );

            $stmt = $pdo->prepare(
                'INSERT INTO clinic_consultations
                (
                    branch_id,
                    consultation_no,
                    patient_id,
                    appointment_id,
                    visit_date,
                    visit_time,
                    consultant_name,
                    chief_complaint,
                    symptoms,
                    clinical_notes,
                    diagnosis_id,
                    consultation_fee,
                    consultation_status,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :branch_id,
                    :consultation_no,
                    :patient_id,
                    :appointment_id,
                    :visit_date,
                    :visit_time,
                    :consultant_name,
                    :chief_complaint,
                    :symptoms,
                    :clinical_notes,
                    :diagnosis_id,
                    :consultation_fee,
                    :consultation_status,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                )'
            );

            $stmt->execute([
                ':branch_id' => $branchId,
                ':consultation_no' => $consultationNo,
                ':patient_id' => $clean['patient_id'],
                ':appointment_id' => $clean['appointment_id'],
                ':visit_date' => $clean['visit_date'],
                ':visit_time' => $clean['visit_time'],
                ':consultant_name' => $clean['consultant_name'],
                ':chief_complaint' => $clean['chief_complaint'],
                ':symptoms' => $clean['symptoms'],
                ':clinical_notes' => $clean['clinical_notes'],
                ':diagnosis_id' => $clean['diagnosis_id'],
                ':consultation_fee' => $clean['consultation_fee'],
                ':consultation_status' => $clean['consultation_status'],
                ':status' => $clean['status'],
                ':created_by' => $userId,
            ]);

            $consultationId =
                (int)$pdo->lastInsertId();
        }

        $planStmt = $pdo->prepare(
            'SELECT id
             FROM clinic_patient_treatment_plans
             WHERE branch_id=:branch_id
               AND consultation_id=:consultation_id
             LIMIT 1'
        );

        $planStmt->execute([
            ':branch_id' => $branchId,
            ':consultation_id' => $consultationId,
        ]);

        $planId =
            (int)(
                $planStmt->fetchColumn() ?:
                0
            );

        if ($planId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE clinic_patient_treatment_plans
                 SET
                    plan_name=:plan_name,
                    notes=:notes,
                    status=:status,
                    updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

            $stmt->execute([
                ':plan_name' => $clean['plan_name'],
                ':notes' => $clean['plan_notes'],
                ':status' => $clean['status'],
                ':id' => $planId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO clinic_patient_treatment_plans
                (
                    branch_id,
                    consultation_id,
                    plan_name,
                    notes,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :branch_id,
                    :consultation_id,
                    :plan_name,
                    :notes,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                )'
            );

            $stmt->execute([
                ':branch_id' => $branchId,
                ':consultation_id' => $consultationId,
                ':plan_name' => $clean['plan_name'],
                ':notes' => $clean['plan_notes'],
                ':status' => $clean['status'],
                ':created_by' => $userId,
            ]);

            $planId =
                (int)$pdo->lastInsertId();
        }

        consultation_sync_days(
            $branchId,
            $planId,
            $userId,
            $clean['days']
        );

        consultation_sync_prescriptions(
            $branchId,
            $consultationId,
            $userId,
            $clean['prescriptions']
        );

        consultation_update_appointment_status(
            $branchId,
            $clean['appointment_id'],
            $clean['consultation_status']
        );

        audit_log(
            $userId,
            $isUpdate ? 3 : 2,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $consultationId,
                'old_data' => $old,
                'new_data' => [
                    'patient_id' => $clean['patient_id'],
                    'appointment_id' => $clean['appointment_id'],
                    'diagnosis_id' => $clean['diagnosis_id'],
                    'treatment_basis' => $clean['treatment_basis'],
                    'days' => $clean['days'],
                    'prescriptions' => $clean['prescriptions'],
                ],
            ]
        );

        $pdo->commit();

        json_success(
            $isUpdate
                ? 'Consultation updated successfully.'
                : 'Consultation saved successfully.',
            [
                'ref' =>
                    encryptReference(
                        'clinic_consultation',
                        $consultationId
                    ),
            ]
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_error(
            'Unable to save Consultation.',
            500,
            [
                'database' =>
                    $e->getMessage(),
            ]
        );
    }
}

json_error('Method not allowed.', 405);
