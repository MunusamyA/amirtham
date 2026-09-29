<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Clinic Diagnosis + Treatment Plan API
 *
 * User-facing flow:
 * Diagnosis -> Day-wise Treatment / Procedure rows
 *
 * Direct primary-ID flow:
 * clinic_diagnoses.id -> clinic_diagnosis_treatment_days.diagnosis_id
 * No protocol master/table is used by this module.
 */

function clinic_diag_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];

    $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
    return $cache[$table] = (bool)($stmt && $stmt->fetchColumn());
}

function clinic_diag_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!clinic_diag_table_exists($table)) return $cache[$key] = false;

    $stmt = db()->query(
        'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . db()->quote($column)
    );

    return $cache[$key] = (bool)($stmt && $stmt->fetchColumn());
}

function clinic_diag_require_table(string $table, array $columns): void
{
    if (!clinic_diag_table_exists($table)) {
        json_error(
            'Clinic Diagnosis database setup is incomplete.',
            500,
            ['schema' => 'Missing table: ' . $table . '.']
        );
    }

    foreach ($columns as $column) {
        if (!clinic_diag_column_exists($table, $column)) {
            json_error(
                'Clinic Diagnosis database structure is incomplete.',
                500,
                ['schema' => 'Missing ' . $table . ' column: ' . $column . '.']
            );
        }
    }
}

function clinic_diag_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    clinic_diag_require_table('clinic_diagnoses', [
        'id','branch_id','diagnosis_code','diagnosis_name','category',
        'description','status','created_by','created_at','updated_at'
    ]);

    clinic_diag_require_table('clinic_treatment_procedures', [
        'id','branch_id','procedure_code','procedure_name','status'
    ]);

    clinic_diag_require_table('clinic_diagnosis_treatment_days', [
        'id','branch_id','diagnosis_id','day_number','treatment_procedure_id',
        'instructions','notes','status','created_by','created_at','updated_at'
    ]);

    $checked = true;
}

function clinic_diag_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Clinic Diagnosis Master is available only for tenant users.', 403);
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

function clinic_diag_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Diagnosis reference is required.', 422, [
            'ref' => 'Diagnosis reference is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'clinic_diagnosis');
    } catch (Throwable $e) {
        json_error('Invalid Diagnosis reference.', 422, [
            'ref' => 'Invalid Diagnosis reference.',
        ]);
    }

    return 0;
}

function clinic_diag_nullable_text($value, int $max): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;

    return mb_substr($value, 0, $max);
}

function clinic_diag_generate_code(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT diagnosis_code
         FROM clinic_diagnoses
         WHERE branch_id=:branch_id
           AND diagnosis_code REGEXP '^DIA[0-9]+$'
         ORDER BY CAST(SUBSTRING(diagnosis_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/^DIA([0-9]+)$/i', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    return 'DIA' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function clinic_diag_procedure_options(array $ctx): array
{
    $stmt = db()->prepare(
        'SELECT id,procedure_code,procedure_name,status
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id
           AND status=1
         ORDER BY procedure_name,procedure_code,id'
    );
    $stmt->execute([':branch_id' => (int)$ctx['branch_id']]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference(
                'clinic_treatment_procedure',
                (int)$row['id']
            ),
            'procedure_code' => (string)$row['procedure_code'],
            'procedure_name' => (string)$row['procedure_name'],
            'status' => (int)$row['status'],
            'label' =>
                (string)$row['procedure_code'] .
                ' - ' .
                (string)$row['procedure_name'],
        ];
    }

    return $rows;
}

function clinic_diag_procedure_ref_to_id(array $ctx, $value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Treatment / Procedure is required.', 422);
    }

    try {
        $id = decryptReference(
            trim($value),
            'clinic_treatment_procedure'
        );
    } catch (Throwable $e) {
        json_error('Invalid Treatment / Procedure reference.', 422);
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
        json_error('Selected Treatment / Procedure is invalid or inactive.', 422);
    }

    return $id;
}

function clinic_diag_validate(array $data, array $ctx): array
{
    $errors = [];

    $diagnosisName = trim((string)($data['diagnosis_name'] ?? ''));
    $category = clinic_diag_nullable_text($data['category'] ?? null, 100);
    $description = clinic_diag_nullable_text($data['description'] ?? null, 5000);
    $status = (int)($data['status'] ?? 1);

    if ($diagnosisName === '') {
        $errors['diagnosis_name'] = 'Disease / Diagnosis Name is required.';
    } elseif (mb_strlen($diagnosisName) > 150) {
        $errors['diagnosis_name'] = 'Disease / Diagnosis Name cannot exceed 150 characters.';
    }

    if (!in_array($status, [0,1], true)) {
        $errors['status'] = 'Select a valid Status.';
    }

    $daysRaw = [];
    if (isset($data['treatment_days']) && is_array($data['treatment_days'])) {
        $daysRaw = array_values($data['treatment_days']);
    } elseif (isset($data['protocol_days']) && is_array($data['protocol_days'])) {
        /* Backward-compatible payload name. */
        $daysRaw = array_values($data['protocol_days']);
    }

    if ($daysRaw === []) {
        $errors['treatment_days'] = 'Add at least one Treatment / Procedure row.';
    }

    $days = [];

    foreach ($daysRaw as $index => $row) {
        $prefix = 'treatment_days[' . $index . ']';

        if (!is_array($row)) {
            $errors[$prefix . '[treatment_procedure_ref]'] = 'Invalid Treatment row.';
            continue;
        }

        $procedureRef = trim((string)($row['treatment_procedure_ref'] ?? ''));
        $procedureId = 0;

        if ($procedureRef === '') {
            $errors[$prefix . '[treatment_procedure_ref]'] =
                'Treatment / Procedure is required.';
        } else {
            try {
                $procedureId = clinic_diag_procedure_ref_to_id($ctx, $procedureRef);
            } catch (Throwable $e) {
                $procedureId = 0;
                $errors[$prefix . '[treatment_procedure_ref]'] =
                    'Select a valid active Treatment / Procedure.';
            }
        }

        $instructions = clinic_diag_nullable_text(
            $row['instructions'] ?? null,
            5000
        );
        $notes = clinic_diag_nullable_text(
            $row['notes'] ?? null,
            5000
        );

        $dayStatus = (int)($row['status'] ?? 1);
        if (!in_array($dayStatus, [0,1], true)) {
            $errors[$prefix . '[status]'] = 'Select a valid Status.';
        }

        $days[] = [
            'day_number' => $index + 1,
            'treatment_procedure_id' => $procedureId,
            'treatment_procedure_ref' => $procedureRef,
            'instructions' => $instructions,
            'notes' => $notes,
            'status' => $dayStatus,
        ];
    }

    if ($errors !== []) {
        json_error('Diagnosis validation failed.', 422, $errors);
    }

    return [
        'diagnosis_name' => $diagnosisName,
        'category' => $category,
        'description' => $description,
        'status' => $status,
        'days' => $days,
    ];
}

function clinic_diag_assert_unique_name(
    PDO $pdo,
    int $branchId,
    string $diagnosisName,
    int $excludeId = 0
): void {
    $sql =
        'SELECT id
         FROM clinic_diagnoses
         WHERE branch_id=:branch_id
           AND LOWER(TRIM(diagnosis_name))=LOWER(TRIM(:diagnosis_name))';

    $params = [
        ':branch_id' => $branchId,
        ':diagnosis_name' => $diagnosisName,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Diagnosis validation failed.', 409, [
            'diagnosis_name' =>
                'This Disease / Diagnosis Name is already used in the current branch.',
        ]);
    }
}

function clinic_diag_record(array $ctx, int $diagnosisId): array
{
    $stmt = db()->prepare(
        'SELECT
            d.id AS diagnosis_id,
            d.diagnosis_code,
            d.diagnosis_name,
            d.category,
            d.description,
            d.status,
            d.created_by,
            d.created_at,
            d.updated_at,
            COALESCE(u.name,\'\') AS created_by_name
         FROM clinic_diagnoses d
         LEFT JOIN users u
                ON u.id=d.created_by
         WHERE d.id=:diagnosis_id
           AND d.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':diagnosis_id' => $diagnosisId,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_error('Diagnosis was not found.', 404);
    }

    $row['diagnosis_id'] = (int)$row['diagnosis_id'];
    $row['status'] = (int)$row['status'];
    $row['ref'] =
        encryptReference('clinic_diagnosis', (int)$row['diagnosis_id']);

    return $row;
}

function clinic_diag_treatment_days(array $ctx, int $diagnosisId): array
{
    $stmt = db()->prepare(
        'SELECT
            d.id,
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
         ORDER BY d.day_number,d.id'
    );
    $stmt->execute([
        ':branch_id' => (int)$ctx['branch_id'],
        ':diagnosis_id' => $diagnosisId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'day_number' => (int)$row['day_number'],
            'treatment_procedure_ref' =>
                encryptReference(
                    'clinic_treatment_procedure',
                    (int)$row['treatment_procedure_id']
                ),
            'procedure_code' => (string)$row['procedure_code'],
            'procedure_name' => (string)$row['procedure_name'],
            'instructions' => $row['instructions'],
            'notes' => $row['notes'],
            'status' => (int)$row['status'],
        ];
    }

    return $rows;
}

function clinic_diag_replace_days(
    PDO $pdo,
    int $branchId,
    int $diagnosisId,
    array $days,
    int $userId
): void {
    $delete = $pdo->prepare(
        'DELETE FROM clinic_diagnosis_treatment_days
         WHERE branch_id=:branch_id
           AND diagnosis_id=:diagnosis_id'
    );
    $delete->execute([
        ':branch_id' => $branchId,
        ':diagnosis_id' => $diagnosisId,
    ]);

    $insert = $pdo->prepare(
        'INSERT INTO clinic_diagnosis_treatment_days
         (
            branch_id,
            diagnosis_id,
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
            :diagnosis_id,
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

    foreach ($days as $day) {
        $insert->execute([
            ':branch_id' => $branchId,
            ':diagnosis_id' => $diagnosisId,
            ':day_number' => (int)$day['day_number'],
            ':treatment_procedure_id' =>
                (int)$day['treatment_procedure_id'],
            ':instructions' => $day['instructions'],
            ':notes' => $day['notes'],
            ':status' => (int)$day['status'],
            ':created_by' => $userId,
        ]);
    }
}

$method = request_method();
clinic_diag_require_schema();

/* Form options */
if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('diagnosis-list.php', ACTION_VIEW);
    $ctx = clinic_diag_tenant_context($access['user']);

    json_success('Diagnosis form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_diagnosis_code' =>
            clinic_diag_generate_code(db(), (int)$ctx['branch_id']),
        'treatment_procedures' => clinic_diag_procedure_options($ctx),
        'branch' => $ctx,
    ]);
}

/* Single record */
if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('diagnosis-list.php', ACTION_VIEW);
    $ctx = clinic_diag_tenant_context($access['user']);

    $id = clinic_diag_ref_to_id($_GET['ref'] ?? '');
    $record = clinic_diag_record($ctx, $id);
    $days = clinic_diag_treatment_days($ctx, $id);

    json_success('Diagnosis loaded.', [
        'record' => $record,
        'treatment_days' => $days,
        /* Backward-compatible response alias. */
        'protocol_days' => $days,
        'treatment_procedures' => clinic_diag_procedure_options($ctx),
        'allowed_actions' => $access['actions'],
        'branch' => $ctx,
    ]);
}

/* HSN-style server-side list */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('diagnosis-list.php', ACTION_VIEW);
    $ctx = clinic_diag_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));

    $lengthRaw = (int)($_GET['length'] ?? 25);
    $length =
        $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

    $search = trim((string)($_GET['search']['value'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));

    $statusFilter =
        isset($_GET['status']) && $_GET['status'] !== ''
            ? normalize_status($_GET['status'])
            : null;

    $where = ['d.branch_id=:branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] =
            '(d.diagnosis_code LIKE :search_code
              OR d.diagnosis_name LIKE :search_name
              OR COALESCE(d.category,\'\') LIKE :search_category)';
        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_category'] = $like;
    }

    if ($category !== '') {
        $where[] = 'd.category=:category';
        $params[':category'] = $category;
    }

    if ($statusFilter !== null) {
        $where[] = 'd.status=:status';
        $params[':status'] = $statusFilter;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $bind = static function (PDOStatement $stmt, array $values): void {
        foreach ($values as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                in_array($key, [':branch_id',':status'], true)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
            );
        }
    };

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM clinic_diagnoses
         WHERE branch_id=:branch_id'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM clinic_diagnoses d' . $whereSql
    );
    $bind($countStmt, $params);
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_diagnoses,
            COALESCE(SUM(CASE WHEN d.status=1 THEN 1 ELSE 0 END),0) AS active_diagnoses,
            COALESCE(SUM(CASE WHEN d.status=0 THEN 1 ELSE 0 END),0) AS inactive_diagnoses,
            COUNT(DISTINCT NULLIF(TRIM(d.category),\'\')) AS categories
         FROM clinic_diagnoses d' . $whereSql
    );
    $bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        'd.diagnosis_code',
        'd.diagnosis_name',
        'd.category',
        'treatment_days',
        'd.status',
        'd.created_at',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDir =
        strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
            ? 'DESC'
            : 'ASC';
    $orderBy = $orderColumns[$orderIndex] ?? 'd.diagnosis_name';

    $sql =
        'SELECT
            d.id,
            d.diagnosis_code,
            d.diagnosis_name,
            d.category,
            d.status,
            d.created_at,
            (
                SELECT COUNT(*)
                FROM clinic_diagnosis_treatment_days tpd
                WHERE tpd.branch_id=d.branch_id
                  AND tpd.diagnosis_id=d.id
            ) AS treatment_days
         FROM clinic_diagnoses d' .
        $whereSql .
        ' ORDER BY ' . $orderBy . ' ' . $orderDir . ',
                  d.diagnosis_name ASC,
                  d.id DESC
          LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    $bind($stmt, $params);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref = encryptReference('clinic_diagnosis', (int)$row['id']);

        $rows[] = [
            'ref' => $ref,
            'diagnosis_code' => (string)$row['diagnosis_code'],
            'diagnosis_name' => (string)$row['diagnosis_name'],
            'category' => $row['category'],
            'treatment_days' => (int)$row['treatment_days'],
            'status' => (int)$row['status'],
            'status_label' =>
                (int)$row['status'] === 1 ? 'Active' : 'Inactive',
            'created_at' => $row['created_at'],
            'view_url' =>
                'diagnosis-form.php?ref=' .
                rawurlencode($ref) .
                '&view=1',
            'edit_url' =>
                'diagnosis-form.php?ref=' .
                rawurlencode($ref),
        ];
    }

    $categoryStmt = db()->prepare(
        'SELECT DISTINCT TRIM(category) AS category
         FROM clinic_diagnoses
         WHERE branch_id=:branch_id
           AND category IS NOT NULL
           AND TRIM(category)<>\'\'
         ORDER BY category'
    );
    $categoryStmt->execute([':branch_id' => $branchId]);
    $categories = array_values(
        array_filter(
            array_map(
                static fn($v): string => trim((string)$v),
                $categoryStmt->fetchAll(PDO::FETCH_COLUMN)
            ),
            static fn(string $v): bool => $v !== ''
        )
    );

    json_success('Diagnosis records loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_diagnoses' => (int)($summary['total_diagnoses'] ?? 0),
            'active_diagnoses' => (int)($summary['active_diagnoses'] ?? 0),
            'inactive_diagnoses' => (int)($summary['inactive_diagnoses'] ?? 0),
            'categories' => (int)($summary['categories'] ?? 0),
        ],
        'filter_categories' => $categories,
        'allowed_actions' => $access['actions'],
    ]);
}

/* Save */
if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    /* Backward compatibility: old Delete button now deactivates the Diagnosis. */
    if ($action === 'delete') {
        $access = require_permission('diagnosis-list.php', 4);
        $ctx = clinic_diag_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $diagnosisId = clinic_diag_ref_to_id($data['ref'] ?? '');
        $old = clinic_diag_record($ctx, $diagnosisId);

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'UPDATE clinic_diagnoses
                 SET status=0,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':id' => $diagnosisId,
                ':branch_id' => $branchId,
            ]);

            $pdo->prepare(
                'UPDATE clinic_diagnosis_treatment_days
                 SET status=0,updated_at=NOW()
                 WHERE diagnosis_id=:diagnosis_id
                   AND branch_id=:branch_id'
            )->execute([
                ':diagnosis_id' => $diagnosisId,
                ':branch_id' => $branchId,
            ]);

            $pdo->commit();

            audit_log((int)$access['user']['id'], 4, [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $diagnosisId,
                'old_data' => $old,
            ]);

            json_success('Diagnosis deactivated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Diagnosis action.', 404);
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access = require_permission(
        'diagnosis-list.php',
        $isUpdate ? ACTION_UPDATE : ACTION_CREATE
    );
    $ctx = clinic_diag_tenant_context($access['user']);

    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $pdo = db();
    $clean = clinic_diag_validate($data, $ctx);

    $diagnosisId =
        $isUpdate
            ? clinic_diag_ref_to_id($data['ref'])
            : 0;

    clinic_diag_assert_unique_name(
        $pdo,
        $branchId,
        $clean['diagnosis_name'],
        $diagnosisId
    );

    $pdo->beginTransaction();

    try {
        $old = null;

        if ($isUpdate) {
            $lock = $pdo->prepare(
                'SELECT *
                 FROM clinic_diagnoses
                 WHERE id=:id
                   AND branch_id=:branch_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $lock->execute([
                ':id' => $diagnosisId,
                ':branch_id' => $branchId,
            ]);
            $old = $lock->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                json_error('Diagnosis was not found.', 404);
            }

            $stmt = $pdo->prepare(
                'UPDATE clinic_diagnoses
                 SET diagnosis_name=:diagnosis_name,
                     category=:category,
                     description=:description,
                     status=:status,
                     updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':diagnosis_name' => $clean['diagnosis_name'],
                ':category' => $clean['category'],
                ':description' => $clean['description'],
                ':status' => $clean['status'],
                ':id' => $diagnosisId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $diagnosisCode = clinic_diag_generate_code($pdo, $branchId);

            $stmt = $pdo->prepare(
                'INSERT INTO clinic_diagnoses
                 (
                    branch_id,
                    diagnosis_code,
                    diagnosis_name,
                    category,
                    description,
                    status,
                    created_by,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    :branch_id,
                    :diagnosis_code,
                    :diagnosis_name,
                    :category,
                    :description,
                    :status,
                    :created_by,
                    NOW(),
                    NOW()
                 )'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':diagnosis_code' => $diagnosisCode,
                ':diagnosis_name' => $clean['diagnosis_name'],
                ':category' => $clean['category'],
                ':description' => $clean['description'],
                ':status' => $clean['status'],
                ':created_by' => $userId,
            ]);

            $diagnosisId = (int)$pdo->lastInsertId();
        }

        clinic_diag_replace_days(
            $pdo,
            $branchId,
            $diagnosisId,
            $clean['days'],
            $userId
        );

        $pdo->commit();

        audit_log(
            $userId,
            $isUpdate ? ACTION_UPDATE : ACTION_CREATE,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $diagnosisId,
                'old_data' => $old,
                'new_data' => [
                    'diagnosis_name' => $clean['diagnosis_name'],
                    'category' => $clean['category'],
                    'description' => $clean['description'],
                    'status' => $clean['status'],
                    'treatment_days' => $clean['days'],
                ],
            ]
        );

        json_success(
            $isUpdate
                ? 'Diagnosis updated successfully.'
                : 'Diagnosis saved successfully.',
            [
                'ref' =>
                    encryptReference(
                        'clinic_diagnosis',
                        $diagnosisId
                    ),
            ],
            $isUpdate ? 200 : 201
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* Activate / Deactivate - same list behavior as HSN Master */
if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['ref','status']);

    $status = normalize_status($data['status']);
    $access = require_permission(
        'diagnosis-list.php',
        $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE
    );
    $ctx = clinic_diag_tenant_context($access['user']);

    $branchId = (int)$ctx['branch_id'];
    $diagnosisId = clinic_diag_ref_to_id($data['ref']);
    $old = clinic_diag_record($ctx, $diagnosisId);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'UPDATE clinic_diagnoses
             SET status=:status,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':status' => $status,
            ':id' => $diagnosisId,
            ':branch_id' => $branchId,
        ]);

        $pdo->prepare(
            'UPDATE clinic_diagnosis_treatment_days
             SET status=CASE WHEN :status=0 THEN 0 ELSE status END,
                 updated_at=NOW()
             WHERE diagnosis_id=:diagnosis_id
               AND branch_id=:branch_id'
        )->execute([
            ':status' => $status,
            ':diagnosis_id' => $diagnosisId,
            ':branch_id' => $branchId,
        ]);

        $pdo->commit();

        audit_log(
            (int)$access['user']['id'],
            $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $diagnosisId,
                'old_data' => $old,
            ]
        );

        json_success(
            $status === 1
                ? 'Diagnosis activated.'
                : 'Diagnosis deactivated.'
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

json_error('Method not allowed.', 405);
