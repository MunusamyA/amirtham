<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}

$data = request_data();
require_fields($data, ['username', 'password']);

$stmt = db()->prepare(
    'SELECT u.*, r.role_name, r.role_type, r.status AS role_status,
            c.status AS company_status, b.status AS branch_status
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN companies c ON c.id = u.company_id
     LEFT JOIN branches b ON b.id = u.branch_id
     WHERE u.username = :username
     LIMIT 1'
);
$stmt->execute([':username' => trim((string) $data['username'])]);
$user = $stmt->fetch();

if (!$user || !password_verify((string) $data['password'], $user['password_hash'])) {
    json_error('Invalid username or password.', 401);
}
if ((int) $user['status'] !== 1 || (int) $user['role_status'] !== 1) {
    json_error('User account is inactive.', 403);
}
if ($user['company_id'] !== null && (int) $user['company_status'] !== 1) {
    json_error('Company is inactive.', 403);
}
if ($user['branch_id'] !== null && (int) $user['branch_status'] !== 1) {
    json_error('Branch is inactive.', 403);
}

db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
    ->execute([':id' => (int) $user['id']]);

$branchId = $user['branch_id'] === null ? null : (int) $user['branch_id'];
$issued = issue_auth_token((int) $user['id'], $branchId);

$absoluteMinutes = (int) $issued['absolute_login_expiry_minutes'];
$idleMinutes = (int) $issued['idle_expiry_minutes'];
$absoluteExpiresAt = (int) $issued['absolute_expires_at'];
$idleExpiresAt = (int) $issued['idle_expires_at'];

json_success('Login successful.', [
    'token' => $issued['token'],
    'absolute_login_expiry_minutes' => $absoluteMinutes,
    'idle_expiry_minutes' => $idleMinutes,
    'absolute_expires_at' => date(DATE_ATOM, $absoluteExpiresAt),
    'absolute_expires_at_unix' => $absoluteExpiresAt,
    'idle_expires_at' => date(DATE_ATOM, $idleExpiresAt),
    'idle_expires_at_unix' => $idleExpiresAt,

    /* Backward-compatible response names. */
    'expires_in_minutes' => $absoluteMinutes,
    'idle_timeout_minutes' => $idleMinutes,

    'user' => [
        'id' => (int) $user['id'],
        'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
        'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'role_type' => (int) $user['role_type'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'absolute_login_expiry_minutes' => $absoluteMinutes,
        'idle_expiry_minutes' => $idleMinutes,
        'absolute_expires_at_unix' => $absoluteExpiresAt,
        'idle_expires_at_unix' => $idleExpiresAt,

        /* Backward-compatible names used by older frontend code. */
        'idle_timeout_minutes' => $idleMinutes,
        'token_expires_at' => date(DATE_ATOM, $absoluteExpiresAt),
    ],
]);
