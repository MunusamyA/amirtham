# Starter Kit Architecture

## 1. Stable backend — reuse for every project

Keep authentication, PDO/database, API responses, Business/Branch scope, Roles, Numeric Permissions, Action Master, Sidebar Menu Master, Security Settings, Theme Settings, Mail Configuration, profile and audit APIs unchanged unless the framework itself needs a new capability.

## 2. Stable frontend functions — behavior only

- `runtime.js` — project-specific storage/event namespace from `APP_KEY`
- `app.js` — auth/API client and common helpers
- `activity.js` — real-user activity + stateless idle-token renewal/logout
- `layout.js` — dynamic sidebar/topbar, responsive navigation and safe flyout placement
- `global-select.js` — searchable select behavior
- `file-upload.js` — reusable upload behavior
- `datatable.js` — DataTables engine + custom pagination
- `validation.js` — common and regex validation
- `toaster.js` — message behavior and semantic states
- `appearance.js` — light/dark switching
- `theme.js` — optional database theme-variable overrides

JavaScript must not contain customer design values.

## 3. Three CSS files

```text
core.css       -> structural foundation
components.css -> reusable component contracts
 theme.css     -> customer design tokens
```

Only `assets/css/theme.css` should normally change for a new customer's visual identity.

## 4. Full variable-driven design

The customer theme includes colors plus typography, radius, spacing, density, dimensions, shadows, transitions and responsive component source values. Reusable component CSS uses semantic variables only.

## 5. Safe submenu behavior

Desktop expanded sidebar -> inline submenu.

Desktop collapsed sidebar -> body-level floating flyout. `layout.js` reads active theme dimensions, then clamps the flyout below the topbar and inside the viewport.

Mobile -> off-canvas sidebar with inline click submenus; no hover flyout.

## 6. Theme Settings interaction

`theme.css` is the default source. Theme Settings can save optional platform/branch color overrides in `app_settings`. Reset deletes those overrides and returns to `theme.css`.

## 7. Stateless session security

The database stores only two configurable security values in `app_settings`:

- `absolute_login_expiry_minutes`
- `idle_expiry_minutes`

The signed Bearer token carries the actual timestamps. Real browser activity can renew only the idle expiry. The absolute expiry never moves. There is no `user_sessions` table and no token stored in MySQL.

## 8. Reusable Mail / SMTP layer

Outgoing SMTP is stored in `email_settings`. The password is encrypted with the permanent `APP_ENCRYPTION_KEY`. Branch configuration overrides Platform Default; when no branch row exists the sender falls back to Platform Default.

Future modules call `send_mail()` from `include/mail.php`; SMTP setup is never duplicated inside business modules.
