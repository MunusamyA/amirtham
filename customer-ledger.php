<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Customer Ledger';

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
        <h1>Customer Ledger</h1>
        <p>Customer sales, payments, returns and running receivable balance.</p>
    </div>

    <a class="btn gray" href="customer-list.php">
        <i data-lucide="users"></i>Customer List
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
            <i data-lucide="receipt-text"></i>
        </span>
        <div>
            <div class="kpi-label">Sales</div>
            <div class="kpi-value" id="kpiSales">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal">
            <i data-lucide="hand-coins"></i>
        </span>
        <div>
            <div class="kpi-label">Payments</div>
            <div class="kpi-value" id="kpiPayments">₹0.00</div>
            <div class="kpi-meta">
                Returns: <span id="kpiReturns">₹0.00</span>
                · Discounts: <span id="kpiDiscounts">₹0.00</span>
            </div>
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
                <label for="customerRef">Customer</label>
                <select id="customerRef">
                    <option value="">Select Customer</option>
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
                    <option value="all">All Transactions</option>
                    <option value="sale">Final Invoice</option>
                    <option value="payment">Customer Payment</option>
                    <option value="sale_return">Sales Return</option>
                    <option value="refund">Refund</option>
                    <option value="opening">Opening Balance</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="ledgerSearch">Search</label>
                <input id="ledgerSearch"
                       type="text"
                       autocomplete="off"
                       placeholder="Reference or transaction...">
            </div>

        </div>
    </div>

    <div class="table-scroll">
        <table id="customerLedgerTable" class="display data-table" style="width:100%">
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

    var customerGlobal = null;
    var transactionTypeGlobal = null;

    var initialCustomerRef =
        new URLSearchParams(window.location.search).get("customer_ref") ||
        new URLSearchParams(window.location.search).get("ref") ||
        "";

    function esc(value){
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function money(value){
        var amount = Number(value || 0);

        if(!Number.isFinite(amount)){
            amount = 0;
        }

        return "₹" + amount.toLocaleString("en-IN",{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function formatDateTime(value){
        if(!value) return "-";

        var text = String(value);
        var date = new Date(text.replace(" ","T"));

        if(Number.isNaN(date.getTime())){
            return text;
        }

        return date.toLocaleString("en-IN",{
            year:"numeric",
            month:"2-digit",
            day:"2-digit",
            hour:"2-digit",
            minute:"2-digit"
        });
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiOpening").textContent =
            money(summary.opening_balance || 0);

        document.getElementById("kpiSales").textContent =
            money(summary.sales || 0);

        document.getElementById("kpiPayments").textContent =
            money(summary.payments || 0);

        document.getElementById("kpiReturns").textContent =
            money(summary.returns || 0);

        document.getElementById("kpiDiscounts").textContent =
            money(summary.discounts || 0);

        document.getElementById("kpiClosing").textContent =
            money(summary.closing_balance || 0);
    }

    function fillCustomers(rows){
        var select = document.getElementById("customerRef");
        var current = select.value || initialCustomerRef;

        var items = (rows || []).map(function(row){
            return {
                value:row.ref,
                text:
                    (row.customer_code
                        ? row.customer_code + " - " + row.customer_name
                        : row.customer_name) +
                    (row.mobile ? " | " + row.mobile : "") +
                    (Number(row.status) === 0 ? " (Inactive)" : "")
            };
        });

        if(customerGlobal && typeof customerGlobal.setOptions === "function"){
            customerGlobal.setOptions(items,current);
        }else{
            select.innerHTML = '<option value="">Select Customer</option>';

            items.forEach(function(item){
                var option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.text;
                option.selected = String(item.value) === String(current);
                select.appendChild(option);
            });
        }

        initialCustomerRef = "";
    }

    if(window.GlobalSelect){
        customerGlobal = GlobalSelect.init(
            document.getElementById("customerRef"),
            {placeholder:"Select Customer"}
        );

        transactionTypeGlobal = GlobalSelect.init(
            document.getElementById("transactionType"),
            {placeholder:"All Transactions"}
        );
    }

    var table = AppDataTable.init("#customerLedgerTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        pageLength:25,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        ordering:false,
        scrollX:true,
        autoWidth:false,

        buttons:[
            {
                extend:"copyHtml5",
                text:"Copy",
                title:"Customer Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"csvHtml5",
                text:"CSV",
                title:"Customer Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"excelHtml5",
                text:"Excel",
                title:"Customer Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"pdfHtml5",
                text:"PDF",
                title:"Customer Ledger",
                orientation:"landscape",
                pageSize:"A4",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            },
            {
                extend:"print",
                text:"Print",
                title:"Customer Ledger",
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6]}
            }
        ],

        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            var customerRef =
                document.getElementById("customerRef").value ||
                initialCustomerRef;

            var dateFrom =
                document.getElementById("dateFrom").value;

            var dateTo =
                document.getElementById("dateTo").value;

            var transactionType =
                document.getElementById("transactionType").value || "all";

            if(customerRef){
                params.set("customer_ref",customerRef);
            }

            if(dateFrom){
                params.set("date_from",dateFrom);
            }

            if(dateTo){
                params.set("date_to",dateTo);
            }

            params.set("transaction_type",transactionType);

            App.api("api/customer-ledger.php?" + params.toString())
                .then(function(result){
                    allowedActions =
                        (result.data.allowed_actions || []).map(Number);

                    setSummary(result.data.period_summary || {});

                    AppDataTable.applyExportPermissions(
                        table,
                        allowedActions
                    );

                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});

                    App.showError(
                        error,
                        "Unable to load Customer Ledger."
                    );

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
                data:"event_datetime",
                render:function(value,type){
                    return type === "display"
                        ? esc(formatDateTime(value))
                        : value;
                }
            },

            {
                data:"reference_no",
                defaultContent:"-",
                render:function(value,type){
                    return type === "display"
                        ? esc(value || "-")
                        : (value || "");
                }
            },

            {
                data:"transaction_label",
                defaultContent:"-",
                render:function(value,type){
                    return type === "display"
                        ? esc(value || "-")
                        : (value || "");
                }
            },

            {
                data:"description",
                defaultContent:"-",
                orderable:false,
                render:function(value,type){
                    return type === "display"
                        ? esc(value || "-")
                        : (value || "");
                }
            },

            {
                data:"movement",
                className:"dt-body-right",
                render:function(value,type){
                    var amount = Number(value || 0);
                    var debit = amount > 0 ? amount : 0;

                    return type === "display"
                        ? money(debit)
                        : debit;
                }
            },

            {
                data:"movement",
                className:"dt-body-right",
                render:function(value,type){
                    var amount = Number(value || 0);
                    var credit = amount < 0 ? Math.abs(amount) : 0;

                    return type === "display"
                        ? money(credit)
                        : credit;
                }
            },

            {
                data:"balance",
                className:"dt-body-right",
                render:function(value,type){
                    return type === "display"
                        ? money(value)
                        : Number(value || 0);
                }
            }
        ],

        language:{
            emptyTable:"Select a Customer to view Ledger.",
            zeroRecords:"No matching ledger transactions found.",
            processing:"Loading Customer Ledger..."
        },

        drawCallback:function(){
            if(window.lucide){
                window.lucide.createIcons();
            }
        }
    });

    (function removeDefaultSearchRow(){
        var element =
            document.getElementById("customerLedgerTable");

        var card =
            element
                ? element.closest(".table-card")
                : null;

        var row =
            card
                ? card.querySelector(".app-table-search-row")
                : null;

        if(row){
            row.remove();
        }
    })();

    document.getElementById("ledgerSearch")
        .addEventListener("input",function(){
            var field = this;

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function(){
                table.search(
                    field.value.trim()
                ).draw();
            },350);
        });

    [
        "customerRef",
        "dateFrom",
        "dateTo",
        "transactionType"
    ].forEach(function(id){
        document.getElementById(id)
            .addEventListener("change",function(){

                var from =
                    document.getElementById("dateFrom").value;

                var to =
                    document.getElementById("dateTo").value;

                if(from && to && from > to){
                    showToast(
                        "From Date cannot be after To Date.",
                        {type:"error",duration:3}
                    );
                    return;
                }

                table.ajax.reload(null,true);
            });
    });

    async function loadOptions(){
        try{
            var result =
                await App.api(
                    "api/customer-ledger.php?options=1"
                );

            allowedActions =
                (result.data.allowed_actions || []).map(Number);

            fillCustomers(
                result.data.customers || []
            );

            AppDataTable.applyExportPermissions(
                table,
                allowedActions
            );

            var selected =
                document.getElementById("customerRef").value;

            if(selected){
                table.ajax.reload(null,true);
            }
        }catch(error){
            App.showError(
                error,
                "Unable to load Customer Ledger options."
            );
        }
    }

    loadOptions();

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
