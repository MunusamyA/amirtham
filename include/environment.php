<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

final class AppConfigurationException extends RuntimeException
{
}

/**
 * Load KEY=VALUE pairs from one application-local .env file.
 * Values from this file always win inside the current request so another
 * localhost PHP project cannot leak a process-global TOKEN_SECRET/DB value.
 */
function load_environment_file(string $path): void
{
    $loadedPath = $GLOBALS['APP_ENV_LOADED_PATH'] ?? null;
    if ($loadedPath === $path) {
        return;
    }

    if (!is_file($path) || !is_readable($path)) {
        throw new AppConfigurationException(
            'The .env file is missing or unreadable. Copy .env.example to .env and configure it.'
        );
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));
        if ($key === '') {
            continue;
        }

        if ($value !== '' && (
            ($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $GLOBALS['APP_ENV_FILE_VALUES'] = $values;
    $GLOBALS['APP_ENV_LOADED_PATH'] = $path;
}

function env_value(string $key, $default = null)
{
    $values = $GLOBALS['APP_ENV_FILE_VALUES'] ?? [];
    if (is_array($values) && array_key_exists($key, $values)) {
        return $values[$key];
    }
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    return $value !== false ? $value : $default;
}

/** Lowercase storage-safe application key used by browser caches/events. */
function application_key(): string
{
    $key = strtolower(trim((string) env_value('APP_KEY', 'amirtham')));
    $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?: 'amirtham';
    return trim($key, '-_') ?: 'amirtham';
}
