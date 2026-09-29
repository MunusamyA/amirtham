<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'POST') {
    json_error('Method not allowed.', 405);
}

/* Stateless logout: server stores no token/session row. */
require_user();
json_success('Logout successful.');
