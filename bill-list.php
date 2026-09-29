<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Patient Bills';

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
        <h1>Patient Billing</h1>
        <p>Bills are created Patient-wise, independent of Protocol / General Treatment basis.</p>
    </div>

    <a
        class="btn btn-primary"
        id="addBillButton"
        href="bill-form.php"
    >
        <i data-lucide="plus"></i>
        Create Bill
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="receipt-text"></i></span>
        <div>
            <div class="kpi-label">Bills</div>
            <div class="kpi-value" id="kpiBillCount">0</div>
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
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-3">
                <label for="billSearch">Search</label>
                <input
                    id="billSearch"
                    type="text"
                    placeholder="Bill No., Patient, Mobile..."
                >
            </div>

            <div class="field col-3">
                <label for="paymentStatusFilter">Payment Status</label>
                <select id="paymentStatusFilter">
                    <option value="">All Payment Status</option>
                    <option value="Paid">Paid</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="dateFromFilter">From Date</label>
                <input
                    id="dateFromFilter"
                    type="date"
                >
            </div>

            <div class="field col-3">
                <label for="dateToFilter">To Date</label>
                <input
                    id="dateToFilter"
                    type="date"
                >
            </div>
        </div>
    </div>

    <div class="table-scroll">
        <table id="billTable" class="display data-table" style="width:100%">
            <thead>
                <tr>
                    <th>Bill No.</th>
                    <th>Bill Date</th>
                    <th>Patient</th>
                    <th>Grand Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Payment Status</th>
                    <th>Created On</th>
                    <th>Manage</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
    'use strict';

    if ( !window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var listActions = [];
    var formActions = [];
    var timer = null;

    function has(actions,id) {
        return (
            Array.isArray(actions) &&
            actions.map(Number).indexOf(
                Number(id)
            ) !== -1
        );
    }

    function esc(value) {
        return $('<div>')
            .text(
                value == null
                    ? ''
                    : String(value)
            )
            .html();
    }

    function money(value) {
        var number =
            Number(value || 0);

        if (!Number.isFinite(number)) {
            number = 0;
        }

        return '₹' +
            number.toLocaleString(
                'en-IN',
                {
                    minimumFractionDigits:2,
                    maximumFractionDigits:2
                }
            );
    }

    function setSummary(summary) {
        summary =
            summary ||
            {};

        document.getElementById(
            'kpiBillCount'
        ).textContent =
            Number(
                summary.bill_count ||
                0
            ).toLocaleString('en-IN');

        document.getElementById(
            'kpiGrandTotal'
        ).textContent =
            money(
                summary.grand_total ||
                0
            );

        document.getElementById(
            'kpiPaid'
        ).textContent =
            money(
                summary.paid_amount ||
                0
            );

        document.getElementById(
            'kpiBalance'
        ).textContent =
            money(
                summary.balance_amount ||
                0
            );
    }

    function removeDefaultSearch() {
        var tableElement =
            document.getElementById(
                'billTable'
            );

        var card =
            tableElement
                ? tableElement.closest(
                    '.table-card'
                )
                : null;

        var wrapper =
            document.getElementById(
                'billTable_wrapper'
            );

        [card,wrapper].forEach(
            function(root) {
                if (!root) {
                    return;
                }

                root.querySelectorAll(
                    '#billTable_filter,.dataTables_filter,.app-table-search-row'
                ).forEach(
                    function(node) {
                        node.remove();
                    }
                );
            }
        );
    }

    if (window.GlobalSelect) {
        GlobalSelect.init(
            document.getElementById(
                'paymentStatusFilter'
            ),
            {
                placeholder:
                    'All Payment Status'
            }
        );
    }

    var table =
        AppDataTable.init(
            '#billTable',
            {
                serverSide:true,
                searching:true,
                appSearch:false,
                pageLength:10,
                lengthMenu:[
                    [10,25,50,100],
                    [10,25,50,100]
                ],
                order:[[1,'desc']],
                scrollX:true,
                autoWidth:false,

                buttons:[
                    {
                        extend:'copyHtml5',
                        text:'Copy',
                        title:'Patient Bills',
                        action:
                            AppDataTable.serverSideExportAction,
                        exportOptions:{
                            columns:[
                                0,1,2,3,4,5,6,7
                            ]
                        }
                    },
                    {
                        extend:'csvHtml5',
                        text:'CSV',
                        title:'Patient Bills',
                        action:
                            AppDataTable.serverSideExportAction,
                        exportOptions:{
                            columns:[
                                0,1,2,3,4,5,6,7
                            ]
                        }
                    },
                    {
                        extend:'excelHtml5',
                        text:'Excel',
                        title:'Patient Bills',
                        action:
                            AppDataTable.serverSideExportAction,
                        exportOptions:{
                            columns:[
                                0,1,2,3,4,5,6,7
                            ]
                        }
                    },
                    {
                        extend:'pdfHtml5',
                        text:'PDF',
                        title:'Patient Bills',
                        action:
                            AppDataTable.serverSideExportAction,
                        exportOptions:{
                            columns:[
                                0,1,2,3,4,5,6,7
                            ]
                        }
                    },
                    {
                        extend:'print',
                        text:'Print',
                        title:'Patient Bills',
                        action:
                            AppDataTable.serverSideExportAction,
                        exportOptions:{
                            columns:[
                                0,1,2,3,4,5,6,7
                            ]
                        }
                    }
                ],

                ajax:function(data,callback){
                    var params =
                        new URLSearchParams();

                    params.set(
                        'datatable',
                        '1'
                    );

                    params.set(
                        'draw',
                        data.draw
                    );

                    params.set(
                        'start',
                        data.start
                    );

                    params.set(
                        'length',
                        data.length
                    );

                    params.set(
                        'search[value]',
                        data.search.value ||
                        ''
                    );

                    params.set(
                        'payment_status',
                        document.getElementById(
                            'paymentStatusFilter'
                        ).value
                    );

                    params.set(
                        'date_from',
                        document.getElementById(
                            'dateFromFilter'
                        ).value
                    );

                    params.set(
                        'date_to',
                        document.getElementById(
                            'dateToFilter'
                        ).value
                    );

                    if (
                        data.order &&
                        data.order[0]
                    ) {
                        params.set(
                            'order[0][column]',
                            data.order[0].column
                        );

                        params.set(
                            'order[0][dir]',
                            data.order[0].dir
                        );
                    }

                    App.api(
                        'api/bills.php?' +
                        params.toString()
                    )
                    .then(
                        function(response) {
                            var payload =
                                response.data ||
                                {};

                            listActions =
                                (
                                    payload.list_actions ||
                                    []
                                ).map(Number);

                            formActions =
                                (
                                    payload.form_actions ||
                                    []
                                ).map(Number);

                            setSummary(
                                payload.summary
                            );

                            document.getElementById(
                                'addBillButton'
                            ).style.display =
                                has(
                                    formActions,
                                    2
                                )
                                    ? 'inline-flex'
                                    : 'none';

                            AppDataTable.applyExportPermissions(
                                table,
                                listActions
                            );

                            callback(
                                payload.datatable
                            );
                        }
                    )
                    .catch(
                        function(error) {
                            setSummary({});

                            App.showError(
                                error,
                                'Unable to load Patient Bills.'
                            );

                            callback({
                                draw:data.draw,
                                recordsTotal:0,
                                recordsFiltered:0,
                                data:[]
                            });
                        }
                    );
                },

                columns:[
                    {
                        data:'bill_no',
                        defaultContent:'-'
                    },
                    {
                        data:'bill_date',
                        defaultContent:'-'
                    },
                    {
                        data:null,
                        render:function(v,t,row){
                            var value =
                                (
                                    (row.patient_code || '') +
                                    ' - ' +
                                    (row.patient_name || '')
                                ).trim();

                            return (
                                t === 'display'
                                    ? esc(value)
                                    : value
                            );
                        }
                    },
                    {
                        data:'grand_total',
                        defaultContent:'0.00'
                    },
                    {
                        data:'paid_amount',
                        defaultContent:'0.00'
                    },
                    {
                        data:'balance_amount',
                        defaultContent:'0.00'
                    },
                    {
                        data:'payment_status',
                        defaultContent:'-'
                    },
                    {
                        data:'created_at',
                        defaultContent:'-'
                    },
                    {
                        data:null,
                        orderable:false,
                        searchable:false,
                        className:'table-action-icons',
                        render:function(data,type,row){
                            if (
                                type !== 'display'
                            ) {
                                return '';
                            }

                            var actions = [];

                            if (
                                has(
                                    formActions,
                                    1
                                )
                            ) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:
                                            row.view_url,

                                        icon:
                                            'eye',

                                        label:
                                            'View Bill'
                                    })
                                );
                            }

                            if (
                                has(
                                    formActions,
                                    3
                                ) &&
                                Number(
                                    row.status
                                ) === 1
                            ) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:
                                            row.edit_url,

                                        icon:
                                            'pencil',

                                        label:
                                            'Edit Bill'
                                    })
                                );
                            }

                            if (
                                has(
                                    listActions,
                                    6
                                )
                            ) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:
                                            row.print_url,

                                        icon:
                                            'printer',

                                        label:
                                            'Print Bill',

                                        target:
                                            '_blank'
                                    })
                                );
                            }

                            if (
                                has(
                                    formActions,
                                    4
                                ) &&
                                Number(
                                    row.status
                                ) === 1
                            ) {
                                actions.push(
                                    '<button type="button" ' +
                                    'class="table-icon-action delete-bill" ' +
                                    'data-ref="' +
                                    esc(row.ref) +
                                    '" title="Deactivate Bill">' +
                                    '<i data-lucide="trash-2"></i>' +
                                    '</button>'
                                );
                            }

                            return actions.join(' ');
                        }
                    }
                ],

                drawCallback:function(){
                    removeDefaultSearch();

                    if (
                        window.lucide
                    ) {
                        window.lucide.createIcons();
                    }
                }
            }
        );

    removeDefaultSearch();

    if (typeof MutationObserver !== 'undefined') {
        var billTableElement =
            document.getElementById(
                'billTable'
            );

        var billCard =
            billTableElement
                ? billTableElement.closest(
                    '.table-card'
                )
                : null;

        if (billCard) {
            new MutationObserver(
                function() {
                    removeDefaultSearch();
                }
            ).observe(
                billCard,
                {
                    childList:true,
                    subtree:true
                }
            );
        }
    }

    document.getElementById(
        'billSearch'
    ).addEventListener(
        'input',
        function() {
            clearTimeout(timer);

            timer =
                setTimeout(
                    function() {
                        table.search(
                            document.getElementById(
                                'billSearch'
                            ).value.trim()
                        ).draw();
                    },
                    300
                );
        }
    );

    [
        'paymentStatusFilter',
        'dateFromFilter',
        'dateToFilter'
    ].forEach(
        function(id) {
            document.getElementById(
                id
            ).addEventListener(
                'change',
                function() {
                    var from =
                        document.getElementById(
                            'dateFromFilter'
                        ).value;

                    var to =
                        document.getElementById(
                            'dateToFilter'
                        ).value;

                    if (
                        from &&
                        to &&
                        from > to
                    ) {
                        if (window.showToast) {
                            showToast(
                                'From Date cannot be after To Date.',
                                {
                                    type:'error',
                                    duration:3
                                }
                            );
                        }

                        return;
                    }

                    table.ajax.reload(
                        null,
                        true
                    );
                }
            );
        }
    );

    document.getElementById(
        'billTable'
    ).addEventListener(
        'click',
        function(event) {
            var button =
                event.target.closest(
                    '.delete-bill'
                );

            if (!button) {
                return;
            }

            if (
                !window.confirm(
                    'Deactivate this Bill? Its source charges will become available for Billing again.'
                )
            ) {
                return;
            }

            button.disabled = true;

            App.api(
                'api/bills.php',
                {
                    method:'POST',
                    body:{
                        action:'delete',
                        ref:
                            button.dataset.ref
                    }
                }
            )
            .then(
                function(response) {
                    showToast(
                        response.message ||
                        'Bill deactivated successfully.',
                        {
                            type:'success',
                            duration:2
                        }
                    );

                    table.ajax.reload(
                        null,
                        false
                    );
                }
            )
            .catch(
                function(error) {
                    button.disabled = false;

                    App.showError(
                        error,
                        'Unable to deactivate Bill.'
                    );
                }
            );
        }
    );

})(jQuery,window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script src="assets/js/appearance.js"></script>
<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>
</body>
</html>
