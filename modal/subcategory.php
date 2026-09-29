<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_subcategory_modal_rendered'])) {
    return;
}
$GLOBALS['app_subcategory_modal_rendered'] = true;
?>
<form id="appSubcategoryForm" class="app-modal-form hidden" data-component="subcategory-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-subcategory-form-title>Add Subcategory</h2>
            <p>Create or update a Subcategory under a parent Category.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Subcategory form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appSubcategoryCode">Subcategory Code</label>
                <input id="appSubcategoryCode" name="category_code" type="text" maxlength="50" readonly aria-readonly="true" placeholder="Auto generated">
            </div>

            <div class="field">
                <label for="appSubcategoryParent" class="required">Category</label>
                <select id="appSubcategoryParent" name="parent_id" required data-required-message="Category is required.">
                    <option value="">Select Category</option>
                </select>
            </div>

            <div class="field">
                <label for="appSubcategoryName" class="required">Subcategory Name</label>
                <input id="appSubcategoryName" name="category_name" type="text" maxlength="120" required
                       placeholder="Enter subcategory name"
                       data-required-message="Subcategory name is required.">
            </div>

            <div class="field full">
                <label for="appSubcategoryDescription">Description</label>
                <textarea id="appSubcategoryDescription" name="description" rows="3" maxlength="255" placeholder="Enter description"></textarea>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-subcategory-save>Save Subcategory</button>
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
        form = document.querySelector('[data-component="subcategory-form"]');
        if (!form) return false;

        titleNode = form.querySelector("[data-subcategory-form-title]");
        saveButton = form.querySelector("[data-subcategory-save]");
        form.addEventListener("submit", submit);

        initialized = true;
        return true;
    }

    function reset() {
        if (!ensure()) return;
        form.reset();
        form.id.value = "";
        form.category_code.value = "";
        form.parent_id.innerHTML = '<option value="">Select Category</option>';
        if (window.Validation) Validation.clearForm(form);
    }

    function populateCategories(categories, selectedId) {
        form.parent_id.innerHTML = '<option value="">Select Category</option>';
        (categories || []).forEach(function (row) {
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = row.category_code ? (row.category_code + " - " + row.category_name) : row.category_name;
            form.parent_id.appendChild(option);
        });
        if (selectedId) form.parent_id.value = String(selectedId);
    }

    function fill(row) {
        form.id.value = row && row.id ? String(row.id) : "";
        form.category_code.value = row && row.category_code ? String(row.category_code) : "";
        form.category_name.value = row && row.category_name ? String(row.category_name) : "";
        form.description.value = row && row.description ? String(row.description) : "";
        form.parent_id.value = row && row.parent_id ? String(row.parent_id) : "";
    }

    function openModal(options) {
        if (!window.AppModal) {
            if (window.App) App.showError(null, "Common modal component is unavailable.");
            return false;
        }

        if (titleNode) titleNode.textContent = options.title || (options.mode === "edit" ? "Edit Subcategory" : "Add Subcategory");
        if (saveButton) saveButton.textContent = options.mode === "edit" ? "Update Subcategory" : "Save Subcategory";

        AppModal.open(form, {
            size: options.size || "lg",
            focusSelector: options.parentId ? '[name="category_name"]' : '[name="parent_id"]',
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
        var apiUrl = options.apiUrl || "api/subcategory.php";

        reset();

        if (mode === "edit") {
            var id = Number(options.id || 0);
            if (!id) {
                App.showError(null, "Subcategory record ID is required.");
                activeOptions = null;
                return false;
            }

            try {
                var result = await App.api(apiUrl + "?id=" + id);
                var row = result.data.subcategory || {};
                var optionsResult = await App.api(apiUrl + "?options=1&include_parent_id=" + encodeURIComponent(row.parent_id || ""));
                populateCategories(optionsResult.data.categories || [], row.parent_id || 0);
                fill(row);
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Subcategory record.");
                return false;
            }
        } else {
            try {
                var query = "?options=1";
                if (options.parentId) query += "&include_parent_id=" + encodeURIComponent(options.parentId);
                var optionsResult = await App.api(apiUrl + query);
                form.category_code.value = optionsResult.data.next_subcategory_code || "";
                populateCategories(optionsResult.data.categories || [], options.parentId || 0);
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Subcategory form.");
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
            parent_id: Number(form.parent_id.value || 0),
            category_name: form.category_name.value.trim(),
            description: form.description.value.trim()
        };

        saveButton.disabled = true;
        try {
            var result = await App.api((activeOptions && activeOptions.apiUrl) || "api/subcategory.php", {
                method: id ? "PUT" : "POST",
                body: body
            });

            var subcategory = result.data && result.data.subcategory ? result.data.subcategory : null;

            if (typeof window.showToast === "function") {
                showToast(result.message || "Subcategory saved successfully.", { type: "success", duration: 2 });
            }

            var callback = activeOptions && typeof activeOptions.onSaved === "function" ? activeOptions.onSaved : null;

            window.dispatchEvent(new CustomEvent("app:subcategory-saved", {
                detail: { subcategory: subcategory, mode: mode }
            }));

            if (!activeOptions || activeOptions.closeOnSaved !== false) {
                if (window.AppModal && AppModal.isOpen()) AppModal.close("saved");
            }

            if (callback) callback(subcategory, result);
        } catch (error) {
            var applied = false;
            if (window.Validation && error && error.errors && Object.keys(error.errors).length) {
                applied = Validation.applyErrors(form, error.errors);
            }
            if (!applied) App.showError(error, "Unable to save Subcategory record.");
        } finally {
            saveButton.disabled = false;
        }
    }

    function close() {
        if (window.AppModal && AppModal.isOpen()) return AppModal.close("programmatic");
        return false;
    }

    window.AppSubcategoryForm = {
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
