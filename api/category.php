<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function category_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Category management is available only for tenant users.', 403);
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

function category_nullable($value): ?string
{
    if ($value === null) return null;
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function category_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT category_code
         FROM food_product_categories
         WHERE branch_id = :branch_id
           AND parent_id IS NULL
           AND category_code REGEXP '^CAT[0-9]+$'
         ORDER BY CAST(SUBSTRING(category_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string)($stmt->fetchColumn() ?: '');
    $nextNumber = 1;
    if ($lastCode !== '' && preg_match('/^CAT([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return 'CAT' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function category_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, category_code, category_name, description, status,
                created_by, created_at, updated_at
         FROM food_product_categories
         WHERE id = :id
           AND branch_id = :branch_id
           AND parent_id IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        json_error('Category was not found in your branch.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = (int)$row['branch_id'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function category_validate_name($value): string
{
    $name = trim((string)$value);
    if ($name === '') {
        json_error('Category name is required.', 422, [
            'category_name' => 'Category name is required.',
        ]);
    }
    if (strlen($name) > 120) {
        json_error('Category name is too long.', 422, [
            'category_name' => 'Category name must be within 120 characters.',
        ]);
    }
    return $name;
}

function category_validate_description($value): ?string
{
    $description = trim((string)$value);
    if ($description === '') return null;
    if (strlen($description) > 255) {
        json_error('Category description is too long.', 422, [
            'description' => 'Description must be within 255 characters.',
        ]);
    }
    return $description;
}

function category_assert_unique_name(int $branchId, string $name, int $excludeId = 0): void
{
    $sql = 'SELECT id
            FROM food_product_categories
            WHERE branch_id = :branch_id
              AND parent_id IS NULL
              AND LOWER(category_name) = LOWER(:category_name)';
    $params = [
        ':branch_id' => $branchId,
        ':category_name' => $name,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Category name already exists in your branch.', 409, [
            'category_name' => 'Use a unique category name.',
        ]);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('category.php', ACTION_VIEW);
    $user = $access['user'];
    $context = category_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options'])) {
        json_success('Category form options loaded.', [
            'next_category_code' => category_generate_code($branchId),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Category loaded.', [
            'category' => category_record($branchId, positive_id($_GET['id'])),
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

        $baseWhere = [
            'c.branch_id = :branch_id',
            'c.parent_id IS NULL'
        ];

        $where = $baseWhere;
        $params = [
            ':branch_id' => $branchId
        ];

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(c.category_code LIKE :search_code
                  OR c.category_name LIKE :search_name
                  OR COALESCE(c.description, \'\') LIKE :search_description)';

            $params[':search_code'] = $like;
            $params[':search_name'] = $like;
            $params[':search_description'] = $like;
        }

        if ($statusFilter !== null) {
            $where[] = 'c.status = :status';
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

        $totalStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM food_product_categories c
             WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([
            ':branch_id' => $branchId
        ]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM food_product_categories c
             WHERE ' . implode(' AND ', $where)
        );
        $bindParams($filteredStmt, $params);
        $filteredStmt->execute();
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_categories,
                COALESCE(SUM(CASE WHEN c.status = 1 THEN 1 ELSE 0 END),0) AS active_categories,
                COALESCE(SUM(CASE WHEN c.status = 0 THEN 1 ELSE 0 END),0) AS inactive_categories,
                COALESCE(SUM(
                    (
                        SELECT COUNT(*)
                        FROM food_product_categories s
                        WHERE s.branch_id = c.branch_id
                          AND s.parent_id = c.id
                    )
                ),0) AS subcategory_count
             FROM food_product_categories c
             WHERE ' . implode(' AND ', $where)
        );
        $bindParams($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            'c.category_code',
            'c.category_name',
            'c.description',
            'c.status'
        ];

        $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderBy =
            $columns[$orderColumn] ??
            'c.category_code';

        $sql =
            'SELECT
                c.id,
                c.category_code,
                c.category_name,
                c.description,
                c.status
             FROM food_product_categories c
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $orderBy . ' ' . $orderDir . ', c.id ASC
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
                'category_code' => (string)$row['category_code'],
                'category_name' => (string)$row['category_name'],
                'description' => $row['description'],
                'status' => (int)$row['status'],
            ];
        }

        json_success('Category records loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_categories' => (int)($summary['total_categories'] ?? 0),
                'active_categories' => (int)($summary['active_categories'] ?? 0),
                'inactive_categories' => (int)($summary['inactive_categories'] ?? 0),
                'subcategory_count' => (int)($summary['subcategory_count'] ?? 0),
            ],
            'allowed_actions' => $access['actions'],
        ]);
    }

    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;
    $sql = 'SELECT id, category_code, category_name, description, status
            FROM food_product_categories
            WHERE branch_id = :branch_id
              AND parent_id IS NULL';
    if ($onlyActive) $sql .= ' AND status = 1';
    $sql .= ' ORDER BY category_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Categories loaded.', [
        'categories' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('category.php', ACTION_CREATE);
    $user = $access['user'];
    $context = category_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    $name = category_validate_name($data['category_name'] ?? '');
    $description = category_validate_description($data['description'] ?? '');
    category_assert_unique_name($branchId, $name);
    $code = category_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_product_categories
             (branch_id, parent_id, category_code, category_name, description, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, NULL, :category_code, :category_name, :description, 1, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':category_code' => $code,
            ':category_name' => $name,
            ':description' => $description,
            ':created_by' => (int)$user['id'],
        ]);

        $newId = (int)db()->lastInsertId();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $newId,
        ]);

        json_success('Category created successfully.', [
            'category' => category_record($branchId, $newId),
        ], 201);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Category code already exists in your branch.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('category.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = category_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['id']);
    $id = positive_id($data['id']);
    $old = category_record($branchId, $id);

    $name = category_validate_name($data['category_name'] ?? '');
    $description = category_validate_description($data['description'] ?? '');
    category_assert_unique_name($branchId, $name, $id);

    $stmt = db()->prepare(
        'UPDATE food_product_categories
         SET category_name = :category_name,
             description = :description,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id
           AND parent_id IS NULL'
    );

    $stmt->execute([
        ':category_name' => $name,
        ':description' => $description,
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

    json_success('Category updated successfully.', [
        'category' => category_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id','status']);

    $id = positive_id($data['id']);
    $status = normalize_status($data['status']);
    $access = require_permission('category.php', $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE);
    $user = $access['user'];
    $context = category_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $old = category_record($branchId, $id);

    db()->prepare(
        'UPDATE food_product_categories
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id
           AND parent_id IS NULL'
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

    json_success($status === 1 ? 'Category activated.' : 'Category deactivated.');
}

json_error('Method not allowed.', 405);
