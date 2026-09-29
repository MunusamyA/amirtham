<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Employee Form'; ?>
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

<div class="page-head"><h1 id="pageHeading">Add Employee</h1><a class="btn gray" href="employee-list.php">Employee List</a></div>
<div class="card form-card">
<form id="employeeForm" novalidate enctype="multipart/form-data">
    <input type="hidden" name="ref" id="employeeRef">
    <div class="card-header"><div><h2>Employee Details</h2><p>Branch, role, employee account and media information.</p></div></div>
    <div class="card-body"><div class="form-grid">
        <div class="card-section-title">Branch and Role</div>
        <div class="field" id="branchField">
            <label for="branchSelect">Branch</label>
            <select name="branch_id" id="branchSelect" required data-placeholder="Select branch"
                    data-required-message="Please select a branch.">
                <option value="">Select branch</option>
            </select>
        </div>
        <div class="field">
            <label for="roleSelect">Role</label>
            <select name="role_id" id="roleSelect" required data-placeholder="Select role"
                    data-required-message="Please select an employee role.">
                <option value="">Select role</option>
            </select>
        </div>

        <div class="card-section-title">Employee Details</div>
        <div class="field"><label for="employeeCode">Employee code</label><input id="employeeCode" name="employee_code" required data-required-message="Employee code is required."></div>
        <div class="field"><label for="employeeName">Employee name</label><input id="employeeName" name="name" required data-required-message="Employee name is required."></div>
        <div class="field"><label for="employeeEmail">Email</label><input id="employeeEmail" name="email" data-validation="email" data-email-message="Enter a valid employee email address."></div>
        <div class="field"><label for="employeeMobile">Mobile</label><input id="employeeMobile" name="mobile" inputmode="numeric" data-validation="mobile" data-mobile-message="Enter a valid 10-digit employee mobile number."></div>
        <div class="field"><label for="employeePan">PAN</label><input id="employeePan" name="pan" maxlength="10" placeholder="ABCDE1234F" autocomplete="off" data-validation="pan" data-pan-message="PAN format must be ABCDE1234F."></div>
        <div class="field"><label for="employeeAadhaar">Aadhaar</label><input id="employeeAadhaar" name="aadhaar" maxlength="14" placeholder="2345 6789 0123" autocomplete="off" inputmode="numeric" data-validation="aadhaar" data-aadhaar-message="Enter a valid 12-digit Aadhaar number."></div>
        <div class="field"><label for="employeeStatus">Status</label><select id="employeeStatus" name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>

        <div class="card-section-title">Login Account</div>
        <div class="field"><label for="employeeUsername">Username</label><input id="employeeUsername" name="username" autocomplete="username" required data-required-message="Login username is required."></div>
        <div class="field">
            <label for="employeePassword" id="passwordLabel">Password</label>
            <div class="password-wrap">
                <input id="employeePassword" name="password" type="password" autocomplete="new-password" data-validation="password" required
                       data-required-message="Login password is required."
                       data-password-message="Use at least 8 characters with uppercase, lowercase, number and special character.">
                <button class="password-toggle" type="button" data-password-toggle="employeePassword" aria-label="Show password"></button>
            </div>
            <div class="muted" id="passwordHelp">Use uppercase, lowercase, number and special character.</div>
        </div>

        <div class="card-section-title">Images and Videos</div>
        <div class="field full">
            <label for="mediaFiles">Upload files</label>
            <input id="mediaFiles" name="media_files[]" data-image-preview   type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime">
            <div class="muted">Maximum 10 files. Images up to 10 MB; videos up to 50 MB.</div>
        </div>
        <div class="field full hidden" id="existingMediaField">
            <label>Existing files</label>
            <div id="existingMedia" style="display:flex;flex-wrap:wrap;gap:8px"></div>
        </div>
    </div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="saveButton" type="submit">Save Employee</button><a class="btn gray" href="employee-list.php">Cancel</a></div></div>
</form>
</div>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>
<script src="assets/js/file-upload.js"></script>
<script>
(function () {
    "use strict";
    var form = document.getElementById("employeeForm");
    var reference = new URLSearchParams(location.search).get("ref") || "";
    var branchSelect = GlobalSelect.init("#branchSelect", { placeholder: "Select or type branch" });
    var roleSelect = GlobalSelect.init("#roleSelect", { placeholder: "Select or type role" });
    var uploader = FileUpload.init("#mediaFiles", {
        multiple: true, minFiles: 0, maxFiles: 10, minSizeMB: 0,
        maxSizeMB: 50, maxImageSizeMB: 10, maxVideoSizeMB: 50,
        allowedTypes: ["jpg", "jpeg", "png", "webp", "gif", "mp4", "webm", "mov"]
    });
    var isLoading = false;

    function optionItems(rows, valueKey, textBuilder) {
        return (rows || []).map(function (row) {
            return { value: row[valueKey], text: textBuilder(row) };
        });
    }

    async function loadOptions(branchId, selectedRoleId) {
        var url = "api/employees.php?options=1" + (branchId ? "&branch_id=" + encodeURIComponent(branchId) : "");
        var result = await App.api(url);
        var data = result.data;
        var selectedBranch = branchId || data.selected_branch_id || "";
        branchSelect.setOptions(optionItems(data.branches, "id", function (row) {
            return row.company_name + " — " + row.branch_name;
        }), selectedBranch);
        roleSelect.setOptions(optionItems(data.roles, "id", function (row) { return row.role_name; }), selectedRoleId || "");
        if (Number(data.current_user.role_type) !== 2) document.getElementById("branchField").classList.add("hidden");
        return data;
    }

    function renderExistingMedia(files) {
        var field = document.getElementById("existingMediaField");
        var target = document.getElementById("existingMedia");
        target.innerHTML = "";
        if (!(files || []).length) { field.classList.add("hidden"); return; }
        field.classList.remove("hidden");
        files.forEach(function (file) {
            var link = document.createElement("a");
            link.className = "btn small gray";
            link.href = file.url || file.path;
            link.target = "_blank";
            link.rel = "noopener";
            link.textContent = file.name || "View file";
            target.appendChild(link);
        });
    }

    function fillEmployee(employee) {
        document.getElementById("employeeRef").value = employee.ref;
        form.employee_code.value = employee.employee_code || "";
        form.name.value = employee.name || "";
        form.email.value = employee.email || "";
        form.mobile.value = employee.mobile || "";
        form.pan.value = employee.pan || "";
        form.aadhaar.value = employee.aadhaar || "";
        Validation.formatField(form.pan);
        Validation.formatField(form.aadhaar);
        form.username.value = employee.username || "";
        form.status.value = String(employee.status);
        document.getElementById("employeePassword").required = false;
        document.getElementById("passwordLabel").textContent = employee.user_id ? "New password (optional)" : "Password";
        document.getElementById("passwordHelp").textContent = employee.user_id ? "Leave blank to keep the current password." : "Password is required for this legacy employee.";
        if (!employee.user_id) document.getElementById("employeePassword").required = true;
        renderExistingMedia(employee.media_files || []);
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;
        try {
            if (reference) {
                document.getElementById("pageHeading").textContent = "Edit Employee";
                var result = await App.api("api/employees.php?ref=" + encodeURIComponent(reference));
                var employee = result.data.employee;
                await loadOptions(employee.branch_id, employee.role_id);
                fillEmployee(employee);
                if ((result.data.allowed_actions || []).map(Number).indexOf(3) === -1) {
                    document.getElementById("saveButton").disabled = true;
                }
            } else {
                await loadOptions("", "");
            }
        } catch (error) {
            App.showError(error, "Unable to load employee form.");
        } finally {
            isLoading = false;
        }
    }

    form.branch_id.addEventListener("change", async function () {
        if (isLoading || !form.branch_id.value) return;
        try { await loadOptions(form.branch_id.value, ""); }
        catch (error) { App.showError(error, "Unable to load roles."); }
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        if (!Validation.validateForm(form)) return;
        var data = new FormData(form);
        if (reference) data.set("_method", "PUT");
        var button = document.getElementById("saveButton");
        button.disabled = true;
        try {
            var result = await App.api("api/employees.php", { method: "POST", body: data });
            showToast(result.message, { type: "success", duration: 2 });
            setTimeout(function () { location.href = "employee-list.php"; }, 700);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save employee.");
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
