<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function permission_role_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted role reference is required.', 422, ['ref' => 'Role reference is required.']);
    }
    try {
        return decryptReference(trim($value), 'role');
    } catch (Throwable $exception) {
        json_error('Invalid or changed role reference.', 422, ['ref' => 'The role reference is invalid.']);
    }
}

function permission_target_role(array $user, int $roleId): array
{
    $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Role was not found.', 404);
    }

    if ((int) $user['role_type'] === 2) {
        if (!in_array((int) $role['role_type'], [1, 2], true) || $role['company_id'] !== null) {
            json_error('Platform users can manage only Plans and Platform Roles.', 403);
        }
    } elseif ((int) $role['role_type'] !== 3 ||
        (int) $role['company_id'] !== (int) $user['company_id']) {
        json_error('You can update only your company-created roles.', 403);
    }
    return $role;
}

function permission_payload(array $data): array
{
    $permissions = $data['permissions'] ?? [];
    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        $permissions = is_array($decoded) ? $decoded : [];
    }
    return is_array($permissions) ? $permissions : [];
}

function validate_platform_role_permissions(array $permissions): array
{
    $active = active_action_ids();
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

        $requested = normalize_csv_ids($permission['action_ids'] ?? []);
        $available = array_values(array_intersect(
            normalize_csv_ids($menu['available_action_ids']),
            $active
        ));

        if (array_diff($requested, $available) !== []) {
            json_error('A selected action is not available for this menu.', 422);
        }
        if ($requested !== []) {
            $clean[] = ['menu_id' => $menuId, 'action_ids' => csv_ids($requested)];
        }
    }
    return $clean;
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('permission-form.php', ACTION_VIEW);
    $user = $access['user'];
    $reference = isset($_GET['ref']) ? (string) $_GET['ref'] : '';
    $roleId = permission_role_id_from_reference($reference);
    $role = permission_target_role($user, $roleId);
    $active = active_action_ids();

    $menus = db()->query(
        'SELECT id, parent_id, menu_name, menu_path, available_action_ids, sort_order
         FROM menus WHERE status = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    $result = [];
    foreach ($menus as $menu) {
        $available = array_values(array_intersect(
            normalize_csv_ids($menu['available_action_ids']),
            $active
        ));

        if ((int) $user['role_type'] !== 2) {
            if (!tenant_role_menu_is_grantable($menu)) {
                continue;
            }
            $plan = role_menu_actions((int) $user['branch_plan_role_id'], (int) $menu['id']);
            $available = array_values(array_intersect($available, $plan));
        }
        if ($available === []) {
            continue;
        }

        $selected = array_values(array_intersect(
            role_menu_actions($roleId, (int) $menu['id']),
            $available
        ));

        $result[] = [
            'id' => (int) $menu['id'],
            'parent_id' => $menu['parent_id'] === null ? null : (int) $menu['parent_id'],
            'menu_name' => $menu['menu_name'],
            'menu_path' => $menu['menu_path'],
            'available_action_ids' => array_map('intval', $available),
            'selected_action_ids' => array_map('intval', $selected),
        ];
    }

    $publicRole = $role;
    unset($publicRole['id']);
    $publicRole['ref'] = $reference;

    json_success('Permissions loaded.', [
        'role' => $publicRole,
        'menus' => $result,
        'actions' => permission_actions_list(true),
        'action_names' => action_names(true),
        'action_groups' => action_group_names(),
        'allowed_actions' => $access['actions'],
        'target_locked' => (int) $role['id'] === (int) $user['role_id'],
    ]);
}

if ($method === 'PUT') {
    $access = require_permission('permission-form.php', ACTION_MANAGE_PERMISSIONS);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, ['ref' => 'Role reference is required.']);

    $roleId = permission_role_id_from_reference($data['ref']);
    $role = permission_target_role($user, $roleId);
    if ((int) $role['id'] === (int) $user['role_id']) {
        json_error('You cannot replace permissions for your currently logged-in role.', 409);
    }

    $permissions = (int) $user['role_type'] === 2
        ? validate_platform_role_permissions(permission_payload($data))
        : validate_branch_role_permissions((int) $user['branch_id'], permission_payload($data));

    $pdo = db();
    try {
        $pdo->beginTransaction();
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
                ':menu_id' => (int) $permission['menu_id'],
                ':action_ids' => csv_ids($permission['action_ids']),
            ]);
        }
        $pdo->commit();

        audit_log((int) $user['id'], ACTION_MANAGE_PERMISSIONS, [
            'company_id' => $role['company_id'],
            'branch_id' => $user['branch_id'],
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $roleId,
        ]);
        json_success('Role permissions updated successfully.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

json_error('Method not allowed.', 405);
