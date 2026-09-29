<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Supplier Ledger';

$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css',
];

$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js',
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

    <?php foreach ($headStyles as $url): ?>
    <link rel="stylesheet" href="<?php echo web_h($url); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $url): ?>
    <script src="<?php echo web_h($url); ?>"></script>
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
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1>Supplier Ledger</h1>
        <p>Supplier purchases, payments, returns and running payable balance.</p>
    </div>

    <a class="btn gray" href="supplier-list.php">
        <i data-lucide="truck"></i>Supplier List
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue">
            <i data-lucide="wallet"></i>
        </span>
        <div>
            <div class="kpi-label">Opening Balance</div>
            <div class="kpi-value" id="kpiOpening">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green">
            <i data-lucide="shopping-cart"></i>
        </span>
        <div>
            <div class="kpi-label">Purchases</div>
            <div class="kpi-value" id="kpiPurchases">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal">
            <i data-lucide="hand-coins"></i>
        </span>
        <div>
            <div class="kpi-label">Payments</div>
            <div class="kpi-value" id="kpiPayments">₹0.00</div>
            <div class="kpi-meta">Returns: <span id="kpiReturns">₹0.00</span></div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange">
            <i data-lucide="indian-rupee"></i>
        </span>
        <div>
            <div class="kpi-label">Closing Balance</div>
            <div class="kpi-value" id="kpiClosing">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">

            <div class="field col-3">
                <label for="supplierRef">Supplier</label>
                <select id="supplierRef">
                    <option value="">Select Supplier</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="dateFrom">From Date</label>
                <input id="dateFrom" type="date">
            </div>

            <div class="field col-2">
                <label for="dateTo">To Date</label>
                <input id="dateTo" type="date">
            </div>

            <div class="field col-2">
                <label for="transactionType">Transaction Type</label>
                <select id="transactionType">
                    <option value="">All Transactions</option>
                    <option value="purchase">Purchase</option>
                    <option value="purchase_payment">Purchase Payment</option>
                    <option value="purchase_return">Purchase Return</option>
                    <option value="supplier_payment">Supplier Payment</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="ledgerSearch">Search</label>
                <input id="ledgerSearch" type="text" autocomplete="off"
                       placeholder="Reference or transaction...">
            </div>

        </div>
    </div>

    <div class="table-scroll">
        <table id="supplierLedgerTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Transaction</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Balance</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
    "use strict";

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowedActions = [];
    var searchTimer = null;
    var supplierSelect = null;
    var transactionTypeSelect = null;
    var initialSupplierRef =
        new URLSearchParams(window.location.search).get("supplier_ref") || "";

    function esc(value){
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function money(value){
        var amount = Number(value || 0);
        if(!Number.isFinite(amount)) amount = 0;

        return "₹" + amount.toLocaleString("en-IN",{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiOpening").textContent =
            money(summary.opening_balance || 0);

        document.getElementById("kpiPurchases").textContent =
            money(summary.purchases || 0);

        document.getElementById("kpiPayments").textContent =
            money(summary.payments || 0);

        document.getElementById("kpiReturns").textContent =
            money(summary.returns || 0);

        document.getElementById("kpiClosing").textContent =
            money(summary.closing_balance || 0);
    }

    function fillSuppliers(rows){
        var select = document.getElementById("supplierRef");
        var current = select.value || initialSupplierRef;

        var items = (rows || []).map(function(row){
            return {
                value: row.ref,
                text:
                    (row.supplier_code
                        ? row.supplier_code + " - " + row.supplier_name
                        : row.supplier_name) +
                    (row.mobile ? " | " + row.mobile : "")
            };
        });

        if(supplierSelect && typeof supplierSelect.setOptions === "function"){
            supplierSelect.setOptions(items,current);
        }else{
            select.innerHTML = '<option value="">Select Supplier</option>';

            items.forEach(function(item){
                var option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.text;
                option.selected = String(item.value) === String(current);
                select.appendChild(option);
            });
        }

        initialSupplierRef = "";
    }

    if(window.GlobalSelect){
        supplierSelect = GlobalSelect.init(
            document.getElementById("supplierRef"),
            { placeholder:"Select Supplier" }
        );

        transactionTypeSelect = GlobalSelect.init(
            document.getElementById("transactionType"),
            { placeholder:"All Transactions" }
        );
    }

    var table = AppDataTable.init("#supplierLedgerTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        pageLength:25,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[],
        ordering:false,
        scrollX:true,
        autoWidth:false,

        buttons:[
            {
                extend:"copyHtml5",
                text:"Copy",
                title:"Supplier Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"csvHtml5",
                text:"CSV",
                title:"Supplier Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"excelHtml5",
                text:"Excel",
                title:"Supplier Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"pdfHtml5",
                text:"PDF",
                title:"Supplier Ledger",
                orientation:"landscape",
                pageSize:"A4",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"print",
                text:"Print",
                title:"Supplier Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            }
        ],

        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            var supplierRef =
                document.getElementById("supplierRef").value ||
                initialSupplierRef;
            var dateFrom = document.getElementById("dateFrom").value;
            var dateTo = document.getElementById("dateTo").value;
            var transactionType = document.getElementById("transactionType").value;

            if(supplierRef) params.set("supplier_ref",supplierRef);
            if(dateFrom) params.set("date_from",dateFrom);
            if(dateTo) params.set("date_to",dateTo);
            if(transactionType) params.set("transaction_type",transactionType);

            App.api("api/supplier-ledger.php?" + params.toString())
                .then(function(result){
                    allowedActions = (result.data.allowed_actions || []).map(Number);

                    fillSuppliers(result.data.suppliers || []);
                    setSummary(result.data.summary || {});

                    AppDataTable.applyExportPermissions(table,allowedActions);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,"Unable to load Supplier Ledger.");

                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {
                data:"date",
                defaultContent:"-"
            },
            {
                data:"reference",
                defaultContent:"-",
                render:function(v,t){
                    return t === "display" ? esc(v || "-") : (v || "");
                }
            },
            {
                data:"transaction_label",
                defaultContent:"-",
                render:function(v,t){
                    return t === "display" ? esc(v || "-") : (v || "");
                }
            },
            {
                data:"description",
                defaultContent:"-",
                orderable:false,
                render:function(v,t){
                    return t === "display" ? esc(v || "-") : (v || "");
                }
            },
            {
                data:"debit",
                className:"dt-body-right",
                render:function(v,t){
                    return t === "display" ? money(v) : Number(v || 0);
                }
            },
            {
                data:"credit",
                className:"dt-body-right",
                render:function(v,t){
                    return t === "display" ? money(v) : Number(v || 0);
                }
            },
            {
                data:"running_balance",
                className:"dt-body-right",
                render:function(v,t){
                    return t === "display" ? money(v) : Number(v || 0);
                }
            }
        ],

        language:{
            emptyTable:"Select a Supplier to view Ledger.",
            zeroRecords:"No matching ledger transactions found.",
            processing:"Loading Supplier Ledger..."
        },

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearchRow(){
        var element = document.getElementById("supplierLedgerTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if(row) row.remove();
    })();

    document.getElementById("ledgerSearch").addEventListener("input",function(){
        var field = this;

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(field.value.trim()).draw();
        },350);
    });

    [
        "supplierRef",
        "dateFrom",
        "dateTo",
        "transactionType"
    ].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>

</body>
</html>
