<?php
declare(strict_types=1);

/* APIs must never print PHP warnings/notices as HTML. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/environment.php';

/** Return JSON even when the application fails before functions.php is loaded. */
function bootstrap_json_error(string $message, int $status = 500): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
}

/* Register handlers before loading .env or any application dependency. */
register_shutdown_function(function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$error || !in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log('Fatal PHP error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    bootstrap_json_error('Internal server error. Check the PHP error log.', 500);
});

set_exception_handler(function ($exception): void {
    error_log(get_class($exception) . ': ' . $exception->getMessage());
    $isConfigurationError = $exception instanceof AppConfigurationException;
    $message = $isConfigurationError || env_value('APP_DEBUG', '0') === '1'
        ? $exception->getMessage()
        : 'Internal server error.';
    bootstrap_json_error($message, 500);
});

set_error_handler(function ($severity, $message, $file, $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

load_environment_file(APP_ROOT . '/.env');

date_default_timezone_set((string) env_value('APP_TIMEZONE', 'UTC'));

$autoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once APP_ROOT . '/include/database.php';
require_once APP_ROOT . '/include/functions.php';
require_once APP_ROOT . '/include/theme.php';
require_once APP_ROOT . '/include/encryption.php';
require_once APP_ROOT . '/include/auth.php';
require_once APP_ROOT . '/include/permission.php';
require_once APP_ROOT . '/include/mail.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigins = array_filter(array_map('trim', explode(',', (string) env_value('ALLOWED_ORIGINS', '*'))));
if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
