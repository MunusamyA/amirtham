<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function theme_branch_scope(array $user, array $data = [])
{
    if ((int) $user['role_type'] !== 2) {
        return positive_id($user['branch_id'], 'branch_id');
    }

    $value = array_key_exists('branch_id', $data)
        ? $data['branch_id']
        : (isset($_GET['branch_id']) ? $_GET['branch_id'] : null);

    if ($value === null || $value === '' || (int) $value === 0) {
        return null;
    }

    $branchId = positive_id($value, 'branch_id');
    $stmt = db()->prepare(
        'SELECT b.id
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :id AND b.status = 1 AND c.status = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $branchId]);
    if (!$stmt->fetch()) {
        json_error('Active branch was not found.', 404);
    }
    return $branchId;
}

function theme_scope_metadata(array $user, $branchId): array
{
    $branches = [];
    $branchName = null;
    $companyName = null;

    if ((int) $user['role_type'] === 2) {
        $branches = db()->query(
            'SELECT b.id, b.branch_name, b.branch_code, c.company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.status = 1 AND c.status = 1
             ORDER BY c.company_name, b.branch_name'
        )->fetchAll();
    }

    if ($branchId !== null) {
        $stmt = db()->prepare(
            'SELECT b.branch_name, c.company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $branchId]);
        $row = $stmt->fetch();
        if ($row) {
            $branchName = $row['branch_name'];
            $companyName = $row['company_name'];
        }
    }

    return [
        'branch_id' => $branchId,
        'scope' => $branchId === null ? 'Platform Default' : 'Branch',
        'branch_name' => $branchName,
        'company_name' => $companyName,
        'branches' => $branches,
    ];
}

$method = request_method();

if ($method === 'GET') {
    /* Every authenticated user needs read access to their own theme. */
    $user = require_user();
    $branchId = theme_branch_scope($user);
    $loaded = theme_load($branchId);
    $meta = theme_scope_metadata($user, $branchId);

    $canUpdate = false;
    $menuStmt = db()->prepare(
        'SELECT * FROM menus WHERE menu_path = :path AND status = 1 LIMIT 1'
    );
    $menuStmt->execute([':path' => 'theme-settings.php']);
    $settingsMenu = $menuStmt->fetch();
    if ($settingsMenu) {
        $actions = effective_actions_for_menu($user, $settingsMenu);
        $canUpdate = in_array(ACTION_MANAGE_THEME, $actions, true);
    }

    json_success('Theme loaded.', array_merge($meta, [
        'can_update' => $canUpdate,
        'preset' => $loaded['preset'],
        'preset_source' => $loaded['preset_source'],
        'presets' => theme_presets_payload(),
        'values' => $loaded['values'],
        'sources' => $loaded['sources'],
        'css_variables' => $loaded['css_variables'],
        'fields' => theme_fields_payload($loaded),
    ]));
}

if ($method === 'PUT' || $method === 'POST') {
    $access = require_permission('theme-settings.php', ACTION_MANAGE_THEME);
    $user = $access['user'];
    $data = request_data();
    $branchId = theme_branch_scope($user, $data);
    $isReset = !empty($data['reset']);
    $preset = theme_normalize_preset($data['preset'] ?? theme_default_preset());
    if (!$isReset && $preset === null) {
        json_error('Select a valid theme preset.', 422);
    }
    $clearColors = !empty($data['clear_colors']);
    $colors = [];
    if (!$isReset) {
        $colors = isset($data['colors']) && is_array($data['colors']) ? $data['colors'] : [];
        /* Empty custom colours are valid when a preset alone is being saved. */
        $colors = theme_validate_values($colors, true);
    }

    try {
        db()->beginTransaction();

        if ($isReset) {
            theme_reset_scope((int) $user['id'], $branchId);
            $saved = [];
            $savedPreset = null;
        } else {
            if ($clearColors) {
                theme_clear_color_overrides($branchId);
            }
            $savedPreset = theme_save_preset((string) $preset, (int) $user['id'], $branchId);
            $saved = theme_save_values($colors, (int) $user['id'], $branchId);
        }

        audit_log((int) $user['id'], ACTION_MANAGE_THEME, [
            'company_id' => $user['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'new_data' => $isReset
                ? ['theme_reset' => true]
                : ['theme_preset' => $savedPreset, 'theme_colors' => $saved, 'colors_replaced' => $clearColors],
        ]);

        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($exception instanceof InvalidArgumentException) {
            json_error($exception->getMessage(), 422);
        }
        error_log('Theme save failed: ' . $exception->getMessage());
        json_error('Unable to save theme settings.', 500);
    }

    $loaded = theme_load($branchId);
    $meta = theme_scope_metadata($user, $branchId);
    json_success(
        $isReset
            ? ($branchId === null ? 'Platform theme override removed. assets/css/theme.css is now the default.' : 'Branch theme override removed. Platform theme is now inherited.')
            : 'Theme preset and settings saved successfully.',
        array_merge($meta, [
            'preset' => $loaded['preset'],
            'preset_source' => $loaded['preset_source'],
            'presets' => theme_presets_payload(),
            'values' => $loaded['values'],
            'sources' => $loaded['sources'],
            'css_variables' => $loaded['css_variables'],
            'fields' => theme_fields_payload($loaded),
        ])
    );
}

json_error('Method not allowed.', 405);
