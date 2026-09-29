<?php

require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Sales List';

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

    <title><?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

    <?php render_frontend_config_script(); ?>

    <script src="assets/js/runtime.js"></script>

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

        <h1>Sales List</h1>

        <p>Quotation, Proforma Bill, Sales Bill and Final Invoice documents.</p>

    </div>

    <a class="btn btn-primary" id="addButton" href="sales.php" hidden>

        <i data-lucide="shopping-bag"></i>Open Sales POS

    </a>

</div>

<div class="kpi-grid">

    <article class="card kpi-card">

        <span class="kpi-icon blue"><i data-lucide="files"></i></span>

        <div>

            <div class="kpi-label">Documents</div>

            <div class="kpi-value" id="kpiDocuments">0</div>

        </div>

    </article>

    <article class="card kpi-card">

        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>

        <div>

            <div class="kpi-label">Grand Total</div>

            <div class="kpi-value" id="kpiGrandTotal">₹0.00</div>

        </div>

    </article>

    <article class="card kpi-card">

        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>

        <div>

            <div class="kpi-label">Paid</div>

            <div class="kpi-value" id="kpiPaid">₹0.00</div>

        </div>

    </article>

    <article class="card kpi-card">

        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>

        <div>

            <div class="kpi-label">Balance</div>

            <div class="kpi-value" id="kpiBalance">₹0.00</div>

        </div>

    </article>

</div>

<div class="card table-card">

    <div class="card-header">

        <div class="form-row">

            <div class="field col-4">

                <label for="salesSearch">Search</label>

                <input id="salesSearch" type="text" autocomplete="off" placeholder="Search sales...">

            </div>

            <div class="field col-2">

                <label for="documentTypeFilter">Document Type</label>

                <select id="documentTypeFilter">

                    <option value="">All</option>

                    <option value="1">Quotation</option>

                    <option value="2">Proforma Bill</option>

                    <option value="3">Sales Bill</option>

                    <option value="4">Final Invoice</option>

                </select>

            </div>

            <div class="field col-2" id="taxModeField" hidden>

                <label for="taxModeFilter">Tax Mode</label>

                <select id="taxModeFilter">

                    <option value="1" selected>GST</option>

                    <option value="0">Non-GST</option>

                </select>

            </div>

            <div class="field col-2">

                <label for="dateFromFilter">From Date</label>

                <input id="dateFromFilter" type="date">

            </div>

            <div class="field col-2">

                <label for="dateToFilter">To Date</label>

                <input id="dateToFilter" type="date">

            </div>

        </div>

    </div>

    <table id="salesTable" class="display data-table">

        <thead>

        <tr>

            <th>Document No</th>

            <th>Date</th>

            <th>Document Type</th>

            <th>Customer</th>

            <th>Tax Mode</th>

            <th>Grand Total</th>

            <th>Paid</th>

            <th>Balance</th>

            <th>Payment</th>

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

    var searchTimer = null;

    var salesSearch = document.getElementById("salesSearch");

    var documentTypeFilter = document.getElementById("documentTypeFilter");

    var taxModeField = document.getElementById("taxModeField");

    var taxModeFilter = document.getElementById("taxModeFilter");

    var dateFromFilter = document.getElementById("dateFromFilter");

    var dateToFilter = document.getElementById("dateToFilter");

    var addButton = document.getElementById("addButton");

    var has = AppDataTable.has;

    var ACTION_QUOTATION = 55;

    var ACTION_PROFORMA = 56;

    var ACTION_SALES_BILL = 57;

    var ACTION_FINAL_INVOICE = 58;

    var TAX_MODE_STATE_KEY = "amirtham:sales-list:non-gst";

    var nonGstMode = false;

    try {

        nonGstMode = window.sessionStorage.getItem(TAX_MODE_STATE_KEY) === "1";

    } catch (e) {

        nonGstMode = false;

    }

    function documentActionId(type) {

        type = Number(type);

        if (type === 1) return ACTION_QUOTATION;

        if (type === 2) return ACTION_PROFORMA;

        if (type === 3) return ACTION_SALES_BILL;

        if (type === 4) return ACTION_FINAL_INVOICE;

        return 0;

    }

    function canGenerate(type) {

        return has(formActions, 3) && has(formActions, documentActionId(type));

    }

    function targetUrl(row, type) {

        var targetRef = row.target_refs && row.target_refs[String(type)]

            ? row.target_refs[String(type)]

            : "";

        if (!targetRef) return row.open_url || ("sales.php?ref=" + encodeURIComponent(row.ref));

        return "sales.php?ref=" + encodeURIComponent(row.ref)

            + "&target=" + encodeURIComponent(targetRef);

    }

    function escapeHtml(value) {

        return String(value === null || value === undefined ? "" : value)

            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")

            .replace(/"/g,"&quot;").replace(/'/g,"&#039;");

    }

    function money(value) {

        var number = Number(value || 0);

        if (!Number.isFinite(number)) number = 0;

        return "₹" + number.toLocaleString("en-IN", {minimumFractionDigits:2,maximumFractionDigits:2});

    }

    function setSummary(summary) {

        summary = summary || {};

        document.getElementById("kpiDocuments").textContent =

            Number(summary.document_count || 0).toLocaleString("en-IN");

        document.getElementById("kpiGrandTotal").textContent =

            money(summary.grand_total || 0);

        document.getElementById("kpiPaid").textContent =

            money(summary.paid_amount || 0);

        document.getElementById("kpiBalance").textContent =

            money(summary.balance_amount || 0);

    }

    function removeDefaultSearch() {

        var tableElement = document.getElementById("salesTable");

        var card = tableElement ? tableElement.closest(".table-card") : null;

        var wrapper = document.getElementById("salesTable_wrapper");

        [card, wrapper].forEach(function(root) {

            if (!root) return;

            root.querySelectorAll(

                "#salesTable_filter,.dataTables_filter,.app-table-search-row"

            ).forEach(function(node) {

                node.remove();

            });

        });

    }

    var table = AppDataTable.init("#salesTable", {

        serverSide: true,

        searching: true,

        searchDelay: 350,

        appSearch: false,

        appSearchPlaceholder: "Search Sales...",

        appLoaderText: "Loading Sales Documents...",

        pageLength: 10,

        lengthMenu: [[10,25,50,100],[10,25,50,100]],

        order: [[1,"desc"]],

        scrollX: true,

        autoWidth: false,

        buttons: [

            {extend:"copyHtml5",text:"Copy",title:"Sales List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},

            {extend:"csvHtml5",text:"CSV",title:"Sales List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},

            {extend:"excelHtml5",text:"Excel",title:"Sales List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},

            {extend:"pdfHtml5",text:"PDF",title:"Sales List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},

            {extend:"print",text:"Print",title:"Sales List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}}

        ],

        ajax: function (data, callback) {

            var params = new URLSearchParams();

            params.set("datatable", "1");

            params.set("draw", data.draw);

            params.set("start", data.start);

            params.set("length", data.length);

            params.set("search[value]", data.search.value || "");

            if (documentTypeFilter.value !== "") params.set("document_type", documentTypeFilter.value);

            /* Tax mode is controlled independently so all other filters keep the active mode. */

            params.set("tax_mode", nonGstMode ? "0" : "1");

            if (dateFromFilter.value) params.set("date_from", dateFromFilter.value);

            if (dateToFilter.value) params.set("date_to", dateToFilter.value);

            if (data.order && data.order[0]) {

                params.set("order[0][column]", data.order[0].column);

                params.set("order[0][dir]", data.order[0].dir);

            }

            App.api("api/sales.php?" + params.toString()).then(function (result) {

                listActions = (result.data.allowed_actions || []).map(Number);

                formActions = (result.data.form_actions || []).map(Number);

                setSummary(result.data.summary);

                addButton.hidden = !has(formActions, 2);

                AppDataTable.applyExportPermissions(table, listActions);

                callback(result.data.datatable);

            }).catch(function (error) {

                setSummary({});

                addButton.hidden = true;

                App.showError(error, "Unable to load Sales Documents.");

                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});

            });

        },

        columns: [

            {data:"sales_no",defaultContent:"-"},

            {data:"invoice_date",defaultContent:"-"},

            {

                data:"document_type_label",

                render:function(value,type){

                    if(type!=="display") return value || "";

                    return escapeHtml(value || "-");

                }

            },

            {

                data:null,

                render:function(data,type,row){

                    var name=row.customer_name || row.customer_name_snapshot || "-";

                    var code=row.customer_code || "";

                    var text=code ? code+" - "+name : name;

                    return type==="display" ? escapeHtml(text) : text;

                }

            },

            {

                data:"tax_mode_label",

                visible:false,

                render:function(value,type,row){

                    if(type!=="display") return Number(row.tax_mode);

                    return Number(row.tax_mode)===0 ? 'Non-GST' : 'GST';

                }

            },

            {data:"grand_total",className:"dt-body-right",render:function(value,type){var text=money(value);return type==="display"?escapeHtml(text):Number(value||0);}},

            {data:"paid_amount",className:"dt-body-right",render:function(value,type,row){if(Number(row.document_type)!==4)return type==="display"?'<span class="muted">-</span>':0;var text=money(value);return type==="display"?escapeHtml(text):Number(value||0);}},

            {data:"balance_amount",className:"dt-body-right",render:function(value,type,row){if(Number(row.document_type)!==4)return type==="display"?'<span class="muted">-</span>':0;var text=money(value);return type==="display"?escapeHtml(text):Number(value||0);}},

            {

                data:"payment_status_label",

                render:function(value,type,row){

                    if(Number(row.document_type)!==4) return type==="display"?'<span class="muted">Document</span>':"Document";

                    if(type!=="display") return value || "";

                    return escapeHtml(value || "-");

                }

            },

            {

                data:null,orderable:false,searchable:false,className:"table-action-icons",

                render:function(data,type,row){

                    if(type!=="display") return "";

                    var html="";

                    var sourceType=Number(row.document_type);

                    if(has(formActions,3)) {

                        html += '<a class="table-icon-action" href="'+escapeHtml(row.open_url)+'" title="Open / Edit" aria-label="Open / Edit"><i data-lucide="pencil"></i></a>';

                    } else if(has(formActions,1)) {

                        html += '<a class="table-icon-action" href="'+escapeHtml(row.open_url)+'" title="View" aria-label="View"><i data-lucide="eye"></i></a>';

                    }

                    /* Final Invoice is final. Pre-sale documents can start any other target directly from this list. */

                    if(sourceType!==4){

                        if(sourceType!==1 && canGenerate(1)) html += '<a class="table-icon-action" href="'+escapeHtml(targetUrl(row,1))+'" title="Generate Quotation" aria-label="Generate Quotation"><i data-lucide="file-text"></i></a>';

                        if(sourceType!==2 && canGenerate(2)) html += '<a class="table-icon-action" href="'+escapeHtml(targetUrl(row,2))+'" title="Generate Proforma Bill" aria-label="Generate Proforma Bill"><i data-lucide="files"></i></a>';

                        if(sourceType!==3 && canGenerate(3)) html += '<a class="table-icon-action" href="'+escapeHtml(targetUrl(row,3))+'" title="Generate Sales Bill" aria-label="Generate Sales Bill"><i data-lucide="receipt-text"></i></a>';

                        if(sourceType!==4 && canGenerate(4)) html += '<a class="table-icon-action success" href="'+escapeHtml(targetUrl(row,4))+'" title="Generate Final Invoice" aria-label="Generate Final Invoice"><i data-lucide="badge-check"></i></a>';

                    }

                    if(has(listActions,9)) {

                        html += '<a class="table-icon-action" href="sales-print.php?ref='+encodeURIComponent(row.ref)+'" target="_blank" title="Print Document" aria-label="Print Document"><i data-lucide="printer"></i></a>';

                    }

                    return html || '<span class="muted">View only</span>';

                }

            }

        ],

        language:{emptyTable:"No Sales documents found.",zeroRecords:"No matching Sales documents found."},

        drawCallback:function(){

            removeDefaultSearch();

            if(window.lucide)window.lucide.createIcons();

        }

    });

    /* Sales List uses only the custom filter-row Search input. */

    removeDefaultSearch();

    if (typeof MutationObserver !== "undefined") {

        var salesTableElement = document.getElementById("salesTable");

        var salesCard = salesTableElement ? salesTableElement.closest(".table-card") : null;

        if (salesCard) {

            new MutationObserver(function(){

                removeDefaultSearch();

            }).observe(salesCard, {childList:true, subtree:true});

        }

    }

    salesSearch.addEventListener("input",function(){

        window.clearTimeout(searchTimer);

        searchTimer=window.setTimeout(function(){table.search(salesSearch.value.trim()).draw();},350);

    });

    function persistTaxMode() {

        try {

            window.sessionStorage.setItem(TAX_MODE_STATE_KEY, nonGstMode ? "1" : "0");

        } catch (e) {}

    }

    function applyTaxModeUi(redraw) {

        /*

         * Default = GST rows only.

         * Once Ctrl + Shift + U activates Non-GST, that mode remains active

         * while Search / Document Type / Date / paging / export filters are used.

         * It changes back only when Tax Mode is explicitly changed or the shortcut

         * is pressed again.

         */

        taxModeFilter.value = nonGstMode ? "0" : "1";

        taxModeField.hidden = !nonGstMode;

        table.column(4).visible(nonGstMode, false);

        table.columns.adjust();

        persistTaxMode();

        if (redraw) {

            table.draw(false);

        }

    }

    documentTypeFilter.addEventListener("change",function(){table.ajax.reload();});

    dateFromFilter.addEventListener("change",function(){table.ajax.reload();});

    dateToFilter.addEventListener("change",function(){table.ajax.reload();});

    taxModeFilter.addEventListener("change",function(){

        nonGstMode = taxModeFilter.value === "0";

        applyTaxModeUi(false);

        table.ajax.reload(null, true);

    });

    /* Restore the current tab's mode. A fresh tab defaults to GST. */

    applyTaxModeUi(false);

    document.addEventListener("keydown",function(event){

        if(event.repeat) return;

        if(event.ctrlKey && event.shiftKey && String(event.key).toLowerCase() === "u"){

            event.preventDefault();

            nonGstMode = !nonGstMode;

            applyTaxModeUi(false);

            table.ajax.reload(null, true);

        }

    });

})(window.jQuery,window);

</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>

</main>

</div>

<script>if(window.lucide){window.lucide.createIcons();}</script>

<script src="assets/js/appearance.js"></script>

</body>

</html>
