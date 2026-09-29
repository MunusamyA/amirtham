<?php

        require_once __DIR__ . '/include/web-config.php';

        $pageTitle = 'Sales POS';

        ?>

        <!doctype html>

        <html lang="en">

        <head>

            <meta charset="utf-8">

            <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">

            <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">

            <title><?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

            <?php render_frontend_config_script(); ?>

            <script src="assets/js/runtime.js"></script>

            <link rel="stylesheet" href="assets/css/core.css">

            <link rel="stylesheet" href="assets/css/components.css">

            <link rel="stylesheet" href="assets/css/theme.css">

            <link rel="stylesheet" href="assets/css/sales-form.css">

            <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

        </head>

        <body class="sales-pos-page">

        <script src="assets/js/toaster.js"></script>

        <script src="assets/js/app.js"></script>

        <script src="assets/js/theme.js"></script>

        <script src="assets/js/validation.js"></script>

        <script src="assets/js/global-select.js"></script>



        <div class="pos-shell" id="salesPosApp">

            <header class="pos-topbar">

                <div class="pos-brand-block">

                    <div class="pos-title-row">

                        <h1>Sales POS</h1>

                        <span class="pos-tax-badge" id="nonGstBadge" hidden>NON GST</span>

                    </div>

                    <div class="pos-current-document" id="currentDocumentLabel">New Sale</div>

                </div>

                <div class="pos-top-actions">

                    <button class="btn gray" id="profitButton" type="button" hidden>

                        <i data-lucide="badge-indian-rupee"></i><span>Profit</span>

                    </button>

                    <button class="btn gray" id="draftsButton" type="button">

                        <i data-lucide="files"></i><span>Drafts</span>

                        <span class="pos-count" id="draftCount">0</span>

                    </button>

                    <button class="btn btn-danger" id="exitButton" type="button">

                        <i data-lucide="log-out"></i><span>Exit</span>

                    </button>

                </div>

            </header>



            <main class="pos-content">

                <section class="pos-card pos-header-card">

                    <div class="pos-form-grid header-grid">

                        <div class="field">

                            <label for="salesDate" class="required">Sales Date</label>

                            <input id="salesDate" type="date" required>

                        </div>

                        <div class="field">

                            <label for="dueDate">Due Date</label>

                            <input id="dueDate" type="date">

                        </div>

                        <div class="field customer-field">

                            <label for="customerId" class="required">Customer</label>

                            <div class="input-group">

                                <div class="input-group-control">

                                    <select id="customerId" required>

                                        <option value="">Select Customer</option>

                                    </select>

                                </div>

                                <button class="btn btn-primary input-group-button" id="addCustomerButton" type="button" title="Add Customer" hidden>

                                    <i data-lucide="plus"></i>

                                </button>

                            </div>

                        </div>

                        <div class="field">

                            <label for="customerReference">Reference</label>

                            <input id="customerReference" type="text" maxlength="150" autocomplete="off" placeholder="PO / customer reference">

                        </div>

                    </div>

                    <div class="pos-supply-line">

                        <span class="muted">Place of Supply:</span>

                        <strong id="placeOfSupply">Select Customer</strong>

                    </div>

                </section>



                <section class="pos-card">

                    <div class="pos-section-head">

                        <div>

                            <h2>Product Entry</h2>

                            <p>Add one Product once. Primary and Secondary quantities can be entered together in the same row.</p>

                        </div>

                    </div>

                    <div class="product-entry-scroll">

                        <div class="product-entry-grid">

                            <div class="field product-col">

                                <label for="entryProduct">Product</label>

                                <select id="entryProduct"><option value="">Select Product</option></select>

                            </div>

                            <div class="field batch-col">

                                <label for="entryBatch">Batch</label>

                                <select id="entryBatch"><option value="">Select Batch</option></select>

                            </div>

                            <div class="field stock-col">

                                <label>Stock</label>

                                <input id="entryStock" type="text" value="" placeholder="0" readonly>

                            </div>

                            <div class="field qty-col">

                                <label for="entryPrimaryQty" id="entryPrimaryQtyLabel">Primary Qty</label>

                                <input id="entryPrimaryQty" type="text" inputmode="decimal" placeholder="0.000">

                            </div>

                            <div class="field qty-col" id="entrySecondaryQtyField">

                                <label for="entrySecondaryQty" id="entrySecondaryQtyLabel">Secondary Qty</label>

                                <input id="entrySecondaryQty" type="text" inputmode="decimal" placeholder="0.000">

                            </div>

                            <div class="field seller-col">

                                <label for="entrySeller">Seller Type</label>

                                <select id="entrySeller"><option value="">Default</option></select>

                            </div>

                            <div class="field rate-col">

                                <label for="entryRate">Primary Rate</label>

                                <input id="entryRate" type="text" inputmode="decimal" placeholder="0.00">

                            </div>

                            <div class="field dtype-col">

                                <label for="entryDiscountType">Disc Type</label>

                                <select id="entryDiscountType">

                                    <option value="1">Percentage</option>

                                    <option value="2">Fixed</option>

                                </select>

                            </div>

                            <div class="field discount-col">

                                <label for="entryDiscount">Discount</label>

                                <input id="entryDiscount" type="text" inputmode="decimal" value="" placeholder="0.00">

                            </div>

                            <div class="field taxtype-col">

                                <label for="entryTaxType">Tax Type</label>

                                <select id="entryTaxType">

                                    <option value="1">Inclusive</option>

                                    <option value="2">Exclusive</option>

                                </select>

                            </div>

                            <div class="field add-col">

                                <label>&nbsp;</label>

                                <button class="btn btn-primary pos-add-product" id="addProductButton" type="button" title="Add Product">

                                    <i data-lucide="plus"></i>

                                </button>

                            </div>

                        </div>

                    </div>

                </section>



                <section class="pos-card items-card">

                    <div class="pos-section-head">

                        <div>

                            <h2>Sales Items</h2>

                            <p id="itemsHelp">No Products added.</p>

                        </div>

                    </div>

                    <div class="pos-items-table-wrap">

                        <table class="pos-items-table" id="salesItemsTable">

                            <thead>

                            <tr>

                                <th>#</th><th>Product</th><th>Batch</th><th>Stock</th><th>Primary Qty</th><th>Secondary Qty</th>

                                <th>Seller</th><th>Primary Rate</th><th>Disc Type</th><th>Discount</th><th>Tax Type</th><th>Amount</th><th></th>

                            </tr>

                            </thead>

                            <tbody id="salesItemsBody"></tbody>

                        </table>

                    </div>

                    <div class="pos-mobile-items" id="mobileItems"></div>

                </section>



                <section class="pos-bottom-grid">

                    <div class="pos-card payment-card">

                        <div class="pos-section-head"><div><h2>Payment</h2><p>Final Invoice creates one Customer Payment receipt with Cash / UPI / Bank / Cheque detail rows.</p></div></div>

                        <div class="payment-table-wrap">

                            <table class="payment-table">

                                <colgroup>

                                    <col class="pay-col-mode">

                                    <col class="pay-col-account">

                                    <col class="pay-col-amount">

                                    <col class="pay-col-reference">

                                    <col class="pay-col-date">

                                </colgroup>

                                <thead><tr><th>Mode</th><th>Account</th><th>Amount</th><th>Reference No</th><th>Date</th></tr></thead>

                                <tbody>

                                <tr data-payment-mode="1"><td><strong>Cash</strong></td><td><select class="pay-account"></select></td><td><input class="pay-amount" type="text" inputmode="decimal" value="" placeholder="0.00"></td><td><span class="muted">—</span><input class="pay-reference" type="hidden" value=""></td><td><span class="muted">—</span></td></tr>

                                <tr data-payment-mode="2"><td><strong>UPI</strong></td><td><select class="pay-account"></select></td><td><input class="pay-amount" type="text" inputmode="decimal" value="" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="100" placeholder="UPI Reference No"></td><td><span class="muted">—</span></td></tr>

                                <tr data-payment-mode="3"><td><strong>Bank</strong></td><td><select class="pay-account"></select></td><td><input class="pay-amount" type="text" inputmode="decimal" value="" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="100" placeholder="Bank Reference No"></td><td><span class="muted">—</span></td></tr>

                                <tr data-payment-mode="4"><td><strong>Cheque</strong></td><td><select class="pay-account"></select></td><td><input class="pay-amount" type="text" inputmode="decimal" value="" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="100" placeholder="Cheque No"></td><td><input class="pay-date" type="date" aria-label="Cheque Date"></td></tr>

                                </tbody>

                            </table>

                        </div>

                        <div class="payment-totals">

                            <div><span>Already Settled / Allocated</span><strong id="externalAllocated">₹0.00</strong></div>

                            <div><span>Available Customer Credit</span><strong id="availableCustomerCredit">₹0.00</strong></div>

                            <div class="summary-control-row">

                                <label for="creditApplied">Customer Credit To Use</label>

                                <input id="creditApplied" type="text" inputmode="decimal" value="" placeholder="0.00">

                            </div>

                            <div><span>Received Now</span><strong id="receivedNow">₹0.00</strong></div>

                            <div id="paymentCreditRow" hidden><span>New Credit Generated</span><strong id="paymentCredit">₹0.00</strong></div>

                            <div class="payment-balance"><span>Balance</span><strong id="paymentBalance">₹0.00</strong></div>

                        </div>

                    </div>



                    <div class="pos-card summary-card">

                        <div class="pos-section-head"><div><h2>Sales Summary</h2><p>Recalculated from the current item rows.</p></div></div>

                        <div class="summary-lines">

                            <div><span>Gross</span><strong id="sumGross">₹0.00</strong></div>

                            <div><span>Item Discount</span><strong id="sumItemDiscount">₹0.00</strong></div>

                            <div class="summary-control-row">

                                <label for="overallDiscountType">Overall Discount Type</label>

                                <select id="overallDiscountType"><option value="1">Percentage</option><option value="2">Fixed</option></select>

                            </div>

                            <div class="summary-control-row">

                                <label for="overallDiscountValue">Overall Discount Value</label>

                                <input id="overallDiscountValue" type="text" inputmode="decimal" value="" placeholder="0.00">

                            </div>

                            <div><span>Overall Discount</span><strong id="sumOverallDiscount">₹0.00</strong></div>

                            <div><span>Taxable</span><strong id="sumTaxable">₹0.00</strong></div>

                            <div class="tax-summary-line"><span>CGST</span><strong id="sumCgst">₹0.00</strong></div>

                            <div class="tax-summary-line"><span>SGST</span><strong id="sumSgst">₹0.00</strong></div>

                            <div class="tax-summary-line"><span>IGST</span><strong id="sumIgst">₹0.00</strong></div>

                            <div class="tax-summary-line"><span>Cess</span><strong id="sumCess">₹0.00</strong></div>

                            <div><span>Before Round</span><strong id="sumBeforeRound">₹0.00</strong></div>

                            <div class="round-row">

                                <span>Round Off</span>

                                <span class="round-actions"><strong id="sumRoundOff">₹0.00</strong><button class="btn gray small" type="button" id="roundOffButton">Round Off</button></span>

                            </div>

                            <div class="grand-total-line"><span>GRAND TOTAL</span><strong id="sumGrandTotal">₹0.00</strong></div>

                        </div>

                    </div>

                </section>



                <section class="pos-card remarks-card">

                    <div class="field">

                        <label for="notes">Remarks</label>

                        <textarea id="notes" rows="2" maxlength="2000" placeholder="Optional remarks"></textarea>

                    </div>

                </section>

            </main>



            <footer class="pos-actionbar">

                <div class="pos-draft-actions">

                    <button class="btn gray" id="clearDraftButton" type="button"><i data-lucide="eraser"></i>Clear Draft</button>

                    <button class="btn gray" id="saveDraftButton" type="button"><i data-lucide="save"></i>Save Draft</button>

                    <span class="pos-target-note" id="targetDocumentNote" hidden></span>

                    <span class="local-save-status" id="localSaveStatus"></span>

                </div>

                <div class="pos-document-actions">

                    <button class="btn doc-action" type="button" data-document-type="1" hidden>Quotation</button>

                    <button class="btn doc-action" type="button" data-document-type="2" hidden>Proforma Bill</button>

                    <button class="btn doc-action" type="button" data-document-type="3" hidden>Sales Bill</button>

                    <button class="btn btn-primary doc-action final-invoice-action" type="button" data-document-type="4" hidden>Final Invoice</button>

                </div>

            </footer>

        </div>



        <div class="pos-modal" id="draftsModal" hidden>

            <div class="pos-modal-backdrop" data-close-modal="draftsModal"></div>

            <div class="pos-modal-dialog wide">

                <div class="pos-modal-head"><div><h2>Local Sales Drafts</h2><p>Stored only in this browser.</p></div><button class="icon-button" type="button" data-close-modal="draftsModal"><i data-lucide="x"></i></button></div>

                <div class="pos-modal-body"><div id="draftList" class="draft-list"></div></div>

            </div>

        </div>



        <div class="pos-modal" id="profitModal" hidden>

            <div class="pos-modal-backdrop" data-close-modal="profitModal"></div>

            <div class="pos-modal-dialog">

                <div class="pos-modal-head"><div><h2>Profit Details</h2><p>Based on the selected Purchase Batch cost.</p></div><button class="icon-button" type="button" data-close-modal="profitModal"><i data-lucide="x"></i></button></div>

                <div class="pos-modal-body">

                    <div class="profit-summary">

                        <div><span>Net Sales Value</span><strong id="profitSales">₹0.00</strong></div>

                        <div><span>Batch Cost</span><strong id="profitCost">₹0.00</strong></div>

                        <div class="profit-total"><span>Profit</span><strong id="profitAmount">₹0.00</strong></div>

                        <div><span>Profit Margin</span><strong id="profitMargin">0.00%</strong></div>

                    </div>

                    <div class="profit-table-wrap"><table class="profit-table"><thead><tr><th>Product</th><th>Sales</th><th>Cost</th><th>Profit</th></tr></thead><tbody id="profitItems"></tbody></table></div>

                </div>

            </div>

        </div>



        <?php require __DIR__ . '/modal/customer.php'; ?>



        <script>
(function (window, document) {
    "use strict";

    var urlParams = new URLSearchParams(window.location.search);

    var ACTION_CREATE = 2;
    var ACTION_UPDATE = 3;
    var ACTION_QUOTATION = 55;
    var ACTION_PROFORMA = 56;
    var ACTION_SALES_BILL = 57;
    var ACTION_FINAL_INVOICE = 58;
    var ACTION_VIEW_PROFIT = 59;

    var DOC_LABELS = {
        1: "Quotation",
        2: "Proforma Bill",
        3: "Sales Bill",
        4: "Final Invoice"
    };

    var state = {
        ref: urlParams.get("ref") || "",
        targetRef: urlParams.get("target") || "",
        targetDocumentType: 0,
        targetRefs: {},
        printMode: urlParams.get("print") === "1",
        currentDocumentType: 0,
        currentSalesNo: "",
        taxMode: 1,
        branch: null,
        currentUserId: 0,
        actions: [],
        customerFormActions: [],
        options: { customers: [], products: [], accounts: [] },
        customerMap: {},
        productMap: {},
        items: [],
        batchCache: {},
        externalAllocated: 0,
        roundOffEnabled: 0,
        totals: emptyTotals(),
        draftId: "",
        storageKey: "",
        workingBase: "",
        workingKey: "",
        loading: true,
        saving: false,
        persistenceTimer: null,
        customerSelect: null,
        entryProductSelect: null,
        pendingDocumentType: 0,
        pendingDocumentButton: null
    };

    function emptyTotals() {
        return {
            subtotal: 0,
            itemDiscountTotal: 0,
            overallDiscountAmount: 0,
            taxableTotal: 0,
            cgstTotal: 0,
            sgstTotal: 0,
            igstTotal: 0,
            cessTotal: 0,
            beforeRound: 0,
            roundOff: 0,
            grandTotal: 0
        };
    }

    function byId(id) { return document.getElementById(id); }
    function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
    function numberValue(value) {
        var n = Number(value || 0);
        return Number.isFinite(n) ? n : 0;
    }
    function round2(value) { return Math.round((numberValue(value) + Number.EPSILON) * 100) / 100; }
    function round3(value) { return Math.round((numberValue(value) + Number.EPSILON) * 1000) / 1000; }
    function money(value) {
        return "₹" + numberValue(value).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function decimal3(value) { return numberValue(value).toFixed(3); }
    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function selected(a, b) { return String(a) === String(b) ? " selected" : ""; }
    function hasAction(actionId) {
        return (state.actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }
    function hasCustomerAction(actionId) {
        return (state.customerFormActions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }
    function showSuccess(message) {
        if (window.showToast) showToast(message, { type: "success", duration: 2 });
    }
    function showInfo(message) {
        if (window.showToast) showToast(message, { type: "info", duration: 2 });
    }
    function showWarning(message) {
        if (window.showToast) showToast(message, { type: "warning", duration: 3 });
        else window.alert(message);
    }
    function reportError(error, fallback) {
        if (window.App && App.showError) App.showError(error, fallback);
        else window.alert((error && error.message) || fallback);
    }
    function today() {
        var d = new Date();
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, "0");
        var day = String(d.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + day;
    }

    function optionItems(rows, valueKey, textBuilder) {
        return (rows || []).map(function (row) {
            return { value: row[valueKey], text: textBuilder(row) };
        });
    }

    function rebuildMaps() {
        state.customerMap = {};
        (state.options.customers || []).forEach(function (row) { state.customerMap[Number(row.id)] = row; });
        state.productMap = {};
        (state.options.products || []).forEach(function (row) { state.productMap[Number(row.id)] = row; });
    }

    function customerText(row) {
        var text = row.customer_code ? row.customer_code + " - " + row.customer_name : row.customer_name;
        if (row.mobile) text += " - " + row.mobile;
        if (Number(row.status) === 0) text += " (Inactive)";
        return text;
    }

    function productText(row) {
        var text = row.product_code ? row.product_code + " - " + row.product_name : row.product_name;
        if (Number(row.status) === 0) text += " (Inactive)";
        return text;
    }

    function setMasterOptions(selectedCustomer, selectedProduct) {
        rebuildMaps();
        if (!state.customerSelect) {
            state.customerSelect = GlobalSelect.init("#customerId", { placeholder: "Select or type Customer" });
        }
        if (!state.entryProductSelect) {
            state.entryProductSelect = GlobalSelect.init("#entryProduct", { placeholder: "Select or type Product" });
        }

        state.customerSelect.setOptions(optionItems(state.options.customers, "id", customerText), selectedCustomer || "");
        state.entryProductSelect.setOptions(optionItems(state.options.products, "id", productText), selectedProduct || "");
        renderPaymentAccounts();
    }

    function documentActionId(type) {
        if (Number(type) === 1) return ACTION_QUOTATION;
        if (Number(type) === 2) return ACTION_PROFORMA;
        if (Number(type) === 3) return ACTION_SALES_BILL;
        if (Number(type) === 4) return ACTION_FINAL_INVOICE;
        return 0;
    }

    function updateDocumentButtons() {
        all(".doc-action").forEach(function (button) {
            var type = Number(button.getAttribute("data-document-type"));
            var actionId = documentActionId(type);
            var visible = false;
            var label = "Generate " + DOC_LABELS[type];

            if (state.printMode) {
                visible = false;
            } else if (state.ref) {
                var effectiveTarget = state.targetDocumentType || state.currentDocumentType;
                visible = type === Number(effectiveTarget)
                    && hasAction(ACTION_UPDATE)
                    && hasAction(actionId);
                label = Number(effectiveTarget) === Number(state.currentDocumentType)
                    ? "Update " + DOC_LABELS[type]
                    : "Generate " + DOC_LABELS[type];
            } else {
                visible = hasAction(ACTION_CREATE) && hasAction(actionId);
            }

            button.hidden = !visible;
            if (visible) button.textContent = label;
        });

        byId("profitButton").hidden = state.printMode || !hasAction(ACTION_VIEW_PROFIT);
        byId("addCustomerButton").hidden = state.printMode || !hasCustomerAction(ACTION_CREATE);
        byId("saveDraftButton").hidden = state.printMode;
        byId("clearDraftButton").hidden = state.printMode;
        byId("draftsButton").hidden = state.printMode;

        var targetNote = byId("targetDocumentNote");
        if (targetNote) {
            if (state.ref && state.targetDocumentType && Number(state.targetDocumentType) !== Number(state.currentDocumentType)) {
                targetNote.hidden = false;
                targetNote.textContent = "Target: " + DOC_LABELS[state.targetDocumentType];
            } else {
                targetNote.hidden = true;
                targetNote.textContent = "";
            }
        }

        if (!state.printMode) {
            byId("clearDraftButton").innerHTML = state.ref
                ? '<i data-lucide="rotate-ccw"></i>Reset Changes'
                : '<i data-lucide="eraser"></i>Clear';
        }
        refreshIcons();
    }

    function updateCurrentDocumentLabel() {
        var text = "New Sale";
        if (state.ref && state.currentDocumentType && state.currentSalesNo) {
            text = DOC_LABELS[state.currentDocumentType] + " · " + state.currentSalesNo;
            if (state.targetDocumentType && Number(state.targetDocumentType) !== Number(state.currentDocumentType)) {
                text += " → " + DOC_LABELS[state.targetDocumentType];
            }
        }
        byId("currentDocumentLabel").textContent = text;
    }

    function refreshIcons() {
        if (window.lucide && lucide.createIcons) lucide.createIcons();
    }

    function setTaxMode(mode, silent) {
        state.taxMode = Number(mode) === 0 ? 0 : 1;
        byId("nonGstBadge").hidden = state.taxMode !== 0;
        recalculateAll();
        scheduleLocalSave();
        /* Keep the shortcut silent. The NON GST badge is the only visual indicator. */
    }

    function toggleTaxMode() {
        if (state.currentDocumentType === 4 && state.ref && !hasAction(ACTION_UPDATE)) {
            showWarning("You do not have permission to edit this Final Invoice.");
            return;
        }
        if (state.items.length > 0) {
            if (!window.confirm("Changing tax mode will recalculate every item and total. Continue?")) return;
        }
        setTaxMode(state.taxMode === 0 ? 1 : 0, false);
    }

    function getCustomer() {
        return state.customerMap[Number(byId("customerId").value || 0)] || null;
    }

    function normalizedStateCode(value) {
        var text = String(value == null ? "" : value).trim();
        if (!/^\d{1,2}$/.test(text)) return "";
        var number = Number(text);
        return number >= 1 && number <= 99 ? String(number).padStart(2, "0") : "";
    }

    function branchStateCode() {
        return normalizedStateCode(state.branch && state.branch.state_code);
    }

    function effectiveCustomerState(customer) {
        customer = customer || {};
        var gstin = String(customer.gstin || "").trim().toUpperCase();
        if (/^\d{2}[A-Z0-9]{13}$/.test(gstin)) {
            var prefix = normalizedStateCode(gstin.slice(0, 2));
            if (prefix) return prefix;
        }
        return normalizedStateCode(customer.state_code) || branchStateCode();
    }

    function supplyType() {
        if (state.taxMode === 0) return null;
        var branchCode = branchStateCode();
        var customerCode = effectiveCustomerState(getCustomer());
        return branchCode && customerCode && branchCode !== customerCode
            ? "inter_state" : "intra_state";
    }

    function updatePlaceOfSupply() {
        var customer = getCustomer();
        if (!customer) {
            byId("placeOfSupply").textContent = "Select Customer";
            return;
        }
        var code = effectiveCustomerState(customer);
        var directCode = normalizedStateCode(customer.state_code);
        var gstinCode = String(customer.gstin || "").match(/^([0-9]{2})[A-Z0-9]{13}$/i);
        var assumption = !directCode && !gstinCode;
        var text = code ? "State Code " + code : "State not recorded";
        if (state.taxMode === 1) {
            text += supplyType() === "inter_state" ? " · Inter State" : " · Intra State";
            if (assumption || !branchStateCode()) text += " · Local-state fallback; verify delivery state";
        }
        byId("placeOfSupply").textContent = text;
    }

    function productUnits(product) {
        var rows = [];
        if (!product) return rows;
        rows.push({
            id: Number(product.primary_unit_id),
            name: product.primary_unit_symbol || product.primary_unit_name || "Primary"
        });
        if (product.secondary_unit_id) {
            rows.push({
                id: Number(product.secondary_unit_id),
                name: product.secondary_unit_symbol || product.secondary_unit_name || "Secondary"
            });
        }
        return rows;
    }

    function conversionFor(source, selectedUnitId, savedConversion) {
        if (!source || !source.secondary_unit_id) return 1;
        var saved = numberValue(savedConversion);
        if (saved > 0) return saved;
        var snapshotRate = numberValue(source.conversion_rate);
        if (snapshotRate > 0) return snapshotRate;

        /* A loaded Purchase Batch is a historical snapshot. Never repair an
           invalid batch conversion from today's Product Master. */
        if (Number(source.purchase_item_id || 0) > 0 || source.available_base_qty !== undefined) {
            return 0;
        }

        /* Product Master is used only before a batch is chosen/new defaults. */
        var master = numberValue(source.secondary_conversion);
        return master > 0 ? master : 0;
    }

    function unitSource(product, batch, item) {
        if (batch && Number(batch.primary_unit_id || 0) > 0) return batch;
        if (item && Number(item.primary_unit_id || 0) > 0) return item;
        return product || {};
    }

    function applyBatchUnitSnapshot(item, batch, product) {
        var source = unitSource(product, batch, item);
        item.primary_unit_id = Number(source.primary_unit_id || (product && product.primary_unit_id) || 0);
        item.secondary_unit_id = Number(source.secondary_unit_id || 0) > 0 ? Number(source.secondary_unit_id) : null;
        item.primary_unit_name = source.primary_unit_name || (product && product.primary_unit_name) || '';
        item.primary_unit_symbol = source.primary_unit_symbol || (product && product.primary_unit_symbol) || '';
        item.secondary_unit_name = source.secondary_unit_name || (product && product.secondary_unit_name) || '';
        item.secondary_unit_symbol = source.secondary_unit_symbol || (product && product.secondary_unit_symbol) || '';
        item.conversion_rate = item.secondary_unit_id ? conversionFor(source, item.primary_unit_id, item.saved_conversion_rate) : 1;
        item.selected_unit_id = item.primary_unit_id;
        if (!item.secondary_unit_id) item.secondary_quantity = 0;
        return item;
    }

    function mixedStockText(baseQty, source, savedConversion) {
        baseQty = Math.max(0, round3(numberValue(baseQty)));
        source = source || {};
        var primaryName = source.primary_unit_symbol || source.primary_unit_name || 'Primary';
        if (!source.secondary_unit_id) return decimal3(baseQty) + ' ' + primaryName;
        var conversion = conversionFor(source, source.primary_unit_id, savedConversion);
        var primary = Math.floor((baseQty + 0.0000001) / conversion);
        var secondary = round3(baseQty - (primary * conversion));
        var secondaryName = source.secondary_unit_symbol || source.secondary_unit_name || 'Secondary';
        return decimal3(primary) + ' ' + primaryName + ' + ' + decimal3(secondary) + ' ' + secondaryName;
    }

    function sellerPriceOptions(product) {
        if (!product || !Array.isArray(product.sale_prices)) return [];
        // Product-specific Seller Types only; do not show inactive/removed associations.
        // Existing invoice snapshots are preserved separately by itemSellerOptions().
        return product.sale_prices.filter(function (row) {
            return Number(row.status) === 1 && Number(row.seller_type_status) === 1;
        });
    }

    function sellerPriceRow(product, sellerTypeId, sellerName) {
        var rows = sellerPriceOptions(product);
        var id = Number(sellerTypeId || 0);
        var name = String(sellerName || "").trim();

        for (var i = 0; i < rows.length; i += 1) {
            if (id > 0 && Number(rows[i].seller_type_id || 0) === id) return rows[i];
        }
        if (name) {
            for (var j = 0; j < rows.length; j += 1) {
                if (String(rows[j].seller_type_name || "").trim().toLowerCase() === name.toLowerCase()) return rows[j];
            }
        }
        return null;
    }

    function sellerPrice(product, sellerTypeId, sellerName) {
        var row = sellerPriceRow(product, sellerTypeId, sellerName);
        return row ? numberValue(row.sale_price) : (product ? numberValue(product.sale_price) : 0);
    }

    function batchCacheKey(productId) {
        return String(Number(productId)) + "|" + (state.ref || "new");
    }

    async function loadBatchOptions(productId, force) {
        productId = Number(productId || 0);
        if (!productId) return [];
        var key = batchCacheKey(productId);
        if (!force && state.batchCache[key]) return state.batchCache[key];

        var url = "api/sales.php?batches=1&product_id=" + encodeURIComponent(productId);
        if (state.ref) url += "&ref=" + encodeURIComponent(state.ref);
        var result = await App.api(url);
        var rows = (result.data && result.data.batches) || [];
        state.batchCache[key] = rows;
        return rows;
    }

    function findBatch(productId, sourcePurchaseId) {
        var rows = state.batchCache[batchCacheKey(productId)] || [];
        for (var i = 0; i < rows.length; i += 1) {
            if (Number(rows[i].id) === Number(sourcePurchaseId)) return rows[i];
        }
        return null;
    }

    function batchLabel(row) {
        var label = row.batch_number || row.purchase_no || ("Purchase #" + row.id);
        if (row.purchase_no && row.batch_number) label += " · " + row.purchase_no;
        label += " · Stock " + decimal3(row.available_base_qty);
        return label;
    }

    function updateEntryBatchUnits() {
        var productId = Number(byId('entryProduct').value || 0);
        var sourceId = Number(byId('entryBatch').value || 0);
        var product = state.productMap[productId] || null;
        var batch = findBatch(productId, sourceId);
        var source = unitSource(product, batch, null);
        var primaryName = source.primary_unit_symbol || source.primary_unit_name || 'Primary';
        var secondaryName = source.secondary_unit_symbol || source.secondary_unit_name || 'Secondary';
        var hasSecondary = Number(source.secondary_unit_id || 0) > 0;
        byId('entryPrimaryQtyLabel').textContent = 'Primary Qty (' + primaryName + ')';
        byId('entrySecondaryQtyLabel').textContent = 'Secondary Qty (' + secondaryName + ')';
        byId('entrySecondaryQtyField').hidden = !hasSecondary;
        byId('entrySecondaryQty').disabled = !hasSecondary;
        if (!hasSecondary) byId('entrySecondaryQty').value = '';
    }

    async function prepareEntryProduct() {
        var productId = Number(byId("entryProduct").value || 0);
        var product = state.productMap[productId] || null;
        var batch = byId("entryBatch");
        var seller = byId("entrySeller");
        var secondaryField = byId("entrySecondaryQtyField");
        var secondaryInput = byId("entrySecondaryQty");

        batch.innerHTML = '<option value="">Select Batch</option>';
        seller.innerHTML = '<option value="">Select Product First</option>';
        seller.disabled = true;
        byId("entryStock").value = "";

        if (!product) {
            byId("entryPrimaryQtyLabel").textContent = "Primary Qty";
            byId("entrySecondaryQtyLabel").textContent = "Secondary Qty";
            secondaryField.hidden = false;
            secondaryInput.disabled = true;
            secondaryInput.value = "";
            byId("entryRate").value = "";
            return;
        }

        updateEntryBatchUnits();

        // The selected Product determines the Seller Types and primary sale rates.
        // No second Seller Type API call is required: prices came with POS options.
        var prices = sellerPriceOptions(product);
        if (prices.length) {
            seller.innerHTML = prices.map(function (row) {
                return '<option value="' + Number(row.seller_type_id || 0) + '">' + escapeHtml(row.seller_type_name) + '</option>';
            }).join("");
            seller.disabled = false;
            seller.value = String(Number(prices[0].seller_type_id || 0));
            byId("entryRate").value = numberValue(prices[0].sale_price).toFixed(2);
        } else {
            // Keep existing legacy base-price behaviour for products without price groups.
            seller.innerHTML = '<option value="">Default Product Price (No Seller Type)</option>';
            seller.disabled = true;
            byId("entryRate").value = numberValue(product.sale_price).toFixed(2);
        }
        byId("entryTaxType").value = String(Number(product.sale_tax_type) === 1 ? 1 : 2);

        try {
            var batches = await loadBatchOptions(productId, false);
            // Ignore a late response if the user has already selected another Product.
            if (Number(byId("entryProduct").value || 0) !== productId) return;
            batches.forEach(function (row) {
                batch.insertAdjacentHTML("beforeend", '<option value="' + Number(row.id) + '">' + escapeHtml(batchLabel(row)) + '</option>');
            });
            updateEntryBatchUnits();
        } catch (error) {
            reportError(error, "Unable to load Product batches.");
        }
    }

    function updateEntryStock() {
        var productId = Number(byId("entryProduct").value || 0);
        var sourceId = Number(byId("entryBatch").value || 0);
        var product = state.productMap[productId] || null;
        var batch = findBatch(productId, sourceId);
        if (!product || !batch) {
            byId("entryStock").value = "";
            validateEntryQuantity(false);
            return;
        }
        updateEntryBatchUnits();
        byId("entryStock").value = mixedStockText(numberValue(batch.available_base_qty), unitSource(product, batch, null), null);

        /* Current Product Master price is only a convenience default. If the
           Product Master Primary Unit has changed since this batch was bought,
           that price cannot safely be assumed to be the batch Primary rate. */
        if (Number(batch.primary_unit_id || 0) > 0 && Number(product.primary_unit_id || 0) > 0 &&
            Number(batch.primary_unit_id) !== Number(product.primary_unit_id)) {
            byId('entryRate').value = '';
        }
        validateEntryQuantity(false);
    }

    function setQtyInvalid(nodes, invalid) {
        (nodes || []).forEach(function (node) {
            if (!node) return;
            node.classList.toggle("is-invalid", !!invalid);
            if (invalid) node.setAttribute("aria-invalid", "true");
            else node.removeAttribute("aria-invalid");
        });
    }

    function selectedUnitLabel(product, unitId) {
        var units = productUnits(product);
        for (var i = 0; i < units.length; i += 1) {
            if (Number(units[i].id) === Number(unitId)) return units[i].name || "Unit";
        }
        return "Unit";
    }

    function validateEntryQuantity(announce) {
        var productId = Number(byId("entryProduct").value || 0);
        var sourceId = Number(byId("entryBatch").value || 0);
        var primaryQty = numberValue(byId("entryPrimaryQty").value);
        var secondaryQty = numberValue(byId("entrySecondaryQty").value);
        var product = state.productMap[productId] || null;
        var batch = findBatch(productId, sourceId);
        var nodes = [byId("entryPrimaryQty"), byId("entrySecondaryQty")];

        if (!product || !batch || (primaryQty <= 0 && secondaryQty <= 0)) {
            setQtyInvalid(nodes, false);
            return true;
        }
        var source = unitSource(product, batch, null);
        if (!source.secondary_unit_id) secondaryQty = 0;
        var conversion = conversionFor(source, source.primary_unit_id, null);
        if (source.secondary_unit_id && conversion <= 0) {
            setQtyInvalid(nodes, true);
            if (announce) showWarning('Selected Batch has an invalid saved unit conversion. Fix/recreate that Purchase Batch first.');
            return false;
        }
        var requestedBase = round3((primaryQty * conversion) + secondaryQty);
        var availableBase = numberValue(batch.available_base_qty);
        var invalid = requestedBase > availableBase + 0.0005;
        setQtyInvalid(nodes, invalid);
        if (invalid && announce) {
            showWarning("Qty exceeds Batch stock. Available: " + mixedStockText(availableBase, source, null) + ".");
            try { byId("entryPrimaryQty").focus(); byId("entryPrimaryQty").select(); } catch (ignore) {}
        }
        return !invalid;
    }

    function validateItemQuantity(index, announce) {
        index = Number(index);
        var item = state.items[index];
        if (!item) return true;
        recalculateAll();

        var nodes = all('.js-item-primary-qty[data-index="' + index + '"],.js-item-secondary-qty[data-index="' + index + '"]');
        if (!item.product_id || !item.source_purchase_id || (numberValue(item.primary_quantity) <= 0 && numberValue(item.secondary_quantity) <= 0)) {
            setQtyInvalid(nodes, false);
            return true;
        }
        var invalid = numberValue(item.base_quantity) > numberValue(item._available_base) + 0.0005;
        setQtyInvalid(nodes, invalid);
        if (invalid && announce) {
            var product = state.productMap[Number(item.product_id)] || null;
            var batch = findBatch(item.product_id, item.source_purchase_id);
            showWarning("Qty exceeds Batch stock. Available: " + mixedStockText(item._available_base, unitSource(product, batch, item), item.saved_conversion_rate) + ".");
            if (nodes[0]) { try { nodes[0].focus(); nodes[0].select(); } catch (ignore) {} }
        }
        return !invalid;
    }

    function clearProductEntry() {
        if (state.entryProductSelect) state.entryProductSelect.setOptions(optionItems(state.options.products, "id", productText), "");
        byId("entryBatch").innerHTML = '<option value="">Select Batch</option>';
        byId("entryStock").value = "";
        byId("entryPrimaryQty").value = "";
        byId("entrySecondaryQty").value = "";
        byId("entrySecondaryQty").disabled = true;
        setQtyInvalid([byId("entryPrimaryQty"), byId("entrySecondaryQty")], false);
        byId("entrySeller").innerHTML = '<option value="">Select Product First</option>';
        byId("entrySeller").disabled = true;
        byId("entryRate").value = "";
        byId("entryDiscountType").value = "1";
        byId("entryDiscount").value = "";
        byId("entryTaxType").value = "2";
    }

    function duplicateProduct(productId, ignoreIndex) {
        for (var i = 0; i < state.items.length; i += 1) {
            if (i !== ignoreIndex && Number(state.items[i].product_id) === Number(productId)) return true;
        }
        return false;
    }

    function addProductFromEntry() {
        var productId = Number(byId("entryProduct").value || 0);
        var sourceId = Number(byId("entryBatch").value || 0);
        var primaryQty = numberValue(byId("entryPrimaryQty").value);
        var secondaryQty = numberValue(byId("entrySecondaryQty").value);
        var rate = numberValue(byId("entryRate").value);
        var product = state.productMap[productId] || null;
        var batch = findBatch(productId, sourceId);

        if (!productId || !product) return showWarning("Select Product.");
        if (duplicateProduct(productId, -1)) return showWarning("This Product is already added. Please edit the existing row.");
        if (!sourceId || !batch) return showWarning("Select Batch.");
        var entryUnitSource = unitSource(product, batch, null);
        if (!entryUnitSource.secondary_unit_id) secondaryQty = 0;
        if (primaryQty <= 0 && secondaryQty <= 0) return showWarning("Enter Primary Qty or Secondary Qty greater than zero.");
        if (rate < 0) return showWarning("Enter a valid Primary Rate.");
        var entryConversion = conversionFor(entryUnitSource, entryUnitSource.primary_unit_id, null);
        if (entryUnitSource.secondary_unit_id && entryConversion <= 0) {
            return showWarning('Selected Batch has an invalid saved unit conversion. Fix/recreate that Purchase Batch first.');
        }
        if (!validateEntryQuantity(true)) return;

        var entrySellerId = Number(byId("entrySeller").value || 0);
        var entrySellerRow = sellerPriceRow(product, entrySellerId, "");

        state.items.push({
            item_id: 0,
            product_id: productId,
            source_purchase_id: sourceId,
            selected_unit_id: Number(entryUnitSource.primary_unit_id || product.primary_unit_id),
            primary_unit_id: Number(entryUnitSource.primary_unit_id || product.primary_unit_id),
            secondary_unit_id: Number(entryUnitSource.secondary_unit_id || 0) > 0 ? Number(entryUnitSource.secondary_unit_id) : null,
            primary_unit_name: entryUnitSource.primary_unit_name || product.primary_unit_name || '',
            primary_unit_symbol: entryUnitSource.primary_unit_symbol || product.primary_unit_symbol || '',
            secondary_unit_name: entryUnitSource.secondary_unit_name || product.secondary_unit_name || '',
            secondary_unit_symbol: entryUnitSource.secondary_unit_symbol || product.secondary_unit_symbol || '',
            saved_conversion_rate: numberValue(entryUnitSource.conversion_rate) || null,
            conversion_rate: entryConversion,
            primary_quantity: round3(primaryQty),
            secondary_quantity: round3(secondaryQty),
            quantity: 0,
            seller_type_id: entrySellerRow ? Number(entrySellerRow.seller_type_id || 0) : null,
            seller_type_name: entrySellerRow ? String(entrySellerRow.seller_type_name || "") : "",
            unit_price: round2(rate),
            discount_type: Number(byId("entryDiscountType").value || 1),
            discount_value: round2(numberValue(byId("entryDiscount").value)),
            tax_type: Number(byId("entryTaxType").value || 2),
            snapshot_gst_rate: null,
            snapshot_cess_rate: null,
            _available_base: numberValue(batch.available_base_qty),
            _base_cost: batch.base_cost === undefined ? null : numberValue(batch.base_cost)
        });

        recalculateAll();
        renderItems();
        clearProductEntry();
        scheduleLocalSave();
        try { byId("entryProduct").focus(); } catch (ignore) {}
    }

    function itemProductOptions(item) {
        var html = '<option value="">Select Product</option>';
        (state.options.products || []).forEach(function (row) {
            html += '<option value="' + Number(row.id) + '"' + selected(row.id, item.product_id) + '>' + escapeHtml(productText(row)) + '</option>';
        });
        return html;
    }

    function itemBatchOptions(item) {
        var html = '<option value="">Select Batch</option>';
        var rows = state.batchCache[batchCacheKey(item.product_id)] || [];
        rows.forEach(function (row) {
            html += '<option value="' + Number(row.id) + '"' + selected(row.id, item.source_purchase_id) + '>' + escapeHtml(batchLabel(row)) + '</option>';
        });
        return html;
    }

    function itemSellerOptions(item) {
        var product = state.productMap[Number(item.product_id)] || null;
        var currentId = Number(item.seller_type_id || 0);
        var currentName = String(item.seller_type_name || "");
        var rows = sellerPriceOptions(product);
        var matchedById = rows.some(function (row) {
            return currentId > 0 && Number(row.seller_type_id || 0) === currentId;
        });
        var matchedByName = rows.some(function (row) {
            return currentName && String(row.seller_type_name || "").toLowerCase() === currentName.toLowerCase();
        });

        var html = '<option value="0"' + ((!currentId && !currentName) ? ' selected' : '') + '>Default</option>';
        rows.forEach(function (row) {
            var selectedRow = currentId > 0
                ? Number(row.seller_type_id || 0) === currentId
                : (!currentId && currentName && String(row.seller_type_name || "").toLowerCase() === currentName.toLowerCase());
            html += '<option value="' + Number(row.seller_type_id || 0) + '"' + (selectedRow ? ' selected' : '') + '>' + escapeHtml(row.seller_type_name) + '</option>';
        });

        if (currentName && !matchedById && !matchedByName) {
            html += '<option value="-1" selected>' + escapeHtml(currentName + ' (Snapshot)') + '</option>';
        }
        return html;
    }

    function itemDisplayedStock(item) {
        var product = state.productMap[Number(item.product_id)] || null;
        var batch = findBatch(item.product_id, item.source_purchase_id);
        return mixedStockText(item._available_base, unitSource(product, batch, item), item.saved_conversion_rate);
    }

    function itemPrimaryLabel(item) {
        var product = state.productMap[Number(item.product_id)] || null;
        var batch = findBatch(item.product_id, item.source_purchase_id);
        var source = unitSource(product, batch, item);
        return source.primary_unit_symbol || source.primary_unit_name || 'Primary';
    }

    function itemSecondaryLabel(item) {
        var product = state.productMap[Number(item.product_id)] || null;
        var batch = findBatch(item.product_id, item.source_purchase_id);
        var source = unitSource(product, batch, item);
        return source.secondary_unit_symbol || source.secondary_unit_name || 'Secondary';
    }

    function itemHasSecondary(item) {
        var product = state.productMap[Number(item.product_id)] || null;
        var batch = findBatch(item.product_id, item.source_purchase_id);
        return Number(unitSource(product, batch, item).secondary_unit_id || 0) > 0;
    }

    function renderItems() {
        var body = byId("salesItemsBody");
        var mobile = byId("mobileItems");
        var desktopHtml = "";
        var mobileHtml = "";

        state.items.forEach(function (item, index) {
            desktopHtml += '<tr data-index="' + index + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td><select class="js-item-product" data-index="' + index + '">' + itemProductOptions(item) + '</select></td>' +
                '<td><select class="js-item-batch" data-index="' + index + '">' + itemBatchOptions(item) + '</select></td>' +
                '<td class="stock-cell" data-stock-index="' + index + '">' + escapeHtml(itemDisplayedStock(item)) + '</td>' +
                '<td><input class="js-item-primary-qty" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.primary_quantity) > 0 ? escapeHtml(numberValue(item.primary_quantity)) : '') + '" placeholder="' + escapeHtml(itemPrimaryLabel(item)) + '"></td>' +
                '<td><input class="js-item-secondary-qty" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.secondary_quantity) > 0 ? escapeHtml(numberValue(item.secondary_quantity)) : '') + '" placeholder="' + escapeHtml(itemSecondaryLabel(item)) + '" ' + (itemHasSecondary(item) ? '' : 'disabled') + '></td>' +
                '<td><select class="js-item-seller" data-index="' + index + '">' + itemSellerOptions(item) + '</select></td>' +
                '<td><input class="js-item-rate" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.unit_price) > 0 ? numberValue(item.unit_price).toFixed(2) : '') + '" placeholder="0.00"></td>' +
                '<td><select class="js-item-discount-type" data-index="' + index + '"><option value="1"' + selected(1,item.discount_type) + '>Percentage</option><option value="2"' + selected(2,item.discount_type) + '>Fixed</option></select></td>' +
                '<td><input class="js-item-discount" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.discount_value) > 0 ? numberValue(item.discount_value).toFixed(2) : '') + '" placeholder="0.00"></td>' +
                '<td><select class="js-item-tax-type" data-index="' + index + '"><option value="1"' + selected(1,item.tax_type) + '>Inclusive</option><option value="2"' + selected(2,item.tax_type) + '>Exclusive</option></select></td>' +
                '<td class="amount-cell" data-amount-index="' + index + '">' + money(item.line_total || 0) + '</td>' +
                '<td class="remove-cell"><button class="row-remove-button js-remove-item" data-index="' + index + '" type="button" title="Remove"><i data-lucide="x"></i></button></td>' +
                '</tr>';

            mobileHtml += '<div class="mobile-item-card" data-mobile-index="' + index + '">' +
                '<div class="mobile-item-top"><span class="mobile-item-number">Item ' + (index + 1) + '</span><button class="row-remove-button js-remove-item" data-index="' + index + '" type="button"><i data-lucide="trash-2"></i></button></div>' +
                '<div class="mobile-item-grid">' +
                mobileField("Product", '<select class="js-item-product" data-index="' + index + '">' + itemProductOptions(item) + '</select>', true) +
                mobileField("Batch", '<select class="js-item-batch" data-index="' + index + '">' + itemBatchOptions(item) + '</select>', true) +
                mobileField("Stock", '<input type="text" value="' + escapeHtml(itemDisplayedStock(item)) + '" readonly data-mobile-stock-index="' + index + '">') +
                mobileField("Primary Qty", '<input class="js-item-primary-qty" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.primary_quantity) > 0 ? escapeHtml(numberValue(item.primary_quantity)) : '') + '">') +
                mobileField("Secondary Qty", '<input class="js-item-secondary-qty" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.secondary_quantity) > 0 ? escapeHtml(numberValue(item.secondary_quantity)) : '') + '" ' + (itemHasSecondary(item) ? '' : 'disabled') + '>') +
                mobileField("Seller", '<select class="js-item-seller" data-index="' + index + '">' + itemSellerOptions(item) + '</select>') +
                mobileField("Primary Rate", '<input class="js-item-rate" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.unit_price) > 0 ? numberValue(item.unit_price).toFixed(2) : '') + '" placeholder="0.00">') +
                mobileField("Discount Type", '<select class="js-item-discount-type" data-index="' + index + '"><option value="1"' + selected(1,item.discount_type) + '>Percentage</option><option value="2"' + selected(2,item.discount_type) + '>Fixed</option></select>') +
                mobileField("Discount", '<input class="js-item-discount" data-index="' + index + '" type="text" inputmode="decimal" value="' + (numberValue(item.discount_value) > 0 ? numberValue(item.discount_value).toFixed(2) : '') + '" placeholder="0.00">') +
                mobileField("Tax Type", '<select class="js-item-tax-type" data-index="' + index + '"><option value="1"' + selected(1,item.tax_type) + '>Inclusive</option><option value="2"' + selected(2,item.tax_type) + '>Exclusive</option></select>') +
                '</div>' +
                '<div class="mobile-item-total"><span>Amount</span><strong data-mobile-amount-index="' + index + '">' + money(item.line_total || 0) + '</strong></div>' +
                '</div>';
        });

        body.innerHTML = desktopHtml;
        mobile.innerHTML = mobileHtml || '<div class="draft-empty">No Products added.</div>';
        byId("itemsHelp").textContent = state.items.length
            ? state.items.length + (state.items.length === 1 ? " Product added." : " Products added.")
            : "No Products added.";
        refreshIcons();
    }

    function mobileField(label, control, full) {
        return '<div class="field' + (full ? ' full' : '') + '"><label>' + escapeHtml(label) + '</label>' + control + '</div>';
    }

    function splitTaxRates(product, item) {
        if (state.taxMode === 0) return { gst: 0, cgst: 0, sgst: 0, igst: 0, cess: 0 };
        var gst = item.snapshot_gst_rate === null || item.snapshot_gst_rate === undefined
            ? numberValue(product && product.gst_rate)
            : numberValue(item.snapshot_gst_rate);
        var cess = item.snapshot_cess_rate === null || item.snapshot_cess_rate === undefined
            ? numberValue(product && product.cess_rate)
            : numberValue(item.snapshot_cess_rate);
        var supply = supplyType();
        var cgst = 0, sgst = 0, igst = 0;
        if (supply === "inter_state") {
            if ((item.snapshot_gst_rate === null || item.snapshot_gst_rate === undefined) && product && Math.abs(numberValue(product.igst_rate) - gst) < 0.01) {
                igst = numberValue(product.igst_rate);
            } else {
                igst = gst;
            }
        } else {
            /* Intra-state, or preview before Customer/State is selected. */
            if ((item.snapshot_gst_rate === null || item.snapshot_gst_rate === undefined) && product && Math.abs(numberValue(product.cgst_rate) + numberValue(product.sgst_rate) - gst) < 0.01) {
                cgst = numberValue(product.cgst_rate);
                sgst = numberValue(product.sgst_rate);
            } else {
                cgst = Math.round((gst / 2) * 1000) / 1000;
                sgst = Math.round((gst - cgst) * 1000) / 1000;
            }
        }
        return { gst: gst, cgst: cgst, sgst: sgst, igst: igst, cess: cess };
    }

    function recalculateAll() {
        updatePlaceOfSupply();
        var subtotal = 0;
        var itemDiscountTotal = 0;
        var preOverallTotal = 0;

        state.items.forEach(function (item) {
            var product = state.productMap[Number(item.product_id)] || null;
            var batch = findBatch(item.product_id, item.source_purchase_id);
            if (batch) {
                item._available_base = numberValue(batch.available_base_qty);
                if (batch.base_cost !== undefined) item._base_cost = numberValue(batch.base_cost);
            }
            applyBatchUnitSnapshot(item, batch, product);
            item.unit_price = numberValue(item.unit_price);
            var secondaryUnitRate = item.secondary_unit_id && item.conversion_rate > 0
                ? (item.unit_price / item.conversion_rate)
                : 0;
            item.base_quantity = round3((numberValue(item.primary_quantity) * item.conversion_rate) + numberValue(item.secondary_quantity));
            item.quantity = round3(numberValue(item.primary_quantity) + (item.secondary_unit_id ? numberValue(item.secondary_quantity) / item.conversion_rate : 0));
            var gross = round2((numberValue(item.primary_quantity) * item.unit_price) + (numberValue(item.secondary_quantity) * secondaryUnitRate));
            var discountType = Number(item.discount_type) === 2 ? 2 : 1;
            var discountValue = Math.max(0, numberValue(item.discount_value));
            var itemDiscount = discountType === 1 ? round2(gross * Math.min(100, discountValue) / 100) : Math.min(gross, round2(discountValue));
            var netGross = Math.max(0, round2(gross - itemDiscount));
            var rates = splitTaxRates(product, item);
            item.gst_rate = rates.gst;
            item.cgst_rate = rates.cgst;
            item.sgst_rate = rates.sgst;
            item.igst_rate = rates.igst;
            item.cess_rate = rates.cess;
            /* Allocate overall discount on the entered amount itself.
               For Inclusive tax, back GST out only after all discounts. */
            item.pre_overall_taxable = netGross;
            item.discount_amount = itemDiscount;
            subtotal += gross;
            itemDiscountTotal += itemDiscount;
            preOverallTotal += item.pre_overall_taxable;
        });

        subtotal = round2(subtotal);
        itemDiscountTotal = round2(itemDiscountTotal);
        preOverallTotal = round2(preOverallTotal);
        var overallType = Number(byId("overallDiscountType").value || 1) === 2 ? 2 : 1;
        var overallValue = Math.max(0, numberValue(byId("overallDiscountValue").value));
        var overallAmount = overallType === 1
            ? round2(preOverallTotal * Math.min(100, overallValue) / 100)
            : Math.min(preOverallTotal, round2(overallValue));

        var allocated = 0;
        var taxable = 0, cgst = 0, sgst = 0, igst = 0, cess = 0, beforeRound = 0;
        state.items.forEach(function (item, index) {
            var share = 0;
            if (overallAmount > 0 && preOverallTotal > 0) {
                if (index === state.items.length - 1) share = round2(overallAmount - allocated);
                else {
                    share = round2(overallAmount * item.pre_overall_taxable / preOverallTotal);
                    allocated += share;
                }
            }
            item.overall_discount_share = share;
            var netLineAmount = Math.max(0, round2(item.pre_overall_taxable - share));
            var combinedRate = numberValue(item.gst_rate) + numberValue(item.cess_rate);
            item.taxable_amount = Number(item.tax_type) === 1 && combinedRate > 0
                ? round2(netLineAmount / (1 + combinedRate / 100))
                : netLineAmount;
            item.cgst_amount = round2(item.taxable_amount * item.cgst_rate / 100);
            item.sgst_amount = round2(item.taxable_amount * item.sgst_rate / 100);
            item.igst_amount = round2(item.taxable_amount * item.igst_rate / 100);
            item.cess_amount = round2(item.taxable_amount * item.cess_rate / 100);
            if (Number(item.tax_type) === 1) {
                var inclusiveDiff = round2(netLineAmount - (item.taxable_amount + item.cgst_amount + item.sgst_amount + item.igst_amount + item.cess_amount));
                if (Math.abs(inclusiveDiff) >= 0.009) {
                    if (numberValue(item.cess_rate) > 0) item.cess_amount = round2(item.cess_amount + inclusiveDiff);
                    else if (numberValue(item.igst_rate) > 0) item.igst_amount = round2(item.igst_amount + inclusiveDiff);
                    else if (numberValue(item.sgst_rate) > 0) item.sgst_amount = round2(item.sgst_amount + inclusiveDiff);
                    else if (numberValue(item.cgst_rate) > 0) item.cgst_amount = round2(item.cgst_amount + inclusiveDiff);
                }
                item.line_total = round2(netLineAmount);
            } else {
                item.line_total = round2(item.taxable_amount + item.cgst_amount + item.sgst_amount + item.igst_amount + item.cess_amount);
            }
            taxable += item.taxable_amount;
            cgst += item.cgst_amount;
            sgst += item.sgst_amount;
            igst += item.igst_amount;
            cess += item.cess_amount;
            beforeRound += item.line_total;
        });

        taxable = round2(taxable);
        cgst = round2(cgst);
        sgst = round2(sgst);
        igst = round2(igst);
        cess = round2(cess);
        beforeRound = round2(beforeRound);
        var roundOff = state.roundOffEnabled ? round2(Math.round(beforeRound) - beforeRound) : 0;
        var grand = round2(beforeRound + roundOff);

        state.totals = {
            subtotal: subtotal,
            itemDiscountTotal: itemDiscountTotal,
            overallDiscountAmount: overallAmount,
            taxableTotal: taxable,
            cgstTotal: cgst,
            sgstTotal: sgst,
            igstTotal: igst,
            cessTotal: cess,
            beforeRound: beforeRound,
            roundOff: roundOff,
            grandTotal: grand
        };
        renderSummary();
        updateItemCalculatedCells();
        updatePaymentSummary();
    }

    function renderSummary() {
        byId("sumGross").textContent = money(state.totals.subtotal);
        byId("sumItemDiscount").textContent = money(state.totals.itemDiscountTotal);
        byId("sumOverallDiscount").textContent = money(state.totals.overallDiscountAmount);
        byId("sumTaxable").textContent = money(state.totals.taxableTotal);
        byId("sumCgst").textContent = money(state.totals.cgstTotal);
        byId("sumSgst").textContent = money(state.totals.sgstTotal);
        byId("sumIgst").textContent = money(state.totals.igstTotal);
        byId("sumCess").textContent = money(state.totals.cessTotal);
        byId("sumBeforeRound").textContent = money(state.totals.beforeRound);
        byId("sumRoundOff").textContent = money(state.totals.roundOff);
        byId("sumGrandTotal").textContent = money(state.totals.grandTotal);
        byId("roundOffButton").textContent = state.roundOffEnabled ? "Unround" : "Round Off";
        all(".tax-summary-line").forEach(function (row) { row.hidden = state.taxMode === 0; });
    }

    function updateItemCalculatedCells() {
        state.items.forEach(function (item, index) {
            all('[data-amount-index="' + index + '"]').forEach(function (node) { node.textContent = money(item.line_total || 0); });
            all('[data-mobile-amount-index="' + index + '"]').forEach(function (node) { node.textContent = money(item.line_total || 0); });
            all('[data-stock-index="' + index + '"]').forEach(function (node) { node.textContent = itemDisplayedStock(item); });
            all('[data-mobile-stock-index="' + index + '"]').forEach(function (node) { node.value = itemDisplayedStock(item); });
        });
    }

    function paymentRows() { return all("tr[data-payment-mode]"); }

    function preferredPaymentAccount(mode) {
        var requiredType = Number(mode) === 1 ? 1 : 2;
        var available = (state.options.accounts || []).filter(function (account) {
            return Number(account.account_type) === requiredType && Number(account.status) === 1;
        });
        if (!available.length) return "";
        var preferred = available.find(function (account) {
            return Number(mode) === 1 ? Number(account.is_default_cash) === 1
                : Number(account.is_default_bank) === 1;
        });
        return String((preferred || available[0]).id);
    }

    function choosePaymentAccount(select, selected, mode) {
        var id = String(selected || "");
        if (id && Array.prototype.some.call(select.options, function (option) { return option.value === id; })) {
            select.value = id;
        } else {
            select.value = preferredPaymentAccount(mode);
        }
    }

    function renderPaymentAccounts() {
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode"));
            var select = row.querySelector(".pay-account");
            var previous = select.value;
            var requiredType = mode === 1 ? 1 : 2;
            var html = '<option value="">Select Account</option>';
            (state.options.accounts || []).forEach(function (account) {
                if (Number(account.account_type) !== requiredType || Number(account.status) !== 1) return;
                var text = (account.account_code ? account.account_code + " - " : "") + account.account_name;
                html += '<option value="' + Number(account.id) + '">' + escapeHtml(text) + '</option>';
            });
            select.innerHTML = html;
            choosePaymentAccount(select, previous, mode);
        });
    }

    function fillPayments(payments, invoiceDate) {
        var modeMap = { cash: 1, upi: 2, bank: 3, cheque: 4 };
        var byMode = {};
        (payments || []).forEach(function (payment) {
            byMode[modeMap[String(payment.payment_mode || "").toLowerCase()] || 0] = payment;
        });
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode"));
            var payment = byMode[mode] || null;
            var accountInput = row.querySelector(".pay-account");
            var amountInput = row.querySelector(".pay-amount");
            var referenceInput = row.querySelector(".pay-reference");
            var dateInput = row.querySelector(".pay-date");

            if (accountInput) choosePaymentAccount(accountInput, payment && payment.account_id, mode);
            if (amountInput) amountInput.value = payment && numberValue(payment.amount) > 0 ? numberValue(payment.amount).toFixed(2) : "";
            if (referenceInput) referenceInput.value = payment ? (payment.payment_reference || payment.cheque_no || "") : "";

            /* Only Cheque has a visible .pay-date input.
               Cash / UPI / Bank use the Sales Date automatically. */
            if (dateInput) {
                dateInput.value = payment
                    ? (payment.cheque_date || payment.payment_date || invoiceDate || "")
                    : (invoiceDate || "");
            }
        });
        updatePaymentSummary();
    }

    function readPayments() {
        var rows = [];
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode"));
            var dateInput = row.querySelector(".pay-date");
            var salesDateInput = byId("salesDate");
            var date = (dateInput && dateInput.value) || (salesDateInput && salesDateInput.value) || "";
            var referenceInput = row.querySelector(".pay-reference");
            var reference = referenceInput ? referenceInput.value.trim() : "";
            rows.push({
                payment_mode: mode,
                account_id: Number(row.querySelector(".pay-account").value || 0),
                amount: round2(numberValue(row.querySelector(".pay-amount").value)),
                reference_no: reference,
                payment_date: date,
                cheque_no: mode === 4 ? reference : null,
                cheque_date: mode === 4 ? date : null
            });
        });
        return rows;
    }

    function updatePaymentSummary() {
        var received = 0;
        readPayments().forEach(function (row) { received += row.amount; });
        received = round2(received);
        var net = round2(state.totals.grandTotal - numberValue(state.externalAllocated) - received);
        var balance = Math.max(0, net);
        var credit = Math.max(0, -net);
        byId("externalAllocated").textContent = money(state.externalAllocated);
        byId("receivedNow").textContent = money(received);
        byId("paymentBalance").textContent = money(balance);
        byId("paymentCredit").textContent = money(credit);
        byId("paymentCreditRow").hidden = credit <= 0.001;
    }

    async function loadBatchesForItems(force) {
        var unique = {};
        state.items.forEach(function (item) { if (item.product_id) unique[Number(item.product_id)] = true; });
        await Promise.all(Object.keys(unique).map(function (productId) { return loadBatchOptions(Number(productId), !!force); }));
        state.items.forEach(function (item) {
            var batch = findBatch(item.product_id, item.source_purchase_id);
            if (batch) {
                item._available_base = numberValue(batch.available_base_qty);
                if (batch.base_cost !== undefined) item._base_cost = numberValue(batch.base_cost);
            }
            applyBatchUnitSnapshot(item, batch, state.productMap[Number(item.product_id)] || null);
        });
    }

    function normalizeLoadedItem(row) {
        return {
            item_id: Number(row.id || 0),
            product_id: Number(row.product_id || 0),
            source_purchase_id: Number(row.source_purchase_id || 0),
            selected_unit_id: Number(row.primary_unit_id || row.selected_unit_id || 0),
            primary_unit_id: Number(row.primary_unit_id || 0),
            secondary_unit_id: Number(row.secondary_unit_id || 0) > 0 ? Number(row.secondary_unit_id) : null,
            primary_unit_name: row.primary_unit_name || '',
            primary_unit_symbol: row.primary_unit_symbol || '',
            secondary_unit_name: row.secondary_unit_name || '',
            secondary_unit_symbol: row.secondary_unit_symbol || '',
            saved_conversion_rate: numberValue(row.conversion_rate) || null,
            primary_quantity: numberValue(row.primary_quantity),
            secondary_quantity: numberValue(row.secondary_quantity),
            quantity: numberValue(row.quantity),
            seller_type_id: Number(row.seller_type_id || 0) > 0 ? Number(row.seller_type_id) : null,
            seller_type_name: row.seller_type_name || "",
            unit_price: numberValue(row.unit_price),
            discount_type: Number(row.discount_type || 1),
            discount_value: numberValue(row.discount_value),
            tax_type: Number(row.tax_type || 2),
            snapshot_gst_rate: numberValue(row.gst_rate || row.tax_rate),
            snapshot_cess_rate: numberValue(row.cess_rate),
            _available_base: numberValue(row.base_quantity),
            _base_cost: null,
            line_total: numberValue(row.line_total)
        };
    }

    function applySaleRecord(sale) {
        state.ref = sale.ref || state.ref;
        state.currentDocumentType = Number(sale.document_type || 0);
        state.currentSalesNo = sale.sales_no || "";
        state.externalAllocated = Number(sale.document_type) === 4 ? numberValue(sale.external_allocated_amount) : 0;
        state.taxMode = Number(sale.tax_mode) === 0 ? 0 : 1;
        state.roundOffEnabled = Number(sale.round_off_enabled || 0) === 1 ? 1 : 0;
        state.items = (sale.items || []).map(normalizeLoadedItem);

        byId("salesDate").value = sale.invoice_date || "";
        byId("dueDate").value = sale.due_date || "";
        byId("customerReference").value = sale.customer_reference || "";
        byId("overallDiscountType").value = String(Number(sale.overall_discount_type || 1));
        byId("overallDiscountValue").value = numberValue(sale.overall_discount_value) > 0
            ? numberValue(sale.overall_discount_value).toFixed(2)
            : "";
        byId("notes").value = sale.notes || "";
        setMasterOptions(sale.customer_id || "", "");

        /* POS receipts created by this Invoice are editable and are loaded back.
           External Customer allocations remain separate and are never overwritten here. */
        fillPayments(sale.payments || [], sale.invoice_date || "");
        updateCurrentDocumentLabel();
        updateDocumentButtons();
        byId("nonGstBadge").hidden = state.taxMode !== 0;
    }

    function applyDefaultNewSale() {
        state.currentDocumentType = 0;
        state.currentSalesNo = "";
        state.externalAllocated = 0;
        state.roundOffEnabled = 0;
        state.items = [];
        state.taxMode = 1;

        /* Only Sales Date has a real default. Other empty controls use placeholders. */
        byId("salesDate").value = today();
        byId("dueDate").value = "";
        byId("customerReference").value = "";
        byId("overallDiscountType").value = "1";
        byId("overallDiscountValue").value = "";
        byId("notes").value = "";
        setMasterOptions("", "");
        fillPayments([], "");
        updateCurrentDocumentLabel();
        updateDocumentButtons();
        byId("nonGstBadge").hidden = state.taxMode !== 0;
    }

    function setStorageKey() {
        var branchId = state.branch ? Number(state.branch.branch_id) : 0;
        var userId = Number(state.currentUserId || 0);
        state.storageKey = "amirtham:sales:drafts:" + branchId + ":" + userId;
        state.workingBase = "amirtham:sales:working:" + branchId + ":" + userId + ":"
            + encodeURIComponent(state.ref || "new");
        state.workingKey = state.workingBase + ":" + encodeURIComponent(state.targetRef || "same");
    }

    function readDrafts() {
        if (!state.storageKey) return [];
        try {
            var parsed = JSON.parse(localStorage.getItem(state.storageKey) || "[]");
            return Array.isArray(parsed) ? parsed : [];
        } catch (ignore) { return []; }
    }

    function writeDrafts(rows) {
        try { localStorage.setItem(state.storageKey, JSON.stringify(rows || [])); } catch (error) {
            showWarning("Unable to store local draft in this browser.");
        }
        updateDraftCount();
    }

    function makeDraftId() {
        return "LD-" + Date.now() + "-" + Math.floor(Math.random() * 100000);
    }

    function hasMeaningfulData() {
        if (Number(byId("customerId").value || 0) > 0) return true;
        if (state.items.length > 0) return true;
        if (byId("customerReference").value.trim() || byId("notes").value.trim()) return true;
        return readPayments().some(function (row) { return row.amount > 0; });
    }

    function captureSnapshot() {
        return {
            draft_id: state.draftId || "",
            ref: state.ref || "",
            target_ref: state.targetRef || "",
            target_document_type: state.targetDocumentType || 0,
            current_document_type: state.currentDocumentType || 0,
            current_sales_no: state.currentSalesNo || "",
            created_at: state._draftCreatedAt || "",
            updated_at: new Date().toISOString(),
            tax_mode: state.taxMode,
            sales_date: byId("salesDate").value,
            due_date: byId("dueDate").value,
            customer_id: Number(byId("customerId").value || 0),
            customer_reference: byId("customerReference").value,
            items: state.items.map(function (item) {
                return {
                    item_id: item.item_id || 0,
                    product_id: item.product_id,
                    source_purchase_id: item.source_purchase_id,
                    selected_unit_id: item.selected_unit_id,
                    saved_conversion_rate: item.saved_conversion_rate,
                    primary_quantity: item.primary_quantity,
                    secondary_quantity: item.secondary_quantity,
                    quantity: item.quantity,
                    seller_type_id: Number(item.seller_type_id || 0) > 0 ? Number(item.seller_type_id) : null,
                    seller_type_name: item.seller_type_name || "",
                    unit_price: item.unit_price,
                    discount_type: item.discount_type,
                    discount_value: item.discount_value,
                    tax_type: item.tax_type,
                    snapshot_gst_rate: item.snapshot_gst_rate,
                    snapshot_cess_rate: item.snapshot_cess_rate
                };
            }),
            overall_discount_type: Number(byId("overallDiscountType").value || 1),
            overall_discount_value: numberValue(byId("overallDiscountValue").value),
            round_off_enabled: state.roundOffEnabled,
            payments: readPayments(),
            notes: byId("notes").value,
            grand_total: state.totals.grandTotal
        };
    }

    function saveLocalDraft(manual) {
        if (state.loading || !state.storageKey || !hasMeaningfulData()) {
            if (manual) showInfo("Nothing to save as a draft yet.");
            return;
        }

        if (!state.draftId) state.draftId = makeDraftId();
        if (!state._draftCreatedAt) state._draftCreatedAt = new Date().toISOString();

        var draft = captureSnapshot();
        draft.draft_id = state.draftId;
        draft.created_at = state._draftCreatedAt;

        var rows = readDrafts();
        var found = false;
        rows = rows.map(function (row) {
            if (row.draft_id === draft.draft_id) {
                found = true;
                return draft;
            }
            return row;
        });
        if (!found) rows.unshift(draft);
        rows.sort(function (a, b) { return String(b.updated_at).localeCompare(String(a.updated_at)); });
        writeDrafts(rows.slice(0, 100));
        byId("localSaveStatus").textContent = "Draft saved locally ✓";
        if (manual) showSuccess("Draft saved locally.");
    }

    function clearWorkingState(allContexts) {
        if (!state.workingKey) return;
        try {
            if (allContexts && state.workingBase) {
                for (var i = sessionStorage.length - 1; i >= 0; i -= 1) {
                    var key = sessionStorage.key(i);
                    if (key && key.indexOf(state.workingBase + ":") === 0) sessionStorage.removeItem(key);
                }
            } else {
                sessionStorage.removeItem(state.workingKey);
            }
        } catch (ignore) {}
    }

    function readWorkingState() {
        if (!state.workingKey) return null;
        try {
            var raw = sessionStorage.getItem(state.workingKey);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === "object" ? parsed : null;
        } catch (ignore) {
            return null;
        }
    }

    function saveWorkingState() {
        if (state.loading || !state.workingKey) return;

        if (!hasMeaningfulData()) {
            clearWorkingState();
            byId("localSaveStatus").textContent = state.draftId ? "Draft saved locally ✓" : "";
            return;
        }

        try {
            sessionStorage.setItem(state.workingKey, JSON.stringify(captureSnapshot()));
            byId("localSaveStatus").textContent = state.draftId
                ? "Draft saved locally ✓"
                : "Refresh protected ✓";
        } catch (ignore) {
            byId("localSaveStatus").textContent = "";
        }

        /* LocalStorage is used only after the user explicitly saves/resumes a Draft. */
        if (state.draftId) saveLocalDraft(false);
    }

    function scheduleLocalSave() {
        if (state.loading) return;
        clearTimeout(state.persistenceTimer);
        byId("localSaveStatus").textContent = state.draftId ? "Saving draft…" : "Protecting changes…";
        state.persistenceTimer = setTimeout(saveWorkingState, 500);
    }

    function removeCurrentDraft() {
        if (!state.draftId) return;
        writeDrafts(readDrafts().filter(function (row) { return row.draft_id !== state.draftId; }));
        state.draftId = "";
        state._draftCreatedAt = "";
        byId("localSaveStatus").textContent = "";
    }

    function updateDraftCount() {
        if (!state.storageKey) return;
        byId("draftCount").textContent = String(readDrafts().length);
    }

    function renderDraftList() {
        var target = byId("draftList");
        var rows = readDrafts();
        if (!rows.length) {
            target.innerHTML = '<div class="draft-empty">No local Sales drafts in this browser.</div>';
            return;
        }
        target.innerHTML = rows.map(function (draft) {
            var customer = state.customerMap[Number(draft.customer_id)] || null;
            var customerName = customer ? customer.customer_name : (draft.customer_id ? "Customer #" + draft.customer_id : "Customer not selected");
            var type = Number(draft.target_document_type || draft.current_document_type || 0);
            var doc = type ? DOC_LABELS[type] : "New Sale";
            var updated = draft.updated_at ? new Date(draft.updated_at).toLocaleString() : "";
            return '<div class="draft-card">' +
                '<div><h3>' + escapeHtml(customerName) + '</h3><div class="draft-meta"><span>' + escapeHtml(doc) + '</span><span>' + money(draft.grand_total || 0) + '</span><span>' + escapeHtml(updated) + '</span>' + (Number(draft.tax_mode) === 0 ? '<span>NON GST</span>' : '') + '</div></div>' +
                '<div class="draft-card-actions"><button class="btn btn-primary js-resume-draft" data-draft-id="' + escapeHtml(draft.draft_id) + '" type="button">Resume</button><button class="btn gray js-delete-draft" data-draft-id="' + escapeHtml(draft.draft_id) + '" type="button">Delete</button></div>' +
                '</div>';
        }).join("");
    }

    async function restoreSnapshot(snapshot, isDraft) {
        if (!snapshot) return;

        if (isDraft) {
            state.draftId = snapshot.draft_id || makeDraftId();
            state._draftCreatedAt = snapshot.created_at || new Date().toISOString();
        } else if (snapshot.draft_id && readDrafts().some(function (row) { return row.draft_id === snapshot.draft_id; })) {
            state.draftId = snapshot.draft_id;
            state._draftCreatedAt = snapshot.created_at || "";
        }

        state.taxMode = Number(snapshot.tax_mode) === 0 ? 0 : 1;
        state.roundOffEnabled = Number(snapshot.round_off_enabled || 0) === 1 ? 1 : 0;

        byId("salesDate").value = snapshot.sales_date || today();
        byId("dueDate").value = snapshot.due_date || "";
        byId("customerReference").value = snapshot.customer_reference || "";
        byId("overallDiscountType").value = String(Number(snapshot.overall_discount_type || 1));
        byId("overallDiscountValue").value = numberValue(snapshot.overall_discount_value) > 0
            ? numberValue(snapshot.overall_discount_value).toFixed(2)
            : "";
        byId("notes").value = snapshot.notes || "";

        setMasterOptions(snapshot.customer_id || "", "");
        state.items = (snapshot.items || []).map(function (item) {
            var product = state.productMap[Number(item.product_id)] || null;
            var conversion = conversionFor(item, item.primary_unit_id || (product ? product.primary_unit_id : 0), item.saved_conversion_rate) || conversionFor(product, product ? product.primary_unit_id : 0, item.saved_conversion_rate);
            var legacyPrimaryRate = numberValue(item.unit_price);
            if (item.primary_quantity === undefined && item.secondary_quantity === undefined) {
                var legacyQty = numberValue(item.quantity);
                if (product && product.secondary_unit_id && Number(item.selected_unit_id) === Number(product.secondary_unit_id)) {
                    item.primary_quantity = 0;
                    item.secondary_quantity = legacyQty;
                    legacyPrimaryRate = round2(numberValue(item.unit_price) * conversion);
                } else {
                    item.primary_quantity = legacyQty;
                    item.secondary_quantity = 0;
                }
            }
            item.unit_price = legacyPrimaryRate;
            item.seller_type_id = Number(item.seller_type_id || 0) > 0 ? Number(item.seller_type_id) : null;
            item.seller_type_name = String(item.seller_type_name || "");
            item.primary_unit_id = Number(item.primary_unit_id || (product ? product.primary_unit_id : 0));
            item.secondary_unit_id = Number(item.secondary_unit_id || 0) > 0 ? Number(item.secondary_unit_id) : null;
            item.selected_unit_id = item.primary_unit_id;
            item._available_base = 0;
            item._base_cost = null;
            return item;
        });

        await loadBatchesForItems(true);
        renderItems();
        fillPaymentDraft(snapshot.payments || []);
        byId("nonGstBadge").hidden = state.taxMode !== 0;
        recalculateAll();
        updateDocumentButtons();
        updateCurrentDocumentLabel();
        updatePlaceOfSupply();
    }

    async function restoreDraft(draft) {
        await restoreSnapshot(draft, true);
        closeModal("draftsModal");
        saveWorkingState();
        showSuccess("Local draft resumed and current stock was rechecked.");
    }

    async function restoreWorkingState() {
        var working = readWorkingState();
        if (!working) return false;
        await restoreSnapshot(working, false);
        byId("localSaveStatus").textContent = state.draftId
            ? "Draft restored ✓"
            : "Unsaved changes restored after refresh ✓";
        return true;
    }

    function fillPaymentDraft(payments) {
        var byMode = {};
        (payments || []).forEach(function (p) { byMode[Number(p.payment_mode)] = p; });
        paymentRows().forEach(function (row) {
            var mode = Number(row.getAttribute("data-payment-mode"));
            var p = byMode[mode] || {};
            var accountInput = row.querySelector(".pay-account");
            var amountInput = row.querySelector(".pay-amount");
            var referenceInput = row.querySelector(".pay-reference");
            var dateInput = row.querySelector(".pay-date");

            if (accountInput) choosePaymentAccount(accountInput, p.account_id, mode);
            if (amountInput) amountInput.value = numberValue(p.amount) > 0 ? numberValue(p.amount).toFixed(2) : "";
            if (referenceInput) referenceInput.value = p.reference_no || p.cheque_no || "";
            if (dateInput) dateInput.value = p.payment_date || p.cheque_date || "";
        });
    }

    function openModal(id) {
        byId(id).hidden = false;
        document.body.style.overflow = "hidden";
        refreshIcons();
    }

    function closeModal(id) {
        byId(id).hidden = true;
        if (!all(".pos-modal:not([hidden])").length) document.body.style.overflow = "";
    }

    function resetNewSale() {
        state.ref = "";
        state.targetRef = "";
        state.targetDocumentType = 0;
        state.draftId = "";
        state._draftCreatedAt = "";
        state.batchCache = {};
        applyDefaultNewSale();
        clearProductEntry();
        renderItems();
        recalculateAll();
        byId("localSaveStatus").textContent = "";
    }

    async function clearOrReset() {
        if (state.ref) {
            if (!window.confirm("Discard unsaved changes and reload the saved document?")) return;
            clearWorkingState(true);
            window.location.reload();
            return;
        }

        if (hasMeaningfulData() && !window.confirm("Clear all entered Sales data?")) return;
        clearWorkingState(true);
        removeCurrentDraft();
        resetNewSale();
        showSuccess("Sales form cleared.");
    }

    function validateBeforeDocument(targetType) {
        if (!Number(byId("customerId").value || 0)) { showWarning("Customer is required."); return false; }
        if (!byId("salesDate").value) { showWarning("Sales Date is required."); return false; }
        if (byId("dueDate").value && byId("dueDate").value < byId("salesDate").value) { showWarning("Due Date cannot be before Sales Date."); return false; }
        if (!state.items.length) { showWarning("Add at least one Product."); return false; }
        for (var i = 0; i < state.items.length; i += 1) {
            var item = state.items[i];
            if (!item.product_id || !item.source_purchase_id || (numberValue(item.primary_quantity) <= 0 && numberValue(item.secondary_quantity) <= 0)) {
                showWarning("Complete Product, Batch and enter Primary Qty or Secondary Qty.");
                return false;
            }
            if (duplicateProduct(item.product_id, i)) { showWarning("Duplicate Product is not allowed."); return false; }
        }
        if (Number(targetType) === 4) {
            var newReceipt = 0;
            readPayments().forEach(function (row) { newReceipt += numberValue(row.amount); });
            newReceipt = round2(newReceipt);
            var remaining = Math.max(0, round2(state.totals.grandTotal - numberValue(state.externalAllocated)));
            if (newReceipt > remaining + 0.001) {
                showWarning("New payment amount cannot exceed the current Invoice balance of " + money(remaining) + ".");
                return false;
            }
        }
        return true;
    }

    function targetReferenceFor(targetType) {
        targetType = Number(targetType);
        if (state.targetRef && Number(state.targetDocumentType) === targetType) return state.targetRef;
        return state.targetRefs && state.targetRefs[String(targetType)]
            ? state.targetRefs[String(targetType)]
            : "";
    }

    function payloadFor(targetType) {
        return {
            ref: state.ref || undefined,
            target_ref: targetReferenceFor(targetType),
            tax_mode: state.taxMode,
            sales_date: byId("salesDate").value,
            due_date: byId("dueDate").value || null,
            customer_id: Number(byId("customerId").value || 0),
            customer_reference: byId("customerReference").value.trim(),
            overall_discount_type: Number(byId("overallDiscountType").value || 1),
            overall_discount_value: round2(numberValue(byId("overallDiscountValue").value)),
            round_off_enabled: state.roundOffEnabled,
            notes: byId("notes").value.trim(),
            items: state.items.map(function (item) {
                return {
                    item_id: Number(item.item_id || 0),
                    product_id: Number(item.product_id),
                    source_purchase_id: Number(item.source_purchase_id),
                    primary_quantity: round3(numberValue(item.primary_quantity)),
                    secondary_quantity: round3(numberValue(item.secondary_quantity)),
                    seller_type_id: Number(item.seller_type_id || 0) > 0 ? Number(item.seller_type_id) : null,
                    seller_type_name: item.seller_type_name || "",
                    unit_price: round2(numberValue(item.unit_price)),
                    discount_type: Number(item.discount_type || 1),
                    discount_value: round2(numberValue(item.discount_value)),
                    tax_type: Number(item.tax_type || 2)
                };
            }),
            payments: readPayments()
        };
    }

    function ensureDocumentSaveModal() {
        if (byId("documentSaveModal")) return;

        var modal = document.createElement("div");
        modal.className = "pos-modal";
        modal.id = "documentSaveModal";
        modal.hidden = true;

        modal.innerHTML =
            '<div class="pos-modal-backdrop" data-close-modal="documentSaveModal"></div>' +
            '<div class="pos-modal-dialog">' +
                '<div class="pos-modal-head">' +
                    '<div>' +
                        '<h2 id="documentSaveModalTitle">Save Document</h2>' +
                        '<p id="documentSaveModalText">Choose how you want to continue.</p>' +
                    '</div>' +
                    '<button class="icon-button" type="button" data-close-modal="documentSaveModal" title="Close">' +
                        '<i data-lucide="x"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">' +
                        '<button class="btn js-document-save-only" type="button">' +
                            '<i data-lucide="save"></i> Save' +
                        '</button>' +
                        '<button class="btn btn-primary js-document-save-print" type="button">' +
                            '<i data-lucide="printer"></i> Save &amp; Print' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        refreshIcons();
    }

    function openDocumentSaveModal(targetType, button) {
        targetType = Number(targetType);
        if (!targetType || state.saving) return;

        state.pendingDocumentType = targetType;
        state.pendingDocumentButton = button || null;

        ensureDocumentSaveModal();

        var label = DOC_LABELS[targetType] || "Document";
        var title = byId("documentSaveModalTitle");
        var textNode = byId("documentSaveModalText");

        if (title) title.textContent = "Generate " + label;
        if (textNode) {
            textNode.textContent =
                "Save stores the " + label +
                " in the database only. Save & Print stores it and opens the printable document.";
        }

        openModal("documentSaveModal");
    }

    async function saveDocument(targetType, button, printAfterSave) {
        targetType = Number(targetType);
        printAfterSave = !!printAfterSave;

        if (state.saving) return;

        var actionId = documentActionId(targetType);
        if (!hasAction(actionId)) return showWarning("You do not have permission for " + DOC_LABELS[targetType] + ".");

        var isUpdate = !!state.ref;
        if (isUpdate && !hasAction(ACTION_UPDATE)) {
            return showWarning("You do not have permission to update this Sales document.");
        }
        if (!isUpdate && !hasAction(ACTION_CREATE)) {
            return showWarning("You do not have permission to create Sales documents.");
        }

        var targetRef = targetReferenceFor(targetType);
        if (!targetRef) {
            return showWarning("Sales document target is unavailable. Reload the page and try again.");
        }

        if (!validateBeforeDocument(targetType)) return;

        if (targetType === 4) {
            var message;
            if (isUpdate && Number(state.currentDocumentType) === 4) {
                message = "Update this Final Invoice? Stock and customer allocation will be recalculated from the current rows.";
            } else if (isUpdate) {
                message = "Generate Final Invoice from this same Sales record? Stock and customer payment effects will be posted only after successful save.";
            } else {
                message = "Generate Final Invoice? Stock and customer payment effects will be posted only after successful save.";
            }
            if (!window.confirm(message)) return;
        }

        state.saving = true;
        if (button) button.disabled = true;

        try {
            var result = await App.api("api/sales.php", {
                method: isUpdate ? "PUT" : "POST",
                body: payloadFor(targetType)
            });
            var sale = result.data && result.data.sale ? result.data.sale : null;
            if (!sale || !sale.ref) throw new Error("Sales document was saved but the response is incomplete.");

            clearWorkingState(true);
            removeCurrentDraft();
            showSuccess(result.message || (DOC_LABELS[targetType] + " saved."));

            setTimeout(function () {
                if (printAfterSave) {
                    window.location.href = "sales-print.php?ref=" + encodeURIComponent(sale.ref);
                    return;
                }

                window.location.href = "sales.php?ref=" + encodeURIComponent(sale.ref);
            }, 450);
        } catch (error) {
            /* Session/local Draft data is intentionally retained on failure. */
            saveWorkingState();
            reportError(error, "Unable to save Sales document.");
        } finally {
            state.saving = false;
            if (button) button.disabled = false;
        }
    }

    async function showProfit() {
        if (!hasAction(ACTION_VIEW_PROFIT)) return;
        try {
            await loadBatchesForItems(false);
            recalculateAll();
            var costTotal = 0;
            var salesTotal = 0;
            var html = "";
            state.items.forEach(function (item) {
                var product = state.productMap[Number(item.product_id)] || {};
                var batch = findBatch(item.product_id, item.source_purchase_id);
                var baseCost = batch && batch.base_cost !== undefined ? numberValue(batch.base_cost) : numberValue(item._base_cost);
                var cost = round2(baseCost * numberValue(item.base_quantity));
                var sales = round2(numberValue(item.taxable_amount));
                var profit = round2(sales - cost);
                costTotal += cost;
                salesTotal += sales;
                html += '<tr><td>' + escapeHtml(product.product_name || ("Product #" + item.product_id)) + '</td><td>' + money(sales) + '</td><td>' + money(cost) + '</td><td>' + money(profit) + '</td></tr>';
            });
            costTotal = round2(costTotal);
            salesTotal = round2(salesTotal);
            var profitTotal = round2(salesTotal - costTotal);
            var margin = salesTotal > 0 ? profitTotal / salesTotal * 100 : 0;
            byId("profitSales").textContent = money(salesTotal);
            byId("profitCost").textContent = money(costTotal);
            byId("profitAmount").textContent = money(profitTotal);
            byId("profitMargin").textContent = margin.toFixed(2) + "%";
            byId("profitItems").innerHTML = html || '<tr><td colspan="4">No Products.</td></tr>';
            openModal("profitModal");
        } catch (error) {
            reportError(error, "Unable to calculate Profit.");
        }
    }

    async function refreshCustomerOptionsAndSelect(customerCode) {
        var result = await App.api("api/sales.php?customers=1");
        state.options.customers = (result.data && result.data.customers) || [];
        rebuildMaps();
        var found = null;
        state.options.customers.forEach(function (row) { if (String(row.customer_code) === String(customerCode)) found = row; });
        state.customerSelect.setOptions(optionItems(state.options.customers, "id", customerText), found ? found.id : "");
        updatePlaceOfSupply();
    }

    async function saveQuickCustomer(event) {
        event.preventDefault();
        var form = byId("quickCustomerForm");
        var name = byId("quickCustomerName").value.trim();
        var stateCode = effectiveCustomerState({ gstin: byId("quickCustomerGstin").value });
        if (!name) return showWarning("Customer Name is required.");

        var button = byId("quickCustomerSave");
        button.disabled = true;
        try {
            var result = await App.api("api/customers.php", {
                method: "POST",
                body: {
                    customer_name: name,
                    mobile: byId("quickCustomerMobile").value.trim(),
                    state_code: stateCode,
                    gstin: byId("quickCustomerGstin").value.trim(),
                    credit_limit: "0.00",
                    opening_balance: "0.00",
                    status: 1
                }
            });
            await refreshCustomerOptionsAndSelect(result.data.customer_code);
            form.reset();
            closeModal("customerModal");
            showSuccess("Customer created and selected.");
            recalculateAll();
            scheduleLocalSave();
        } catch (error) {
            reportError(error, "Unable to create Customer.");
        } finally {
            button.disabled = false;
        }
    }

    function updateItemFromInput(target) {
        var index = Number(target.getAttribute("data-index"));
        if (!Number.isInteger(index) || !state.items[index]) return;
        var item = state.items[index];
        if (target.classList.contains("js-item-primary-qty")) item.primary_quantity = round3(numberValue(target.value));
        if (target.classList.contains("js-item-secondary-qty")) item.secondary_quantity = round3(numberValue(target.value));
        if (target.classList.contains("js-item-rate")) {
            item.unit_price = round2(numberValue(target.value));
        }
        if (target.classList.contains("js-item-discount")) item.discount_value = round2(numberValue(target.value));
        if (target.classList.contains("js-item-discount-type")) item.discount_type = Number(target.value || 1);
        if (target.classList.contains("js-item-tax-type")) item.tax_type = Number(target.value || 2);
        recalculateAll();
        scheduleLocalSave();
    }

    async function handleItemChange(target) {
        var index = Number(target.getAttribute("data-index"));
        if (!Number.isInteger(index) || !state.items[index]) return;
        var item = state.items[index];

        if (target.classList.contains("js-item-product")) {
            var newProductId = Number(target.value || 0);
            if (newProductId && duplicateProduct(newProductId, index)) {
                showWarning("This Product is already added. Please edit the existing row.");
                renderItems();
                return;
            }
            var product = state.productMap[newProductId] || null;
            if (!product) return;
            item.product_id = newProductId;
            item.source_purchase_id = 0;
            item.selected_unit_id = Number(product.primary_unit_id);
            item.primary_unit_id = Number(product.primary_unit_id);
            item.secondary_unit_id = product.secondary_unit_id ? Number(product.secondary_unit_id) : null;
            item.primary_unit_name = product.primary_unit_name || '';
            item.primary_unit_symbol = product.primary_unit_symbol || '';
            item.secondary_unit_name = product.secondary_unit_name || '';
            item.secondary_unit_symbol = product.secondary_unit_symbol || '';
            item.saved_conversion_rate = null;
            item.primary_quantity = 0;
            item.secondary_quantity = 0;
            var defaultSeller = sellerPriceOptions(product).length ? sellerPriceOptions(product)[0] : null;
            item.seller_type_id = defaultSeller ? Number(defaultSeller.seller_type_id || 0) : null;
            item.seller_type_name = defaultSeller ? String(defaultSeller.seller_type_name || "") : "";
            item.unit_price = sellerPrice(product, item.seller_type_id, item.seller_type_name);
            item.tax_type = Number(product.sale_tax_type) === 1 ? 1 : 2;
            item.snapshot_gst_rate = null;
            item.snapshot_cess_rate = null;
            item._available_base = 0;
            item._base_cost = null;
            try { await loadBatchOptions(newProductId, true); } catch (error) { reportError(error, "Unable to load Batch options."); }
            renderItems();
            recalculateAll();
            scheduleLocalSave();
            return;
        }

        if (target.classList.contains("js-item-batch")) {
            item.source_purchase_id = Number(target.value || 0);
            var batch = findBatch(item.product_id, item.source_purchase_id);
            item._available_base = batch ? numberValue(batch.available_base_qty) : 0;
            item._base_cost = batch && batch.base_cost !== undefined ? numberValue(batch.base_cost) : null;
            item.saved_conversion_rate = batch && numberValue(batch.conversion_rate) > 0 ? numberValue(batch.conversion_rate) : null;
            applyBatchUnitSnapshot(item, batch, state.productMap[Number(item.product_id)] || null);
            recalculateAll();
            renderItems();
            validateItemQuantity(index, true);
            scheduleLocalSave();
            return;
        }

        if (target.classList.contains("js-item-seller")) {
            var selectedSellerId = Number(target.value || 0);
            if (selectedSellerId === -1) return;

            var productForSeller = state.productMap[Number(item.product_id)] || null;
            var selectedSellerRow = sellerPriceRow(productForSeller, selectedSellerId, "");
            item.seller_type_id = selectedSellerRow ? Number(selectedSellerRow.seller_type_id || 0) : null;
            item.seller_type_name = selectedSellerRow ? String(selectedSellerRow.seller_type_name || "") : "";

            var sellerBatch = findBatch(item.product_id, item.source_purchase_id);
            if (productForSeller && sellerBatch && Number(productForSeller.primary_unit_id || 0) === Number(sellerBatch.primary_unit_id || 0)) {
                item.unit_price = sellerPrice(productForSeller, item.seller_type_id, item.seller_type_name);
            } else if (productForSeller && !sellerBatch) {
                item.unit_price = sellerPrice(productForSeller, item.seller_type_id, item.seller_type_name);
            } else {
                showWarning('Product Master unit has changed for this batch. Enter the Primary Rate manually for the batch unit.');
            }
            recalculateAll();
            renderItems();
            scheduleLocalSave();
            return;
        }

        updateItemFromInput(target);
    }

    function bindEvents() {
        document.addEventListener("keydown", function (event) {
            if (event.ctrlKey && event.shiftKey && String(event.key).toLowerCase() === "u") {
                event.preventDefault();
                toggleTaxMode();
            }
        });

        byId("customerId").addEventListener("change", function () { recalculateAll(); scheduleLocalSave(); });
        byId("salesDate").addEventListener("change", function () {
            scheduleLocalSave();
        });
        byId("dueDate").addEventListener("change", scheduleLocalSave);
        byId("customerReference").addEventListener("input", scheduleLocalSave);
        byId("notes").addEventListener("input", scheduleLocalSave);

        byId("entryProduct").addEventListener("change", prepareEntryProduct);
        byId("entryBatch").addEventListener("change", updateEntryStock);
        byId("entrySeller").addEventListener("change", function () {
            var productId = Number(byId("entryProduct").value || 0);
            var product = state.productMap[productId] || null;
            var batch = findBatch(productId, Number(byId('entryBatch').value || 0));
            if (product && batch && Number(product.primary_unit_id || 0) !== Number(batch.primary_unit_id || 0)) {
                byId('entryRate').value = '';
                showWarning('Product Master unit has changed for this batch. Enter the Primary Rate manually for the batch unit.');
                return;
            }
            var sellerTypeId = Number(byId("entrySeller").value || 0);
            var row = sellerPriceRow(product, sellerTypeId, "");
            byId("entryRate").value = sellerPrice(
                product,
                row ? Number(row.seller_type_id || 0) : 0,
                row ? row.seller_type_name : ""
            ).toFixed(2);
        });
        byId("entryPrimaryQty").addEventListener("change", function () { validateEntryQuantity(true); });
        byId("entrySecondaryQty").addEventListener("change", function () { validateEntryQuantity(true); });
        byId("entryPrimaryQty").addEventListener("keydown", function (event) { if (event.key === "Enter") { event.preventDefault(); addProductFromEntry(); } });
        byId("entrySecondaryQty").addEventListener("keydown", function (event) { if (event.key === "Enter") { event.preventDefault(); addProductFromEntry(); } });
        byId("addProductButton").addEventListener("click", addProductFromEntry);

        byId("overallDiscountType").addEventListener("change", function () { recalculateAll(); scheduleLocalSave(); });
        byId("overallDiscountValue").addEventListener("input", function () { recalculateAll(); scheduleLocalSave(); });
        byId("roundOffButton").addEventListener("click", function () { state.roundOffEnabled = state.roundOffEnabled ? 0 : 1; recalculateAll(); scheduleLocalSave(); });

        paymentRows().forEach(function (row) {
            all("input,select", row).forEach(function (input) {
                input.addEventListener(input.tagName === "INPUT" ? "input" : "change", function () { updatePaymentSummary(); scheduleLocalSave(); });
            });
        });

        var itemsCard = document.querySelector(".items-card");
        itemsCard.addEventListener("input", function (event) {
            var target = event.target;
            if (target.classList.contains("js-item-primary-qty") || target.classList.contains("js-item-secondary-qty") || target.classList.contains("js-item-rate") || target.classList.contains("js-item-discount")) {
                updateItemFromInput(target);
            }
        });
        itemsCard.addEventListener("change", function (event) {
            var target = event.target;
            if (target.classList.contains("js-item-primary-qty") || target.classList.contains("js-item-secondary-qty")) {
                updateItemFromInput(target);
                validateItemQuantity(Number(target.getAttribute("data-index")), true);
                return;
            }
            if (target.classList.contains("js-item-product") || target.classList.contains("js-item-batch") || target.classList.contains("js-item-seller") || target.classList.contains("js-item-discount-type") || target.classList.contains("js-item-tax-type")) {
                handleItemChange(target);
            }
        });
        itemsCard.addEventListener("click", function (event) {
            var button = event.target.closest(".js-remove-item");
            if (!button) return;
            var index = Number(button.getAttribute("data-index"));
            if (!state.items[index]) return;
            state.items.splice(index, 1);
            renderItems();
            recalculateAll();
            scheduleLocalSave();
        });

        byId("saveDraftButton").addEventListener("click", function () { saveLocalDraft(true); });
        byId("clearDraftButton").addEventListener("click", clearOrReset);
        byId("draftsButton").addEventListener("click", function () { renderDraftList(); openModal("draftsModal"); });
        byId("profitButton").addEventListener("click", showProfit);
        byId("exitButton").addEventListener("click", function () {
            if (hasMeaningfulData() && !state.draftId) {
                if (!window.confirm("Exit Sales POS? Unsaved data will be discarded. Use Save Draft if you want to keep it.")) return;
            }
            clearWorkingState(true);
            window.location.href = "sales-list.php";
        });

        all(".doc-action").forEach(function (button) {
            button.addEventListener("click", function () {
                openDocumentSaveModal(
                    Number(button.getAttribute("data-document-type")),
                    button
                );
            });
        });

        document.addEventListener("click", function (event) {
            var saveOnly = event.target.closest(".js-document-save-only");
            if (saveOnly) {
                var saveType = Number(state.pendingDocumentType || 0);
                var saveButton = state.pendingDocumentButton;

                closeModal("documentSaveModal");

                state.pendingDocumentType = 0;
                state.pendingDocumentButton = null;

                if (saveType) {
                    saveDocument(saveType, saveButton, false);
                }
                return;
            }

            var savePrint = event.target.closest(".js-document-save-print");
            if (savePrint) {
                var printType = Number(state.pendingDocumentType || 0);
                var printButton = state.pendingDocumentButton;

                closeModal("documentSaveModal");

                state.pendingDocumentType = 0;
                state.pendingDocumentButton = null;

                if (printType) {
                    saveDocument(printType, printButton, true);
                }
                return;
            }

            var close = event.target.closest("[data-close-modal]");
            if (close) closeModal(close.getAttribute("data-close-modal"));
            var resume = event.target.closest(".js-resume-draft");
            if (resume) {
                var draftId = resume.getAttribute("data-draft-id");
                var draft = readDrafts().find(function (row) { return row.draft_id === draftId; });
                if (!draft) return;
                var draftRef = draft.ref || "";
                var draftTargetRef = draft.target_ref || "";
                if (draftRef !== state.ref || draftTargetRef !== state.targetRef) {
                    var resumeUrl = "sales.php";
                    var resumeParams = new URLSearchParams();
                    if (draftRef) resumeParams.set("ref", draftRef);
                    if (draftTargetRef) resumeParams.set("target", draftTargetRef);
                    resumeParams.set("local_draft", draftId);
                    window.location.href = resumeUrl + "?" + resumeParams.toString();
                    return;
                }
                restoreDraft(draft).catch(function (error) { reportError(error, "Unable to restore local draft."); });
            }
            var remove = event.target.closest(".js-delete-draft");
            if (remove) {
                var removeId = remove.getAttribute("data-draft-id");
                if (!window.confirm("Delete this local draft?")) return;
                writeDrafts(readDrafts().filter(function (row) { return row.draft_id !== removeId; }));
                if (state.draftId === removeId) state.draftId = "";
                renderDraftList();
            }
        });

        window.addEventListener("beforeunload", function () {
            if (!state.saving) saveWorkingState();
        });

        var quickState = byId("quickCustomerState");
        if (quickState) {
            quickState.required = false;
            var stateField = quickState.closest(".field");
            if (stateField) stateField.hidden = true;
        }
        byId("addCustomerButton").addEventListener("click", function () {
            if (quickState) quickState.value = branchStateCode();
            openModal("customerModal");
        });
        byId("quickCustomerForm").addEventListener("submit", saveQuickCustomer);
    }

    async function init() {
        bindEvents();
        try {
            var result;
            if (state.ref) {
                var loadUrl = "api/sales.php?ref=" + encodeURIComponent(state.ref);
                if (state.targetRef) loadUrl += "&target=" + encodeURIComponent(state.targetRef);
                result = await App.api(loadUrl);
            } else {
                result = await App.api("api/sales.php?options=1");
            }

            var data = result.data || {};
            state.actions = data.allowed_actions || [];
            state.customerFormActions = data.customer_form_actions || [];
            state.currentUserId = Number(data.current_user_id || 0);
            state.branch = data.branch || null;
            state.options = data.options || { customers: [], products: [], accounts: [] };
            state.targetRefs = data.target_refs || {};
            state.targetRef = data.target_ref || state.targetRef || "";
            state.targetDocumentType = Number(data.target_document_type || 0);

            rebuildMaps();
            setStorageKey();

            if (state.ref && data.sale) {
                applySaleRecord(data.sale);
                await loadBatchesForItems(false);
                renderItems();
                recalculateAll();
            } else {
                applyDefaultNewSale();
                renderItems();
                recalculateAll();
            }

            updateDraftCount();
            state.loading = false;

            var localDraftId = urlParams.get("local_draft") || "";
            if (localDraftId) {
                var draft = readDrafts().find(function (row) { return row.draft_id === localDraftId; });
                if (draft) await restoreDraft(draft);
            } else {
                await restoreWorkingState();
            }

            updateDocumentButtons();
            updateCurrentDocumentLabel();
            updatePlaceOfSupply();

            if (state.printMode) {
                document.body.classList.add("sales-pos-print-mode");
                all("input,select,textarea,button").forEach(function (control) {
                    if (control.id === "exitButton") return;
                    control.disabled = true;
                });
                setTimeout(function () { window.print(); }, 250);
            }

            refreshIcons();
        } catch (error) {
            state.loading = false;
            reportError(error, "Unable to load Sales POS.");
            all("button,input,select,textarea").forEach(function (control) {
                if (!control.hasAttribute("data-close-modal")) control.disabled = true;
            });
        }
    }

    init();
})(window, document);
</script>

        <script>if(window.lucide){window.lucide.createIcons();}</script>

        <script src="assets/js/appearance.js"></script>

        </body>

        </html>
