<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Student Admissions';

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
        <h1>Student Admissions</h1>
        <p>Manage Student Admissions, Course, Batch, Fee Plan and Payment status.</p>
    </div>

    <a class="btn btn-primary" id="addAdmissionButton" href="admission-form.php">
        <i data-lucide="user-plus"></i>
        Add Admission
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="user-round-check"></i></span>
        <div>
            <div class="kpi-label">Admissions</div>
            <div class="kpi-value" id="kpiAdmissions">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="graduation-cap"></i></span>
        <div>
            <div class="kpi-label">Active Admissions</div>
            <div class="kpi-value" id="kpiActiveAdmissions">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Total Paid</div>
            <div class="kpi-value" id="kpiTotalPaid">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Balance Due</div>
            <div class="kpi-value" id="kpiBalanceDue">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-4">
                <label for="admissionSearch">Search</label>
                <input
                    id="admissionSearch"
                    type="text"
                    placeholder="Admission No, Student, Mobile, Course, Batch...">
            </div>

            <div class="field col-2">
                <label for="courseFilter">Course</label>
                <select id="courseFilter">
                    <option value="">All Courses</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="stateFilter">Admission State</label>
                <select id="stateFilter">
                    <option value="">All States</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="discontinued">Discontinued</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="paymentStatusFilter">Payment Status</label>
                <select id="paymentStatusFilter">
                    <option value="">All Payments</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partially Paid</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>

            <div class="field col-2">
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
        <table id="admissionTable" class="display data-table">
            <thead>
            <tr>
                <th>Admission No</th>
                <th>Student</th>
                <th>Mobile</th>
                <th>Course</th>
                <th>Batch</th>
                <th>Admission Date</th>
                <th>Total Fee</th>
                <th>Admission Payment</th>
                <th>Total Paid</th>
                <th>Balance</th>
                <th>Payment Plan</th>
                <th>Admission State</th>
                <th>Status</th>
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
    var paymentActions = [];
    var searchTimer = null;
    var filtersReady = false;
    var courseFilterSelect = null;
    var has = AppDataTable.has;

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiAdmissions').textContent =
            Number(summary.total_admissions || 0).toLocaleString('en-IN');

        document.getElementById('kpiActiveAdmissions').textContent =
            Number(summary.active_admissions || 0).toLocaleString('en-IN');

        document.getElementById('kpiTotalPaid').textContent =
            '₹' + money(summary.total_paid || 0);

        document.getElementById('kpiBalanceDue').textContent =
            '₹' + money(summary.balance_due || 0);
    }

    function formatDate(value) {
        if (!value) return '-';

        var parts = String(value).split('-');
        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : esc(value);
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

        showToast(message, { type:'danger', duration:4 });
    }

    function populateCourses(rows) {
        var select = document.getElementById('courseFilter');
        var current = select.value;

        select.innerHTML = '<option value="">All Courses</option>';

        (rows || []).forEach(function (row) {
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = row.course_code + ' - ' + row.course_name;
            select.appendChild(option);
        });

        if (current) {
            select.value = current;
        }

        if (courseFilterSelect && typeof courseFilterSelect.sync === 'function') {
            courseFilterSelect.sync();
        }
    }

    var table = AppDataTable.init('#admissionTable', {
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[5,'desc'],[0,'desc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {
                extend:'copyHtml5',
                text:'Copy',
                title:'Student Admissions',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}
            },
            {
                extend:'csvHtml5',
                text:'CSV',
                title:'Student Admissions',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}
            },
            {
                extend:'excelHtml5',
                text:'Excel',
                title:'Student Admissions',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}
            },
            {
                extend:'pdfHtml5',
                text:'PDF',
                title:'Student Admissions',
                orientation:'landscape',
                pageSize:'A4',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}
            },
            {
                extend:'print',
                text:'Print',
                title:'Student Admissions',
                action:AppDataTable.serverSideExportAction,
                exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}
            }
        ],
        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set('datatable','1');
            params.set('draw',data.draw);
            params.set('start',data.start);
            params.set('length',data.length);
            params.set('search[value]',data.search.value || '');
            params.set('course_id',document.getElementById('courseFilter').value);
            params.set('admission_state',document.getElementById('stateFilter').value);
            params.set('payment_status',document.getElementById('paymentStatusFilter').value);
            params.set('status',document.getElementById('statusFilter').value);

            if (data.order && data.order[0]) {
                params.set('order[0][column]',data.order[0].column);
                params.set('order[0][dir]',data.order[0].dir);
            }

            App.api('api/admissions.php?' + params.toString())
                .then(function(response){
                    listActions = (response.data.list_actions || []).map(Number);
                    formActions = (response.data.form_actions || []).map(Number);
                    paymentActions = (response.data.payment_actions || []).map(Number);

                    document.getElementById('addAdmissionButton').style.display =
                        has(formActions,2) ? 'inline-flex' : 'none';

                    if (!filtersReady) {
                        populateCourses(response.data.courses || []);
                        filtersReady = true;
                    }

                    setSummary(response.data.summary);
                    AppDataTable.applyExportPermissions(table,listActions);
                    callback(response.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    showError(error,'Unable to load Student Admissions.');
                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },
        columns:[
            {data:'admission_no',defaultContent:'-'},
            {data:'student_label',defaultContent:'-'},
            {data:'mobile',defaultContent:'-',render:function(v){return v || '-';}},
            {data:'course_label',defaultContent:'-'},
            {data:'batch_label',defaultContent:'-'},
            {
                data:'admission_date',
                render:function(v,t){
                    return t === 'display' ? formatDate(v) : v;
                }
            },
            {
                data:'net_payable',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display' ? money(v) : Number(v || 0);
                }
            },
            {
                data:'admission_payment',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display' ? money(v) : Number(v || 0);
                }
            },
            {
                data:'total_paid',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display' ? money(v) : Number(v || 0);
                }
            },
            {
                data:'balance_amount',
                className:'dt-body-right',
                render:function(v,t){
                    return t === 'display' ? money(v) : Number(v || 0);
                }
            },
            {data:'payment_plan_label',defaultContent:'-'},
            {data:'admission_state_label',defaultContent:'-'},
            {
                data:'status',
                render:function(v,t){
                    if (t !== 'display') return Number(v || 0);

                    return Number(v) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
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
                                label:'View Admission'
                            })
                        );
                    }

                    if (has(paymentActions,1)) {
                        actions.push(
                            App.iconActionHtml({
                                href:row.payment_url,
                                icon:'indian-rupee',
                                label:'Student Payment'
                            })
                        );
                    }

                    if (has(listActions,9) && row.receipt_ref) {
                        actions.push(
                            '<button type="button" ' +
                            'class="table-icon-action admission-receipt-print" ' +
                            'data-receipt-ref="' + esc(row.receipt_ref) + '" ' +
                            'title="Admission Receipt" aria-label="Admission Receipt">' +
                            '<i data-lucide="printer"></i>' +
                            '</button>'
                        );
                    }

                    if (has(formActions,3)) {
                        actions.push(
                            App.iconActionHtml({
                                href:row.edit_url,
                                icon:'pencil',
                                label:'Edit Admission'
                            })
                        );
                    }

                    if (has(formActions,4)) {
                        actions.push(
                            '<button type="button" ' +
                            'class="table-icon-action delete-admission" ' +
                            'data-ref="' + esc(row.ref) + '" ' +
                            'title="Delete Admission" aria-label="Delete Admission">' +
                            '<i data-lucide="trash-2"></i>' +
                            '</button>'
                        );
                    }

                    return actions.join(' ') || '<span class="muted">View only</span>';
                }
            }
        ],
        drawCallback:function(){
            if (window.lucide) {
                window.lucide.createIcons();
            }
        }
    });

    var tableElement = document.getElementById('admissionTable');
    var tableCard = tableElement ? tableElement.closest('.table-card') : null;
    var duplicateSearch = tableCard
        ? tableCard.querySelector('.app-table-search-row')
        : null;

    if (duplicateSearch) {
        duplicateSearch.remove();
    }

    document.getElementById('admissionSearch').addEventListener('input',function(){
        var input = this;
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(input.value.trim()).draw();
        },300);
    });

    [
        'courseFilter',
        'stateFilter',
        'paymentStatusFilter',
        'statusFilter'
    ].forEach(function(id){
        document.getElementById(id).addEventListener('change',function(){
            if (filtersReady) {
                table.ajax.reload();
            }
        });
    });

    document.getElementById('admissionTable').addEventListener('click',function(event){
        var receiptButton = event.target.closest('.admission-receipt-print');

            if (!receiptButton) return;

            event.preventDefault();

            var receiptRef = receiptButton.getAttribute('data-receipt-ref') || '';
            if (!receiptRef) return;

            openReceiptPdf(
                'student-fee-receipt.php?ref=' +
                encodeURIComponent(receiptRef)
            );
        });

    document.getElementById('admissionTable').addEventListener('click',function(event){
        var button = event.target.closest('.delete-admission');

        if (!button) return;

        if (!window.confirm(
            'Delete this Admission? Deletion is blocked after Attendance or later Student Payments exist.'
        )) {
            return;
        }

        button.disabled = true;

        App.api('api/admissions.php',{
            method:'POST',
            body:{
                action:'delete',
                ref:button.dataset.ref
            }
        })
        .then(function(response){
            showToast(
                response.message || 'Admission deleted successfully.',
                {type:'success',duration:2}
            );
            table.ajax.reload(null,false);
        })
        .catch(function(error){
            button.disabled = false;
            showError(error,'Unable to delete Admission.');
        });
    });

    if (window.GlobalSelect) {
        courseFilterSelect = GlobalSelect.init(
            document.getElementById('courseFilter'),
            {placeholder:'All Courses'}
        );

        GlobalSelect.init(
            document.getElementById('stateFilter'),
            {placeholder:'All States'}
        );

        GlobalSelect.init(
            document.getElementById('paymentStatusFilter'),
            {placeholder:'All Payments'}
        );

        GlobalSelect.init(
            document.getElementById('statusFilter'),
            {placeholder:'All Status'}
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
