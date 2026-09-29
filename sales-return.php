<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Sales Return';
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
                    <h1 id="pageHeading">Sales Return</h1>
                    <p id="pageSubheading">Return Primary + Secondary quantities together against the original Final Invoice and Purchase Batch.</p>
                </div>
                <a class="btn" href="sales-return-list.php">
                    <i data-lucide="list"></i>Sales Return List
                </a>
            </div>

            <form id="salesReturnForm" novalidate>
                <div class="card form-card form-section">
                    <div class="card-header">
                        <div><h2 class="section-heading"><i data-lucide="rotate-ccw"></i>Return Information</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="field col-3">
                                <label for="returnNo">Return No</label>
                                <input id="returnNo" type="text" placeholder="Auto on Post" readonly>
                            </div>
                            <div class="field col-3">
                                <label for="returnDate" class="required">Return Date</label>
                                <input id="returnDate" type="date" required>
                                <div class="field-error">Return Date is required.</div>
                            </div>
                            <div class="field col-3">
                                <label for="customerSelect" class="required">Customer</label>
                                <select id="customerSelect" data-global-select data-placeholder="Select Customer" required>
                                    <option value="">Select Customer</option>
                                </select>
                                <div class="field-error">Select Customer.</div>
                            </div>
                            <div class="field col-3">
                                <label for="saleRef" class="required">Final Invoice</label>
                                <select id="saleRef" data-global-select data-placeholder="Select Final Invoice" required>
                                    <option value="">Select Final Invoice</option>
                                </select>
                                <div class="field-error">Select Final Invoice.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card form-section">
                    <div class="card-header">
                        <div><h2 class="section-heading"><i data-lucide="file-text"></i>Invoice Information</h2></div>
                    </div>
                    <div class="card-body">
                        <div class="app-split-grid">
                            <div class="app-side-card">
                                <div class="app-side-card-body">
                                    <div class="app-summary-row"><span>Invoice Date</span><strong id="invoiceDate">-</strong></div>
                                    <div class="app-summary-row"><span>Original Invoice</span><strong id="invoiceTotal">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Previous Returns</span><strong id="previousReturns">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Current Net Invoice</span><strong id="currentNet">₹0.00</strong></div>
                                </div>
                            </div>
                            <div class="app-side-card">
                                <div class="app-side-card-body">
                                    <div class="app-summary-row"><span>Paid / Credit</span><strong id="paidSettled">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Settlement Discount</span><strong id="settlementDiscount">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Current Balance</span><strong id="currentBalance">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Available Customer Credit</span><strong id="customerCreditBefore">₹0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card table-card form-section">
                    <div class="card-header">
                        <div>
                            <h2 class="section-heading"><i data-lucide="package-check"></i>Return Items</h2>
                            <p id="itemsNote">Select a Customer and Final Invoice to load sold items.</p>
                        </div>
                    </div>
                    <div class="app-table-wrap">
                        <table class="app-editable-table" id="returnItemsTable">
                            <thead>
                            <tr>
                                <th>#</th><th>Product</th><th>Batch</th>
                                <th class="dt-body-right">Sold Qty</th>
                                <th class="dt-body-right">Returned Qty</th>
                                <th class="dt-body-right">Available Return</th>
                                <th>Primary Return Qty</th>
                                <th>Secondary Return Qty</th>
                                <th>Reason for Return</th>
                                <th>Add to Stock?</th>
                                <th class="dt-body-right">Primary Rate</th>
                                <th class="dt-body-right">Return Amount</th>
                            </tr>
                            </thead>
                            <tbody id="returnItemsBody">
                                <tr><td colspan="12" class="empty">Select a Final Invoice.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="app-split-grid form-section">
                    <div class="card form-card">
                        <div class="card-header">
                            <div>
                                <h2 class="section-heading"><i data-lucide="calculator"></i>Return Calculation</h2>
                                <p>Calculated live from the Primary + Secondary Return Qty entered above.</p>
                            </div>
                        </div>
                        <div class="app-table-wrap">
                            <table class="app-editable-table">
                                <thead>
                                <tr>
                                    <th>Calculation</th>
                                    <th class="dt-body-right">Amount</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr><td>Return Subtotal</td><td class="dt-body-right"><strong id="sumGross">₹0.00</strong></td></tr>
                                <tr><td>Item Discount</td><td class="dt-body-right"><strong id="sumItemDiscount">₹0.00</strong></td></tr>
                                <tr><td>Overall Discount</td><td class="dt-body-right"><strong id="sumOverallDiscount">₹0.00</strong></td></tr>
                                <tr><td>Taxable Amount</td><td class="dt-body-right"><strong id="sumTaxable">₹0.00</strong></td></tr>
                                <tr><td>CGST</td><td class="dt-body-right"><strong id="sumCgst">₹0.00</strong></td></tr>
                                <tr><td>SGST</td><td class="dt-body-right"><strong id="sumSgst">₹0.00</strong></td></tr>
                                <tr><td>IGST</td><td class="dt-body-right"><strong id="sumIgst">₹0.00</strong></td></tr>
                                <tr><td>Cess</td><td class="dt-body-right"><strong id="sumCess">₹0.00</strong></td></tr>
                                <tr><td>Round Off</td><td class="dt-body-right"><strong id="sumRoundOff">₹0.00</strong></td></tr>
                                <tr><td><strong>Return Total</strong></td><td class="dt-body-right"><strong id="sumGrandTotal">₹0.00</strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card form-card">
                        <div class="card-header">
                            <div>
                                <h2 class="section-heading"><i data-lucide="wallet-cards"></i>Settlement Summary</h2>
                                <p id="settlementMessage">Return settlement updates live.</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="field">
                                <label for="settlementType" class="required">Settlement Method</label>
                                <select id="settlementType" data-global-select data-placeholder="Select Settlement" required>
                                    <option value="">Select Settlement</option>
                                    <option value="1">Customer Credit / Outstanding Adjustment</option>
                                    <option value="2">Refund</option>
                                    <option value="3">Credit + Refund</option>
                                </select>
                                <div class="field-error">Select Settlement Method.</div>
                            </div>
                        </div>
                        <div class="app-table-wrap">
                            <table class="app-editable-table">
                                <thead>
                                <tr>
                                    <th>Settlement</th>
                                    <th class="dt-body-right">Amount</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr><td>Return Total</td><td class="dt-body-right"><strong id="settReturnTotal">₹0.00</strong></td></tr>
                                <tr><td>Outstanding Before</td><td class="dt-body-right"><strong id="settOutstandingBefore">₹0.00</strong></td></tr>
                                <tr><td>Outstanding Adjusted</td><td class="dt-body-right"><strong id="settOutstandingAdjusted">₹0.00</strong></td></tr>
                                <tr><td>New Invoice Net</td><td class="dt-body-right"><strong id="settNewNet">₹0.00</strong></td></tr>
                                <tr><td>Released Actual Payment</td><td class="dt-body-right"><strong id="settReleasedPayment">₹0.00</strong></td></tr>
                                <tr><td>Restored Old Credit</td><td class="dt-body-right"><strong id="settReleasedCredit">₹0.00</strong></td></tr>
                                <tr><td>Released Discount</td><td class="dt-body-right"><strong id="settReleasedDiscount">₹0.00</strong></td></tr>
                                <tr><td>Maximum Refund</td><td class="dt-body-right"><strong id="settMaxRefund">₹0.00</strong></td></tr>
                                <tr><td>Refund Entered</td><td class="dt-body-right"><strong id="settRefund">₹0.00</strong></td></tr>
                                <tr><td><strong>Stored in Customer Credit</strong></td><td class="dt-body-right"><strong id="settCreditGenerated">₹0.00</strong></td></tr>
                                <tr><td>Customer Credit After</td><td class="dt-body-right"><strong id="settCreditAfter">₹0.00</strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card table-card form-section" id="refundDetailsSection" hidden>
                    <div class="card-header">
                        <div>
                            <h2 class="section-heading"><i data-lucide="wallet-cards"></i>Refund Details</h2>
                            <p id="refundHelpText">Enter the refund methods actually paid back to the customer.</p>
                        </div>
                        <div class="field">
                            <label for="refundDate">Refund Date</label>
                            <input id="refundDate" type="date">
                        </div>
                    </div>
                    <div class="app-table-wrap">
                        <table class="app-editable-table" id="refundTable">
                            <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Account</th>
                                <th class="dt-body-right">Amount</th>
                                <th>Reference No</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr class="refund-method-row" data-method="CASH">
                                <td><strong>Cash</strong></td>
                                <td><select class="refund-account" data-method-code="CASH" data-global-select data-placeholder="Select Cash Account"><option value="">Select Account</option></select></td>
                                <td><input class="refund-amount" data-method-code="CASH" type="text" inputmode="decimal" placeholder="0.00"></td>
                                <td><span class="muted">—</span></td>
                                <td><span class="muted">—</span></td>
                            </tr>
                            <tr class="refund-method-row" data-method="UPI">
                                <td><strong>UPI</strong></td>
                                <td><select class="refund-account" data-method-code="UPI" data-global-select data-placeholder="Select Bank Account"><option value="">Select Account</option></select></td>
                                <td><input class="refund-amount" data-method-code="UPI" type="text" inputmode="decimal" placeholder="0.00"></td>
                                <td><input class="refund-reference" data-method-code="UPI" type="text" maxlength="100" placeholder="UPI Reference No"></td>
                                <td><span class="muted">—</span></td>
                            </tr>
                            <tr class="refund-method-row" data-method="BANK">
                                <td><strong>Bank</strong></td>
                                <td><select class="refund-account" data-method-code="BANK" data-global-select data-placeholder="Select Bank Account"><option value="">Select Account</option></select></td>
                                <td><input class="refund-amount" data-method-code="BANK" type="text" inputmode="decimal" placeholder="0.00"></td>
                                <td><input class="refund-reference" data-method-code="BANK" type="text" maxlength="100" placeholder="Bank Reference No"></td>
                                <td><span class="muted">—</span></td>
                            </tr>
                            <tr class="refund-method-row" data-method="CHEQUE">
                                <td><strong>Cheque</strong></td>
                                <td><select class="refund-account" data-method-code="CHEQUE" data-global-select data-placeholder="Select Bank Account"><option value="">Select Account</option></select></td>
                                <td><input class="refund-amount" data-method-code="CHEQUE" type="text" inputmode="decimal" placeholder="0.00"></td>
                                <td><input class="refund-cheque-no" type="text" maxlength="100" placeholder="Cheque No"></td>
                                <td><input class="refund-cheque-date" type="date" aria-label="Cheque Date"></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body">
                        <div class="app-summary-row"><span>Maximum Refund</span><strong id="refundMaximumLive">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Refund Entered</span><strong id="refundEnteredLive">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Refund Remaining</span><strong id="refundRemainingLive">₹0.00</strong></div>
                        <div class="app-summary-row total"><span>Stored in Customer Credit</span><strong id="refundCreditStoredLive">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Customer Credit After Return</span><strong id="refundCreditAfterLive">₹0.00</strong></div>
                    </div>
                </div>

                <div class="card form-card form-section">
                    <div class="card-header"><div><h2 class="section-heading"><i data-lucide="message-square-text"></i>Remarks</h2></div></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="field col-12">
                                <label for="notes">Remarks</label>
                                <textarea id="notes" rows="3" maxlength="2000" placeholder="Optional overall return remarks"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a class="btn" href="sales-return-list.php">Cancel</a>
                        <button class="btn btn-primary" id="saveButton" type="submit" hidden>
                            <i data-lucide="check-circle-2"></i><span id="saveButtonText">Post Sales Return</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>
        <?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>
(function (window, document) {
    "use strict";

    var ACTION_VIEW = 1;
    var ACTION_UPDATE = 3;
    var ACTION_RETURN = 32;

    var qs = new URLSearchParams(window.location.search);
    var returnRef = qs.get("ref") || "";
    var mode = (qs.get("mode") || "").toLowerCase();
    var isEdit = returnRef !== "" && mode === "edit";
    var isView = returnRef !== "" && !isEdit;

    var state = {
        actions: [],
        customers: [],
        invoices: [],
        accounts: [],
        methods: {},
        invoice: null,
        saved: null,
        loading: false,
        globals: {},
        calculation: null,
        settlementRefundAvailable: null
    };

    function byId(id) { return document.getElementById(id); }
    function numberValue(value) {
        var n = Number(String(value == null ? "" : value).replace(/,/g, ""));
        return Number.isFinite(n) ? n : 0;
    }
    function money(value) {
        var n = numberValue(value);
        return "₹" + n.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function qty(value) {
        var n = numberValue(value);
        return n.toLocaleString("en-IN", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }
    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    function today() {
        var d = new Date();
        return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    }
    function hasAction(id) {
        return (state.actions || []).map(Number).indexOf(Number(id)) !== -1;
    }
    function toast(message, type) {
        if (window.showToast) showToast(message, { type: type || "success", duration: 3 });
    }
    function reportError(error, fallback) {
        if (window.App && typeof App.showError === "function") App.showError(error, fallback);
        else toast((error && error.message) || fallback, "error");
    }

    function initGlobal(select, options) {
        if (!select || !window.GlobalSelect) return null;
        var instance = select._globalSelect || GlobalSelect.init(select, options || {});
        return instance;
    }
    function setGlobalOptions(select, items, selectedValue) {
        if (!select) return;
        var instance = select._globalSelect || initGlobal(select);
        var rows = (items || []).map(function (item) {
            return { value: String(item.value), text: String(item.text), disabled: Boolean(item.disabled) };
        });
        if (instance && typeof instance.setOptions === "function") {
            instance.setOptions(rows, selectedValue == null ? "" : String(selectedValue));
        } else {
            select.innerHTML = '<option value="">Select</option>';
            rows.forEach(function (row) {
                var option = document.createElement("option");
                option.value = row.value;
                option.textContent = row.text;
                option.disabled = row.disabled;
                select.appendChild(option);
            });
            select.value = selectedValue == null ? "" : String(selectedValue);
        }
    }
    function refreshGlobal(select) {
        if (select && select._globalSelect && typeof select._globalSelect.refresh === "function") {
            select._globalSelect.refresh();
        }
    }

    var SETTLEMENT_OPTIONS = [
        { value: "1", text: "Customer Credit / Outstanding Adjustment" },
        { value: "2", text: "Refund" },
        { value: "3", text: "Credit + Refund" }
    ];

    /*
     * Keep the native <select> and GlobalSelect UI on the same value.
     * Using setOptions(..., selectedValue) is important here because a plain
     * select.value + refresh can leave the visible custom control stale.
     */
    function setSettlementValue(value, refundAvailable) {
        if (!settlementType) return;

        var desired = value == null ? "" : String(value);
        var allowRefund = refundAvailable !== false;
        var rows = SETTLEMENT_OPTIONS.map(function (row) {
            return {
                value: row.value,
                text: row.text,
                disabled: !allowRefund && (row.value === "2" || row.value === "3")
            };
        });

        var instance = settlementType._globalSelect || initGlobal(settlementType);
        if (instance && typeof instance.setOptions === "function") {
            instance.setOptions(rows, desired);
        } else {
            Array.prototype.forEach.call(settlementType.options, function (option) {
                if (option.value === "2" || option.value === "3") {
                    option.disabled = !allowRefund;
                }
            });
            settlementType.value = desired;
            refreshGlobal(settlementType);
        }

        /* setOptions is authoritative for the custom UI; force the native value
           too so calculations/submission always read the same selection. */
        settlementType.value = desired;
        refreshGlobal(settlementType);

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                if (settlementType.value !== desired) settlementType.value = desired;
                refreshGlobal(settlementType);
            });
        }
    }

    function syncSettlementAvailability(maxRefund, force) {
        if (!settlementType || isView) return false;

        var available = numberValue(maxRefund) > 0.001;
        var current = String(settlementType.value || "");
        var next = current;
        var changed = false;

        /* A Refund/Split selection is no longer valid once no actual payment is
           released. Move it to the adjustment mode and visibly sync GlobalSelect. */
        if (!available && (current === "2" || current === "3")) {
            clearRefundValuesOnly();
            next = "1";
            changed = true;
        }

        if (force || changed || state.settlementRefundAvailable !== available) {
            setSettlementValue(next, available);
            state.settlementRefundAvailable = available;
        }

        return changed;
    }
    function setDisabled(select, disabled) {
        if (!select) return;
        select.disabled = Boolean(disabled);
        refreshGlobal(select);
    }

    var form = byId("salesReturnForm");
    var returnNo = byId("returnNo");
    var returnDate = byId("returnDate");
    var customerSelect = byId("customerSelect");
    var saleRef = byId("saleRef");
    var body = byId("returnItemsBody");
    var settlementType = byId("settlementType");
    var refundDate = byId("refundDate");
    var refundDetailsSection = byId("refundDetailsSection");
    var notes = byId("notes");
    var saveButton = byId("saveButton");
    var saveButtonText = byId("saveButtonText");

    function accountText(row) {
        var text = row.account_code ? row.account_code + " - " : "";
        text += row.account_name || "";
        if (row.bank_name) text += " | " + row.bank_name;
        return text;
    }
    function customerText(row) {
        var text = row.customer_code ? row.customer_code + " - " : "";
        text += row.customer_name || "";
        if (row.mobile) text += " | " + row.mobile;
        return text;
    }
    function invoiceText(row) {
        var customer = row.customer_code ? row.customer_code + " - " + row.customer_name : row.customer_name;
        return row.sales_no + " | " + row.invoice_date + " | " + customer + " | " + money(row.grand_total);
    }

    function populateCustomers(selectedId) {
        var rows = state.customers.map(function (row) {
            return { value: Number(row.id || 0) > 0 ? String(row.id) : "walkin", text: customerText(row) };
        });
        var selected = Number(selectedId || 0) > 0 ? String(selectedId) : (selectedId === 0 || selectedId === "0" ? "walkin" : "");
        setGlobalOptions(customerSelect, rows, selected);
    }

    function filteredInvoices() {
        var rawCustomer = String(customerSelect.value || "");
        var customerId = rawCustomer === "walkin" ? 0 : Number(rawCustomer || 0);
        var hasCustomerFilter = rawCustomer !== "";
        return state.invoices.filter(function (row) {
            if (hasCustomerFilter && Number(row.customer_id || 0) !== customerId) return false;
            if (isEdit && state.saved && Number(row.id) === Number(state.saved.sale_id)) return true;
            return Boolean(row.returnable);
        });
    }

    function populateInvoices(selectedSaleId) {
        var rows = filteredInvoices();
        var selectedRef = "";
        rows.forEach(function (row) {
            if (Number(row.id) === Number(selectedSaleId || 0)) selectedRef = row.ref;
        });
        setGlobalOptions(saleRef, rows.map(function (row) {
            return { value: row.ref, text: invoiceText(row) };
        }), selectedRef);
    }

    function populateRefundAccounts() {
        document.querySelectorAll(".refund-account").forEach(function (select) {
            var code = String(select.getAttribute("data-method-code") || "").toUpperCase();
            var method = state.methods[code] || null;
            var requiredType = method ? Number(method.account_type || 0) : (code === "CASH" ? 1 : 2);
            var current = select.value;
            var rows = state.accounts.filter(function (a) {
                return !requiredType || Number(a.account_type) === requiredType;
            }).map(function (a) {
                return { value: a.id, text: accountText(a) };
            });
            setGlobalOptions(select, rows, current);
        });
    }

    function setText(id, value) {
        var el = byId(id);
        if (el) el.textContent = value;
    }

    function resetInvoice() {
        state.settlementRefundAvailable = null;
        state.invoice = null;
        body.innerHTML = '<tr><td colspan="12" class="empty">Select a Final Invoice.</td></tr>';
        setText("invoiceDate", "-");
        ["invoiceTotal","previousReturns","currentNet","paidSettled","settlementDiscount","currentBalance","customerCreditBefore"]
            .forEach(function (id) { setText(id, money(0)); });
        byId("itemsNote").textContent = "Select a Customer and Final Invoice to load sold items.";
        updateLiveCalculation();
    }

    function itemReasonOptions(selectedValue) {
        var options = ["Damaged","Expired","Wrong Item","Customer Return","Quality Issue","Excess Supply","Other"];
        var selected = String(selectedValue || "");
        if (selected && options.indexOf(selected) === -1) options.push(selected);
        var html = '<option value="">Select Reason</option>';
        options.forEach(function (value) {
            html += '<option value="' + escapeHtml(value) + '"' + (value === selected ? ' selected' : '') + '>' +
                escapeHtml(value) + '</option>';
        });
        return html;
    }

    function stockChoiceOptions(selectedValue) {
        var selected = selectedValue === 0 || selectedValue === "0" ? "0" :
            (selectedValue === 1 || selectedValue === "1" ? "1" : "");
        return '<option value="">Select</option>' +
            '<option value="1"' + (selected === "1" ? ' selected' : '') + '>Yes - Add to Stock</option>' +
            '<option value="0"' + (selected === "0" ? ' selected' : '') + '>No - Do Not Add</option>';
    }

    function initReturnItemSelects() {
        body.querySelectorAll("select.return-reason,select.add-to-stock").forEach(function (select) {
            initGlobal(select);
            if (isView) setDisabled(select, true);
        });
    }

    function itemUnitName(item, type) {
        if (type === "primary") return item.primary_unit_symbol || item.primary_unit_name || "Primary";
        return item.secondary_unit_symbol || item.secondary_unit_name || "Secondary";
    }

    function mixedQtyText(primary, secondary, item) {
        var text = qty(primary) + " " + itemUnitName(item, "primary");
        if (Number(item.secondary_unit_id || 0) > 0) text += " + " + qty(secondary) + " " + itemUnitName(item, "secondary");
        return text;
    }

    function rowReturnQuantities(tr) {
        var p = tr ? tr.querySelector(".return-primary-qty") : null;
        var s = tr ? tr.querySelector(".return-secondary-qty") : null;
        return {
            primary: Math.max(0, numberValue(p ? p.value : 0)),
            secondary: Math.max(0, numberValue(s && !s.disabled ? s.value : 0))
        };
    }

    function renderInvoice(invoice, savedItems) {
        state.invoice = invoice;
        setText("invoiceDate", invoice.invoice_date || "-");
        setText("invoiceTotal", money(invoice.original_total));
        setText("previousReturns", money(invoice.previous_return_total));
        setText("currentNet", money(invoice.current_net_total));
        setText("paidSettled", money(numberValue(invoice.settlement_payment) + numberValue(invoice.settlement_credit)));
        setText("settlementDiscount", money(invoice.settlement_discount));
        setText("currentBalance", money(invoice.balance_amount));
        setText("customerCreditBefore", money(invoice.customer_credit_balance));

        var savedMap = {};
        (savedItems || []).forEach(function (row) { savedMap[Number(row.sale_item_id)] = row; });

        var rows = "";
        (invoice.items || []).forEach(function (item, index) {
            var savedRow = savedMap[Number(item.sale_item_id)] || {};
            var savedPrimary = numberValue(savedRow.primary_quantity);
            var hasSecondary = Number(item.secondary_unit_id || 0) > 0;
            var savedSecondary = hasSecondary ? numberValue(savedRow.secondary_quantity) : 0;
            var savedReason = String(savedRow.return_reason || "");
            var savedAddToStock = savedRow.add_to_stock;
            var availableBase = numberValue(item.returnable_base_quantity);
            var savedBase = (savedPrimary * numberValue(item.conversion_rate || 1)) + savedSecondary;
            var disabled = availableBase <= 0.0004 && savedBase <= 0;
            rows += '<tr data-index="' + index + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td><strong>' + escapeHtml(item.product_name) + '</strong><br><small>' + escapeHtml(item.product_code || "") + '</small></td>' +
                '<td>' + escapeHtml(item.batch_number || item.purchase_no || "-") + '</td>' +
                '<td class="dt-body-right">' + escapeHtml(mixedQtyText(item.primary_quantity, item.secondary_quantity, item)) + '</td>' +
                '<td class="dt-body-right">' + escapeHtml(mixedQtyText(item.returned_primary_quantity, item.returned_secondary_quantity, item)) + '</td>' +
                '<td class="dt-body-right">' + escapeHtml(mixedQtyText(item.returnable_primary_quantity, item.returnable_secondary_quantity, item)) + '</td>' +
                '<td><input class="return-primary-qty" type="text" inputmode="decimal" autocomplete="off" data-index="' + index + '" value="' + (savedPrimary > 0 ? escapeHtml(String(savedPrimary)) : "") + '" ' + (disabled || isView ? 'readonly' : '') + ' placeholder="' + escapeHtml(itemUnitName(item,"primary")) + '"></td>' +
                '<td><input class="return-secondary-qty" type="text" inputmode="decimal" autocomplete="off" data-index="' + index + '" value="' + (savedSecondary > 0 ? escapeHtml(String(savedSecondary)) : "") + '" ' + (!hasSecondary ? 'disabled' : ((disabled || isView) ? 'readonly' : '')) + ' placeholder="' + escapeHtml(itemUnitName(item,"secondary")) + '"></td>' +
                '<td><select class="return-reason" data-global-select data-placeholder="Select Reason"' + (disabled ? ' disabled' : '') + '>' + itemReasonOptions(savedReason) + '</select></td>' +
                '<td><select class="add-to-stock" data-global-select data-placeholder="Add to Stock?"' + (disabled ? ' disabled' : '') + '>' + stockChoiceOptions(savedAddToStock) + '</select></td>' +
                '<td class="dt-body-right">' + escapeHtml(money(item.unit_price)) + '</td>' +
                '<td class="dt-body-right line-return-amount">₹0.00</td>' +
                '</tr>';
        });
        body.innerHTML = rows || '<tr><td colspan="12" class="empty">No returnable Invoice items.</td></tr>';
        byId("itemsNote").textContent = "Enter Primary and/or Secondary Return Qty. For every returned product, select the reason and whether it should be added back to the original Purchase Batch.";

        initReturnItemSelects();
        body.querySelectorAll(".return-primary-qty,.return-secondary-qty").forEach(function (input) {
            input.addEventListener("input", function () { validateReturnRow(input.closest("tr"), false); updateLiveCalculation(); });
            input.addEventListener("change", function () { validateReturnRow(input.closest("tr"), true); updateLiveCalculation(); });
        });
        body.querySelectorAll(".return-reason,.add-to-stock").forEach(function (select) { select.addEventListener("change", updateLiveCalculation); });
        updateLiveCalculation();
    }

    function validateReturnRow(tr, showMessage) {
        if (!tr) return true;
        var index = Number(tr.getAttribute("data-index"));
        var item = state.invoice && state.invoice.items ? state.invoice.items[index] : null;
        if (!item) return true;
        var pInput = tr.querySelector(".return-primary-qty");
        var sInput = tr.querySelector(".return-secondary-qty");
        [pInput,sInput].forEach(function(input){ if(input) input.classList.remove("is-invalid"); });
        var pRaw = pInput ? pInput.value.trim() : "";
        var sRaw = sInput ? sInput.value.trim() : "";
        var p = pRaw === "" ? 0 : Number(pRaw);
        var sec = sRaw === "" ? 0 : Number(sRaw);
        var message = "";
        if (!Number.isFinite(p) || p < 0 || !Number.isFinite(sec) || sec < 0) message = "Enter valid Return quantities.";
        var conversion = numberValue(item.conversion_rate) || 1;
        var returnBase = (Math.max(0,p) * conversion) + Math.max(0,sec);
        if (!message && returnBase > numberValue(item.returnable_base_quantity) + 0.0004) message = "Return Qty exceeds Available Return Qty.";
        if (message) {
            [pInput,sInput].forEach(function(input){ if(input && !input.readOnly) input.classList.add("is-invalid"); });
            if (showMessage) toast(message,"warning");
            return false;
        }
        return true;
    }

    function readReturnRows(showMessage) {
        var rows = [];
        var invalid = false;
        var firstMessage = "";
        body.querySelectorAll("tr[data-index]").forEach(function (tr) {
            if (!validateReturnRow(tr, false)) {
                invalid = true;
                if (!firstMessage) firstMessage = "Correct the Return Qty values.";
            }
            var values = rowReturnQuantities(tr);
            if (values.primary <= 0 && values.secondary <= 0) return;
            var reasonSelect = tr.querySelector(".return-reason");
            var stockSelect = tr.querySelector(".add-to-stock");
            var returnReason = reasonSelect ? String(reasonSelect.value || "").trim() : "";
            var stockValue = stockSelect ? String(stockSelect.value || "") : "";
            if (!returnReason) { invalid = true; if (!firstMessage) firstMessage = "Select Reason for Return for every returned product."; }
            if (stockValue !== "0" && stockValue !== "1") { invalid = true; if (!firstMessage) firstMessage = "Select Add to Stock Yes/No for every returned product."; }
            var item = state.invoice.items[Number(tr.getAttribute("data-index"))];
            rows.push({
                sale_item_ref:item.sale_item_ref,
                return_primary_quantity:values.primary,
                return_secondary_quantity:values.secondary,
                return_reason:returnReason,
                add_to_stock:stockValue === "1" ? 1 : 0
            });
        });
        if (showMessage && invalid && firstMessage) toast(firstMessage,"warning");
        return { rows:rows, invalid:invalid, message:firstMessage };
    }

    function calculateReturn() {
        var invoice = state.invoice;
        var totals = {
            subtotal:0,item_discount_total:0,overall_discount_total:0,taxable_total:0,
            cgst_total:0,sgst_total:0,igst_total:0,cess_total:0,before_round_total:0,round_off:0,grand_total:0
        };
        if (!invoice) return totals;

        var returnBaseMap = {};
        body.querySelectorAll("tr[data-index]").forEach(function (tr) {
            var index = Number(tr.getAttribute("data-index"));
            var item = invoice.items[index];
            var values = rowReturnQuantities(tr);
            var conversion = numberValue(item.conversion_rate) || 1;
            var returnBase = (values.primary * conversion) + values.secondary;
            var maxBase = numberValue(item.returnable_base_quantity);
            if (returnBase > maxBase) returnBase = maxBase;
            returnBaseMap[Number(item.sale_item_id)] = returnBase;
            var ratio = Math.min(1, returnBase / Math.max(0.000001, numberValue(item.base_quantity)));
            var primaryRate = numberValue(item.unit_price);
            var secondaryRate = Number(item.secondary_unit_id || 0) > 0 && conversion > 0
                ? (primaryRate / conversion)
                : 0;
            var originalGross = Math.round(((numberValue(item.primary_quantity) * primaryRate) + (numberValue(item.secondary_quantity) * secondaryRate)) * 100) / 100;
            if (originalGross <= 0 && numberValue(item.quantity) > 0) {
                originalGross = Math.round(numberValue(item.quantity) * numberValue(item.unit_price) * 100) / 100;
            }
            var gross = Math.round((originalGross * ratio) * 100) / 100;
            var itemDiscount = Math.round(numberValue(item.discount_amount) * ratio * 100) / 100;
            var overall = Math.round(numberValue(item.overall_discount_share) * ratio * 100) / 100;
            var taxable = Math.round(numberValue(item.taxable_amount) * ratio * 100) / 100;
            var cgst = Math.round(numberValue(item.cgst_amount) * ratio * 100) / 100;
            var sgst = Math.round(numberValue(item.sgst_amount) * ratio * 100) / 100;
            var igst = Math.round(numberValue(item.igst_amount) * ratio * 100) / 100;
            var cess = Math.round(numberValue(item.cess_amount) * ratio * 100) / 100;
            var line = Math.round(numberValue(item.line_total) * ratio * 100) / 100;

            totals.subtotal += gross;
            totals.item_discount_total += itemDiscount;
            totals.overall_discount_total += overall;
            totals.taxable_total += taxable;
            totals.cgst_total += cgst;
            totals.sgst_total += sgst;
            totals.igst_total += igst;
            totals.cess_total += cess;
            totals.before_round_total += line;
            var cell = tr.querySelector(".line-return-amount");
            if (cell) cell.textContent = money(line);
        });

        Object.keys(totals).forEach(function (key) { totals[key] = Math.round(totals[key] * 100) / 100; });

        var completes = true;
        (invoice.items || []).forEach(function (item) {
            var soldBase = numberValue(item.base_quantity);
            var returnedBefore = numberValue(item.returned_base_quantity);
            var now = numberValue(returnBaseMap[Number(item.sale_item_id)] || 0);
            if (soldBase - returnedBefore - now > 0.0004) completes = false;
        });
        var invoiceRound = numberValue(invoice.round_off);
        var previousRound = numberValue(invoice.previous_return_round_off);
        var remainingRound = Math.round((invoiceRound - previousRound) * 100) / 100;
        var roundOff = 0;
        if (Math.abs(remainingRound) >= 0.005) {
            if (completes) roundOff = remainingRound;
            else if (Math.abs(numberValue(invoice.before_round_total)) >= 0.005 && Math.abs(invoiceRound) >= 0.005) {
                var share = Math.round(invoiceRound * (totals.before_round_total / numberValue(invoice.before_round_total)) * 100) / 100;
                roundOff = remainingRound > 0 ? Math.max(0, Math.min(share, remainingRound)) : Math.min(0, Math.max(share, remainingRound));
            }
        }
        totals.round_off = roundOff;
        totals.grand_total = Math.round((totals.before_round_total + roundOff) * 100) / 100;
        return totals;
    }

    function calculateSettlement(returnTotal) {
        var inv = state.invoice || {};
        var netBefore = numberValue(inv.current_net_total);
        var balanceBefore = numberValue(inv.balance_amount);
        var newNet = Math.max(0, Math.round((netBefore - returnTotal) * 100) / 100);
        var oldPayment = numberValue(inv.settlement_payment);
        var oldCredit = numberValue(inv.settlement_credit);
        var oldDiscount = numberValue(inv.settlement_discount);

        var remaining = newNet;
        var keepPayment = Math.min(oldPayment, remaining);
        remaining = Math.max(0, Math.round((remaining - keepPayment) * 100) / 100);
        var keepCredit = Math.min(oldCredit, remaining);
        remaining = Math.max(0, Math.round((remaining - keepCredit) * 100) / 100);
        var keepDiscount = Math.min(oldDiscount, remaining);

        var releasedPayment = Math.max(0, Math.round((oldPayment - keepPayment) * 100) / 100);
        var releasedCredit = Math.max(0, Math.round((oldCredit - keepCredit) * 100) / 100);
        var releasedDiscount = Math.max(0, Math.round((oldDiscount - keepDiscount) * 100) / 100);
        var settlementMode = Number(settlementType ? settlementType.value || 0 : 0);
        var refund = (settlementMode === 2 || settlementMode === 3) ? refundTotal() : 0;
        var creditGenerated = Math.max(0, Math.round(((releasedPayment - Math.min(refund, releasedPayment)) + releasedCredit) * 100) / 100);
        return {
            return_total: returnTotal,
            outstanding_before: balanceBefore,
            outstanding_adjusted: Math.min(returnTotal, balanceBefore),
            new_net: newNet,
            released_payment: releasedPayment,
            released_credit: releasedCredit,
            released_discount: releasedDiscount,
            max_refund: releasedPayment,
            refund: refund,
            credit_generated: creditGenerated,
            credit_after: Math.max(0, Math.round((numberValue(inv.customer_credit_balance) + creditGenerated) * 100) / 100)
        };
    }

    function refundTotal() {
        var total = 0;
        document.querySelectorAll(".refund-amount").forEach(function (input) {
            total += Math.max(0, numberValue(input.value));
        });
        return Math.round(total * 100) / 100;
    }

    function clearRefundValuesOnly() {
        document.querySelectorAll(".refund-amount,.refund-reference,.refund-cheque-no,.refund-cheque-date").forEach(function (el) {
            el.value = "";
            el.classList.remove("is-invalid");
        });
        document.querySelectorAll(".refund-account").forEach(function (select) {
            select.value = "";
            refreshGlobal(select);
        });
    }

    function settlementMessageText(s) {
        if (!state.invoice) return "Select a Final Invoice and enter Primary / Secondary Return Qty.";
        if (Number(s.return_total || 0) <= 0.001) return "Enter Return Qty to calculate the settlement.";
        if (Number(s.outstanding_adjusted || 0) >= Number(s.return_total || 0) - 0.01) {
            return "This return is fully adjusted against the invoice outstanding. No refund or customer credit is released.";
        }
        if (Number(s.max_refund || 0) > 0.001) {
            return money(s.max_refund) + " of actual customer payment is available for refund. Any unrefunded amount is stored in Customer Credit.";
        }
        if (Number(s.released_credit || 0) > 0.001) {
            return money(s.released_credit) + " of previously used Customer Credit is being restored.";
        }
        return "Settlement is calculated from the invoice outstanding and existing allocations.";
    }

    function updateLiveCalculation() {
        var t = calculateReturn();
        setText("sumGross", money(t.subtotal));
        setText("sumItemDiscount", money(t.item_discount_total));
        setText("sumOverallDiscount", money(t.overall_discount_total));
        setText("sumTaxable", money(t.taxable_total));
        setText("sumCgst", money(t.cgst_total));
        setText("sumSgst", money(t.sgst_total));
        setText("sumIgst", money(t.igst_total));
        setText("sumCess", money(t.cess_total));
        setText("sumRoundOff", money(t.round_off));
        setText("sumGrandTotal", money(t.grand_total));

        var s = calculateSettlement(t.grand_total);
        var type = Number(settlementType.value || 0);
        var wantsRefund = type === 2 || type === 3;

        /* Keep Refund / Split available only when an actual customer payment has
           been released by this return. The custom selector and native select are
           synchronised together, so the visible input never gets stuck. */
        var settlementWasChanged = syncSettlementAvailability(s.max_refund, false);
        if (settlementWasChanged) {
            type = Number(settlementType.value || 0);
            wantsRefund = type === 2 || type === 3;
            s = calculateSettlement(t.grand_total);
        } else if (!isView && !wantsRefund && refundTotal() > 0.001) {
            clearRefundValuesOnly();
            s = calculateSettlement(t.grand_total);
        }

        state.calculation = { totals: t, settlement: s };
        setText("settReturnTotal", money(s.return_total));
        setText("settOutstandingBefore", money(s.outstanding_before));
        setText("settOutstandingAdjusted", money(s.outstanding_adjusted));
        setText("settNewNet", money(s.new_net));
        setText("settReleasedPayment", money(s.released_payment));
        setText("settReleasedCredit", money(s.released_credit));
        setText("settReleasedDiscount", money(s.released_discount));
        setText("settMaxRefund", money(s.max_refund));
        setText("settRefund", money(s.refund));
        setText("settCreditGenerated", money(s.credit_generated));
        setText("settCreditAfter", money(s.credit_after));
        setText("refundMaximumLive", money(s.max_refund));
        setText("refundEnteredLive", money(s.refund));
        setText("refundRemainingLive", money(Math.max(0, s.max_refund - s.refund)));
        setText("refundCreditStoredLive", money(s.credit_generated));
        setText("refundCreditAfterLive", money(s.credit_after));
        setText("settlementMessage", settlementMessageText(s));
        updateRefundUi(s);
    }

    function updateRefundUi(summary) {
        var type = Number(settlementType.value || 0);
        var refundSelected = type === 2 || type === 3;
        var s = summary || (state.calculation ? state.calculation.settlement : {max_refund:0,refund:0});
        var hasRefundablePayment = Number(s.max_refund || 0) > 0.001;
        var refundEnabled = refundSelected && hasRefundablePayment && !isView;
        var entered = refundTotal();
        var overLimit = entered > Number(s.max_refund || 0) + 0.001;

        if (refundDetailsSection) refundDetailsSection.hidden = !refundSelected;
        document.querySelectorAll(".refund-method-row input, .refund-method-row select, #refundDate").forEach(function (el) {
            el.disabled = !refundEnabled;
            if (el.tagName === "SELECT") refreshGlobal(el);
        });
        document.querySelectorAll(".refund-amount").forEach(function (input) {
            input.classList.toggle("is-invalid", refundSelected && overLimit);
        });

        var help = byId("refundHelpText");
        if (help) {
            if (!refundSelected) {
                help.textContent = "Select Refund or Credit + Refund to enter refund details.";
            } else if (!hasRefundablePayment) {
                help.textContent = "No refund is available because this return is currently adjusted against outstanding and releases no actual payment.";
            } else if (overLimit) {
                help.textContent = "Refund Entered cannot exceed Maximum Refund " + money(s.max_refund) + ".";
            } else {
                help.textContent = "Enter refund against the fixed Cash / UPI / Bank / Cheque rows. Unrefunded payment is stored in Customer Credit.";
            }
        }
    }

    function refundRowData(code) {
        var row = document.querySelector('.refund-method-row[data-method="' + code + '"]');
        if (!row) return null;
        return {
            row: row,
            account: row.querySelector(".refund-account"),
            amount: row.querySelector(".refund-amount"),
            reference: row.querySelector(".refund-reference"),
            chequeNo: row.querySelector(".refund-cheque-no"),
            chequeDate: row.querySelector(".refund-cheque-date")
        };
    }

    function clearRefunds() {
        clearRefundValuesOnly();
        if (!isView) refundDate.value = returnDate.value || today();
        updateLiveCalculation();
    }

    function fillRefunds(rows) {
        clearRefunds();
        (rows || []).forEach(function (refund) {
            var code = String(refund.method_code || "").toUpperCase();
            var controls = refundRowData(code);
            if (!controls) return;
            controls.account.value = String(refund.account_id || "");
            refreshGlobal(controls.account);
            controls.amount.value = numberValue(refund.amount) > 0 ? numberValue(refund.amount).toFixed(2) : "";
            if (controls.reference) controls.reference.value = refund.payment_reference || "";
            if (controls.chequeNo) controls.chequeNo.value = refund.cheque_no || "";
            if (controls.chequeDate) controls.chequeDate.value = refund.cheque_date || "";
            if (refund.refund_date) refundDate.value = refund.refund_date;
        });
        updateLiveCalculation();
    }

    function collectRefunds() {
        var rows = [];
        ["CASH","UPI","BANK","CHEQUE"].forEach(function (code) {
            var c = refundRowData(code);
            if (!c) return;
            var amount = numberValue(c.amount.value);
            if (amount <= 0) return;
            rows.push({
                method_code: code,
                account_id: Number(c.account.value || 0),
                amount: amount,
                payment_reference: c.reference ? c.reference.value.trim() : "",
                cheque_no: c.chequeNo ? c.chequeNo.value.trim() : "",
                cheque_date: c.chequeDate ? c.chequeDate.value : "",
                refund_date: refundDate.value || returnDate.value
            });
        });
        return rows;
    }

    function validateSettlement() {
        var calc = state.calculation ? state.calculation.settlement : calculateSettlement(0);
        var type = Number(settlementType.value || 0);
        if (![1,2,3].includes(type)) {
            toast("Select Return Settlement.", "warning");
            return false;
        }
        var refund = refundTotal();
        if (refund > calc.max_refund + 0.001) {
            toast("Refund Amount cannot exceed " + money(calc.max_refund) + ".", "warning");
            return false;
        }
        if (type === 1 && refund > 0.001) {
            toast("Customer Credit settlement cannot contain Refund Amount.", "warning");
            return false;
        }
        if (type === 2) {
            if (calc.max_refund <= 0.001) {
                toast("No actual Customer Payment is refundable for this return.", "warning");
                return false;
            }
            if (Math.abs(refund - calc.max_refund) > 0.01) {
                toast("Refund settlement must refund the full refundable amount of " + money(calc.max_refund) + ".", "warning");
                return false;
            }
        }
        if (type === 3) {
            if (calc.max_refund <= 0.001 || refund <= 0.001 || refund >= calc.max_refund - 0.001) {
                toast("Split settlement needs a Refund Amount greater than ₹0.00 and less than " + money(calc.max_refund) + ".", "warning");
                return false;
            }
        }
        var methods = state.methods;
        var valid = true;
        collectRefunds().forEach(function (row) {
            var method = methods[row.method_code] || {};
            if (!row.account_id) { toast(row.method_code + " refund requires an Account.", "warning"); valid = false; return; }
            if (Number(method.requires_reference || 0) === 1 && !row.payment_reference) {
                toast((method.name || row.method_code) + " refund requires a Reference.", "warning"); valid = false;
            }
            if (Number(method.requires_cheque_details || 0) === 1 && (!row.cheque_no || !row.cheque_date)) {
                toast("Cheque refund requires Cheque No and Cheque Date.", "warning"); valid = false;
            }
        });
        if (refund > 0.001 && !refundDate.value) {
            toast("Refund Date is required.", "warning"); return false;
        }
        if (refundDate.value && returnDate.value && refundDate.value < returnDate.value) {
            toast("Refund Date cannot be before Return Date.", "warning"); return false;
        }
        return valid;
    }

    async function loadInvoiceByRef(ref, savedItems) {
        if (!ref) { resetInvoice(); return; }
        state.loading = true;
        try {
            var url = "api/sales-returns.php?sale_ref=" + encodeURIComponent(ref);
            if (isEdit && returnRef) url += "&exclude_return_ref=" + encodeURIComponent(returnRef);
            var result = await App.api(url);
            state.actions = (result.data.allowed_actions || []).map(Number);
            renderInvoice(result.data.invoice, savedItems || []);
        } catch (error) {
            resetInvoice();
            reportError(error, "Unable to load Final Invoice.");
        } finally {
            state.loading = false;
        }
    }

    function applySavedHeader(saved) {
        returnNo.value = saved.return_no || "";
        returnDate.value = saved.return_date || "";
        notes.value = saved.notes || "";
        setSettlementValue(String(saved.settlement_type || ""), true);
        saveButtonText.textContent = isEdit ? "Update Sales Return" : "Post Sales Return";
        byId("pageHeading").textContent = isEdit ? "Edit Sales Return" : "View Sales Return";
        byId("pageSubheading").textContent = isEdit ? "Update return quantity, refund and credit settlement." : "Posted Sales Return details.";
    }

    function makeReadOnly() {
        form.querySelectorAll("input,select,textarea").forEach(function (el) {
            if (el.id === "returnNo") return;
            if (el.tagName === "SELECT") setDisabled(el, true);
            else el.readOnly = true;
        });
        saveButton.hidden = true;
    }

    async function loadOptions() {
        var result = await App.api("api/sales-returns.php?options=1");
        state.actions = (result.data.allowed_actions || []).map(Number);
        state.customers = result.data.customers || [];
        state.invoices = result.data.invoices || [];
        state.accounts = result.data.accounts || [];
        state.methods = {};
        (result.data.payment_methods || []).forEach(function (m) { state.methods[String(m.code || "").toUpperCase()] = m; });
        returnNo.placeholder = (result.data.next_return_no || "Auto") + " (Auto on Post)";
        populateCustomers("");
        populateInvoices(0);
        populateRefundAccounts();
        return result;
    }

    async function loadExisting() {
        var result = await App.api("api/sales-returns.php?ref=" + encodeURIComponent(returnRef));
        state.actions = (result.data.allowed_actions || []).map(Number);
        state.saved = result.data.sales_return;
        var saved = state.saved;

        applySavedHeader(saved);
        populateCustomers(saved.customer_id || 0);
        populateInvoices(saved.sale_id || 0);
        setDisabled(customerSelect, true);
        setDisabled(saleRef, true);

        var currentInvoiceOption = state.invoices.find(function (row) { return Number(row.id) === Number(saved.sale_id); });
        var ref = currentInvoiceOption ? currentInvoiceOption.ref : (saved.editable_invoice ? saved.editable_invoice.ref : saved.sale_ref);
        if (saved.editable_invoice) {
            renderInvoice(saved.editable_invoice, saved.items || []);
        } else {
            await loadInvoiceByRef(ref, saved.items || []);
        }
        fillRefunds(saved.refunds || []);
        setSettlementValue(String(saved.settlement_type || ""), true);
        updateLiveCalculation();
        if (isView) makeReadOnly();
        else saveButton.hidden = !hasAction(ACTION_UPDATE);
    }

    function setupStaticGlobals() {
        [customerSelect, saleRef, settlementType].forEach(function (select) { initGlobal(select); });
        document.querySelectorAll(".refund-account").forEach(function (select) { initGlobal(select); });
    }

    async function start() {
        setupStaticGlobals();
        returnDate.value = today();
        refundDate.value = today();
        try {
            await loadOptions();
            if (returnRef) {
                await loadExisting();
            } else {
                saveButton.hidden = !hasAction(ACTION_RETURN);
                saveButtonText.textContent = "Post Sales Return";
                updateLiveCalculation();
            }
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            reportError(error, "Unable to load Sales Return.");
            saveButton.hidden = true;
        }
    }

    customerSelect.addEventListener("change", function () {
        if (state.loading || returnRef) return;
        populateInvoices(0);
        resetInvoice();
    });
    saleRef.addEventListener("change", function () {
        if (state.loading || returnRef) return;
        loadInvoiceByRef(saleRef.value, []);
    });
    settlementType.addEventListener("change", function () {
        var selected = String(settlementType.value || "");
        var refundAvailable = state.calculation
            ? Number(state.calculation.settlement.max_refund || 0) > 0.001
            : state.settlementRefundAvailable !== false;

        /* Re-sync the visual GlobalSelect immediately after a user selection. */
        setSettlementValue(selected, refundAvailable);

        var type = Number(settlementType.value || 0);
        if (type === 1 && !isView) {
            clearRefundValuesOnly();
        }
        updateLiveCalculation();
    });
    returnDate.addEventListener("change", function () {
        if (!refundDate.value || refundDate.value < returnDate.value) refundDate.value = returnDate.value;
    });
    document.querySelectorAll(".refund-amount").forEach(function (input) {
        input.addEventListener("input", updateLiveCalculation);
        input.addEventListener("change", updateLiveCalculation);
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (isView) return;
        if (isEdit ? !hasAction(ACTION_UPDATE) : !hasAction(ACTION_RETURN)) {
            toast("You do not have permission for this Sales Return action.", "warning");
            return;
        }
        if (!returnDate.value) { toast("Return Date is required.", "warning"); return; }
        if (!saleRef.value) { toast("Select Final Invoice.", "warning"); return; }
        if (state.invoice && state.invoice.invoice_date && returnDate.value < state.invoice.invoice_date) {
            toast("Return Date cannot be before Final Invoice Date.", "warning"); return;
        }
        var itemData = readReturnRows(true);
        if (itemData.invalid) return;
        if (!itemData.rows.length) { toast("Enter Primary or Secondary Return Qty for at least one item.", "warning"); return; }
        if (!validateSettlement()) return;

        var payload = {
            ref: isEdit ? returnRef : undefined,
            sale_ref: saleRef.value,
            return_date: returnDate.value,
            notes: notes.value.trim(),
            settlement_type: Number(settlementType.value || 0),
            refunds: collectRefunds(),
            items: itemData.rows
        };

        saveButton.disabled = true;
        try {
            var result = await App.api("api/sales-returns.php", {
                method: isEdit ? "PUT" : "POST",
                body: payload
            });
            toast(result.message || (isEdit ? "Sales Return updated successfully." : "Sales Return posted successfully."), "success");
            window.location.href = "sales-return-list.php";
        } catch (error) {
            reportError(error, isEdit ? "Unable to update Sales Return." : "Unable to post Sales Return.");
        } finally {
            saveButton.disabled = false;
        }
    });

    start();
})(window, document);

</script>
<script>if (window.lucide) window.lucide.createIcons();</script>
</body>
</html>