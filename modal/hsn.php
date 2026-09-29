<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_hsn_modal_rendered'])) {
    return;
}
$GLOBALS['app_hsn_modal_rendered'] = true;
?>
<form id="appHsnForm" class="app-modal-form hidden" data-component="hsn-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-hsn-form-title>Add HSN</h2>
            <p>Maintain the HSN code and applicable tax split. GST can automatically suggest CGST, SGST and IGST.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close HSN form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appHsnCode" class="required">HSN Code</label>
                <input id="appHsnCode" name="hsn_code" type="text" inputmode="numeric" autocomplete="off" maxlength="8" required
                       data-regex="^[0-9]{2,8}$"
                       data-required-message="HSN code is required."
                       data-regex-message="HSN code must contain 2 to 8 digits."
                       placeholder="Example: 0910">
            </div>

            <div class="field two-span">
                <label for="appHsnDescription">Description</label>
                <input id="appHsnDescription" name="description" type="text" maxlength="255" placeholder="Example: Spices">
            </div>

            <div class="field">
                <label for="appHsnGst">GST %</label>
                <input id="appHsnGst" name="gst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="GST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnCgst">CGST %</label>
                <input id="appHsnCgst" name="cgst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="CGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnSgst">SGST %</label>
                <input id="appHsnSgst" name="sgst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="SGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnIgst">IGST %</label>
                <input id="appHsnIgst" name="igst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="IGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnCess">Cess %</label>
                <input id="appHsnCess" name="cess_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="Cess must be between 0 and 100.">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-hsn-save>Save HSN</button>
        </div>
    </div>
</form>

<script>
(function (window, document) {
    "use strict";

    var form = null;
    var titleNode = null;
    var saveButton = null;
    var activeOptions = null;
    var initialized = false;
    var splitTouched = false;

    function ensure() {
        if (initialized) return Boolean(form);
        form = document.querySelector('[data-component="hsn-form"]');
        if (!form) return false;

        titleNode = form.querySelector("[data-hsn-form-title]");
        saveButton = form.querySelector("[data-hsn-save]");

        form.gst_rate.addEventListener("input", function () {
            if (!splitTouched) suggestSplit();
        });

        ["cgst_rate", "sgst_rate", "igst_rate"].forEach(function (name) {
            form.elements[name].addEventListener("input", function (event) {
                if (document.activeElement === event.target) splitTouched = true;
            });
        });

        form.addEventListener("submit", submit);
        initialized = true;
        return true;
    }

    function formatRate(value) {
        var number = Number(value || 0);
        return Number.isFinite(number) ? number.toFixed(2) : "0.00";
    }

    function rate(name) {
        return Number(form.elements[name].value || 0);
    }

    function reset() {
        if (!ensure()) return;
        form.reset();
        form.id.value = "";
        ["gst_rate", "cgst_rate", "sgst_rate", "igst_rate", "cess_rate"].forEach(function (name) {
            form.elements[name].value = "0.00";
        });
        splitTouched = false;
        if (window.Validation) Validation.clearForm(form);
    }

    function suggestSplit() {
        if (!ensure()) return;
        var gst = Math.max(0, Number(form.gst_rate.value || 0));
        form.cgst_rate.value = formatRate(gst / 2);
        form.sgst_rate.value = formatRate(gst / 2);
        form.igst_rate.value = formatRate(gst);
    }

    function fill(row) {
        reset();
        form.id.value = row && row.id ? String(row.id) : "";
        form.hsn_code.value = row && row.hsn_code ? String(row.hsn_code) : "";
        form.description.value = row && row.description ? String(row.description) : "";
        ["gst_rate", "cgst_rate", "sgst_rate", "igst_rate", "cess_rate"].forEach(function (name) {
            form.elements[name].value = formatRate(row && row[name]);
        });
        splitTouched = true;
    }

    function openModal(options) {
        if (!window.AppModal) {
            if (window.App) App.showError(null, "Common modal component is unavailable.");
            return false;
        }

        if (titleNode) titleNode.textContent = options.title || (options.mode === "edit" ? "Edit HSN" : "Add HSN");

        AppModal.open(form, {
            size: options.size || "lg",
            focusSelector: '[name="hsn_code"]',
            onClose: function (reason) {
                if (window.Validation) Validation.clearForm(form);
                var closedOptions = activeOptions;
                activeOptions = null;
                if (closedOptions && typeof closedOptions.onClosed === "function") {
                    closedOptions.onClosed(reason || "close");
                }
            }
        });

        if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
        return true;
    }

    async function open(options) {
        options = options || {};
        if (!ensure()) return false;

        activeOptions = options;
        var mode = String(options.mode || (options.id ? "edit" : "create")).toLowerCase();
        options.mode = mode;

        if (mode === "edit") {
            var id = Number(options.id || 0);
            if (!id) {
                if (window.App) App.showError(null, "HSN record ID is required.");
                activeOptions = null;
                return false;
            }

            try {
                var result = await App.api((options.apiUrl || "api/hsn.php") + "?id=" + id);
                fill(result.data.hsn || {});
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load HSN record.");
                return false;
            }
        } else {
            reset();
            if (options.initialData && typeof options.initialData === "object") {
                Object.keys(options.initialData).forEach(function (name) {
                    if (form.elements[name]) form.elements[name].value = String(options.initialData[name] == null ? "" : options.initialData[name]);
                });
                if (Object.prototype.hasOwnProperty.call(options.initialData, "gst_rate")) suggestSplit();
            }
        }

        return openModal(options);
    }

    async function submit(event) {
        event.preventDefault();
        if (!activeOptions) activeOptions = { mode: form.id.value ? "edit" : "create" };

        if (window.Validation) {
            Validation.clearForm(form);
            if (!Validation.validateForm(form)) return;
        }

        var id = Number(form.id.value || 0);
        var mode = id ? "edit" : "create";
        var body = {
            id: id || undefined,
            hsn_code: form.hsn_code.value.trim(),
            description: form.description.value.trim(),
            gst_rate: rate("gst_rate"),
            cgst_rate: rate("cgst_rate"),
            sgst_rate: rate("sgst_rate"),
            igst_rate: rate("igst_rate"),
            cess_rate: rate("cess_rate")
        };

        saveButton.disabled = true;
        try {
            var result = await App.api(activeOptions.apiUrl || "api/hsn.php", {
                method: id ? "PUT" : "POST",
                body: body
            });

            var hsn = result.data && result.data.hsn ? result.data.hsn : null;

            if (typeof window.showToast === "function") {
                showToast(result.message || "HSN saved successfully.", { type: "success", duration: 2 });
            }

            var callback = activeOptions && typeof activeOptions.onSaved === "function" ? activeOptions.onSaved : null;

            window.dispatchEvent(new CustomEvent("app:hsn-saved", {
                detail: { hsn: hsn, mode: mode }
            }));

            if (activeOptions.closeOnSaved !== false && window.AppModal && AppModal.isOpen()) {
                AppModal.close("saved");
            }

            if (callback) callback(hsn, result);
        } catch (error) {
            var applied = false;
            if (window.Validation && error && error.errors && Object.keys(error.errors).length) {
                applied = Validation.applyErrors(form, error.errors);
            }
            if (!applied) App.showError(error, "Unable to save HSN record.");
        } finally {
            saveButton.disabled = false;
        }
    }

    function close() {
        if (window.AppModal && AppModal.isOpen()) return AppModal.close("programmatic");
        return false;
    }

    window.AppHSNForm = {
        open: open,
        openCreate: function (options) {
            options = options || {};
            options.mode = "create";
            return open(options);
        },
        openEdit: function (id, options) {
            options = options || {};
            options.mode = "edit";
            options.id = id;
            return open(options);
        },
        close: close,
        reset: reset,
        getForm: function () { ensure(); return form; }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", ensure);
    } else {
        ensure();
    }
})(window, document);
</script>
