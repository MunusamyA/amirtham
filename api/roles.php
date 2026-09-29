<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function role_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted role reference is required.', 422, [
            'ref' => 'Role reference is required.',
        ]);
        return 0;
    }
    try {
        return decryptReference(trim($value), 'role');
    } catch (Throwable $exception) {
        json_error('Invalid or changed role reference.', 422, [
            'ref' => 'The role reference is invalid.',
        ]);
        return 0;
    }
}

function role_record_for_user(array $user, int $roleId): array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.name AS created_by_name, c.company_name
         FROM roles r
         LEFT JOIN users u ON u.id = r.created_by
         LEFT JOIN companies c ON c.id = r.company_id
         WHERE r.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Role was not found.', 404);
    }

    if ((int) $user['role_type'] === 2) {
        if (!in_array((int) $role['role_type'], [1, 2], true) || $role['company_id'] !== null) {
            json_error('You cannot access this role.', 403);
        }
    } elseif ((int) $role['company_id'] !== (int) $user['company_id'] &&
        (int) $role['id'] !== (int) $user['branch_plan_role_id']) {
        json_error('You cannot access this role.', 403);
    }
    return $role;
}

function public_role(
    array $role,
    int $currentRoleId,
    int $preferredRoleId = 0,
    string $preferredReference = ''
): array
{
    $roleId = (int) $role['id'];
    $reference = $roleId === $preferredRoleId && $preferredReference !== ''
        ? $preferredReference : encryptReference('role', $roleId);
    $role['ref'] = $reference;
    $role['is_current_role'] = $roleId === $currentRoleId;
    $role['edit_url'] = 'role-form.php?ref=' . rawurlencode($reference);
    $role['permission_url'] = 'permission-form.php?ref=' . rawurlencode($reference);
    return $role;
}

function posted_permissions(array $data): array
{
    $permissions = isset($data['permissions']) ? $data['permissions'] : [];
    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        $permissions = is_array($decoded) ? $decoded : [];
    }
    return is_array($permissions) ? $permissions : [];
}

function validate_platform_permissions(array $permissions): array
{
    $clean = [];
    foreach ($permissions as $permission) {
        if (!is_array($permission) || !isset($permission['menu_id'])) {
            json_error('Invalid permission format.', 422);
        }
        $menuId = positive_id($permission['menu_id'], 'menu_id');
        $stmt = db()->prepare('SELECT available_action_ids FROM menus WHERE id = :id AND status = 1');
        $stmt->execute([':id' => $menuId]);
        $menu = $stmt->fetch();
        if (!$menu) {
            json_error('Invalid menu selected.', 422);
        }
        $requested = normalize_csv_ids(isset($permission['action_ids']) ? $permission['action_ids'] : []);
        $available = array_values(array_intersect(
            normalize_csv_ids($menu['available_action_ids']),
            active_action_ids()
        ));
        if (array_diff($requested, $available) !== []) {
            json_error('A selected action is unavailable for this menu.', 422);
        }
        if ($requested !== []) {
            $clean[] = ['menu_id' => $menuId, 'action_ids' => implode(',', $requested)];
        }
    }
    return $clean;
}

function save_role_permissions(PDO $pdo, int $roleId, array $permissions): void
{
    $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')
        ->execute([':role_id' => $roleId]);
    $stmt = $pdo->prepare(
        'INSERT INTO role_permissions
         (role_id, menu_id, action_ids, status, created_at, updated_at)
         VALUES (:role_id, :menu_id, :action_ids, 1, NOW(), NOW())'
    );
    foreach ($permissions as $permission) {
        $stmt->execute([
            ':role_id' => $roleId,
            ':menu_id' => $permission['menu_id'],
            ':action_ids' => csv_ids($permission['action_ids']),
        ]);
    }
}

function validate_role_status_change(array $user, array $role, int $status): void
{
    if ($status === 1) {
        return;
    }

    if ((int) $role['id'] === (int) $user['role_id']) {
        json_error('You cannot deactivate your currently logged-in role.', 409);
    }

    if ((int) $role['role_type'] === 1) {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM branches WHERE role_id = :role_id AND status = 1'
        );
        $stmt->execute([':role_id' => (int) $role['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            json_error('This plan is assigned to an active branch and cannot be deactivated.', 409);
        }
    }
}

try {
$method = request_method();

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('role-list.php', ACTION_VIEW);
    $user = $access['user'];
    $role = role_record_for_user($user, role_id_from_reference($_GET['ref']));
    json_success('Role loaded.', [
        'role' => public_role($role, (int) $user['role_id']),
        'allowed_actions' => $access['actions'],
        'current_user' => [
            'id' => (int) $user['id'],
            'role_type' => (int) $user['role_type'],
            'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
        ],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('role-list.php', ACTION_VIEW);
    $user = $access['user'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));

    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));

    $search = trim((string)($_GET['search']['value'] ?? ''));
    $roleTypeRaw = trim((string)($_GET['role_type'] ?? ''));
    $statusRaw = trim((string)($_GET['status'] ?? ''));

    $baseFrom =
        ' FROM roles r
          LEFT JOIN users u ON u.id=r.created_by
          LEFT JOIN companies c ON c.id=r.company_id ';

    $baseWhere = [];
    $baseParams = [];

    if ((int)$user['role_type'] === 2) {
        $baseWhere[] = 'r.role_type IN (1,2)';
        $baseWhere[] = 'r.company_id IS NULL';
    } else {
        $baseWhere[] = '(r.company_id=:access_company_id OR r.id=:access_plan_role_id)';
        $baseParams[':access_company_id'] = (int)$user['company_id'];
        $baseParams[':access_plan_role_id'] = (int)$user['branch_plan_role_id'];
    }

    $where = $baseWhere;
    $params = $baseParams;

    if ($search !== '') {
        $like = '%' . $search . '%';

        $where[] =
            '(r.role_name LIKE :search_role
              OR COALESCE(c.company_name,\'Platform\') LIKE :search_owner
              OR COALESCE(u.name,\'\') LIKE :search_creator)';

        $params[':search_role'] = $like;
        $params[':search_owner'] = $like;
        $params[':search_creator'] = $like;
    }

    if ($roleTypeRaw !== '') {
        $roleType = (int)$roleTypeRaw;

        if (!in_array($roleType, [1,2,3], true)) {
            json_error('Invalid Role category.', 422);
        }

        $where[] = 'r.role_type=:filter_role_type';
        $params[':filter_role_type'] = $roleType;
    }

    if ($statusRaw !== '') {
        $status = normalize_status($statusRaw);
        $where[] = 'r.status=:filter_status';
        $params[':filter_status'] = $status;
    }

    $bind = static function (PDOStatement $stmt, array $values): void {
        foreach ($values as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    };

    $baseWhereSql =
        ' WHERE ' . implode(' AND ', $baseWhere);

    $whereSql =
        ' WHERE ' . implode(' AND ', $where);

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        $baseWhereSql
    );
    $bind($totalStmt, $baseParams);
    $totalStmt->execute();
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $filteredStmt = db()->prepare(
        'SELECT COUNT(*)' .
        $baseFrom .
        $whereSql
    );
    $bind($filteredStmt, $params);
    $filteredStmt->execute();
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_roles,
            COALESCE(SUM(CASE WHEN r.status=1 THEN 1 ELSE 0 END),0) AS active_roles,
            COALESCE(SUM(CASE WHEN r.status=0 THEN 1 ELSE 0 END),0) AS inactive_roles,
            COALESCE(SUM(CASE WHEN r.role_type=1 THEN 1 ELSE 0 END),0) AS plan_roles' .
        $baseFrom .
        $whereSql
    );
    $bind($summaryStmt, $params);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        0 => 'r.role_name',
        1 => 'r.role_type',
        2 => 'c.company_name',
        3 => 'r.status',
        4 => 'r.id',
    ];

    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir =
        strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
            ? 'DESC'
            : 'ASC';

    $orderColumn =
        $orderColumns[$orderIndex] ??
        'r.role_name';

    $sql =
        'SELECT r.*,u.name AS created_by_name,c.company_name' .
        $baseFrom .
        $whereSql .
        ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',r.id ASC
          LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    $bind($stmt, $params);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
        $row = public_role(
            $role,
            (int)$user['role_id']
        );

        $row['role_type'] = (int)$row['role_type'];
        $row['status'] = (int)$row['status'];
        $row['company_id'] =
            $row['company_id'] === null
                ? null
                : (int)$row['company_id'];

        $rows[] = $row;
    }

    json_success('Roles loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_roles' => (int)($summary['total_roles'] ?? 0),
            'active_roles' => (int)($summary['active_roles'] ?? 0),
            'inactive_roles' => (int)($summary['inactive_roles'] ?? 0),
            'plan_roles' => (int)($summary['plan_roles'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'current_user' => [
            'id' => (int)$user['id'],
            'role_type' => (int)$user['role_type'],
            'company_id' =>
                $user['company_id'] === null
                    ? null
                    : (int)$user['company_id'],
        ],
    ]);
}

if ($method === 'GET') {
    $access = require_permission('role-list.php', ACTION_VIEW);
    $user = $access['user'];
    $preferredRoleId = 0;
    $preferredReference = '';
    if (isset($_GET['selected_ref']) && trim((string) $_GET['selected_ref']) !== '') {
        $preferredReference = trim((string) $_GET['selected_ref']);
        $preferredRoleId = role_id_from_reference($preferredReference);
        role_record_for_user($user, $preferredRoleId);
    }

    if ((int) $user['role_type'] === 2) {
        $stmt = db()->query(
            'SELECT r.*, u.name AS created_by_name, c.company_name
             FROM roles r
             LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN companies c ON c.id = r.company_id
             WHERE r.role_type IN (1, 2) AND r.company_id IS NULL
             ORDER BY r.role_type ASC, r.role_name ASC'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT r.*, u.name AS created_by_name, c.company_name
             FROM roles r
             LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN companies c ON c.id = r.company_id
             WHERE r.company_id = :company_id OR r.id = :plan_role_id
             ORDER BY r.role_type ASC, r.role_name ASC'
        );
        $stmt->execute([
            ':company_id' => (int) $user['company_id'],
            ':plan_role_id' => (int) $user['branch_plan_role_id'],
        ]);
    }
    $roles = [];
    foreach ($stmt->fetchAll() as $role) {
        $roles[] = public_role(
            $role,
            (int) $user['role_id'],
            $preferredRoleId,
            $preferredReference
        );
    }
    json_success('Roles loaded.', [
        'roles' => $roles,
        'allowed_actions' => $access['actions'],
        'current_user' => [
            'id' => (int) $user['id'],
            'role_type' => (int) $user['role_type'],
            'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
        ],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('role-list.php', ACTION_CREATE);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, [
        'role_name' => 'Role name is required.',
    ]);

    if ((int) $user['role_type'] === 2) {
        $roleType = isset($data['role_type']) ? (int) $data['role_type'] : 0;
        if (!in_array($roleType, [1, 2], true)) {
            json_error('Select Plan or Platform Role.', 422, [
                'role_type' => 'Allowed values are 1 for Plan and 2 for Platform Role.',
            ]);
        }
        $companyId = null;
        $permissions = validate_platform_permissions(posted_permissions($data));
    } else {
        $roleType = 3;
        $companyId = (int) $user['company_id'];
        $permissions = validate_branch_role_permissions(
            (int) $user['branch_id'],
            posted_permissions($data)
        );
    }

    $status = isset($data['status']) ? normalize_status($data['status']) : 1;

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO roles
             (company_id, role_name, role_type, status, created_by, created_at, updated_at)
             VALUES (:company_id, :role_name, :role_type, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':role_name' => trim((string) $data['role_name']),
            ':role_type' => $roleType,
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);
        $roleId = (int) $pdo->lastInsertId();
        save_role_permissions($pdo, $roleId, $permissions);
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_CREATE, [
            'company_id' => $companyId,
            'branch_id' => $user['branch_id'],
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $roleId,
        ]);
        $reference = encryptReference('role', $roleId);
        json_success('Role created successfully.', [
            'role_ref' => $reference,
            'edit_url' => 'role-form.php?ref=' . rawurlencode($reference),
            'permission_url' => 'permission-form.php?ref=' . rawurlencode($reference),
        ], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('role-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, [
        'ref' => 'Role reference is required.',
        'role_name' => 'Role name is required.',
    ]);
    $roleId = role_id_from_reference($data['ref']);

    $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Role was not found.', 404);
    }

    if ((int) $user['role_type'] === 2) {
        if (!in_array((int) $role['role_type'], [1, 2], true) || $role['company_id'] !== null) {
            json_error('This role cannot be updated by a platform user.', 403);
        }
        $permissions = array_key_exists('permissions', $data)
            ? validate_platform_permissions(posted_permissions($data)) : null;
    } else {
        if ((int) $role['role_type'] !== 3 || (int) $role['company_id'] !== (int) $user['company_id']) {
            json_error('Only tenant-created roles can be updated.', 403);
        }
        $permissions = array_key_exists('permissions', $data)
            ? validate_branch_role_permissions(
                (int) $user['branch_id'],
                posted_permissions($data)
            ) : null;
    }

    $status = isset($data['status']) ? normalize_status($data['status']) : (int) $role['status'];
    validate_role_status_change($user, $role, $status);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE roles SET role_name = :name, status = :status, updated_at = NOW() WHERE id = :id'
        )->execute([
            ':name' => trim((string) $data['role_name']),
            ':status' => $status,
            ':id' => $roleId,
        ]);
        if ($permissions !== null) {
            save_role_permissions($pdo, $roleId, $permissions);
        }
        $pdo->commit();
        audit_log((int) $user['id'], ACTION_UPDATE, [
            'company_id' => $role['company_id'],
            'branch_id' => $user['branch_id'],
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $roleId,
        ]);
        json_success('Role updated successfully.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $access = require_permission('role-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, [
        'ref' => 'Role reference is required.',
        'status' => 'Status is required.',
    ]);
    $roleId = role_id_from_reference($data['ref']);

    $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Role was not found.', 404);
    }
    if ((int) $user['role_type'] === 2) {
        if (!in_array((int) $role['role_type'], [1, 2], true) || $role['company_id'] !== null) {
            json_error('Platform users can change only Plans and Platform Roles.', 403);
        }
    } elseif ((int) $role['role_type'] !== 3 ||
        (int) $role['company_id'] !== (int) $user['company_id']) {
        json_error('Only tenant-created roles can be changed.', 403);
    }

    $status = normalize_status($data['status']);
    validate_role_status_change($user, $role, $status);

    db()->prepare('UPDATE roles SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute([':status' => $status, ':id' => $roleId]);
    json_success('Role status updated successfully.');
}

json_error('Method not allowed.', 405);
} catch (Throwable $exception) {
    error_log('Role API failure: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    $message = env_value('APP_DEBUG', '0') === '1'
        ? $exception->getMessage()
        : 'Unable to process roles. Please try again.';
    json_error($message, 500);
}

