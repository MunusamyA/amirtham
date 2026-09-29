<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function mail_scope_branch(array $user, array $data = [])
{
    if ((int) $user['role_type'] === 2) {
        $value = $data['branch_id'] ?? ($_GET['branch_id'] ?? null);
        if ($value === null || (string) $value === '') {
            return null;
        }
        return positive_id($value, 'branch_id');
    }
    return positive_id($user['branch_id'], 'branch_id');
}

function mail_scope_branches(array $user): array
{
    if ((int) $user['role_type'] !== 2) {
        return [];
    }
    return db()->query(
        'SELECT b.id, b.branch_name, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.status = 1 AND c.status = 1
         ORDER BY c.company_name, b.branch_name'
    )->fetchAll();
}

function mail_settings_public(?array $row): ?array
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

function validate_mail_input(array $data, ?array $existingSource = null): array
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

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('mail-settings.php', ACTION_VIEW);
    $user = $access['user'];
    $branchId = mail_scope_branch($user);
    $exact = mail_settings_record($branchId, false);
    $effective = $exact ?: mail_settings_record($branchId, true);

    json_success('Mail settings loaded.', [
        'branch_id' => $branchId,
        'branch_name' => $user['branch_name'] ?? null,
        'settings' => mail_settings_public($effective),
        'has_scope_override' => $exact !== null,
        'is_inherited' => $branchId !== null && $exact === null && $effective !== null,
        'can_manage' => in_array(ACTION_MANAGE_SMTP, $access['actions'], true),
        'branches' => mail_scope_branches($user),
        'phpmailer_installed' => class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    $access = require_permission('mail-settings.php', ACTION_MANAGE_SMTP);
    $user = $access['user'];
    $data = request_data();
    $branchId = mail_scope_branch($user, $data);

    $exact = mail_settings_record($branchId, false);
    $fallback = $exact ?: mail_settings_record($branchId, true);
    $normalized = validate_mail_input($data, $fallback);

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
        json_success('Test email sent successfully.');
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

    audit_log((int) $user['id'], ACTION_MANAGE_SMTP, [
        'company_id' => $user['company_id'],
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

    json_success('Mail settings saved successfully.', [
        'settings_id' => $settingsId,
        'smtp_password_configured' => $normalized['smtp_auth'] === 0 || $normalized['smtp_password'] !== '',
    ]);
}

json_error('Method not allowed.', 405);
