<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Product Form';
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
<script src="assets/js/global-select.js"></script>
<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Product</h1>
        <p>Maintain Product Master, MRP, stock price and manually entered seller-type base pricing.</p>
    </div>
    <a class="btn gray" href="product-list.php">
        <i data-lucide="list"></i> Product List
    </a>
</div>
<div class="card form-card">
<form id="productForm" novalidate>
    <input type="hidden" name="ref" id="productRef">
    <!-- Compatibility values for the current Product API. -->
    <input type="hidden" name="purchase_tax_type" id="legacyPurchaseTaxType" value="2">
    <input type="hidden" name="sale_tax_type" id="legacySaleTaxType" value="2">
    <input type="hidden" name="sale_price" id="legacySalePrice" value="0.00">
    <input type="hidden" name="sale_prices_json" id="salePricesJson" value="[]">
    <div class="card-header">
        <div>
            <h2>Product Details</h2>
            <p>Category and Primary Unit are mandatory. Subcategory, Secondary Unit and HSN are optional.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="card-section-title">Basic Details</div>
        <div class="form-row">
            <div class="field col-4">
                <label for="productCode">Product Code</label>
                <input id="productCode" name="product_code" type="text" maxlength="50" readonly
                       aria-readonly="true" placeholder="Auto generated">
                <div class="muted">Auto generated branch-wise.</div>
            </div>
            <div class="field col-4">
                <label for="productName" class="required">Product Name</label>
                <input id="productName" name="product_name" type="text" maxlength="180" required
                       autocomplete="off" placeholder="Enter product name"
                       data-required-message="Product Name is required.">
            </div>
            <div class="field col-4">
                <label for="productType" class="required">Product Type</label>
                <select id="productType" name="product_type" required
                        data-required-message="Product Type is required.">
                    <option value="2">Finished Product</option>
                    <option value="1">Raw Material</option>
                    <option value="3">Consumable</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="field col-4">
                <label for="categoryId" class="required">Category</label>
                <div class="input-group">
                    <div class="input-group-control">
                        <select id="categoryId" name="category_id" required
                                data-placeholder="Select or type Category"
                                data-error-id="categoryError"
                                data-required-message="Category is required.">
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <button class="btn btn-primary input-group-button" id="addCategoryButton" type="button"
                            title="Add Category" aria-label="Add Category">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
                <small id="categoryError" class="validation-error"></small>
            </div>
            <div class="field col-4">
                <label for="subcategoryId">Subcategory</label>
                <div class="input-group">
                    <div class="input-group-control">
                        <select id="subcategoryId" name="subcategory_id"
                                data-placeholder="Select or type Subcategory"
                                data-error-id="subcategoryError">
                            <option value="">Select Subcategory</option>
                        </select>
                    </div>
                    <button class="btn btn-primary input-group-button" id="addSubcategoryButton" type="button"
                            title="Add Subcategory" aria-label="Add Subcategory">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
                <small id="subcategoryError" class="validation-error"></small>
            </div>
            <div class="field col-4">
                <label for="productStatus" class="required">Status</label>
                <select id="productStatus" name="status" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
        <div class="card-section-title">Unit Details</div>
        <div class="form-row">
            <div class="field col-4">
                <label for="primaryUnitId" class="required">Primary Unit</label>
                <div class="input-group">
                    <div class="input-group-control">
                        <select id="primaryUnitId" name="primary_unit_id" required
                                data-placeholder="Select or type Primary Unit"
                                data-error-id="primaryUnitError"
                                data-required-message="Primary Unit is required.">
                            <option value="">Select Primary Unit</option>
                        </select>
                    </div>
                    <button class="btn btn-primary input-group-button" id="addPrimaryUnitButton" type="button"
                            title="Add Unit" aria-label="Add Primary Unit">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
                <small id="primaryUnitError" class="validation-error"></small>
            </div>
            <div class="field col-4">
                <label for="secondaryUnitId">Secondary Unit</label>
                <div class="input-group">
                    <div class="input-group-control">
                        <select id="secondaryUnitId" name="secondary_unit_id"
                                data-placeholder="Select or type Secondary Unit"
                                data-error-id="secondaryUnitError">
                            <option value="">Select Secondary Unit</option>
                        </select>
                    </div>
                    <button class="btn btn-primary input-group-button" id="addSecondaryUnitButton" type="button"
                            title="Add Unit" aria-label="Add Secondary Unit">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
                <small id="secondaryUnitError" class="validation-error"></small>
            </div>
            <div class="field col-4">
                <label for="secondaryConversion">Conversion Qty</label>
                <input id="secondaryConversion" name="secondary_conversion" type="text" inputmode="decimal"
                       placeholder="Example: 24"
                       data-validation="decimal" data-decimal-places="3"
                       data-decimal-message="Enter a valid Conversion Qty.">
                <div class="muted" id="conversionHelp">Select a Secondary Unit to set conversion.</div>
            </div>
        </div>
        <div class="card-section-title">HSN & Tax Details</div>
        <div class="form-row">
            <div class="field col-4">
                <label for="hsnId">HSN Code</label>
                <div class="input-group">
                    <div class="input-group-control">
                        <select id="hsnId" name="hsn_id"
                                data-placeholder="Select or type HSN"
                                data-error-id="hsnError">
                            <option value="">Select HSN</option>
                        </select>
                    </div>
                    <button class="btn btn-primary input-group-button" id="addHsnButton" type="button"
                            title="Add HSN" aria-label="Add HSN">
                        <i data-lucide="plus"></i>
                    </button>
                </div>
                <small id="hsnError" class="validation-error"></small>
            </div>
            <div class="field col-4">
                <label for="gstRate">GST %</label>
                <input id="gstRate" type="text" value="0.00" readonly aria-readonly="true">
            </div>
            <div class="field col-4">
                <label for="cessRate">Cess %</label>
                <input id="cessRate" type="text" value="0.00" readonly aria-readonly="true">
            </div>
        </div>
        <div class="form-row">
            <div class="field col-4">
                <label for="cgstRate">CGST %</label>
                <input id="cgstRate" type="text" value="0.00" readonly aria-readonly="true">
            </div>
            <div class="field col-4">
                <label for="sgstRate">SGST %</label>
                <input id="sgstRate" type="text" value="0.00" readonly aria-readonly="true">
            </div>
            <div class="field col-4">
                <label for="igstRate">IGST %</label>
                <input id="igstRate" type="text" value="0.00" readonly aria-readonly="true">
            </div>
        </div>
        <div class="card-section-title">Product Pricing</div>
        <div class="form-row">
            <div class="field col-3">
                <label for="enterMrp" class="required">Enter MRP</label>
                <input id="enterMrp" name="enter_mrp" type="text" inputmode="decimal" required
                       placeholder="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-required-message="Enter MRP is required."
                       data-decimal-message="Enter a valid MRP.">
            </div>
            <div class="field col-3">
                <label for="gstType" class="required">GST Type</label>
                <select id="gstType" name="gst_type" required>
                    <option value="1">Inclusive</option>
                    <option value="2" selected>Exclusive</option>
                </select>
                <div class="muted" id="gstTypeInfo">GST rate comes from HSN Master.</div>
            </div>
            <div class="field col-3">
                <label for="finalMrp">Final MRP</label>
                <input id="finalMrp" name="final_mrp" type="text" value="0.00" readonly
                       aria-readonly="true" tabindex="-1">
            </div>
            <div class="field col-3">
                <label for="purchasePrice" class="required">Purchase Price / Stock Price</label>
                <input id="purchasePrice" name="purchase_price" type="text" inputmode="decimal" required
                       placeholder="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-required-message="Purchase Price / Stock Price is required."
                       data-decimal-message="Enter a valid Purchase Price / Stock Price.">
            </div>
        </div>
        <div class="card-section-title card-section-title-actions">
            <span>Sale Base Pricing</span>
            <button class="btn btn-primary" id="quickAddSellerTypeButton" type="button" aria-haspopup="dialog" aria-controls="quickSellerTypeDialog">
                <i data-lucide="plus"></i> Quick Add Seller Type
            </button>
        </div>
        <div class="card table-card">
            <table id="salePricingTable">
                <thead>
                <tr>
                    <th>Use</th>
                    <th>Seller Type</th>
                    <th>Markup Type</th>
                    <th>Markup Value</th>
                    <th>Sale Price</th>
                    <th>Profit</th>
                    <th>Profit Margin %</th>
                </tr>
                </thead>
                <tbody id="salePricingBody">
                    <tr><td colspan="7" class="empty">Loading Seller Types...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="muted">
            Active Seller Types automatically load from Seller Type Master. Check Use for the pricing groups
            applicable to this product. Sale Price, Profit and Margin are calculated from Purchase / Stock Price;
            Sale Price cannot exceed Final MRP. Add new Seller Types using Quick Add.
        </div>
        <div class="card-section-title">Stock Settings</div>
        <div class="form-row">
            <div class="field col-4">
                <label for="reorderLevel">Reorder Level</label>
                <input id="reorderLevel" name="reorder_level" type="text" inputmode="decimal"
                       placeholder="0.000"
                       data-validation="decimal" data-decimal-places="3"
                       data-decimal-message="Enter a valid Reorder Level.">
            </div>
            <div class="field col-4">
                <label for="trackBatch">Track Batch</label>
                <select id="trackBatch" name="track_batch">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            <div class="field col-4">
                <label for="trackExpiry">Track Expiry</label>
                <select id="trackExpiry" name="track_expiry">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
                <div class="muted">Expiry tracking is available only when Batch tracking is enabled.</div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <div class="buttons">
            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i> Save Product
            </button>
            <a class="btn gray" href="product-list.php">Cancel</a>
        </div>
    </div>
</form>
</div>
<?php require __DIR__ . '/modal/category.php'; ?>
<?php require __DIR__ . '/modal/subcategory.php'; ?>
<?php require __DIR__ . '/modal/hsn.php'; ?>
<?php require __DIR__ . '/modal/unit.php'; ?>

<?php require __DIR__ . '/modal/seller-type.php'; ?>
<script>
(function (window, document) {
    "use strict";
    var form = document.getElementById("productForm");
    var reference = new URLSearchParams(window.location.search).get("ref") || "";
    var saveButton = document.getElementById("saveButton");
    var salePricingBody = document.getElementById("salePricingBody");
    var isLoading = false;
    var isFilling = false;
    var categoryRows = [];
    var subcategoryRows = [];
    var hsnRows = [];
    var unitRows = [];
    var salePrices = [];
    var sellerTypeRows = [];
    var canEditProduct = false;
    var hsnMap = {};
    var unitMap = {};
    var categorySelect = GlobalSelect.init("#categoryId", {
        placeholder: "Select or type Category"
    });
    var subcategorySelect = GlobalSelect.init("#subcategoryId", {
        placeholder: "Select or type Subcategory"
    });
    var hsnSelect = GlobalSelect.init("#hsnId", {
        placeholder: "Select or type HSN"
    });
    var primaryUnitSelect = GlobalSelect.init("#primaryUnitId", {
        placeholder: "Select or type Primary Unit"
    });
    var secondaryUnitSelect = GlobalSelect.init("#secondaryUnitId", {
        placeholder: "Select or type Secondary Unit"
    });
    function apiData(response) {
        if (!response || typeof response !== "object") return {};
        return response.data && typeof response.data === "object" ? response.data : response;
    }
    function apiOptions(data) {
        if (!data || typeof data !== "object") return {};
        return data.options && typeof data.options === "object" ? data.options : data;
    }
    function hasAction(actions, actionId) {
        return (actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }
    function numberValue(value) {
        var valueNumber = Number(value || 0);
        return Number.isFinite(valueNumber) ? valueNumber : 0;
    }
    function round2(value) {
        return Math.round((numberValue(value) + Number.EPSILON) * 100) / 100;
    }
    function formatRate(value) {
        return numberValue(value).toFixed(2);
    }
    function formatDecimalValue(value) {
        if (value === null || value === undefined || value === "") return "";
        var valueNumber = Number(value);
        return Number.isFinite(valueNumber) ? String(valueNumber) : "";
    }
    function money(value) {
        return "₹" + numberValue(value).toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    function escapeHtml(value) {
        if (window.App && typeof App.escapeHtml === "function") {
            return App.escapeHtml(String(value == null ? "" : value));
        }
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function optionItems(rows, valueKey, textBuilder) {
        return (rows || []).map(function (row) {
            return { value: row[valueKey], text: textBuilder(row) };
        });
    }
    function optionText(code, name, status) {
        var text = code ? (code + " - " + name) : name;
        if (Number(status) === 0) text += " (Inactive)";
        return text;
    }
    function unitText(row) {
        var text = row.unit_symbol ? (row.unit_symbol + " - " + row.unit_name) : row.unit_name;
        if (Number(row.status) === 0) text += " (Inactive)";
        return text;
    }
    function hsnText(row) {
        var text = row.hsn_code || "";
        if (row.description) text += " - " + row.description;
        text += " - GST " + formatRate(row.gst_rate) + "%";
        if (Number(row.status) === 0) text += " (Inactive)";
        return text;
    }
    function upsertRow(rows, row) {
        var found = false;
        var result = (rows || []).map(function (item) {
            if (Number(item.id) === Number(row.id)) {
                found = true;
                return row;
            }
            return item;
        });
        if (!found) result.push(row);
        return result;
    }
    function setCategoryOptions(selectedValue) {
        categorySelect.setOptions(
            optionItems(categoryRows, "id", function (row) {
                return optionText(row.category_code, row.category_name, row.status);
            }),
            selectedValue || ""
        );
    }
    function setSubcategoryOptions(selectedValue) {
        subcategorySelect.setOptions(
            optionItems(subcategoryRows, "id", function (row) {
                return optionText(row.category_code, row.category_name, row.status);
            }),
            selectedValue || ""
        );
    }
    function rebuildHsnMap() {
        hsnMap = {};
        hsnRows.forEach(function (row) {
            hsnMap[String(row.id)] = row;
        });
    }
    function setHsnOptions(selectedValue) {
        rebuildHsnMap();
        hsnSelect.setOptions(optionItems(hsnRows, "id", hsnText), selectedValue || "");
        applyHsnTaxes();
    }
    function rebuildUnitMap() {
        unitMap = {};
        unitRows.forEach(function (row) {
            unitMap[String(row.id)] = row;
        });
    }
    function setUnitOptions(primaryValue, secondaryValue) {
        rebuildUnitMap();
        var items = optionItems(unitRows, "id", unitText);
        primaryUnitSelect.setOptions(items, primaryValue || "");
        secondaryUnitSelect.setOptions(items, secondaryValue || "");
        updateConversionState();
    }
    function currentHsn() {
        return hsnMap[String(form.hsn_id.value || "")] || null;
    }
    function applyHsnTaxes() {
        var row = currentHsn();
        document.getElementById("gstRate").value = formatRate(row && row.gst_rate);
        document.getElementById("cgstRate").value = formatRate(row && row.cgst_rate);
        document.getElementById("sgstRate").value = formatRate(row && row.sgst_rate);
        document.getElementById("igstRate").value = formatRate(row && row.igst_rate);
        document.getElementById("cessRate").value = formatRate(row && row.cess_rate);
        calculateFinalMrp();
    }
    function calculateFinalMrp() {
        var entered = numberValue(form.enter_mrp.value);
        var gstType = Number(form.gst_type.value || 2);
        var hsn = currentHsn();
        var gstRate = numberValue(hsn && hsn.gst_rate);
        var cessRate = numberValue(hsn && hsn.cess_rate);
        var totalTaxRate = gstRate + cessRate;
        var finalMrp = entered;
        if (gstType === 2) {
            finalMrp = round2(entered + (entered * totalTaxRate / 100));
            document.getElementById("gstTypeInfo").textContent =
                "GST/Cess added from HSN Master (" + totalTaxRate.toFixed(2) + "%).";
        } else {
            document.getElementById("gstTypeInfo").textContent =
                "Entered MRP already includes GST/Cess (" + totalTaxRate.toFixed(2) + "%).";
        }
        form.final_mrp.value = finalMrp.toFixed(2);
        /* Current Product API compatibility until the Product API is migrated. */
        document.getElementById("legacyPurchaseTaxType").value = String(gstType);
        document.getElementById("legacySaleTaxType").value = String(gstType);
        recalculateSalePrices();
        return finalMrp;
    }
    function unitLabel(id) {
        var row = unitMap[String(id || "")] || null;
        if (!row) return "Primary Unit";
        return row.unit_symbol || row.unit_name || "Primary Unit";
    }
    function updateConversionState() {
        var primaryId = Number(form.primary_unit_id.value || 0);
        var secondaryId = Number(form.secondary_unit_id.value || 0);
        var conversion = form.secondary_conversion;
        var help = document.getElementById("conversionHelp");
        if (!secondaryId) {
            conversion.required = false;
            conversion.disabled = true;
            conversion.value = "";
            help.textContent = "Select a Secondary Unit to set conversion.";
            return;
        }
        conversion.disabled = false;
        conversion.required = true;
        conversion.setAttribute("data-required-message", "Conversion Qty is required when Secondary Unit is selected.");
        help.textContent = primaryId
            ? ("1 " + unitLabel(primaryId) + " = Conversion Qty × " + unitLabel(secondaryId) + ".")
            : "Select a Primary Unit and enter the conversion quantity.";
    }
    function updateExpiryState() {
        var enabled = Number(form.track_batch.value) === 1;
        form.track_expiry.disabled = !enabled;
        if (!enabled) form.track_expiry.value = "0";
    }
    async function loadSubcategories(categoryId, selectedId) {
        if (!categoryId) {
            subcategoryRows = [];
            setSubcategoryOptions("");
            return;
        }
        var url = "api/products.php?subcategories=1&category_id=" + encodeURIComponent(categoryId);
        if (selectedId) url += "&include_subcategory_id=" + encodeURIComponent(selectedId);
        var response = await App.api(url);
        var data = apiData(response);
        subcategoryRows = Array.isArray(data.subcategories) ? data.subcategories : [];
        setSubcategoryOptions(selectedId || "");
    }
    function normalizeSellerTypeName(value) {
        return String(value == null ? "" : value).trim().replace(/\s+/g, " ");
    }
    function calculateSellerPrice(row) {
        var stockPrice = numberValue(form.purchase_price.value);
        var markupValue = Math.max(0, numberValue(row.markup_value));
        var markupAmount = Number(row.markup_type) === 2
            ? markupValue
            : round2(stockPrice * markupValue / 100);
        var salePrice = round2(stockPrice + markupAmount);
        var profit = round2(salePrice - stockPrice);
        row.sale_price = salePrice;
        row.profit = profit;
        row.profit_margin = salePrice > 0 ? round2((profit / salePrice) * 100) : 0;
        return row;
    }
    function orderedSellerTypes() {
        return sellerTypeRows.slice().sort(function (a, b) {
            return Number(a.sort_order || 0) - Number(b.sort_order || 0) ||
                String(a.seller_type_name || "").localeCompare(String(b.seller_type_name || "")) ||
                Number(a.id) - Number(b.id);
        });
    }
    function syncSellerTypeRows(saved, isNewProduct, newSellerId) {
        var previous = {};
        salePrices.forEach(function (row) { previous[String(row.seller_type_id)] = row; });
        (saved || []).forEach(function (row) {
            if (!row || !Number(row.seller_type_id)) return;
            var id = String(row.seller_type_id);
            previous[id] = {
                seller_type_id: Number(row.seller_type_id),
                seller_type_name: normalizeSellerTypeName(row.seller_type_name),
                seller_type_status: Number(row.seller_type_status) === 0 ? 0 : 1,
                enabled: true,
                markup_type: Number(row.markup_type || 1),
                markup_value: formatDecimalValue(row.markup_value),
                sale_price: numberValue(row.sale_price),
                profit: 0,
                profit_margin: 0
            };
        });
        salePrices = orderedSellerTypes().filter(function (type) {
            return Number(type.status) === 1 || Boolean(previous[String(type.id)] && previous[String(type.id)].enabled);
        }).map(function (type) {
            var id = String(type.id);
            var prior = previous[id];
            var enabled = prior ? Boolean(prior.enabled) : (isNewProduct || Number(type.id) === Number(newSellerId));
            return {
                seller_type_id: Number(type.id),
                seller_type_name: normalizeSellerTypeName(type.seller_type_name),
                seller_type_status: Number(type.status) === 0 ? 0 : 1,
                enabled: enabled,
                markup_type: prior ? Number(prior.markup_type || 1) : 1,
                markup_value: prior ? prior.markup_value : '0',
                sale_price: prior ? numberValue(prior.sale_price) : 0,
                profit: 0,
                profit_margin: 0
            };
        });
        renderSalePrices();
    }
    function syncSalePricesJson() {
        var payload = salePrices.filter(function (row) { return row.enabled; }).map(function (row) {
            calculateSellerPrice(row);
            return {
                seller_type_id: Number(row.seller_type_id),
                markup_type: Number(row.markup_type || 1),
                markup_value: round2(row.markup_value),
                sale_price: round2(row.sale_price)
            };
        });
        form.sale_prices_json.value = JSON.stringify(payload);
        var retail = salePrices.find(function (row) {
            return row.enabled && normalizeSellerTypeName(row.seller_type_name).toLowerCase() === 'retail';
        });
        var fallback = retail || salePrices.find(function (row) { return row.enabled; });
        form.sale_price.value = fallback ? numberValue(calculateSellerPrice(fallback).sale_price).toFixed(2) : '0.00';
    }
    function renderSalePrices() {
        if (!salePrices.length) {
            salePricingBody.innerHTML = '<tr><td colspan="7" class="empty">No active Seller Types found. Click Quick Add Seller Type.</td></tr>';
            syncSalePricesJson();
            return;
        }
        salePrices.forEach(calculateSellerPrice);
        salePricingBody.innerHTML = salePrices.map(function (row, index) {
            var inactive = Number(row.seller_type_status) === 0;
            var disabled = !row.enabled || !canEditProduct;
            var saleTooHigh = row.enabled && numberValue(form.final_mrp.value) > 0 && row.sale_price > numberValue(form.final_mrp.value) + 0.001;
            return '<tr data-index="' + index + '">' +
                '<td><input class="js-use-price" type="checkbox" aria-label="Use ' + escapeHtml(row.seller_type_name) + '" ' + (row.enabled ? 'checked ' : '') + (!canEditProduct || inactive ? 'disabled ' : '') + '></td>' +
                '<td><strong>' + escapeHtml(row.seller_type_name) + '</strong>' + (inactive ? ' <span class="badge gray">Inactive (existing)</span>' : '') + '</td>' +
                '<td><select class="js-markup-type" ' + (disabled ? 'disabled' : '') + '>' +
                    '<option value="1" ' + (Number(row.markup_type) === 1 ? 'selected' : '') + '>Percentage</option>' +
                    '<option value="2" ' + (Number(row.markup_type) === 2 ? 'selected' : '') + '>Fixed</option>' +
                '</select></td>' +
                '<td><input class="js-markup-value" type="text" inputmode="decimal" value="' + escapeHtml(formatDecimalValue(row.markup_value)) + '" placeholder="0.00" ' + (disabled ? 'disabled' : '') + '></td>' +
                '<td><input class="js-sale-price" type="text" value="' + escapeHtml(row.sale_price.toFixed(2)) + '" readonly tabindex="-1" aria-readonly="true" ' + (saleTooHigh ? 'aria-invalid="true"' : '') + '></td>' +
                '<td><input class="js-profit" type="text" value="' + escapeHtml(row.profit.toFixed(2)) + '" readonly tabindex="-1" aria-readonly="true"></td>' +
                '<td><input class="js-profit-margin" type="text" value="' + escapeHtml(row.profit_margin.toFixed(2) + '%') + '" readonly tabindex="-1" aria-readonly="true"></td>' +
            '</tr>';
        }).join('');
        syncSalePricesJson();
        if (window.lucide) window.lucide.createIcons();
    }
    function recalculateSalePrices() {
        salePrices.forEach(calculateSellerPrice);
        renderSalePrices();
    }
    function rememberSellerTypes(rows) {
        (rows || []).forEach(function (row) {
            if (!row || !Number(row.id)) return;
            var id = Number(row.id);
            sellerTypeRows = sellerTypeRows.filter(function (type) { return Number(type.id) !== id; });
            sellerTypeRows.push(row);
        });
    }
    function populateFormOptions(options, product) {
        options = options || {};
        product = product || {};
        categoryRows = Array.isArray(options.categories) ? options.categories : [];
        subcategoryRows = Array.isArray(options.subcategories) ? options.subcategories : [];
        hsnRows = Array.isArray(options.hsn_codes) ? options.hsn_codes : [];
        unitRows = Array.isArray(options.units) ? options.units : [];
        sellerTypeRows = Array.isArray(options.seller_types) ? options.seller_types : [];
        setCategoryOptions(product.category_id || "");
        setSubcategoryOptions(product.subcategory_id || "");
        setHsnOptions(product.hsn_id || "");
        setUnitOptions(product.primary_unit_id || "", product.secondary_unit_id || "");
    }
    function fillProduct(product) {
        product = product || {};
        form.ref.value = product.ref || reference || "";
        form.product_code.value = product.product_code || "";
        form.product_name.value = product.product_name || "";
        form.product_type.value = String(Number(product.product_type || 2));
        form.status.value = String(Number(product.status) === 0 ? 0 : 1);
        form.secondary_conversion.value = formatDecimalValue(product.secondary_conversion);
        form.enter_mrp.value = formatDecimalValue(
            product.enter_mrp !== undefined ? product.enter_mrp :
            (product.mrp !== undefined ? product.mrp : "")
        );
        form.gst_type.value = String(Number(product.gst_type || product.sale_tax_type || 2));
        form.purchase_price.value = formatDecimalValue(product.purchase_price);
        form.reorder_level.value = formatDecimalValue(product.reorder_level);
        form.track_batch.value = String(Number(product.track_batch) === 1 ? 1 : 0);
        form.track_expiry.value = String(Number(product.track_expiry) === 1 ? 1 : 0);
        syncSellerTypeRows(Array.isArray(product.sale_prices) ? product.sale_prices : [], false, 0);
        updateConversionState();
        updateExpiryState();
        applyHsnTaxes();
        renderSalePrices();
    }
    function validateProductExtras() {
        updateConversionState();
        updateExpiryState();
        calculateFinalMrp();
        syncSalePricesJson();
        var errors = {};
        var primaryUnitId = Number(form.primary_unit_id.value || 0);
        var secondaryUnitId = Number(form.secondary_unit_id.value || 0);
        var conversion = String(form.secondary_conversion.value || "").trim();
        var enteredMrp = numberValue(form.enter_mrp.value);
        var stockPrice = numberValue(form.purchase_price.value);
        var finalMrp = numberValue(form.final_mrp.value);
        var reorder = String(form.reorder_level.value || "").trim();
        if (secondaryUnitId) {
            if (secondaryUnitId === primaryUnitId) {
                errors.secondary_unit_id = "Secondary Unit must be different from Primary Unit.";
            }
            if (conversion === "" || !Number.isFinite(Number(conversion)) || Number(conversion) <= 0) {
                errors.secondary_conversion = "Conversion Qty must be greater than zero.";
            }
        }
        if (enteredMrp <= 0) {
            errors.enter_mrp = "Enter MRP must be greater than zero.";
        }
        if (stockPrice <= 0) {
            errors.purchase_price = "Purchase Price / Stock Price must be greater than zero.";
        }
        if (reorder !== "" && (!Number.isFinite(Number(reorder)) || Number(reorder) < 0)) {
            errors.reorder_level = "Reorder Level must be zero or greater.";
        }
        if (Object.keys(errors).length) {
            Validation.applyErrors(form, errors);
            return false;
        }
        var usedSellerTypes = {};
        for (var i = 0; i < salePrices.length; i++) {
            var row = salePrices[i];
            if (!row.enabled) continue;
            calculateSellerPrice(row);
            var sellerId = Number(row.seller_type_id);
            if (!sellerId || usedSellerTypes[sellerId]) {
                showToast('Select each Seller Type only once.', { type: 'danger', duration: 3 });
                return false;
            }
            usedSellerTypes[sellerId] = true;
            if (![1, 2].includes(Number(row.markup_type))) {
                showToast('Select a valid Markup Type for ' + row.seller_type_name + '.', { type: 'danger', duration: 3 });
                return false;
            }
            var markupText = String(row.markup_value == null ? '' : row.markup_value).trim();
            if (markupText === '' || !Number.isFinite(Number(markupText)) || Number(markupText) < 0) {
                showToast('Enter a valid Markup Value for ' + row.seller_type_name + '.', { type: 'danger', duration: 3 });
                return false;
            }
            if (finalMrp > 0 && row.sale_price > finalMrp + 0.001) {
                showToast(row.seller_type_name + ': Sale Price cannot exceed Final MRP.', { type: 'danger', duration: 3 });
                return false;
            }
        }
        return true;
    }
    async function load() {
        if (isLoading) return;
        isLoading = true;
        try {
            if (reference) {
                document.getElementById("pageHeading").textContent = "Edit Product";
                saveButton.innerHTML = '<i data-lucide="save"></i> Update Product';
                var response = await App.api("api/products.php?ref=" + encodeURIComponent(reference));
                var data = apiData(response);
                var product = data.product || {};
                var options = apiOptions(data);
                canEditProduct = hasAction(data.allowed_actions || [], 3);
                isFilling = true;
                populateFormOptions(options, product);
                fillProduct(product);
                isFilling = false;
                if (!hasAction(data.allowed_actions || [], 3)) saveButton.disabled = true;
            } else {
                var response = await App.api("api/products.php?options=1");
                var data = apiData(response);
                var options = apiOptions(data);
                canEditProduct = hasAction(data.allowed_actions || [], 2);
                form.product_code.value = data.next_product_code || "";
                populateFormOptions(options, {});
                syncSellerTypeRows([], true, 0);
                updateExpiryState();
                calculateFinalMrp();
                renderSalePrices();
                if (!hasAction(data.allowed_actions || [], 2)) saveButton.disabled = true;
            }
            document.getElementById('quickAddSellerTypeButton').disabled = !canEditProduct;
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            saveButton.disabled = true;
            document.getElementById('quickAddSellerTypeButton').disabled = true;
            App.showError(error, "Unable to load Product form.");
        } finally {
            isLoading = false;
        }
    }
    document.getElementById("categoryId").addEventListener("change", async function () {
        if (isFilling) return;
        try {
            await loadSubcategories(form.category_id.value, "");
        } catch (error) {
            subcategoryRows = [];
            setSubcategoryOptions("");
            App.showError(error, "Unable to load Subcategories.");
        }
    });
    document.getElementById("hsnId").addEventListener("change", applyHsnTaxes);
    document.getElementById("primaryUnitId").addEventListener("change", updateConversionState);
    document.getElementById("secondaryUnitId").addEventListener("change", updateConversionState);
    form.track_batch.addEventListener("change", updateExpiryState);
    form.enter_mrp.addEventListener("input", calculateFinalMrp);
    form.gst_type.addEventListener("change", calculateFinalMrp);
    form.purchase_price.addEventListener("input", recalculateSalePrices);
    var quickSellerDialog = document.getElementById('quickSellerTypeDialog');
    var quickSellerForm = document.getElementById('quickSellerTypeForm');
    var quickSellerTrigger = document.getElementById('quickAddSellerTypeButton');
    var quickSellerSaving = false;
    var quickSellerPreviousFocus = null;

    function openQuickSellerModal() {
        if (!canEditProduct || quickSellerDialog.classList.contains('open')) return;
        quickSellerPreviousFocus = document.activeElement;
        quickSellerForm.reset();
        Validation.clearForm(quickSellerForm);
        var maxSort = sellerTypeRows.reduce(function (max, item) {
            return Math.max(max, Number(item.sort_order || 0));
        }, 0);
        document.getElementById('quickSellerTypeSort').value = String(maxSort + 10);
        quickSellerDialog.classList.add('open');
        quickSellerDialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        document.getElementById('quickSellerTypeName').focus();
        if (window.lucide) window.lucide.createIcons();
    }

    function closeQuickSellerModal() {
        if (quickSellerSaving || !quickSellerDialog.classList.contains('open')) return;
        quickSellerDialog.classList.remove('open');
        quickSellerDialog.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal-backdrop.open')) {
            document.body.classList.remove('modal-open');
        }
        if (quickSellerPreviousFocus && typeof quickSellerPreviousFocus.focus === 'function') {
            quickSellerPreviousFocus.focus();
        }
    }

    quickSellerTrigger.addEventListener('click', openQuickSellerModal);
    document.getElementById('cancelQuickSellerType').addEventListener('click', closeQuickSellerModal);
    document.getElementById('quickSellerTypeClose').addEventListener('click', closeQuickSellerModal);
    quickSellerDialog.addEventListener('click', function (event) {
        if (event.target === quickSellerDialog) closeQuickSellerModal();
    });
    document.addEventListener('keydown', function (event) {
        if (!quickSellerDialog.classList.contains('open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeQuickSellerModal();
            return;
        }
        if (event.key !== 'Tab') return;
        var focusable = Array.prototype.slice.call(quickSellerDialog.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]'
        )).filter(function (node) { return node.getClientRects().length > 0; });
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
        }
    });
    quickSellerForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        Validation.clearForm(quickSellerForm);
        if (!Validation.validateForm(quickSellerForm)) return;
        var name = normalizeSellerTypeName(quickSellerForm.elements['seller_type_name'].value);
        if (!name) {
            Validation.applyErrors(quickSellerForm, { seller_type_name: 'Seller Type Name is required.' });
            return;
        }
        if (sellerTypeRows.some(function (item) { return normalizeSellerTypeName(item.seller_type_name).toLowerCase() === name.toLowerCase(); })) {
            showToast('Seller Type already exists.', { type: 'warning', duration: 3 });
            return;
        }
        var submit = document.getElementById('saveQuickSellerType');
        submit.disabled = true;
        quickSellerSaving = true;
        try {
            var body = new FormData(quickSellerForm);
            body.set('seller_type_name', name);
            var response = await App.api('api/seller-types.php', { method: 'POST', body: body });
            var created = apiData(response).seller_type || null;
            if (!created || !Number(created.id)) throw new Error('Seller Type was created but the API did not return its ID.');
            rememberSellerTypes([created]);
            // Reload master list only, without disturbing the unsaved Product form.
            try {
                var optionsResponse = await App.api('api/products.php?options=1');
                rememberSellerTypes(apiOptions(apiData(optionsResponse)).seller_types || []);
            } catch (refreshError) {
                // The created Seller Type was returned by the save response, so continue.
            }
            syncSellerTypeRows([], !reference, Number(created.id));
            quickSellerSaving = false;
            closeQuickSellerModal();
            showToast(response.message || 'Seller Type created successfully.', { type: 'success', duration: 2 });
        } catch (error) {
            Validation.applyErrors(quickSellerForm, error.errors || {});
            App.showError(error, 'Unable to add Seller Type. Check Seller Type Create permission.');
        } finally {
            quickSellerSaving = false;
            submit.disabled = false;
        }
    });
    salePricingBody.addEventListener('change', function (event) {
        var tr = event.target.closest('tr[data-index]');
        if (!tr || !canEditProduct) return;
        var row = salePrices[Number(tr.dataset.index)];
        if (!row) return;
        if (event.target.classList.contains('js-use-price')) {
            row.enabled = event.target.checked;
        }
        if (event.target.classList.contains('js-markup-type')) {
            row.markup_type = Number(event.target.value || 1);
        }
        renderSalePrices();
    });
    salePricingBody.addEventListener('input', function (event) {
        var tr = event.target.closest('tr[data-index]');
        if (!tr || !canEditProduct) return;
        var row = salePrices[Number(tr.dataset.index)];
        if (!row || !row.enabled) return;
        if (event.target.classList.contains('js-markup-value')) {
            row.markup_value = event.target.value;
            calculateSellerPrice(row);
            syncSalePricesJson();
            var saleInput = tr.querySelector('.js-sale-price');
            var profitInput = tr.querySelector('.js-profit');
            var marginInput = tr.querySelector('.js-profit-margin');
            if (saleInput) {
                saleInput.value = row.sale_price.toFixed(2);
                saleInput.setAttribute('aria-invalid',
                    numberValue(form.final_mrp.value) > 0 && row.sale_price > numberValue(form.final_mrp.value) + 0.001 ? 'true' : 'false');
            }
            if (profitInput) profitInput.value = row.profit.toFixed(2);
            if (marginInput) marginInput.value = row.profit_margin.toFixed(2) + '%';
        }
    });
    document.getElementById("addCategoryButton").addEventListener("click", function () {
        if (!window.AppCategoryForm) {
            App.showError(null, "Reusable Category modal is unavailable.");
            return;
        }
        AppCategoryForm.openCreate({
            onSaved: function (category) {
                if (!category) return;
                categoryRows = upsertRow(categoryRows, category);
                setCategoryOptions(category.id);
                loadSubcategories(category.id, "").catch(function (error) {
                    App.showError(error, "Unable to load Subcategories.");
                });
            }
        });
    });
    document.getElementById("addSubcategoryButton").addEventListener("click", function () {
        var categoryId = Number(form.category_id.value || 0);
        if (!categoryId) {
            showToast("Please select a Category first.", { type: "warning", duration: 2 });
            return;
        }
        if (!window.AppSubcategoryForm) {
            App.showError(null, "Reusable Subcategory modal is unavailable.");
            return;
        }
        AppSubcategoryForm.openCreate({
            parentId: categoryId,
            onSaved: function (subcategory) {
                if (!subcategory) return;
                subcategoryRows = upsertRow(subcategoryRows, subcategory);
                setSubcategoryOptions(subcategory.id);
            }
        });
    });
    document.getElementById("addHsnButton").addEventListener("click", function () {
        if (!window.AppHSNForm) {
            App.showError(null, "Reusable HSN modal is unavailable.");
            return;
        }
        AppHSNForm.openCreate({
            onSaved: function (hsn) {
                if (!hsn) return;
                hsnRows = upsertRow(hsnRows, hsn);
                setHsnOptions(hsn.id);
            }
        });
    });
    function openUnitCreate(target) {
        if (!window.AppUnitForm) {
            App.showError(null, "Reusable Unit modal is unavailable.");
            return;
        }
        AppUnitForm.openCreate({
            onSaved: function (unit) {
                if (!unit) return;
                var primaryId = form.primary_unit_id.value || "";
                var secondaryId = form.secondary_unit_id.value || "";
                unitRows = upsertRow(unitRows, unit);
                if (target === "primary") primaryId = unit.id;
                else secondaryId = unit.id;
                setUnitOptions(primaryId, secondaryId);
            }
        });
    }
    document.getElementById("addPrimaryUnitButton").addEventListener("click", function () {
        openUnitCreate("primary");
    });
    document.getElementById("addSecondaryUnitButton").addEventListener("click", function () {
        openUnitCreate("secondary");
    });
    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        updateConversionState();
        updateExpiryState();
        calculateFinalMrp();
        if (!Validation.validateForm(form)) return;
        if (!validateProductExtras()) return;
        syncSalePricesJson();
        var data = new FormData(form);
        if (reference) data.set("_method", "PUT");
        saveButton.disabled = true;
        try {
            var response = await App.api("api/products.php", {
                method: "POST",
                body: data
            });
            var result = response || {};
            showToast(result.message || "Product saved successfully.", { type: "success", duration: 2 });
            window.setTimeout(function () {
                window.location.href = "product-list.php";
            }, 700);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save Product.");
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
