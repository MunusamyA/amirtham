<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Branch Form'; ?>
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

<div class="page-head"><h1 id="title">Add Branch</h1><a class="btn gray" href="branch-list.php">Branch List</a></div>
<div class="card form-card"><form id="form" novalidate>
    <div class="card-header"><div><h2>Branch Details</h2><p>Configure the branch, plan and branch administrator.</p></div></div>
    <div class="card-body"><div class="form-grid">
    <div class="field"><label>Business</label><select name="company_id" id="companySelect" required><option value="">Select business</option></select></div>
    <div class="field"><label>Plan</label><select name="plan_role_id" id="planSelect" required><option value="">Select plan</option></select></div>
    <div class="field"><label>Branch name</label><input name="branch_name" required></div>
    <div class="field"><label>Branch code</label><input name="branch_code" required></div>
    <div class="field"><label>Status</label><select name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <div id="adminFields" style="display:contents">
        <div class="card-section-title">Branch Admin</div>
        <div class="field"><label>Admin name</label><input name="admin_name" required></div>
        <div class="field"><label>Username</label><input name="username" required></div>
        <div class="field"><label>Admin email</label><input name="admin_email" data-validation="email"></div>
        <div class="field"><label>Admin mobile</label><input name="admin_mobile" inputmode="numeric" data-validation="mobile"></div>
        <div class="field full"><label>Password</label><div class="password-wrap"><input id="adminPassword" name="password" type="password" data-validation="password" required><button class="password-toggle" type="button" data-password-toggle="adminPassword" aria-label="Show password"></button></div></div>
    </div>
</div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="save" type="submit">Save Branch</button><a class="btn gray" href="branch-list.php">Cancel</a></div></div>
</form></div>
<script src="assets/js/validation.js"></script>
<script>
(function(){"use strict";var form=document.getElementById("form"),id=Number(new URLSearchParams(location.search).get("id")||0),user=App.getUser();
function option(select,value,label){var item=document.createElement("option");item.value=value;item.textContent=label;select.appendChild(item);}
async function loadOptions(){var businesses=await App.api("api/businesses.php");(businesses.data.businesses||[]).filter(function(item){return Number(item.status)===1;}).forEach(function(item){option(document.getElementById("companySelect"),item.id,item.company_name);});var roles=await App.api("api/roles.php");(roles.data.roles||[]).filter(function(item){return Number(item.role_type)===1&&Number(item.status)===1;}).forEach(function(item){option(document.getElementById("planSelect"),item.id,item.role_name);});}
async function load(){try{await loadOptions();if(id){document.getElementById("title").textContent="Edit Branch";var admin=document.getElementById("adminFields");admin.classList.add("hidden");Array.prototype.forEach.call(admin.querySelectorAll("input,select,button"),function(control){control.disabled=true;});var result=await App.api("api/branches.php?id="+id),item=result.data.branch;form.company_id.value=String(item.company_id);form.plan_role_id.value=String(item.role_id);form.branch_name.value=item.branch_name;form.branch_code.value=item.branch_code;form.status.value=String(item.status);if(Number(user.role_type)!==2){form.company_id.disabled=true;form.plan_role_id.disabled=true;form.status.disabled=true;}}else if(Number(user.role_type)!==2){throw new Error("Only Platform users can create branches.");}}catch(error){App.showError(error);}}
form.onsubmit=async function(event){event.preventDefault();if(!Validation.validateForm(form))return;var data={id:id||undefined,company_id:Number(form.company_id.value),plan_role_id:Number(form.plan_role_id.value),branch_name:form.branch_name.value.trim(),branch_code:form.branch_code.value.trim(),status:Number(form.status.value)};if(!id){data.admin_name=form.admin_name.value.trim();data.username=form.username.value.trim();data.admin_email=form.admin_email.value.trim();data.admin_mobile=form.admin_mobile.value.trim();data.password=form.password.value;}var button=document.getElementById("save");button.disabled=true;try{var result=await App.api("api/branches.php",{method:id?"PUT":"POST",body:data});showToast(result.message,{type:"success",duration:2});setTimeout(function(){location.href="branch-list.php";},650);}catch(error){App.showError(error);button.disabled=false;}};load();})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
