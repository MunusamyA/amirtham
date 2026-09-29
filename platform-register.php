<?php
declare(strict_types=1);
require_once __DIR__ . '/include/web-config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title>Initial Platform Owner Registration · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-brand">
        <span class="auth-brand-mark"><?php echo web_h(substr(app_short_name(), 0, 3)); ?></span>
        <div><strong><?php echo web_h(app_name()); ?></strong><small><?php echo web_h(app_subtitle()); ?></small></div>
    </div>
    <h1>Register Platform Owner</h1>
    <p class="auth-sub">This one-time form creates the first platform account and initializes the reusable system masters.</p>
    <div class="notice" id="registrationNotice" style="display:none"></div>

    <form id="registerForm" novalidate>
        <div class="auth-grid">
            <div class="auth-field wide">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" autocomplete="name" required>
            </div>
            <div class="auth-field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required>
            </div>
            <div class="auth-field">
                <label for="mobile">Mobile</label>
                <input id="mobile" name="mobile" type="text" inputmode="numeric" data-validation="mobile">
            </div>
            <div class="auth-field wide">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" data-validation="email" required>
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" autocomplete="new-password" data-validation="password" required>
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password"></button>
                </div>
            </div>
            <div class="auth-field">
                <label for="confirmPassword">Confirm password</label>
                <div class="password-wrap">
                    <input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" data-password-toggle="confirmPassword" aria-label="Show password"></button>
                </div>
            </div>
        </div>
        <button class="auth-submit" id="registerButton" type="submit">Create Platform Owner</button>
    </form>

    <div class="auth-footer"><a href="login.php">Back to Login</a></div>
</main>

<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";
    var form = document.getElementById("registerForm");
    var button = document.getElementById("registerButton");
    var notice = document.getElementById("registrationNotice");

    async function checkAvailability() {
        try {
            var result = await App.api("api/platform-register.php", { auth: false });
            if (result.success && !result.data.registration_available) {
                form.style.display = "none";
                notice.style.display = "block";
                notice.textContent = "Platform Owner is already registered. Please use the login page.";
            }
        } catch (error) {
            App.showError(error, "Unable to check registration status.");
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!Validation.validateForm(form)) return;
        if (form.password.value !== form.confirmPassword.value) {
            showToast("Passwords do not match.", { type: "warning", duration: 4 });
            form.confirmPassword.focus();
            return;
        }

        button.disabled = true;
        button.textContent = "Creating account...";
        try {
            var result = await App.api("api/platform-register.php", {
                method: "POST",
                auth: false,
                body: {
                    name: form.name.value.trim(),
                    username: form.username.value.trim(),
                    email: form.email.value.trim(),
                    mobile: form.mobile.value.trim(),
                    password: form.password.value
                }
            });
            showToast(result.message, { type: "success", duration: 2 });
            window.setTimeout(function () { window.location.href = "login.php"; }, 700);
        } catch (error) {
            App.showError(error, "Unable to register.");
        } finally {
            button.disabled = false;
            button.textContent = "Create Platform Owner";
        }
    });

    checkAvailability();
})();
</script>
</body>
</html>
