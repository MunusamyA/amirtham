<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Purchase Return Form';
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
                    <h1 id="pageHeading">Add Purchase Return</h1>
                    <p>Return stock against the original posted Purchase. Product, batch, rate and tax come from the original Purchase.</p>
                </div>

                <a class="btn gray" href="purchase-return-list.php">
                    <i data-lucide="list"></i>
                    Purchase Return List
                </a>
            </div>

            <div class="card form-card" id="returnCard">
                <form id="returnForm" novalidate>
                    <div class="card-header">
                        <div>
                            <h2>Purchase Return Information</h2>
                            <p id="formModeText">Save as Draft or Post directly. Posted Returns are read-only.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="form-row">
                            <div class="field col-2">
                                <label for="returnNo">Return No</label>
                                <input id="returnNo" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="Auto generated">
                            </div>

                            <div class="field col-2">
                                <label for="returnDate" class="required">Return Date</label>
                                <input id="returnDate" name="return_date" type="date" required data-required-message="Return Date is required.">
                            </div>

                            <div class="field col-4">
                                <label for="purchaseRef" class="required">Original Purchase</label>
                                <select id="purchaseRef" name="purchase_ref" required data-placeholder="Select or type Purchase" data-required-message="Original Purchase is required.">
                                    <option value="">Select Purchase</option>
                                </select>
                            </div>

                            <div class="field col-4">
                                <label for="supplierName">Supplier</label>
                                <input id="supplierName" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="Auto from Purchase">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-3">
                                <label for="batchNumber">Purchase Batch</label>
                                <input id="batchNumber" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="Auto from Purchase">
                            </div>

                            <div class="field col-3">
                                <label for="purchaseDate">Purchase Date</label>
                                <input id="purchaseDate" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="-">
                            </div>

                            <div class="field col-3">
                                <label for="supplierInvoiceNo">Supplier Invoice No</label>
                                <input id="supplierInvoiceNo" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="-">
                            </div>

                            <div class="field col-3">
                                <label for="purchaseGrandTotal">Purchase Grand Total</label>
                                <input id="purchaseGrandTotal" type="text" readonly tabindex="-1" aria-readonly="true" placeholder="₹0.00">
                            </div>
                        </div>

                        <div class="card-section-title">Return Items</div>

                        <div class="muted" id="returnHelp">
                            Select an Original Purchase. Enter Primary and/or Secondary Return Qty. Original conversion, rate, discount and tax are reversed proportionately from the saved Purchase Item.
                        </div>

                        <div class="card table-card" style="margin-top:12px;">
                            <table id="returnItemsTable" class="data-table">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Purchased</th>
                                    <th>Already Returned</th>
                                    <th>Available</th>
                                    <th>Primary Return Qty</th>
                                    <th>Secondary Return Qty</th>
                                    <th>Primary Rate</th>
                                    <th>Tax</th>
                                    <th>Amount</th>
                                </tr>
                                </thead>
                                <tbody id="returnItemsBody">
                                <tr>
                                    <td colspan="10" class="empty">Select an Original Purchase.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="app-split-grid" style="margin-top:16px;">
                            <div class="app-side-card">
                                <div class="app-side-card-head">
                                    <strong>Return Reason</strong>
                                </div>
                                <div class="app-side-card-body">
                                    <div class="field" style="margin-bottom:0;">
                                        <label for="reason">Reason</label>
                                        <textarea id="reason" name="reason" rows="5" maxlength="255" placeholder="Optional return reason"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="app-side-card">
                                <div class="app-side-card-head">
                                    <strong>Purchase Return Summary</strong>
                                </div>
                                <div class="app-side-card-body">
                                    <div class="app-summary-row"><span>Gross</span><strong id="sumGross">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Reversed Discount</span><strong id="sumDiscount">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Taxable</span><strong id="sumTaxable">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>CGST</span><strong id="sumCgst">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>SGST</span><strong id="sumSgst">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>IGST</span><strong id="sumIgst">₹0.00</strong></div>
                                    <div class="app-summary-row"><span>Cess</span><strong id="sumCess">₹0.00</strong></div>
                                    <div class="app-summary-row total"><span>Grand Total</span><strong id="sumGrand">₹0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="buttons">
                            <a class="btn gray" href="purchase-return-list.php">Cancel</a>
                            <button class="btn gray" id="saveDraftButton" type="button">
                                <i data-lucide="save"></i>
                                Save Draft
                            </button>
                            <button class="btn btn-primary" id="savePostButton" type="button">
                                <i data-lucide="check-circle"></i>
                                Save &amp; Post
                            </button>
                        </div>
                    </div>
                </form>
            </div>

<script>
(function (window, document) {
    'use strict';

    var form = document.getElementById('returnForm');
    var body = document.getElementById('returnItemsBody');
    var reference = new URLSearchParams(window.location.search).get('ref') || '';
    var isEdit = reference !== '';
    var isPosted = false;
    var actions = [];
    var purchases = [];
    var purchaseMap = {};
    var purchase = null;
    var savedQuantityMap = {};
    var purchaseSelect = GlobalSelect.init('#purchaseRef', {
        placeholder: 'Select or type Purchase'
    });

    function has(id) {
        return actions.indexOf(Number(id)) !== -1;
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

    function money(value) {
        return '₹' + n(value).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function qty(value) {
        return n(value).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
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

    function responseData(response) {
        if (!response || typeof response !== 'object') return {};
        return (response.data && typeof response.data === 'object') ? response.data : response;
    }

    function rebuildPurchaseMap() {
        purchaseMap = {};
        purchases.forEach(function (item) {
            purchaseMap[String(item.ref)] = item;
        });
    }

    function populatePurchases(selectedRef) {
        purchaseSelect.setOptions(
            option(purchases, 'ref', function (item) {
                return (
                    item.purchase_no +
                    ' - ' + item.supplier_name +
                    (item.batch_number ? ' - ' + item.batch_number : '') +
                    ' - ' + money(item.grand_total) +
                    (Number(item.status) === 0 ? ' (Inactive)' : '')
                );
            }),
            selectedRef || ''
        );
    }

    function taxLabel(item) {
        var parts = [];
        if (n(item.cgst_rate) > 0) parts.push('CGST ' + n(item.cgst_rate).toFixed(2) + '%');
        if (n(item.sgst_rate) > 0) parts.push('SGST ' + n(item.sgst_rate).toFixed(2) + '%');
        if (n(item.igst_rate) > 0) parts.push('IGST ' + n(item.igst_rate).toFixed(2) + '%');
        if (n(item.cess_rate) > 0) parts.push('Cess ' + n(item.cess_rate).toFixed(2) + '%');
        return parts.length ? parts.join(' + ') : 'No Tax';
    }

    function fillPurchaseHeader() {
        document.getElementById('supplierName').value = purchase
            ? ((purchase.supplier_code ? purchase.supplier_code + ' - ' : '') + purchase.supplier_name)
            : '';
        document.getElementById('batchNumber').value = purchase ? (purchase.batch_number || '') : '';
        document.getElementById('purchaseDate').value = purchase ? (purchase.purchase_date || '') : '';
        document.getElementById('supplierInvoiceNo').value = purchase ? (purchase.supplier_invoice_number || '') : '';
        document.getElementById('purchaseGrandTotal').value = purchase ? money(purchase.grand_total) : '';
    }

    function currentReturnQuantities(itemId) {
        var primary = body.querySelector('.return-primary-qty[data-item-id="' + itemId + '"]');
        var secondary = body.querySelector('.return-secondary-qty[data-item-id="' + itemId + '"]');
        return {
            primary: primary ? n(primary.value) : 0,
            secondary: secondary && !secondary.disabled ? n(secondary.value) : 0
        };
    }

    function unitName(item, type) {
        if (type === 'primary') return item.primary_unit_symbol || item.primary_unit_name || 'Primary';
        return item.secondary_unit_symbol || item.secondary_unit_name || 'Secondary';
    }

    function mixedText(primary, secondary, item) {
        var text = qty(primary) + ' ' + unitName(item, 'primary');
        if (Number(item.secondary_unit_id || 0) > 0) text += ' + ' + qty(secondary) + ' ' + unitName(item, 'secondary');
        return text;
    }

    function renderItems() {
        if (!purchase || !Array.isArray(purchase.items) || !purchase.items.length) {
            body.innerHTML = '<tr><td colspan="10" class="empty">No Purchase Items available.</td></tr>';
            calculate();
            return;
        }

        body.innerHTML = '';
        purchase.items.forEach(function (item, index) {
            var itemId = Number(item.id);
            var saved = savedQuantityMap[String(itemId)] || { primary: 0, secondary: 0 };
            var row = document.createElement('tr');
            row.dataset.itemId = String(itemId);
            var hasSecondary = Number(item.secondary_unit_id || 0) > 0;
            if (!hasSecondary) saved.secondary = 0;
            row.innerHTML =
                '<td>' + (index + 1) + '</td>' +
                '<td><strong>' + esc((item.product_code ? item.product_code + ' - ' : '') + item.product_name) + '</strong></td>' +
                '<td>' + esc(mixedText(n(item.primary_quantity), n(item.secondary_quantity), item)) + '</td>' +
                '<td>' + esc(mixedText(n(item.already_returned_primary_quantity), n(item.already_returned_secondary_quantity), item)) + '</td>' +
                '<td>' + esc(mixedText(n(item.available_primary_quantity), n(item.available_secondary_quantity), item)) + '</td>' +
                '<td><input class="return-primary-qty" data-item-id="' + itemId + '" type="text" inputmode="decimal" value="' + (n(saved.primary) > 0 ? n(saved.primary).toFixed(3) : '') + '" placeholder="' + esc(unitName(item,'primary')) + '"' + (isPosted ? ' disabled' : '') + '></td>' +
                '<td><input class="return-secondary-qty" data-item-id="' + itemId + '" type="text" inputmode="decimal" value="' + (n(saved.secondary) > 0 ? n(saved.secondary).toFixed(3) : '') + '" placeholder="' + esc(unitName(item,'secondary')) + '"' + ((!hasSecondary || isPosted) ? ' disabled' : '') + '></td>' +
                '<td>' + money(item.unit_price) + '</td>' +
                '<td>' + esc(taxLabel(item)) + '</td>' +
                '<td class="return-line-total" data-item-id="' + itemId + '">₹0.00</td>';
            body.appendChild(row);
        });

        calculate();
        if (window.lucide) window.lucide.createIcons();
    }

    function calculateLine(item, quantities) {
        var conversion = n(item.conversion_rate) > 0 ? n(item.conversion_rate) : 1;
        var returnBase = r3((n(quantities.primary) * conversion) + n(quantities.secondary));
        var originalBase = n(item.base_quantity);
        if (originalBase <= 0 || returnBase <= 0) {
            return { gross:0, discount:0, taxable:0, cgst:0, sgst:0, igst:0, cess:0, total:0 };
        }
        var ratio = Math.min(1, returnBase / originalBase);
        var primaryRate = n(item.unit_price);
        var secondaryRate = Number(item.secondary_unit_id || 0) > 0 && conversion > 0
            ? (primaryRate / conversion)
            : 0;
        var grossOriginal = r2((n(item.primary_quantity) * primaryRate) + (n(item.secondary_quantity) * secondaryRate));
        if (grossOriginal <= 0 && n(item.quantity) > 0) {
            grossOriginal = r2(n(item.quantity) * n(item.unit_price));
        }
        var gross = r2(grossOriginal * ratio);
        var discount = r2((n(item.discount_amount) + n(item.overall_discount_share)) * ratio);
        var taxable = r2(n(item.taxable_amount) * ratio);
        var cgst = r2(n(item.cgst_amount) * ratio);
        var sgst = r2(n(item.sgst_amount) * ratio);
        var igst = r2(n(item.igst_amount) * ratio);
        var cess = r2(n(item.cess_amount) * ratio);
        var total = r2(n(item.line_total) * ratio);
        return { gross:gross, discount:discount, taxable:taxable, cgst:cgst, sgst:sgst, igst:igst, cess:cess, total:total };
    }

    function calculate() {
        var totals = {
            gross: 0,
            discount: 0,
            taxable: 0,
            cgst: 0,
            sgst: 0,
            igst: 0,
            cess: 0,
            total: 0
        };

        if (purchase && Array.isArray(purchase.items)) {
            purchase.items.forEach(function (item) {
                var itemId = Number(item.id);
                var quantities = currentReturnQuantities(itemId);
                var line = calculateLine(item, quantities);

                Object.keys(totals).forEach(function (key) {
                    totals[key] = r2(totals[key] + line[key]);
                });

                var cell = body.querySelector('.return-line-total[data-item-id="' + itemId + '"]');
                if (cell) cell.textContent = money(line.total);
            });
        }

        document.getElementById('sumGross').textContent = money(totals.gross);
        document.getElementById('sumDiscount').textContent = money(totals.discount);
        document.getElementById('sumTaxable').textContent = money(totals.taxable);
        document.getElementById('sumCgst').textContent = money(totals.cgst);
        document.getElementById('sumSgst').textContent = money(totals.sgst);
        document.getElementById('sumIgst').textContent = money(totals.igst);
        document.getElementById('sumCess').textContent = money(totals.cess);
        document.getElementById('sumGrand').textContent = money(totals.total);
    }

    function collectItems() {
        var items = [];
        if (!purchase || !Array.isArray(purchase.items)) return items;
        purchase.items.forEach(function (item) {
            var itemId = Number(item.id);
            var values = currentReturnQuantities(itemId);
            var primary = r3(values.primary);
            var secondary = r3(values.secondary);
            if (primary > 0 || secondary > 0) {
                items.push({ purchase_item_id:itemId, primary_quantity:primary, secondary_quantity:secondary });
            }
        });
        return items;
    }

    function validateItems() {
        var items = collectItems();
        if (!items.length) {
            showToast('Enter Primary Return Qty or Secondary Return Qty for at least one Product.', { type:'error', duration:3 });
            return false;
        }
        var valid = true;
        purchase.items.forEach(function (item) {
            var itemId = Number(item.id);
            var pInput = body.querySelector('.return-primary-qty[data-item-id="' + itemId + '"]');
            var sInput = body.querySelector('.return-secondary-qty[data-item-id="' + itemId + '"]');
            if (!pInput) return;
            var values = currentReturnQuantities(itemId);
            var conversion = n(item.conversion_rate) > 0 ? n(item.conversion_rate) : 1;
            var base = r3((values.primary * conversion) + values.secondary);
            var availableBase = r3(n(item.available_base_quantity));
            pInput.removeAttribute('aria-invalid');
            if (sInput) sInput.removeAttribute('aria-invalid');
            if (values.primary < 0 || values.secondary < 0 || base > availableBase + 0.0005) {
                pInput.setAttribute('aria-invalid','true');
                if (sInput && !sInput.disabled) sInput.setAttribute('aria-invalid','true');
                valid = false;
            }
        });
        if (!valid) showToast('Return quantity cannot exceed Available Qty.', { type:'error', duration:3 });
        return valid;
    }

    async function loadPurchase(purchaseRef) {
        if (!purchaseRef) {
            purchase = null;
            savedQuantityMap = {};
            fillPurchaseHeader();
            renderItems();
            return;
        }

        var result = await App.api(
            'api/purchase-returns.php?purchase_ref=' + encodeURIComponent(purchaseRef)
        );
        var data = responseData(result);
        purchase = data.purchase || null;
        fillPurchaseHeader();
        renderItems();
    }

    function applySavedReturn(returnData) {
        document.getElementById('returnNo').value = returnData.return_no || '';
        form.return_date.value = returnData.return_date || '';
        form.reason.value = returnData.reason || '';

        savedQuantityMap = {};
        (returnData.items || []).forEach(function (item) {
            savedQuantityMap[String(item.purchase_item_id)] = { primary:n(item.primary_quantity), secondary:n(item.secondary_quantity) };
        });

        isPosted = Number(returnData.posting_status) === 1;

        document.getElementById('pageHeading').textContent = isPosted
            ? 'View Purchase Return'
            : 'Edit Purchase Return Draft';

        if (isPosted) {
            document.getElementById('formModeText').textContent = 'Posted Purchase Return is read-only.';
        }
    }

    function setReadonly() {
        Array.prototype.forEach.call(
            form.querySelectorAll('input,select,textarea,button'),
            function (element) {
                element.disabled = true;
            }
        );

        form.querySelectorAll('.global-select input,.global-select button').forEach(function (element) {
            element.disabled = true;
            element.tabIndex = -1;
        });

        document.getElementById('saveDraftButton').style.display = 'none';
        document.getElementById('savePostButton').style.display = 'none';
    }

    async function load() {
        try {
            var base = await App.api('api/purchase-returns.php?options=1');
            var baseData = responseData(base);

            actions = Array.isArray(baseData.allowed_actions)
                ? baseData.allowed_actions.map(Number)
                : [];

            purchases = Array.isArray(baseData.purchases) ? baseData.purchases : [];
            rebuildPurchaseMap();

            document.getElementById('returnNo').value = baseData.next_return_no || '';
            if (!form.return_date.value) {
                form.return_date.value = new Date().toISOString().slice(0, 10);
            }

            if (reference) {
                var response = await App.api(
                    'api/purchase-returns.php?ref=' + encodeURIComponent(reference)
                );
                var editData = responseData(response);

                if (Array.isArray(editData.allowed_actions)) {
                    actions = editData.allowed_actions.map(Number);
                }

                purchases = Array.isArray(editData.purchases) ? editData.purchases : purchases;
                rebuildPurchaseMap();

                var returnData = editData.purchase_return || {};
                applySavedReturn(returnData);
                populatePurchases(returnData.purchase_ref || '');
                purchase = editData.purchase || null;
                fillPurchaseHeader();
                renderItems();

                if (isPosted || !has(3)) {
                    if (!isPosted) {
                        document.getElementById('formModeText').textContent = 'You have View permission only for this Purchase Return.';
                    }
                    setReadonly();
                }
            } else {
                populatePurchases('');
                if (!has(2)) {
                    throw new Error('You do not have permission to create Purchase Returns.');
                }
            }

            if (window.lucide) window.lucide.createIcons();
        } catch (errorObject) {
            App.showError(errorObject, 'Unable to prepare Purchase Return Form.');
            document.getElementById('saveDraftButton').disabled = true;
            document.getElementById('savePostButton').disabled = true;
        }
    }

    async function save(mode) {
        Validation.clearForm(form);

        if (!Validation.validateForm(form)) return;
        if (!purchase) {
            showToast('Select an Original Purchase.', { type: 'error', duration: 3 });
            return;
        }
        if (!validateItems()) return;

        var payload = {
            ref: reference,
            purchase_ref: form.purchase_ref.value,
            return_date: form.return_date.value,
            reason: form.reason.value.trim(),
            save_mode: mode,
            items: collectItems()
        };

        var draftButton = document.getElementById('saveDraftButton');
        var postButton = document.getElementById('savePostButton');
        draftButton.disabled = true;
        postButton.disabled = true;

        try {
            var result = await App.api('api/purchase-returns.php', {
                method: isEdit ? 'PUT' : 'POST',
                body: payload
            });

            showToast(result.message, { type: 'success', duration: 2 });
            setTimeout(function () {
                window.location.href = 'purchase-return-list.php';
            }, 650);
        } catch (errorObject) {
            Validation.applyErrors(form, errorObject.errors || {});
            App.showError(errorObject, 'Unable to save Purchase Return.');
            draftButton.disabled = false;
            postButton.disabled = false;
        }
    }

    form.purchase_ref.addEventListener('change', async function () {
        if (isPosted) return;

        savedQuantityMap = {};
        try {
            await loadPurchase(form.purchase_ref.value);
        } catch (errorObject) {
            purchase = null;
            fillPurchaseHeader();
            renderItems();
            App.showError(errorObject, 'Unable to load Original Purchase.');
        }
    });

    body.addEventListener('input', function (event) {
        if (!event.target.classList.contains('return-primary-qty') && !event.target.classList.contains('return-secondary-qty')) return;
        var itemId = Number(event.target.dataset.itemId || 0);
        var item = null;
        (purchase && purchase.items ? purchase.items : []).some(function (candidate) {
            if (Number(candidate.id) === itemId) { item = candidate; return true; }
            return false;
        });
        if (item) {
            var values = currentReturnQuantities(itemId);
            var conversion = n(item.conversion_rate) > 0 ? n(item.conversion_rate) : 1;
            var base = r3((values.primary * conversion) + values.secondary);
            var availableBase = r3(n(item.available_base_quantity));
            var invalid = values.primary < 0 || values.secondary < 0 || base > availableBase + 0.0005;
            var pInput = body.querySelector('.return-primary-qty[data-item-id="' + itemId + '"]');
            var sInput = body.querySelector('.return-secondary-qty[data-item-id="' + itemId + '"]');
            [pInput,sInput].forEach(function(input){ if(!input) return; if(invalid) input.setAttribute('aria-invalid','true'); else input.removeAttribute('aria-invalid'); });
        }
        calculate();
    });

    document.getElementById('saveDraftButton').addEventListener('click', function () {
        save('draft');
    });

    document.getElementById('savePostButton').addEventListener('click', function () {
        save('post');
    });

    load();
})(window, document);
</script>
        </section>
        <?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
