<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Mail Configuration'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
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

<div class="page-head">
    <div>
        <h1>Mail Configuration</h1>
        <p>Configure reusable outgoing SMTP for system emails and test the connection before using it in business modules.</p>
    </div>
</div>

<div class="card form-card" style="max-width:920px">
    <form id="mailForm" novalidate>
        <div class="card-header">
            <div><h2>SMTP Settings</h2><p id="scopeHelp">Platform default SMTP is used when a branch does not have its own override.</p></div>
        </div>
        <div class="card-body">
            <div class="field" id="scopeField">
                <label for="branchSelect">Setting scope</label>
                <select id="branchSelect"><option value="">Platform Default</option></select>
            </div>
            <div class="field hidden" id="tenantScope">
                <label>Setting scope</label>
                <div id="tenantScopeName" class="muted"></div>
            </div>
            <div id="inheritNotice" class="muted hidden">This branch is currently using the Platform Default SMTP configuration. Saving creates a branch-specific override.</div>

            <div class="card-section-title"><div><strong>SMTP Server</strong><small>Outgoing mail server connection and authentication.</small></div></div>
            <div class="form-grid">
                <div class="field">
                    <label for="smtpHost">SMTP Host</label>
                    <input id="smtpHost" type="text" maxlength="190" placeholder="smtp.example.com" required>
                </div>
                <div class="field">
                    <label for="smtpPort">SMTP Port</label>
                    <input id="smtpPort" type="number" min="1" max="65535" value="587" required>
                </div>
                <div class="field">
                    <label for="smtpEncryption">Encryption</label>
                    <select id="smtpEncryption">
                        <option value="tls">TLS / STARTTLS</option>
                        <option value="ssl">SSL / SMTPS</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div class="field">
                    <label for="timeoutSeconds">Connection Timeout</label>
                    <input id="timeoutSeconds" type="number" min="5" max="120" value="20">
                </div>
            </div>

            <div class="field">
                <label class="switch" for="smtpAuth">
                    <input id="smtpAuth" type="checkbox" checked>
                    <span class="switch-track"></span>
                    <span>Use SMTP authentication</span>
                </label>
            </div>

            <div class="form-grid" id="authFields">
                <div class="field">
                    <label for="smtpUsername">SMTP Username</label>
                    <input id="smtpUsername" type="text" maxlength="190" autocomplete="off" placeholder="mail@example.com">
                </div>
                <div class="field">
                    <label for="smtpPassword">SMTP Password</label>
                    <div class="password-wrap">
                        <input id="smtpPassword" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                        <button class="password-toggle" type="button" data-password-toggle="smtpPassword" aria-label="Show password"></button>
                    </div>
                    <small id="passwordHelp" class="muted">Required for the first authenticated SMTP configuration.</small>
                </div>
            </div>

            <div class="card-section-title"><div><strong>Sender Identity</strong><small>These values appear on emails sent by the application.</small></div></div>
            <div class="form-grid">
                <div class="field"><label for="fromName">From Name</label><input id="fromName" type="text" maxlength="150" required></div>
                <div class="field"><label for="fromEmail">From Email</label><input id="fromEmail" type="email" maxlength="190" required></div>
                <div class="field"><label for="replyName">Reply-To Name</label><input id="replyName" type="text" maxlength="150"></div>
                <div class="field"><label for="replyEmail">Reply-To Email</label><input id="replyEmail" type="email" maxlength="190"></div>
            </div>

            <div class="card-section-title"><div><strong>Test Email</strong><small>Uses the values currently entered above. You can test before saving.</small></div></div>
            <div class="form-grid">
                <div class="field"><label for="testEmail">Send test to</label><input id="testEmail" type="email" maxlength="190" placeholder="you@example.com"></div>
                <div class="field" style="align-self:end"><button class="btn gray" id="testButton" type="button"><i data-lucide="send"></i> Send Test Email</button></div>
            </div>
            <p id="mailerStatus" class="muted"></p>
        </div>
        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="save"></i> Save Mail Settings</button>
                <a class="btn gray" href="dashboard.php">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
(function(){"use strict";
var user=App.getUser(),form=document.getElementById("mailForm"),branch=document.getElementById("branchSelect"),scopeField=document.getElementById("scopeField"),tenantScope=document.getElementById("tenantScope"),tenantScopeName=document.getElementById("tenantScopeName"),inheritNotice=document.getElementById("inheritNotice"),host=document.getElementById("smtpHost"),port=document.getElementById("smtpPort"),encryption=document.getElementById("smtpEncryption"),smtpAuth=document.getElementById("smtpAuth"),authFields=document.getElementById("authFields"),username=document.getElementById("smtpUsername"),password=document.getElementById("smtpPassword"),passwordHelp=document.getElementById("passwordHelp"),fromName=document.getElementById("fromName"),fromEmail=document.getElementById("fromEmail"),replyName=document.getElementById("replyName"),replyEmail=document.getElementById("replyEmail"),timeout=document.getElementById("timeoutSeconds"),testEmail=document.getElementById("testEmail"),testButton=document.getElementById("testButton"),saveButton=document.getElementById("saveButton"),mailerStatus=document.getElementById("mailerStatus");
var canManage=false;
function query(){return Number(user.role_type)===2&&branch.value?"?branch_id="+encodeURIComponent(branch.value):"";}
function toggleAuth(){authFields.classList.toggle("hidden",!smtpAuth.checked);username.required=smtpAuth.checked;}
function payload(action){var body={smtp_host:host.value.trim(),smtp_port:Number(port.value),smtp_encryption:encryption.value,smtp_auth:smtpAuth.checked?1:0,smtp_username:username.value.trim(),smtp_password:password.value,from_email:fromEmail.value.trim(),from_name:fromName.value.trim(),reply_to_email:replyEmail.value.trim(),reply_to_name:replyName.value.trim(),timeout_seconds:Number(timeout.value||20),status:1};if(Number(user.role_type)===2)body.branch_id=branch.value||null;if(action)body.action=action;return body;}
function applySettings(settings){settings=settings||{};host.value=settings.smtp_host||"";port.value=settings.smtp_port||587;encryption.value=settings.smtp_encryption||"tls";smtpAuth.checked=settings.smtp_auth===undefined?true:Number(settings.smtp_auth)===1;username.value=settings.smtp_username||"";password.value="";fromName.value=settings.from_name||"";fromEmail.value=settings.from_email||"";replyName.value=settings.reply_to_name||"";replyEmail.value=settings.reply_to_email||"";timeout.value=settings.timeout_seconds||20;passwordHelp.textContent=settings.smtp_password_configured?"Password is configured. Leave blank to keep it unchanged.":"Required for the first authenticated SMTP configuration.";toggleAuth();}
async function load(){try{var selected=branch.value,result=await App.api("api/mail-settings.php"+query()),data=result.data||{};canManage=Boolean(data.can_manage);if(Number(user.role_type)===2){branch.innerHTML='<option value="">Platform Default</option>';(data.branches||[]).forEach(function(item){var option=document.createElement("option");option.value=item.id;option.textContent=item.company_name+" — "+item.branch_name;branch.appendChild(option);});branch.value=data.branch_id!==null&&data.branch_id!==undefined?String(data.branch_id):selected;}else{scopeField.classList.add("hidden");tenantScope.classList.remove("hidden");tenantScopeName.textContent=data.branch_name||"Current branch";}applySettings(data.settings);inheritNotice.classList.toggle("hidden",!data.is_inherited);saveButton.disabled=!canManage;testButton.disabled=!canManage;Array.prototype.forEach.call(form.querySelectorAll("input,select"),function(el){if(el===branch||el===testEmail)return;if(!canManage)el.disabled=true;});mailerStatus.textContent=data.phpmailer_installed?"PHPMailer is installed and available.":"PHPMailer is not installed yet. Run composer install before sending email.";if(window.lucide)window.lucide.createIcons();}catch(error){App.showError(error,"Unable to load mail settings.");}}
branch.addEventListener("change",load);smtpAuth.addEventListener("change",toggleAuth);
form.addEventListener("submit",async function(event){event.preventDefault();if(!canManage)return;saveButton.disabled=true;try{var result=await App.api("api/mail-settings.php",{method:"PUT",body:payload()});showToast(result.message,{type:"success",duration:3});password.value="";await load();}catch(error){App.showError(error,"Unable to save mail settings.");}finally{saveButton.disabled=!canManage;}});
testButton.addEventListener("click",async function(){if(!canManage)return;if(!testEmail.value.trim()){showToast("Enter the test recipient email address.",{type:"warning",duration:3});testEmail.focus();return;}testButton.disabled=true;try{var body=payload("test");body.test_email=testEmail.value.trim();var result=await App.api("api/mail-settings.php",{method:"POST",body:body});showToast(result.message,{type:"success",duration:4});}catch(error){App.showError(error,"Unable to send test email.");}finally{testButton.disabled=!canManage;}});
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
