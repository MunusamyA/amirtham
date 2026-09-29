<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Student Master';

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
        <h1>Student Master</h1>
        <p>Manage Student records and open the complete College Profile for each Student.</p>
    </div>

    <a class="btn btn-primary" id="addStudentButton" href="student-form.php">
        <i data-lucide="user-plus"></i>
        Add Student
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="users"></i></span>
        <div><div class="kpi-label">Students</div><div class="kpi-value" id="kpiStudentTotal">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active</div><div class="kpi-value" id="kpiStudentActive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div><div class="kpi-label">Total Paid</div><div class="kpi-value" id="kpiStudentPaid">₹0.00</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>
        <div><div class="kpi-label">Outstanding</div><div class="kpi-value" id="kpiStudentBalance">₹0.00</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="studentSearch">Search</label>
                <input id="studentSearch" type="text" autocomplete="off" placeholder="Code, Name, Mobile, Admission...">
            </div>

            <div class="field col-3">
                <label for="courseFilter">Course</label>
                <select id="courseFilter">
                    <option value="">All Courses</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="batchFilter">Batch</label>
                <select id="batchFilter">
                    <option value="">All Batches</option>
                </select>
            </div>

            <div class="field col-3">
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
        <table id="studentTable" class="display data-table">
            <thead>
            <tr>
                <th>Student Code</th>
                <th>Student Name</th>
                <th>Mobile</th>
                <th>Course</th>
                <th>Batch</th>
                <th>Admission No</th>
                <th>Admission Date</th>
                <th>Admission State</th>
                <th>Total Fee</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Attendance %</th>
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

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var actions = [];
    var courses = [];
    var batches = [];
    var optionsLoaded = false;
    var searchTimer = null;
    var courseFilterSelect = null;
    var batchFilterSelect = null;
    var statusFilterSelect = null;
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

        document.getElementById('kpiStudentTotal').textContent =
            Number(summary.student_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiStudentActive').textContent =
            Number(summary.active_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiStudentPaid').textContent =
            '₹' + money(summary.total_paid || 0);

        document.getElementById('kpiStudentBalance').textContent =
            '₹' + money(summary.balance_amount || 0);
    }

    function removeDefaultSearch() {
        var tableElement=document.getElementById('studentTable');
        var card=tableElement?tableElement.closest('.table-card'):null;
        var wrapper=document.getElementById('studentTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root)return;
            root.querySelectorAll(
                '#studentTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){node.remove();});
        });
    }

    function formatDate(value) {
        if (!value) return '-';
        var parts = String(value).split('-');
        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : esc(value);
    }

    function showError(error, fallback) {
        var message = error && error.message
            ? String(error.message)
            : (error && error.data && error.data.message
                ? String(error.data.message)
                : fallback);

        showToast(message, {type:'danger',duration:4});
    }

    function populateCourses() {
        var select = document.getElementById('courseFilter');
        var current = select.value;
        select.innerHTML = '<option value="">All Courses</option>';

        courses.forEach(function (row) {
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = row.course_code + ' - ' + row.course_name;
            select.appendChild(option);
        });

        select.value = current || '';
        if (courseFilterSelect && typeof courseFilterSelect.sync === 'function') {
            courseFilterSelect.sync();
        }
    }

    function populateBatches() {
        var select = document.getElementById('batchFilter');
        var current = select.value;
        var courseId = Number(document.getElementById('courseFilter').value || 0);
        select.innerHTML = '<option value="">All Batches</option>';

        batches.filter(function (row) {
            return courseId === 0 || Number(row.course_id) === courseId;
        }).forEach(function (row) {
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = row.batch_code + ' - ' + row.batch_name;
            select.appendChild(option);
        });

        if (current && select.querySelector('option[value="' + current + '"]')) {
            select.value = current;
        }

        if (batchFilterSelect && typeof batchFilterSelect.sync === 'function') {
            batchFilterSelect.sync();
        }
    }

    function loadOptions(options) {
        if (optionsLoaded) return;
        courses = options.courses || [];
        batches = options.batches || [];
        populateCourses();
        populateBatches();
        optionsLoaded = true;
    }

    var table = AppDataTable.init('#studentTable', {
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,'asc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:'copyHtml5',text:'Copy',title:'Student Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}},
            {extend:'csvHtml5',text:'CSV',title:'Student Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}},
            {extend:'excelHtml5',text:'Excel',title:'Student Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}},
            {extend:'pdfHtml5',text:'PDF',title:'Student Master',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}},
            {extend:'print',text:'Print',title:'Student Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11,12]}}
        ],
        ajax:function (data, callback) {
            var params = new URLSearchParams();
            params.set('datatable','1');
            params.set('draw',data.draw);
            params.set('start',data.start);
            params.set('length',data.length);
            params.set('search[value]',data.search.value || '');
            params.set('course_id',document.getElementById('courseFilter').value);
            params.set('batch_id',document.getElementById('batchFilter').value);
            params.set('status',document.getElementById('statusFilter').value);

            if (data.order && data.order[0]) {
                params.set('order[0][column]',data.order[0].column);
                params.set('order[0][dir]',data.order[0].dir);
            }

            App.api('api/students.php?' + params.toString())
                .then(function (response) {
                    actions = (response.data.allowed_actions || []).map(Number);
                    setSummary(response.data.summary);
                    loadOptions(response.data.options || {});
                    document.getElementById('addStudentButton').hidden = !has(actions,2);
                    AppDataTable.applyExportPermissions(table,actions);
                    callback(response.data.datatable);
                })
                .catch(function (error) {
                    setSummary({});
                    showError(error,'Unable to load Students.');
                    callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                });
        },
        columns:[
            {data:'student_code'},
            {data:'student_name'},
            {data:'mobile',render:function (value,type) {return type === 'display' ? esc(value || '-') : (value || '');}},
            {data:'course_label'},
            {data:'batch_label'},
            {data:'admission_no'},
            {data:'admission_date',render:function (value,type) {return type === 'display' ? formatDate(value) : value;}},
            {data:'admission_state',render:function (value,type) {return type === 'display' ? esc(String(value || '-').replace(/_/g,' ')) : (value || '');}},
            {data:'net_payable',className:'dt-body-right',render:function (value,type) {return type === 'display' ? '₹' + money(value) : Number(value || 0);}},
            {data:'total_paid',className:'dt-body-right',render:function (value,type) {return type === 'display' ? '₹' + money(value) : Number(value || 0);}},
            {data:'balance_amount',className:'dt-body-right',render:function (value,type) {return type === 'display' ? '₹' + money(value) : Number(value || 0);}},
            {data:'attendance_percentage',className:'dt-body-right',render:function (value,type) {return type === 'display' ? Number(value || 0).toFixed(2) + '%' : Number(value || 0);}},
            {data:'status',render:function (value,type) {return type === 'display' ? (Number(value) === 1 ? 'Active' : 'Inactive') : Number(value);}},
            {
                data:null,
                orderable:false,
                searchable:false,
                className:'table-action-icons',
                render:function (data,type,row) {
                    if (type !== 'display') return '';
                    var manage = [];

                    if (has(actions,1)) {
                        manage.push(App.iconActionHtml({href:row.profile_url,icon:'eye',label:'View Student Profile'}));
                    }
                    if (has(actions,3)) {
                        manage.push(App.iconActionHtml({href:row.edit_url,icon:'pencil',label:'Edit Student'}));
                    }

                    return manage.join(' ') || '<span>View only</span>';
                }
            }
        ],
        drawCallback:function () {
            removeDefaultSearch();
            if (window.lucide) window.lucide.createIcons();
        }
    });

    removeDefaultSearch();

    if(typeof MutationObserver!=='undefined'){
        var studentTableElement=document.getElementById('studentTable');
        var studentCard=studentTableElement?studentTableElement.closest('.table-card'):null;

        if(studentCard){
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(studentCard,{childList:true,subtree:true});
        }
    }

    document.getElementById('studentSearch').addEventListener('input',function () {
        var input = this;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            table.search(input.value.trim()).draw();
        },300);
    });

    document.getElementById('courseFilter').addEventListener('change',function () {
        populateBatches();
        table.ajax.reload();
    });

    ['batchFilter','statusFilter'].forEach(function (id) {
        document.getElementById(id).addEventListener('change',function () {
            table.ajax.reload();
        });
    });

    courseFilterSelect = GlobalSelect.init('#courseFilter',{placeholder:'All Courses'});
    batchFilterSelect = GlobalSelect.init('#batchFilter',{placeholder:'All Batches'});
    statusFilterSelect = GlobalSelect.init('#statusFilter',{placeholder:'All Status'});
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
