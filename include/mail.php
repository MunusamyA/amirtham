<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Find the active SMTP row for a scope.
 * Branch configuration wins; when it does not exist, platform/default SMTP is used.
 */
function mail_settings_record($branchId = null, bool $allowFallback = true): ?array
{
    if ($branchId !== null) {
        $stmt = db()->prepare(
            'SELECT * FROM email_settings
             WHERE branch_id = :branch_id AND status = 1
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':branch_id' => (int) $branchId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['_source_scope'] = 'branch';
            return $row;
        }
        if (!$allowFallback) {
            return null;
        }
    }

    $stmt = db()->query(
        'SELECT * FROM email_settings
         WHERE branch_id IS NULL AND status = 1
         ORDER BY id DESC LIMIT 1'
    );
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['_source_scope'] = 'platform';
    return $row;
}

function load_mail_settings($branchId = null): array
{
    $settings = mail_settings_record($branchId, true);
    if (!$settings) {
        throw new RuntimeException('Active SMTP settings were not found. Configure Mail Settings first.');
    }

    $settings['smtp_password_plain'] = '';
    if (!empty($settings['smtp_password'])) {
        $settings['smtp_password_plain'] = decryptSecret((string) $settings['smtp_password']);
    }
    unset($settings['smtp_password']);
    return $settings;
}

/** Configure one PHPMailer instance from a normalized SMTP settings array. */
function configure_phpmailer(PHPMailer $mail, array $settings): void
{
    $mail->isSMTP();
    $mail->Host = trim((string) ($settings['smtp_host'] ?? ''));
    $mail->Port = (int) ($settings['smtp_port'] ?? 0);
    $mail->SMTPAuth = !empty($settings['smtp_auth']);
    $mail->Timeout = max(5, min(120, (int) ($settings['timeout_seconds'] ?? 20)));
    $mail->CharSet = 'UTF-8';

    if ($mail->SMTPAuth) {
        $mail->Username = (string) ($settings['smtp_username'] ?? '');
        $mail->Password = (string) ($settings['smtp_password_plain'] ?? '');
    }

    $encryption = strtolower(trim((string) ($settings['smtp_encryption'] ?? 'tls')));
    if ($encryption === 'ssl' || $encryption === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls' || $encryption === 'starttls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = '';
    }

    $fromEmail = trim((string) ($settings['from_email'] ?? ''));
    $fromName = trim((string) ($settings['from_name'] ?? ''));
    $mail->setFrom($fromEmail, $fromName);

    $replyEmail = trim((string) ($settings['reply_to_email'] ?? ''));
    if ($replyEmail !== '') {
        $mail->addReplyTo($replyEmail, trim((string) ($settings['reply_to_name'] ?? '')));
    }
}

/**
 * Generic reusable mail sender.
 *
 * $to can be a single email string or an array. Array entries may be either:
 *   'user@example.com'
 *   ['email' => 'user@example.com', 'name' => 'User Name']
 *
 * Options:
 *   alt_body, cc, bcc, reply_to, attachments
 */
function send_mail($branchId, $to, string $subject, string $htmlBody, array $options = []): bool
{
    if (!class_exists(PHPMailer::class)) {
        throw new RuntimeException('PHPMailer is not installed. Run composer install in the project folder.');
    }

    $settings = load_mail_settings($branchId);
    return send_mail_with_settings($settings, $to, $subject, $htmlBody, $options);
}

/** Send using an already-resolved settings array. Used by Test SMTP as well. */
function send_mail_with_settings(array $settings, $to, string $subject, string $htmlBody, array $options = []): bool
{
    if (!class_exists(PHPMailer::class)) {
        throw new RuntimeException('PHPMailer is not installed. Run composer install in the project folder.');
    }

    $mail = new PHPMailer(true);
    try {
        configure_phpmailer($mail, $settings);

        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $email = trim((string) ($recipient['email'] ?? ''));
                $name = trim((string) ($recipient['name'] ?? ''));
            } else {
                $email = trim((string) $recipient);
                $name = '';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid recipient email address: ' . $email);
            }
            $mail->addAddress($email, $name);
        }

        foreach ((array) ($options['cc'] ?? []) as $recipient) {
            $email = is_array($recipient) ? (string) ($recipient['email'] ?? '') : (string) $recipient;
            $name = is_array($recipient) ? (string) ($recipient['name'] ?? '') : '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($email, $name);
            }
        }
        foreach ((array) ($options['bcc'] ?? []) as $recipient) {
            $email = is_array($recipient) ? (string) ($recipient['email'] ?? '') : (string) $recipient;
            $name = is_array($recipient) ? (string) ($recipient['name'] ?? '') : '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($email, $name);
            }
        }

        if (!empty($options['reply_to']) && is_array($options['reply_to'])) {
            $replyEmail = trim((string) ($options['reply_to']['email'] ?? ''));
            if (filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->clearReplyTos();
                $mail->addReplyTo($replyEmail, trim((string) ($options['reply_to']['name'] ?? '')));
            }
        }

        foreach ((array) ($options['attachments'] ?? []) as $attachment) {
            if (is_array($attachment)) {
                $path = (string) ($attachment['path'] ?? '');
                $name = (string) ($attachment['name'] ?? '');
            } else {
                $path = (string) $attachment;
                $name = '';
            }
            if ($path !== '' && is_file($path)) {
                $name !== '' ? $mail->addAttachment($path, $name) : $mail->addAttachment($path);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = isset($options['alt_body'])
            ? (string) $options['alt_body']
            : trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));

        return $mail->send();
    } catch (Exception $exception) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        throw new RuntimeException('Email could not be sent. Check SMTP settings and the PHP error log.');
    }
}
