<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Payment';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
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
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1 id="pageHeading">Customer Payment</h1>
        <p>Opening Balance, Overall FIFO and Particular Invoice settlement.</p>
    </div>
    <a class="btn gray" href="customer-payment-list.php"><i data-lucide="list"></i>Payment List</a>
</div>

<form id="paymentForm" novalidate>
    <input type="hidden" id="paymentRef" name="ref">

    <div class="card form-card">
        <div class="card-header">
            <div>
                <h2>Customer Payment Information</h2>
                <p>Actual payment modes, Customer Credit and settlement discount are handled separately.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="card-section-title">Payment Information</div>
            <div class="form-row">
                <div class="field col-3">
                    <label for="paymentNumber">Payment No</label>
                    <input id="paymentNumber" type="text" value="Auto generated" readonly>
                </div>
                <div class="field col-3">
                    <label for="paymentDate" class="required">Payment Date</label>
                    <input id="paymentDate" type="date" required>
                </div>
                <div class="field col-3">
                    <label for="customerRef" class="required">Customer</label>
                    <select id="customerRef" required><option value="">Select Customer</option></select>
                </div>
                <div class="field col-3">
                    <label for="paymentTypeRef" class="required">Payment For</label>
                    <select id="paymentTypeRef" required><option value="">Select Payment Type</option></select>
                </div>
            </div>

            <div class="form-row" id="invoiceField" hidden>
                <div class="field col-6">
                    <label for="invoiceRef" class="required">Particular Final Invoice</label>
                    <select id="invoiceRef"><option value="">Select Invoice</option></select>
                </div>
            </div>

            <div class="card-section-title">Customer Outstanding</div>
            <div class="form-row">
                <div class="field col-3">
                    <label>Invoice Outstanding</label>
                    <input id="invoiceOutstanding" type="text" placeholder="0.00" readonly>
                </div>
                <div class="field col-3">
                    <label>Opening Balance Pending</label>
                    <input id="openingOutstanding" type="text" placeholder="0.00" readonly>
                </div>
                <div class="field col-3">
                    <label>Available Customer Credit</label>
                    <input id="availableCredit" type="text" placeholder="0.00" readonly>
                </div>
                <div class="field col-3">
                    <label>Overall Outstanding</label>
                    <input id="overallOutstanding" type="text" placeholder="0.00" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="field col-8">
                    <div class="card-section-title">Payment Details</div>
                    <div class="card table-card app-allocation-wrap">
                        <table class="data-table app-allocation-table">
                            <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Account</th>
                                <th>Amount</th>
                                <th>Reference No</th>
                                <th>Cheque No</th>
                                <th>Cheque Date</th>
                            </tr>
                            </thead>
                            <tbody id="paymentRows">
                            <tr><td colspan="6" class="muted">Loading payment methods...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="app-total-line">
                        <span>Actual Payment</span>
                        <strong id="actualPaymentDisplay">₹0.00</strong>
                    </div>

                    <div class="card-section-title">Customer Credit & Settlement Discount</div>
                    <div class="form-row">
                        <div class="field col-6">
                            <label for="creditApplied">Apply Customer Credit</label>
                            <input id="creditApplied" type="text" inputmode="decimal" placeholder="0.00">
                        </div>
                        <div class="field col-6">
                            <label>Customer Credit After</label>
                            <input id="creditAfter" type="text" placeholder="0.00" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field col-4">
                            <label for="discountType">Settlement Discount Type</label>
                            <select id="discountType">
                                <option value="">Select Discount Type</option>
                                <option value="1">Percentage</option>
                                <option value="2">Fixed</option>
                            </select>
                        </div>
                        <div class="field col-4">
                            <label for="discountValue">Discount Value</label>
                            <input id="discountValue" type="text" inputmode="decimal" placeholder="0.00">
                        </div>
                        <div class="field col-4">
                            <label>Discount Amount</label>
                            <input id="discountAmount" type="text" placeholder="0.00" readonly>
                        </div>
                    </div>

                    <div class="card-section-title">Remarks</div>
                    <div class="form-row">
                        <div class="field col-12">
                            <label for="notes">Notes</label>
                            <textarea id="notes" rows="3" maxlength="255" placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                </div>

                <div class="field col-4">
                    <div class="app-side-card">
                        <div class="app-side-card-head">Selected Settlement</div>
                        <div class="app-side-card-body">
                            <div class="app-summary-row">
                                <span>Payment For</span>
                                <strong id="selectedPaymentFor">—</strong>
                            </div>
                            <div class="app-summary-row">
                                <span>Invoice / Target</span>
                                <strong id="selectedInvoice">—</strong>
                            </div>
                            <div class="app-summary-row total">
                                <span>Target Outstanding</span>
                                <strong id="targetOutstandingDisplay">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="card-section-title">Settlement Summary</div>
                    <div class="app-side-card">
                        <div class="app-side-card-body">
                            <div class="app-summary-row">
                                <span>Actual Payment</span>
                                <strong id="summaryActualPayment">₹0.00</strong>
                            </div>
                            <div class="app-summary-row">
                                <span>Credit Applied</span>
                                <strong id="summaryCreditApplied">₹0.00</strong>
                            </div>
                            <div class="app-summary-row">
                                <span>Discount</span>
                                <strong id="summaryDiscount">₹0.00</strong>
                            </div>
                            <div class="app-summary-row total">
                                <span>Total Settlement</span>
                                <strong id="settledAmountDisplay">₹0.00</strong>
                            </div>
                            <div class="app-summary-row">
                                <span>Balance After</span>
                                <strong id="remainingOutstandingDisplay">₹0.00</strong>
                            </div>
                            <div class="app-summary-row">
                                <span>Excess → Customer Credit</span>
                                <strong id="excessCreditDisplay">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <input id="actualPayment" type="hidden" value="0.00">
                    <input id="targetOutstanding" type="hidden" value="0.00">
                    <input id="settledAmount" type="hidden" value="0.00">
                    <input id="remainingOutstanding" type="hidden" value="0.00">
                    <input id="excessCredit" type="hidden" value="0.00">

                    <div class="card-section-title">Allocation Preview</div>
                    <div class="card table-card">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Target</th>
                                <th>Applied</th>
                                <th>Balance</th>
                            </tr>
                            </thead>
                            <tbody id="allocationBody">
                            <tr><td colspan="3" class="muted">Select Customer and Payment Type.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <a class="btn gray" href="customer-payment-list.php">Cancel</a>
                <button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="hand-coins"></i>Post Payment</button>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    "use strict";

    var form = document.getElementById("paymentForm");
    var params = new URLSearchParams(location.search);
    var editRef = params.get("ref") || "";
    var viewMode = params.get("view") === "1";
    var linkCustomer = params.get("customer") || "";
    var linkInvoice = params.get("invoice") || "";
    var accounts = [];
    var paymentMethods = [];
    var currentContext = null;
    var previewTimer = null;
    var previewSerial = 0;
    var contextSerial = 0;
    var loading = false;
    var customerGlobalSelect = null;
    var paymentTypeGlobalSelect = null;
    var invoiceGlobalSelect = null;
    var discountTypeGlobalSelect = null;
    var linkInvoiceId = 0;

    function el(id) {
        return document.getElementById(id);
    }

    function initGlobalSelects() {
        if (!window.GlobalSelect || typeof window.GlobalSelect.init !== "function") return;
        customerGlobalSelect = window.GlobalSelect.init(el("customerRef"), { placeholder: "Select Customer" });
        paymentTypeGlobalSelect = window.GlobalSelect.init(el("paymentTypeRef"), { placeholder: "Select Payment Type" });
        invoiceGlobalSelect = window.GlobalSelect.init(el("invoiceRef"), { placeholder: "Select Invoice" });
        discountTypeGlobalSelect = window.GlobalSelect.init(el("discountType"), { placeholder: "Select Discount Type" });
    }

    function refreshGlobalSelect(instance) {
        if (instance && typeof instance.refresh === "function") instance.refresh();
    }

    function refreshMainGlobalSelects() {
        refreshGlobalSelect(customerGlobalSelect);
        refreshGlobalSelect(paymentTypeGlobalSelect);
        refreshGlobalSelect(invoiceGlobalSelect);
        refreshGlobalSelect(discountTypeGlobalSelect);
    }

    function selectCustomerById(customerId) {
        var option = Array.prototype.find.call(el("customerRef").options, function (row) {
            return Number(row.dataset.customerId || 0) === Number(customerId || 0);
        });
        el("customerRef").value = option ? option.value : "";
        refreshGlobalSelect(customerGlobalSelect);
    }

    function selectPaymentTypeByType(paymentType) {
        var option = Array.prototype.find.call(el("paymentTypeRef").options, function (row) {
            return Number(row.dataset.type || 0) === Number(paymentType || 0);
        });
        el("paymentTypeRef").value = option ? option.value : "";
        refreshGlobalSelect(paymentTypeGlobalSelect);
    }

    function money(value) {
        var number = Number(value || 0);
        if (!Number.isFinite(number)) number = 0;
        return number.toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function numberValue(value) {
        var number = Number(String(value == null ? "" : value).replace(/,/g, "").trim() || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function has(actions, id) {
        return (actions || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function selectedPaymentType() {
        var option = el("paymentTypeRef").selectedOptions[0];
        return option ? Number(option.dataset.type || 0) : 0;
    }

    function accountOptions(requiredType, selectedId) {
        var html = '<option value="">Select Account</option>';
        accounts.forEach(function (account) {
            if (Number(account.account_type) !== Number(requiredType)) return;
            var selected = String(account.id) === String(selectedId || "") ? " selected" : "";
            html += '<option value="' + esc(account.id) + '"' + selected + '>' +
                esc(account.account_code + " - " + account.account_name) + '</option>';
        });
        return html;
    }

    function paymentRow(method, existing) {
        existing = existing || {};
        var needsReference = Number(method.requires_reference) === 1;
        var needsCheque = Number(method.requires_cheque_details) === 1;
        var amount = Number(existing.amount || 0) > 0 ? String(Number(existing.amount)) : "";
        var reference = existing.payment_reference || "";
        var chequeNo = existing.cheque_no || "";
        var chequeDate = existing.cheque_date || "";

        return '<tr class="payment-row" data-method-id="' + esc(method.id) + '">' +
            '<td><span class="app-allocation-mode">' + esc(method.method_name) + '</span></td>' +
            '<td><select class="pay-account">' + accountOptions(method.account_type, existing.account_id) + '</select></td>' +
            '<td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00" value="' + esc(amount) + '"></td>' +
            '<td><input class="pay-reference" type="text" maxlength="100" placeholder="' + (needsReference ? 'Required' : '—') + '" value="' + esc(reference) + '"' + (needsReference ? '' : ' disabled') + '></td>' +
            '<td><input class="pay-cheque-no" type="text" maxlength="100" placeholder="' + (needsCheque ? 'Cheque No' : '—') + '" value="' + esc(chequeNo) + '"' + (needsCheque ? '' : ' disabled') + '></td>' +
            '<td><input class="pay-cheque-date" type="date" value="' + esc(chequeDate) + '"' + (needsCheque ? '' : ' disabled') + '></td>' +
            '</tr>';
    }

    function renderPaymentRows(existingRows) {
        var existingMap = {};
        (existingRows || []).forEach(function (row) {
            existingMap[String(row.payment_method_id)] = row;
        });

        if (!paymentMethods.length) {
            el("paymentRows").innerHTML = '<tr><td colspan="6" class="muted">No active Payment Methods found.</td></tr>';
            updateActualPayment();
            return;
        }

        el("paymentRows").innerHTML = paymentMethods.map(function (method) {
            return paymentRow(method, existingMap[String(method.id)] || null);
        }).join("");

        if (window.GlobalSelect && typeof window.GlobalSelect.init === "function") {
            Array.prototype.forEach.call(el("paymentRows").querySelectorAll(".pay-account"), function (select) {
                window.GlobalSelect.init(select, { placeholder: "Select Account" });
            });
        }
        updateActualPayment();
    }

    function collectPayments() {
        var rows = [];
        Array.prototype.forEach.call(el("paymentRows").querySelectorAll(".payment-row"), function (row) {
            rows.push({
                payment_method_id: Number(row.dataset.methodId || 0),
                account_id: row.querySelector(".pay-account").value,
                amount: row.querySelector(".pay-amount").value.trim(),
                payment_reference: row.querySelector(".pay-reference").disabled ? "" : row.querySelector(".pay-reference").value.trim(),
                cheque_no: row.querySelector(".pay-cheque-no").disabled ? "" : row.querySelector(".pay-cheque-no").value.trim(),
                cheque_date: row.querySelector(".pay-cheque-date").disabled ? "" : row.querySelector(".pay-cheque-date").value
            });
        });
        return rows;
    }

    function updateActualPayment() {
        var total = 0;
        Array.prototype.forEach.call(el("paymentRows").querySelectorAll(".pay-amount"), function (input) {
            var number = numberValue(input.value);
            if (number > 0) total += number;
        });
        el("actualPayment").value = money(total);
        el("actualPaymentDisplay").textContent = "₹" + money(total);
        el("summaryActualPayment").textContent = "₹" + money(total);
        return total;
    }


    function selectedTargetTotal() {
        if (!currentContext) return 0;

        var type = selectedPaymentType();
        if (type === 1) {
            return Math.max(0, numberValue(currentContext.opening_outstanding));
        }
        if (type === 2) {
            return Math.max(0, numberValue(currentContext.overall_outstanding));
        }
        if (type === 3) {
            var invoiceRef = el("invoiceRef").value;
            var invoice = (currentContext.invoices || []).find(function (row) {
                return String(row.sale_ref || "") === String(invoiceRef || "");
            });
            return invoice ? Math.max(0, numberValue(invoice.pending)) : 0;
        }
        return 0;
    }

    function liveDiscountAmount(targetTotal) {
        var type = Number(el("discountType").value || 0);
        var value = Math.max(0, numberValue(el("discountValue").value));

        if (value <= 0) return 0;
        if (type === 1) {
            return Math.round((targetTotal * Math.min(value, 100) / 100) * 100) / 100;
        }
        if (type === 2) {
            return Math.round(value * 100) / 100;
        }
        return 0;
    }

    function updateLiveSummary() {
        var actualPayment = updateActualPayment();
        var targetTotal = selectedTargetTotal();
        var availableCredit = Math.max(0, numberValue(currentContext && currentContext.available_credit));
        var enteredCredit = Math.max(0, numberValue(el("creditApplied").value));
        var rawDiscount = liveDiscountAmount(targetTotal);

        var paymentApplied = Math.min(actualPayment, targetTotal);
        var remainingAfterPayment = Math.max(0, targetTotal - paymentApplied);
        var creditApplied = Math.min(enteredCredit, availableCredit, remainingAfterPayment);
        var remainingAfterCredit = Math.max(0, remainingAfterPayment - creditApplied);
        var discountApplied = Math.min(rawDiscount, remainingAfterCredit);
        var settled = paymentApplied + creditApplied + discountApplied;
        var remaining = Math.max(0, targetTotal - settled);
        var excessCredit = Math.max(0, actualPayment - targetTotal);
        var creditAfter = Math.max(0, availableCredit - creditApplied + excessCredit);

        el("targetOutstanding").value = money(targetTotal);
        el("targetOutstandingDisplay").textContent = "₹" + money(targetTotal);

        el("discountAmount").value = money(rawDiscount);
        el("creditAfter").value = money(creditAfter);

        el("summaryCreditApplied").textContent = "₹" + money(creditApplied);
        el("summaryDiscount").textContent = "₹" + money(discountApplied);
        el("settledAmount").value = money(settled);
        el("settledAmountDisplay").textContent = "₹" + money(settled);
        el("remainingOutstanding").value = money(remaining);
        el("remainingOutstandingDisplay").textContent = "₹" + money(remaining);
        el("excessCredit").value = money(excessCredit);
        el("excessCreditDisplay").textContent = "₹" + money(excessCredit);
    }

    function payload(action) {
        return {
            action: action || "preview",
            ref: editRef || "",
            customer_ref: el("customerRef").value,
            payment_type_ref: el("paymentTypeRef").value,
            invoice_ref: el("invoiceRef").value,
            payment_date: el("paymentDate").value,
            payments: collectPayments(),
            credit_applied: el("creditApplied").value.trim(),
            discount_type: el("discountType").value,
            discount_value: el("discountValue").value.trim(),
            notes: el("notes").value.trim()
        };
    }

    function updatePaymentTypeAvailability(ctx) {
        var opening = Number(ctx && ctx.opening_outstanding || 0);
        var overall = Number(ctx && ctx.overall_outstanding || 0);
        var invoiceCount = Number(ctx && ctx.invoices ? ctx.invoices.length : 0);

        Array.prototype.forEach.call(el("paymentTypeRef").options, function (option) {
            var type = Number(option.dataset.type || 0);
            if (!type) return;

            if (type === 1) {
                option.disabled = opening <= 0.001;
                option.textContent = option.textContent.replace(/ \(No Pending\)$/i, "") + (option.disabled ? " (No Pending)" : "");
            } else if (type === 2) {
                option.disabled = overall <= 0.001;
                option.textContent = option.textContent.replace(/ \(No Pending\)$/i, "") + (option.disabled ? " (No Pending)" : "");
            } else if (type === 3) {
                option.disabled = invoiceCount < 1;
                option.textContent = option.textContent.replace(/ \(No Pending\)$/i, "") + (option.disabled ? " (No Pending)" : "");
            }
        });

        var selected = el("paymentTypeRef").selectedOptions[0];
        if (selected && selected.value && selected.disabled) {
            var preferred = Array.prototype.find.call(el("paymentTypeRef").options, function (option) {
                return Number(option.dataset.type || 0) === 2 && !option.disabled;
            }) || Array.prototype.find.call(el("paymentTypeRef").options, function (option) {
                return Number(option.dataset.type || 0) === 3 && !option.disabled;
            }) || Array.prototype.find.call(el("paymentTypeRef").options, function (option) {
                return Number(option.dataset.type || 0) === 1 && !option.disabled;
            });

            el("paymentTypeRef").value = preferred ? preferred.value : "";
        }
        refreshGlobalSelect(paymentTypeGlobalSelect);
    }

    function pendingMessageForSelectedType() {
        if (!currentContext) return "";
        var type = selectedPaymentType();
        if (type === 1 && Number(currentContext.opening_outstanding || 0) <= 0.001) {
            return "This Customer has no pending Opening Balance.";
        }
        if (type === 2 && Number(currentContext.overall_outstanding || 0) <= 0.001) {
            return "This Customer has no pending Final Invoice or Opening Balance.";
        }
        if (type === 3 && !(currentContext.invoices || []).length) {
            return "This Customer has no pending Final Invoice.";
        }
        return "";
    }

    function setContext(ctx) {
        currentContext = ctx || null;
        el("invoiceOutstanding").value = ctx ? money(ctx.invoice_outstanding) : "";
        el("openingOutstanding").value = ctx ? money(ctx.opening_outstanding) : "";
        el("overallOutstanding").value = ctx ? money(ctx.overall_outstanding) : "";
        el("availableCredit").value = ctx ? money(ctx.available_credit) : "";

        var oldInvoice = el("invoiceRef").value;
        el("invoiceRef").innerHTML = '<option value="">Select Invoice</option>';
        (ctx && ctx.invoices || []).forEach(function (row) {
            var option = document.createElement("option");
            option.value = row.sale_ref;
            option.dataset.saleId = String(row.sale_id || 0);
            option.textContent = row.sales_no + " | " + row.invoice_date + " | Pending ₹" + money(row.pending);
            el("invoiceRef").appendChild(option);
        });

        var invoiceById = linkInvoiceId > 0 ? Array.prototype.find.call(el("invoiceRef").options, function (option) {
            return Number(option.dataset.saleId || 0) === Number(linkInvoiceId);
        }) : null;

        if (invoiceById) {
            el("invoiceRef").value = invoiceById.value;
            linkInvoiceId = 0;
            linkInvoice = "";
        } else if (linkInvoice && Array.prototype.some.call(el("invoiceRef").options, function (option) {
            return option.value === linkInvoice;
        })) {
            el("invoiceRef").value = linkInvoice;
            linkInvoice = "";
        } else if (Array.prototype.some.call(el("invoiceRef").options, function (option) {
            return option.value === oldInvoice;
        })) {
            el("invoiceRef").value = oldInvoice;
        }
        refreshGlobalSelect(invoiceGlobalSelect);

        updatePaymentTypeAvailability(ctx);
        updateTypeUi();
        updateLiveSummary();
        schedulePreview();
    }

    async function loadContext() {
        var customerRef = el("customerRef").value;
        var requestId = ++contextSerial;
        if (!customerRef) {
            setContext(null);
            return;
        }

        try {
            var url = "api/customer-payments.php?customer_context=1&customer_ref=" + encodeURIComponent(customerRef);
            if (editRef) url += "&payment_ref=" + encodeURIComponent(editRef);
            var result = await App.api(url);
            if (requestId !== contextSerial) return;
            setContext(result.data);
        } catch (error) {
            if (requestId !== contextSerial) return;
            setContext(null);
            App.showError(error, "Unable to load Customer outstanding.");
        }
    }

    function updateSelectionSummary() {
        var typeOption = el("paymentTypeRef").selectedOptions[0];
        var invoiceOption = el("invoiceRef").selectedOptions[0];
        el("selectedPaymentFor").textContent = typeOption && typeOption.value ? typeOption.textContent : "—";
        el("selectedInvoice").textContent = selectedPaymentType() === 3 && invoiceOption && invoiceOption.value
            ? invoiceOption.textContent.split(" | ")[0]
            : (selectedPaymentType() === 1 ? "Opening Balance" : (selectedPaymentType() === 2 ? "FIFO Allocation" : "—"));
    }

    function updateTypeUi() {
        var type = selectedPaymentType();
        el("invoiceField").hidden = type !== 3;
        if (type !== 3) {
            el("invoiceRef").value = "";
            refreshGlobalSelect(invoiceGlobalSelect);
        }
        updateSelectionSummary();
        updateLiveSummary();
    }

    function clearAllocationPreview(message) {
        el("allocationBody").innerHTML =
            '<tr><td colspan="3" class="muted">' +
            esc(message || "Enter settlement values.") +
            '</td></tr>';
    }

    function renderPreview(data) {
        el("actualPayment").value = money(data.actual_payment);
        el("actualPaymentDisplay").textContent = "₹" + money(data.actual_payment);
        el("summaryActualPayment").textContent = "₹" + money(data.actual_payment);
        el("discountAmount").value = money(data.discount_amount);
        el("creditAfter").value = money(data.available_credit_after);
        el("targetOutstanding").value = money(data.targets_total);
        el("settledAmount").value = money(data.settled_amount);
        el("remainingOutstanding").value = money(data.remaining_outstanding);
        el("excessCredit").value = money(data.excess_credit);
        el("targetOutstandingDisplay").textContent = "₹" + money(data.targets_total);
        el("summaryCreditApplied").textContent = "₹" + money(data.credit_applied);
        el("summaryDiscount").textContent = "₹" + money(data.discount_amount);
        el("settledAmountDisplay").textContent = "₹" + money(data.settled_amount);
        el("remainingOutstandingDisplay").textContent = "₹" + money(data.remaining_outstanding);
        el("excessCreditDisplay").textContent = "₹" + money(data.excess_credit);

        var rows = data.allocations || [];
        if (!rows.length) {
            clearAllocationPreview("No amount allocated yet.");
            return;
        }

        el("allocationBody").innerHTML = rows.map(function (row) {
            var applied = Number(row.payment_amount || 0) + Number(row.credit_amount || 0) + Number(row.discount_amount || 0);
            return '<tr>' +
                '<td>' + esc(row.label) + '</td>' +
                '<td>₹' + money(applied) + '</td>' +
                '<td>₹' + money(row.pending_after) + '</td>' +
                '</tr>';
        }).join("");
    }

    async function preview() {
        var requestId = ++previewSerial;
        updateLiveSummary();

        if (!el("customerRef").value || !el("paymentTypeRef").value) {
            clearAllocationPreview("Select Customer and Payment Type.");
            return;
        }
        var pendingMessage = pendingMessageForSelectedType();
        if (pendingMessage) {
            clearAllocationPreview(pendingMessage);
            return;
        }
        if (selectedPaymentType() === 3 && !el("invoiceRef").value) {
            clearAllocationPreview("Select Particular Invoice.");
            return;
        }

        try {
            var result = await App.api("api/customer-payments.php", {
                method: "POST",
                body: payload("preview")
            });
            if (requestId !== previewSerial) return;
            renderPreview(result.data);
        } catch (error) {
            if (requestId !== previewSerial) return;
            clearAllocationPreview(error && error.message ? error.message : "Check settlement values.");
        }
    }

    function schedulePreview() {
        updateLiveSummary();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(preview, 280);
    }

    function fillTypeOptions(types) {
        el("paymentTypeRef").innerHTML = '<option value="">Select Payment Type</option>';
        types.forEach(function (type) {
            var option = document.createElement("option");
            option.value = type.value;
            option.textContent = type.label;
            option.dataset.type = String(type.type || 0);
            el("paymentTypeRef").appendChild(option);
        });
        refreshGlobalSelect(paymentTypeGlobalSelect);
    }

    function fillCustomers(rows) {
        el("customerRef").innerHTML = '<option value="">Select Customer</option>';
        rows.forEach(function (customer) {
            var option = document.createElement("option");
            option.value = customer.ref;
            option.dataset.customerId = String(customer.id || 0);
            option.textContent = customer.customer_code + " - " + customer.customer_name + (customer.mobile ? " | " + customer.mobile : "");
            el("customerRef").appendChild(option);
        });
        refreshGlobalSelect(customerGlobalSelect);
    }


    function applyViewMode() {
        if (!viewMode) return;

        el("pageHeading").textContent = "View Customer Payment";
        el("saveButton").hidden = true;

        Array.prototype.forEach.call(
            form.querySelectorAll("input,select,textarea"),
            function (control) {
                if (control.type !== "hidden") {
                    control.disabled = true;
                }
            }
        );
        refreshMainGlobalSelects();
        Array.prototype.forEach.call(el("paymentRows").querySelectorAll(".pay-account"), function (select) {
            if (select._globalSelect && typeof select._globalSelect.refresh === "function") select._globalSelect.refresh();
        });
    }

    async function load() {
        if (loading) return;
        loading = true;

        try {
            if (editRef) {
                var result = await App.api("api/customer-payments.php?ref=" + encodeURIComponent(editRef));
                var optionsResult = await App.api("api/customer-payments.php?options=1");
                var payment = result.data.payment;

                accounts = result.data.accounts || [];
                paymentMethods = result.data.payment_methods || [];
                fillTypeOptions(result.data.payment_types || []);
                fillCustomers(optionsResult.data.customers || []);
                renderPaymentRows(payment.payments || []);

                el("pageHeading").textContent = viewMode ? "View Customer Payment" : "Edit Customer Payment";
                el("paymentNumber").value = payment.payment_no || "";
                if (!viewMode) {
                    el("saveButton").innerHTML = '<i data-lucide="save"></i>Update Payment';
                }
                selectCustomerById(payment.customer_id);
                selectPaymentTypeByType(payment.payment_type);
                el("paymentDate").value = payment.payment_date || "";
                el("creditApplied").value = Number(payment.credit_applied || 0) > 0 ? String(Number(payment.credit_applied)) : "";
                el("discountType").value = payment.discount_type ? String(payment.discount_type) : "";
                el("discountValue").value = Number(payment.discount_value || 0) > 0 ? String(Number(payment.discount_value)) : "";
                el("notes").value = payment.notes || "";
                linkInvoiceId = Number(payment.invoice_id || 0);
                linkInvoice = payment.invoice_ref || "";

                setContext(result.data.customer_context);
                if (!viewMode && !has(result.data.allowed_actions, 3)) el("saveButton").disabled = true;
            } else {
                var url = "api/customer-payments.php?options=1" + (linkCustomer ? "&customer_ref=" + encodeURIComponent(linkCustomer) : "");
                var options = await App.api(url);

                accounts = options.data.accounts || [];
                paymentMethods = options.data.payment_methods || [];
                fillTypeOptions(options.data.payment_types || []);
                fillCustomers(options.data.customers || []);
                renderPaymentRows([]);
                el("paymentNumber").value = "Auto generated on save";
                el("paymentDate").value = options.data.today || new Date().toISOString().slice(0, 10);

                if (linkCustomer) {
                    el("customerRef").value = linkCustomer;
                    refreshGlobalSelect(customerGlobalSelect);
                }

                var typeWanted = linkInvoice ? 3 : 2;
                var typeOption = Array.prototype.find.call(el("paymentTypeRef").options, function (option) {
                    return Number(option.dataset.type) === typeWanted;
                });
                if (typeOption) {
                    el("paymentTypeRef").value = typeOption.value;
                    refreshGlobalSelect(paymentTypeGlobalSelect);
                }

                if (options.data.customer_context) {
                    setContext(options.data.customer_context);
                } else if (linkCustomer) {
                    await loadContext();
                }

                if (!has(options.data.allowed_actions, 29)) el("saveButton").disabled = true;
            }

            updateTypeUi();
            refreshMainGlobalSelects();
            updateLiveSummary();
            clearTimeout(previewTimer);
            if (viewMode) {
                await preview();
            } else {
                schedulePreview();
            }
            applyViewMode();
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            el("saveButton").disabled = true;
            App.showError(error, "Unable to load Customer Payment form.");
        } finally {
            loading = false;
        }
    }

    initGlobalSelects();

    el("customerRef").addEventListener("change", loadContext);
    el("paymentTypeRef").addEventListener("change", function () {
        updateTypeUi();
        schedulePreview();
    });
    el("invoiceRef").addEventListener("change", function () {
        updateSelectionSummary();
        updateLiveSummary();
        schedulePreview();
    });
    el("creditApplied").addEventListener("input", schedulePreview);
    el("creditApplied").addEventListener("change", schedulePreview);
    el("discountValue").addEventListener("input", schedulePreview);
    el("discountValue").addEventListener("change", schedulePreview);
    el("discountType").addEventListener("change", schedulePreview);

    el("paymentRows").addEventListener("input", function (event) {
        if (event.target.matches(".pay-amount,.pay-reference,.pay-cheque-no,.pay-cheque-date")) {
            schedulePreview();
        }
    });
    el("paymentRows").addEventListener("change", function (event) {
        if (event.target.matches(".pay-account,.pay-amount,.pay-reference,.pay-cheque-no,.pay-cheque-date")) {
            schedulePreview();
        }
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (viewMode || el("saveButton").disabled) return;

        var pendingMessage = pendingMessageForSelectedType();
        if (pendingMessage) {
            App.showError(null, pendingMessage);
            return;
        }
        if (selectedPaymentType() === 3 && !el("invoiceRef").value) {
            App.showError(null, "Select a pending Particular Invoice.");
            return;
        }

        el("saveButton").disabled = true;
        try {
            var result = await App.api("api/customer-payments.php", {
                method: "POST",
                body: payload("save")
            });
            showToast(result.message || "Customer Payment saved successfully.", { type: "success", duration: 2 });
            location.href = "customer-payment-list.php";
        } catch (error) {
            App.showError(error, "Unable to save Customer Payment.");
            el("saveButton").disabled = false;
        }
    });

    load();
})();
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
