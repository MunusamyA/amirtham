<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Seller Type Form';
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
<script src="assets/js/validation.js"></script>

<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Seller Type</h1>
        <p>Maintain seller pricing groups such as Retail, Wholesale, Distributor and Dealer.</p>
    </div>
    <a class="btn gray" href="seller-type-list.php">
        <i data-lucide="list"></i> Seller Type List
    </a>
</div>

<div class="card form-card">
<form id="sellerTypeForm" novalidate>
    <input type="hidden" name="ref" id="sellerTypeRef">

    <div class="card-header">
        <div>
            <h2>Seller Type Details</h2>
            <p>Seller Type is branch-wise and is used by Product Sale Base Pricing.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-6">
                <label for="sellerTypeName" class="required">Seller Type Name</label>
                <input id="sellerTypeName" name="seller_type_name" type="text" maxlength="120" required
                       autocomplete="off" placeholder="Example: Retail"
                       data-required-message="Seller Type Name is required.">
            </div>

            <div class="field col-3">
                <label for="sortOrder">Sort Order</label>
                <input id="sortOrder" name="sort_order" type="number" min="0" step="1" value="0"
                       inputmode="numeric" placeholder="0">
            </div>

            <div class="field col-3">
                <label for="sellerTypeStatus" class="required">Status</label>
                <select id="sellerTypeStatus" name="status" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div class="muted" id="usageInfo" style="margin-top:10px;display:none;"></div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i> Save Seller Type
            </button>
            <a class="btn gray" href="seller-type-list.php">Cancel</a>
        </div>
    </div>
</form>
</div>

<script>
(function (window, document) {
    "use strict";

    var form = document.getElementById("sellerTypeForm");
    var reference = new URLSearchParams(window.location.search).get("ref") || "";
    var saveButton = document.getElementById("saveButton");

    function apiData(response) {
        if (!response || typeof response !== "object") return {};
        return response.data && typeof response.data === "object" ? response.data : response;
    }

    function hasAction(actions, actionId) {
        return (actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    async function load() {
        saveButton.disabled = true;
        try {
            if (reference) {
                var response = await App.api("api/seller-types.php?ref=" + encodeURIComponent(reference));
                var data = apiData(response);
                var row = data.seller_type || {};

                form.ref.value = row.ref || reference;
                form.seller_type_name.value = row.seller_type_name || "";
                form.sort_order.value = String(Number(row.sort_order || 0));
                form.status.value = String(Number(row.status) === 0 ? 0 : 1);

                document.getElementById("pageHeading").textContent = "Edit Seller Type";
                saveButton.innerHTML = '<i data-lucide="save"></i> Update Seller Type';

                var usage = Number(row.product_price_count || 0);
                if (usage > 0) {
                    var info = document.getElementById("usageInfo");
                    info.style.display = "block";
                    info.textContent = "Used in " + usage + " Product Sale Pricing row" + (usage === 1 ? "" : "s") + ". Renaming will automatically update the compatibility name snapshot.";
                }

                saveButton.disabled = !hasAction(data.allowed_actions || [], 3);
            } else {
                var response = await App.api("api/seller-types.php?options=1");
                var data = apiData(response);
                saveButton.disabled = !hasAction(data.allowed_actions || [], 2);
            }

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            saveButton.disabled = true;
            App.showError(error, "Unable to load Seller Type form.");
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        if (!Validation.validateForm(form)) return;

        var name = String(form.seller_type_name.value || "").trim().replace(/\s+/g, " ");
        if (!name) {
            Validation.applyErrors(form, { seller_type_name: "Seller Type Name is required." });
            return;
        }
        if (Number(form.sort_order.value || 0) < 0) {
            Validation.applyErrors(form, { sort_order: "Sort Order must be zero or greater." });
            return;
        }

        var payload = new FormData(form);
        if (reference) payload.set("_method", "PUT");

        saveButton.disabled = true;
        try {
            var response = await App.api("api/seller-types.php", {
                method: "POST",
                body: payload
            });
            showToast((response && response.message) || "Seller Type saved successfully.", { type: "success", duration: 2 });
            window.setTimeout(function () {
                window.location.href = "seller-type-list.php";
            }, 700);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save Seller Type.");
            saveButton.disabled = false;
        }
    });

    load();
})(window, document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
