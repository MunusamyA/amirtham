<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Daily Ledger';

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
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>

    <?php foreach ($headStyles as $url): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $url): ?>
        <script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"></script>
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
        <h1>Daily Ledger</h1>
        <p>Live Cash/Bank movement from Customer/Sales receipts, Purchase payments, Supplier payments, Sales Return refunds and Expense payments.</p>
    </div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="circle-dot"></i></span>
        <div>
            <div class="kpi-label">Opening Balance</div>
            <div class="kpi-value" id="sumOpening">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="arrow-down-left"></i></span>
        <div>
            <div class="kpi-label">Money In</div>
            <div class="kpi-value" id="sumIn">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="arrow-up-right"></i></span>
        <div>
            <div class="kpi-label">Money Out</div>
            <div class="kpi-value" id="sumOut">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="arrow-left-right"></i></span>
        <div>
            <div class="kpi-label">Net Movement</div>
            <div class="kpi-value" id="sumNet">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Closing Balance</div>
            <div class="kpi-value" id="sumClosing">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">

            <div class="field col-4">
                <label for="ledgerSearch">Search</label>
                <input
                    id="ledgerSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Search reference, transaction, account..."
                >
            </div>

            <div class="field col-3">
                <label for="accountFilter">Account</label>
                <select id="accountFilter">
                    <option value="">All Accounts</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="transactionType">Transaction Type</label>
                <select id="transactionType">
                    <option value="">All Transactions</option>
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

        </div>
    </div>

    <div class="table-scroll">
        <table id="ledgerTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>Reference</th>
                <th>Transaction</th>
                <th>Particular</th>
                <th>Account</th>
                <th>Money In</th>
                <th>Money Out</th>
                <th>Balance</th>
                <th>View</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($){
    'use strict';

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()){
        return;
    }

    var accountSelect = null;
    var typeSelect = null;
    var allowedActions = [];
    var searchTimer = null;

    function el(id){
        return document.getElementById(id);
    }

    function money(value){
        var number = Number(value || 0);

        return (number < 0 ? '− ' : '') +
            '₹' +
            Math.abs(number).toLocaleString('en-IN',{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            });
    }

    function incoming(value){
        var number = Number(value || 0);

        return number > 0
            ? '<span class="trend-up">' + money(number) + '</span>'
            : '<span class="muted">—</span>';
    }

    function outgoing(value){
        var number = Number(value || 0);

        return number > 0
            ? '<span class="trend-down">' + money(number) + '</span>'
            : '<span class="muted">—</span>';
    }

    var table = AppDataTable.init('#ledgerTable',{
        serverSide:false,
        processing:false,
        searching:true,
        appSearch:false,
        pageLength:25,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[],
        scrollX:true,
        autoWidth:false,
        data:[],
        language:{
            emptyTable:'No money movement for the selected filters.'
        },
        buttons:[
            {
                extend:'copyHtml5',
                text:'Copy',
                title:'Daily Ledger',
                exportOptions:{columns:[0,1,2,3,4,5,6,7]}
            },
            {
                extend:'csvHtml5',
                text:'CSV',
                title:'Daily Ledger',
                exportOptions:{columns:[0,1,2,3,4,5,6,7]}
            },
            {
                extend:'excelHtml5',
                text:'Excel',
                title:'Daily Ledger',
                exportOptions:{columns:[0,1,2,3,4,5,6,7]}
            },
            {
                extend:'pdfHtml5',
                text:'PDF',
                title:'Daily Ledger',
                orientation:'landscape',
                pageSize:'A4',
                exportOptions:{columns:[0,1,2,3,4,5,6,7]}
            },
            {
                extend:'print',
                text:'Print',
                title:'Daily Ledger',
                exportOptions:{columns:[0,1,2,3,4,5,6,7]}
            }
        ],
        columns:[
            {data:'date_display',defaultContent:'-'},
            {data:'reference',defaultContent:'-'},
            {data:'transaction',defaultContent:'-'},
            {data:'particular',defaultContent:'-'},
            {data:'mode_account',defaultContent:'-'},
            {
                data:'money_in',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? incoming(value)
                        : Number(value || 0);
                }
            },
            {
                data:'money_out',
                className:'dt-body-right',
                render:function(value,type){
                    return type === 'display'
                        ? outgoing(value)
                        : Number(value || 0);
                }
            },
            {
                data:'balance',
                className:'dt-body-right',
                render:function(value,type){
                    if(type !== 'display'){
                        return Number(value || 0);
                    }

                    var number = Number(value || 0);
                    var cls = number < 0
                        ? 'trend-down'
                        : (number > 0 ? 'trend-up' : '');

                    return '<span class="' + cls + '">' +
                        money(number) +
                        '</span>';
                }
            },
            {
                data:'view_url',
                orderable:false,
                searchable:false,
                className:'table-action-icons',
                render:function(value,type){
                    if(type !== 'display' || !value){
                        return type === 'display'
                            ? '<span class="muted">—</span>'
                            : '';
                    }

                    return App.iconActionHtml({
                        href:value,
                        icon:'eye',
                        label:'View Transaction'
                    });
                }
            }
        ],
        drawCallback:function(){
            removeDefaultSearch();

            if(window.lucide){
                window.lucide.createIcons();
            }
        }
    });

    function removeDefaultSearch(){
        var tableElement = el('ledgerTable');
        var card = tableElement ? tableElement.closest('.table-card') : null;
        var wrapper = el('ledgerTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root) return;

            root.querySelectorAll(
                '#ledgerTable_filter,.dataTables_filter,.dt-search,.app-table-search-row'
            ).forEach(function(node){
                node.remove();
            });
        });
    }

    removeDefaultSearch();

    function setSummary(summary){
        summary = summary || {};

        el('sumOpening').textContent =
            money(summary.opening_balance || 0);

        el('sumIn').textContent =
            money(summary.money_in || 0);

        el('sumOut').textContent =
            money(summary.money_out || 0);

        el('sumNet').textContent =
            money(summary.net_movement || 0);

        el('sumClosing').textContent =
            money(summary.closing_balance || 0);
    }

    function fillOptions(data){
        el('accountFilter').innerHTML =
            '<option value="">All Accounts</option>';

        (data.accounts || []).forEach(function(account){
            var option = document.createElement('option');

            option.value = account.id;
            option.textContent =
                (account.account_code ? account.account_code + ' - ' : '') +
                account.account_name;

            el('accountFilter').appendChild(option);
        });

        el('transactionType').innerHTML = '';

        (data.transaction_types || []).forEach(function(item){
            var option = document.createElement('option');

            option.value = item.value;
            option.textContent = item.label;

            el('transactionType').appendChild(option);
        });

        if(window.GlobalSelect){
            accountSelect = GlobalSelect.init(
                el('accountFilter'),
                {placeholder:'All Accounts'}
            );

            typeSelect = GlobalSelect.init(
                el('transactionType'),
                {placeholder:'All Transactions'}
            );
        }
    }

    async function loadLedger(){
        var params = new URLSearchParams();

        params.set('date_from',el('dateFrom').value);
        params.set('date_to',el('dateTo').value);
        params.set('account_id',el('accountFilter').value);
        params.set('transaction_type',el('transactionType').value);

        try{
            var response = await App.api(
                'api/daily-ledger.php?' + params.toString()
            );

            allowedActions =
                (response.data.allowed_actions || allowedActions || [])
                .map(Number);

            table
                .clear()
                .rows
                .add(response.data.transactions || [])
                .draw();

            AppDataTable.applyExportPermissions(
                table,
                allowedActions
            );

            setSummary(
                response.data.summary
            );

            if(window.lucide){
                window.lucide.createIcons();
            }
        }
        catch(error){
            table.clear().draw();
            setSummary({});

            App.showError(
                error,
                'Unable to load Daily Ledger.'
            );
        }
    }

    App.api(
        'api/daily-ledger.php?options=1'
    )
    .then(function(response){
        allowedActions =
            (response.data.allowed_actions || [])
            .map(Number);

        fillOptions(response.data);

        el('dateFrom').value =
            response.data.today ||
            new Date().toISOString().slice(0,10);

        el('dateTo').value =
            response.data.today ||
            el('dateFrom').value;

        AppDataTable.applyExportPermissions(
            table,
            allowedActions
        );

        loadLedger();
    })
    .catch(function(error){
        App.showError(
            error,
            'Unable to load Daily Ledger options.'
        );
    });

    [
        'dateFrom',
        'dateTo',
        'accountFilter',
        'transactionType'
    ].forEach(function(id){
        el(id).addEventListener(
            'change',
            loadLedger
        );
    });

    el('ledgerSearch').addEventListener(
        'input',
        function(){
            clearTimeout(searchTimer);

            searchTimer = setTimeout(function(){
                table
                    .search(
                        el('ledgerSearch').value.trim()
                    )
                    .draw();
            },250);
        }
    );

})(jQuery);
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
