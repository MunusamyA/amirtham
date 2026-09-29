<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Clinic Appointment API
 *
 * Table:
 * - clinic_appointments
 *
 * Uses:
 * - clinic_patients
 *
 * Actions:
 * - GET  ?options=1
 * - GET  ?patients=1
 * - GET  ?ref=<encrypted>
 * - GET  ?datatable=1
 * - POST action=save
 * - POST action=delete
 */

function appointment_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Clinic Appointment is available only for tenant users.', 403);
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

function appointment_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $required = [
        'id','branch_id','appointment_no','patient_id',
        'appointment_date','appointment_time','visit_type',
        'consultant_name','reason_complaint','notes',
        'appointment_status','status',
        'created_by','created_at','updated_at'
    ];

    try {
        $stmt = db()->query('SHOW COLUMNS FROM `clinic_appointments`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        json_error(
            'Clinic Appointment database table is missing: clinic_appointments.',
            500,
            [
                'schema' =>
                    'Run sql/clinic-appointment.sql or appointment-schema-upgrade.php first.'
            ]
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
            'Clinic Appointment database structure is incomplete.',
            500,
            [
                'schema' =>
                    'Missing clinic_appointments columns: ' .
                    implode(', ', $missing) .
                    '. Run appointment-schema-upgrade.php once.'
            ]
        );
    }

    $checked = true;
}

function appointment_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Appointment reference is required.',
            422,
            ['ref' => 'Appointment reference is required.']
        );
    }

    try {
        return decryptReference(trim($value), 'clinic_appointment');
    } catch (Throwable $e) {
        json_error(
            'Invalid Appointment reference.',
            422,
            ['ref' => 'Invalid Appointment reference.']
        );
    }

    return 0;
}

function appointment_patient_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Patient is required.',
            422,
            ['patient_ref' => 'Patient is required.']
        );
    }

    try {
        return decryptReference(trim($value), 'clinic_patient');
    } catch (Throwable $e) {
        json_error(
            'Invalid Patient reference.',
            422,
            ['patient_ref' => 'Invalid Patient reference.']
        );
    }

    return 0;
}

function appointment_nullable_text($value, int $maxLength): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    return mb_substr($value, 0, $maxLength);
}

function appointment_generate_no(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT appointment_no
         FROM clinic_appointments
         WHERE branch_id=:branch_id
           AND appointment_no REGEXP '^APT[0-9]+$'
         ORDER BY CAST(SUBSTRING(appointment_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^APT([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'APT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function appointment_valid_date($value): ?string
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

function appointment_valid_time($value): ?string
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

function appointment_patient_row(array $ctx, int $patientId): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            patient_code,
            patient_name,
            date_of_birth,
            gender,
            mobile,
            alternate_mobile,
            blood_group,
            status,
            CASE
                WHEN date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE())
            END AS age
         FROM clinic_patients
         WHERE id=:patient_id
           AND branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':patient_id' => $patientId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Selected Patient was not found.',
            422,
            ['patient_ref' => 'Selected Patient was not found.']
        );
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    $row['age'] = $row['age'] === null ? null : (int)$row['age'];
    $row['ref'] = encryptReference('clinic_patient', (int)$row['id']);

    return $row;
}

function appointment_patient_options(array $ctx): array
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

function appointment_validate(array $data, array $ctx): array
{
    $errors = [];

    $patientId = appointment_patient_ref_to_id($data['patient_ref'] ?? '');
    $patient = appointment_patient_row($ctx, $patientId);

    if ((int)$patient['status'] !== 1) {
        $errors['patient_ref'] =
            'Selected Patient is inactive. Select an active Patient.';
    }

    $appointmentDateRaw =
        trim((string)($data['appointment_date'] ?? ''));

    $appointmentDate =
        appointment_valid_date($appointmentDateRaw);

    if ($appointmentDateRaw === '') {
        $errors['appointment_date'] =
            'Appointment Date is required.';
    } elseif ($appointmentDate === null) {
        $errors['appointment_date'] =
            'Enter a valid Appointment Date.';
    }

    $appointmentTimeRaw =
        trim((string)($data['appointment_time'] ?? ''));

    $appointmentTime =
        appointment_valid_time($appointmentTimeRaw);

    if ($appointmentTimeRaw === '') {
        $errors['appointment_time'] =
            'Appointment Time is required.';
    } elseif ($appointmentTime === null) {
        $errors['appointment_time'] =
            'Enter a valid Appointment Time.';
    }

    $visitType =
        trim((string)($data['visit_type'] ?? ''));

    $visitTypes = [
        'New Patient',
        'Follow-up',
        'Treatment Visit',
        'Review',
    ];

    if (!in_array($visitType, $visitTypes, true)) {
        $errors['visit_type'] =
            'Select a valid Visit Type.';
    }

    $consultantName =
        appointment_nullable_text(
            $data['consultant_name'] ?? null,
            150
        );

    $reasonComplaint =
        appointment_nullable_text(
            $data['reason_complaint'] ?? null,
            5000
        );

    $notes =
        appointment_nullable_text(
            $data['notes'] ?? null,
            5000
        );

    $appointmentStatus =
        trim((string)($data['appointment_status'] ?? ''));

    $appointmentStatuses = [
        'Scheduled',
        'Confirmed',
        'Checked In',
        'In Consultation',
        'Completed',
        'Cancelled',
        'No Show',
    ];

    if (!in_array($appointmentStatus, $appointmentStatuses, true)) {
        $errors['appointment_status'] =
            'Select a valid Appointment Status.';
    }

    $status = (int)($data['status'] ?? 1);

    if (!in_array($status, [0,1], true)) {
        $errors['status'] =
            'Select a valid Status.';
    }

    if ($errors !== []) {
        json_error(
            'Appointment validation failed.',
            422,
            $errors
        );
    }

    return [
        'patient_id' => $patientId,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'visit_type' => $visitType,
        'consultant_name' => $consultantName,
        'reason_complaint' => $reasonComplaint,
        'notes' => $notes,
        'appointment_status' => $appointmentStatus,
        'status' => $status,
    ];
}

function appointment_record(array $ctx, int $appointmentId): array
{
    $stmt = db()->prepare(
        'SELECT
            a.id,
            a.appointment_no,
            a.patient_id,
            a.appointment_date,
            a.appointment_time,
            a.visit_type,
            a.consultant_name,
            a.reason_complaint,
            a.notes,
            a.appointment_status,
            a.status,
            a.created_by,
            a.created_at,
            a.updated_at,
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
            u.name AS created_by_name
         FROM clinic_appointments a
         INNER JOIN clinic_patients p
                 ON p.id=a.patient_id
                AND p.branch_id=a.branch_id
         LEFT JOIN users u
                ON u.id=a.created_by
         WHERE a.id=:appointment_id
           AND a.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Appointment was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['patient_id'] = (int)$row['patient_id'];
    $row['status'] = (int)$row['status'];
    $row['age'] = $row['age'] === null ? null : (int)$row['age'];

    $row['ref'] =
        encryptReference(
            'clinic_appointment',
            (int)$row['id']
        );

    $row['patient_ref'] =
        encryptReference(
            'clinic_patient',
            (int)$row['patient_id']
        );

    if (!empty($row['appointment_time'])) {
        $row['appointment_time'] =
            substr((string)$row['appointment_time'], 0, 5);
    }

    return $row;
}

$method = request_method();
appointment_require_schema();

/* Options */
if ($method === 'GET' && isset($_GET['options'])) {
    $access =
        require_permission(
            'appointment-list.php',
            ACTION_VIEW
        );

    $ctx =
        appointment_context(
            $access['user']
        );

    json_success(
        'Appointment form options loaded.',
        [
            'allowed_actions' =>
                $access['actions'],

            'next_appointment_no' =>
                appointment_generate_no(
                    db(),
                    (int)$ctx['branch_id']
                ),

            'patients' =>
                appointment_patient_options(
                    $ctx
                ),

            'visit_types' => [
                'New Patient',
                'Follow-up',
                'Treatment Visit',
                'Review',
            ],

            'appointment_statuses' => [
                'Scheduled',
                'Confirmed',
                'Checked In',
                'In Consultation',
                'Completed',
                'Cancelled',
                'No Show',
            ],

            'branch' => $ctx,
        ]
    );
}

/* Reload Patient dropdown */
if ($method === 'GET' && isset($_GET['patients'])) {
    $access =
        require_permission(
            'appointment-list.php',
            ACTION_VIEW
        );

    $ctx =
        appointment_context(
            $access['user']
        );

    json_success(
        'Patients loaded.',
        [
            'patients' =>
                appointment_patient_options(
                    $ctx
                ),
        ]
    );
}


/* Appointment Calendar */
if ($method === 'GET' && isset($_GET['calendar'])) {
    $access = require_permission('appointment-list.php', ACTION_VIEW);
    $ctx = appointment_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $startRaw = substr(trim((string)($_GET['start'] ?? '')), 0, 10);
    $endRaw = substr(trim((string)($_GET['end'] ?? '')), 0, 10);

    $startDate = appointment_valid_date($startRaw);
    $endDate = appointment_valid_date($endRaw);

    if ($startDate === null || $endDate === null) {
        json_error('Valid calendar start and end dates are required.', 422);
    }

    $where = [
        'a.branch_id=:branch_id',
        'a.appointment_date>=:start_date',
        'a.appointment_date<:end_date',
    ];

    $params = [
        ':branch_id' => $branchId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ];

    $appointmentStatus = trim((string)($_GET['appointment_status'] ?? ''));

    if (in_array($appointmentStatus, [
        'Scheduled',
        'Confirmed',
        'Checked In',
        'In Consultation',
        'Completed',
        'Cancelled',
        'No Show',
    ], true)) {
        $where[] = 'a.appointment_status=:appointment_status';
        $params[':appointment_status'] = $appointmentStatus;
    }

    $visitType = trim((string)($_GET['visit_type'] ?? ''));

    if (in_array($visitType, [
        'New Patient',
        'Follow-up',
        'Treatment Visit',
        'Review',
    ], true)) {
        $where[] = 'a.visit_type=:visit_type';
        $params[':visit_type'] = $visitType;
    }

    $patientRef = trim((string)($_GET['patient_ref'] ?? ''));

    if ($patientRef !== '') {
        $patientId = appointment_patient_ref_to_id($patientRef);
        $where[] = 'a.patient_id=:patient_id';
        $params[':patient_id'] = $patientId;
    }

    $stmt = db()->prepare(
        'SELECT
            a.id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.visit_type,
            a.consultant_name,
            a.appointment_status,
            a.status,
            p.patient_code,
            p.patient_name,
            p.mobile
         FROM clinic_appointments a
         INNER JOIN clinic_patients p
                 ON p.id=a.patient_id
                AND p.branch_id=a.branch_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.appointment_date,a.appointment_time,a.id'
    );

    $stmt->execute($params);

    $events = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref = encryptReference('clinic_appointment', (int)$row['id']);
        $time = !empty($row['appointment_time'])
            ? substr((string)$row['appointment_time'], 0, 5)
            : '00:00';

        $events[] = [
            'id' => $ref,
            'title' =>
                $time . ' ' .
                (string)$row['patient_code'] . ' - ' .
                (string)$row['patient_name'],
            'start' =>
                (string)$row['appointment_date'] .
                'T' .
                $time .
                ':00',
            'allDay' => false,
            'url' =>
                'appointment-form.php?ref=' .
                rawurlencode($ref),
            'extendedProps' => [
                'appointment_no' => (string)$row['appointment_no'],
                'patient_code' => (string)$row['patient_code'],
                'patient_name' => (string)$row['patient_name'],
                'mobile' => $row['mobile'],
                'visit_type' => $row['visit_type'],
                'consultant_name' => $row['consultant_name'],
                'appointment_status' => $row['appointment_status'],
                'status' => (int)$row['status'],
                'edit_url' =>
                    'appointment-form.php?ref=' .
                    rawurlencode($ref),
                'view_url' =>
                    'appointment-form.php?ref=' .
                    rawurlencode($ref) .
                    '&view=1',
            ],
        ];
    }

    json_success('Appointment calendar loaded.', [
        'events' => $events,
        'allowed_actions' => $access['actions'],
    ]);
}

/* Single record */
if ($method === 'GET' && isset($_GET['ref'])) {
    $access =
        require_permission(
            'appointment-list.php',
            ACTION_VIEW
        );

    $ctx =
        appointment_context(
            $access['user']
        );

    $appointmentId =
        appointment_ref_to_id(
            $_GET['ref'] ?? ''
        );

    json_success(
        'Appointment loaded.',
        [
            'record' =>
                appointment_record(
                    $ctx,
                    $appointmentId
                ),

            'patients' =>
                appointment_patient_options(
                    $ctx
                ),

            'allowed_actions' =>
                $access['actions'],

            'branch' => $ctx,
        ]
    );
}

/* DataTable */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access =
        require_permission(
            'appointment-list.php',
            ACTION_VIEW
        );

    $ctx =
        appointment_context(
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

    $appointmentStatus =
        trim(
            (string)(
                $_GET['appointment_status'] ??
                ''
            )
        );

    $visitType =
        trim(
            (string)(
                $_GET['visit_type'] ??
                ''
            )
        );

    $dateFrom =
        appointment_valid_date(
            $_GET['date_from'] ?? ''
        );

    $dateTo =
        appointment_valid_date(
            $_GET['date_to'] ?? ''
        );

    $status =
        isset($_GET['status']) &&
        $_GET['status'] !== ''
            ? (int)$_GET['status']
            : -1;

    $where = [
        'a.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' => $branchId
    ];

    if ($search !== '') {
        $like =
            '%' .
            $search .
            '%';

        $where[] =
            '(a.appointment_no LIKE :s_appt
              OR p.patient_code LIKE :s_pcode
              OR p.patient_name LIKE :s_pname
              OR p.mobile LIKE :s_mobile
              OR a.consultant_name LIKE :s_consultant
              OR a.reason_complaint LIKE :s_reason)';

        $params += [
            ':s_appt' => $like,
            ':s_pcode' => $like,
            ':s_pname' => $like,
            ':s_mobile' => $like,
            ':s_consultant' => $like,
            ':s_reason' => $like,
        ];
    }

    if (
        in_array(
            $appointmentStatus,
            [
                'Scheduled',
                'Confirmed',
                'Checked In',
                'In Consultation',
                'Completed',
                'Cancelled',
                'No Show',
            ],
            true
        )
    ) {
        $where[] =
            'a.appointment_status=:appointment_status';

        $params[':appointment_status'] =
            $appointmentStatus;
    }

    if (
        in_array(
            $visitType,
            [
                'New Patient',
                'Follow-up',
                'Treatment Visit',
                'Review',
            ],
            true
        )
    ) {
        $where[] =
            'a.visit_type=:visit_type';

        $params[':visit_type'] =
            $visitType;
    }

    if ($dateFrom !== null) {
        $where[] =
            'a.appointment_date>=:date_from';

        $params[':date_from'] =
            $dateFrom;
    }

    if ($dateTo !== null) {
        $where[] =
            'a.appointment_date<=:date_to';

        $params[':date_to'] =
            $dateTo;
    }

    if (in_array($status, [0,1], true)) {
        $where[] =
            'a.status=:status';

        $params[':status'] =
            $status;
    }

    $totalStmt =
        db()->prepare(
            'SELECT COUNT(*)
             FROM clinic_appointments
             WHERE branch_id=:branch_id'
        );

    $totalStmt->execute([
        ':branch_id' => $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $countStmt =
        db()->prepare(
            'SELECT COUNT(*)
             FROM clinic_appointments a
             INNER JOIN clinic_patients p
                     ON p.id=a.patient_id
                    AND p.branch_id=a.branch_id
             WHERE ' .
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
                COUNT(*) AS total_appointments,
                COALESCE(SUM(
                    CASE WHEN a.appointment_status=\'Scheduled\'
                         THEN 1 ELSE 0 END
                ),0) AS scheduled_appointments,
                COALESCE(SUM(
                    CASE WHEN a.appointment_status=\'Completed\'
                         THEN 1 ELSE 0 END
                ),0) AS completed_appointments,
                COALESCE(SUM(
                    CASE WHEN a.appointment_status=\'Cancelled\'
                         THEN 1 ELSE 0 END
                ),0) AS cancelled_appointments
             FROM clinic_appointments a
             INNER JOIN clinic_patients p
                     ON p.id=a.patient_id
                    AND p.branch_id=a.branch_id
             WHERE ' .
             implode(
                 ' AND ',
                 $where
             )
        );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array(
                $key,
                [
                    ':branch_id',
                    ':status'
                ],
                true
            )
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary =
        $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'a.appointment_no',
        'a.appointment_date',
        'a.appointment_time',
        'p.patient_name',
        'a.visit_type',
        'a.consultant_name',
        'a.appointment_status',
        'a.status',
        'a.created_at',
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
        'a.appointment_date';

    $sql =
        'SELECT
            a.id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.visit_type,
            a.consultant_name,
            a.appointment_status,
            a.status,
            a.created_at,
            p.patient_code,
            p.patient_name,
            p.mobile
         FROM clinic_appointments a
         INNER JOIN clinic_patients p
                 ON p.id=a.patient_id
                AND p.branch_id=a.branch_id
         WHERE ' .
         implode(
             ' AND ',
             $where
         ) .
         ' ORDER BY ' .
         $orderBy .
         ' ' .
         $orderDir .
         ',a.appointment_time DESC,a.id DESC
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
                    ':status'
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
        $row['status'] =
            (int)$row['status'];

        $row['status_label'] =
            $row['status'] === 1
                ? 'Active'
                : 'Inactive';

        $row['appointment_time'] =
            !empty($row['appointment_time'])
                ? substr(
                    (string)$row['appointment_time'],
                    0,
                    5
                )
                : null;

        $row['ref'] =
            encryptReference(
                'clinic_appointment',
                (int)$row['id']
            );

        $row['view_url'] =
            'appointment-form.php?ref=' .
            rawurlencode(
                $row['ref']
            ) .
            '&view=1';

        $row['edit_url'] =
            'appointment-form.php?ref=' .
            rawurlencode(
                $row['ref']
            );

        unset($row['id']);

        $rows[] = $row;
    }

    json_success(
        'Appointment list loaded.',
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
                'total_appointments' =>
                    (int)($summary['total_appointments'] ?? 0),
                'scheduled_appointments' =>
                    (int)($summary['scheduled_appointments'] ?? 0),
                'completed_appointments' =>
                    (int)($summary['completed_appointments'] ?? 0),
                'cancelled_appointments' =>
                    (int)($summary['cancelled_appointments'] ?? 0),
            ],

            'list_actions' =>
                $access['actions'],

            'form_actions' =>
                $access['actions'],
        ]
    );
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
                'appointment-list.php',
                4
            );

        $ctx =
            appointment_context(
                $access['user']
            );

        $branchId =
            (int)$ctx['branch_id'];

        $userId =
            (int)$access['user']['id'];

        $appointmentId =
            appointment_ref_to_id(
                $data['ref'] ?? ''
            );

        $stmt =
            db()->prepare(
                'SELECT *
                 FROM clinic_appointments
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1'
            );

        $stmt->execute([
            ':id' => $appointmentId,
            ':branch_id' => $branchId,
        ]);

        $old =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$old) {
            json_error(
                'Appointment was not found.',
                404
            );
        }

        $update =
            db()->prepare(
                'UPDATE clinic_appointments
                 SET status=0,
                     updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

        $update->execute([
            ':id' => $appointmentId,
            ':branch_id' => $branchId,
        ]);

        audit_log(
            $userId,
            4,
            [
                'company_id' =>
                    (int)$ctx['company_id'],

                'branch_id' =>
                    $branchId,

                'menu_id' =>
                    (int)$access['menu']['id'],

                'record_id' =>
                    $appointmentId,

                'old_data' =>
                    $old,
            ]
        );

        json_success(
            'Appointment deactivated successfully.'
        );
    }

    if ($action !== 'save') {
        json_error(
            'Unsupported Appointment action.',
            404
        );
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access =
        require_permission(
            'appointment-list.php',
            $isUpdate
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $ctx =
        appointment_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $clean =
        appointment_validate(
            $data,
            $ctx
        );

    $pdo = db();

    if (!$isUpdate) {
        $appointmentNo =
            appointment_generate_no(
                $pdo,
                $branchId
            );

        $stmt =
            $pdo->prepare(
                'INSERT INTO clinic_appointments
                 (
                    branch_id,
                    appointment_no,
                    patient_id,
                    appointment_date,
                    appointment_time,
                    visit_type,
                    consultant_name,
                    reason_complaint,
                    notes,
                    appointment_status,
                    status,
                    created_by,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    :branch_id,
                    :appointment_no,
                    :patient_id,
                    :appointment_date,
                    :appointment_time,
                    :visit_type,
                    :consultant_name,
                    :reason_complaint,
                    :notes,
                    :appointment_status,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                 )'
            );

        $stmt->execute([
            ':branch_id' =>
                $branchId,

            ':appointment_no' =>
                $appointmentNo,

            ':patient_id' =>
                $clean['patient_id'],

            ':appointment_date' =>
                $clean['appointment_date'],

            ':appointment_time' =>
                $clean['appointment_time'],

            ':visit_type' =>
                $clean['visit_type'],

            ':consultant_name' =>
                $clean['consultant_name'],

            ':reason_complaint' =>
                $clean['reason_complaint'],

            ':notes' =>
                $clean['notes'],

            ':appointment_status' =>
                $clean['appointment_status'],

            ':status' =>
                $clean['status'],

            ':created_by' =>
                $userId,
        ]);

        $appointmentId =
            (int)$pdo->lastInsertId();

        audit_log(
            $userId,
            2,
            [
                'company_id' =>
                    (int)$ctx['company_id'],

                'branch_id' =>
                    $branchId,

                'menu_id' =>
                    (int)$access['menu']['id'],

                'record_id' =>
                    $appointmentId,

                'new_data' =>
                    $clean,
            ]
        );

        json_success(
            'Appointment saved successfully.',
            [
                'ref' =>
                    encryptReference(
                        'clinic_appointment',
                        $appointmentId
                    ),
            ]
        );
    }

    $appointmentId =
        appointment_ref_to_id(
            $data['ref'] ?? ''
        );

    $oldStmt =
        $pdo->prepare(
            'SELECT *
             FROM clinic_appointments
             WHERE id=:id
               AND branch_id=:branch_id
             LIMIT 1'
        );

    $oldStmt->execute([
        ':id' => $appointmentId,
        ':branch_id' => $branchId,
    ]);

    $old =
        $oldStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$old) {
        json_error(
            'Appointment was not found.',
            404
        );
    }

    $stmt =
        $pdo->prepare(
            'UPDATE clinic_appointments
             SET
                patient_id=:patient_id,
                appointment_date=:appointment_date,
                appointment_time=:appointment_time,
                visit_type=:visit_type,
                consultant_name=:consultant_name,
                reason_complaint=:reason_complaint,
                notes=:notes,
                appointment_status=:appointment_status,
                status=:status,
                updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        );

    $stmt->execute([
        ':patient_id' =>
            $clean['patient_id'],

        ':appointment_date' =>
            $clean['appointment_date'],

        ':appointment_time' =>
            $clean['appointment_time'],

        ':visit_type' =>
            $clean['visit_type'],

        ':consultant_name' =>
            $clean['consultant_name'],

        ':reason_complaint' =>
            $clean['reason_complaint'],

        ':notes' =>
            $clean['notes'],

        ':appointment_status' =>
            $clean['appointment_status'],

        ':status' =>
            $clean['status'],

        ':id' =>
            $appointmentId,

        ':branch_id' =>
            $branchId,
    ]);

    audit_log(
        $userId,
        3,
        [
            'company_id' =>
                (int)$ctx['company_id'],

            'branch_id' =>
                $branchId,

            'menu_id' =>
                (int)$access['menu']['id'],

            'record_id' =>
                $appointmentId,

            'old_data' =>
                $old,

            'new_data' =>
                $clean,
        ]
    );

    json_success(
        'Appointment updated successfully.',
        [
            'ref' =>
                encryptReference(
                    'clinic_appointment',
                    $appointmentId
                ),
        ]
    );
}

json_error(
    'Method not allowed.',
    405
);
