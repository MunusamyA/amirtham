# New Project Workflow

1. Copy the Starter Kit.
2. Copy `.env.example` to `.env`.
3. Change `APP_NAME`, `APP_SHORT_NAME`, `APP_KEY`, `APP_URL`, database credentials and fixed secrets.
4. Import `database/starterkit_fresh.sql`.
5. Run `composer install` so PHPMailer is available.
6. Create the first Platform Owner using `platform-register.php`.
7. Change the new customer's complete visual theme in **one file only**: `assets/css/theme.css`.
8. Configure Business, Branch, Roles, Menus, Permissions, Security, Theme and Mail Configuration.
9. Add only project-specific modules/tables/APIs.

Do not rebuild authentication, permissions, Mail/SMTP, Global Select, upload, DataTable, pagination, validation, regex, toast, light/dark mode or stateless idle/absolute security for each new project.
