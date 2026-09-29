<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'My Profile'; ?>
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

<div class="page-head"><div><h1>My Profile</h1><p>Update your personal details and optionally change your password.</p></div></div>
<div class="card form-card" style="max-width:900px">
<form id="profileForm" novalidate>
    <div class="card-header"><div><h2>Profile Details</h2><p>Update your account information and security details.</p></div></div>
    <div class="card-body"><div class="form-grid">
        <div class="card-section-title">Account</div>
        <div class="field"><label for="profileName">Name</label><input id="profileName" name="name" required data-required-message="Name is required."></div>
        <div class="field"><label for="profileUsername">Username</label><input id="profileUsername" name="username" readonly></div>
        <div class="field"><label for="profileEmail">Email</label><input id="profileEmail" name="email" data-validation="email"></div>
        <div class="field"><label for="profileMobile">Mobile</label><input id="profileMobile" name="mobile" inputmode="numeric" data-validation="mobile"></div>
        <div class="field"><label for="profileRole">Role</label><input id="profileRole" readonly></div>
        <div class="field"><label for="profileScope">Company / Branch</label><input id="profileScope" readonly></div>
        <div class="card-section-title">Security</div>
        <div class="field full"><label for="profilePassword">New password (optional)</label><div class="password-wrap"><input id="profilePassword" name="password" type="password" autocomplete="new-password" data-validation="password"><button class="password-toggle" type="button" data-password-toggle="profilePassword" aria-label="Show password"></button></div><div class="muted">Leave blank to keep your current password.</div></div>
    </div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="profileSave" type="submit"><i data-lucide="save"></i>Save Profile</button></div></div>
</form>
</div>
<script src="assets/js/validation.js"></script>
<script>
(function(){
    "use strict";
    var form=document.getElementById("profileForm"), button=document.getElementById("profileSave");
    function fill(p){ form.name.value=p.name||""; form.username.value=p.username||""; form.email.value=p.email||""; form.mobile.value=p.mobile||""; document.getElementById("profileRole").value=p.role_name||""; document.getElementById("profileScope").value=[p.company_name,p.branch_name].filter(Boolean).join(" · ")||"Platform"; }
    async function load(){ try{ var r=await App.api("api/profile.php"); fill(r.data.profile); }catch(e){ App.showError(e,"Unable to load profile."); } }
    form.addEventListener("submit",async function(e){ e.preventDefault(); Validation.clearForm(form); if(!Validation.validateForm(form))return; button.disabled=true; try{ var body={name:form.name.value,email:form.email.value,mobile:form.mobile.value,password:form.password.value}; var r=await App.api("api/profile.php",{method:"PUT",body:body}); fill(r.data.profile); form.password.value=""; var u=App.getUser(); u.name=r.data.profile.name; u.email=r.data.profile.email; u.mobile=r.data.profile.mobile; App.saveLogin(App.getToken(),u); document.getElementById("appUserName").textContent=u.name; showToast(r.message,{type:"success",duration:3}); }catch(err){ Validation.applyErrors(form,err.errors||{}); App.showError(err,"Unable to update profile."); }finally{ button.disabled=false; } });
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
