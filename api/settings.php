<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/* ========================================================================== */
/* COMMON SETTINGS SCOPE                                                       */
/* ========================================================================== */

function settings_scope(array $user, array $data = [])
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

function settings_scope_metadata(array $user, $branchId, bool $includeBranches = false): array
{
    $branchName = null;
    $companyName = null;
    $companyId = null;

    if ($branchId !== null) {
        $stmt = db()->prepare(
            'SELECT b.id, b.branch_name, b.company_id, c.company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $branchId]);
        $row = $stmt->fetch();
        if ($row) {
            $branchName = (string) $row['branch_name'];
            $companyName = (string) $row['company_name'];
            $companyId = (int) $row['company_id'];
        }
    }

    $branches = [];
    if ($includeBranches && (int) $user['role_type'] === 2) {
        $branches = db()->query(
            'SELECT b.id, b.branch_name, b.branch_code, b.company_id, c.company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.status = 1 AND c.status = 1
             ORDER BY c.company_name ASC, b.branch_name ASC'
        )->fetchAll();
    }

    return [
        'branch_id' => $branchId,
        'company_id' => $companyId,
        'scope' => $branchId === null ? 'Platform Default' : 'Branch',
        'branch_name' => $branchName,
        'company_name' => $companyName,
        'branches' => $branches,
    ];
}

function settings_has_action(array $access, int $actionId): bool
{
    return in_array($actionId, array_map('intval', $access['actions'] ?? []), true);
}

function settings_exact_app_values($branchId, array $keys): array
{
    if ($keys === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($keys) as $index => $key) {
        $ph = ':key_' . $index;
        $placeholders[] = $ph;
        $params[$ph] = (string) $key;
    }

    if ($branchId === null) {
        $sql = 'SELECT setting_key, setting_value
                FROM app_settings
                WHERE branch_id IS NULL
                  AND setting_key IN (' . implode(',', $placeholders) . ')';
    } else {
        $sql = 'SELECT setting_key, setting_value
                FROM app_settings
                WHERE branch_id = :branch_id
                  AND setting_key IN (' . implode(',', $placeholders) . ')';
        $params[':branch_id'] = (int) $branchId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['setting_key']] = (string) $row['setting_value'];
    }
    return $map;
}

function settings_delete_app_keys($branchId, array $keys): void
{
    if ($keys === []) {
        return;
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($keys) as $index => $key) {
        $ph = ':key_' . $index;
        $placeholders[] = $ph;
        $params[$ph] = (string) $key;
    }

    if ($branchId === null) {
        $sql = 'DELETE FROM app_settings
                WHERE branch_id IS NULL
                  AND setting_key IN (' . implode(',', $placeholders) . ')';
    } else {
        $sql = 'DELETE FROM app_settings
                WHERE branch_id = :branch_id
                  AND setting_key IN (' . implode(',', $placeholders) . ')';
        $params[':branch_id'] = (int) $branchId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

/* ========================================================================== */
/* GENERAL SETTINGS                                                            */
/* ========================================================================== */

function settings_general_definitions(): array
{
    return [
        'business_name' => 'Business / company display name.',
        'business_short_name' => 'Short business name.',
        'legal_name' => 'Registered legal name.',
        'company_logo' => 'Common company / branch logo.',
        'digital_signature' => 'Digital signature image used on invoices, PDFs and documents.',
        'gst_number' => 'GST registration number.',
        'pan_number' => 'PAN number.',
        'business_email' => 'Primary business email.',
        'business_mobile' => 'Primary business mobile.',
        'website' => 'Business website.',
        'address_line_1' => 'Primary address line.',
        'address_line_2' => 'Secondary address line.',
        'city' => 'Business city.',
        'state' => 'Business state.',
        'pincode' => 'Business pincode.',
        'country' => 'Business country.',
        'currency_code' => 'Default currency ISO code.',
        'currency_symbol' => 'Default currency symbol.',
        'timezone' => 'Default application timezone.',
        'date_format' => 'Default display date format.',
        'financial_year_start' => 'Financial year start in MM-DD format.',
        'invoice_footer' => 'Default invoice footer.',
        'terms_conditions' => 'Default document terms and conditions.',
        'authorized_signatory' => 'Default authorized signatory name.',
    ];
}

function settings_general_defaults(array $meta): array
{
    $fallbackName = trim((string) ($meta['company_name'] ?? ''));
    if ($fallbackName === '') {
        $fallbackName = trim((string) env_value('APP_NAME', 'Amirtham Integrated Management'));
    }

    return [
        'business_name' => $fallbackName,
        'business_short_name' => trim((string) env_value('APP_SHORT_NAME', 'Amirtham')),
        'legal_name' => '',
        'company_logo' => '',
        'digital_signature' => '',
        'gst_number' => '',
        'pan_number' => '',
        'business_email' => '',
        'business_mobile' => '',
        'website' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
        'country' => 'India',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'timezone' => 'Asia/Kolkata',
        'date_format' => 'd-m-Y',
        'financial_year_start' => '04-01',
        'invoice_footer' => '',
        'terms_conditions' => '',
        'authorized_signatory' => '',
    ];
}

function settings_asset_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^(?:https?:)?//~i', $path) || strpos($path, 'data:') === 0 || strpos($path, 'blob:') === 0) {
        return $path;
    }

    $baseUrl = rtrim((string) env_value('UPLOAD_BASE_URL', ''), '/');
    if ($baseUrl !== '' && strpos($path, 'uploads/') === 0) {
        return $baseUrl . '/' . ltrim(substr($path, strlen('uploads/')), '/');
    }

    return ltrim(str_replace('\\', '/', $path), '/');
}

function settings_general_load(array $user, array $access, $branchId): array
{
    $meta = settings_scope_metadata($user, $branchId, false);
    $definitions = settings_general_definitions();
    $keys = array_keys($definitions);
    $defaults = settings_general_defaults($meta);
    $exact = settings_exact_app_values($branchId, $keys);
    $platformExact = $branchId === null ? $exact : settings_exact_app_values(null, $keys);

    $values = [];
    $sources = [];
    foreach ($keys as $key) {
        $values[$key] = (string) app_setting($key, $defaults[$key] ?? '', $branchId);
        if (array_key_exists($key, $exact)) {
            $sources[$key] = $branchId === null ? 'platform' : 'branch';
        } elseif ($branchId !== null && array_key_exists($key, $platformExact)) {
            $sources[$key] = 'platform';
        } else {
            $sources[$key] = 'default';
        }
    }

    return array_merge($meta, [
        'section' => 'general',
        'can_manage' => settings_has_action($access, ACTION_MANAGE_APP_SETTINGS),
        'has_scope_override' => $exact !== [],
        'is_inherited' => $branchId !== null && $exact === [],
        'values' => $values,
        'sources' => $sources,
        'logo_url' => settings_asset_url((string) ($values['company_logo'] ?? '')),
        'signature_url' => settings_asset_url((string) ($values['digital_signature'] ?? '')),
    ]);
}

function settings_validate_general(array $data): array
{
    $businessName = preg_replace('/\s+/', ' ', trim((string) ($data['business_name'] ?? '')));
    if ($businessName === '' || strlen($businessName) > 190) {
        json_error('Enter a valid Business Name.', 422, ['business_name' => 'Business Name is required.']);
    }

    $shortName = preg_replace('/\s+/', ' ', trim((string) ($data['business_short_name'] ?? '')));
    if (strlen($shortName) > 100) {
        json_error('Business Short Name is too long.', 422);
    }

    $legalName = preg_replace('/\s+/', ' ', trim((string) ($data['legal_name'] ?? '')));
    if (strlen($legalName) > 190) {
        json_error('Legal Name is too long.', 422);
    }

    $gst = strtoupper(trim((string) ($data['gst_number'] ?? '')));
    if ($gst !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst)) {
        json_error('Enter a valid GST Number.', 422, ['gst_number' => 'Invalid GST Number.']);
    }

    $pan = strtoupper(trim((string) ($data['pan_number'] ?? '')));
    if ($pan !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
        json_error('Enter a valid PAN Number.', 422, ['pan_number' => 'Invalid PAN Number.']);
    }

    $email = trim((string) ($data['business_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Enter a valid Business Email.', 422, ['business_email' => 'Invalid email address.']);
    }

    $mobile = preg_replace('/\D+/', '', (string) ($data['business_mobile'] ?? ''));
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        json_error('Enter a valid 10-digit Business Mobile.', 422, ['business_mobile' => 'Invalid mobile number.']);
    }

    $pincode = preg_replace('/\D+/', '', (string) ($data['pincode'] ?? ''));
    if ($pincode !== '' && !preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
        json_error('Enter a valid Pincode.', 422, ['pincode' => 'Invalid pincode.']);
    }

    $currencyCode = strtoupper(trim((string) ($data['currency_code'] ?? 'INR')));
    if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
        json_error('Currency Code must contain exactly 3 letters.', 422);
    }

    $currencySymbol = trim((string) ($data['currency_symbol'] ?? '₹'));
    if ($currencySymbol === '' || strlen($currencySymbol) > 12) {
        json_error('Enter a valid Currency Symbol.', 422);
    }

    $timezone = trim((string) ($data['timezone'] ?? 'Asia/Kolkata'));
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        json_error('Select a valid Timezone.', 422);
    }

    $dateFormat = trim((string) ($data['date_format'] ?? 'd-m-Y'));
    $allowedDateFormats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'];
    if (!in_array($dateFormat, $allowedDateFormats, true)) {
        json_error('Select a valid Date Format.', 422);
    }

    $financialYearStart = trim((string) ($data['financial_year_start'] ?? '04-01'));
    if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $financialYearStart)) {
        json_error('Financial Year Start must use MM-DD format.', 422);
    }
    list($fyMonth, $fyDay) = array_map('intval', explode('-', $financialYearStart));
    if (!checkdate($fyMonth, $fyDay, 2000)) {
        json_error('Financial Year Start is not a valid month/day.', 422);
    }

    $website = trim((string) ($data['website'] ?? ''));
    if (strlen($website) > 190) {
        json_error('Website value is too long.', 422);
    }

    $simpleLimits = [
        'address_line_1' => 255,
        'address_line_2' => 255,
        'city' => 100,
        'state' => 100,
        'country' => 100,
        'authorized_signatory' => 150,
    ];
    $clean = [];
    foreach ($simpleLimits as $field => $limit) {
        $clean[$field] = trim((string) ($data[$field] ?? ''));
        if (strlen($clean[$field]) > $limit) {
            json_error(ucwords(str_replace('_', ' ', $field)) . ' is too long.', 422);
        }
    }

    $invoiceFooter = trim((string) ($data['invoice_footer'] ?? ''));
    $terms = trim((string) ($data['terms_conditions'] ?? ''));
    if (strlen($invoiceFooter) > 2000 || strlen($terms) > 5000) {
        json_error('Invoice Footer or Terms & Conditions is too long.', 422);
    }

    return [
        'business_name' => $businessName,
        'business_short_name' => $shortName,
        'legal_name' => $legalName,
        'gst_number' => $gst,
        'pan_number' => $pan,
        'business_email' => $email,
        'business_mobile' => $mobile,
        'website' => $website,
        'address_line_1' => $clean['address_line_1'],
        'address_line_2' => $clean['address_line_2'],
        'city' => $clean['city'],
        'state' => $clean['state'],
        'pincode' => $pincode,
        'country' => $clean['country'],
        'currency_code' => $currencyCode,
        'currency_symbol' => $currencySymbol,
        'timezone' => $timezone,
        'date_format' => $dateFormat,
        'financial_year_start' => $financialYearStart,
        'invoice_footer' => $invoiceFooter,
        'terms_conditions' => $terms,
        'authorized_signatory' => $clean['authorized_signatory'],
    ];
}

function settings_save_general(array $user, array $access, $branchId, array $data): array
{
    $meta = settings_scope_metadata($user, $branchId, false);
    $definitions = settings_general_definitions();
    $defaults = settings_general_defaults($meta);
    $values = settings_validate_general($data);

    /* Use only the existing common/global upload helper from bootstrap.php. */
    $logoFiles = save_form_uploads('company_logo', 'settings', 0, 1);
    $signatureFiles = save_form_uploads('digital_signature', 'settings', 0, 1);

    $logoPath = $logoFiles !== []
        ? trim((string) ($logoFiles[0]['path'] ?? ''))
        : null;
    $signaturePath = $signatureFiles !== []
        ? trim((string) ($signatureFiles[0]['path'] ?? ''))
        : null;

    $removeLogo = !empty($data['remove_logo']);
    $removeSignature = !empty($data['remove_signature']);

    $pdo = db();
    try {
        $pdo->beginTransaction();

        foreach ($values as $key => $value) {
            $description = isset($definitions[$key])
                ? $definitions[$key]
                : 'General application setting.';

            if ($branchId !== null) {
                $parentValue = (string) app_setting(
                    $key,
                    $defaults[$key] ?? '',
                    null
                );
                if ((string) $value === $parentValue) {
                    settings_delete_app_keys($branchId, [$key]);
                    continue;
                }
            }

            save_app_setting(
                $key,
                (string) $value,
                $description,
                (int) $user['id'],
                $branchId
            );
        }

        if ($removeLogo) {
            save_app_setting(
                'company_logo',
                '',
                $definitions['company_logo'],
                (int) $user['id'],
                $branchId
            );
        } elseif ($logoPath !== null) {
            save_app_setting(
                'company_logo',
                $logoPath,
                $definitions['company_logo'],
                (int) $user['id'],
                $branchId
            );
        }

        if ($removeSignature) {
            save_app_setting(
                'digital_signature',
                '',
                $definitions['digital_signature'],
                (int) $user['id'],
                $branchId
            );
        } elseif ($signaturePath !== null) {
            save_app_setting(
                'digital_signature',
                $signaturePath,
                $definitions['digital_signature'],
                (int) $user['id'],
                $branchId
            );
        }

        audit_log((int) $user['id'], ACTION_MANAGE_APP_SETTINGS, [
            'company_id' => $meta['company_id'] ?? $user['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'new_data' => array_merge($values, [
                'company_logo_changed' => $logoPath !== null,
                'company_logo_removed' => $removeLogo,
                'digital_signature_changed' => $signaturePath !== null,
                'digital_signature_removed' => $removeSignature,
            ]),
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return ['branch_id' => $branchId];
}

function settings_reset_general(array $user, array $access, $branchId): array
{
    $meta = settings_scope_metadata($user, $branchId, false);

    settings_delete_app_keys($branchId, array_keys(settings_general_definitions()));

    audit_log((int) $user['id'], ACTION_MANAGE_APP_SETTINGS, [
        'company_id' => $meta['company_id'] ?? $user['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'new_data' => ['general_settings_reset' => true],
    ]);

    return ['branch_id' => $branchId];
}

/* ========================================================================== */
/* SECURITY SETTINGS                                                           */
/* ========================================================================== */

function settings_security_load(array $user, array $access, $branchId): array
{
    $meta = settings_scope_metadata($user, $branchId, false);
    $keys = ['absolute_login_expiry_minutes', 'idle_expiry_minutes'];
    $exact = settings_exact_app_values($branchId, $keys);

    return array_merge($meta, [
        'section' => 'security',
        'can_manage' => settings_has_action($access, ACTION_MANAGE_APP_SETTINGS),
        'has_scope_override' => $exact !== [],
        'is_inherited' => $branchId !== null && $exact === [],
        'absolute_login_expiry_minutes' => auth_absolute_login_expiry_minutes($branchId),
        'idle_expiry_minutes' => auth_idle_expiry_minutes($branchId),
        'token_expiry_minutes' => auth_absolute_login_expiry_minutes($branchId),
        'idle_timeout_minutes' => auth_idle_expiry_minutes($branchId),
    ]);
}

function settings_save_security(array $user, array $access, $branchId, array $data): array
{
    $absoluteRaw = isset($data['absolute_login_expiry_minutes'])
        ? $data['absolute_login_expiry_minutes']
        : ($data['token_expiry_minutes'] ?? null);
    $idleRaw = isset($data['idle_expiry_minutes'])
        ? $data['idle_expiry_minutes']
        : ($data['idle_timeout_minutes'] ?? null);

    if ($absoluteRaw === null || $absoluteRaw === '' || $idleRaw === null || $idleRaw === '') {
        json_error('Please complete the required fields.', 422, [
            'absolute_login_expiry_minutes' => 'This field is required.',
            'idle_expiry_minutes' => 'This field is required.',
        ]);
    }

    $absoluteMinutes = filter_var(
        $absoluteRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 5, 'max_range' => 43200]]
    );
    if ($absoluteMinutes === false) {
        json_error('Absolute login expiry must be between 5 minutes and 30 days.', 422);
    }

    $idleMinutes = filter_var(
        $idleRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 5, 'max_range' => 10080]]
    );
    if ($idleMinutes === false) {
        json_error('Idle expiry must be between 5 minutes and 7 days.', 422);
    }

    if ((int) $idleMinutes > (int) $absoluteMinutes) {
        json_error('Idle expiry cannot be greater than the absolute login expiry.', 422);
    }

    save_app_setting(
        'absolute_login_expiry_minutes',
        (string) $absoluteMinutes,
        'Maximum login lifetime in minutes. Activity never extends this limit.',
        (int) $user['id'],
        $branchId
    );
    save_app_setting(
        'idle_expiry_minutes',
        (string) $idleMinutes,
        'Rolling inactivity expiry in minutes.',
        (int) $user['id'],
        $branchId
    );

    $meta = settings_scope_metadata($user, $branchId, false);
    audit_log((int) $user['id'], ACTION_MANAGE_APP_SETTINGS, [
        'company_id' => $meta['company_id'] ?? $user['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'new_data' => [
            'absolute_login_expiry_minutes' => (int) $absoluteMinutes,
            'idle_expiry_minutes' => (int) $idleMinutes,
        ],
    ]);

    return [
        'branch_id' => $branchId,
        'absolute_login_expiry_minutes' => (int) $absoluteMinutes,
        'idle_expiry_minutes' => (int) $idleMinutes,
    ];
}

function settings_reset_security(array $user, array $access, $branchId): array
{
    settings_delete_app_keys($branchId, [
        'absolute_login_expiry_minutes',
        'idle_expiry_minutes',
        'token_expiry_minutes',
        'idle_timeout_minutes',
    ]);

    $meta = settings_scope_metadata($user, $branchId, false);
    audit_log((int) $user['id'], ACTION_MANAGE_APP_SETTINGS, [
        'company_id' => $meta['company_id'] ?? $user['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'new_data' => ['security_settings_reset' => true],
    ]);

    return ['branch_id' => $branchId];
}

/* ========================================================================== */
/* THEME SETTINGS                                                              */
/* ========================================================================== */

function settings_theme_load(array $user, array $access, $branchId): array
{
    $loaded = theme_load($branchId);
    $meta = settings_scope_metadata($user, $branchId, false);

    return array_merge($meta, [
        'section' => 'theme',
        'can_update' => settings_has_action($access, ACTION_MANAGE_THEME),
        'preset' => $loaded['preset'],
        'preset_source' => $loaded['preset_source'],
        'presets' => theme_presets_payload(),
        'values' => $loaded['values'],
        'sources' => $loaded['sources'],
        'css_variables' => $loaded['css_variables'],
        'fields' => theme_fields_payload($loaded),
    ]);
}

function settings_save_theme(array $user, array $access, $branchId, array $data): array
{
    $isReset = !empty($data['reset']) || (($data['action'] ?? '') === 'reset');
    $preset = theme_normalize_preset($data['preset'] ?? theme_default_preset());
    if (!$isReset && $preset === null) {
        json_error('Select a valid theme preset.', 422);
    }

    $clearColors = !empty($data['clear_colors']);
    $colors = [];
    if (!$isReset) {
        $colors = isset($data['colors']) && is_array($data['colors']) ? $data['colors'] : [];
        $colors = theme_validate_values($colors, true);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

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

        $meta = settings_scope_metadata($user, $branchId, false);
        audit_log((int) $user['id'], ACTION_MANAGE_THEME, [
            'company_id' => $meta['company_id'] ?? $user['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'new_data' => $isReset
                ? ['theme_reset' => true]
                : [
                    'theme_preset' => $savedPreset,
                    'theme_colors' => $saved,
                    'colors_replaced' => $clearColors,
                ],
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException) {
            json_error($exception->getMessage(), 422);
        }
        error_log('Theme save failed: ' . $exception->getMessage());
        json_error('Unable to save theme settings.', 500);
    }

    return settings_theme_load($user, $access, $branchId);
}

/* ========================================================================== */
/* MAIL SETTINGS                                                               */
/* ========================================================================== */

function settings_mail_public(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'branch_id' => $row['branch_id'] === null ? null : (int) $row['branch_id'],
        'smtp_host' => (string) $row['smtp_host'],
        'smtp_port' => (int) $row['smtp_port'],
        'smtp_encryption' => (string) $row['smtp_encryption'],
        'smtp_auth' => (int) $row['smtp_auth'],
        'smtp_username' => (string) ($row['smtp_username'] ?? ''),
        'smtp_password_configured' => !empty($row['smtp_password']),
        'from_email' => (string) $row['from_email'],
        'from_name' => (string) $row['from_name'],
        'reply_to_email' => (string) ($row['reply_to_email'] ?? ''),
        'reply_to_name' => (string) ($row['reply_to_name'] ?? ''),
        'timeout_seconds' => (int) ($row['timeout_seconds'] ?? 20),
        'status' => (int) $row['status'],
        'updated_at' => $row['updated_at'],
        'source_scope' => (string) ($row['_source_scope'] ?? ($row['branch_id'] === null ? 'platform' : 'branch')),
    ];
}

function settings_validate_mail(array $data, ?array $existingSource = null): array
{
    require_fields($data, ['smtp_host', 'smtp_port', 'smtp_encryption', 'from_email', 'from_name']);

    $host = trim((string) $data['smtp_host']);
    $port = (int) $data['smtp_port'];
    $encryption = strtolower(trim((string) $data['smtp_encryption']));
    $smtpAuth = isset($data['smtp_auth']) ? normalize_status($data['smtp_auth']) : 1;
    $username = trim((string) ($data['smtp_username'] ?? ''));
    $password = (string) ($data['smtp_password'] ?? '');
    $fromEmail = trim((string) $data['from_email']);
    $fromName = trim((string) $data['from_name']);
    $replyEmail = trim((string) ($data['reply_to_email'] ?? ''));
    $replyName = trim((string) ($data['reply_to_name'] ?? ''));
    $timeout = isset($data['timeout_seconds']) ? (int) $data['timeout_seconds'] : 20;

    if ($host === '' || strlen($host) > 190) {
        json_error('Enter a valid SMTP host.', 422);
    }
    if ($port < 1 || $port > 65535) {
        json_error('SMTP port must be between 1 and 65535.', 422);
    }
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        json_error('SMTP encryption must be TLS, SSL or None.', 422);
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        json_error('Enter a valid From Email address.', 422);
    }
    if ($replyEmail !== '' && !filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        json_error('Enter a valid Reply-To Email address.', 422);
    }
    if ($timeout < 5 || $timeout > 120) {
        json_error('SMTP timeout must be between 5 and 120 seconds.', 422);
    }
    if ($smtpAuth === 1 && $username === '') {
        json_error('SMTP username is required when authentication is enabled.', 422);
    }

    $encryptedPassword = '';
    $plainPassword = '';
    if ($password !== '') {
        $encryptedPassword = encryptSecret($password);
        $plainPassword = $password;
    } elseif ($existingSource && !empty($existingSource['smtp_password'])) {
        $encryptedPassword = (string) $existingSource['smtp_password'];
        $plainPassword = decryptSecret($encryptedPassword);
    } elseif ($smtpAuth === 1) {
        json_error('SMTP password is required when authentication is enabled.', 422);
    }

    return [
        'smtp_host' => $host,
        'smtp_port' => $port,
        'smtp_encryption' => $encryption,
        'smtp_auth' => $smtpAuth,
        'smtp_username' => $username,
        'smtp_password' => $encryptedPassword,
        'smtp_password_plain' => $plainPassword,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'reply_to_email' => $replyEmail,
        'reply_to_name' => $replyName,
        'timeout_seconds' => $timeout,
        'status' => isset($data['status']) ? normalize_status($data['status']) : 1,
    ];
}

function settings_mail_load(array $user, array $access, $branchId): array
{
    $exact = mail_settings_record($branchId, false);
    $effective = $exact ?: mail_settings_record($branchId, true);
    $meta = settings_scope_metadata($user, $branchId, false);

    return array_merge($meta, [
        'section' => 'mail',
        'settings' => settings_mail_public($effective),
        'has_scope_override' => $exact !== null,
        'is_inherited' => $branchId !== null && $exact === null && $effective !== null,
        'can_manage' => settings_has_action($access, ACTION_MANAGE_SMTP),
        'phpmailer_installed' => class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
    ]);
}

function settings_save_mail(array $user, array $access, $branchId, array $data): array
{
    $exact = mail_settings_record($branchId, false);
    $fallback = $exact ?: mail_settings_record($branchId, true);
    $normalized = settings_validate_mail($data, $fallback);

    if (($data['action'] ?? '') === 'test') {
        require_fields($data, ['test_email']);
        $testEmail = trim((string) $data['test_email']);
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            json_error('Enter a valid test email address.', 422);
        }

        send_mail_with_settings(
            $normalized,
            $testEmail,
            trim((string) env_value('APP_NAME', 'Amirtham Integrated Management')) . ' - SMTP Test Email',
            '<h2>SMTP configuration successful</h2><p>This test email confirms that the outgoing mail configuration is working.</p>'
        );

        return ['test_sent' => true];
    }

    $params = [
        ':branch_id' => $branchId,
        ':smtp_host' => $normalized['smtp_host'],
        ':smtp_port' => $normalized['smtp_port'],
        ':smtp_encryption' => $normalized['smtp_encryption'],
        ':smtp_auth' => $normalized['smtp_auth'],
        ':smtp_username' => $normalized['smtp_username'] !== '' ? $normalized['smtp_username'] : null,
        ':smtp_password' => $normalized['smtp_password'] !== '' ? $normalized['smtp_password'] : null,
        ':from_email' => $normalized['from_email'],
        ':from_name' => $normalized['from_name'],
        ':reply_to_email' => $normalized['reply_to_email'] !== '' ? $normalized['reply_to_email'] : null,
        ':reply_to_name' => $normalized['reply_to_name'] !== '' ? $normalized['reply_to_name'] : null,
        ':timeout_seconds' => $normalized['timeout_seconds'],
        ':status' => $normalized['status'],
    ];

    if ($exact) {
        $params[':id'] = (int) $exact['id'];
        $stmt = db()->prepare(
            'UPDATE email_settings SET
                smtp_host = :smtp_host,
                smtp_port = :smtp_port,
                smtp_encryption = :smtp_encryption,
                smtp_auth = :smtp_auth,
                smtp_username = :smtp_username,
                smtp_password = :smtp_password,
                from_email = :from_email,
                from_name = :from_name,
                reply_to_email = :reply_to_email,
                reply_to_name = :reply_to_name,
                timeout_seconds = :timeout_seconds,
                status = :status,
                updated_at = NOW()
             WHERE id = :id'
        );
        unset($params[':branch_id']);
        $stmt->execute($params);
        $settingsId = (int) $exact['id'];
    } else {
        $params[':created_by'] = (int) $user['id'];
        $stmt = db()->prepare(
            'INSERT INTO email_settings
             (branch_id, smtp_host, smtp_port, smtp_encryption, smtp_auth,
              smtp_username, smtp_password, from_email, from_name,
              reply_to_email, reply_to_name, timeout_seconds, status,
              created_by, created_at, updated_at)
             VALUES
             (:branch_id, :smtp_host, :smtp_port, :smtp_encryption, :smtp_auth,
              :smtp_username, :smtp_password, :from_email, :from_name,
              :reply_to_email, :reply_to_name, :timeout_seconds, :status,
              :created_by, NOW(), NOW())'
        );
        $stmt->execute($params);
        $settingsId = (int) db()->lastInsertId();
    }

    $meta = settings_scope_metadata($user, $branchId, false);
    audit_log((int) $user['id'], ACTION_MANAGE_SMTP, [
        'company_id' => $meta['company_id'] ?? $user['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'record_id' => $settingsId,
        'new_data' => [
            'smtp_host' => $normalized['smtp_host'],
            'smtp_port' => $normalized['smtp_port'],
            'smtp_encryption' => $normalized['smtp_encryption'],
            'smtp_auth' => $normalized['smtp_auth'],
            'smtp_username' => $normalized['smtp_username'],
            'from_email' => $normalized['from_email'],
            'from_name' => $normalized['from_name'],
            'reply_to_email' => $normalized['reply_to_email'],
            'timeout_seconds' => $normalized['timeout_seconds'],
            'status' => $normalized['status'],
            'smtp_password_changed' => (string) ($data['smtp_password'] ?? '') !== '',
        ],
    ]);

    return [
        'settings_id' => $settingsId,
        'smtp_password_configured' => $normalized['smtp_auth'] === 0 || $normalized['smtp_password'] !== '',
    ];
}

function settings_reset_mail(array $user, array $access, $branchId): array
{
    if ($branchId === null) {
        $stmt = db()->prepare('DELETE FROM email_settings WHERE branch_id IS NULL');
        $stmt->execute();
    } else {
        $stmt = db()->prepare('DELETE FROM email_settings WHERE branch_id = :branch_id');
        $stmt->execute([':branch_id' => (int) $branchId]);
    }

    $meta = settings_scope_metadata($user, $branchId, false);
    audit_log((int) $user['id'], ACTION_MANAGE_SMTP, [
        'company_id' => $meta['company_id'] ?? $user['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int) $access['menu']['id'],
        'new_data' => ['mail_settings_reset' => true],
    ]);

    return ['branch_id' => $branchId];
}

/* ========================================================================== */
/* ROUTER                                                                      */
/* ========================================================================== */

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('settings.php', ACTION_VIEW);
    $user = $access['user'];
    $section = strtolower(trim((string) ($_GET['section'] ?? 'general')));

    if ($section === 'bootstrap') {
        $meta = settings_scope_metadata(
            $user,
            (int) $user['role_type'] === 2 ? null : settings_scope($user),
            true
        );

        json_success('Settings ready.', [
            'user' => [
                'id' => (int) $user['id'],
                'role_type' => (int) $user['role_type'],
                'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
                'branch_name' => $user['branch_name'] ?? null,
                'company_name' => $user['company_name'] ?? null,
            ],
            'branches' => $meta['branches'],
            'permissions' => [
                'general' => settings_has_action($access, ACTION_MANAGE_APP_SETTINGS),
                'security' => settings_has_action($access, ACTION_MANAGE_APP_SETTINGS),
                'theme' => settings_has_action($access, ACTION_MANAGE_THEME),
                'mail' => settings_has_action($access, ACTION_MANAGE_SMTP),
            ],
        ]);
    }

    $branchId = settings_scope($user);

    switch ($section) {
        case 'general':
            json_success('General settings loaded.', settings_general_load($user, $access, $branchId));
            break;

        case 'security':
            json_success('Security settings loaded.', settings_security_load($user, $access, $branchId));
            break;

        case 'theme':
            json_success('Theme settings loaded.', settings_theme_load($user, $access, $branchId));
            break;

        case 'mail':
            json_success('Mail settings loaded.', settings_mail_load($user, $access, $branchId));
            break;

        default:
            json_error('Invalid settings section.', 422);
    }
}

if ($method === 'POST' || $method === 'PUT') {
    $data = request_data();
    $section = strtolower(trim((string) ($data['section'] ?? '')));
    if ($section === '') {
        json_error('Settings section is required.', 422);
    }

    switch ($section) {
        case 'general':
            $access = require_permission('settings.php', ACTION_MANAGE_APP_SETTINGS);
            $user = $access['user'];
            $branchId = settings_scope($user, $data);
            if (($data['action'] ?? '') === 'reset') {
                json_success('General settings reset successfully.', settings_reset_general($user, $access, $branchId));
            }
            json_success('General settings updated successfully.', settings_save_general($user, $access, $branchId, $data));
            break;

        case 'security':
            $access = require_permission('settings.php', ACTION_MANAGE_APP_SETTINGS);
            $user = $access['user'];
            $branchId = settings_scope($user, $data);
            if (($data['action'] ?? '') === 'reset') {
                json_success('Security settings reset successfully.', settings_reset_security($user, $access, $branchId));
            }
            json_success('Security settings updated successfully.', settings_save_security($user, $access, $branchId, $data));
            break;

        case 'theme':
            $access = require_permission('settings.php', ACTION_MANAGE_THEME);
            $user = $access['user'];
            $branchId = settings_scope($user, $data);
            $savedTheme = settings_save_theme($user, $access, $branchId, $data);
            json_success(
                (!empty($data['reset']) || (($data['action'] ?? '') === 'reset'))
                    ? 'Theme reset successfully.'
                    : 'Theme preset and settings saved successfully.',
                $savedTheme
            );
            break;

        case 'mail':
            $access = require_permission('settings.php', ACTION_MANAGE_SMTP);
            $user = $access['user'];
            $branchId = settings_scope($user, $data);
            if (($data['action'] ?? '') === 'reset') {
                json_success('Mail settings reset successfully.', settings_reset_mail($user, $access, $branchId));
            }
            $result = settings_save_mail($user, $access, $branchId, $data);
            json_success(
                !empty($result['test_sent']) ? 'Test email sent successfully.' : 'Mail settings saved successfully.',
                $result
            );
            break;

        default:
            json_error('Invalid settings section.', 422);
    }
}

json_error('Method not allowed.', 405);
