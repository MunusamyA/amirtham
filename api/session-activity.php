<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}

/*
 * This endpoint is called only after real browser interaction.
 * It does not write session activity to MySQL. It returns a newly signed token
 * with a renewed idle expiry while preserving the original absolute expiry.
 */
$payload = require_auth_payload();
require_user();
$renewed = renew_auth_token($payload);

json_success('Session activity renewed.', [
    'token' => $renewed['token'],
    'idle_expiry_minutes' => (int) $renewed['idle_expiry_minutes'],
    'absolute_login_expiry_minutes' => (int) $renewed['absolute_login_expiry_minutes'],
    'idle_expires_at' => date(DATE_ATOM, (int) $renewed['idle_expires_at']),
    'idle_expires_at_unix' => (int) $renewed['idle_expires_at'],
    'absolute_expires_at' => date(DATE_ATOM, (int) $renewed['absolute_expires_at']),
    'absolute_expires_at_unix' => (int) $renewed['absolute_expires_at'],

    /* Backward-compatible field name. */
    'idle_timeout_minutes' => (int) $renewed['idle_expiry_minutes'],
]);
