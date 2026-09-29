<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Theme Settings';
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
<script src="assets/js/datatable.js"></script>

<div class="page-head">
    <div>
        <h1>Theme Settings</h1>
        <p>Choose one of 10 complete UI themes, then optionally fine-tune semantic colors.</p>
    </div>
    <a class="btn btn-soft" href="settings.php"><i data-lucide="shield-check"></i> Security Settings</a>
</div>

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
        <div class="field" id="scopeField">
            <label for="branchSelect">Theme Scope</label>
            <select id="branchSelect"><option value="">Platform Default</option></select>
        </div>
        <div class="theme-scope-note" id="scopeNote">Loading theme scope...</div>
    </div>

    <div class="theme-custom-heading">
        <div>
            <h2>10 Ready Themes</h2>
            <p>Selecting a preset changes the complete design profile, not only the brand color.</p>
        </div>
    </div>
    <div id="themePresets" class="theme-presets"><div class="empty">Loading theme presets...</div></div>

    <div class="theme-custom-heading">
        <div>
            <h2>Custom Color Overrides</h2>
            <p>Optional. These colors override only the selected preset's semantic colors; font, radius, density and layout remain preset-driven.</p>
        </div>
    </div>
    <div id="themeGrid" class="theme-grid"><div class="empty">Loading colors...</div></div>

    <div class="theme-actions">
        <button class="btn btn-soft" id="resetButton" type="button"><i data-lucide="rotate-ccw"></i> Reset / Inherit</button>
        <button class="btn btn-primary" id="saveButton" type="button"><i data-lucide="save"></i> Save Theme</button>
    </div>
</div>

<script>
(function(){
    "use strict";

    var user = App.getUser();
    var branchSelect = document.getElementById("branchSelect");
    var scopeField = document.getElementById("scopeField");
    var scopeNote = document.getElementById("scopeNote");
    var presetGrid = document.getElementById("themePresets");
    var grid = document.getElementById("themeGrid");
    var saveButton = document.getElementById("saveButton");
    var resetButton = document.getElementById("resetButton");
    var previewPresetName = document.getElementById("previewPresetName");

    var fields = [];
    var currentData = null;
    var selectedPreset = "forest";
    var presetChanged = false;
    var customChanges = {};

    function queryString() {
        return Number(user.role_type) === 2 && branchSelect.value
            ? "?branch_id=" + encodeURIComponent(branchSelect.value)
            : "";
    }

    function selectedBranchBody() {
        return Number(user.role_type) === 2 ? (branchSelect.value || null) : undefined;
    }

    function fieldByKey(key) {
        return fields.find(function(item){ return item.key === key; }) || null;
    }

    function fieldVariableNames() {
        return fields.map(function(field){ return field.css_variable; }).filter(Boolean);
    }

    function presetMeta(id) {
        return ((currentData && currentData.presets) || []).find(function(item){ return item.id === id; }) || null;
    }

    function validHex(value) {
        return /^#[0-9a-fA-F]{6}$/.test(String(value || "").trim());
    }

    function syncPair(key, value) {
        var color = document.querySelector('[data-theme-color="' + key + '"]');
        var text = document.querySelector('[data-theme-text="' + key + '"]');
        if (color && validHex(value)) color.value = value;
        if (text) text.value = String(value || "").toUpperCase();
    }

    function computedFieldValue(field) {
        if (!field) return "#000000";
        var value = window.Theme ? Theme.computedHex(field.css_variable) : field.value;
        return validHex(value) ? String(value).toLowerCase() : String(field.value || field.default || "#000000").toLowerCase();
    }

    function updatePresetCards() {
        presetGrid.querySelectorAll(".theme-preset-card").forEach(function(card){
            var active = card.dataset.preset === selectedPreset;
            card.classList.toggle("is-active", active);
            card.setAttribute("aria-pressed", active ? "true" : "false");
        });
        var meta = presetMeta(selectedPreset);
        previewPresetName.textContent = meta ? meta.label : "Live Preview";
    }

    function renderPresets(data) {
        presetGrid.innerHTML = "";
        (data.presets || []).forEach(function(item){
            var button = document.createElement("button");
            button.type = "button";
            button.className = "theme-preset-card";
            button.dataset.preset = item.id;
            button.setAttribute("aria-pressed", "false");
            button.innerHTML =
                '<span class="theme-preset-swatch" aria-hidden="true"></span>' +
                '<span class="theme-preset-copy"><strong></strong><small></small><span class="theme-preset-profile"></span></span>';
            button.querySelector("strong").textContent = item.label;
            button.querySelector("small").textContent = item.description;
            button.querySelector(".theme-preset-profile").textContent = item.profile;
            button.addEventListener("click", function(){
                if (data.can_update === false) return;
                selectedPreset = item.id;
                presetChanged = true;
                customChanges = {};
                if (window.Theme) {
                    Theme.clearCssVariables(fieldVariableNames());
                    Theme.setPreset(selectedPreset);
                }
                updatePresetCards();
                renderFields(data, true);
                showToast(item.label + " preview applied. Save Theme to keep it.", {type:"info", duration:3});
            });
            presetGrid.appendChild(button);
        });
        updatePresetCards();
    }

    function renderFields(data, fromPreset) {
        fields = data.fields || fields || [];
        grid.innerHTML = "";
        fields.forEach(function(field){
            var value = computedFieldValue(field);
            var source = fromPreset ? "preset" : (field.source || "preset");
            var card = document.createElement("div");
            card.className = "theme-color-card";
            card.innerHTML =
                '<span class="theme-source">' + String(source) + '</span>' +
                '<input type="color" data-theme-color="' + field.key + '" value="' + value + '" aria-label="' + field.label + ' color">' +
                '<div class="theme-meta"><label class="theme-label">' + field.label + '</label>' +
                '<input type="text" maxlength="7" data-theme-text="' + field.key + '" value="' + String(value).toUpperCase() + '" aria-label="' + field.label + ' HEX value"></div>';
            grid.appendChild(card);
        });

        grid.querySelectorAll("[data-theme-color]").forEach(function(input){
            input.addEventListener("input", function(){
                var key = input.dataset.themeColor;
                var field = fieldByKey(key);
                syncPair(key, input.value);
                customChanges[key] = input.value.toLowerCase();
                if (window.Theme && field) {
                    var payload = {};
                    payload[field.css_variable] = input.value;
                    Theme.applyCssVariables(payload, false);
                }
                var source = input.closest(".theme-color-card").querySelector(".theme-source");
                if (source) source.textContent = "custom";
            });
        });

        grid.querySelectorAll("[data-theme-text]").forEach(function(input){
            input.addEventListener("input", function(){
                var value = input.value.trim();
                if (!validHex(value)) return;
                var key = input.dataset.themeText;
                var field = fieldByKey(key);
                syncPair(key, value.toLowerCase());
                customChanges[key] = value.toLowerCase();
                if (window.Theme && field) {
                    var payload = {};
                    payload[field.css_variable] = value;
                    Theme.applyCssVariables(payload, false);
                }
                var source = input.closest(".theme-color-card").querySelector(".theme-source");
                if (source) source.textContent = "custom";
            });
            input.addEventListener("blur", function(){
                if (validHex(input.value.trim())) return;
                var field = fieldByKey(input.dataset.themeText);
                syncPair(input.dataset.themeText, computedFieldValue(field));
                showToast("Invalid HEX color.", {type:"warning", duration:3});
            });
        });
        if (window.lucide) window.lucide.createIcons();
    }

    function updateScope(data) {
        currentData = data;
        selectedPreset = data.preset || "forest";
        presetChanged = false;
        customChanges = {};

        if (Number(user.role_type) === 2) {
            var selected = data.branch_id !== null ? String(data.branch_id) : "";
            branchSelect.innerHTML = '<option value="">Platform Default</option>';
            (data.branches || []).forEach(function(item){
                var option = document.createElement("option");
                option.value = item.id;
                option.textContent = item.company_name + " — " + item.branch_name;
                branchSelect.appendChild(option);
            });
            branchSelect.value = selected;
            scopeField.classList.remove("hidden");
        } else {
            scopeField.classList.add("hidden");
        }

        var meta = presetMeta(selectedPreset);
        var presetLabel = meta ? meta.label : selectedPreset;
        if (data.branch_id === null) {
            scopeNote.textContent = "Platform default · " + presetLabel + ". Preset design comes from assets/css/theme.css; optional color overrides are stored in app_settings.";
            resetButton.innerHTML = '<i data-lucide="rotate-ccw"></i> Reset to theme.css Default';
        } else if (data.preset_source === "branch") {
            scopeNote.textContent = (data.company_name ? data.company_name + " · " : "") + (data.branch_name || "Current Branch") + " · " + presetLabel + " (branch preset).";
            resetButton.innerHTML = '<i data-lucide="rotate-ccw"></i> Remove Branch Theme';
        } else {
            scopeNote.textContent = (data.company_name ? data.company_name + " · " : "") + (data.branch_name || "Current Branch") + " · inheriting " + presetLabel + " from platform.";
            resetButton.innerHTML = '<i data-lucide="rotate-ccw"></i> Remove Branch Overrides';
        }

        saveButton.style.display = data.can_update === false ? "none" : "inline-flex";
        resetButton.style.display = data.can_update === false ? "none" : "inline-flex";
        if (window.lucide) window.lucide.createIcons();
    }

    function applyLoadedData(data) {
        if (window.Theme) Theme.applyPayload(data, true);
        updateScope(data);
        renderPresets(data);
        renderFields(data, false);
    }

    async function load() {
        presetGrid.innerHTML = '<div class="empty">Loading theme presets...</div>';
        grid.innerHTML = '<div class="empty">Loading colors...</div>';
        try {
            var result = await App.api("api/theme-settings.php" + queryString());
            applyLoadedData(result.data);
        } catch(error) {
            presetGrid.innerHTML = '<div class="empty">Unable to load theme presets.</div>';
            grid.innerHTML = '<div class="empty">Unable to load theme settings.</div>';
            App.showError(error, "Unable to load theme settings.");
        }
    }

    branchSelect.addEventListener("change", load);

    saveButton.addEventListener("click", async function(){
        saveButton.disabled = true;
        try {
            var body = {
                preset: selectedPreset,
                colors: customChanges,
                clear_colors: presetChanged
            };
            var branchId = selectedBranchBody();
            if (branchId !== undefined) body.branch_id = branchId;
            var result = await App.api("api/theme-settings.php", {method:"PUT", body:body});
            applyLoadedData(result.data);
            if (window.Theme && String(result.data.branch_id || "") === String(Theme.currentBranchId() || "")) {
                Theme.cacheTheme(result.data.branch_id, result.data);
            }
            showToast(result.message || "Theme saved successfully.", {type:"success", duration:3});
        } catch(error) {
            App.showError(error, "Unable to save theme settings.");
        } finally {
            saveButton.disabled = false;
        }
    });

    resetButton.addEventListener("click", async function(){
        resetButton.disabled = true;
        try {
            var body = {reset:true};
            var branchId = selectedBranchBody();
            if (branchId !== undefined) body.branch_id = branchId;
            var result = await App.api("api/theme-settings.php", {method:"PUT", body:body});
            applyLoadedData(result.data);
            if (window.Theme && String(result.data.branch_id || "") === String(Theme.currentBranchId() || "")) {
                Theme.cacheTheme(result.data.branch_id, result.data);
            }
            showToast(result.message || "Theme reset successfully.", {type:"success", duration:3});
        } catch(error) {
            App.showError(error, "Unable to reset theme settings.");
        } finally {
            resetButton.disabled = false;
        }
    });

    load();
})();
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
</body>
</html>
