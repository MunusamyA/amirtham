<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Product List';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
];
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
    <?php foreach ($headStyles as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ($headScripts as $scriptUrl): ?>
    <script src="<?php echo web_h($scriptUrl); ?>"></script>
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
<script src="assets/js/datatable.js"></script>

<div class="page-head">
    <div>
        <h1>Product List</h1>
        <p>Manage Product MRP, Stock Price and manually entered Seller Type Sale Base Pricing.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="product-form.php">
        <i data-lucide="package-plus"></i>Add Product
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="package"></i></span>
        <div><div class="kpi-label">Total Products</div><div class="kpi-value" id="kpiTotalProducts">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active Products</div><div class="kpi-value" id="kpiActiveProducts">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div><div class="kpi-label">Inactive Products</div><div class="kpi-value" id="kpiInactiveProducts">0</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-4">
                <label for="productSearch">Search</label>
                <input id="productSearch" type="text" autocomplete="off" placeholder="Search product...">
            </div>

            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="productTypeFilter">Product Type</label>
                <select id="productTypeFilter">
                    <option value="">All</option>
                    <option value="1">Raw Material</option>
                    <option value="2">Finished Product</option>
                    <option value="3">Consumable</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter">
                    <option value="">All</option>
                </select>
            </div>
        </div>
    </div>

    <table id="productTable" class="display data-table" style="width:100%">
        <thead>
        <tr>
            <th>Code</th>
            <th>Product</th>
            <th>Category</th>
            <th>Subcategory</th>
            <th>Type</th>
            <th>Units</th>
            <th>Final MRP</th>
            <th>Stock Price</th>
            <th>Sale Base Pricing</th>
            <th>Status</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<script>
(function ($, window) {
    "use strict";

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var listActions = [];
    var formActions = [];
    var has = AppDataTable.has;
    var statusFilter = "";
    var typeFilter = "";
    var categoryFilter = "";
    var searchTimer = null;

    var productSearch = document.getElementById("productSearch");
    var statusFilterInput = document.getElementById("statusFilter");
    var productTypeFilterInput = document.getElementById("productTypeFilter");
    var categoryFilterInput = document.getElementById("categoryFilter");

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function money(value) {
        var amount = Number(value || 0);
        if (!Number.isFinite(amount)) amount = 0;

        return "₹" + amount.toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function setSummary(summary) {
        summary = summary || {};
        document.getElementById("kpiTotalProducts").textContent =
            Number(summary.total_products || 0).toLocaleString("en-IN");
        document.getElementById("kpiActiveProducts").textContent =
            Number(summary.active_products || 0).toLocaleString("en-IN");
        document.getElementById("kpiInactiveProducts").textContent =
            Number(summary.inactive_products || 0).toLocaleString("en-IN");
    }

    function qty(value) {
        var number = Number(value || 0);
        if (!Number.isFinite(number)) number = 0;

        return number.toLocaleString("en-IN", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        });
    }


    var table = AppDataTable.init("#productTable", {
        serverSide: true,
        searching: true,
        searchDelay: 350,
        appSearch: false,
        appSearchPlaceholder: "Search product, category, subcategory or unit...",
        appLoaderText: "Loading Products...",
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0,"asc"]],
        scrollX: true,
        autoWidth: false,
        buttons: [
            {extend:"copyHtml5",text:"Copy",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"csvHtml5",text:"CSV",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"excelHtml5",text:"Excel",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"pdfHtml5",text:"PDF",title:"Product List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
            {extend:"print",text:"Print",title:"Product List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}
        ],
        ajax:function(data, callback) {
            var params = new URLSearchParams();

            params.set("datatable", "1");
            params.set("draw", data.draw);
            params.set("start", data.start);
            params.set("length", data.length);
            params.set("search[value]", data.search.value || "");

            if (statusFilter !== "") params.set("status", statusFilter);
            if (typeFilter !== "") params.set("product_type", typeFilter);
            if (categoryFilter !== "") params.set("category_id", categoryFilter);

            if (data.order && data.order[0]) {
                params.set("order[0][column]", data.order[0].column);
                params.set("order[0][dir]", data.order[0].dir);
            }

            App.api("api/products.php?" + params.toString()).then(function(result) {
                listActions = (result.data.list_actions || []).map(Number);
                formActions = (result.data.form_actions || []).map(Number);
                setSummary(result.data.summary);

                document.getElementById("addButton").style.display =
                    has(formActions, 2) ? "inline-flex" : "none";

                AppDataTable.applyExportPermissions(table, listActions);
                callback(result.data.datatable);
            }).catch(function(error) {
                setSummary({});
                document.getElementById("addButton").style.display = "none";
                App.showError(error, "Unable to load Products.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"product_code",defaultContent:"-"},
            {data:"product_name",defaultContent:"-"},
            {data:"category_name",defaultContent:"-"},
            {data:"subcategory_name",defaultContent:"-"},
            {
                data:"product_type_label",
                render:function(value,type) {
                    return type === "display" ? escapeHtml(value || "-") : (value || "");
                }
            },
            {
                data:null,
                orderable:false,
                render:function(data,type,row) {
                    var primary = row.primary_unit_symbol || row.primary_unit_name || "-";
                    var secondary = row.secondary_unit_symbol || row.secondary_unit_name || "";
                    var text = primary;

                    if (secondary) {
                        text += " / " + secondary;

                        if (Number(row.secondary_conversion || 0) > 0) {
                            text += " (1 " + primary + " = " + qty(row.secondary_conversion) + " " + secondary + ")";
                        }
                    }

                    return type === "display" ? escapeHtml(text) : text;
                }
            },
            {
                data:"final_mrp",
                render:function(value,type,row) {
                    var text = money(value);
                    if (type !== "display") return text + " / " + (row.gst_type_label || "");
                    return '<div>' + escapeHtml(text) + '</div>' +
                           '<small class="muted">' + escapeHtml(row.gst_type_label || "-") + '</small>';
                }
            },
            {
                data:"purchase_price",
                render:function(value,type) {
                    var text = money(value);
                    return type === "display" ? escapeHtml(text) : text;
                }
            },
            {
                data:"sale_prices",
                orderable:false,
                searchable:false,
                render:function(value,type) {
                    var prices = Array.isArray(value) ? value : [];

                    if (!prices.length) {
                        return type === "display"
                            ? '<span class="muted">Not set</span>'
                            : 'Not set';
                    }

                    if (type !== "display") {
                        return prices.map(function(item) {
                            var label = item.seller_type_name || "Seller";
                            var markup = Number(item.markup_type) === 2
                                ? ("Fixed " + money(item.markup_value))
                                : (Number(item.markup_value || 0).toFixed(2) + "%");
                            return label + ": " + money(item.sale_price) + " [" + markup + "]";
                        }).join("; ");
                    }

                    return prices.map(function(item) {
                        var label = item.seller_type_name || "Seller";
                        var markup = Number(item.markup_type) === 2
                            ? ("Fixed " + money(item.markup_value))
                            : (Number(item.markup_value || 0).toFixed(2) + "%");

                        return '<div><strong>' + escapeHtml(label) + '</strong>: ' +
                               escapeHtml(money(item.sale_price)) +
                               ' <span class="muted">(' + escapeHtml(markup) + ')</span></div>';
                    }).join("");
                }
            },
            {
                data:"status",
                render:function(value,type) {
                    if (type !== "display") return Number(value);

                    return Number(value) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:"table-action-icons",
                render:function(data,type,row) {
                    if (type !== "display") return "";

                    var html = "";

                    if (has(formActions, 3)) {
                        html += '<a class="table-icon-action" href="' + escapeHtml(row.edit_url) + '" title="Edit Product" aria-label="Edit Product"><i data-lucide="pencil"></i></a>';
                    }

                    if (Number(row.status) === 1 && has(listActions, 28)) {
                        html += '<button type="button" class="table-icon-action danger js-product-status" data-ref="' + escapeHtml(row.ref) + '" data-action="deactivate" title="Deactivate Product" aria-label="Deactivate Product"><i data-lucide="circle-off"></i></button>';
                    }

                    if (Number(row.status) === 0 && has(listActions, 27)) {
                        html += '<button type="button" class="table-icon-action success js-product-status" data-ref="' + escapeHtml(row.ref) + '" data-action="activate" title="Activate Product" aria-label="Activate Product"><i data-lucide="circle-check"></i></button>';
                    }

                    return html || '<span class="muted">View only</span>';
                }
            }
        ],
        language:{
            emptyTable:"No Product records found.",
            zeroRecords:"No matching Product records found."
        },
        drawCallback:function(){
            if (window.lucide) window.lucide.createIcons();
        }
    });

    /* Product List uses normal form controls in the card header.
       Keep DataTables only as the table engine. */
    (function removeDefaultSearchRow() {
        var tableElement = document.getElementById("productTable");
        var card = tableElement ? tableElement.closest(".table-card") : null;
        var searchRow = card ? card.querySelector(".app-table-search-row") : null;
        if (searchRow) searchRow.remove();
    })();

    productSearch.addEventListener("input", function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            table.search(productSearch.value.trim()).draw();
        }, 350);
    });

    statusFilterInput.addEventListener("change", function () {
        statusFilter = this.value;
        table.ajax.reload();
    });

    productTypeFilterInput.addEventListener("change", function () {
        typeFilter = this.value;
        table.ajax.reload();
    });

    categoryFilterInput.addEventListener("change", function () {
        categoryFilter = this.value;
        table.ajax.reload();
    });

    App.api("api/products.php?list_options=1").then(function(result) {
        categoryFilterInput.innerHTML = '<option value="">All</option>';

        (result.data.categories || []).forEach(function(row) {
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = row.category_code
                ? (row.category_code + " - " + row.category_name)
                : row.category_name;
            categoryFilterInput.appendChild(option);
        });
    }).catch(function() {
        // Product list remains usable if Category options cannot be loaded.
    });

    $("#productTable").on("click", ".js-product-status", async function() {
        var button = this;
        var ref = button.getAttribute("data-ref") || "";
        var action = button.getAttribute("data-action") || "";

        if (!ref || (action !== "activate" && action !== "deactivate")) return;

        if (!window.confirm("Are you sure you want to " + action + " this Product?")) return;

        button.disabled = true;

        try {
            var result = await App.api("api/products.php", {
                method:"PATCH",
                body:{
                    ref:ref,
                    action:action
                }
            });

            showToast(result.message, {type:"success",duration:2});
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change Product status.");
            button.disabled = false;
        }
    });
})(window.jQuery, window);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
