<?php
declare(strict_types=1);

require_once __DIR__ . '/include/web-config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo web_h(app_name()); ?></title>

    <?php render_frontend_config_script(); ?>

    <script src="assets/js/runtime.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body>

<script>
(function () {
    "use strict";

    try {
        // Check login token using your existing App system
        var token = null;

        if (window.App && typeof App.getToken === "function") {
            token = App.getToken();
        }

        // Logged in
        if (token) {
            window.location.replace("dashboard.php");
            return;
        }

        // Not logged in
        window.location.replace("login.php");

    } catch (error) {
        // If anything fails, safely send to login page
        window.location.replace("login.php");
    }
})();
</script>

</body>
</html>