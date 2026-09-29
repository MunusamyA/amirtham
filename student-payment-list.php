<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Student Payment History';

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
<script src="assets/js/app.js?v=20260918-receipt-pdf-2"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1>Student Payment History</h1>
        <p>View the complete Student Payment history and safely edit or delete any Payment with automatic EMI and balance recalculation.</p>
    </div>

    <a class="btn btn-primary" id="addPaymentButton" href="student-payment.php">
        <i data-lucide="plus"></i>
        Add Payment
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="receipt-text"></i></span>
        <div><div class="kpi-label">Payment Records</div><div class="kpi-value" id="kpiPaymentCount">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="indian-rupee"></i></span>
        <div><div class="kpi-label">Amount Received</div><div class="kpi-value" id="kpiPaymentAmount">₹0.00</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="graduation-cap"></i></span>
        <div><div class="kpi-label">Admissions</div><div class="kpi-value" id="kpiPaymentAdmissions">0</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="buttons" style="margin-bottom:12px">
            <button class="btn btn-primary payment-type-tab" type="button" data-against="overall">
                Overall Outstanding
            </button>
            <button class="btn gray payment-type-tab" type="button" data-against="emi">
                Particular EMI
            </button>
            <button class="btn gray payment-type-tab" type="button" data-against="">
                All Payments
            </button>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="paymentSearch">Search</label>
                <input
                    id="paymentSearch"
                    type="text"
                    placeholder="Receipt, Student, Admission, Mobile, Course, Batch...">
            </div>

            <div class="field col-2">
                <label for="againstFilter">Payment Against</label>
                <select id="againstFilter">
                    <option value="">All</option>
                    <option value="overall">Overall Outstanding</option>
                    <option value="emi">Particular EMI</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="dateFrom">From Date</label>
                <input id="dateFrom" type="date">
            </div>

            <div class="field col-3">
                <label for="dateTo">To Date</label>
                <input id="dateTo" type="date">
            </div>
        </div>
    </div>

    <div class="table-scroll">
        <table id="paymentTable" class="display data-table">
            <thead>
            <tr>
                <th>Receipt No</th>
                <th>Date</th>
                <th>Student</th>
                <th>Admission No</th>
                <th>Course</th>
                <th>Batch</th>
                <th>Against</th>
                <th>Allocation</th>
                <th>Mode</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Manage</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function ($) {
    'use strict';


    function openReceiptPdf(url, existingWindow) {
        var token = window.App && typeof App.getToken === 'function'
            ? App.getToken()
            : '';

        if (!token) {
            if (window.App && typeof App.goToLogin === 'function') {
                App.goToLogin('Your login has expired. Please login again.');
            } else if (typeof window.showToast === 'function') {
                showToast('Authentication token is missing. Please login again.', {
                    type:'danger',
                    duration:4
                });
            }
            return false;
        }

        var receiptRef = '';
        try {
            var parsedUrl = new URL(url, window.location.href);
            receiptRef = parsedUrl.searchParams.get('ref') || '';
        } catch (error) {
            receiptRef = '';
        }

        if (!receiptRef) {
            if (typeof window.showToast === 'function') {
                showToast('Receipt reference is missing.', {
                    type:'danger',
                    duration:4
                });
            }
            return false;
        }

        var pdfWindow = existingWindow || window.open('', '_blank');

        if (!pdfWindow) {
            if (typeof window.showToast === 'function') {
                showToast('Please allow popups to open the receipt.', {
                    type:'warning',
                    duration:4
                });
            }
            return false;
        }

        var targetName = 'amirtham_receipt_window';

        try {
            pdfWindow.name = targetName;
        } catch (ignoreName) {}

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'student-fee-receipt.php';
        form.target = targetName;
        form.style.display = 'none';

        var refInput = document.createElement('input');
        refInput.type = 'hidden';
        refInput.name = 'ref';
        refInput.value = receiptRef;
        form.appendChild(refInput);

        var tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'auth_token';
        tokenInput.value = token;
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
        form.remove();

        return true;
    }


    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var listActions = [];
    var formActions = [];
    var searchTimer = null;
    var has = AppDataTable.has;

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiPaymentCount').textContent =
            Number(summary.payment_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiPaymentAmount').textContent =
            '₹' + money(summary.amount_received || 0);

        document.getElementById('kpiPaymentAdmissions').textContent =
            Number(summary.admission_count || 0).toLocaleString('en-IN');
    }

    function removeDefaultSearch() {
        var tableElement=document.getElementById('paymentTable');
        var card=tableElement?tableElement.closest('.table-card'):null;
        var wrapper=document.getElementById('paymentTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root)return;
            root.querySelectorAll(
                '#paymentTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){node.remove();});
        });
    }

    function formatDate(value) {
        if (!value) return '-';

        var parts = String(value).split('-');

        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : String(value);
    }

    function showError(error, fallback) {
        var message =
            error && error.message
                ? String(error.message)
                : (
                    error &&
                    error.data &&
                    error.data.message
                        ? String(error.data.message)
                        : fallback
                );

        showToast(message,{type:'danger',duration:4});
    }

    var table = AppDataTable.init('#paymentTable', {
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,'desc'],[0,'desc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {
                extend:'copyHtml5',
                text:'Copy',
                title:'Student Payment History',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}
            },
            {
                extend:'csvHtml5',
                text:'CSV',
                title:'Student Payment History',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}
            },
            {
                extend:'excelHtml5',
                text:'Excel',
                title:'Student Payment History',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}
            },
            {
                extend:'pdfHtml5',
                text:'PDF',
                title:'Student Payment History',
                orientation:'landscape',
                pageSize:'A4',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}
            },
            {
                extend:'print',
                text:'Print',
                title:'Student Payment History',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}
            }
        ],
        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set('datatable','1');
            params.set('draw',data.draw);
            params.set('start',data.start);
            params.set('length',data.length);
            params.set('search[value]',data.search.value || '');
            params.set(
                'payment_against',
                document.getElementById('againstFilter').value
            );
            params.set(
                'date_from',
                document.getElementById('dateFrom').value
            );
            params.set(
                'date_to',
                document.getElementById('dateTo').value
            );

            if (data.order && data.order[0]) {
                params.set('order[0][column]',data.order[0].column);
                params.set('order[0][dir]',data.order[0].dir);
            }

            App.api(
                'api/student-payments.php?' + params.toString()
            )
            .then(function(response){
                listActions = (response.data.list_actions || []).map(Number);
                formActions = (response.data.form_actions || []).map(Number);
                setSummary(response.data.summary);

                document.getElementById('addPaymentButton').style.display =
                    has(formActions,2)
                        ? 'inline-flex'
                        : 'none';

                AppDataTable.applyExportPermissions(
                    table,
                    listActions
                );

                callback(response.data.datatable);
            })
            .catch(function(error){
                setSummary({});
                showError(error,'Unable to load Student Payments.');

                callback({
                    draw:data.draw,
                    recordsTotal:0,
                    recordsFiltered:0,
                    data:[]
                });
            });
        },
        columns:[
            {data:'receipt_no',defaultContent:'-'},
            {
                data:'receipt_date',
                render:function(v,t){
                    return t === 'display' ? formatDate(v) : v;
                }
            },
            {data:'student_label',defaultContent:'-'},
            {data:'admission_no',defaultContent:'-'},
            {data:'course_label',defaultContent:'-'},
            {data:'batch_label',defaultContent:'-'},
            {data:'payment_against_label',defaultContent:'-'},
            {data:'allocation_label',defaultContent:'-'},
            {data:'mode_label',defaultContent:'-'},
            {
                data:'amount',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display'
                        ? money(v)
                        : Number(v || 0);
                }
            },
            {
                data:'balance_after',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display'
                        ? money(v)
                        : Number(v || 0);
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:'table-action-icons',
                render:function(data,type,row){
                    if (type !== 'display') return '';

                    var actions = [];

                    if (has(formActions,1)) {
                        actions.push(
                            App.iconActionHtml({
                                href:row.view_url,
                                icon:'eye',
                                label:'View Payment'
                            })
                        );
                    }

                    if (has(listActions,9) && row.receipt_ref) {
                        actions.push(
                            '<button type="button" ' +
                            'class="table-icon-action payment-receipt-print" ' +
                            'data-receipt-ref="' + esc(row.receipt_ref) + '" ' +
                            'title="Payment Receipt" aria-label="Payment Receipt">' +
                            '<i data-lucide="printer"></i>' +
                            '</button>'
                        );
                    }

                    if (has(formActions,3)) {
                        actions.push(
                            App.iconActionHtml({
                                href:row.edit_url,
                                icon:'pencil',
                                label:'Edit Payment'
                            })
                        );
                    }

                    if (has(formActions,4)) {
                        actions.push(
                            '<button type="button" ' +
                            'class="table-icon-action delete-payment" ' +
                            'data-ref="' + esc(row.ref) + '" ' +
                            'title="Delete Payment" aria-label="Delete Payment">' +
                            '<i data-lucide="trash-2"></i>' +
                            '</button>'
                        );
                    }

                    return actions.join(' ') ||
                        '<span class="muted">View only</span>';
                }
            }
        ],
        drawCallback:function(){
            removeDefaultSearch();
            if (window.lucide) {
                window.lucide.createIcons();
            }
        }
    });

    removeDefaultSearch();

    if(typeof MutationObserver!=='undefined'){
        var paymentTableElement=document.getElementById('paymentTable');
        var paymentCard=paymentTableElement?paymentTableElement.closest('.table-card'):null;

        if(paymentCard){
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(paymentCard,{childList:true,subtree:true});
        }
    }

    document.getElementById('paymentSearch')
        .addEventListener('input',function(){
            var input = this;

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function(){
                table.search(input.value.trim()).draw();
            },300);
        });

    [
        'againstFilter',
        'dateFrom',
        'dateTo'
    ].forEach(function(id){
        document.getElementById(id)
            .addEventListener('change',function(){
                table.ajax.reload();
            });
    });

    document.getElementById('paymentTable')
        .addEventListener('click',function(event){
            var receiptButton = event.target.closest('.payment-receipt-print');

            if (!receiptButton) return;

            event.preventDefault();

            var receiptRef = receiptButton.getAttribute('data-receipt-ref') || '';
            if (!receiptRef) return;

            openReceiptPdf(
                'student-fee-receipt.php?ref=' +
                encodeURIComponent(receiptRef)
            );
        });

    document.getElementById('paymentTable')
        .addEventListener('click',async function(event){
            var button = event.target.closest('.delete-payment');

            if (!button) return;

            if (!window.confirm(
                'Delete this Payment? The receipt will be deleted, then all remaining Student Payments will be replayed in date order and EMI Paid, EMI Outstanding, Total Paid and Balance will be recalculated automatically.'
            )) {
                return;
            }

            button.disabled = true;

            try {
                var response = await App.api(
                    'api/college-fee-payments.php',
                    {
                        method:'POST',
                        body:{
                            action:'delete',
                            receipt_ref:button.dataset.ref
                        }
                    }
                );

                showToast(
                    response.message ||
                    'Student Payment deleted successfully.',
                    {type:'success',duration:2}
                );

                table.ajax.reload(null,false);
            } catch (error) {
                button.disabled = false;
                showError(error,'Unable to delete Student Payment.');
            }
        });

    Array.prototype.forEach.call(
        document.querySelectorAll('.payment-type-tab'),
        function(button){
            button.addEventListener('click',function(){
                var value = this.getAttribute('data-against') || '';
                var select = document.getElementById('againstFilter');
                select.value = value;

                Array.prototype.forEach.call(
                    document.querySelectorAll('.payment-type-tab'),
                    function(tab){
                        tab.classList.remove('btn-primary');
                        tab.classList.add('gray');
                    }
                );

                this.classList.remove('gray');
                this.classList.add('btn-primary');

                if (window.GlobalSelect) {
                    var instance = GlobalSelect.get
                        ? GlobalSelect.get(select)
                        : null;
                    if (instance && typeof instance.sync === 'function') {
                        instance.sync();
                    }
                }

                table.ajax.reload();
            });
        }
    );

    if (window.GlobalSelect) {
        GlobalSelect.init(
            document.getElementById('againstFilter'),
            {placeholder:'All'}
        );
    }
})(jQuery);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
if(window.lucide){window.lucide.createIcons();}
</script>
</body>
</html>
