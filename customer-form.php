<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Form';
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

<div class="page-head">
    <h1 id="pageHeading">Add Customer</h1>
    <a class="btn gray" href="customer-list.php">Customer List</a>
</div>

<div class="card form-card">
    <form id="customerForm" novalidate>
        <input type="hidden" name="ref" id="customerRef">

        <div class="card-header">
            <div>
                <h2>Customer Details</h2>
                <p>Customer master information for the logged-in tenant branch.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="card-section-title">Basic Details</div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="customerCode" class="required">Customer code</label>
                    <input id="customerCode" name="customer_code" type="text" maxlength="50" readonly
                           aria-readonly="true" placeholder="Auto generated">
                    <div class="muted">Auto generated branch-wise (example: CUS0001).</div>
                </div>

                <div class="field col-4">
                    <label for="customerName" class="required">Customer name</label>
                    <input id="customerName" name="customer_name" type="text" maxlength="150" required
                           placeholder="Enter customer name"
                           data-required-message="Customer name is required.">
                </div>

                <div class="field col-4">
                    <label for="customerMobile">Mobile</label>
                    <input id="customerMobile" name="mobile" type="text" inputmode="numeric" maxlength="10"
                           placeholder="Enter 10-digit mobile number"
                           data-validation="mobile"
                           data-mobile-message="Enter a valid 10-digit customer mobile number.">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-6">
                    <label for="customerEmail">Email</label>
                    <input id="customerEmail" name="email" type="text" maxlength="190"
                           placeholder="Enter customer email"
                           data-validation="email"
                           data-email-message="Enter a valid customer email address.">
                </div>

                <div class="field col-6">
                    <label for="customerStatus" class="required">Status</label>
                    <select id="customerStatus" name="status" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="card-section-title">Tax & Financial Details</div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="customerGstin">GSTIN</label>
                    <input id="customerGstin" name="gstin" type="text" maxlength="15"
                           placeholder="32DSGFD3453GGZG" autocomplete="off"
                           data-validation="gst"
                           data-gst-message="Enter a valid 15-character GSTIN.">
                </div>

                <div class="field col-4">
                    <label for="customerPan">PAN</label>
                    <input id="customerPan" name="pan" type="text" maxlength="10"
                           placeholder="ABCDE1234F" autocomplete="off"
                           data-validation="pan"
                           data-pan-message="PAN format must be ABCDE1234F.">
                </div>

                <div class="field col-4">
                    <label for="stateCode">State code</label>
                    <input id="stateCode" name="state_code" type="text"
                           inputmode="numeric" maxlength="2"
                           placeholder="29"
                           data-validation="integer"
                           data-regex="^[0-9]{1,2}$"
                           data-regex-message="Enter a valid state code.">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="creditLimit">Credit limit</label>
                    <input id="creditLimit" name="credit_limit" type="text" inputmode="decimal"
                           placeholder="0.00"
                           data-validation="decimal" data-decimal-places="2"
                           data-decimal-message="Enter a valid credit limit.">
                </div>

                <div class="field col-4">
                    <label for="openingBalance">Opening balance</label>
                    <input id="openingBalance" name="opening_balance" type="text" inputmode="decimal"
                           placeholder="0.00"
                           data-validation="decimal" data-decimal-places="2"
                           data-decimal-message="Enter a valid opening balance.">
                </div>

                <div class="field col-4">
                    <label for="openingBalanceDate">Opening balance date</label>
                    <input id="openingBalanceDate" name="opening_balance_date" type="date">
                    <div class="muted">Required only when Opening Balance is greater than zero.</div>
                </div>
            </div>

            <div class="card-section-title">Address</div>

            <div class="form-row">
                <div class="field col-12">
                    <label for="customerAddress">Address</label>
                    <textarea id="customerAddress" name="address" rows="4"
                              placeholder="Enter customer address"></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">Save Customer</button>
                <a class="btn gray" href="customer-list.php">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";

    var form = document.getElementById("customerForm");
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

    function validateCustomerExtras() {
        normalizeTaxFields();

        var errors = {};
        var stateCode = String(form.state_code.value || "").trim();
        var creditLimit = String(form.credit_limit.value || "").trim();
        var openingBalance = String(form.opening_balance.value || "").trim();
        var openingBalanceDate = String(form.opening_balance_date.value || "").trim();

        if (stateCode !== "") {
            var stateNumber = Number(stateCode);
            if (!Number.isInteger(stateNumber) || stateNumber < 1 || stateNumber > 99) {
                errors.state_code = "Enter a valid state code between 1 and 99.";
            }
        }

        if (creditLimit !== "" && (!isFinite(Number(creditLimit)) || Number(creditLimit) < 0)) {
            errors.credit_limit = "Credit limit must be zero or greater.";
        }

        if (openingBalance !== "" && (!isFinite(Number(openingBalance)) || Number(openingBalance) < 0)) {
            errors.opening_balance = "Opening balance must be zero or greater.";
        }
        if (openingBalance !== "" && isFinite(Number(openingBalance)) && Number(openingBalance) > 0 && openingBalanceDate === "") {
            errors.opening_balance_date = "Opening balance date is required when Opening Balance is greater than zero.";
        }

        if (Object.keys(errors).length) {
            Validation.applyErrors(form, errors);
            return false;
        }

        return true;
    }

    function fillCustomer(customer) {
        document.getElementById("customerRef").value = customer.ref || "";
        form.customer_code.value = customer.customer_code || "";
        form.customer_name.value = customer.customer_name || "";
        form.mobile.value = customer.mobile || "";
        form.email.value = customer.email || "";
        form.gstin.value = customer.gstin || "";
        form.pan.value = customer.pan || "";
        form.state_code.value = customer.state_code || "";
        form.credit_limit.value = customer.credit_limit === null || customer.credit_limit === undefined
            ? ""
            : String(Number(customer.credit_limit));
        form.opening_balance.value = customer.opening_balance === null || customer.opening_balance === undefined
            ? ""
            : String(Number(customer.opening_balance));
        form.opening_balance_date.value = customer.opening_balance_date || "";
        form.address.value = customer.address || "";
        form.status.value = String(Number(customer.status) === 1 ? 1 : 0);

        normalizeTaxFields();
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;

        try {
            if (reference) {
                document.getElementById("pageHeading").textContent = "Edit Customer";
                saveButton.textContent = "Update Customer";

                var result = await App.api("api/customers.php?ref=" + encodeURIComponent(reference));
                fillCustomer(result.data.customer);

                if (!hasAction(result.data.allowed_actions, 3)) {
                    saveButton.disabled = true;
                }
            } else {
                var accessResult = await App.api("api/customers.php?options=1");
                form.customer_code.value = accessResult.data.next_customer_code || "";

                if (!hasAction(accessResult.data.allowed_actions, 2)) {
                    saveButton.disabled = true;
                }
            }
        } catch (error) {
            saveButton.disabled = true;
            App.showError(error, "Unable to load customer form.");
        } finally {
            isLoading = false;
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        Validation.clearForm(form);
        normalizeTaxFields();

        if (!Validation.validateForm(form)) return;
        if (!validateCustomerExtras()) return;

        var data = new FormData(form);
        if (reference) data.set("_method", "PUT");

        saveButton.disabled = true;

        try {
            var result = await App.api("api/customers.php", {
                method: "POST",
                body: data
            });

            showToast(result.message, { type:"success", duration:2 });

            setTimeout(function () {
                location.href = "customer-list.php";
            }, 700);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save customer.");
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
