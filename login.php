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
    <title>Login · <?php echo web_h(app_name()); ?></title>
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
    <h1>Welcome back</h1>
    <p class="auth-sub">Sign in using your username and password.</p>

    <form id="loginForm" novalidate>
        <div class="auth-field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required>
        </div>
        <div class="auth-field">
            <label for="password">Password</label>
            <div class="password-wrap">
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password"></button>
            </div>
        </div>
        <button class="auth-submit" id="loginButton" type="submit">Login</button>
    </form>

    <div class="auth-footer">
        First installation? <a href="platform-register.php">Register Platform Owner</a>
    </div>
</main>

<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";
    var form = document.getElementById("loginForm");
    var button = document.getElementById("loginButton");
    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; } };
    var logoutKey = runtime.key("logout_message");

    var logoutMessage = sessionStorage.getItem(logoutKey);
    if (logoutMessage) {
        sessionStorage.removeItem(logoutKey);
        showToast(logoutMessage, { type: "info", duration: 3 });
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!Validation.validateForm(form)) return;

        button.disabled = true;
        button.textContent = "Signing in...";
        try {
            var result = await App.api("api/login.php", {
                method: "POST",
                auth: false,
                body: {
                    username: form.username.value.trim(),
                    password: form.password.value
                }
            });
            App.saveLogin(result.data.token, result.data.user);
            showToast(result.message, { type: "success", duration: 2 });
            window.setTimeout(function () { window.location.href = "dashboard.php"; }, 500);
        } catch (error) {
            App.showError(error, "Unable to connect to the server.");
        } finally {
            button.disabled = false;
            button.textContent = "Login";
        }
    });
})();
</script>
</body>
</html>
