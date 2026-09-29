# Mail / SMTP Configuration

## Purpose

The Starter Kit has one reusable SMTP layer for all future modules. Invoice, payroll, student, patient, notification or workflow code should call `send_mail()` instead of creating a new PHPMailer configuration in each module.

## Installation

PHPMailer is declared in `composer.json`:

```bash
composer install
```

The application loads `vendor/autoload.php` automatically when it exists.

## Security

Generate `APP_ENCRYPTION_KEY` once and never change it after SMTP passwords or other secrets have been encrypted:

```text
APP_ENCRYPTION_KEY=<fixed base64 32-byte key>
```

SMTP passwords are AES-256-GCM encrypted in `email_settings.smtp_password`. The API only returns `smtp_password_configured: true/false`; it never returns the password.

## Scope

- Platform Default SMTP: `email_settings.branch_id IS NULL`
- Branch Override: row with that `branch_id`
- Sending from a branch first checks the branch override, then falls back to Platform Default.

## Permission

- Action 1: View
- Action 48: Manage SMTP

The menu path is `mail-settings.php`.

## Common helper

```php
send_mail(
    $branchId,
    'customer@example.com',
    'Invoice INV-001',
    '<h1>Invoice</h1><p>Please find your invoice attached.</p>',
    [
        'attachments' => [
            ['path' => $pdfPath, 'name' => 'INV-001.pdf']
        ]
    ]
);
```

Multiple recipients are also supported:

```php
send_mail($branchId, [
    ['email' => 'one@example.com', 'name' => 'One'],
    ['email' => 'two@example.com', 'name' => 'Two'],
], 'Notice', '<p>Hello</p>');
```

Optional `cc`, `bcc`, `reply_to`, `attachments`, and `alt_body` values can be passed through the fifth argument.

## Test Email

Open **Administration → Mail Configuration**, enter the SMTP values and a test recipient, then click **Send Test Email**. The test uses the values currently entered in the form, so it can be tested before saving.
