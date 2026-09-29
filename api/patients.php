<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function patient_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Patient Master is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
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

function patient_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    $required = [
        'id','branch_id','patient_code','patient_name','date_of_birth','gender',
        'mobile','alternate_mobile','email','address','emergency_contact_name',
        'emergency_contact_mobile','blood_group','known_allergies',
        'medical_conditions','identity_number','status','created_by','created_at','updated_at'
    ];

    $tableStmt = db()->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
    );
    $tableStmt->execute([':table_name' => 'clinic_patients']);

    if ((int)$tableStmt->fetchColumn() !== 1) {
        json_error('Patient Master database table is missing: clinic_patients.', 500);
    }

    $ph = implode(',', array_fill(0, count($required), '?'));
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME IN (' . $ph . ')'
    );
    $stmt->execute(array_merge(['clinic_patients'], $required));

    $found = array_map('strtolower', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
    $missing = array_values(array_diff($required, $found));

    if ($missing !== []) {
        json_error(
            'Patient Master database structure is incomplete.',
            500,
            ['schema' => 'Missing clinic_patients columns: ' . implode(', ', $missing)]
        );
    }

    $checked = true;
}

function patient_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Patient reference is required.', 422, ['ref' => 'Patient reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'clinic_patient');
    } catch (Throwable $e) {
        json_error('Invalid Patient reference.', 422, ['ref' => 'Invalid Patient reference.']);
    }

    return 0;
}

function patient_nullable_text($value, int $max): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : mb_substr($value, 0, $max);
}

function patient_generate_code(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT patient_code
         FROM clinic_patients
         WHERE branch_id=:branch_id
           AND patient_code REGEXP '^PAT[0-9]+$'
         ORDER BY CAST(SUBSTRING(patient_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^PAT([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'PAT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function patient_validate_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return null;

    return $value;
}

function patient_validate(array $data): array
{
    $errors = [];

    $name = trim((string)($data['patient_name'] ?? ''));
    $dobRaw = trim((string)($data['date_of_birth'] ?? ''));
    $dob = $dobRaw === '' ? null : patient_validate_date($dobRaw);
    $gender = trim((string)($data['gender'] ?? ''));
    $mobile = trim((string)($data['mobile'] ?? ''));
    $altMobile = trim((string)($data['alternate_mobile'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $address = patient_nullable_text($data['address'] ?? null, 5000);
    $emergencyName = patient_nullable_text($data['emergency_contact_name'] ?? null, 150);
    $emergencyMobile = trim((string)($data['emergency_contact_mobile'] ?? ''));
    $bloodGroup = trim((string)($data['blood_group'] ?? ''));
    $allergies = patient_nullable_text($data['known_allergies'] ?? null, 5000);
    $conditions = patient_nullable_text($data['medical_conditions'] ?? null, 5000);
    $identity = patient_nullable_text($data['identity_number'] ?? null, 100);
    $status = (int)($data['status'] ?? 1);

    if ($name === '') {
        $errors['patient_name'] = 'Patient Name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors['patient_name'] = 'Patient Name cannot exceed 150 characters.';
    }

    if ($dobRaw !== '' && $dob === null) {
        $errors['date_of_birth'] = 'Enter a valid Date of Birth.';
    } elseif ($dob !== null && $dob > date('Y-m-d')) {
        $errors['date_of_birth'] = 'Date of Birth cannot be in the future.';
    }

    if (!in_array($gender, ['', 'Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Select a valid Gender.';
    }

    if ($mobile === '') {
        $errors['mobile'] = 'Mobile Number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid 10-digit Mobile Number.';
    }

    if ($altMobile !== '' && !preg_match('/^[0-9]{10}$/', $altMobile)) {
        $errors['alternate_mobile'] = 'Enter a valid 10-digit Alternate Mobile Number.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid Email Address.';
    }

    if ($emergencyMobile !== '' && !preg_match('/^[0-9]{10}$/', $emergencyMobile)) {
        $errors['emergency_contact_mobile'] = 'Enter a valid 10-digit Emergency Contact Mobile.';
    }

    if (!in_array($bloodGroup, ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $errors['blood_group'] = 'Select a valid Blood Group.';
    }

    if (!in_array($status, [0,1], true)) {
        $errors['status'] = 'Select a valid Status.';
    }

    if ($errors !== []) {
        json_error('Patient validation failed.', 422, $errors);
    }

    return [
        'patient_name' => $name,
        'date_of_birth' => $dob,
        'gender' => $gender === '' ? null : $gender,
        'mobile' => $mobile,
        'alternate_mobile' => $altMobile === '' ? null : $altMobile,
        'email' => $email === '' ? null : $email,
        'address' => $address,
        'emergency_contact_name' => $emergencyName,
        'emergency_contact_mobile' => $emergencyMobile === '' ? null : $emergencyMobile,
        'blood_group' => $bloodGroup === '' ? null : $bloodGroup,
        'known_allergies' => $allergies,
        'medical_conditions' => $conditions,
        'identity_number' => $identity,
        'status' => $status,
    ];
}

function patient_record(array $ctx, int $patientId): array
{
    $stmt = db()->prepare(
        'SELECT p.*,u.name AS created_by_name
         FROM clinic_patients p
         LEFT JOIN users u ON u.id=p.created_by
         WHERE p.id=:patient_id AND p.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':patient_id' => $patientId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_error('Patient was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('clinic_patient', (int)$row['id']);
    $row['age'] = null;

    if (!empty($row['date_of_birth'])) {
        try {
            $dob = new DateTimeImmutable((string)$row['date_of_birth']);
            $today = new DateTimeImmutable('today');
            if ($dob <= $today) {
                $row['age'] = $dob->diff($today)->y;
            }
        } catch (Throwable $e) {
            $row['age'] = null;
        }
    }

    return $row;
}

function patient_dependency_counts(PDO $pdo, int $patientId): array
{
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND COLUMN_NAME='patient_id'
           AND TABLE_NAME LIKE 'clinic\\_%'
           AND TABLE_NAME<>'clinic_patients'"
    );
    $stmt->execute();

    $counts = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        $tableName = (string)$tableName;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) continue;

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `' . $tableName . '` WHERE patient_id=:patient_id'
        );
        $countStmt->execute([':patient_id' => $patientId]);

        $count = (int)$countStmt->fetchColumn();
        if ($count > 0) {
            $counts[$tableName] = $count;
        }
    }

    return $counts;
}

$method = request_method();
patient_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('patient-list.php', ACTION_VIEW);
    $ctx = patient_context($access['user']);

    json_success('Patient form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_patient_code' => patient_generate_code(db(), (int)$ctx['branch_id']),
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('patient-list.php', ACTION_VIEW);
    $ctx = patient_context($access['user']);

    $id = patient_ref_to_id($_GET['ref'] ?? '');

    json_success('Patient loaded.', [
        'record' => patient_record($ctx, $id),
        'allowed_actions' => $access['actions'],
        'branch' => $ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('patient-list.php', ACTION_VIEW);
    $ctx = patient_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));

    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0 ? 100000 : max(1, min(100000, $lengthRaw));

    $search = trim((string)($_GET['search']['value'] ?? ''));
    $gender = trim((string)($_GET['gender'] ?? ''));
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : -1;

    $where = ['p.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] =
            '(p.patient_code LIKE :s_code
              OR p.patient_name LIKE :s_name
              OR p.mobile LIKE :s_mobile
              OR p.alternate_mobile LIKE :s_alt
              OR p.email LIKE :s_email
              OR p.identity_number LIKE :s_identity)';

        $params += [
            ':s_code' => $like,
            ':s_name' => $like,
            ':s_mobile' => $like,
            ':s_alt' => $like,
            ':s_email' => $like,
            ':s_identity' => $like,
        ];
    }

    if (in_array($gender, ['Male','Female','Other'], true)) {
        $where[] = 'p.gender=:gender';
        $params[':gender'] = $gender;
    }

    if (in_array($status, [0,1], true)) {
        $where[] = 'p.status=:status';
        $params[':status'] = $status;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*) FROM clinic_patients WHERE branch_id=:branch_id'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*) FROM clinic_patients p WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN p.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN p.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(
                AVG(
                    CASE
                        WHEN p.date_of_birth IS NULL THEN NULL
                        ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
                    END
                ),
                0
            ) AS average_age
         FROM clinic_patients p
         WHERE ' . implode(' AND ', $where)
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'p.patient_code','p.patient_name','p.date_of_birth','p.gender',
        'p.mobile','p.blood_group','p.status','p.created_at'
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = $orderColumns[$orderIndex] ?? 'p.patient_name';

    $sql =
        'SELECT
            p.id,p.patient_code,p.patient_name,p.date_of_birth,p.gender,
            p.mobile,p.blood_group,p.status,p.created_at,
            CASE
                WHEN p.date_of_birth IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE())
            END AS age
         FROM clinic_patients p
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $orderBy . ' ' . $orderDir . ',p.patient_name,p.id DESC
         LIMIT :start,:length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status'], true) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['status'] = (int)$row['status'];
        $row['age'] = $row['age'] === null ? null : (int)$row['age'];
        $row['status_label'] = $row['status'] === 1 ? 'Active' : 'Inactive';
        $row['ref'] = encryptReference('clinic_patient', (int)$row['id']);
        $row['view_url'] = 'patient-profile.php?ref=' . rawurlencode($row['ref']);
        $row['edit_url'] = 'patient-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Patient list loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_count' => (int)($summary['total_count'] ?? 0),
            'active_count' => (int)($summary['active_count'] ?? 0),
            'inactive_count' => (int)($summary['inactive_count'] ?? 0),
            'average_age' => round((float)($summary['average_age'] ?? 0), 1),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'delete') {
        $access = require_permission('patient-list.php', 4);
        $ctx = patient_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $patientId = patient_ref_to_id($data['ref'] ?? '');
        $pdo = db();

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM clinic_patients
                 WHERE id=:id AND branch_id=:branch_id
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([
                ':id' => $patientId,
                ':branch_id' => $branchId,
            ]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                json_error('Patient was not found.', 404);
            }

            if (patient_dependency_counts($pdo, $patientId) !== []) {
                $pdo->rollBack();
                json_error(
                    'This Patient is already used in the Clinic. Set the Patient to Inactive instead of deleting.',
                    409
                );
            }

            $deleteStmt = $pdo->prepare(
                'DELETE FROM clinic_patients WHERE id=:id AND branch_id=:branch_id'
            );
            $deleteStmt->execute([
                ':id' => $patientId,
                ':branch_id' => $branchId,
            ]);

            $pdo->commit();

            audit_log($userId, 4, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $patientId,
                'old_data' => $old,
            ]);

            json_success('Patient deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Patient action.', 404);
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access = require_permission(
        'patient-list.php',
        $isUpdate ? ACTION_UPDATE : ACTION_CREATE
    );
    $ctx = patient_context($access['user']);

    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $clean = patient_validate($data);
    $pdo = db();

    if (!$isUpdate) {
        $patientCode = patient_generate_code($pdo, $branchId);

        $stmt = $pdo->prepare(
            'INSERT INTO clinic_patients
             (
                branch_id,patient_code,patient_name,date_of_birth,gender,mobile,
                alternate_mobile,email,address,emergency_contact_name,
                emergency_contact_mobile,blood_group,known_allergies,
                medical_conditions,identity_number,status,created_by,created_at,updated_at
             )
             VALUES
             (
                :branch_id,:patient_code,:patient_name,:date_of_birth,:gender,:mobile,
                :alternate_mobile,:email,:address,:emergency_contact_name,
                :emergency_contact_mobile,:blood_group,:known_allergies,
                :medical_conditions,:identity_number,:status,:created_by,NOW(),NOW()
             )'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':patient_code' => $patientCode,
            ':patient_name' => $clean['patient_name'],
            ':date_of_birth' => $clean['date_of_birth'],
            ':gender' => $clean['gender'],
            ':mobile' => $clean['mobile'],
            ':alternate_mobile' => $clean['alternate_mobile'],
            ':email' => $clean['email'],
            ':address' => $clean['address'],
            ':emergency_contact_name' => $clean['emergency_contact_name'],
            ':emergency_contact_mobile' => $clean['emergency_contact_mobile'],
            ':blood_group' => $clean['blood_group'],
            ':known_allergies' => $clean['known_allergies'],
            ':medical_conditions' => $clean['medical_conditions'],
            ':identity_number' => $clean['identity_number'],
            ':status' => $clean['status'],
            ':created_by' => $userId,
        ]);

        $patientId = (int)$pdo->lastInsertId();

        audit_log($userId, 2, [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $patientId,
            'new_data' => $clean,
        ]);

        json_success('Patient saved successfully.', [
            'ref' => encryptReference('clinic_patient', $patientId),
        ]);
    }

    $patientId = patient_ref_to_id($data['ref'] ?? '');

    $oldStmt = $pdo->prepare(
        'SELECT * FROM clinic_patients
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $oldStmt->execute([
        ':id' => $patientId,
        ':branch_id' => $branchId,
    ]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        json_error('Patient was not found.', 404);
    }

    $stmt = $pdo->prepare(
        'UPDATE clinic_patients
         SET patient_name=:patient_name,
             date_of_birth=:date_of_birth,
             gender=:gender,
             mobile=:mobile,
             alternate_mobile=:alternate_mobile,
             email=:email,
             address=:address,
             emergency_contact_name=:emergency_contact_name,
             emergency_contact_mobile=:emergency_contact_mobile,
             blood_group=:blood_group,
             known_allergies=:known_allergies,
             medical_conditions=:medical_conditions,
             identity_number=:identity_number,
             status=:status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );

    $stmt->execute([
        ':patient_name' => $clean['patient_name'],
        ':date_of_birth' => $clean['date_of_birth'],
        ':gender' => $clean['gender'],
        ':mobile' => $clean['mobile'],
        ':alternate_mobile' => $clean['alternate_mobile'],
        ':email' => $clean['email'],
        ':address' => $clean['address'],
        ':emergency_contact_name' => $clean['emergency_contact_name'],
        ':emergency_contact_mobile' => $clean['emergency_contact_mobile'],
        ':blood_group' => $clean['blood_group'],
        ':known_allergies' => $clean['known_allergies'],
        ':medical_conditions' => $clean['medical_conditions'],
        ':identity_number' => $clean['identity_number'],
        ':status' => $clean['status'],
        ':id' => $patientId,
        ':branch_id' => $branchId,
    ]);

    audit_log($userId, 3, [
        'company_id' => (int)$ctx['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $patientId,
        'old_data' => $old,
        'new_data' => $clean,
    ]);

    json_success('Patient updated successfully.', [
        'ref' => encryptReference('clinic_patient', $patientId),
    ]);
}

json_error('Method not allowed.', 405);
