<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Purchase Form';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

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
                    <h1 id="pageHeading">Add Purchase</h1>
                    <p>Keyboard-first purchase entry with direct editable items, overall discount, compact payment allocation and stock posting.</p>
                </div>

                <a class="btn gray" href="purchase-list.php">
                    <i data-lucide="list"></i>
                    Purchase List
                </a>
            </div>

            <div class="card form-card" id="purchaseCard">
                <form id="purchaseForm" novalidate>
                    <div class="card-header">
                        <div>
                            <h2>Purchase Information</h2>
                            <p id="formModeText">Save as Draft or Post directly. Purchase status is controlled by the action button.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- =====================================================
                             PURCHASE INFORMATION
                             ===================================================== -->
                        <div class="form-row">
                            <div class="field col-2">
                                <label for="purchaseNo">Purchase No</label>
                                <input
                                    id="purchaseNo"
                                    type="text"
                                    readonly
                                    tabindex="-1"
                                    aria-readonly="true"
                                    placeholder="Auto generated">
                            </div>

                            <div class="field col-2">
                                <label for="purchaseDate" class="required">Purchase Date</label>
                                <input
                                    id="purchaseDate"
                                    name="purchase_date"
                                    type="date"
                                    required
                                    data-required-message="Purchase Date is required.">
                            </div>

                            <div class="field col-2">
                                <label for="batchNumber" class="required">Batch No</label>
                                <input
                                    id="batchNumber"
                                    name="batch_number"
                                    type="text"
                                    maxlength="100"
                                    readonly
                                    tabindex="-1"
                                    aria-readonly="true"
                                    placeholder="Auto generated">
                            </div>

                            <div class="field col-3">
                                <label for="supplierId" class="required">Supplier</label>
                                <div class="input-group">
                                    <div class="input-group-control">
                                        <select
                                            id="supplierId"
                                            name="supplier_id"
                                            required
                                            data-placeholder="Select or type Supplier"
                                            data-required-message="Supplier is required.">
                                            <option value="">Select Supplier</option>
                                        </select>
                                    </div>
                                    <button
                                        class="btn btn-primary input-group-button"
                                        id="addSupplierButton"
                                        type="button"
                                        title="Add Supplier"
                                        aria-label="Add Supplier">
                                        <i data-lucide="plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="field col-3">
                                <label for="supplierInvoiceNo">Supplier Invoice No</label>
                                <input
                                    id="supplierInvoiceNo"
                                    name="supplier_invoice_number"
                                    type="text"
                                    maxlength="100"
                                    autocomplete="off"
                                    placeholder="Invoice number">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-2">
                                <label for="supplierInvoiceDate">Invoice Date</label>
                                <input
                                    id="supplierInvoiceDate"
                                    name="supplier_invoice_date"
                                    type="date">
                            </div>

                            <div class="field col-4">
                                <label for="placeOfSupply">Place of Supply</label>
                                <input
                                    id="placeOfSupply"
                                    type="text"
                                    readonly
                                    tabindex="-1"
                                    aria-readonly="true"
                                    value="Select Supplier">
                            </div>

                            <div class="field col-6" id="taxInfoField">
                                <label>Tax Information</label>
                                <div class="muted" id="taxInfo">
                                    GST is calculated automatically from available State Codes or Supplier GSTIN and Product HSN Master.
                                </div>
                            </div>
                        </div>

                        <!-- =====================================================
                             ADD PRODUCT - SINGLE ROW
                             ===================================================== -->
                        <div class="card-section-title">Add Product</div>

                        <div class="app-entry-grid" id="itemEntry">
                            <div class="field field-main">
                                <label for="entryProduct" class="required">Product</label>
                                <select id="entryProduct" data-placeholder="Select or type Product">
                                    <option value="">Select Product</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="entryUnit" class="required">Unit</label>
                                <select id="entryUnit">
                                    <option value="">Unit</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="entryQty" class="required">Qty</label>
                                <input id="entryQty" type="text" inputmode="decimal" placeholder="0.000">
                            </div>

                            <div class="field">
                                <label for="entryFreeQty">Free Qty</label>
                                <input id="entryFreeQty" type="text" inputmode="decimal" placeholder="0.000">
                            </div>

                            <div class="field">
                                <label for="entryRate" class="required">Rate</label>
                                <input id="entryRate" type="text" inputmode="decimal" placeholder="0.00">
                            </div>

                            <div class="field">
                                <label for="entryDiscountType">Disc Type</label>
                                <select id="entryDiscountType">
                                    <option value="1">%</option>
                                    <option value="2">Fixed</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="entryDiscount">Discount</label>
                                <input id="entryDiscount" type="text" inputmode="decimal" placeholder="0.00">
                            </div>

                            <div class="field">
                                <label for="entryTaxType">Tax Type</label>
                                <select id="entryTaxType">
                                    <option value="2">Exclusive</option>
                                    <option value="1">Inclusive</option>
                                </select>
                            </div>

                            <div class="field" id="entryExpiryField" hidden>
                                <label for="entryExpiry" class="required">Expiry</label>
                                <input id="entryExpiry" type="date" disabled>
                            </div>

                            <button
                                class="btn btn-primary app-entry-action"
                                id="addItemButton"
                                type="button"
                                title="Add Product"
                                aria-label="Add Product">
                                <i data-lucide="plus"></i>
                            </button>
                        </div>

                        <div class="muted" id="entryProductInfo">
                            Select a Product. Expiry appears only when enabled in Product Master. Batch Number applies to the complete Purchase.
                        </div>

                        <!-- =====================================================
                             DIRECT EDITABLE PURCHASE ITEMS
                             ===================================================== -->
                        <div class="card-section-title">Purchase Items — Direct Editable</div>

                        <div class="card table-card app-allocation-wrap">
                            <table class="data-table app-editable-table" id="itemsTable">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Qty</th>
                                    <th>Free Qty</th>
                                    <th>Rate</th>
                                    <th>Disc Type</th>
                                    <th>Discount</th>
                                    <th>Tax Type</th>
                                    <th>Expiry</th>
                                    <th>Tax</th>
                                    <th>Amount</th>
                                    <th>×</th>
                                </tr>
                                </thead>
                                <tbody id="itemsBody">
                                <tr id="emptyItemsRow">
                                    <td colspan="13" class="empty">No Products added.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- =====================================================
                             DISCOUNT + PAYMENT LEFT / SUMMARY RIGHT
                             ===================================================== -->
                        <div class="card-section-title">Discount, Payment & Summary</div>

                        <div class="app-split-grid">
                            <!-- LEFT SIDE -->
                            <div class="app-side-card">
                                <div class="app-side-card-head">Discount & Payment</div>

                                <div class="app-side-card-body">
                                    <div class="app-inline-grid">
                                        <div class="field">
                                            <label for="overallDiscountType">Overall Discount Type</label>
                                            <select id="overallDiscountType">
                                                <option value="1">Percentage (%)</option>
                                                <option value="2">Fixed</option>
                                            </select>
                                        </div>

                                        <div class="field">
                                            <label for="overallDiscountValue">Overall Discount</label>
                                            <input
                                                id="overallDiscountValue"
                                                type="text"
                                                inputmode="decimal"
                                                placeholder="0.00">
                                        </div>
                                    </div>

                                    <div class="card-section-title">Payment Allocation</div>

                                    <div class="card table-card app-allocation-wrap">
                                        <table class="app-allocation-table" id="paymentAllocationTable">
                                            <thead>
                                            <tr>
                                                <th class="col-mode">Mode</th>
                                                <th class="col-account">Account</th>
                                                <th class="col-amount">Amount</th>
                                                <th class="col-reference">Reference No</th>
                                                <th class="col-date">Date</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td class="app-allocation-mode">Cash</td>
                                                <td>
                                                    <select
                                                        id="cashAccountId"
                                                        class="payment-account"
                                                        data-mode="1"
                                                        data-placeholder="Cash Account">
                                                        <option value="">Select Cash Account</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-amount"
                                                        data-mode="1"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="0.00">
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-reference"
                                                        data-mode="1"
                                                        type="text"
                                                        maxlength="150"
                                                        placeholder="Optional">
                                                </td>
                                                <td class="muted">—</td>
                                            </tr>

                                            <tr>
                                                <td class="app-allocation-mode">UPI</td>
                                                <td>
                                                    <select
                                                        id="upiAccountId"
                                                        class="payment-account"
                                                        data-mode="2"
                                                        data-placeholder="Bank Account">
                                                        <option value="">Select Bank Account</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-amount"
                                                        data-mode="2"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="0.00">
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-reference"
                                                        data-mode="2"
                                                        type="text"
                                                        maxlength="150"
                                                        placeholder="Optional UTR / Ref No">
                                                </td>
                                                <td class="muted">—</td>
                                            </tr>

                                            <tr>
                                                <td class="app-allocation-mode">Bank</td>
                                                <td>
                                                    <select
                                                        id="bankAccountId"
                                                        class="payment-account"
                                                        data-mode="3"
                                                        data-placeholder="Bank Account">
                                                        <option value="">Select Bank Account</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-amount"
                                                        data-mode="3"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="0.00">
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-reference"
                                                        data-mode="3"
                                                        type="text"
                                                        maxlength="150"
                                                        placeholder="Optional Reference No">
                                                </td>
                                                <td class="muted">—</td>
                                            </tr>

                                            <tr>
                                                <td class="app-allocation-mode">Cheque</td>
                                                <td>
                                                    <select
                                                        id="chequeAccountId"
                                                        class="payment-account"
                                                        data-mode="4"
                                                        data-placeholder="Bank Account">
                                                        <option value="">Select Bank Account</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-amount"
                                                        data-mode="4"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="0.00">
                                                </td>
                                                <td>
                                                    <input
                                                        class="payment-reference"
                                                        data-mode="4"
                                                        type="text"
                                                        maxlength="100"
                                                        placeholder="Optional Cheque No">
                                                </td>
                                                <td>
                                                    <input
                                                        id="chequeDate"
                                                        class="payment-cheque-date"
                                                        type="date">
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="app-total-line">
                                        <span>Paid Now</span>
                                        <strong id="paidNow">₹0.00</strong>
                                    </div>

                                    <div class="app-total-line">
                                        <span>Balance</span>
                                        <strong id="balanceAmount">₹0.00</strong>
                                        <div id="settlementSummary" hidden>Later supplier payments: <strong id="laterPaid"></strong><br>Purchase returns: <strong id="returnedAmount"></strong></div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT SIDE -->
                            <div class="app-side-card">
                                <div class="app-side-card-head">Purchase Summary</div>

                                <div class="app-side-card-body">
                                    <div class="app-summary-row">
                                        <span>Gross</span>
                                        <strong id="sumGross">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>Item Discount</span>
                                        <strong id="sumItemDiscount">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>Overall Discount</span>
                                        <strong id="sumOverallDiscount">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>Taxable</span>
                                        <strong id="sumTaxable">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>CGST</span>
                                        <strong id="sumCgst">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>SGST</span>
                                        <strong id="sumSgst">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>IGST</span>
                                        <strong id="sumIgst">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>Cess</span>
                                        <strong id="sumCess">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row">
                                        <span>Round Off</span>
                                        <strong id="sumRoundOff">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-row total">
                                        <span>Grand Total</span>
                                        <strong id="sumGrand">₹0.00</strong>
                                    </div>

                                    <div class="app-summary-actions">
                                        <button class="btn gray" id="roundButton" type="button">Round Off</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- =====================================================
                             REMARKS
                             ===================================================== -->
                        <div class="card-section-title">Remarks</div>

                        <div class="form-row">
                            <div class="field col-12">
                                <label for="notes">Remarks</label>
                                <textarea
                                    id="notes"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Enter remarks"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="buttons">
                            <a class="btn gray" href="purchase-list.php">Cancel</a>

                            <button class="btn gray" id="saveDraftButton" type="button">
                                <i data-lucide="save"></i>
                                Save Draft
                            </button>

                            <button class="btn btn-primary" id="savePostButton" type="button">
                                <i data-lucide="check-circle"></i>
                                Save & Post
                            </button>
                        </div>
                    </div>
                </form>
            </div>

<?php require __DIR__ . '/modal/supplier.php'; ?>

<script>
(function (window, document) {
    'use strict';

    var form = document.getElementById('purchaseForm');
    var card = document.getElementById('purchaseCard');
    var body = document.getElementById('itemsBody');

    var reference = new URLSearchParams(location.search).get('ref') || '';
    var isEdit = reference !== '';
    var isPosted = false;
    var postedPurchase = null;
    var actions = [];

    var suppliers = [];
    var products = [];
    var accounts = [];

    var supplierMap = {};
    var productMap = {};
    var accountMap = {};

    var rows = [];
    var roundEnabled = false;
    var branch = {};
    var savedSupplyType = '';

    var supplierSelect = GlobalSelect.init('#supplierId', {
        placeholder: 'Select or type Supplier'
    });

    var productSelect = GlobalSelect.init('#entryProduct', {
        placeholder: 'Select or type Product'
    });

    var accountSelects = {
        1: GlobalSelect.init('#cashAccountId', { placeholder: 'Select Cash Account' }),
        2: GlobalSelect.init('#upiAccountId', { placeholder: 'Select Bank Account' }),
        3: GlobalSelect.init('#bankAccountId', { placeholder: 'Select Bank Account' }),
        4: GlobalSelect.init('#chequeAccountId', { placeholder: 'Select Bank Account' })
    };

    function has(actionId) {
        return actions.indexOf(Number(actionId)) !== -1;
    }

    function n(value) {
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function r2(value) {
        return Math.round((n(value) + Number.EPSILON) * 100) / 100;
    }


    function r3(value) {
        return Math.round((n(value) + Number.EPSILON) * 1000) / 1000;
    }

    function unitConversion(source) {
        var conversion = n(source && (source.conversion_rate !== undefined
            ? source.conversion_rate
            : source.secondary_conversion));
        return conversion > 0 ? conversion : 1;
    }

    function isSecondaryUnit(source, unitId) {
        return Number(source && source.secondary_unit_id || 0) > 0 &&
            Number(unitId || 0) === Number(source.secondary_unit_id || 0);
    }

    function primaryRateFromUnit(rate, source, unitId) {
        rate = n(rate);
        return isSecondaryUnit(source, unitId)
            ? rate * unitConversion(source)
            : rate;
    }

    function rateForUnitFromPrimary(primaryRate, source, unitId) {
        primaryRate = n(primaryRate);
        return isSecondaryUnit(source, unitId)
            ? primaryRate / unitConversion(source)
            : primaryRate;
    }

    function convertRateBetweenUnits(rate, source, fromUnitId, toUnitId) {
        return rateForUnitFromPrimary(
            primaryRateFromUnit(rate, source, fromUnitId),
            source,
            toUnitId
        );
    }

    function formatRateInput(value) {
        value = n(value);
        if (Math.abs(value - r2(value)) < 0.0000005) return value.toFixed(2);
        return value.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
    }

    function money(value) {
        return '₹' + n(value).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function esc(value) {
        return App.escapeHtml(String(value == null ? '' : value));
    }

    function option(items, key, textCallback) {
        return (items || []).map(function (item) {
            return {
                value: item[key],
                text: textCallback(item)
            };
        });
    }

    function selectedSupplier() {
        return supplierMap[String(form.supplier_id.value || '')] || null;
    }

    function supplierStateCode(supplier) {
        if (!supplier) return '';
        var recorded = String(supplier.state_code || '').trim();
        if (recorded) return recorded;
        var gstin = String(supplier.gstin || '').trim().toUpperCase();
        var matched = gstin.match(/^([0-9]{2})[A-Z0-9]{13}$/);
        return matched ? matched[1] : '';
    }

    function supplyType() {
        var supplier = selectedSupplier();
        var branchState = String(branch.state_code || '').trim();
        var supplierState = supplierStateCode(supplier);
        if (branchState && supplierState) {
            return branchState === supplierState ? 'intra_state' : 'inter_state';
        }
        // Preserve the original GST classification of an existing purchase.
        if (savedSupplyType === 'intra_state' || savedSupplyType === 'inter_state') {
            return savedSupplyType;
        }
        // Non-blocking fallback: no State Code or Tax Treatment input is needed.
        // This assumption is shown to the operator and should be checked
        // against the supplier invoice before posting.
        return 'intra_state';
    }

    function updateSupply() {
        var supplier = selectedSupplier();
        var type = supplyType();
        var branchState = String(branch.state_code || '').trim();
        var supplierState = supplierStateCode(supplier);
        var place = document.getElementById('placeOfSupply');
        var taxInfo = document.getElementById('taxInfo');

        if (!supplier) {
            place.value = 'Select Supplier';
            taxInfo.textContent = 'GST is automatically calculated using available State Codes or Supplier GSTIN.';
            return;
        }

        place.value = supplierState
            ? 'Supplier State ' + supplierState
            : 'Supplier state not recorded';
        place.value += ' · ' + (type === 'intra_state' ? 'Same State' : 'Different State');

        if (!branchState || !supplierState) {
            taxInfo.textContent = savedSupplyType
                ? 'Saved GST classification retained. Verify the supplier invoice before posting.'
                : 'Supplier/Branch state unavailable: Same State is assumed automatically (CGST + SGST). Verify the supplier invoice before posting.';
        } else if (type === 'intra_state') {
            taxInfo.textContent = 'Same state purchase: CGST + SGST from Product HSN Master.';
        } else {
            taxInfo.textContent = 'Different state purchase: IGST from Product HSN Master.';
        }
    }

    function rebuildMaps() {
        supplierMap = {};
        suppliers.forEach(function (item) {
            supplierMap[String(item.id)] = item;
        });

        productMap = {};
        products.forEach(function (item) {
            productMap[String(item.id)] = item;
        });

        accountMap = {};
        accounts.forEach(function (item) {
            accountMap[String(item.id)] = item;
        });
    }

    function accountOptions(mode) {
        var wantedType = Number(mode) === 1 ? 1 : 2;

        return option(
            accounts.filter(function (account) {
                return Number(account.account_type) === wantedType;
            }),
            'id',
            function (account) {
                return (
                    (account.account_code ? account.account_code + ' - ' : '') +
                    account.account_name +
                    (Number(account.status) === 0 ? ' (Inactive)' : '')
                );
            }
        );
    }

    function defaultAccountId(mode) {
        var type = Number(mode) === 1 ? 1 : 2;
        var active = accounts.filter(function (account) {
            return Number(account.account_type) === type && Number(account.status) === 1;
        });
        if (!active.length) return '';
        if (Number(mode) === 1) {
            var cash = active.find(function (account) { return Number(account.is_default_cash) === 1; });
            if (cash) return String(cash.id);
        }
        if (Number(mode) === 2) {
            var upi = active.find(function (account) { return String(account.upi_id || '').trim() !== ''; });
            if (upi) return String(upi.id);
        }
        return String(active[0].id);
    }

    function setAccountOptions(mode, selectedValue) {
        var control = accountSelects[mode];
        if (!control) return;
        var chosen = selectedValue ? String(selectedValue) : defaultAccountId(mode);
        control.setOptions(accountOptions(mode), chosen);
    }

    function populateOptions() {
        supplierSelect.setOptions(
            option(suppliers, 'id', function (supplier) {
                return (
                    (supplier.supplier_code ? supplier.supplier_code + ' - ' : '') +
                    supplier.supplier_name +
                    (Number(supplier.status) === 0 ? ' (Inactive)' : '')
                );
            }),
            form.supplier_id.value || ''
        );

        productSelect.setOptions(
            option(products, 'id', function (product) {
                return (
                    (product.product_code ? product.product_code + ' - ' : '') +
                    product.product_name +
                    (Number(product.status) === 0 ? ' (Inactive)' : '')
                );
            }),
            document.getElementById('entryProduct').value || ''
        );

        [1, 2, 3, 4].forEach(function (mode) {
            var select = document.querySelector('.payment-account[data-mode="' + mode + '"]');
            setAccountOptions(mode, select ? select.value : '');
        });
    }

    function entryProductChanged() {
        var product = productMap[String(document.getElementById('entryProduct').value || '')] || null;

        var unit = document.getElementById('entryUnit');
        var expiryField = document.getElementById('entryExpiryField');
        var expiry = document.getElementById('entryExpiry');
        var info = document.getElementById('entryProductInfo');

        unit.innerHTML = '<option value="">Unit</option>';

        if (!product) {
            expiryField.hidden = true;
            expiry.disabled = true;
            expiry.value = '';
            info.textContent = 'Select a Product. Expiry appears only when enabled in Product Master. Batch Number applies to the complete Purchase.';
            return;
        }

        [
            [product.primary_unit_id, product.primary_unit_symbol || product.primary_unit_name],
            [product.secondary_unit_id, product.secondary_unit_symbol || product.secondary_unit_name]
        ].forEach(function (unitItem) {
            if (!unitItem[0]) {
                return;
            }

            var optionElement = document.createElement('option');
            optionElement.value = unitItem[0];
            optionElement.textContent = unitItem[1] || 'Unit';
            unit.appendChild(optionElement);
        });

        unit.value = String(product.primary_unit_id || '');
        unit.dataset.previousUnit = unit.value;
        document.getElementById('entryRate').value = formatRateInput(product.purchase_price);
        document.getElementById('entryTaxType').value = String(product.purchase_tax_type || 2);

        var usesExpiry = Number(product.track_expiry) === 1;


        expiryField.hidden = !usesExpiry;
        expiry.disabled = !usesExpiry;

        if (!usesExpiry) {
            expiry.value = '';
        }

        var conversionText = '';
        if (Number(product.secondary_unit_id || 0) > 0 && n(product.secondary_conversion) > 0) {
            conversionText =
                ' · 1 ' + (product.primary_unit_symbol || product.primary_unit_name || 'Primary') +
                ' = ' + n(product.secondary_conversion).toLocaleString('en-IN', { maximumFractionDigits: 3 }) +
                ' ' + (product.secondary_unit_symbol || product.secondary_unit_name || 'Secondary');
        }

        info.textContent =
            'HSN: ' + (product.hsn_code || '-') +
            ' · GST: ' + n(product.gst_rate).toFixed(2) + '%' +
            ' · Cess: ' + n(product.cess_rate).toFixed(2) + '%' +
            conversionText;
    }

    function entryUnitChanged() {
        var product = productMap[String(document.getElementById('entryProduct').value || '')] || null;
        var unit = document.getElementById('entryUnit');
        var rateInput = document.getElementById('entryRate');
        if (!product || !unit.value) return;

        var oldUnitId = Number(unit.dataset.previousUnit || product.primary_unit_id || 0);
        var newUnitId = Number(unit.value || 0);
        rateInput.value = formatRateInput(
            convertRateBetweenUnits(rateInput.value, product, oldUnitId, newUnitId)
        );
        unit.dataset.previousUnit = String(newUnitId);
    }

    function entryData() {
        return {
            product_id: Number(document.getElementById('entryProduct').value || 0),
            selected_unit_id: Number(document.getElementById('entryUnit').value || 0),
            quantity: document.getElementById('entryQty').value,
            free_quantity: document.getElementById('entryFreeQty').value || 0,
            unit_price: document.getElementById('entryRate').value,
            discount_type: Number(document.getElementById('entryDiscountType').value || 1),
            discount_value: document.getElementById('entryDiscount').value || 0,
            purchase_tax_type: Number(document.getElementById('entryTaxType').value || 2),
            expiry_date: document.getElementById('entryExpiry').disabled
                ? ''
                : document.getElementById('entryExpiry').value
        };
    }

    function validateItem(item) {
        var product = productMap[String(item.product_id)] || null;

        if (!product) {
            return 'Select Product.';
        }

        if (!item.selected_unit_id) {
            return 'Select Unit.';
        }

        if (n(item.quantity) <= 0) {
            return 'Quantity must be greater than zero.';
        }

        if (n(item.free_quantity) < 0) {
            return 'Free Quantity cannot be negative.';
        }

        if (n(item.unit_price) < 0) {
            return 'Enter a valid Rate.';
        }

        if (Number(item.discount_type) === 1 && n(item.discount_value) > 100) {
            return 'Percentage Discount cannot exceed 100%.';
        }

        if (n(item.discount_value) < 0) {
            return 'Discount cannot be negative.';
        }


        if (Number(product.track_expiry) === 1 && !item.expiry_date) {
            return 'Expiry Date is required.';
        }

        return '';
    }

    function duplicateProductIndex(productId, excludeIndex) {
        productId = Number(productId || 0);
        if (!productId) return -1;

        for (var i = 0; i < rows.length; i++) {
            if (Number(i) === Number(excludeIndex)) continue;
            if (Number(rows[i].product_id || 0) === productId) return i;
        }
        return -1;
    }

    function duplicateProductMessage(productId) {
        var product = productMap[String(productId)] || {};
        var label = (product.product_code ? product.product_code + ' - ' : '') +
            (product.product_name || 'Selected Product');
        return label + ' is already added to this Purchase.';
    }

    function resetEntry() {
        document.getElementById('entryProduct').value = '';

        if (productSelect && productSelect.selectValue) {
            productSelect.selectValue('');
        }

        document.getElementById('entryUnit').innerHTML = '<option value="">Unit</option>';
        document.getElementById('entryUnit').dataset.previousUnit = '';
        document.getElementById('entryQty').value = '';
        document.getElementById('entryFreeQty').value = '';
        document.getElementById('entryRate').value = '';
        document.getElementById('entryDiscountType').value = '1';
        document.getElementById('entryDiscount').value = '';
        document.getElementById('entryTaxType').value = '2';
        document.getElementById('entryExpiry').value = '';

        entryProductChanged();

        setTimeout(function () {
            if (productSelect && productSelect.input) {
                productSelect.input.focus();
            } else {
                document.getElementById('entryProduct').focus();
            }
        }, 20);
    }

    function addItem() {
        var item = entryData();
        var error = validateItem(item);

        if (error) {
            showToast(error, {
                type: 'danger',
                duration: 3
            });
            return;
        }

        if (duplicateProductIndex(item.product_id, -1) !== -1) {
            showToast(duplicateProductMessage(item.product_id), {
                type: 'warning',
                duration: 3
            });
            return;
        }

        rows.push(item);
        renderRows();
        resetEntry();
    }

    function unitOptions(product, selected) {
        var html = '';

        [
            [product.primary_unit_id, product.primary_unit_symbol || product.primary_unit_name],
            [product.secondary_unit_id, product.secondary_unit_symbol || product.secondary_unit_name]
        ].forEach(function (unitItem) {
            if (!unitItem[0]) {
                return;
            }

            html +=
                '<option value="' + esc(unitItem[0]) + '" ' +
                (Number(selected) === Number(unitItem[0]) ? 'selected' : '') +
                '>' + esc(unitItem[1] || 'Unit') + '</option>';
        });

        return html;
    }

    function productOptions(selected) {
        return products.map(function (product) {
            return (
                '<option value="' + product.id + '" ' +
                (Number(selected) === Number(product.id) ? 'selected' : '') +
                '>' +
                esc(
                    (product.product_code ? product.product_code + ' - ' : '') +
                    product.product_name +
                    (Number(product.status) === 0 ? ' (Inactive)' : '')
                ) +
                '</option>'
            );
        }).join('');
    }

    function renderRows() {
        if (!rows.length) {
            body.innerHTML = '<tr id="emptyItemsRow"><td colspan="13" class="empty">No Products added.</td></tr>';
            calculate();
            return;
        }

        body.innerHTML = rows.map(function (item, index) {
            var product = productMap[String(item.product_id)] || {};
            var showExpiry = Number(product.track_expiry) === 1;

            return (
                '<tr data-index="' + index + '">' +
                    '<td>' + (index + 1) + '</td>' +

                    '<td class="cell-main">' +
                        '<select class="row-product">' + productOptions(item.product_id) + '</select>' +
                    '</td>' +

                    '<td class="cell-small">' +
                        '<select class="row-unit">' + unitOptions(item._unit_snapshot || product, item.selected_unit_id) + '</select>' +
                    '</td>' +

                    '<td class="cell-small">' +
                        '<input class="row-qty" type="text" inputmode="decimal" value="' + esc(item.quantity) + '">' +
                    '</td>' +

                    '<td class="cell-small">' +
                        '<input class="row-free-qty" type="text" inputmode="decimal" value="' + esc(item.free_quantity || '') + '" placeholder="0.000">' +
                    '</td>' +

                    '<td class="cell-medium">' +
                        '<input class="row-rate" type="text" inputmode="decimal" value="' + esc(item.unit_price) + '">' +
                    '</td>' +

                    '<td class="cell-small">' +
                        '<select class="row-discount-type">' +
                            '<option value="1" ' + (Number(item.discount_type) === 1 ? 'selected' : '') + '>%</option>' +
                            '<option value="2" ' + (Number(item.discount_type) === 2 ? 'selected' : '') + '>Fixed</option>' +
                        '</select>' +
                    '</td>' +

                    '<td class="cell-medium">' +
                        '<input class="row-discount" type="text" inputmode="decimal" value="' + esc(item.discount_value || 0) + '">' +
                    '</td>' +

                    '<td class="cell-medium">' +
                        '<select class="row-tax-type">' +
                            '<option value="2" ' + (Number(item.purchase_tax_type) === 2 ? 'selected' : '') + '>Exclusive</option>' +
                            '<option value="1" ' + (Number(item.purchase_tax_type) === 1 ? 'selected' : '') + '>Inclusive</option>' +
                        '</select>' +
                    '</td>' +

                    '<td class="cell-date">' +
                        '<input class="row-expiry" type="date" value="' + esc(item.expiry_date || '') + '" ' +
                        (showExpiry ? '' : 'disabled') + '>' +
                    '</td>' +

                    '<td class="row-tax cell-amount">-</td>' +
                    '<td class="row-money cell-amount">₹0.00</td>' +

                    '<td class="cell-action">' +
                        '<button type="button" class="table-icon-action danger js-remove-row" title="Remove" aria-label="Remove Product">' +
                            '<i data-lucide="x"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
        }).join('');

        calculate();

        if (window.lucide) {
            window.lucide.createIcons();
        }

        if (isPosted || (isEdit && !has(3))) {
            setReadonly();
        }
    }

    function syncRowFromDom(tr) {
        var index = Number(tr.dataset.index);
        var item = rows[index];

        if (!item) {
            return;
        }

        item.product_id = Number(tr.querySelector('.row-product').value || 0);

        var product = productMap[String(item.product_id)] || null;
        if (!product) {
            return;
        }

        item.selected_unit_id = Number(tr.querySelector('.row-unit').value || 0);
        item.quantity = tr.querySelector('.row-qty').value;
        item.free_quantity = tr.querySelector('.row-free-qty').value || 0;
        item.unit_price = tr.querySelector('.row-rate').value;
        item.discount_type = Number(tr.querySelector('.row-discount-type').value || 1);
        item.discount_value = tr.querySelector('.row-discount').value;
        item.purchase_tax_type = Number(tr.querySelector('.row-tax-type').value || 2);
        item.expiry_date = tr.querySelector('.row-expiry').disabled
            ? ''
            : tr.querySelector('.row-expiry').value;
    }

    function rowProductChange(tr) {
        var index = Number(tr.dataset.index);
        var item = rows[index];
        var newProductId = Number(tr.querySelector('.row-product').value || 0);
        var product = productMap[String(newProductId)] || null;

        if (!item || !product) {
            return;
        }

        if (duplicateProductIndex(newProductId, index) !== -1) {
            showToast(duplicateProductMessage(newProductId), {
                type: 'warning',
                duration: 3
            });
            renderRows();
            return;
        }

        item._unit_snapshot = null;
        item.item_id = 0;
        item._use_snapshot_rates = false;
        item.product_id = product.id;
        item.selected_unit_id = product.primary_unit_id;
        item.unit_price = formatRateInput(product.purchase_price);
        item.purchase_tax_type = Number(product.purchase_tax_type || 2);
        item.expiry_date = '';

        renderRows();
    }

    function rowUnitChange(tr) {
        var index = Number(tr.dataset.index);
        var item = rows[index];
        if (!item) return;

        var product = productMap[String(item.product_id)] || {};
        var source = item._unit_snapshot || product;
        var select = tr.querySelector('.row-unit');
        var rateInput = tr.querySelector('.row-rate');
        var oldUnitId = Number(item.selected_unit_id || source.primary_unit_id || 0);
        var newUnitId = Number(select.value || 0);

        if (!newUnitId) return;
        if (oldUnitId !== newUnitId) {
            rateInput.value = formatRateInput(
                convertRateBetweenUnits(rateInput.value, source, oldUnitId, newUnitId)
            );
        }

        item.selected_unit_id = newUnitId;
        syncRowFromDom(tr);
        calculate();
    }

    function calcLine(item) {
        var product = productMap[String(item.product_id)] || {};

        var gross = r2(n(item.quantity) * n(item.unit_price));

        var discount = Number(item.discount_type) === 1
            ? r2(gross * n(item.discount_value) / 100)
            : r2(n(item.discount_value));

        discount = Math.min(gross, Math.max(0, discount));

        var net = r2(gross - discount);
        var type = supplyType();
        var useSnapshotRates = item._use_snapshot_rates === true;

        var cgstRate = useSnapshotRates
            ? n(item.cgst_rate)
            : (type === 'intra_state' ? n(product.cgst_rate) : 0);
        var sgstRate = useSnapshotRates
            ? n(item.sgst_rate)
            : (type === 'intra_state' ? n(product.sgst_rate) : 0);
        var igstRate = useSnapshotRates
            ? n(item.igst_rate)
            : (type === 'inter_state' ? n(product.igst_rate) : 0);
        var cessRate = useSnapshotRates ? n(item.cess_rate) : n(product.cess_rate);
        var gstRate = useSnapshotRates ? n(item.gst_rate) : n(product.gst_rate);

        if (!useSnapshotRates && type === 'intra_state' && Math.abs(cgstRate + sgstRate - gstRate) >= 0.01) {
            cgstRate = Math.round(gstRate / 2 * 1000) / 1000;
            sgstRate = Math.round((gstRate - cgstRate) * 1000) / 1000;
        }
        if (!useSnapshotRates && type === 'inter_state' && Math.abs(igstRate - gstRate) >= 0.01) igstRate = gstRate;
        var totalTaxRate = cgstRate + sgstRate + igstRate + cessRate;

        var taxable = Number(item.purchase_tax_type) === 1 && totalTaxRate > 0
            ? r2(net / (1 + totalTaxRate / 100))
            : net;

        return {
            gross: gross,
            discount: discount,
            preTaxable: taxable,
            cgstRate: cgstRate,
            sgstRate: sgstRate,
            igstRate: igstRate,
            cessRate: cessRate,
            gst: gstRate
        };
    }

    function calculate() {
        var calculations = rows.map(calcLine);

        var gross = 0;
        var itemDiscount = 0;
        var preTaxable = 0;

        calculations.forEach(function (calculation) {
            gross += calculation.gross;
            itemDiscount += calculation.discount;
            preTaxable += calculation.preTaxable;
        });

        gross = r2(gross);
        itemDiscount = r2(itemDiscount);
        preTaxable = r2(preTaxable);

        var overallType = Number(document.getElementById('overallDiscountType').value || 1);
        var overallValue = n(document.getElementById('overallDiscountValue').value);

        var overallDiscount = overallType === 1
            ? r2(preTaxable * overallValue / 100)
            : r2(overallValue);

        overallDiscount = Math.min(preTaxable, Math.max(0, overallDiscount));

        var distributed = 0;
        var taxable = 0;
        var cgst = 0;
        var sgst = 0;
        var igst = 0;
        var cess = 0;
        var beforeRound = 0;

        calculations.forEach(function (calculation, index) {
            var share = 0;

            if (overallDiscount > 0 && preTaxable > 0) {
                share = index === calculations.length - 1
                    ? r2(overallDiscount - distributed)
                    : r2(overallDiscount * (calculation.preTaxable / preTaxable));
            }

            if (index < calculations.length - 1) {
                distributed += share;
            }

            var lineTaxable = Math.max(0, r2(calculation.preTaxable - share));
            var cgstAmount = r2(lineTaxable * calculation.cgstRate / 100);
            var sgstAmount = r2(lineTaxable * calculation.sgstRate / 100);
            var igstAmount = r2(lineTaxable * calculation.igstRate / 100);
            var cessAmount = r2(lineTaxable * calculation.cessRate / 100);

            var lineTotal = r2(
                lineTaxable +
                cgstAmount +
                sgstAmount +
                igstAmount +
                cessAmount
            );

            calculation.taxable = lineTaxable;
            calculation.cgst = cgstAmount;
            calculation.sgst = sgstAmount;
            calculation.igst = igstAmount;
            calculation.cess = cessAmount;
            calculation.total = lineTotal;

            taxable += lineTaxable;
            cgst += cgstAmount;
            sgst += sgstAmount;
            igst += igstAmount;
            cess += cessAmount;
            beforeRound += lineTotal;
        });

        taxable = r2(taxable);
        cgst = r2(cgst);
        sgst = r2(sgst);
        igst = r2(igst);
        cess = r2(cess);
        beforeRound = r2(beforeRound);

        var grand = roundEnabled ? Math.round(beforeRound) : beforeRound;
        grand = r2(grand);

        var roundOff = r2(grand - beforeRound);

        document.getElementById('sumGross').textContent = money(gross);
        document.getElementById('sumItemDiscount').textContent = money(itemDiscount);
        document.getElementById('sumOverallDiscount').textContent = money(overallDiscount);
        document.getElementById('sumTaxable').textContent = money(taxable);
        document.getElementById('sumCgst').textContent = money(cgst);
        document.getElementById('sumSgst').textContent = money(sgst);
        document.getElementById('sumIgst').textContent = money(igst);
        document.getElementById('sumCess').textContent = money(cess);
        document.getElementById('sumRoundOff').textContent = money(roundOff);
        document.getElementById('sumGrand').textContent = money(grand);
        document.getElementById('roundButton').textContent = roundEnabled ? 'Unround' : 'Round Off';

        body.querySelectorAll('tr[data-index]').forEach(function (tr) {
            var index = Number(tr.dataset.index);
            var calculation = calculations[index];

            if (!calculation) {
                return;
            }

            tr.querySelector('.row-tax').textContent = calculation.gst.toFixed(2) + '%';
            tr.querySelector('.row-money').textContent = money(calculation.total);
        });

        var paid = 0;

        document.querySelectorAll('.payment-amount').forEach(function (input) {
            paid += Math.max(0, n(input.value));
        });

        paid = r2(paid);

        document.getElementById('paidNow').textContent = money(paid);
        document.getElementById('balanceAmount').textContent = money(Math.max(0, r2(grand - paid)));

        if (isPosted && postedPurchase) {
            var saved = postedPurchase;
            var totals = {sumGross:'subtotal', sumOverallDiscount:'overall_discount_amount',sumTaxable:'taxable_total',sumCgst:'cgst_total',sumSgst:'sgst_total',sumIgst:'igst_total',sumCess:'cess_total',sumRoundOff:'round_off',sumGrand:'grand_total'};
            Object.keys(totals).forEach(function(id){document.getElementById(id).textContent=money(saved[totals[id]]);});
            document.getElementById('sumItemDiscount').textContent=money((saved.items||[]).reduce(function(total,item){return total+n(item.discount_amount);},0));
            document.getElementById('balanceAmount').textContent=money(saved.balance_amount);
            document.getElementById('settlementSummary').hidden=false;
            document.getElementById('laterPaid').textContent=money(saved.supplier_paid);
            document.getElementById('returnedAmount').textContent=money(saved.return_total);
            body.querySelectorAll('tr[data-index]').forEach(function(tr){var item=rows[Number(tr.dataset.index)];if(item)tr.querySelector('.row-money').textContent=money(item._stored_line_total);});
        }
        return {
            grand: grand,
            paid: paid
        };
    }

    function paymentPayload() {
        var output = [];

        [1, 2, 3, 4].forEach(function (mode) {
            var amountInput = document.querySelector('.payment-amount[data-mode="' + mode + '"]');
            var accountInput = document.querySelector('.payment-account[data-mode="' + mode + '"]');
            var referenceInput = document.querySelector('.payment-reference[data-mode="' + mode + '"]');

            var referenceNumber = referenceInput ? referenceInput.value.trim() : '';

            var payment = {
                payment_mode: mode,
                account_id: Number(accountInput ? accountInput.value || 0 : 0),
                amount: amountInput ? amountInput.value || 0 : 0,
                reference_no: referenceNumber
            };

            if (mode === 4) {
                payment.cheque_no = referenceNumber;
                payment.cheque_date = document.getElementById('chequeDate').value;
            }

            output.push(payment);
        });

        return output;
    }

    function payload(saveMode) {
        return {
            ref: reference || undefined,
            save_mode: saveMode,
            purchase_date: form.purchase_date.value,
            supplier_id: Number(form.supplier_id.value || 0),
            supplier_invoice_number: form.supplier_invoice_number.value.trim(),
            supplier_invoice_date: form.supplier_invoice_date.value,
            overall_discount_type: Number(document.getElementById('overallDiscountType').value || 1),
            overall_discount_value: document.getElementById('overallDiscountValue').value || 0,
            round_off_enabled: roundEnabled ? 1 : 0,
            notes: document.getElementById('notes').value.trim(),
            items: rows.map(function (item) {
                return {
                    item_id: Number(item.item_id || 0),
                    product_id: Number(item.product_id || 0),
                    selected_unit_id: Number(item.selected_unit_id || 0),
                    quantity: item.quantity,
                    free_quantity: item.free_quantity || 0,
                    unit_price: item.unit_price,
                    discount_type: Number(item.discount_type || 1),
                    discount_value: item.discount_value || 0,
                    purchase_tax_type: Number(item.purchase_tax_type || 2),
                    expiry_date: item.expiry_date || ''
                };
            }),
            payments: paymentPayload()
        };
    }

    function validateBeforeSave() {
        if (!form.purchase_date.value) {
            return 'Purchase Date is required.';
        }

        if (!Number(form.supplier_id.value || 0)) {
            return 'Supplier is required.';
        }

        if (!rows.length) {
            return 'Add at least one Product.';
        }

        var seenProducts = {};
        for (var duplicateIndex = 0; duplicateIndex < rows.length; duplicateIndex++) {
            var duplicateProductId = Number(rows[duplicateIndex].product_id || 0);
            if (duplicateProductId && seenProducts[duplicateProductId]) {
                return 'Row ' + (duplicateIndex + 1) + ': ' + duplicateProductMessage(duplicateProductId);
            }
            if (duplicateProductId) seenProducts[duplicateProductId] = true;
        }

        for (var index = 0; index < rows.length; index++) {
            var itemError = validateItem(rows[index]);
            if (itemError) {
                return 'Row ' + (index + 1) + ': ' + itemError;
            }
        }

        var overallType = Number(document.getElementById('overallDiscountType').value || 1);
        var overallValue = n(document.getElementById('overallDiscountValue').value);

        if (overallValue < 0) {
            return 'Overall Discount cannot be negative.';
        }

        if (overallType === 1 && overallValue > 100) {
            return 'Overall Discount Percentage cannot exceed 100%.';
        }

        var calculation = calculate();

        if (calculation.paid > calculation.grand + 0.01) {
            return 'Paid Now cannot exceed Grand Total.';
        }

        var paymentError = '';

        paymentPayload().forEach(function (payment) {
            if (paymentError || n(payment.amount) <= 0) {
                return;
            }

            var account = accountMap[String(payment.account_id)] || null;

            if (!account) {
                paymentError = 'Select an Account for each entered payment amount.';
                return;
            }

            if (payment.payment_mode === 1 && Number(account.account_type) !== 1) {
                paymentError = 'Cash must use a Cash Account.';
                return;
            }

            if (payment.payment_mode !== 1 && Number(account.account_type) !== 2) {
                paymentError = 'UPI, Bank and Cheque must use a Bank Account.';
                return;
            }

            // Reference / UTR / Cheque Number is optional in all payment modes.
            if (payment.payment_mode === 4 && !payment.cheque_date) {
                paymentError = 'Cheque Date is required when a Cheque Amount is entered.';
            }
        });

        return paymentError;
    }

    async function save(saveMode) {
        var error = validateBeforeSave();

        if (error) {
            showToast(error, {
                type: 'danger',
                duration: 3
            });
            return;
        }

        var draftButton = document.getElementById('saveDraftButton');
        var postButton = document.getElementById('savePostButton');

        draftButton.disabled = true;
        postButton.disabled = true;

        try {
            var result = await App.api('api/purchases.php', {
                method: isEdit ? 'PUT' : 'POST',
                body: payload(saveMode)
            });

            showToast(result.message, {
                type: 'success',
                duration: 2
            });

            setTimeout(function () {
                location.href = 'purchase-list.php';
            }, 700);
        } catch (errorObject) {
            App.showError(errorObject, 'Unable to save Purchase.');
            draftButton.disabled = false;
            postButton.disabled = false;
        }
    }

    function loadPayments(list) {
        var byMode = {};

        (list || []).forEach(function (payment) {
            byMode[Number(payment.payment_mode)] = payment;
        });

        [1, 2, 3, 4].forEach(function (mode) {
            var payment = byMode[mode] || {};
            var accountSelect = document.querySelector('.payment-account[data-mode="' + mode + '"]');
            var amountInput = document.querySelector('.payment-amount[data-mode="' + mode + '"]');
            var referenceInput = document.querySelector('.payment-reference[data-mode="' + mode + '"]');

            var selectedAccount = payment.account_id ? String(payment.account_id) : defaultAccountId(mode);
            setAccountOptions(mode, selectedAccount);

            if (accountSelect) accountSelect.value = selectedAccount;
            if (accountSelects[mode] && accountSelects[mode].selectValue) {
                accountSelects[mode].selectValue(selectedAccount);
            }

            if (amountInput) {
                amountInput.value = n(payment.amount).toFixed(2);
            }

            if (referenceInput) {
                referenceInput.value = mode === 4
                    ? (payment.cheque_no || payment.reference_no || '')
                    : (payment.reference_no || '');
            }

            if (mode === 4) {
                document.getElementById('chequeDate').value = payment.cheque_date || '';
            }
        });

        calculate();
    }

    function applyPurchase(purchase) {
        isPosted = Number(purchase.posting_status) === 1;
        postedPurchase = isPosted ? purchase : null;
        document.getElementById('purchaseNo').value = purchase.purchase_no || '';
        form.purchase_date.value = purchase.purchase_date || '';
        document.getElementById('batchNumber').value = purchase.batch_number || '';

        supplierSelect.setOptions(
            option(suppliers, 'id', function (supplier) {
                return (
                    (supplier.supplier_code ? supplier.supplier_code + ' - ' : '') +
                    supplier.supplier_name +
                    (Number(supplier.status) === 0 ? ' (Inactive)' : '')
                );
            }),
            purchase.supplier_id || ''
        );

        form.supplier_invoice_number.value = purchase.supplier_invoice_number || '';
        form.supplier_invoice_date.value = purchase.supplier_invoice_date || '';

        var reverseOverallType = Number(purchase.overall_discount_type || 1);
        var reverseOverallValue = n(purchase.overall_discount_value);

        /* Reverse compatibility for older saved rows where only the calculated
           overall discount amount exists. */
        if (reverseOverallValue <= 0 && n(purchase.overall_discount_amount) > 0) {
            if (reverseOverallType === 1) {
                var reverseTaxableBeforeOverall =
                    n(purchase.taxable_total) + n(purchase.overall_discount_amount);
                reverseOverallValue = reverseTaxableBeforeOverall > 0
                    ? r2(n(purchase.overall_discount_amount) * 100 / reverseTaxableBeforeOverall)
                    : 0;
            } else {
                reverseOverallValue = n(purchase.overall_discount_amount);
            }
        }

        document.getElementById('overallDiscountType').value = String(reverseOverallType);
        document.getElementById('overallDiscountValue').value = reverseOverallValue.toFixed(2);

        roundEnabled = Number(purchase.round_off_enabled) === 1 || Math.abs(n(purchase.round_off)) > 0.0001;
        document.getElementById('notes').value = purchase.notes || '';

        rows = (purchase.items || []).map(function (item) {
            var conversion = n(item.conversion_rate) > 0 ? n(item.conversion_rate) : 1;
            var selectedUnitId = Number(item.selected_unit_id || item.primary_unit_id || 0);
            var selectedIsSecondary = Number(item.secondary_unit_id || 0) > 0 &&
                selectedUnitId === Number(item.secondary_unit_id);

            var quantity = selectedIsSecondary
                ? n(item.secondary_quantity)
                : n(item.primary_quantity);

            if (quantity <= 0) {
                quantity = selectedIsSecondary
                    ? r3(n(item.quantity) * conversion)
                    : n(item.quantity);
            }

            var primaryRate = n(item.unit_price);
            var selectedRate = selectedIsSecondary
                ? primaryRate / conversion
                : primaryRate;

            /* API stores free_quantity as Primary-equivalent quantity. */
            var selectedFreeQty = selectedIsSecondary
                ? r3(n(item.free_quantity) * conversion)
                : n(item.free_quantity);

            var gross = r2(quantity * selectedRate);
            var discountType = Number(item.discount_type || 1);
            var discountValue = n(item.discount_value);

            if (discountValue <= 0 && n(item.discount_amount) > 0) {
                discountValue = discountType === 1 && gross > 0
                    ? r2(n(item.discount_amount) * 100 / gross)
                    : n(item.discount_amount);
            }

            return {
                _unit_snapshot: {
                    primary_unit_id: item.primary_unit_id,
                    secondary_unit_id: item.secondary_unit_id,
                    primary_unit_name: item.primary_unit_name,
                    primary_unit_symbol: item.primary_unit_symbol,
                    secondary_unit_name: item.secondary_unit_name,
                    secondary_unit_symbol: item.secondary_unit_symbol,
                    conversion_rate: conversion
                },
                _stored_line_total: n(item.line_total),
                item_id: Number(item.id || item.item_id || 0),
                product_id: Number(item.product_id),
                selected_unit_id: selectedUnitId,
                quantity: quantity.toFixed(3).replace(/\.?0+$/, ''),
                free_quantity: selectedFreeQty > 0
                    ? selectedFreeQty.toFixed(3).replace(/\.?0+$/, '')
                    : '',
                unit_price: formatRateInput(selectedRate),
                discount_type: discountType,
                discount_value: discountValue.toFixed(2),
                purchase_tax_type: Number(item.purchase_tax_type || 2),
                expiry_date: item.expiry_date || '',
                gst_rate: n(item.gst_rate),
                cgst_rate: n(item.cgst_rate),
                sgst_rate: n(item.sgst_rate),
                igst_rate: n(item.igst_rate),
                cess_rate: n(item.cess_rate),
                _use_snapshot_rates: true
            };
        });

        isPosted = Number(purchase.posting_status) === 1;

        savedSupplyType = (purchase.supply_type === 'intra_state' || purchase.supply_type === 'inter_state')
            ? purchase.supply_type : '';
        updateSupply();
        renderRows();
        loadPayments(purchase.payments || []);
        calculate();

        if (isPosted) {
            document.getElementById('pageHeading').textContent = 'View Purchase';
            document.getElementById('formModeText').textContent = 'Posted Purchase is read-only.';
            setReadonly();
            return;
        }

        document.getElementById('pageHeading').textContent = 'Edit Purchase Draft';

        if (!has(3)) {
            document.getElementById('formModeText').textContent = 'You have View permission only for this Purchase.';
            setReadonly();
        }
    }

    function setReadonly() {
        Array.prototype.forEach.call(
            form.querySelectorAll('input,select,textarea,button'),
            function (element) {
                if (element.type !== 'button') {
                    element.disabled = true;
                } else {
                    element.disabled = true;
                }
            }
        );

        form.querySelectorAll('.global-select input,.global-select button').forEach(function (element) {
            element.disabled = true;
            element.tabIndex = -1;
        });

        document.getElementById('saveDraftButton').style.display = 'none';
        document.getElementById('savePostButton').style.display = 'none';
        document.getElementById('addItemButton').style.display = 'none';
    }

    function responseData(response) {
        if (!response || typeof response !== 'object') return {};
        return (response.data && typeof response.data === 'object') ? response.data : response;
    }

    function responseOptions(data) {
        if (!data || typeof data !== 'object') return {};
        if (data.options && typeof data.options === 'object') return data.options;

        /* Backward compatibility for older API responses that returned
           suppliers/products/accounts directly inside data. */
        return data;
    }

    async function refreshSupplierOptions(preferredSupplierCode) {
        var response = await App.api('api/purchases.php?options=1');
        var data = responseData(response);
        var options = responseOptions(data);

        suppliers = Array.isArray(options.suppliers) ? options.suppliers : [];
        rebuildMaps();

        var selected = '';
        if (preferredSupplierCode) {
            var createdSupplier = suppliers.find(function (supplier) {
                return String(supplier.supplier_code || '').toUpperCase() ===
                    String(preferredSupplierCode || '').toUpperCase();
            });
            if (createdSupplier) selected = String(createdSupplier.id);
        }

        supplierSelect.setOptions(
            option(suppliers, 'id', function (supplier) {
                return (
                    (supplier.supplier_code ? supplier.supplier_code + ' - ' : '') +
                    supplier.supplier_name +
                    (Number(supplier.status) === 0 ? ' (Inactive)' : '')
                );
            }),
            selected
        );

        if (selected) {
            form.supplier_id.value = selected;
            if (supplierSelect && supplierSelect.selectValue) {
                supplierSelect.selectValue(selected);
            }
            savedSupplyType = '';
            updateSupply();
            calculate();
        }
    }

    async function load() {
        try {
            var base = await App.api('api/purchases.php?options=1');
            var baseData = responseData(base);
            var baseOptions = responseOptions(baseData);

            actions = Array.isArray(baseData.allowed_actions)
                ? baseData.allowed_actions.map(Number)
                : [];

            branch = baseData.branch || {};
            suppliers = Array.isArray(baseOptions.suppliers) ? baseOptions.suppliers : [];
            products = Array.isArray(baseOptions.products) ? baseOptions.products : [];
            accounts = Array.isArray(baseOptions.accounts) ? baseOptions.accounts : [];

            if (!Array.isArray(baseOptions.suppliers) ||
                !Array.isArray(baseOptions.products) ||
                !Array.isArray(baseOptions.accounts)) {
                console.error('Unexpected Purchase options API response:', base);
                throw new Error('Purchase options response is incomplete. Check api/purchases.php?options=1.');
            }

            rebuildMaps();
            populateOptions();

            document.getElementById('purchaseNo').value = baseData.next_purchase_no || '';
            document.getElementById('batchNumber').value = baseData.next_batch_no || '';

            if (!form.purchase_date.value) {
                form.purchase_date.value = new Date().toISOString().slice(0, 10);
            }

            if (reference) {
                var response = await App.api(
                    'api/purchases.php?ref=' + encodeURIComponent(reference)
                );
                var editData = responseData(response);
                var editOptions = responseOptions(editData);

                if (Array.isArray(editData.allowed_actions)) {
                    actions = editData.allowed_actions.map(Number);
                }

                branch = editData.branch || branch;
                suppliers = Array.isArray(editOptions.suppliers) ? editOptions.suppliers : suppliers;
                products = Array.isArray(editOptions.products) ? editOptions.products : products;
                accounts = Array.isArray(editOptions.accounts) ? editOptions.accounts : accounts;

                rebuildMaps();
                populateOptions();
                applyPurchase(editData.purchase || {});
            } else if (!has(2)) {
                throw new Error('You do not have permission to create Purchases.');
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }

            setTimeout(function () {
                form.purchase_date.focus();
            }, 30);
        } catch (errorObject) {
            App.showError(errorObject, 'Unable to prepare Purchase Form.');
            document.getElementById('saveDraftButton').disabled = true;
            document.getElementById('savePostButton').disabled = true;
        }
    }

    /* =====================================================
       EVENTS
       ===================================================== */

    document.getElementById('addSupplierButton').addEventListener('click', function () {
        if (!window.AppSupplierForm) {
            App.showError(null, 'Reusable Supplier modal is unavailable.');
            return;
        }

        AppSupplierForm.openCreate({
            onSaved: function (supplier) {
                refreshSupplierOptions(supplier && supplier.supplier_code ? supplier.supplier_code : '')
                    .catch(function (error) {
                        App.showError(error, 'Supplier was created, but the Purchase Supplier list could not be refreshed.');
                    });
            }
        });
    });

    document.getElementById('entryProduct').addEventListener('change', entryProductChanged);
    document.getElementById('entryUnit').addEventListener('change', entryUnitChanged);

    form.supplier_id.addEventListener('change', function () {
        /* Supplier change can change intra/inter-state GST. Once the user
           deliberately changes Supplier, stop using the old Purchase tax
           snapshot and calculate from the currently selected Supplier/Product. */
        rows.forEach(function (item) {
            item._use_snapshot_rates = false;
        });
        savedSupplyType = '';
        updateSupply();
        calculate();
    });

    document.getElementById('addItemButton').addEventListener('click', addItem);

    body.addEventListener('change', function (event) {
        var tr = event.target.closest('tr[data-index]');

        if (!tr) {
            return;
        }

        if (event.target.classList.contains('row-product')) {
            rowProductChange(tr);
            return;
        }

        if (event.target.classList.contains('row-unit')) {
            rowUnitChange(tr);
            return;
        }

        syncRowFromDom(tr);
        calculate();
    });

    body.addEventListener('input', function (event) {
        var tr = event.target.closest('tr[data-index]');

        if (!tr) {
            return;
        }

        syncRowFromDom(tr);
        calculate();
    });

    body.addEventListener('click', function (event) {
        var button = event.target.closest('.js-remove-row');

        if (!button) {
            return;
        }

        var tr = button.closest('tr[data-index]');
        var index = Number(tr.dataset.index);

        rows.splice(index, 1);
        renderRows();
    });

    document.getElementById('overallDiscountType').addEventListener('change', calculate);
    document.getElementById('overallDiscountValue').addEventListener('input', calculate);

    document.getElementById('roundButton').addEventListener('click', function () {
        roundEnabled = !roundEnabled;
        calculate();
    });

    document.querySelectorAll('.payment-amount').forEach(function (input) {
        input.addEventListener('input', calculate);
    });

    document.querySelectorAll('.payment-account,.payment-reference,.payment-cheque-date').forEach(function (input) {
        input.addEventListener('change', calculate);
        input.addEventListener('input', calculate);
    });

    document.getElementById('saveDraftButton').addEventListener('click', function () {
        save('draft');
    });

    document.getElementById('savePostButton').addEventListener('click', function () {
        var supplier = selectedSupplier();
        var locationUnknown = !String(branch.state_code || '').trim() || !supplierStateCode(supplier);
        var taxable = rows.some(function (item) {
            var product = productMap[String(item.product_id || '')] || {};
            return n(product.gst_rate) > 0 || n(product.cess_rate) > 0;
        });
        var message = 'Post this Purchase? Stock will increase and the Purchase becomes read-only.';
        if (locationUnknown && taxable && !savedSupplyType) {
            message += '\n\nSupplier or Branch state could not be verified. Same-state CGST + SGST has been assumed automatically. Confirm this agrees with the supplier invoice before posting.';
        }
        if (confirm(message)) save('post');
    });

    /* =====================================================
       KEYBOARD / TAB / ENTER FLOW
       Native TAB follows the DOM order.
       Hidden Expiry input is disabled and skipped.
       ENTER on the last visible product field adds the row.
       ===================================================== */

    document.getElementById('entryExpiry').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addItem();
        }
    });

    document.getElementById('entryTaxType').addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && document.getElementById('entryExpiry').disabled) {
            event.preventDefault();
            addItem();
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
</body>
</html>
