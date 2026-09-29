<?php
declare(strict_types=1);

require_once __DIR__ . '/environment.php';

try {
    load_environment_file(APP_ROOT . '/.env');
} catch (AppConfigurationException $exception) {
    // Allow setup/login pages to render useful defaults before .env exists.
    if (is_file(APP_ROOT . '/.env.example')) {
        load_environment_file(APP_ROOT . '/.env.example');
    } else {
        throw $exception;
    }
}

function web_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_name(): string
{
    return trim((string) env_value('APP_NAME', 'Amirtham Integrated Management')) ?: 'Amirtham Integrated Management';
}

function app_short_name(): string
{
    $value = trim((string) env_value('APP_SHORT_NAME', 'Amirtham'));
    return $value !== '' ? $value : 'ERP';
}

function app_subtitle(): string
{
    return trim((string) env_value('APP_SUBTITLE', 'Integrateddddd Food, College & Clinic Management'));
}

function theme_css_path(): string
{
    return APP_ROOT . '/assets/css/theme.css';
}

/** Read one literal CSS variable from the single customer-editable theme.css. */
function theme_css_default_variable(string $variable, string $fallback = ''): string
{
    static $css = null;
    if ($css === null) {
        $path = theme_css_path();
        $css = is_file($path) ? (string) file_get_contents($path) : '';
    }

    $pattern = '/(?:^|[;{\s])' . preg_quote($variable, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\s*;/m';
    if ($css !== '' && preg_match($pattern, $css, $matches) === 1) {
        return strtolower($matches[1]);
    }
    return $fallback;
}

function app_theme_color(): string
{
    return theme_css_default_variable('--primary', '');
}

function frontend_config(): array
{
    return [
        'appName' => app_name(),
        'appShortName' => app_short_name(),
        'appSubtitle' => app_subtitle(),
        'appKey' => application_key(),
        'uiTheme' => 'theme-css',
    ];
}

function render_frontend_config_script(): void
{
    echo '<script>window.STARTER_CONFIG=' . json_encode(
        frontend_config(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . ';</script>';
}
