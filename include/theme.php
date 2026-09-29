<?php
declare(strict_types=1);

/**
 * Runtime theme system.
 *
 * assets/css/theme.css is the customer-design source file.
 * app_settings stores only:
 *   - theme_preset (one of the supported preset IDs)
 *   - optional semantic colour overrides from theme_definition()
 *
 * Fonts, radius, spacing, density, dimensions, shadows and motion belong to
 * the preset/customer theme CSS and are never duplicated in PHP.
 */
function theme_definition(): array
{
    return [
        'theme_primary_deep'    => ['css' => '--primary-deep', 'label' => 'Primary Deep', 'group' => 'Brand'],
        'theme_primary_strong'  => ['css' => '--primary-strong', 'label' => 'Primary Strong', 'group' => 'Brand'],
        'theme_primary'         => ['css' => '--primary', 'label' => 'Primary', 'group' => 'Brand'],
        'theme_primary_medium'  => ['css' => '--primary-medium', 'label' => 'Primary Medium', 'group' => 'Brand'],
        'theme_primary_light'   => ['css' => '--primary-light', 'label' => 'Primary Light', 'group' => 'Brand'],
        'theme_primary_soft'    => ['css' => '--primary-soft', 'label' => 'Primary Soft', 'group' => 'Brand'],
        'theme_primary_subtle'  => ['css' => '--primary-subtle', 'label' => 'Primary Tint', 'group' => 'Brand'],
        'theme_accent'          => ['css' => '--accent', 'label' => 'Accent', 'group' => 'Status / Accent'],
        'theme_accent_teal'     => ['css' => '--accent-teal', 'label' => 'Teal Accent', 'group' => 'Status / Accent'],
        'theme_success'         => ['css' => '--success', 'label' => 'Success', 'group' => 'Status / Accent'],
        'theme_info'            => ['css' => '--info', 'label' => 'Info', 'group' => 'Status / Accent'],
        'theme_warning'         => ['css' => '--warning', 'label' => 'Warning', 'group' => 'Status / Accent'],
        'theme_danger'          => ['css' => '--danger', 'label' => 'Danger', 'group' => 'Status / Accent'],
        'theme_text_primary'    => ['css' => '--light-text-primary', 'label' => 'Main Text', 'group' => 'Light Appearance'],
        'theme_text_secondary'  => ['css' => '--light-text-secondary', 'label' => 'Secondary Text', 'group' => 'Light Appearance'],
        'theme_text_muted'      => ['css' => '--light-text-muted', 'label' => 'Muted Text', 'group' => 'Light Appearance'],
        'theme_border'          => ['css' => '--light-border', 'label' => 'Border', 'group' => 'Light Appearance'],
        'theme_surface'         => ['css' => '--light-surface', 'label' => 'Surface', 'group' => 'Light Appearance'],
        'theme_canvas'          => ['css' => '--light-canvas', 'label' => 'Canvas', 'group' => 'Light Appearance'],
    ];
}

function theme_presets(): array
{
    return [
        'forest' => [
            'label' => 'Forest Classic',
            'description' => 'Balanced green ERP theme with medium density and classic branding.',
            'profile' => 'Balanced · Rounded',
        ],
        'ocean' => [
            'label' => 'Ocean Corporate',
            'description' => 'Professional blue interface with cleaner edges and corporate spacing.',
            'profile' => 'Corporate · Clean',
        ],
        'violet' => [
            'label' => 'Royal Violet',
            'description' => 'Modern violet theme with softer cards, larger radius and comfortable controls.',
            'profile' => 'Modern · Soft',
        ],
        'ruby' => [
            'label' => 'Ruby Executive',
            'description' => 'Sharp executive styling with ruby accents and compact professional controls.',
            'profile' => 'Executive · Sharp',
        ],
        'amber' => [
            'label' => 'Amber Warm',
            'description' => 'Warm amber identity with friendly typography, spacing and rounded surfaces.',
            'profile' => 'Warm · Friendly',
        ],
        'teal' => [
            'label' => 'Teal Modern',
            'description' => 'Fresh teal theme with efficient density and contemporary component sizing.',
            'profile' => 'Modern · Efficient',
        ],
        'indigo' => [
            'label' => 'Indigo Pro',
            'description' => 'Structured indigo theme designed for dashboards and knowledge-work applications.',
            'profile' => 'Pro · Structured',
        ],
        'rose' => [
            'label' => 'Rose Soft',
            'description' => 'Soft rose palette with generous radius, subtle surfaces and comfortable spacing.',
            'profile' => 'Soft · Spacious',
        ],
        'slate' => [
            'label' => 'Slate Compact',
            'description' => 'Neutral compact ERP layout for inventory, billing and data-heavy workflows.',
            'profile' => 'Compact · Dense',
        ],
        'midnight' => [
            'label' => 'Midnight Premium',
            'description' => 'Premium navy identity with refined spacing, strong contrast and deeper shadows.',
            'profile' => 'Premium · Refined',
        ],
    ];
}

function theme_default_preset(): string
{
    return 'forest';
}

function theme_normalize_preset($value): ?string
{
    $value = strtolower(trim((string) $value));
    return isset(theme_presets()[$value]) ? $value : null;
}

function theme_css_file_contents(): string
{
    static $css = null;
    if ($css !== null) {
        return $css;
    }
    $path = APP_ROOT . '/assets/css/theme.css';
    if (!is_file($path)) {
        throw new RuntimeException('Customer theme file assets/css/theme.css is missing.');
    }
    $css = (string) file_get_contents($path);
    return $css;
}

function theme_css_hex(string $variable): string
{
    $css = theme_css_file_contents();
    $pattern = '/(?:^|[;{\s])' . preg_quote($variable, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\s*;/m';
    if (preg_match($pattern, $css, $matches) !== 1) {
        throw new RuntimeException('Theme variable ' . $variable . ' must contain a literal 6-digit HEX value in assets/css/theme.css.');
    }
    return strtolower($matches[1]);
}

function theme_defaults(): array
{
    $values = [];
    foreach (theme_definition() as $key => $meta) {
        $values[$key] = theme_css_hex($meta['css']);
    }
    return $values;
}

function theme_normalize_hex($value): ?string
{
    $value = strtoupper(trim((string) $value));
    if (preg_match('/^#[0-9A-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }
    if (preg_match('/^#[0-9A-F]{3}$/', $value) === 1) {
        return '#' . strtolower($value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]);
    }
    return null;
}

/**
 * Resolve the preset first.
 * Branch preset wins. Otherwise platform preset is inherited. Otherwise the
 * theme.css default preset is used.
 */
function theme_load_preset($branchId = null): array
{
    $preset = theme_default_preset();
    $source = 'theme.css';

    try {
        if ($branchId === null) {
            $stmt = db()->prepare(
                "SELECT branch_id, setting_value
                 FROM app_settings
                 WHERE branch_id IS NULL AND setting_key = 'theme_preset'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute();
        } else {
            $stmt = db()->prepare(
                "SELECT branch_id, setting_value
                 FROM app_settings
                 WHERE (branch_id = ? OR branch_id IS NULL)
                   AND setting_key = 'theme_preset'
                 ORDER BY CASE WHEN branch_id = ? THEN 0 ELSE 1 END, id DESC
                 LIMIT 1"
            );
            $stmt->execute([(int) $branchId, (int) $branchId]);
        }

        $row = $stmt->fetch();
        if ($row) {
            $normalized = theme_normalize_preset($row['setting_value']);
            if ($normalized !== null) {
                $preset = $normalized;
                $source = $row['branch_id'] === null ? 'platform' : 'branch';
            }
        }
    } catch (Throwable $exception) {
        error_log('theme_load_preset failed: ' . $exception->getMessage());
    }

    return ['preset' => $preset, 'source' => $source];
}

function theme_load($branchId = null): array
{
    $definition = theme_definition();
    $values = theme_defaults();
    $sources = array_fill_keys(array_keys($values), 'theme.css');
    $cssVariables = [];
    $presetInfo = theme_load_preset($branchId);

    try {
        /*
         * If a branch selected its own full preset, platform colour overrides
         * must not be layered over that preset. Branch-specific custom colours
         * may still override it. Without a branch preset, normal platform
         * inheritance remains active.
         */
        if ($branchId === null) {
            $stmt = db()->prepare(
                "SELECT branch_id, setting_key, setting_value
                 FROM app_settings
                 WHERE branch_id IS NULL
                   AND setting_key LIKE 'theme_%'
                   AND setting_key <> 'theme_preset'
                 ORDER BY id DESC"
            );
            $stmt->execute();
        } elseif ($presetInfo['source'] === 'branch') {
            $stmt = db()->prepare(
                "SELECT branch_id, setting_key, setting_value
                 FROM app_settings
                 WHERE branch_id = ?
                   AND setting_key LIKE 'theme_%'
                   AND setting_key <> 'theme_preset'
                 ORDER BY id DESC"
            );
            $stmt->execute([(int) $branchId]);
        } else {
            $stmt = db()->prepare(
                "SELECT branch_id, setting_key, setting_value
                 FROM app_settings
                 WHERE (branch_id = ? OR branch_id IS NULL)
                   AND setting_key LIKE 'theme_%'
                   AND setting_key <> 'theme_preset'
                 ORDER BY CASE WHEN branch_id = ? THEN 0 ELSE 1 END, id DESC"
            );
            $stmt->execute([(int) $branchId, (int) $branchId]);
        }

        $seen = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['setting_key'];
            if (!isset($definition[$key]) || isset($seen[$key])) {
                continue;
            }
            $color = theme_normalize_hex($row['setting_value']);
            if ($color === null) {
                continue;
            }
            $values[$key] = $color;
            $sources[$key] = $row['branch_id'] === null ? 'platform' : 'branch';
            $cssVariables[$definition[$key]['css']] = $color;
            $seen[$key] = true;
        }
    } catch (Throwable $exception) {
        error_log('theme_load failed: ' . $exception->getMessage());
    }

    return [
        'preset' => $presetInfo['preset'],
        'preset_source' => $presetInfo['source'],
        'values' => $values,
        'sources' => $sources,
        /* Only DB overrides are emitted inline. Preset/default values stay CSS-owned. */
        'css_variables' => $cssVariables,
    ];
}

function theme_fields_payload(array $loaded): array
{
    $fields = [];
    foreach (theme_definition() as $key => $meta) {
        $default = theme_css_hex($meta['css']);
        $fields[] = [
            'key' => $key,
            'css_variable' => $meta['css'],
            'label' => $meta['label'],
            'group' => $meta['group'],
            'default' => $default,
            'value' => $loaded['values'][$key] ?? $default,
            'source' => $loaded['sources'][$key] ?? 'theme.css',
        ];
    }
    return $fields;
}

function theme_presets_payload(): array
{
    $items = [];
    foreach (theme_presets() as $id => $meta) {
        $items[] = array_merge(['id' => $id], $meta);
    }
    return $items;
}

function theme_validate_values(array $colors, bool $allowEmpty = false): array
{
    $definition = theme_definition();
    $clean = [];
    $errors = [];
    foreach ($colors as $key => $value) {
        if (!isset($definition[$key])) {
            $errors[$key] = 'Unknown theme setting.';
            continue;
        }
        $normalized = theme_normalize_hex($value);
        if ($normalized === null) {
            $errors[$key] = 'Enter a valid HEX color such as #2563EB.';
            continue;
        }
        $clean[$key] = $normalized;
    }
    if ($errors !== []) {
        json_error('Please correct the theme colors.', 422, $errors);
    }
    if (!$allowEmpty && $clean === []) {
        json_error('No theme colors were supplied.', 422);
    }
    return $clean;
}

function theme_save_values(array $colors, int $userId, $branchId = null): array
{
    $definition = theme_definition();
    $clean = theme_validate_values($colors, true);
    foreach ($clean as $key => $value) {
        save_app_setting($key, $value, 'Theme: ' . $definition[$key]['label'], $userId, $branchId);
    }
    return $clean;
}

function theme_save_preset(string $preset, int $userId, $branchId = null): string
{
    $normalized = theme_normalize_preset($preset);
    if ($normalized === null) {
        throw new InvalidArgumentException('Select a valid theme preset.');
    }
    save_app_setting('theme_preset', $normalized, 'Theme preset', $userId, $branchId);
    return $normalized;
}

function theme_clear_color_overrides($branchId = null): void
{
    $keys = array_keys(theme_definition());
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    if ($branchId === null) {
        $stmt = db()->prepare("DELETE FROM app_settings WHERE branch_id IS NULL AND setting_key IN ($placeholders)");
        $stmt->execute($keys);
        return;
    }
    $stmt = db()->prepare("DELETE FROM app_settings WHERE branch_id = ? AND setting_key IN ($placeholders)");
    $stmt->execute(array_merge([(int) $branchId], $keys));
}

/** Reset removes preset and colour overrides for the selected scope. */
function theme_reset_scope(int $userId, $branchId = null): void
{
    if ($branchId === null) {
        $stmt = db()->prepare("DELETE FROM app_settings WHERE branch_id IS NULL AND setting_key LIKE 'theme_%'");
        $stmt->execute();
        return;
    }
    $stmt = db()->prepare("DELETE FROM app_settings WHERE branch_id = ? AND setting_key LIKE 'theme_%'");
    $stmt->execute([(int) $branchId]);
}
