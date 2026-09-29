<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier List';
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
<script src="assets/js/datatable.js"></script>

<div class="page-head">
    <div>
        <h1>Supplier List</h1>
        <p>Tenant branch suppliers with server-side search, paging, sorting and export.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="supplier-form.php">
        <i data-lucide="truck"></i>Add Supplier
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="truck"></i></span>
        <div><div class="kpi-label">Total Suppliers</div><div class="kpi-value" id="kpiTotal">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active Suppliers</div><div class="kpi-value" id="kpiActive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div><div class="kpi-label">Inactive Suppliers</div><div class="kpi-value" id="kpiInactive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div><div class="kpi-label">Opening Balance</div><div class="kpi-value" id="kpiOpening">₹0.00</div></div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-5">
                <label for="supplierSearch">Search</label>
                <input id="supplierSearch" type="text" autocomplete="off" placeholder="Code, supplier, contact, GSTIN...">
            </div>

            <div class="field col-3">
                <label for="gstFilter">GSTIN</label>
                <select id="gstFilter">
                    <option value="">All Suppliers</option>
                    <option value="with">With GSTIN</option>
                    <option value="without">Without GSTIN</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="table-scroll">
        <table id="supplierTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Code</th>
                <th>Supplier</th>
                <th>Contact Person</th>
                <th>Contact</th>
                <th>GSTIN</th>
                <th>State</th>
                <th>Opening Balance</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function ($, window, document) {
    "use strict";

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var listActions = [];
    var formActions = [];
    var has = AppDataTable.has;
    var searchTimer = null;

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

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_suppliers || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_suppliers || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_suppliers || 0).toLocaleString("en-IN");

        document.getElementById("kpiOpening").textContent =
            money(summary.opening_balance || 0);
    }

    var table = AppDataTable.init("#supplierTable", {
        serverSide: true,
        searching: true,
        searchDelay: 350,
        appSearch: false,
        appLoaderText: "Loading suppliers...",
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [],
        scrollX: true,
        autoWidth: false,

        buttons: [
            { extend:"copyHtml5", text:"Copy", title:"Supplier List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5,6,7]} },
            { extend:"csvHtml5", text:"CSV", title:"Supplier List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5,6,7]} },
            { extend:"excelHtml5", text:"Excel", title:"Supplier List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5,6,7]} },
            { extend:"pdfHtml5", text:"PDF", title:"Supplier List", orientation:"landscape", pageSize:"A4", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5,6,7]} },
            { extend:"print", text:"Print", title:"Supplier List", action:AppDataTable.serverSideExportAction, exportOptions:{columns:[0,1,2,3,4,5,6,7]} }
        ],

        ajax: function (data, callback) {
            var params = new URLSearchParams();

            params.set("datatable", "1");
            params.set("draw", data.draw);
            params.set("start", data.start);
            params.set("length", data.length);
            params.set("search[value]", data.search.value || "");

            var gst = document.getElementById("gstFilter").value;
            var status = document.getElementById("statusFilter").value;

            if (gst !== "") params.set("gst_filter", gst);
            if (status !== "") params.set("status", status);

            if (data.order && data.order[0]) {
                params.set("order[0][column]", data.order[0].column);
                params.set("order[0][dir]", data.order[0].dir);
            }

            App.api("api/suppliers.php?" + params.toString()).then(function (result) {
                listActions = (result.data.list_actions || []).map(Number);
                formActions = (result.data.form_actions || []).map(Number);

                document.getElementById("addButton").style.display =
                    has(formActions, 2) ? "inline-flex" : "none";

                setSummary(result.data.summary);
                AppDataTable.applyExportPermissions(table, listActions);
                callback(result.data.datatable);
            }).catch(function (error) {
                setSummary({});
                App.showError(error, "Unable to load suppliers.");
                document.getElementById("addButton").style.display = "none";

                callback({
                    draw: data.draw,
                    recordsTotal: 0,
                    recordsFiltered: 0,
                    data: []
                });
            });
        },

        columns: [
            { data:"supplier_code", defaultContent:"-" },
            { data:"supplier_name", defaultContent:"-" },
            { data:"contact_person", defaultContent:"-" },
            {
                data:null,
                orderable:false,
                render:function(data,type,row) {
                    var text = [row.mobile, row.email].filter(Boolean).join(" · ") || "-";
                    return type === "display" ? escapeHtml(text) : text;
                }
            },
            { data:"gstin", defaultContent:"-" },
            { data:"state_code", defaultContent:"-" },
            {
                data:"opening_balance",
                className:"dt-body-right",
                render:function(value,type) {
                    return type === "display" ? money(value) : Number(value || 0);
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

                    var actions = [];

                    if (has(formActions, 3)) {
                        actions.push(App.iconActionHtml({
                            href: row.edit_url,
                            icon: "pencil",
                            label: "Edit supplier"
                        }));
                    }

                    if (Number(row.status) === 1 && has(listActions, 28)) {
                        actions.push(
                            '<button type="button" class="btn small gray js-supplier-status" ' +
                            'data-ref="' + escapeHtml(row.ref) + '" data-action="deactivate" ' +
                            'title="Deactivate supplier">Deactivate</button>'
                        );
                    }

                    if (Number(row.status) === 0 && has(listActions, 27)) {
                        actions.push(
                            '<button type="button" class="btn small gray js-supplier-status" ' +
                            'data-ref="' + escapeHtml(row.ref) + '" data-action="activate" ' +
                            'title="Activate supplier">Activate</button>'
                        );
                    }

                    return actions.length ? actions.join(" ") : '<span class="muted">View only</span>';
                }
            }
        ],

        language: {
            emptyTable:"No suppliers found.",
            zeroRecords:"No matching suppliers found.",
            processing:"Loading suppliers..."
        },

        drawCallback:function() {
            if (window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearchRow() {
        var tableElement = document.getElementById("supplierTable");
        var card = tableElement ? tableElement.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if (row) row.remove();
    })();

    var searchField = document.getElementById("supplierSearch");

    searchField.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(searchField.value.trim()).draw();
        },350);
    });

    ["gstFilter","statusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    $("#supplierTable").on("click", ".js-supplier-status", async function () {
        var button = this;
        var ref = button.getAttribute("data-ref") || "";
        var action = button.getAttribute("data-action") || "";

        if (!ref || (action !== "activate" && action !== "deactivate")) return;

        var label = action === "activate" ? "activate" : "deactivate";

        if (!window.confirm("Are you sure you want to " + label + " this supplier?")) return;

        button.disabled = true;

        try {
            var data = new FormData();
            data.set("action", action);
            data.set("ref", ref);

            var result = await App.api("api/suppliers.php", {
                method: "POST",
                body: data
            });

            showToast(result.message, { type:"success", duration:2 });
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change supplier status.");
        } finally {
            button.disabled = false;
        }
    });

})(window.jQuery, window, document);
</script>

        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
