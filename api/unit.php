<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function unit_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Unit management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :branch_id
           AND b.status = 1
           AND c.status = 1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $context = $stmt->fetch();

    if (!$context) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$context['branch_id'],
        'company_id' => (int)$context['company_id'],
        'branch_name' => (string)$context['branch_name'],
        'company_name' => (string)$context['company_name'],
    ];
}

function unit_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT unit_code
         FROM food_units
         WHERE branch_id = :branch_id
           AND unit_code REGEXP '^UNT[0-9]+$'
         ORDER BY CAST(SUBSTRING(unit_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string)($stmt->fetchColumn() ?: '');
    $nextNumber = 1;

    if ($lastCode !== '' && preg_match('/^UNT([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return 'UNT' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function unit_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, unit_code, unit_name, unit_symbol, status,
                created_by, created_at, updated_at
         FROM food_units
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Unit record was not found.', 404);
    }

    foreach (['id', 'branch_id', 'status', 'created_by'] as $key) {
        $row[$key] = (int)$row[$key];
    }

    return $row;
}

function unit_clean_name($value): string
{
    $name = preg_replace('/\s+/', ' ', trim((string)$value));

    if ($name === '') {
        json_error('Unit validation failed.', 422, [
            'unit_name' => 'Unit Name is required.',
        ]);
    }

    if (strlen($name) > 100) {
        json_error('Unit validation failed.', 422, [
            'unit_name' => 'Unit Name must be within 100 characters.',
        ]);
    }

    return $name;
}

function unit_clean_symbol($value): string
{
    $symbol = strtoupper(trim((string)$value));
    $symbol = preg_replace('/\s+/', '', $symbol);

    if ($symbol === '') {
        json_error('Unit validation failed.', 422, [
            'unit_symbol' => 'Unit Symbol is required.',
        ]);
    }

    if (strlen($symbol) > 30 || preg_match('/^[A-Z0-9._\/-]+$/', $symbol) !== 1) {
        json_error('Unit validation failed.', 422, [
            'unit_symbol' => 'Use up to 30 letters, numbers, dot, slash, underscore or hyphen.',
        ]);
    }

    return $symbol;
}

function unit_ensure_unique(
    int $branchId,
    string $name,
    string $symbol,
    int $excludeId = 0
): void {
    $sql = 'SELECT id, unit_name, unit_symbol
            FROM food_units
            WHERE branch_id = :branch_id
              AND (unit_name = :unit_name OR unit_symbol = :unit_symbol)';
    $params = [
        ':branch_id' => $branchId,
        ':unit_name' => $name,
        ':unit_symbol' => $symbol,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row) return;

    $errors = [];

    if (strcasecmp((string)$row['unit_name'], $name) === 0) {
        $errors['unit_name'] = 'Unit Name already exists in this branch.';
    }

    if (strcasecmp((string)$row['unit_symbol'], $symbol) === 0) {
        $errors['unit_symbol'] = 'Unit Symbol already exists in this branch.';
    }

    json_error('Unit already exists.', 409, $errors ?: [
        'unit_name' => 'Use a unique Unit Name and Symbol.',
    ]);
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('unit.php', ACTION_VIEW);
    $context = unit_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options']) && (int)$_GET['options'] === 1) {
        json_success('Unit form options loaded.', [
            'next_unit_code' => unit_generate_code($branchId),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Unit record loaded.', [
            'unit' => unit_record($branchId, positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable']) && (int)$_GET['datatable'] === 1) {
        $draw = max(0, (int)($_GET['draw'] ?? 0));
        $start = max(0, (int)($_GET['start'] ?? 0));

        $lengthRaw = (int)($_GET['length'] ?? 25);
        $length = $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

        $search = trim((string)($_GET['search']['value'] ?? ''));

        $statusFilter =
            isset($_GET['status']) && $_GET['status'] !== ''
                ? normalize_status($_GET['status'])
                : null;

        $where = [
            'branch_id = :branch_id'
        ];

        $params = [
            ':branch_id' => $branchId
        ];

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(unit_code LIKE :search_code
                  OR unit_name LIKE :search_name
                  OR unit_symbol LIKE :search_symbol)';

            $params[':search_code'] = $like;
            $params[':search_name'] = $like;
            $params[':search_symbol'] = $like;
        }

        if ($statusFilter !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        }

        $bindParams = static function (PDOStatement $stmt, array $values): void {
            foreach ($values as $key => $value) {
                $stmt->bindValue(
                    $key,
                    $value,
                    in_array($key, [':branch_id', ':status'], true)
                        ? PDO::PARAM_INT
                        : PDO::PARAM_STR
                );
            }
        };

        $total = db()->prepare(
            'SELECT COUNT(*)
             FROM food_units
             WHERE branch_id = :branch_id'
        );
        $total->execute([
            ':branch_id' => $branchId
        ]);
        $recordsTotal = (int)$total->fetchColumn();

        $count = db()->prepare(
            'SELECT COUNT(*)
             FROM food_units
             WHERE ' . implode(' AND ', $where)
        );
        $bindParams($count, $params);
        $count->execute();
        $recordsFiltered = (int)$count->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_units,
                COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END),0) AS active_units,
                COALESCE(SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END),0) AS inactive_units
             FROM food_units
             WHERE ' . implode(' AND ', $where)
        );
        $bindParams($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            'unit_code',
            'unit_name',
            'unit_symbol',
            'status'
        ];

        $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderBy =
            $columns[$orderColumn] ??
            'unit_code';

        $sql =
            'SELECT
                id,
                unit_code,
                unit_name,
                unit_symbol,
                status
             FROM food_units
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $orderBy . ' ' . $orderDir . ', id ASC
             LIMIT :start, :length';

        $stmt = db()->prepare($sql);
        $bindParams($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'unit_code' => (string)$row['unit_code'],
                'unit_name' => (string)$row['unit_name'],
                'unit_symbol' => (string)$row['unit_symbol'],
                'status' => (int)$row['status'],
            ];
        }

        json_success('Unit records loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_units' => (int)($summary['total_units'] ?? 0),
                'active_units' => (int)($summary['active_units'] ?? 0),
                'inactive_units' => (int)($summary['inactive_units'] ?? 0),
            ],
            'allowed_actions' => $access['actions'],
        ]);
    }

    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;

    $sql = 'SELECT id, unit_code, unit_name, unit_symbol, status
            FROM food_units
            WHERE branch_id = :branch_id';

    if ($onlyActive) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' ORDER BY unit_name ASC, unit_symbol ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Units loaded.', [
        'units' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    $isUpdate = $method === 'PUT';
    $access = require_permission('unit.php', $isUpdate ? ACTION_UPDATE : ACTION_CREATE);
    $user = $access['user'];
    $context = unit_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, [
        'unit_name' => 'Unit Name is required.',
        'unit_symbol' => 'Unit Symbol is required.',
    ]);

    $id = $isUpdate ? positive_id($data['id'] ?? 0) : 0;
    $old = $isUpdate ? unit_record($branchId, $id) : null;

    $name = unit_clean_name($data['unit_name']);
    $symbol = unit_clean_symbol($data['unit_symbol']);
    unit_ensure_unique($branchId, $name, $symbol, $id);

    if ($isUpdate) {
        $stmt = db()->prepare(
            'UPDATE food_units
             SET unit_name = :unit_name,
                 unit_symbol = :unit_symbol,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );
        $stmt->execute([
            ':unit_name' => $name,
            ':unit_symbol' => $symbol,
            ':id' => $id,
            ':branch_id' => $branchId,
        ]);

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]);

        json_success('Unit updated successfully.', [
            'unit' => unit_record($branchId, $id),
        ]);
    }

    $code = unit_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_units
             (branch_id, unit_code, unit_name, unit_symbol, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :unit_code, :unit_name, :unit_symbol, 1, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':unit_code' => $code,
            ':unit_name' => $name,
            ':unit_symbol' => $symbol,
            ':created_by' => (int)$user['id'],
        ]);

        $newId = (int)db()->lastInsertId();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $newId,
        ]);

        json_success('Unit created successfully.', [
            'unit_id' => $newId,
            'unit' => unit_record($branchId, $newId),
        ], 201);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            unit_ensure_unique($branchId, $name, $symbol);
            json_error('Unit already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id', 'status']);

    $id = positive_id($data['id']);
    $status = normalize_status($data['status']);

    $access = require_permission('unit.php', $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE);
    $user = $access['user'];
    $context = unit_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    $old = unit_record($branchId, $id);

    db()->prepare(
        'UPDATE food_units
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id'
    )->execute([
        ':status' => $status,
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success($status === 1 ? 'Unit activated.' : 'Unit deactivated.');
}

json_error('Method not allowed.', 405);
