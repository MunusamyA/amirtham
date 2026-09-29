<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function platform_admin_exists(): bool
{
    $stmt = db()->query(
        'SELECT COUNT(*)
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.role_type = 2'
    );
    return (int) $stmt->fetchColumn() > 0;
}

$method = request_method();

if ($method === 'GET') {
    json_success('Registration status loaded.', [
        'registration_available' => !platform_admin_exists(),
    ]);
}

if ($method !== 'POST') {
    json_error('Method not allowed.', 405);
}

$data = request_data();
require_fields($data, ['name', 'username', 'email', 'password']);

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    json_error('Enter a valid email address.', 422, ['email' => 'Invalid email address.']);
}

$password = (string) $data['password'];
if (strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)) {
    json_error(
        'Password must contain uppercase, lowercase, number and special character.',
        422,
        ['password' => 'Enter a strong password with at least 8 characters.']
    );
}

$pdo = db();
$lockName = application_key() . '_initial_platform_owner';
$lockStmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
$lockStmt->execute([':lock_name' => $lockName]);
$lock = $lockStmt->fetchColumn();
if ((int) $lock !== 1) {
    json_error('Registration is temporarily busy. Please try again.', 409);
}

try {
    $pdo->beginTransaction();

    ensure_system_configuration();

    if (platform_admin_exists()) {
        $pdo->rollBack();
        $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $releaseStmt->execute([':lock_name' => $lockName]);
        json_error('Initial Platform Owner is already registered.', 409);
    }

    $stmt = $pdo->query(
        "SELECT id FROM roles
         WHERE company_id IS NULL AND role_type = 2
           AND role_name = 'Platform Owner'
         LIMIT 1"
    );
    $roleId = (int) $stmt->fetchColumn();

    if ($roleId === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO roles
             (company_id, role_name, role_type, status, created_by, created_at, updated_at)
             VALUES (NULL, :role_name, 2, 1, NULL, NOW(), NOW())'
        );
        $stmt->execute([':role_name' => 'Platform Owner']);
        $roleId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE roles SET status = 1, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $roleId]);
    }

    $menus = $pdo->query(
        'SELECT id, available_action_ids FROM menus WHERE status = 1'
    )->fetchAll();
    $permissionStmt = $pdo->prepare(
        'INSERT INTO role_permissions
         (role_id, menu_id, action_ids, status, created_at, updated_at)
         VALUES (:role_id, :menu_id, :action_ids, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            action_ids = VALUES(action_ids), status = 1, updated_at = NOW()'
    );
    foreach ($menus as $menu) {
        $permissionStmt->execute([
            ':role_id' => $roleId,
            ':menu_id' => (int) $menu['id'],
            ':action_ids' => csv_ids($menu['available_action_ids']),
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users
         (company_id, branch_id, role_id, name, username, email, mobile,
          password_hash, status, created_by, created_at, updated_at)
         VALUES (NULL, NULL, :role_id, :name, :username, :email, :mobile,
                 :password_hash, 1, NULL, NOW(), NOW())'
    );
    $stmt->execute([
        ':role_id' => $roleId,
        ':name' => trim((string) $data['name']),
        ':username' => trim((string) $data['username']),
        ':email' => trim((string) $data['email']),
        ':mobile' => isset($data['mobile']) && trim((string) $data['mobile']) !== ''
            ? trim((string) $data['mobile']) : null,
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE roles SET created_by = :user_id WHERE id = :role_id')
        ->execute([':user_id' => $userId, ':role_id' => $roleId]);

    $pdo->commit();
    $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $releaseStmt->execute([':lock_name' => $lockName]);

    json_success('Platform Owner registered successfully.', [
        'user_id' => $userId,
        'redirect' => 'login.php',
    ], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $releaseStmt->execute([':lock_name' => $lockName]);

    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        json_error('Username already exists.', 409);
    }
    throw $exception;
}
