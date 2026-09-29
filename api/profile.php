<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

$user = require_user();
$method = request_method();

function profile_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'mobile' => $user['mobile'],
        'role_name' => $user['role_name'],
        'role_type' => (int) $user['role_type'],
        'company_name' => $user['company_name'],
        'branch_name' => $user['branch_name'],
        'last_login_at' => $user['last_login_at'],
    ];
}

if ($method === 'GET') {
    json_success('Profile loaded.', ['profile' => profile_payload($user)]);
}

if ($method !== 'PUT' && $method !== 'POST') {
    json_error('Method not allowed.', 405);
}

$data = request_data();
$name = trim((string) ($data['name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$mobile = trim((string) ($data['mobile'] ?? ''));
$password = isset($data['password']) ? (string) $data['password'] : '';

$errors = [];
if ($name === '') $errors['name'] = 'Name is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
if ($mobile !== '' && !valid_input_value('mobile', normalize_input_value('mobile', $mobile))) $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
if ($errors) json_error('Profile validation failed.', 422, $errors);
if ($password !== '') require_strong_password($password);

$sql = 'UPDATE users SET name = :name, email = :email, mobile = :mobile, updated_at = NOW()';
$params = [
    ':name' => $name,
    ':email' => $email === '' ? null : $email,
    ':mobile' => $mobile === '' ? null : normalize_input_value('mobile', $mobile),
    ':id' => (int) $user['id'],
];
if ($password !== '') {
    $sql .= ', password_hash = :password_hash';
    $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
}
$sql .= ' WHERE id = :id';
db()->prepare($sql)->execute($params);

audit_log((int) $user['id'], ACTION_UPDATE, [
    'company_id' => $user['company_id'],
    'branch_id' => $user['branch_id'],
    'record_id' => (int) $user['id'],
    'new_data' => ['profile_updated' => true],
]);

$updated = require_user();
$updated['name'] = $name;
$updated['email'] = $email === '' ? null : $email;
$updated['mobile'] = $mobile === '' ? null : normalize_input_value('mobile', $mobile);
json_success('Profile updated successfully.', ['profile' => profile_payload($updated)]);
