<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Business Form'; ?>
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

<div class="page-head"><h1 id="title">Add Business</h1><a class="btn gray" href="business-list.php">Business List</a></div>
<div class="card form-card"><form id="form" novalidate>
    <div class="card-header"><div><h2>Business Details</h2><p>Configure the business, main branch, plan and administrator.</p></div></div>
    <div class="card-body"><div class="form-grid">
    <div class="field"><label>Business name</label><input name="company_name" required></div>
    <div class="field"><label>Business code</label><input name="company_code" required></div>
    <div class="field"><label>Email</label><input name="company_email" data-validation="email"></div>
    <div class="field"><label>Mobile</label><input name="company_mobile" inputmode="numeric" data-validation="mobile"></div>
    <div class="field"><label>Status</label><select name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <div id="creationFields" style="display:contents">
        <div class="card-section-title">Main Branch and Plan</div>
        <div class="field"><label>Branch name</label><input name="branch_name" required></div>
        <div class="field"><label>Branch code</label><input name="branch_code" required></div>
        <div class="field full"><label>Plan</label><select name="plan_role_id" id="planSelect" required><option value="">Select plan</option></select></div>
        <div class="card-section-title">Branch Admin</div>
        <div class="field"><label>Admin name</label><input name="admin_name" required></div>
        <div class="field"><label>Username</label><input name="username" required></div>
        <div class="field"><label>Admin email</label><input name="admin_email" data-validation="email"></div>
        <div class="field"><label>Admin mobile</label><input name="admin_mobile" inputmode="numeric" data-validation="mobile"></div>
        <div class="field full"><label>Password</label><div class="password-wrap"><input id="adminPassword" name="password" type="password" data-validation="password" required><button class="password-toggle" type="button" data-password-toggle="adminPassword" aria-label="Show password"></button></div></div>
    </div>
</div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="save" type="submit">Save Business</button><a class="btn gray" href="business-list.php">Cancel</a></div></div>
</form></div>
<script src="assets/js/validation.js"></script>
<script>
(function(){"use strict";var form=document.getElementById("form"),id=Number(new URLSearchParams(location.search).get("id")||0),user=App.getUser();
async function load(){try{if(id){document.getElementById("title").textContent="Edit Business";var creation=document.getElementById("creationFields");creation.classList.add("hidden");Array.prototype.forEach.call(creation.querySelectorAll("input,select,button"),function(control){control.disabled=true;});var result=await App.api("api/businesses.php?id="+id),item=result.data.business;form.company_name.value=item.company_name;form.company_code.value=item.company_code;form.company_email.value=item.email||"";form.company_mobile.value=item.mobile||"";form.status.value=String(item.status);if(Number(user.role_type)!==2)form.status.disabled=true;return;}if(Number(user.role_type)!==2)throw new Error("Only Platform users can create businesses.");var roles=await App.api("api/roles.php");(roles.data.roles||[]).filter(function(role){return Number(role.role_type)===1&&Number(role.status)===1;}).forEach(function(role){var option=document.createElement("option");option.value=role.id;option.textContent=role.role_name;document.getElementById("planSelect").appendChild(option);});}catch(error){App.showError(error);}}
form.onsubmit=async function(event){event.preventDefault();if(!Validation.validateForm(form))return;var data=id?{id:id,company_name:form.company_name.value.trim(),company_code:form.company_code.value.trim(),email:form.company_email.value.trim(),mobile:form.company_mobile.value.trim(),status:Number(form.status.value)}:{company_name:form.company_name.value.trim(),company_code:form.company_code.value.trim(),company_email:form.company_email.value.trim(),company_mobile:form.company_mobile.value.trim(),status:Number(form.status.value),branch_name:form.branch_name.value.trim(),branch_code:form.branch_code.value.trim(),plan_role_id:Number(form.plan_role_id.value),admin_name:form.admin_name.value.trim(),username:form.username.value.trim(),admin_email:form.admin_email.value.trim(),admin_mobile:form.admin_mobile.value.trim(),password:form.password.value};var button=document.getElementById("save");button.disabled=true;try{var result=await App.api("api/businesses.php",{method:id?"PUT":"POST",body:data});showToast(result.message,{type:"success",duration:2});setTimeout(function(){location.href="business-list.php";},650);}catch(error){App.showError(error);button.disabled=false;}};load();})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
