
<?php   require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier Form';
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)   $styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
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

<div class="page-head">
    <h1 id="pageHeading">Add Supplier</h1>
    <a class="btn gray" href="supplier-list.php">Supplier List</a>
</div>

<div class="card form-card">
    <form id="supplierForm" novalidate>
        <input type="hidden" name="ref" id="supplierRef">

        <div class="card-header">
            <div>
                <h2>Supplier Details</h2>
                <p>Supplier master information for the logged-in tenant branch.</p>
            </div>
        </div>

        <div class="card-body">

            <div class="card-section-title">Basic Details</div>

            <!-- Row 1: 3 fields -->
            <div class="form-row">
                <div class="field col-4">
                    <label for="supplierCode">Supplier code</label>
                    <input id="supplierCode" name="supplier_code" type="text" maxlength="50" readonly
                           aria-readonly="true" placeholder="Auto generated">
                    <div class="muted">Auto generated branch-wise (example: SUP0001).</div>
                </div>

                <div class="field col-4">
                    <label for="supplierName" class="required">Supplier name</label>
                    <input id="supplierName" name="supplier_name" type="text" maxlength="150" required
                           placeholder="Enter supplier name"
                           data-required-message="Supplier name is required.">
                </div>

                <div class="field col-4">
                    <label for="contactPerson">Contact person</label>
                    <input id="contactPerson" name="contact_person" type="text" maxlength="150"
                           placeholder="Enter contact person">
                </div>
            </div>

            <!-- Row 2: 3 fields -->
            <div class="form-row">
                <div class="field col-4">
                    <label for="supplierMobile" class="required">Mobile</label>
                    <input id="supplierMobile" name="mobile" type="text" inputmode="numeric" maxlength="10" required
                           placeholder="Enter 10-digit mobile number"
                           data-required-message="Mobile number is required."
                           data-validation="mobile"
                           data-mobile-message="Enter a valid 10-digit supplier mobile number.">
                </div>

                <div class="field col-4">
                    <label for="supplierEmail">Email</label>
                    <input id="supplierEmail" name="email" type="email" maxlength="190"
                           placeholder="Enter supplier email"
                           data-validation="email"
                           data-email-message="Enter a valid supplier email address.">
                </div>

                <div class="field col-4">
                    <label for="supplierStatus" class="required">Status</label>
                    <select id="supplierStatus" name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="card-section-title">Tax Details</div>

            <!-- Row 3: 4 fields -->
            <div class="form-row">
                <div class="field col-3">
                    <label for="supplierGstin">GSTIN</label>
                    <input id="supplierGstin" name="gstin" type="text" maxlength="15"
                           placeholder="32DSGFD3453GGZG" autocomplete="off"
                           data-validation="gst"
                           data-gst-message="Enter a valid 15-character GSTIN.">
                </div>

                <div class="field col-3">
                    <label for="supplierPan">PAN</label>
                    <input id="supplierPan" name="pan" type="text" maxlength="10"
                           placeholder="ABCDE1234F" autocomplete="off"
                           data-validation="pan"
                           data-pan-message="PAN format must be ABCDE1234F.">
                </div>

                <div class="field col-3">
                    <label for="stateCode">State code</label>
                    <input id="stateCode" name="state_code" type="text"
                           inputmode="numeric" maxlength="2"
                           placeholder="29"
                           data-validation="integer"
                           data-regex="^[0-9]{1,2}$"
                           data-regex-message="Enter a valid state code.">
                </div>

                <div class="field col-3">
                    <label for="openingBalance">Opening balance</label>
                    <input id="openingBalance" name="opening_balance" type="text" inputmode="decimal"
                           placeholder="0.00"
                           data-validation="decimal" data-decimal-places="2"
                           data-decimal-message="Enter a valid opening balance.">
                </div>
            </div>

            <div class="card-section-title">Address</div>

            <!-- Row 4: full width -->
            <div class="form-row">
                <div class="field col-12">
                    <label for="supplierAddress">Address</label>
                    <textarea id="supplierAddress" name="address" rows="4"
                              placeholder="Enter supplier address"></textarea>
                </div>
            </div>

        </div>

        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">Save Supplier</button>
                <a class="btn gray" href="supplier-list.php">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";

    var form = document.getElementById("supplierForm");
    var reference = new URLSearchParams(location.search).get("ref") || "";
    var saveButton = document.getElementById("saveButton");
    var isLoading = false;

    function hasAction(actions, actionId) {
        return (actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function normalizeTaxFields() {
        form.gstin.value = (form.gstin.value || "").trim().toUpperCase().replace(/\s+/g, "");
        form.pan.value = (form.pan.value || "").trim().toUpperCase().replace(/\s+/g, "");

        if (form.state_code.value !== "") {
            var stateNumber = parseInt(form.state_code.value, 10);
            if (!isNaN(stateNumber)) form.state_code.value = String(stateNumber);
        }

        if (window.Validation && typeof Validation.formatField === "function") {
            Validation.formatField(form.gstin);
            Validation.formatField(form.pan);
        }
    }

    function validateSupplierExtras() {
        normalizeTaxFields();

        var errors = {};
        var stateCode = String(form.state_code.value || "").trim();
        var openingBalance = String(form.opening_balance.value || "").trim();

        if (stateCode !== "") {
            var stateNumber = Number(stateCode);
            if (!Number.isInteger(stateNumber) || stateNumber < 1 || stateNumber > 99) {
                errors.state_code = "Enter a valid state code between 1 and 99.";
            }
        }

        if (openingBalance !== "" && (!isFinite(Number(openingBalance)) || Number(openingBalance) < 0)) {
            errors.opening_balance = "Opening balance must be zero or greater.";
        }

        if (Object.keys(errors).length) {
            Validation.applyErrors(form, errors);
            return false;
        }

        return true;
    }

    function fillSupplier(supplier) {
        document.getElementById("supplierRef").value = supplier.ref || "";
        form.supplier_code.value = supplier.supplier_code || "";
        form.supplier_name.value = supplier.supplier_name || "";
        form.contact_person.value = supplier.contact_person || "";
        form.mobile.value = supplier.mobile || "";
        form.email.value = supplier.email || "";
        form.gstin.value = supplier.gstin || "";
        form.pan.value = supplier.pan || "";
        form.state_code.value = supplier.state_code || "";
        form.opening_balance.value = supplier.opening_balance === null || supplier.opening_balance === undefined ? "" : String(Number(supplier.opening_balance));
        form.address.value = supplier.address || "";
        form.status.value = String(Number(supplier.status) === 1 ? 1 : 0);

        normalizeTaxFields();
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;

        try {
            if (reference) {
                document.getElementById("pageHeading").textContent = "Edit Supplier";
                saveButton.textContent = "Update Supplier";

                var result = await App.api("api/suppliers.php?ref=" + encodeURIComponent(reference));
                fillSupplier(result.data.supplier);

                if (!hasAction(result.data.allowed_actions, 3)) {
                    saveButton.disabled = true;
                }
            } else {
                var accessResult = await App.api("api/suppliers.php?options=1");
                form.supplier_code.value = accessResult.data.next_supplier_code || "";

                if (!hasAction(accessResult.data.allowed_actions, 2)) {
                    saveButton.disabled = true;
                }
            }
        } catch (error) {
            saveButton.disabled = true;
            App.showError(error, "Unable to load supplier form.");
        } finally {
            isLoading = false;
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        Validation.clearForm(form);
        normalizeTaxFields();

        if (!Validation.validateForm(form)) return;
        if (!validateSupplierExtras()) return;

        var data = new FormData(form);
        if (reference) data.set("_method", "PUT");

        saveButton.disabled = true;

        try {
            var result = await App.api("api/suppliers.php", {
                method: "POST",
                body: data
            });

            showToast(result.message, { type:"success", duration:2 });

            setTimeout(function () {
                location.href = "supplier-list.php";
            }, 700);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save supplier.");
            saveButton.disabled = false;
        }
    });

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