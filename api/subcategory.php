<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function subcategory_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Subcategory management is available only for tenant users.', 403);
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

function subcategory_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT category_code
         FROM food_product_categories
         WHERE branch_id = :branch_id
           AND parent_id IS NOT NULL
           AND category_code REGEXP '^SUB[0-9]+$'
         ORDER BY CAST(SUBSTRING(category_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string)($stmt->fetchColumn() ?: '');
    $nextNumber = 1;
    if ($lastCode !== '' && preg_match('/^SUB([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return 'SUB' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function subcategory_validate_name($value): string
{
    $name = trim((string)$value);
    if ($name === '') {
        json_error('Subcategory name is required.', 422, [
            'category_name' => 'Subcategory name is required.',
        ]);
    }
    if (strlen($name) > 120) {
        json_error('Subcategory name is too long.', 422, [
            'category_name' => 'Subcategory name must be within 120 characters.',
        ]);
    }
    return $name;
}

function subcategory_validate_description($value): ?string
{
    $description = trim((string)$value);
    if ($description === '') return null;
    if (strlen($description) > 255) {
        json_error('Subcategory description is too long.', 422, [
            'description' => 'Description must be within 255 characters.',
        ]);
    }
    return $description;
}

function subcategory_parent_record(int $branchId, int $parentId, bool $requireActive = true): array
{
    $sql = 'SELECT id, category_code, category_name, status
            FROM food_product_categories
            WHERE id = :id
              AND branch_id = :branch_id
              AND parent_id IS NULL';
    if ($requireActive) $sql .= ' AND status = 1';
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $parentId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        json_error($requireActive ? 'Select an active Category.' : 'Selected Category was not found.', 422, [
            'parent_id' => $requireActive ? 'Select an active Category.' : 'Selected Category was not found.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function subcategory_record(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT s.id, s.branch_id, s.parent_id, s.category_code, s.category_name, s.description, s.status,
                s.created_by, s.created_at, s.updated_at,
                p.category_code AS parent_category_code,
                p.category_name AS parent_category_name,
                p.status AS parent_status
         FROM food_product_categories s
         INNER JOIN food_product_categories p ON p.id = s.parent_id
         WHERE s.id = :id
           AND s.branch_id = :branch_id
           AND s.parent_id IS NOT NULL
           AND p.branch_id = s.branch_id
           AND p.parent_id IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        json_error('Subcategory was not found in your branch.', 404);
    }

    foreach (['id','branch_id','parent_id','status','parent_status'] as $key) {
        $row[$key] = (int)$row[$key];
    }
    return $row;
}

function subcategory_assert_unique_name(int $branchId, int $parentId, string $name, int $excludeId = 0): void
{
    $sql = 'SELECT id
            FROM food_product_categories
            WHERE branch_id = :branch_id
              AND parent_id = :parent_id
              AND LOWER(category_name) = LOWER(:category_name)';
    $params = [
        ':branch_id' => $branchId,
        ':parent_id' => $parentId,
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
        json_error('Subcategory name already exists under this Category.', 409, [
            'category_name' => 'Use a unique Subcategory name for the selected Category.',
        ]);
    }
}

function subcategory_parent_options(int $branchId, int $includeParentId = 0): array
{
    $sql = 'SELECT id, category_code, category_name, status
            FROM food_product_categories
            WHERE branch_id = :branch_id
              AND parent_id IS NULL
              AND (status = 1';
    $params = [':branch_id' => $branchId];

    if ($includeParentId > 0) {
        $sql .= ' OR id = :include_parent_id';
        $params[':include_parent_id'] = $includeParentId;
    }

    $sql .= ') ORDER BY category_name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    return $rows;
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('subcategory.php', ACTION_VIEW);
    $user = $access['user'];
    $context = subcategory_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    if (isset($_GET['options'])) {
        $includeParentId = isset($_GET['include_parent_id']) && $_GET['include_parent_id'] !== ''
            ? max(0, (int)$_GET['include_parent_id'])
            : 0;

        json_success('Subcategory form options loaded.', [
            'next_subcategory_code' => subcategory_generate_code($branchId),
            'categories' => subcategory_parent_options($branchId, $includeParentId),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['id'])) {
        json_success('Subcategory loaded.', [
            'subcategory' => subcategory_record($branchId, positive_id($_GET['id'])),
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

        $categoryFilter =
            isset($_GET['category_id']) && $_GET['category_id'] !== ''
                ? max(0, (int)$_GET['category_id'])
                : 0;

        $baseWhere = [
            's.branch_id = :branch_id',
            's.parent_id IS NOT NULL',
            'p.branch_id = s.branch_id',
            'p.parent_id IS NULL',
        ];

        $where = $baseWhere;

        $params = [
            ':branch_id' => $branchId
        ];

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(s.category_code LIKE :search_sub_code
                  OR s.category_name LIKE :search_sub_name
                  OR COALESCE(s.description, \'\') LIKE :search_description
                  OR p.category_name LIKE :search_parent_name
                  OR p.category_code LIKE :search_parent_code)';

            $params[':search_sub_code'] = $like;
            $params[':search_sub_name'] = $like;
            $params[':search_description'] = $like;
            $params[':search_parent_name'] = $like;
            $params[':search_parent_code'] = $like;
        }

        if ($statusFilter !== null) {
            $where[] = 's.status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($categoryFilter > 0) {
            $where[] = 's.parent_id = :category_id';
            $params[':category_id'] = $categoryFilter;
        }

        $from =
            ' FROM food_product_categories s
              INNER JOIN food_product_categories p ON p.id = s.parent_id';

        $bindParams = static function (PDOStatement $stmt, array $values): void {
            foreach ($values as $key => $value) {
                $stmt->bindValue(
                    $key,
                    $value,
                    in_array(
                        $key,
                        [':branch_id', ':status', ':category_id'],
                        true
                    )
                        ? PDO::PARAM_INT
                        : PDO::PARAM_STR
                );
            }
        };

        $totalStmt = db()->prepare(
            'SELECT COUNT(*)' .
            $from .
            ' WHERE ' . implode(' AND ', $baseWhere)
        );
        $totalStmt->execute([
            ':branch_id' => $branchId
        ]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*)' .
            $from .
            ' WHERE ' . implode(' AND ', $where)
        );
        $bindParams($filteredStmt, $params);
        $filteredStmt->execute();
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_subcategories,
                COALESCE(SUM(CASE WHEN s.status = 1 THEN 1 ELSE 0 END),0) AS active_subcategories,
                COALESCE(SUM(CASE WHEN s.status = 0 THEN 1 ELSE 0 END),0) AS inactive_subcategories,
                COUNT(DISTINCT s.parent_id) AS category_count' .
            $from .
            ' WHERE ' . implode(' AND ', $where)
        );
        $bindParams($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            's.category_code',
            's.category_name',
            'p.category_name',
            's.description',
            's.status'
        ];

        $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderBy =
            $columns[$orderColumn] ??
            's.category_code';

        $sql =
            'SELECT
                s.id,
                s.category_code,
                s.category_name,
                s.description,
                s.status,
                s.parent_id,
                p.category_code AS parent_category_code,
                p.category_name AS parent_category_name,
                p.status AS parent_status' .
            $from .
            ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . $orderBy . ' ' . $orderDir . ', s.id ASC
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
                'parent_id' => (int)$row['parent_id'],
                'parent_category_code' => (string)$row['parent_category_code'],
                'parent_category_name' => (string)$row['parent_category_name'],
                'parent_status' => (int)$row['parent_status'],
            ];
        }

        $filterStmt = db()->prepare(
            'SELECT id, category_name
             FROM food_product_categories
             WHERE branch_id = :branch_id
               AND parent_id IS NULL
             ORDER BY category_name ASC, id ASC'
        );
        $filterStmt->execute([
            ':branch_id' => $branchId
        ]);

        json_success('Subcategory records loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_subcategories' => (int)($summary['total_subcategories'] ?? 0),
                'active_subcategories' => (int)($summary['active_subcategories'] ?? 0),
                'inactive_subcategories' => (int)($summary['inactive_subcategories'] ?? 0),
                'category_count' => (int)($summary['category_count'] ?? 0),
            ],
            'filter_categories' => $filterStmt->fetchAll(PDO::FETCH_ASSOC),
            'allowed_actions' => $access['actions'],
        ]);
    }

    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
        ? max(0, (int)$_GET['category_id'])
        : 0;
    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;

    $where = [
        's.branch_id = :branch_id',
        's.parent_id IS NOT NULL',
        'p.branch_id = s.branch_id',
        'p.parent_id IS NULL',
    ];
    $params = [':branch_id' => $branchId];

    if ($categoryId > 0) {
        $where[] = 's.parent_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($onlyActive) {
        $where[] = 's.status = 1';
        $where[] = 'p.status = 1';
    }

    $sql = 'SELECT s.id, s.parent_id, s.category_code, s.category_name, s.description, s.status,
                   p.category_code AS parent_category_code,
                   p.category_name AS parent_category_name
            FROM food_product_categories s
            INNER JOIN food_product_categories p ON p.id = s.parent_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.category_name ASC, s.category_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = (int)$row['parent_id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    json_success('Subcategories loaded.', [
        'subcategories' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('subcategory.php', ACTION_CREATE);
    $user = $access['user'];
    $context = subcategory_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['parent_id']);
    $parentId = positive_id($data['parent_id']);
    subcategory_parent_record($branchId, $parentId, true);

    $name = subcategory_validate_name($data['category_name'] ?? '');
    $description = subcategory_validate_description($data['description'] ?? '');
    subcategory_assert_unique_name($branchId, $parentId, $name);
    $code = subcategory_generate_code($branchId);

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_product_categories
             (branch_id, parent_id, category_code, category_name, description, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :parent_id, :category_code, :category_name, :description, 1, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':parent_id' => $parentId,
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

        json_success('Subcategory created successfully.', [
            'subcategory' => subcategory_record($branchId, $newId),
        ], 201);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Subcategory code already exists in your branch.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('subcategory.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = subcategory_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, ['id','parent_id']);
    $id = positive_id($data['id']);
    $parentId = positive_id($data['parent_id']);
    $old = subcategory_record($branchId, $id);

    $requireActiveParent = $parentId !== (int)$old['parent_id'];
    subcategory_parent_record($branchId, $parentId, $requireActiveParent);

    $name = subcategory_validate_name($data['category_name'] ?? '');
    $description = subcategory_validate_description($data['description'] ?? '');
    subcategory_assert_unique_name($branchId, $parentId, $name, $id);

    $stmt = db()->prepare(
        'UPDATE food_product_categories
         SET parent_id = :parent_id,
             category_name = :category_name,
             description = :description,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id
           AND parent_id IS NOT NULL'
    );

    $stmt->execute([
        ':parent_id' => $parentId,
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

    json_success('Subcategory updated successfully.', [
        'subcategory' => subcategory_record($branchId, $id),
    ]);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id','status']);

    $id = positive_id($data['id']);
    $status = normalize_status($data['status']);
    $access = require_permission('subcategory.php', $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE);
    $user = $access['user'];
    $context = subcategory_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $old = subcategory_record($branchId, $id);

    if ($status === 1) {
        subcategory_parent_record($branchId, (int)$old['parent_id'], true);
    }

    db()->prepare(
        'UPDATE food_product_categories
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id
           AND parent_id IS NOT NULL'
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

    json_success($status === 1 ? 'Subcategory activated.' : 'Subcategory deactivated.');
}

json_error('Method not allowed.', 405);
