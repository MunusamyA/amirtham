<?php
declare(strict_types=1);

function encryption_key(): string
{
    $configured = (string) env_value('APP_ENCRYPTION_KEY', '');
    if ($configured === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY is missing.');
    }

    $decoded = base64_decode($configured, true);
    if ($decoded !== false && strlen($decoded) === 32) {
        return $decoded;
    }
    return hash('sha256', $configured, true);
}

function encryptSecret(string $plainText): string
{
    if ($plainText === '') {
        return '';
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $plainText,
        'aes-256-gcm',
        encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($cipherText === false) {
        throw new RuntimeException('Secret encryption failed.');
    }

    return 'v1:' . base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($cipherText);
}

function decryptSecret(string $storedValue): string
{
    if ($storedValue === '') {
        return '';
    }
    if (strpos($storedValue, 'v1:') !== 0) {
        throw new RuntimeException('Unsupported encrypted value format.');
    }

    $parts = explode('.', substr($storedValue, 3));
    if (count($parts) !== 3) {
        throw new RuntimeException('Encrypted value is invalid.');
    }

    $iv = base64_decode($parts[0], true);
    $tag = base64_decode($parts[1], true);
    $cipherText = base64_decode($parts[2], true);
    if ($iv === false || $tag === false || $cipherText === false) {
        throw new RuntimeException('Encrypted value is corrupt.');
    }

    $plainText = openssl_decrypt(
        $cipherText,
        'aes-256-gcm',
        encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    if ($plainText === false) {
        throw new RuntimeException('Secret decryption failed. Check the permanent encryption key.');
    }

    return $plainText;
}

function reference_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function reference_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid encrypted reference.');
    }
    return $decoded;
}

/** Create a URL-safe encrypted record reference. The real database ID never enters JavaScript. */
function encryptReference(string $type, int $id): string
{
    if (!preg_match('/^[a-z][a-z0-9_-]{1,30}$/', $type) || $id < 1) {
        throw new InvalidArgumentException('Invalid reference data.');
    }

    $payload = json_encode(['type' => $type, 'id' => $id]);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode reference.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
        $payload,
        'aes-256-gcm',
        encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'record-reference-v1',
        16
    );
    if ($cipherText === false) {
        throw new RuntimeException('Unable to encrypt reference.');
    }

    return 'r1.' . reference_base64url_encode($iv) . '.' .
        reference_base64url_encode($tag) . '.' .
        reference_base64url_encode($cipherText);
}

/** Decrypt and type-check a URL reference before using its record ID. */
function decryptReference(string $reference, string $expectedType): int
{
    $parts = explode('.', $reference);
    if (count($parts) !== 4 || $parts[0] !== 'r1') {
        throw new RuntimeException('Invalid encrypted reference.');
    }

    $plainText = openssl_decrypt(
        reference_base64url_decode($parts[3]),
        'aes-256-gcm',
        encryption_key(),
        OPENSSL_RAW_DATA,
        reference_base64url_decode($parts[1]),
        reference_base64url_decode($parts[2]),
        'record-reference-v1'
    );
    if ($plainText === false) {
        throw new RuntimeException('Encrypted reference is invalid or has been changed.');
    }

    $payload = json_decode($plainText, true);
    if (!is_array($payload) || !isset($payload['type'], $payload['id']) ||
        !hash_equals($expectedType, (string) $payload['type'])) {
        throw new RuntimeException('Encrypted reference type is invalid.');
    }

    $id = filter_var($payload['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new RuntimeException('Encrypted reference ID is invalid.');
    }
    return (int) $id;
}
