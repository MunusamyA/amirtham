<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Role Form'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ((isset($headStyles) && is_array($headStyles) ? $headStyles : []) as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ((isset($headScripts) && is_array($headScripts) ? $headScripts : []) as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars((string)$scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
    <main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
        <section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>

<div class="page-head"><h1 id="title">Add Role</h1><a class="btn gray" href="role-list.php">Role List</a></div>
<div class="card form-card" style="max-width:650px">
<form id="form" novalidate>
    <div class="card-header"><div><h2>Role Details</h2><p>Configure the role name, category and status.</p></div></div>
    <div class="card-body"><div class="form-grid">
        <div class="field full">
            <label for="roleName">Role name</label>
            <input id="roleName" name="role_name" required
                   data-required-message="Role name is required.">
        </div>
        <div class="field full hidden" id="categoryField">
            <label for="roleType">Role category</label>
            <select id="roleType" name="role_type" required
                    data-required-message="Please select a role category.">
                <option value="1">Plan</option>
                <option value="2">Platform Role</option>
            </select>
        </div>
        <div class="field full">
            <label for="roleStatus">Status</label>
            <select id="roleStatus" name="status" required
                    data-required-message="Please select a status.">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
    </div></div>
    <div class="card-footer"><div class="buttons">
        <button class="btn btn-primary" id="save" type="submit">Save Role</button>
        <a class="btn gray" href="role-list.php">Cancel</a>
    </div></div>
</form>
</div>
<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";
    var form = document.getElementById("form");
    var reference = new URLSearchParams(location.search).get("ref") || "";
    var current = {};

    async function load() {
        try {
            var result;
            if (reference) {
                result = await App.api("api/roles.php?ref=" + encodeURIComponent(reference));
                current = result.data.current_user || {};
                var role = result.data.role;
                document.getElementById("title").textContent = "Edit Role";
                form.role_name.value = role.role_name;
                form.status.value = String(role.status);
                form.role_type.value = String(role.role_type);
                form.role_type.disabled = true;
                if (Number(current.role_type) === 2) {
                    document.getElementById("categoryField").classList.remove("hidden");
                }
                if (role.is_current_role) {
                    form.status.disabled = true;
                    form.status.title = "Your current role cannot be deactivated.";
                }
            } else {
                result = await App.api("api/roles.php");
                current = result.data.current_user || {};
                if (Number(current.role_type) === 2) {
                    document.getElementById("categoryField").classList.remove("hidden");
                }
            }
        } catch (error) {
            App.showError(error, "Unable to load role form.");
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        if (!Validation.validateForm(form)) return;

        var data = {
            ref: reference || undefined,
            role_name: form.role_name.value.trim(),
            status: Number(form.status.value)
        };
        if (!reference && Number(current.role_type) === 2) {
            data.role_type = Number(form.role_type.value);
        }

        var button = document.getElementById("save");
        button.disabled = true;
        try {
            var result = await App.api("api/roles.php", {
                method: reference ? "PUT" : "POST",
                body: data
            });
            showToast(result.message, { type: "success", duration: 2 }); if (App.clearSidebarCache) App.clearSidebarCache();
            setTimeout(function () {
                location.href = !reference && result.data.permission_url
                    ? result.data.permission_url : "role-list.php";
            }, 650);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save role.");
            button.disabled = false;
        }
    });

    load();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
