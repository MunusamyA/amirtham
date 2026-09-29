<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Settings';
?>
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
<script src="assets/js/validation.js"></script>
<script src="assets/js/file-upload.js"></script>

<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p>Manage business identity, session security, theme and outgoing mail from one place.</p>
    </div>
</div>

<div class="card form-card">
    <div class="card-header">
        <div>
            <h2>Settings Scope</h2>
            <p>Platform users can manage the platform default or a branch-specific configuration.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="field" id="settingsScopeField">
            <label for="settingsBranchSelect">Setting scope</label>
            <select id="settingsBranchSelect">
                <option value="">Platform Default</option>
            </select>
        </div>
        <div class="field hidden" id="settingsTenantScope">
            <label>Setting scope</label>
            <div class="muted" id="settingsTenantScopeName">Current Branch</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="tab-row" role="tablist" aria-label="Settings sections">
        <button class="tab-button active" type="button" role="tab" aria-selected="true" data-settings-tab="general">
            <i data-lucide="building-2"></i><span>General</span>
        </button>
        <button class="tab-button" type="button" role="tab" aria-selected="false" data-settings-tab="security">
            <i data-lucide="shield-check"></i><span>Security</span>
        </button>
        <button class="tab-button" type="button" role="tab" aria-selected="false" data-settings-tab="theme">
            <i data-lucide="palette"></i><span>Theme</span>
        </button>
        <button class="tab-button" type="button" role="tab" aria-selected="false" data-settings-tab="mail">
            <i data-lucide="mail"></i><span>Mail</span>
        </button>
    </div>
</div>

<!-- GENERAL ---------------------------------------------------------------- -->
<section data-settings-panel="general">
    <form id="generalSettingsForm" novalidate enctype="multipart/form-data">
        <div class="card form-card">
            <div class="card-header">
                <div>
                    <h2>General Settings</h2>
                    <p id="generalScopeNote">Loading general settings...</p>
                </div>
            </div>
            <div class="card-body">
                <div class="card-section-title">
                    <div><strong>Business Identity</strong><small>Common identity used by invoices, PDFs, reports and other modules.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="businessName">Business Name</label>
                        <input id="businessName" name="business_name" type="text" maxlength="190" required>
                    </div>
                    <div class="field">
                        <label for="businessShortName">Short Name</label>
                        <input id="businessShortName" name="business_short_name" type="text" maxlength="100">
                    </div>
                    <div class="field full">
                        <label for="legalName">Legal Name</label>
                        <input id="legalName" name="legal_name" type="text" maxlength="190">
                    </div>
                    <div class="field full">
                        <label for="companyLogo">Company Logo</label>
                        <input id="companyLogo" name="company_logo" type="file"
                               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                        <input id="removeCompanyLogo" name="remove_logo" type="hidden" value="0">
                    </div>
                    <div class="field full">
                        <label for="digitalSignature">Digital Signature</label>
                        <input id="digitalSignature" name="digital_signature" type="file"
                               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                        <input id="removeDigitalSignature" name="remove_signature" type="hidden" value="0">
                    </div>
                </div>

                <div class="card-section-title">
                    <div><strong>Tax Information</strong><small>Default registration information used on business documents.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="gstNumber">GST Number</label>
                        <input id="gstNumber" name="gst_number" type="text" maxlength="15" data-validation="gst" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="panNumber">PAN Number</label>
                        <input id="panNumber" name="pan_number" type="text" maxlength="10" data-validation="pan" autocomplete="off">
                    </div>
                </div>

                <div class="card-section-title">
                    <div><strong>Contact Information</strong><small>Primary business contact details.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="businessEmail">Email</label>
                        <input id="businessEmail" name="business_email" type="email" maxlength="190" data-validation="email">
                    </div>
                    <div class="field">
                        <label for="businessMobile">Mobile</label>
                        <input id="businessMobile" name="business_mobile" type="text" maxlength="10" inputmode="numeric" data-validation="mobile">
                    </div>
                    <div class="field full">
                        <label for="businessWebsite">Website</label>
                        <input id="businessWebsite" name="website" type="text" maxlength="190" placeholder="https://example.com">
                    </div>
                </div>

                <div class="card-section-title">
                    <div><strong>Address</strong><small>Default address used on invoices and reports.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field full"><label for="addressLine1">Address Line 1</label><input id="addressLine1" name="address_line_1" type="text" maxlength="255"></div>
                    <div class="field full"><label for="addressLine2">Address Line 2</label><input id="addressLine2" name="address_line_2" type="text" maxlength="255"></div>
                    <div class="field"><label for="businessCity">City</label><input id="businessCity" name="city" type="text" maxlength="100"></div>
                    <div class="field"><label for="businessState">State</label><input id="businessState" name="state" type="text" maxlength="100"></div>
                    <div class="field"><label for="businessPincode">Pincode</label><input id="businessPincode" name="pincode" type="text" maxlength="6" inputmode="numeric" data-validation="pincode"></div>
                    <div class="field"><label for="businessCountry">Country</label><input id="businessCountry" name="country" type="text" maxlength="100"></div>
                </div>

                <div class="card-section-title">
                    <div><strong>Application Defaults</strong><small>Shared formatting and financial defaults.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="currencyCode">Currency Code</label><input id="currencyCode" name="currency_code" type="text" maxlength="3"></div>
                    <div class="field"><label for="currencySymbol">Currency Symbol</label><input id="currencySymbol" name="currency_symbol" type="text" maxlength="12"></div>
                    <div class="field">
                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone">
                            <option value="Asia/Kolkata">Asia/Kolkata</option>
                            <option value="UTC">UTC</option>
                            <option value="Asia/Dubai">Asia/Dubai</option>
                            <option value="Asia/Singapore">Asia/Singapore</option>
                            <option value="Europe/London">Europe/London</option>
                            <option value="America/New_York">America/New_York</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="dateFormat">Date Format</label>
                        <select id="dateFormat" name="date_format">
                            <option value="d-m-Y">DD-MM-YYYY</option>
                            <option value="d/m/Y">DD/MM/YYYY</option>
                            <option value="Y-m-d">YYYY-MM-DD</option>
                            <option value="m/d/Y">MM/DD/YYYY</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="financialYearStart">Financial Year Start</label>
                        <input id="financialYearStart" name="financial_year_start" type="text" maxlength="5" placeholder="04-01">
                    </div>
                    <div class="field"><label for="authorizedSignatory">Authorized Signatory</label><input id="authorizedSignatory" name="authorized_signatory" type="text" maxlength="150"></div>
                </div>

                <div class="card-section-title">
                    <div><strong>Document Defaults</strong><small>Optional default content for invoices, quotations and PDFs.</small></div>
                </div>
                <div class="form-grid">
                    <div class="field full"><label for="invoiceFooter">Invoice Footer</label><textarea id="invoiceFooter" name="invoice_footer" rows="3" maxlength="2000"></textarea></div>
                    <div class="field full"><label for="termsConditions">Terms & Conditions</label><textarea id="termsConditions" name="terms_conditions" rows="4" maxlength="5000"></textarea></div>
                </div>
            </div>
            <div class="card-footer">
                <div class="buttons">
                    <button class="btn gray" id="resetGeneralButton" type="button"><i data-lucide="rotate-ccw"></i> Reset / Inherit</button>
                    <button class="btn btn-primary" id="saveGeneralButton" type="submit"><i data-lucide="save"></i> Save General Settings</button>
                </div>
            </div>
        </div>
    </form>
</section>

<!-- SECURITY --------------------------------------------------------------- -->
<section data-settings-panel="security" hidden>
    <form id="securitySettingsForm" novalidate>
        <div class="card form-card">
            <div class="card-header">
                <div><h2>Session Security</h2><p id="securityScopeNote">Loading security settings...</p></div>
            </div>
            <div class="card-body">
                <div class="card-section-title"><div><strong>Absolute Login Expiry</strong><small>Maximum lifetime of one login. User activity never extends this limit.</small></div></div>
                <div class="form-grid">
                    <div class="field"><label for="expiryValue">Absolute login expiry</label><input id="expiryValue" type="number" min="1" required></div>
                    <div class="field"><label for="expiryUnit">Unit</label><select id="expiryUnit"><option value="1">Minutes</option><option value="60">Hours</option><option value="1440">Days</option></select></div>
                </div>
                <p class="muted">Allowed range: 5 minutes to 30 days. Default from .env is 24 hours.</p>

                <div class="card-section-title"><div><strong>Idle Expiry</strong><small>Automatically logs out when the user performs no real action for this period.</small></div></div>
                <div class="form-grid">
                    <div class="field"><label for="idleValue">Idle expiry</label><input id="idleValue" type="number" min="1" required></div>
                    <div class="field"><label for="idleUnit">Unit</label><select id="idleUnit"><option value="1">Minutes</option><option value="60">Hours</option><option value="1440">Days</option></select></div>
                </div>
                <p class="muted">Allowed range: 5 minutes to 7 days. Background layout requests must not renew real user activity.</p>
            </div>
            <div class="card-footer">
                <div class="buttons">
                    <button class="btn gray" id="resetSecurityButton" type="button"><i data-lucide="rotate-ccw"></i> Reset / Inherit</button>
                    <button class="btn btn-primary" id="saveSecurityButton" type="submit"><i data-lucide="save"></i> Save Security Settings</button>
                </div>
            </div>
        </div>
    </form>
</section>

<!-- THEME ------------------------------------------------------------------ -->
<section data-settings-panel="theme" hidden>
    <div class="card theme-preview" aria-label="Theme preview">
        <div class="theme-preview-bar"><strong><?php echo web_h(app_name()); ?> Theme Preview</strong><span id="previewPresetName">Live Preview</span></div>
        <div class="theme-preview-body">
            <div class="theme-preview-chip"><strong>Primary Brand</strong><small>Buttons, focus, highlights and active navigation</small></div>
            <div class="theme-preview-chip"><strong>Component Design</strong><small>Font, radius, spacing, density, sidebar and control sizing</small></div>
            <div class="theme-preview-chip"><strong>Accent</strong><small class="theme-preview-accent">Status, charts and supporting business identity</small></div>
        </div>
    </div>

    <div class="card theme-settings-card">
        <div class="theme-toolbar">
            <div class="theme-scope-note" id="themeScopeNote">Loading theme settings...</div>
        </div>

        <div class="theme-custom-heading"><div><h2>10 Ready Themes</h2><p>Selecting a preset changes the complete design profile, not only the brand color.</p></div></div>
        <div id="themePresets" class="theme-presets"><div class="empty">Open the Theme tab to load presets.</div></div>

        <div class="theme-custom-heading"><div><h2>Custom Color Overrides</h2><p>Optional semantic color overrides for the selected preset.</p></div></div>
        <div id="themeGrid" class="theme-grid"><div class="empty">Open the Theme tab to load colors.</div></div>

        <div class="theme-actions">
            <button class="btn btn-soft" id="resetThemeButton" type="button"><i data-lucide="rotate-ccw"></i> Reset / Inherit</button>
            <button class="btn btn-primary" id="saveThemeButton" type="button"><i data-lucide="save"></i> Save Theme</button>
        </div>
    </div>
</section>

<!-- MAIL ------------------------------------------------------------------- -->
<section data-settings-panel="mail" hidden>
    <form id="mailSettingsForm" novalidate>
        <div class="card form-card">
            <div class="card-header">
                <div><h2>SMTP Settings</h2><p id="mailScopeNote">Loading mail settings...</p></div>
            </div>
            <div class="card-body">
                <div id="mailInheritNotice" class="muted hidden">This branch is using the Platform Default SMTP configuration. Saving creates a branch-specific override.</div>

                <div class="card-section-title"><div><strong>SMTP Server</strong><small>Outgoing mail server connection and authentication.</small></div></div>
                <div class="form-grid">
                    <div class="field"><label for="smtpHost">SMTP Host</label><input id="smtpHost" type="text" maxlength="190" placeholder="smtp.example.com" required></div>
                    <div class="field"><label for="smtpPort">SMTP Port</label><input id="smtpPort" type="number" min="1" max="65535" value="587" required></div>
                    <div class="field"><label for="smtpEncryption">Encryption</label><select id="smtpEncryption"><option value="tls">TLS / STARTTLS</option><option value="ssl">SSL / SMTPS</option><option value="none">None</option></select></div>
                    <div class="field"><label for="timeoutSeconds">Connection Timeout</label><input id="timeoutSeconds" type="number" min="5" max="120" value="20"></div>
                </div>

                <div class="field">
                    <label class="switch" for="smtpAuth"><input id="smtpAuth" type="checkbox" checked><span class="switch-track"></span><span>Use SMTP authentication</span></label>
                </div>

                <div class="form-grid" id="mailAuthFields">
                    <div class="field"><label for="smtpUsername">SMTP Username</label><input id="smtpUsername" type="text" maxlength="190" autocomplete="off" placeholder="mail@example.com"></div>
                    <div class="field">
                        <label for="smtpPassword">SMTP Password</label>
                        <div class="password-wrap"><input id="smtpPassword" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password"><button class="password-toggle" type="button" data-password-toggle="smtpPassword" aria-label="Show password"></button></div>
                        <small id="smtpPasswordHelp" class="muted">Required for the first authenticated SMTP configuration.</small>
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
                    <div class="field"><label>&nbsp;</label><button class="btn gray" id="testMailButton" type="button"><i data-lucide="send"></i> Send Test Email</button></div>
                </div>
                <p id="mailerStatus" class="muted"></p>
            </div>
            <div class="card-footer">
                <div class="buttons">
                    <button class="btn gray" id="resetMailButton" type="button"><i data-lucide="rotate-ccw"></i> Reset / Inherit</button>
                    <button class="btn btn-primary" id="saveMailButton" type="submit"><i data-lucide="save"></i> Save Mail Settings</button>
                </div>
            </div>
        </div>
    </form>
</section>

<script>
(function (window, document) {
    "use strict";

    var bootstrapData = null;
    var activeTab = "general";
    var loaded = { general: false, security: false, theme: false, mail: false };

    var branchSelect = document.getElementById("settingsBranchSelect");
    var scopeField = document.getElementById("settingsScopeField");
    var tenantScope = document.getElementById("settingsTenantScope");
    var tenantScopeName = document.getElementById("settingsTenantScopeName");

    function refreshIcons() {
        if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    }

    function userIsPlatform() {
        return bootstrapData && bootstrapData.user && Number(bootstrapData.user.role_type) === 2;
    }

    function currentBranchId() {
        if (!bootstrapData || !bootstrapData.user) return null;
        if (userIsPlatform()) return branchSelect.value ? Number(branchSelect.value) : null;
        return bootstrapData.user.branch_id ? Number(bootstrapData.user.branch_id) : null;
    }

    function sectionUrl(section) {
        var url = "api/settings.php?section=" + encodeURIComponent(section);
        var branchId = currentBranchId();
        if (userIsPlatform() && branchId) url += "&branch_id=" + encodeURIComponent(branchId);
        return url;
    }

    function addScope(body) {
        if (userIsPlatform()) body.branch_id = currentBranchId();
        return body;
    }

    function scopeText(data, platformText, branchText) {
        if (!data) return "";
        if (data.branch_id === null || data.branch_id === undefined) return platformText || "Platform Default";
        var name = [data.company_name, data.branch_name].filter(Boolean).join(" · ") || "Current Branch";
        return name + (branchText ? " · " + branchText : "");
    }

    function setFormControls(form, enabled, exceptions) {
        exceptions = exceptions || [];
        Array.prototype.forEach.call(form.querySelectorAll("input,select,textarea,button"), function (control) {
            if (exceptions.indexOf(control) !== -1) return;
            control.disabled = !enabled;
        });
    }

    function invalidateAll() {
        loaded.general = false;
        loaded.security = false;
        loaded.theme = false;
        loaded.mail = false;
    }

    function showTab(name) {
        activeTab = name;
        document.querySelectorAll("[data-settings-tab]").forEach(function (button) {
            var active = button.getAttribute("data-settings-tab") === name;
            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });
        document.querySelectorAll("[data-settings-panel]").forEach(function (panel) {
            panel.hidden = panel.getAttribute("data-settings-panel") !== name;
        });
        if (!loaded[name]) loadSection(name);
        refreshIcons();
    }

    async function loadSection(name) {
        if (!bootstrapData) return;
        if (name === "general") return loadGeneral();
        if (name === "security") return loadSecurity();
        if (name === "theme") return loadTheme();
        if (name === "mail") return loadMail();
    }

    document.querySelectorAll("[data-settings-tab]").forEach(function (button) {
        button.addEventListener("click", function () {
            showTab(button.getAttribute("data-settings-tab"));
        });
    });

    branchSelect.addEventListener("change", function () {
        invalidateAll();
        showTab(activeTab);
    });

    /* GENERAL -------------------------------------------------------------- */

    var generalForm = document.getElementById("generalSettingsForm");
    var generalFields = {
        business_name: document.getElementById("businessName"),
        business_short_name: document.getElementById("businessShortName"),
        legal_name: document.getElementById("legalName"),
        gst_number: document.getElementById("gstNumber"),
        pan_number: document.getElementById("panNumber"),
        business_email: document.getElementById("businessEmail"),
        business_mobile: document.getElementById("businessMobile"),
        website: document.getElementById("businessWebsite"),
        address_line_1: document.getElementById("addressLine1"),
        address_line_2: document.getElementById("addressLine2"),
        city: document.getElementById("businessCity"),
        state: document.getElementById("businessState"),
        pincode: document.getElementById("businessPincode"),
        country: document.getElementById("businessCountry"),
        currency_code: document.getElementById("currencyCode"),
        currency_symbol: document.getElementById("currencySymbol"),
        timezone: document.getElementById("timezone"),
        date_format: document.getElementById("dateFormat"),
        financial_year_start: document.getElementById("financialYearStart"),
        invoice_footer: document.getElementById("invoiceFooter"),
        terms_conditions: document.getElementById("termsConditions"),
        authorized_signatory: document.getElementById("authorizedSignatory")
    };
    var removeLogo = document.getElementById("removeCompanyLogo");
    var removeSignature = document.getElementById("removeDigitalSignature");

    /* Images are handled globally by assets/js/file-upload.js using the common AppModal. */
    var companyLogoUploader = FileUpload.init("#companyLogo", {
        multiple: false,
        minFiles: 0,
        maxFiles: 1,
        minSizeMB: 0,
        maxSizeMB: 10,
        maxImageSizeMB: 10,
        allowedTypes: ["jpg", "jpeg", "png", "webp"],
        prompt: "Drag and drop company logo here",
        chooseText: "or click to choose",
        clearText: "Clear",
        removeInput: "#removeCompanyLogo"
    });
    var digitalSignatureUploader = FileUpload.init("#digitalSignature", {
        multiple: false,
        minFiles: 0,
        maxFiles: 1,
        minSizeMB: 0,
        maxSizeMB: 10,
        maxImageSizeMB: 10,
        allowedTypes: ["jpg", "jpeg", "png", "webp"],
        prompt: "Drag and drop digital signature here",
        chooseText: "or click to choose",
        clearText: "Clear",
        removeInput: "#removeDigitalSignature"
    });

    var saveGeneralButton = document.getElementById("saveGeneralButton");
    var resetGeneralButton = document.getElementById("resetGeneralButton");
    var generalScopeNote = document.getElementById("generalScopeNote");

    function applyGeneral(data) {
        var values = data.values || {};

        /* Keep the Timezone field reusable even when the database contains a
           valid timezone that is not one of the common quick options above. */
        var timezoneValue = values.timezone || "Asia/Kolkata";
        var timezoneExists = Array.prototype.some.call(generalFields.timezone.options, function (option) {
            return String(option.value) === String(timezoneValue);
        });
        if (!timezoneExists) {
            var timezoneOption = document.createElement("option");
            timezoneOption.value = timezoneValue;
            timezoneOption.textContent = timezoneValue;
            generalFields.timezone.appendChild(timezoneOption);
        }

        Object.keys(generalFields).forEach(function (key) {
            generalFields[key].value = values[key] === undefined || values[key] === null ? "" : String(values[key]);
        });

        companyLogoUploader.setCurrentFile(
            data.logo_url ? { url: data.logo_url, name: "Company Logo" } : null
        );
        digitalSignatureUploader.setCurrentFile(
            data.signature_url ? { url: data.signature_url, name: "Digital Signature" } : null
        );

        generalScopeNote.textContent = data.branch_id === null
            ? "Platform Default general configuration."
            : (data.has_scope_override
                ? scopeText(data, "", "Branch overrides are active.")
                : scopeText(data, "", "Inheriting Platform Default values."));

        var canManage = Boolean(data.can_manage);
        setFormControls(generalForm, canManage);
        saveGeneralButton.hidden = !canManage;
        resetGeneralButton.hidden = !canManage;
        refreshIcons();
    }

    async function loadGeneral() {
        try {
            var result = await App.api(sectionUrl("general"));
            applyGeneral(result.data || {});
            loaded.general = true;
        } catch (error) {
            App.showError(error, "Unable to load general settings.");
        }
    }


    generalForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (window.Validation) {
            Validation.clearForm(generalForm);
            if (!Validation.validateForm(generalForm)) return;
        } else if (!generalForm.checkValidity()) {
            generalForm.reportValidity();
            return;
        }

        var formData = new FormData(generalForm);
        formData.set("section", "general");
        if (userIsPlatform()) formData.set("branch_id", currentBranchId() || "");
        formData.set("remove_logo", removeLogo.value === "1" ? "1" : "0");
        formData.set("remove_signature", removeSignature.value === "1" ? "1" : "0");

        saveGeneralButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", { method: "POST", body: formData });
            showToast(result.message || "General settings saved.", { type: "success", duration: 3 });
            loaded.general = false;
            await loadGeneral();
        } catch (error) {
            App.showError(error, "Unable to save general settings.");
        } finally {
            saveGeneralButton.disabled = false;
        }
    });

    resetGeneralButton.addEventListener("click", async function () {
        if (!window.confirm("Reset the General settings for this scope?")) return;
        resetGeneralButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "POST",
                body: addScope({ section: "general", action: "reset" })
            });
            showToast(result.message || "General settings reset.", { type: "success", duration: 3 });
            loaded.general = false;
            await loadGeneral();
        } catch (error) {
            App.showError(error, "Unable to reset general settings.");
        } finally {
            resetGeneralButton.disabled = false;
        }
    });

    /* SECURITY ------------------------------------------------------------- */

    var securityForm = document.getElementById("securitySettingsForm");
    var expiryValue = document.getElementById("expiryValue");
    var expiryUnit = document.getElementById("expiryUnit");
    var idleValue = document.getElementById("idleValue");
    var idleUnit = document.getElementById("idleUnit");
    var saveSecurityButton = document.getElementById("saveSecurityButton");
    var resetSecurityButton = document.getElementById("resetSecurityButton");
    var securityScopeNote = document.getElementById("securityScopeNote");

    function displayMinutes(minutes, value, unit) {
        minutes = Number(minutes || 0);
        if (minutes % 1440 === 0) {
            unit.value = "1440";
            value.value = minutes / 1440;
        } else if (minutes % 60 === 0) {
            unit.value = "60";
            value.value = minutes / 60;
        } else {
            unit.value = "1";
            value.value = minutes;
        }
    }

    function applySecurity(data) {
        displayMinutes(data.absolute_login_expiry_minutes || data.token_expiry_minutes, expiryValue, expiryUnit);
        displayMinutes(data.idle_expiry_minutes || data.idle_timeout_minutes, idleValue, idleUnit);
        securityScopeNote.textContent = data.branch_id === null
            ? "Platform Default security configuration."
            : (data.has_scope_override
                ? scopeText(data, "", "Branch security override is active.")
                : scopeText(data, "", "Inheriting Platform Default security."));
        var canManage = Boolean(data.can_manage);
        setFormControls(securityForm, canManage);
        saveSecurityButton.hidden = !canManage;
        resetSecurityButton.hidden = !canManage;
        refreshIcons();
    }

    async function loadSecurity() {
        try {
            var result = await App.api(sectionUrl("security"));
            applySecurity(result.data || {});
            loaded.security = true;
        } catch (error) {
            App.showError(error, "Unable to load security settings.");
        }
    }

    securityForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        var tokenMinutes = Math.round(Number(expiryValue.value) * Number(expiryUnit.value));
        var idleMinutes = Math.round(Number(idleValue.value) * Number(idleUnit.value));

        if (!Number.isFinite(tokenMinutes) || tokenMinutes < 5 || tokenMinutes > 43200) {
            showToast("Absolute login expiry must be between 5 minutes and 30 days.", { type: "warning", duration: 4 });
            return;
        }
        if (!Number.isFinite(idleMinutes) || idleMinutes < 5 || idleMinutes > 10080) {
            showToast("Idle expiry must be between 5 minutes and 7 days.", { type: "warning", duration: 4 });
            return;
        }
        if (idleMinutes > tokenMinutes) {
            showToast("Idle expiry cannot be greater than the absolute login expiry.", { type: "warning", duration: 4 });
            return;
        }

        saveSecurityButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "PUT",
                body: addScope({
                    section: "security",
                    absolute_login_expiry_minutes: tokenMinutes,
                    idle_expiry_minutes: idleMinutes
                })
            });
            showToast(result.message || "Security settings saved.", { type: "success", duration: 3 });
            loaded.security = false;
            await loadSecurity();
        } catch (error) {
            App.showError(error, "Unable to save security settings.");
        } finally {
            saveSecurityButton.disabled = false;
        }
    });

    resetSecurityButton.addEventListener("click", async function () {
        if (!window.confirm("Reset the Security settings for this scope?")) return;
        resetSecurityButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "POST",
                body: addScope({ section: "security", action: "reset" })
            });
            showToast(result.message || "Security settings reset.", { type: "success", duration: 3 });
            loaded.security = false;
            await loadSecurity();
        } catch (error) {
            App.showError(error, "Unable to reset security settings.");
        } finally {
            resetSecurityButton.disabled = false;
        }
    });

    /* THEME ---------------------------------------------------------------- */

    var themeScopeNote = document.getElementById("themeScopeNote");
    var presetGrid = document.getElementById("themePresets");
    var themeGrid = document.getElementById("themeGrid");
    var saveThemeButton = document.getElementById("saveThemeButton");
    var resetThemeButton = document.getElementById("resetThemeButton");
    var previewPresetName = document.getElementById("previewPresetName");
    var themeFields = [];
    var currentThemeData = null;
    var selectedPreset = "forest";
    var presetChanged = false;
    var customChanges = {};

    function themeFieldByKey(key) {
        return themeFields.find(function (item) { return item.key === key; }) || null;
    }

    function themeVariableNames() {
        return themeFields.map(function (field) { return field.css_variable; }).filter(Boolean);
    }

    function themePresetMeta(id) {
        return ((currentThemeData && currentThemeData.presets) || []).find(function (item) { return item.id === id; }) || null;
    }

    function validHex(value) {
        return /^#[0-9a-fA-F]{6}$/.test(String(value || "").trim());
    }

    function syncThemePair(key, value) {
        var color = document.querySelector('[data-theme-color="' + key + '"]');
        var text = document.querySelector('[data-theme-text="' + key + '"]');
        if (color && validHex(value)) color.value = value;
        if (text) text.value = String(value || "").toUpperCase();
    }

    function computedThemeValue(field) {
        if (!field) return "#000000";
        var value = window.Theme ? Theme.computedHex(field.css_variable) : field.value;
        return validHex(value) ? String(value).toLowerCase() : String(field.value || field.default || "#000000").toLowerCase();
    }

    function updateThemePresetCards() {
        presetGrid.querySelectorAll(".theme-preset-card").forEach(function (card) {
            var active = card.dataset.preset === selectedPreset;
            card.classList.toggle("is-active", active);
            card.setAttribute("aria-pressed", active ? "true" : "false");
        });
        var meta = themePresetMeta(selectedPreset);
        previewPresetName.textContent = meta ? meta.label : "Live Preview";
    }

    function renderThemePresets(data) {
        presetGrid.innerHTML = "";
        (data.presets || []).forEach(function (item) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "theme-preset-card";
            button.dataset.preset = item.id;
            button.setAttribute("aria-pressed", "false");
            button.innerHTML = '<span class="theme-preset-swatch" aria-hidden="true"></span>' +
                '<span class="theme-preset-copy"><strong></strong><small></small><span class="theme-preset-profile"></span></span>';
            button.querySelector("strong").textContent = item.label;
            button.querySelector("small").textContent = item.description;
            button.querySelector(".theme-preset-profile").textContent = item.profile;
            button.disabled = data.can_update === false;
            button.addEventListener("click", function () {
                if (data.can_update === false) return;
                selectedPreset = item.id;
                presetChanged = true;
                customChanges = {};
                if (window.Theme) {
                    Theme.clearCssVariables(themeVariableNames());
                    Theme.setPreset(selectedPreset);
                }
                updateThemePresetCards();
                renderThemeFields(data, true);
                showToast(item.label + " preview applied. Save Theme to keep it.", { type: "info", duration: 3 });
            });
            presetGrid.appendChild(button);
        });
        updateThemePresetCards();
    }

    function renderThemeFields(data, fromPreset) {
        themeFields = data.fields || themeFields || [];
        themeGrid.innerHTML = "";
        themeFields.forEach(function (field) {
            var value = computedThemeValue(field);
            var source = fromPreset ? "preset" : (field.source || "preset");
            var card = document.createElement("div");
            card.className = "theme-color-card";
            card.innerHTML = '<span class="theme-source">' + String(source) + '</span>' +
                '<input type="color" data-theme-color="' + field.key + '" value="' + value + '" aria-label="' + field.label + ' color">' +
                '<div class="theme-meta"><label class="theme-label">' + field.label + '</label>' +
                '<input type="text" maxlength="7" data-theme-text="' + field.key + '" value="' + String(value).toUpperCase() + '" aria-label="' + field.label + ' HEX value"></div>';
            themeGrid.appendChild(card);
        });

        themeGrid.querySelectorAll("[data-theme-color]").forEach(function (input) {
            input.disabled = data.can_update === false;
            input.addEventListener("input", function () {
                var key = input.dataset.themeColor;
                var field = themeFieldByKey(key);
                syncThemePair(key, input.value);
                customChanges[key] = input.value.toLowerCase();
                if (window.Theme && field) {
                    var payload = {};
                    payload[field.css_variable] = input.value;
                    Theme.applyCssVariables(payload, false);
                }
                var sourceNode = input.closest(".theme-color-card").querySelector(".theme-source");
                if (sourceNode) sourceNode.textContent = "custom";
            });
        });

        themeGrid.querySelectorAll("[data-theme-text]").forEach(function (input) {
            input.disabled = data.can_update === false;
            input.addEventListener("input", function () {
                var value = input.value.trim();
                if (!validHex(value)) return;
                var key = input.dataset.themeText;
                var field = themeFieldByKey(key);
                syncThemePair(key, value.toLowerCase());
                customChanges[key] = value.toLowerCase();
                if (window.Theme && field) {
                    var payload = {};
                    payload[field.css_variable] = value;
                    Theme.applyCssVariables(payload, false);
                }
                var sourceNode = input.closest(".theme-color-card").querySelector(".theme-source");
                if (sourceNode) sourceNode.textContent = "custom";
            });
            input.addEventListener("blur", function () {
                if (validHex(input.value.trim())) return;
                var field = themeFieldByKey(input.dataset.themeText);
                syncThemePair(input.dataset.themeText, computedThemeValue(field));
                showToast("Invalid HEX color.", { type: "warning", duration: 3 });
            });
        });
        refreshIcons();
    }

    function applyTheme(data) {
        currentThemeData = data;
        selectedPreset = data.preset || "forest";
        presetChanged = false;
        customChanges = {};
        if (window.Theme) Theme.applyPayload(data, true);

        var meta = themePresetMeta(selectedPreset);
        var presetLabel = meta ? meta.label : selectedPreset;
        if (data.branch_id === null) {
            themeScopeNote.textContent = "Platform Default · " + presetLabel + ".";
        } else if (data.preset_source === "branch") {
            themeScopeNote.textContent = scopeText(data, "", presetLabel + " (branch preset).");
        } else {
            themeScopeNote.textContent = scopeText(data, "", "Inheriting " + presetLabel + " from platform.");
        }

        saveThemeButton.hidden = data.can_update === false;
        resetThemeButton.hidden = data.can_update === false;
        renderThemePresets(data);
        renderThemeFields(data, false);
        refreshIcons();
    }

    async function loadTheme() {
        presetGrid.innerHTML = '<div class="empty">Loading theme presets...</div>';
        themeGrid.innerHTML = '<div class="empty">Loading colors...</div>';
        try {
            var result = await App.api(sectionUrl("theme"));
            applyTheme(result.data || {});
            loaded.theme = true;
        } catch (error) {
            presetGrid.innerHTML = '<div class="empty">Unable to load theme presets.</div>';
            themeGrid.innerHTML = '<div class="empty">Unable to load theme settings.</div>';
            App.showError(error, "Unable to load theme settings.");
        }
    }

    saveThemeButton.addEventListener("click", async function () {
        saveThemeButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "PUT",
                body: addScope({
                    section: "theme",
                    preset: selectedPreset,
                    colors: customChanges,
                    clear_colors: presetChanged
                })
            });
            applyTheme(result.data || {});
            loaded.theme = true;
            if (window.Theme && String((result.data || {}).branch_id || "") === String(Theme.currentBranchId() || "")) {
                Theme.cacheTheme(result.data.branch_id, result.data);
            }
            showToast(result.message || "Theme saved successfully.", { type: "success", duration: 3 });
        } catch (error) {
            App.showError(error, "Unable to save theme settings.");
        } finally {
            saveThemeButton.disabled = false;
        }
    });

    resetThemeButton.addEventListener("click", async function () {
        if (!window.confirm("Reset the Theme settings for this scope?")) return;
        resetThemeButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "PUT",
                body: addScope({ section: "theme", reset: true, action: "reset" })
            });
            applyTheme(result.data || {});
            loaded.theme = true;
            showToast(result.message || "Theme reset successfully.", { type: "success", duration: 3 });
        } catch (error) {
            App.showError(error, "Unable to reset theme settings.");
        } finally {
            resetThemeButton.disabled = false;
        }
    });

    /* MAIL ----------------------------------------------------------------- */

    var mailForm = document.getElementById("mailSettingsForm");
    var mailScopeNote = document.getElementById("mailScopeNote");
    var mailInheritNotice = document.getElementById("mailInheritNotice");
    var smtpHost = document.getElementById("smtpHost");
    var smtpPort = document.getElementById("smtpPort");
    var smtpEncryption = document.getElementById("smtpEncryption");
    var smtpAuth = document.getElementById("smtpAuth");
    var mailAuthFields = document.getElementById("mailAuthFields");
    var smtpUsername = document.getElementById("smtpUsername");
    var smtpPassword = document.getElementById("smtpPassword");
    var smtpPasswordHelp = document.getElementById("smtpPasswordHelp");
    var fromName = document.getElementById("fromName");
    var fromEmail = document.getElementById("fromEmail");
    var replyName = document.getElementById("replyName");
    var replyEmail = document.getElementById("replyEmail");
    var timeoutSeconds = document.getElementById("timeoutSeconds");
    var testEmail = document.getElementById("testEmail");
    var testMailButton = document.getElementById("testMailButton");
    var saveMailButton = document.getElementById("saveMailButton");
    var resetMailButton = document.getElementById("resetMailButton");
    var mailerStatus = document.getElementById("mailerStatus");
    var mailCanManage = false;

    function toggleMailAuth() {
        mailAuthFields.classList.toggle("hidden", !smtpAuth.checked);
        smtpUsername.required = smtpAuth.checked;
    }

    function mailPayload(action) {
        var body = {
            section: "mail",
            smtp_host: smtpHost.value.trim(),
            smtp_port: Number(smtpPort.value),
            smtp_encryption: smtpEncryption.value,
            smtp_auth: smtpAuth.checked ? 1 : 0,
            smtp_username: smtpUsername.value.trim(),
            smtp_password: smtpPassword.value,
            from_email: fromEmail.value.trim(),
            from_name: fromName.value.trim(),
            reply_to_email: replyEmail.value.trim(),
            reply_to_name: replyName.value.trim(),
            timeout_seconds: Number(timeoutSeconds.value || 20),
            status: 1
        };
        if (action) body.action = action;
        return addScope(body);
    }

    function applyMail(data) {
        var settings = data.settings || {};
        mailCanManage = Boolean(data.can_manage);
        smtpHost.value = settings.smtp_host || "";
        smtpPort.value = settings.smtp_port || 587;
        smtpEncryption.value = settings.smtp_encryption || "tls";
        smtpAuth.checked = settings.smtp_auth === undefined ? true : Number(settings.smtp_auth) === 1;
        smtpUsername.value = settings.smtp_username || "";
        smtpPassword.value = "";
        fromName.value = settings.from_name || "";
        fromEmail.value = settings.from_email || "";
        replyName.value = settings.reply_to_name || "";
        replyEmail.value = settings.reply_to_email || "";
        timeoutSeconds.value = settings.timeout_seconds || 20;
        smtpPasswordHelp.textContent = settings.smtp_password_configured
            ? "Password is configured. Leave blank to keep it unchanged."
            : "Required for the first authenticated SMTP configuration.";
        toggleMailAuth();

        mailInheritNotice.classList.toggle("hidden", !data.is_inherited);
        mailScopeNote.textContent = data.branch_id === null
            ? "Platform Default SMTP configuration."
            : (data.has_scope_override
                ? scopeText(data, "", "Branch SMTP override is active.")
                : scopeText(data, "", "Inheriting Platform Default SMTP."));

        saveMailButton.hidden = !mailCanManage;
        testMailButton.hidden = !mailCanManage;
        resetMailButton.hidden = !mailCanManage;
        setFormControls(mailForm, mailCanManage, [testEmail]);
        testEmail.disabled = false;
        mailerStatus.textContent = data.phpmailer_installed
            ? "PHPMailer is installed and available."
            : "PHPMailer is not installed yet. Run composer install before sending email.";
        refreshIcons();
    }

    async function loadMail() {
        try {
            var result = await App.api(sectionUrl("mail"));
            applyMail(result.data || {});
            loaded.mail = true;
        } catch (error) {
            App.showError(error, "Unable to load mail settings.");
        }
    }

    smtpAuth.addEventListener("change", toggleMailAuth);

    mailForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!mailCanManage) return;
        if (!mailForm.checkValidity()) {
            mailForm.reportValidity();
            return;
        }
        saveMailButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", { method: "PUT", body: mailPayload() });
            showToast(result.message || "Mail settings saved.", { type: "success", duration: 3 });
            smtpPassword.value = "";
            loaded.mail = false;
            await loadMail();
        } catch (error) {
            App.showError(error, "Unable to save mail settings.");
        } finally {
            saveMailButton.disabled = false;
        }
    });

    testMailButton.addEventListener("click", async function () {
        if (!mailCanManage) return;
        if (!testEmail.value.trim()) {
            showToast("Enter the test recipient email address.", { type: "warning", duration: 3 });
            testEmail.focus();
            return;
        }
        testMailButton.disabled = true;
        try {
            var body = mailPayload("test");
            body.test_email = testEmail.value.trim();
            var result = await App.api("api/settings.php", { method: "POST", body: body });
            showToast(result.message || "Test email sent successfully.", { type: "success", duration: 4 });
        } catch (error) {
            App.showError(error, "Unable to send test email.");
        } finally {
            testMailButton.disabled = false;
        }
    });

    resetMailButton.addEventListener("click", async function () {
        if (!mailCanManage) return;
        if (!window.confirm("Reset the Mail settings for this scope?")) return;
        resetMailButton.disabled = true;
        try {
            var result = await App.api("api/settings.php", {
                method: "POST",
                body: addScope({ section: "mail", action: "reset" })
            });
            showToast(result.message || "Mail settings reset.", { type: "success", duration: 3 });
            loaded.mail = false;
            await loadMail();
        } catch (error) {
            App.showError(error, "Unable to reset mail settings.");
        } finally {
            resetMailButton.disabled = false;
        }
    });

    /* BOOTSTRAP ------------------------------------------------------------ */

    async function init() {
        try {
            var result = await App.api("api/settings.php?section=bootstrap");
            bootstrapData = result.data || {};

            if (userIsPlatform()) {
                branchSelect.innerHTML = '<option value="">Platform Default</option>';
                (bootstrapData.branches || []).forEach(function (item) {
                    var option = document.createElement("option");
                    option.value = item.id;
                    option.textContent = item.company_name + " — " + item.branch_name;
                    branchSelect.appendChild(option);
                });
                scopeField.classList.remove("hidden");
                tenantScope.classList.add("hidden");
            } else {
                scopeField.classList.add("hidden");
                tenantScope.classList.remove("hidden");
                tenantScopeName.textContent = [bootstrapData.user.company_name, bootstrapData.user.branch_name].filter(Boolean).join(" · ") || "Current Branch";
            }

            showTab("general");
            refreshIcons();
        } catch (error) {
            App.showError(error, "Unable to load Settings.");
        }
    }

    init();
})(window, document);
</script>

        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
