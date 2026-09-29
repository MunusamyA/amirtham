<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function seller_type_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Seller Type management is available only for tenant users.', 403);
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

function seller_type_require_schema(): void
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => 'food_seller_types']);

    if ((int)$stmt->fetchColumn() !== 1) {
        json_error('Seller Type database update is required.', 500, [
            'schema' => 'Run seller-type-master-upgrade.sql first.',
        ]);
    }
}

function seller_type_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Seller Type reference is required.', 422, [
            'ref' => 'Seller Type reference is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'seller_type');
    } catch (Throwable $exception) {
        json_error('Invalid Seller Type reference.', 422, [
            'ref' => 'Invalid Seller Type reference.',
        ]);
    }

    return 0;
}

function seller_type_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT st.id, st.branch_id, st.seller_type_name, st.sort_order, st.status,
                st.created_by, st.updated_by, st.created_at, st.updated_at,
                cu.name AS created_by_name,
                uu.name AS updated_by_name,
                (SELECT COUNT(*)
                 FROM food_product_sale_prices ps
                 WHERE ps.branch_id = st.branch_id
                   AND ps.seller_type_id = st.id) AS product_price_count
         FROM food_seller_types st
         LEFT JOIN users cu ON cu.id = st.created_by
         LEFT JOIN users uu ON uu.id = st.updated_by
         WHERE st.id = :id
           AND st.branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Seller Type was not found.', 404);
    }

    foreach (['id', 'branch_id', 'sort_order', 'status', 'created_by', 'product_price_count'] as $key) {
        $row[$key] = (int)$row[$key];
    }
    $row['updated_by'] = $row['updated_by'] === null ? null : (int)$row['updated_by'];
    $row['ref'] = encryptReference('seller_type', (int)$row['id']);

    return $row;
}

function seller_type_clean_name($value): string
{
    $name = preg_replace('/\s+/', ' ', trim((string)$value));
    if ($name === '' || strlen($name) > 120) {
        json_error('Seller Type validation failed.', 422, [
            'seller_type_name' => 'Seller Type Name is required and must be within 120 characters.',
        ]);
    }
    return $name;
}

function seller_type_clean_sort_order($value): int
{
    if ($value === '' || $value === null) return 0;
    if (!is_numeric($value)) {
        json_error('Seller Type validation failed.', 422, [
            'sort_order' => 'Sort Order must be a whole number.',
        ]);
    }
    return max(0, (int)$value);
}

function seller_type_assert_unique_name(int $branchId, string $name, int $excludeId = 0): void
{
    $sql = 'SELECT id
            FROM food_seller_types
            WHERE branch_id = :branch_id
              AND seller_type_name = :seller_type_name';
    $params = [
        ':branch_id' => $branchId,
        ':seller_type_name' => $name,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        json_error('Seller Type already exists.', 409, [
            'seller_type_name' => 'This Seller Type Name is already in use.',
        ]);
    }
}

$method = request_method();
seller_type_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('seller-type-form.php', ACTION_VIEW);
    $context = seller_type_tenant_context($access['user']);

    json_success('Seller Type form options loaded.', [
        'allowed_actions' => $access['actions'],
        'branch' => $context,
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('seller-type-form.php', ACTION_VIEW);
    $context = seller_type_tenant_context($access['user']);
    $row = seller_type_record(
        (int)$context['branch_id'],
        seller_type_id_from_reference($_GET['ref'])
    );

    json_success('Seller Type loaded.', [
        'seller_type' => $row,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['list'])) {
    $access = require_permission('seller-type-list.php', ACTION_VIEW);
    $context = seller_type_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    $search = trim((string)($_GET['search'] ?? ''));
    $status = isset($_GET['status']) && $_GET['status'] !== ''
        ? normalize_status($_GET['status'])
        : null;

    $where = ['st.branch_id = :branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = 'st.seller_type_name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status !== null) {
        $where[] = 'st.status = :status';
        $params[':status'] = $status;
    }

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM food_seller_types st
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, $key === ':branch_id' || $key === ':status' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_seller_types,
            COALESCE(SUM(CASE WHEN st.status = 1 THEN 1 ELSE 0 END), 0) AS active_seller_types,
            COALESCE(SUM(CASE WHEN st.status = 0 THEN 1 ELSE 0 END), 0) AS inactive_seller_types
         FROM food_seller_types st
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            $key === ':branch_id' || $key === ':status'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = db()->prepare(
        'SELECT st.id, st.seller_type_name, st.sort_order, st.status, st.created_at, st.updated_at,
                (SELECT COUNT(*)
                 FROM food_product_sale_prices ps
                 WHERE ps.branch_id = st.branch_id
                   AND ps.seller_type_id = st.id) AS product_price_count
         FROM food_seller_types st
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY st.sort_order ASC, st.seller_type_name ASC, st.id ASC
         LIMIT :offset, :limit'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':branch_id' || $key === ':status' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        $rows[] = [
            'id' => $id,
            'ref' => encryptReference('seller_type', $id),
            'edit_url' => 'seller-type-form.php?ref=' . rawurlencode(encryptReference('seller_type', $id)),
            'seller_type_name' => (string)$row['seller_type_name'],
            'sort_order' => (int)$row['sort_order'],
            'status' => (int)$row['status'],
            'product_price_count' => (int)$row['product_price_count'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages && $total > 0) $page = $pages;

    $formActions = effective_actions_for_menu(
        $access['user'],
        menu_by_path('seller-type-form.php')
    );

    json_success('Seller Types loaded.', [
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
        ],
        'summary' => [
            'total_seller_types' => (int)($summary['total_seller_types'] ?? 0),
            'active_seller_types' => (int)($summary['active_seller_types'] ?? 0),
            'inactive_seller_types' => (int)($summary['inactive_seller_types'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'form_actions' => $formActions,
    ]);
}

if ($method === 'POST') {
    $access = require_permission('seller-type-form.php', ACTION_CREATE);
    $user = $access['user'];
    $context = seller_type_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $name = seller_type_clean_name($data['seller_type_name'] ?? '');
    $sortOrder = seller_type_clean_sort_order($data['sort_order'] ?? 0);
    $status = isset($data['status']) ? normalize_status($data['status']) : 1;
    seller_type_assert_unique_name($branchId, $name);

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_seller_types
             (branch_id, seller_type_name, sort_order, status, created_by, updated_by, created_at, updated_at)
             VALUES
             (:branch_id, :seller_type_name, :sort_order, :status, :created_by, :updated_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':seller_type_name' => $name,
            ':sort_order' => $sortOrder,
            ':status' => $status,
            ':created_by' => (int)$user['id'],
            ':updated_by' => (int)$user['id'],
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            json_error('Seller Type already exists.', 409, [
                'seller_type_name' => 'This Seller Type Name is already in use.',
            ]);
        }
        throw $exception;
    }

    $id = (int)db()->lastInsertId();
    audit_log((int)$user['id'], ACTION_CREATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
    ]);

    json_success('Seller Type created successfully.', [
        'seller_type' => seller_type_record($branchId, $id),
    ], 201);
}

if ($method === 'PUT') {
    $access = require_permission('seller-type-form.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = seller_type_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $id = seller_type_id_from_reference($data['ref'] ?? '');
    $old = seller_type_record($branchId, $id);
    $name = seller_type_clean_name($data['seller_type_name'] ?? '');
    $sortOrder = seller_type_clean_sort_order($data['sort_order'] ?? 0);
    $status = isset($data['status']) ? normalize_status($data['status']) : (int)$old['status'];
    seller_type_assert_unique_name($branchId, $name, $id);

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE food_seller_types
             SET seller_type_name = :seller_type_name,
                 sort_order = :sort_order,
                 status = :status,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );
        $stmt->execute([
            ':seller_type_name' => $name,
            ':sort_order' => $sortOrder,
            ':status' => $status,
            ':updated_by' => (int)$user['id'],
            ':id' => $id,
            ':branch_id' => $branchId,
        ]);

        // Keep legacy seller_type_name snapshot synchronized for current Product pricing.
        $pdo->prepare(
            'UPDATE food_product_sale_prices
             SET seller_type_name = :seller_type_name,
                 updated_at = NOW()
             WHERE branch_id = :branch_id
               AND seller_type_id = :seller_type_id'
        )->execute([
            ':seller_type_name' => $name,
            ':branch_id' => $branchId,
            ':seller_type_id' => $id,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Seller Type already exists.', 409, [
                'seller_type_name' => 'This Seller Type Name is already in use.',
            ]);
        }
        throw $exception;
    }

    $new = seller_type_record($branchId, $id);
    audit_log((int)$user['id'], ACTION_UPDATE, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
        'new_data' => $new,
    ]);

    json_success('Seller Type updated successfully.', [
        'seller_type' => $new,
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, [
        'ref' => 'Seller Type reference is required.',
        'action' => 'Seller Type status action is required.',
    ]);

    $action = strtolower(trim((string)$data['action']));
    if (!in_array($action, ['activate', 'deactivate'], true)) {
        json_error('Invalid Seller Type status action.', 422);
    }

    $newStatus = $action === 'activate' ? 1 : 0;
    $permission = $newStatus === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;
    $access = require_permission('seller-type-list.php', $permission);
    $user = $access['user'];
    $context = seller_type_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $id = seller_type_id_from_reference($data['ref']);
    $old = seller_type_record($branchId, $id);

    db()->prepare(
        'UPDATE food_seller_types
         SET status = :status,
             updated_by = :updated_by,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id'
    )->execute([
        ':status' => $newStatus,
        ':updated_by' => (int)$user['id'],
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $permission, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success($newStatus === 1
        ? 'Seller Type activated successfully.'
        : 'Seller Type deactivated successfully.'
    );
}

json_error('Unsupported Seller Type request.', 405);
