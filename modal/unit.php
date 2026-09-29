<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_unit_modal_rendered'])) {
    return;
}
$GLOBALS['app_unit_modal_rendered'] = true;
?>
<form id="appUnitForm" class="app-modal-form hidden" data-component="unit-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-unit-form-title>Add Unit</h2>
            <p>Create or update a Food Supplementary unit. Products store only the Unit ID.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Unit form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appUnitCode">Unit Code</label>
                <input id="appUnitCode" name="unit_code" type="text" maxlength="50" readonly
                       aria-readonly="true" placeholder="Auto generated">
            </div>

            <div class="field">
                <label for="appUnitName" class="required">Unit Name</label>
                <input id="appUnitName" name="unit_name" type="text" maxlength="100" required
                       placeholder="Example: Pieces"
                       data-required-message="Unit Name is required.">
            </div>

            <div class="field">
                <label for="appUnitSymbol" class="required">Unit Symbol</label>
                <input id="appUnitSymbol" name="unit_symbol" type="text" maxlength="30" required
                       placeholder="Example: PCS"
                       data-required-message="Unit Symbol is required.">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-unit-save>Save Unit</button>
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

    function ensure() {
        if (initialized) return Boolean(form);

        form = document.querySelector('[data-component="unit-form"]');
        if (!form) return false;

        titleNode = form.querySelector("[data-unit-form-title]");
        saveButton = form.querySelector("[data-unit-save]");
        form.addEventListener("submit", submit);

        form.unit_symbol.addEventListener("input", function () {
            form.unit_symbol.value = String(form.unit_symbol.value || "").toUpperCase().replace(/\s+/g, "");
        });

        initialized = true;
        return true;
    }

    function reset() {
        if (!ensure()) return;
        form.reset();
        form.id.value = "";
        form.unit_code.value = "";
        if (window.Validation) Validation.clearForm(form);
    }

    function fill(row) {
        reset();
        form.id.value = row && row.id ? String(row.id) : "";
        form.unit_code.value = row && row.unit_code ? String(row.unit_code) : "";
        form.unit_name.value = row && row.unit_name ? String(row.unit_name) : "";
        form.unit_symbol.value = row && row.unit_symbol ? String(row.unit_symbol) : "";
    }

    function openModal(options) {
        if (!window.AppModal) {
            if (window.App) App.showError(null, "Common modal component is unavailable.");
            return false;
        }

        if (titleNode) {
            titleNode.textContent = options.title || (options.mode === "edit" ? "Edit Unit" : "Add Unit");
        }
        if (saveButton) {
            saveButton.textContent = options.mode === "edit" ? "Update Unit" : "Save Unit";
        }

        AppModal.open(form, {
            size: options.size || "lg",
            focusSelector: '[name="unit_name"]',
            onClose: function (reason) {
                if (window.Validation) Validation.clearForm(form);
                var closedOptions = activeOptions;
                activeOptions = null;
                if (closedOptions && typeof closedOptions.onClosed === "function") {
                    closedOptions.onClosed(reason || "close");
                }
            }
        });

        if (window.lucide && typeof window.lucide.createIcons === "function") {
            window.lucide.createIcons();
        }
        return true;
    }

    async function open(options) {
        options = options || {};
        if (!ensure()) return false;

        activeOptions = options;
        var mode = String(options.mode || (options.id ? "edit" : "create")).toLowerCase();
        options.mode = mode;
        var apiUrl = options.apiUrl || "api/unit.php";

        if (mode === "edit") {
            var id = Number(options.id || 0);
            if (!id) {
                App.showError(null, "Unit record ID is required.");
                activeOptions = null;
                return false;
            }

            try {
                var result = await App.api(apiUrl + "?id=" + id);
                fill(result.data.unit || {});
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Unit record.");
                return false;
            }
        } else {
            reset();
            try {
                var result = await App.api(apiUrl + "?options=1");
                form.unit_code.value = result.data.next_unit_code || "";
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Unit form.");
                return false;
            }
        }

        return openModal(options);
    }

    async function submit(event) {
        event.preventDefault();

        if (window.Validation) {
            Validation.clearForm(form);
            if (!Validation.validateForm(form)) return;
        }

        var id = Number(form.id.value || 0);
        var mode = id ? "edit" : "create";
        var body = {
            id: id || undefined,
            unit_name: form.unit_name.value.trim(),
            unit_symbol: form.unit_symbol.value.trim().toUpperCase()
        };

        saveButton.disabled = true;

        try {
            var result = await App.api((activeOptions && activeOptions.apiUrl) || "api/unit.php", {
                method: id ? "PUT" : "POST",
                body: body
            });

            var unit = result.data && result.data.unit ? result.data.unit : null;

            if (typeof window.showToast === "function") {
                showToast(result.message || "Unit saved successfully.", { type: "success", duration: 2 });
            }

            var callback = activeOptions && typeof activeOptions.onSaved === "function"
                ? activeOptions.onSaved
                : null;

            window.dispatchEvent(new CustomEvent("app:unit-saved", {
                detail: { unit: unit, mode: mode }
            }));

            if (!activeOptions || activeOptions.closeOnSaved !== false) {
                if (window.AppModal && AppModal.isOpen()) AppModal.close("saved");
            }

            if (callback) callback(unit, result);
        } catch (error) {
            var applied = false;

            if (window.Validation && error && error.errors && Object.keys(error.errors).length) {
                applied = Validation.applyErrors(form, error.errors);
            }

            if (!applied) App.showError(error, "Unable to save Unit record.");
        } finally {
            saveButton.disabled = false;
        }
    }

    function close() {
        if (window.AppModal && AppModal.isOpen()) return AppModal.close("programmatic");
        return false;
    }

    window.AppUnitForm = {
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
