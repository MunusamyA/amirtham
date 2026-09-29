<?php
declare(strict_types=1);

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid token encoding.');
    }
    return $decoded;
}

/**
 * Maximum lifetime of one login. Stored as a configuration value in app_settings.
 * Existing legacy token_expiry_minutes is used as a fallback so upgrades are safe.
 */
function auth_absolute_login_expiry_minutes($branchId = null): int
{
    $fallback = max(1, (int) env_value('TOKEN_EXPIRY_HOURS', 24)) * 60;
    $legacy = (int) app_setting('token_expiry_minutes', $fallback, $branchId);
    $minutes = (int) app_setting('absolute_login_expiry_minutes', $legacy, $branchId);
    return max(5, min(43200, $minutes));
}

/**
 * Rolling inactivity window. Stored as a configuration value in app_settings.
 * Existing legacy idle_timeout_minutes is used as a fallback so upgrades are safe.
 */
function auth_idle_expiry_minutes($branchId = null): int
{
    $fallback = (int) env_value('IDLE_TIMEOUT_MINUTES', 60);
    $fallback = max(5, min(10080, $fallback));
    $legacy = (int) app_setting('idle_timeout_minutes', $fallback, $branchId);
    $minutes = (int) app_setting('idle_expiry_minutes', $legacy, $branchId);
    return max(5, min(10080, $minutes));
}

/* Backward-compatible helper names used by older pages/APIs. */
function auth_token_expiry_minutes($branchId = null): int
{
    return auth_absolute_login_expiry_minutes($branchId);
}

function auth_idle_timeout_minutes($branchId = null): int
{
    return auth_idle_expiry_minutes($branchId);
}

/**
 * Issue a stateless signed token.
 *
 * exp          = rolling idle expiry
 * absolute_exp = fixed maximum login expiry
 * login_iat    = original login time and never changes during idle renewal
 * iat          = time this particular signed token was issued
 */
function issue_auth_token(
    int $userId,
    $branchId,
    ?int $absoluteExpiresAt = null,
    ?int $loginIssuedAt = null
): array {
    $secret = (string) env_value('TOKEN_SECRET', '');
    if (strlen($secret) < 32) {
        throw new RuntimeException('TOKEN_SECRET must contain at least 32 characters.');
    }

    $now = time();
    $loginIssuedAt = $loginIssuedAt !== null && $loginIssuedAt > 0 ? $loginIssuedAt : $now;

    if ($absoluteExpiresAt === null) {
        $absoluteMinutes = auth_absolute_login_expiry_minutes($branchId);
        $absoluteExpiresAt = $loginIssuedAt + ($absoluteMinutes * 60);
    }

    if ($absoluteExpiresAt <= $now) {
        throw new RuntimeException('Your login has reached its maximum duration. Please login again.');
    }

    $idleMinutes = auth_idle_expiry_minutes($branchId);
    $idleExpiresAt = min($now + ($idleMinutes * 60), $absoluteExpiresAt);

    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $payload = [
        'sub' => $userId,
        'bid' => $branchId === null ? 0 : (int) $branchId,
        'iat' => $now,
        'login_iat' => $loginIssuedAt,
        'exp' => $idleExpiresAt,
        'absolute_exp' => $absoluteExpiresAt,
    ];

    $unsigned = base64url_encode((string) json_encode($header)) . '.' .
        base64url_encode((string) json_encode($payload));
    $signature = hash_hmac('sha256', $unsigned, $secret, true);
    $token = $unsigned . '.' . base64url_encode($signature);

    return [
        'token' => $token,
        'payload' => $payload,
        'idle_expiry_minutes' => $idleMinutes,
        'idle_expires_at' => $idleExpiresAt,
        'absolute_expires_at' => $absoluteExpiresAt,
        'absolute_login_expiry_minutes' => max(1, (int) ceil(($absoluteExpiresAt - $loginIssuedAt) / 60)),
    ];
}

/** Backward-compatible token creator for code that only needs the token string. */
function create_auth_token(int $userId, $branchId): string
{
    $issued = issue_auth_token($userId, $branchId);
    return (string) $issued['token'];
}

function verify_auth_token(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Invalid authentication token.');
    }

    $header = json_decode(base64url_decode($parts[0]), true);
    $payload = json_decode(base64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($payload) ||
        !isset($header['alg']) || $header['alg'] !== 'HS256') {
        throw new RuntimeException('Invalid authentication token.');
    }

    $secret = (string) env_value('TOKEN_SECRET', '');
    if (strlen($secret) < 32) {
        throw new RuntimeException('TOKEN_SECRET must contain at least 32 characters.');
    }

    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
    $received = base64url_decode($parts[2]);
    if (!hash_equals($expected, $received)) {
        throw new RuntimeException('Authentication token signature is invalid.');
    }

    $required = ['sub', 'bid', 'iat', 'login_iat', 'exp', 'absolute_exp'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $payload)) {
            throw new RuntimeException('Invalid authentication token.');
        }
    }

    $userId = (int) $payload['sub'];
    $issuedAt = (int) $payload['iat'];
    $loginIssuedAt = (int) $payload['login_iat'];
    $idleExpiresAt = (int) $payload['exp'];
    $absoluteExpiresAt = (int) $payload['absolute_exp'];

    if ($userId <= 0 || $loginIssuedAt <= 0 || $issuedAt < $loginIssuedAt ||
        $idleExpiresAt <= $issuedAt || $absoluteExpiresAt <= $loginIssuedAt ||
        $idleExpiresAt > $absoluteExpiresAt) {
        throw new RuntimeException('Invalid authentication token.');
    }

    $now = time();
    if ($absoluteExpiresAt <= $now) {
        throw new RuntimeException('Your login has reached its maximum duration. Please login again.');
    }
    if ($idleExpiresAt <= $now) {
        throw new RuntimeException('Your session expired due to inactivity. Please login again.');
    }

    return $payload;
}

/**
 * Extend only the idle window. The original absolute_exp and login_iat are preserved.
 */
function renew_auth_token(array $payload): array
{
    if (!isset($payload['sub'], $payload['bid'], $payload['login_iat'], $payload['absolute_exp'])) {
        throw new RuntimeException('Invalid authentication token.');
    }

    $branchId = (int) $payload['bid'] === 0 ? null : (int) $payload['bid'];
    return issue_auth_token(
        (int) $payload['sub'],
        $branchId,
        (int) $payload['absolute_exp'],
        (int) $payload['login_iat']
    );
}

function bearer_token(): string
{
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = (string) $headers['Authorization'];
        }
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        json_error('Bearer token is required.', 401);
        return '';
    }
    return trim($matches[1]);
}

function require_auth_payload(): array
{
    static $payload = null;
    if (is_array($payload)) {
        return $payload;
    }

    try {
        $payload = verify_auth_token(bearer_token());
    } catch (Throwable $exception) {
        json_error($exception->getMessage(), 401);
        return [];
    }
    return $payload;
}

function require_user(): array
{
    static $user = null;
    if (is_array($user)) {
        return $user;
    }

    $payload = require_auth_payload();
    $stmt = db()->prepare(
        'SELECT u.*, r.role_name, r.role_type, r.status AS role_status,
                c.company_name, c.status AS company_status,
                b.branch_name, b.status AS branch_status,
                b.role_id AS branch_plan_role_id
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         LEFT JOIN companies c ON c.id = u.company_id
         LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int) $payload['sub']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['status'] !== 1 || (int) $user['role_status'] !== 1) {
        json_error('User account is inactive or unavailable.', 401);
    }
    if ($user['company_id'] !== null && (int) $user['company_status'] !== 1) {
        json_error('Company is inactive.', 403);
    }
    if ($user['branch_id'] !== null && (int) $user['branch_status'] !== 1) {
        json_error('Branch is inactive.', 403);
    }

    $currentBranchId = $user['branch_id'] === null ? 0 : (int) $user['branch_id'];
    if ($currentBranchId !== (int) $payload['bid']) {
        json_error('User branch assignment has changed. Please login again.', 401);
    }

    unset($user['password_hash']);
    return $user;
}

function require_platform_user(): array
{
    $user = require_user();
    if ((int) $user['role_type'] !== 2) {
        json_error('Platform access is required.', 403);
    }
    return $user;
}
