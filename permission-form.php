<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Role Permissions'; ?>
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

<div class="page-head">
    <div><h1>Role Permissions</h1><div class="muted" id="roleLabel">Select a role.</div></div>
    <div style="min-width:280px">
        <label for="roleSelect">Role</label>
        <select id="roleSelect" data-placeholder="Select role">
            <option value="">Select role</option>
        </select>
    </div>
</div>
<div class="card form-card permission-card">
    <div class="card-header"><div><h2>Menu Permissions</h2><p>Select the numeric actions allowed for each menu.</p></div></div>
    <div class="card-body table-body"><div class="table-frame"><table><thead><tr><th>Menu</th><th>Available permissions</th></tr></thead>
    <tbody id="permissionRows"><tr><td colspan="2" class="empty">Select a role.</td></tr></tbody></table></div></div>
    <div class="card-footer"><div class="buttons">
        <button class="btn btn-primary" id="saveButton" type="button" disabled>Update Permissions</button>
        <a class="btn gray" href="role-list.php">Role List</a>
    </div></div>
</div>
<script src="assets/js/global-select.js"></script>
<script>
(function () {
    "use strict";
    var select = document.getElementById("roleSelect");
    var roleSelect = GlobalSelect.init(select, { placeholder: "Select role" });
    var save = document.getElementById("saveButton");
    var selectedRole = new URLSearchParams(location.search).get("ref") || "";
    var canUpdate = false;

    async function loadRoles() {
        try {
            var url = "api/roles.php" + (selectedRole
                ? "?selected_ref=" + encodeURIComponent(selectedRole) : "");
            var result = await App.api(url);
            var current = result.data.current_user || {};
            var options = [];
            (result.data.roles || []).forEach(function (role) {
                if (Number(current.role_type) !== 2 && Number(role.role_type) !== 3) return;
                options.push({
                    value: role.ref,
                    text: role.role_name + (Number(role.status) === 1 ? "" : " (Inactive)")
                });
            });
            roleSelect.setOptions(options, selectedRole);
            if (selectedRole) loadPermissions(selectedRole);
        } catch (error) {
            App.showError(error, "Unable to load roles.");
        }
    }

    async function loadPermissions(reference) {
        selectedRole = String(reference || "");
        if (!selectedRole) return;
        document.getElementById("permissionRows").innerHTML =
            '<tr><td colspan="2" class="empty">Loading...</td></tr>';
        try {
            var result = await App.api("api/permissions.php?ref=" + encodeURIComponent(selectedRole));
            canUpdate = (result.data.allowed_actions || []).map(Number).indexOf(53) !== -1 &&
                !result.data.target_locked;
            save.disabled = !canUpdate;
            document.getElementById("roleLabel").textContent =
                (canUpdate ? "Updating: " : "Viewing: ") + result.data.role.role_name +
                (result.data.target_locked ? " — current role is protected" : "");

            var rows = document.getElementById("permissionRows");
            rows.innerHTML = "";
            var actionById = {};
            (result.data.actions || []).forEach(function (action) {
                actionById[Number(action.id)] = action;
            });
            (result.data.menus || []).forEach(function (menu) {
                var row = document.createElement("tr");
                row.dataset.menuId = menu.id;
                var name = document.createElement("td");
                name.textContent = menu.menu_name;
                row.appendChild(name);
                var actions = document.createElement("td");
                var groupContainers = {};
                menu.available_action_ids.forEach(function (actionId) {
                    var action = actionById[Number(actionId)] || {
                        id: Number(actionId),
                        action_name: "Action",
                        purpose: "",
                        group_name: "Other"
                    };
                    if (!groupContainers[action.group_name]) {
                        var group = document.createElement("div");
                        group.style.cssText = "margin-bottom:8px";
                        var title = document.createElement("div");
                        title.className = "muted";
                        title.style.cssText = "font-weight:700;margin-bottom:4px";
                        title.textContent = action.group_name;
                        group.appendChild(title);
                        actions.appendChild(group);
                        groupContainers[action.group_name] = group;
                    }
                    var label = document.createElement("label");
                    label.style.cssText = "display:inline-flex;align-items:center;gap:5px;margin:0 14px 6px 0";
                    label.title = action.purpose || "";
                    var box = document.createElement("input");
                    box.type = "checkbox";
                    box.style = "width:17px;height:17px";
                    box.value = actionId;
                    box.checked = menu.selected_action_ids.map(Number)
                        .indexOf(Number(actionId)) !== -1;
                    box.disabled = !canUpdate;
                    box.onchange = function () {
                        if (Number(actionId) !== 1 && box.checked) {
                            var view = row.querySelector('input[value="1"]');
                            if (view) view.checked = true;
                        }
                    };
                    label.appendChild(box);
                    label.appendChild(document.createTextNode(
                        actionId + " - " + action.action_name
                    ));
                    groupContainers[action.group_name].appendChild(label);
                });
                row.appendChild(actions);
                rows.appendChild(row);
            });
        } catch (error) {
            App.showError(error, "Unable to load permissions.");
        }
    }

    select.addEventListener("change", function () {
        if (!select.value) return;
        selectedRole = select.value;
        history.replaceState(null, "", "permission-form.php?ref=" + encodeURIComponent(selectedRole));
        loadPermissions(selectedRole);
    });

    save.addEventListener("click", async function () {
        var permissions = [];
        Array.prototype.forEach.call(
            document.querySelectorAll("#permissionRows tr[data-menu-id]"),
            function (row) {
                var ids = [];
                Array.prototype.forEach.call(row.querySelectorAll("input:checked"), function (input) {
                    ids.push(Number(input.value));
                });
                if (ids.length) permissions.push({
                    menu_id: Number(row.dataset.menuId),
                    action_ids: ids
                });
            }
        );
        save.disabled = true;
        try {
            var result = await App.api("api/permissions.php", {
                method: "PUT",
                body: { ref: selectedRole, permissions: permissions }
            });
            showToast(result.message, { type: "success", duration: 3 }); if (App.clearSidebarCache) App.clearSidebarCache();
            loadPermissions(selectedRole);
        } catch (error) {
            App.showError(error, "Unable to update permissions.");
            save.disabled = false;
        }
    });

    loadRoles();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
