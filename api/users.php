<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function validate_user_contact(array $data): void
{
    $errors = [];
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!empty($data['mobile']) && !preg_match('/^[6-9][0-9]{9}$/', (string) $data['mobile'])) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if ($errors !== []) {
        json_error('User validation failed.', 422, $errors);
    }
}

function resolve_user_assignment(array $actor, int $roleId, $requestedBranchId): array
{
    $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id AND status = 1 LIMIT 1');
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();
    if (!$role) {
        json_error('Selected role is invalid or inactive.', 422);
    }

    if ((int) $actor['role_type'] !== 2) {
        if ((int) $role['role_type'] !== 3 ||
            (int) $role['company_id'] !== (int) $actor['company_id']) {
            json_error('You can assign only your company-created roles.', 403);
        }
        return [
            'company_id' => (int) $actor['company_id'],
            'branch_id' => (int) $actor['branch_id'],
        ];
    }

    if ((int) $role['role_type'] === 2) {
        return ['company_id' => null, 'branch_id' => null];
    }

    $branchId = positive_id($requestedBranchId, 'branch_id');
    $stmt = db()->prepare('SELECT company_id, role_id FROM branches WHERE id = :id AND status = 1');
    $stmt->execute([':id' => $branchId]);
    $branch = $stmt->fetch();
    if (!$branch) {
        json_error('Selected branch is invalid.', 422);
    }
    if ((int) $role['role_type'] === 1 && (int) $branch['role_id'] !== $roleId) {
        json_error('Branch Admin must use the branch current plan role.', 422);
    }
    if ((int) $role['role_type'] === 3 && (int) $role['company_id'] !== (int) $branch['company_id']) {
        json_error('Role does not belong to the selected company.', 422);
    }
    return ['company_id' => (int) $branch['company_id'], 'branch_id' => $branchId];
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('users', ACTION_VIEW);
    $actor = $access['user'];
    if ((int) $actor['role_type'] === 2) {
        $branchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
        if ($branchId > 0) {
            $stmt = db()->prepare(
                'SELECT u.id, u.company_id, u.branch_id, u.role_id, r.role_name,
                        u.name, u.username, u.email, u.mobile, u.status,
                        u.last_login_at, u.created_at
                 FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.branch_id = :branch_id ORDER BY u.id DESC'
            );
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $stmt = db()->query(
                'SELECT u.id, u.company_id, u.branch_id, u.role_id, r.role_name,
                        u.name, u.username, u.email, u.mobile, u.status,
                        u.last_login_at, u.created_at
                 FROM users u INNER JOIN roles r ON r.id = u.role_id
                 ORDER BY u.id DESC'
            );
        }
    } else {
        $stmt = db()->prepare(
            'SELECT u.id, u.company_id, u.branch_id, u.role_id, r.role_name,
                    u.name, u.username, u.email, u.mobile, u.status,
                    u.last_login_at, u.created_at
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.branch_id = :branch_id ORDER BY u.id DESC'
        );
        $stmt->execute([':branch_id' => (int) $actor['branch_id']]);
    }
    json_success('Users loaded.', ['users' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $access = require_permission('users', ACTION_CREATE);
    $actor = $access['user'];
    $data = request_data();
    require_fields($data, ['role_id', 'name', 'username', 'password']);
    validate_user_contact($data);
    require_strong_password($data['password']);

    $roleId = positive_id($data['role_id'], 'role_id');
    $assignment = resolve_user_assignment(
        $actor,
        $roleId,
        isset($data['branch_id']) ? $data['branch_id'] : null
    );
    try {
        $stmt = db()->prepare(
            'INSERT INTO users
             (company_id, branch_id, role_id, name, username, email, mobile,
              password_hash, status, created_by, created_at, updated_at)
             VALUES (:company_id, :branch_id, :role_id, :name, :username,
                     :email, :mobile, :password_hash, 1, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $assignment['company_id'],
            ':branch_id' => $assignment['branch_id'],
            ':role_id' => $roleId,
            ':name' => trim((string) $data['name']),
            ':username' => trim((string) $data['username']),
            ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
            ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
            ':password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
            ':created_by' => (int) $actor['id'],
        ]);
        $userId = (int) db()->lastInsertId();
        audit_log((int) $actor['id'], ACTION_CREATE, [
            'company_id' => $assignment['company_id'],
            'branch_id' => $assignment['branch_id'],
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $userId,
        ]);
        json_success('User created successfully.', ['user_id' => $userId], 201);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            json_error('Username already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('users', ACTION_UPDATE);
    $actor = $access['user'];
    $data = request_data();
    require_fields($data, ['id', 'role_id', 'name', 'username']);
    validate_user_contact($data);
    $userId = positive_id($data['id']);

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch();
    if (!$target) {
        json_error('User was not found.', 404);
    }
    if ((int) $actor['role_type'] !== 2 && (int) $target['branch_id'] !== (int) $actor['branch_id']) {
        json_error('You cannot update a user from another branch.', 403);
    }

    $roleId = positive_id($data['role_id'], 'role_id');
    $assignment = resolve_user_assignment(
        $actor,
        $roleId,
        isset($data['branch_id']) ? $data['branch_id'] : $target['branch_id']
    );

    $sql = 'UPDATE users SET company_id = :company_id, branch_id = :branch_id,
            role_id = :role_id, name = :name, username = :username,
            email = :email, mobile = :mobile, updated_at = NOW()';
    $params = [
        ':company_id' => $assignment['company_id'],
        ':branch_id' => $assignment['branch_id'],
        ':role_id' => $roleId,
        ':name' => trim((string) $data['name']),
        ':username' => trim((string) $data['username']),
        ':email' => isset($data['email']) ? trim((string) $data['email']) : null,
        ':mobile' => isset($data['mobile']) ? trim((string) $data['mobile']) : null,
        ':id' => $userId,
    ];
    if (isset($data['password']) && (string) $data['password'] !== '') {
        require_strong_password($data['password']);
        $sql .= ', password_hash = :password_hash';
        $params[':password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
    }
    $sql .= ' WHERE id = :id';

    try {
        db()->prepare($sql)->execute($params);
        audit_log((int) $actor['id'], ACTION_UPDATE, [
            'company_id' => $assignment['company_id'],
            'branch_id' => $assignment['branch_id'],
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $userId,
        ]);
        json_success('User updated successfully.');
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            json_error('Username already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $access = require_permission('users', ACTION_UPDATE);
    $actor = $access['user'];
    $data = request_data();
    require_fields($data, ['id', 'status']);
    $userId = positive_id($data['id']);

    $stmt = db()->prepare('SELECT branch_id FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch();
    if (!$target) {
        json_error('User was not found.', 404);
    }
    if ((int) $actor['role_type'] !== 2 && (int) $target['branch_id'] !== (int) $actor['branch_id']) {
        json_error('You cannot change a user from another branch.', 403);
    }
    if ($userId === (int) $actor['id'] && normalize_status($data['status']) === 0) {
        json_error('You cannot deactivate your own account.', 422);
    }

    db()->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute([':status' => normalize_status($data['status']), ':id' => $userId]);
    json_success('User status updated successfully.');
}

json_error('Method not allowed. User deletion is not supported.', 405);
