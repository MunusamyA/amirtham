<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Attendance List';

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
        <h1>Attendance List</h1>
        <p>Manage Regular and Practical Student Attendance by Course and Batch.</p>
    </div>

    <a class="btn btn-primary" id="addAttendanceButton" href="attendance-form.php">
        <i data-lucide="calendar-plus"></i>
        Add Attendance
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="calendar-check-2"></i></span>
        <div><div class="kpi-label">Sessions</div><div class="kpi-value" id="kpiAttendanceSessions">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="user-check"></i></span>
        <div><div class="kpi-label">Present</div><div class="kpi-value" id="kpiAttendancePresent">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="user-x"></i></span>
        <div><div class="kpi-label">Absent</div><div class="kpi-value" id="kpiAttendanceAbsent">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="percent"></i></span>
        <div><div class="kpi-label">Attendance %</div><div class="kpi-value" id="kpiAttendancePercent">0.00%</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-3">
                <label for="attendanceSearch">Search</label>
                <input
                    id="attendanceSearch"
                    type="text"
                    placeholder="Course, Batch, Subject, Remarks...">
            </div>

            <div class="field col-2">
                <label for="typeFilter">Attendance Type</label>
                <select id="typeFilter">
                    <option value="">All Types</option>
                    <option value="1">Regular</option>
                    <option value="2">Practical</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="courseFilter">Course</label>
                <select id="courseFilter">
                    <option value="">All Courses</option>
                </select>
            </div>

            <div class="field col-2">
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
        <table id="attendanceTable" class="display data-table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Course</th>
                <th>Batch</th>
                <th>Practical / Subject</th>
                <th>Total</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Leave</th>
                <th>Late</th>
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

    if (
        !window.AppDataTable ||
        !AppDataTable.ensureAvailable()
    ) {
        return;
    }

    var listActions = [];
    var formActions = [];
    var courses = [];
    var batches = [];
    var searchTimer = null;
    var optionsLoaded = false;
    var has = AppDataTable.has;

    var courseFilterGlobal = null;
    var batchFilterGlobal = null;

    function esc(value) {
        return $('<div>')
            .text(
                value == null
                    ? ''
                    : String(value)
            )
            .html();
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiAttendanceSessions').textContent =
            Number(summary.session_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiAttendancePresent').textContent =
            Number(summary.present_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiAttendanceAbsent').textContent =
            Number(summary.absent_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiAttendancePercent').textContent =
            Number(summary.attendance_percentage || 0).toFixed(2) + '%';
    }

    function removeDefaultSearch() {
        var tableElement=document.getElementById('attendanceTable');
        var card=tableElement?tableElement.closest('.table-card'):null;
        var wrapper=document.getElementById('attendanceTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root)return;
            root.querySelectorAll(
                '#attendanceTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){node.remove();});
        });
    }

    function formatDate(value) {
        if (!value) return '-';

        var parts = String(value).split('-');

        return parts.length === 3
            ? (
                parts[2] +
                '/' +
                parts[1] +
                '/' +
                parts[0]
            )
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

        showToast(
            message,
            {
                type:'danger',
                duration:4
            }
        );
    }

    function syncGlobal(instance) {
        if (
            instance &&
            typeof instance.sync === 'function'
        ) {
            instance.sync();
        }
    }

    function populateCourses() {
        var select =
            document.getElementById(
                'courseFilter'
            );

        var current = select.value;

        select.innerHTML =
            '<option value="">All Courses</option>';

        courses.forEach(function (row) {
            var option =
                document.createElement(
                    'option'
                );

            option.value = String(row.id);
            option.textContent =
                row.course_code +
                ' - ' +
                row.course_name;

            select.appendChild(option);
        });

        select.value = current || '';
        syncGlobal(courseFilterGlobal);
    }

    function populateBatches() {
        var select =
            document.getElementById(
                'batchFilter'
            );

        var current = select.value;

        var courseId =
            Number(
                document.getElementById(
                    'courseFilter'
                ).value || 0
            );

        select.innerHTML =
            '<option value="">All Batches</option>';

        batches
            .filter(function (row) {
                return (
                    courseId === 0 ||
                    Number(row.course_id) ===
                        courseId
                );
            })
            .forEach(function (row) {
                var option =
                    document.createElement(
                        'option'
                    );

                option.value = String(row.id);
                option.textContent =
                    row.batch_code +
                    ' - ' +
                    row.batch_name;

                select.appendChild(option);
            });

        if (
            current &&
            select.querySelector(
                'option[value="' +
                current +
                '"]'
            )
        ) {
            select.value = current;
        }

        syncGlobal(batchFilterGlobal);
    }

    function loadOptions(options) {
        if (optionsLoaded) return;

        courses = options.courses || [];
        batches = options.batches || [];

        populateCourses();
        populateBatches();

        optionsLoaded = true;
    }

    var table =
        AppDataTable.init(
            '#attendanceTable',
            {
                serverSide:true,
                searching:true,
                appSearch:false,
                pageLength:10,
                lengthMenu:[
                    [10,25,50,100],
                    [10,25,50,100]
                ],
                order:[[0,'desc']],
                scrollX:true,
                autoWidth:false,
                buttons:[
                    {
                        extend:'copyHtml5',
                        text:'Copy',
                        title:'Attendance List',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}
                    },
                    {
                        extend:'csvHtml5',
                        text:'CSV',
                        title:'Attendance List',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}
                    },
                    {
                        extend:'excelHtml5',
                        text:'Excel',
                        title:'Attendance List',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}
                    },
                    {
                        extend:'pdfHtml5',
                        text:'PDF',
                        title:'Attendance List',
                        orientation:'landscape',
                        pageSize:'A4',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}
                    },
                    {
                        extend:'print',
                        text:'Print',
                        title:'Attendance List',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10,11]}
                    }
                ],
                ajax:function (data, callback) {
                    var params = new URLSearchParams();

                    params.set('datatable','1');
                    params.set('draw',data.draw);
                    params.set('start',data.start);
                    params.set('length',data.length);
                    params.set('search[value]',data.search.value || '');
                    params.set(
                        'attendance_type',
                        document.getElementById('typeFilter').value
                    );
                    params.set(
                        'course_id',
                        document.getElementById('courseFilter').value
                    );
                    params.set(
                        'batch_id',
                        document.getElementById('batchFilter').value
                    );
                    params.set(
                        'status',
                        document.getElementById('statusFilter').value
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
                        'api/attendance.php?' +
                        params.toString()
                    )
                    .then(function (response) {
                        listActions =
                            (
                                response.data.list_actions ||
                                []
                            ).map(Number);

                        formActions =
                            (
                                response.data.form_actions ||
                                []
                            ).map(Number);

                        setSummary(
                            response.data.summary
                        );

                        loadOptions(
                            response.data.options || {}
                        );

                        document.getElementById(
                            'addAttendanceButton'
                        ).hidden =
                            !has(formActions,2);

                        AppDataTable.applyExportPermissions(
                            table,
                            listActions
                        );

                        callback(response.data.datatable);
                    })
                    .catch(function (error) {
                        setSummary({});

                        showError(
                            error,
                            'Unable to load Attendance.'
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
                        data:'attendance_date',
                        render:function (value, type) {
                            return type === 'display'
                                ? formatDate(value)
                                : value;
                        }
                    },
                    {data:'attendance_type_label'},
                    {data:'course_label'},
                    {data:'batch_label'},
                    {data:'subject_label'},
                    {
                        data:'total_students',
                        className:'dt-body-right'
                    },
                    {
                        data:'present_count',
                        className:'dt-body-right'
                    },
                    {
                        data:'absent_count',
                        className:'dt-body-right'
                    },
                    {
                        data:'leave_count',
                        className:'dt-body-right'
                    },
                    {
                        data:'late_count',
                        className:'dt-body-right'
                    },
                    {
                        data:'attendance_percentage',
                        className:'dt-body-right',
                        render:function (value, type) {
                            return type === 'display'
                                ? Number(value || 0).toFixed(2) + '%'
                                : Number(value || 0);
                        }
                    },
                    {
                        data:'status',
                        render:function (value, type) {
                            if (type !== 'display') {
                                return Number(value);
                            }

                            return Number(value) === 1
                                ? 'Active'
                                : 'Inactive';
                        }
                    },
                    {
                        data:null,
                        orderable:false,
                        searchable:false,
                        className:'table-action-icons',
                        render:function (data, type, row) {
                            if (type !== 'display') return '';

                            var actions = [];

                            if (has(formActions,1)) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:row.view_url,
                                        icon:'eye',
                                        label:'View Attendance'
                                    })
                                );
                            }

                            if (has(formActions,3)) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:row.edit_url,
                                        icon:'pencil',
                                        label:'Edit Attendance'
                                    })
                                );
                            }

                            if (has(formActions,4)) {
                                actions.push(
                                    '<button type="button" ' +
                                    'class="table-icon-action delete-attendance" ' +
                                    'data-ref="' + esc(row.ref) + '" ' +
                                    'title="Delete Attendance" ' +
                                    'aria-label="Delete Attendance">' +
                                    '<i data-lucide="trash-2"></i>' +
                                    '</button>'
                                );
                            }

                            return (
                                actions.join(' ') ||
                                '<span>View only</span>'
                            );
                        }
                    }
                ],
                drawCallback:function () {
                    removeDefaultSearch();
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            }
        );

    removeDefaultSearch();

    if(typeof MutationObserver!=='undefined'){
        var attendanceTableElement=document.getElementById('attendanceTable');
        var attendanceCard=attendanceTableElement?attendanceTableElement.closest('.table-card'):null;

        if(attendanceCard){
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(attendanceCard,{childList:true,subtree:true});
        }
    }

    document.getElementById('attendanceSearch')
        .addEventListener('input', function () {
            var input = this;

            clearTimeout(searchTimer);

            searchTimer =
                setTimeout(
                    function () {
                        table.search(
                            input.value.trim()
                        ).draw();
                    },
                    300
                );
        });

    document.getElementById('courseFilter')
        .addEventListener('change', function () {
            populateBatches();
            table.ajax.reload();
        });

    [
        'typeFilter',
        'batchFilter',
        'statusFilter',
        'dateFrom',
        'dateTo'
    ].forEach(function (id) {
        document.getElementById(id)
            .addEventListener('change', function () {
                table.ajax.reload();
            });
    });

    document.getElementById('attendanceTable')
        .addEventListener('click', async function (event) {
            var button =
                event.target.closest('.delete-attendance');

            if (!button) return;

            if (
                !window.confirm(
                    'Delete this Attendance Session and all Student Attendance records?'
                )
            ) {
                return;
            }

            button.disabled = true;

            try {
                var response =
                    await App.api(
                        'api/attendance.php',
                        {
                            method:'POST',
                            body:{
                                action:'delete',
                                ref:button.dataset.ref
                            }
                        }
                    );

                showToast(
                    response.message ||
                    'Attendance deleted successfully.',
                    {type:'success',duration:2}
                );

                table.ajax.reload(null,false);
            } catch (error) {
                button.disabled = false;

                showError(
                    error,
                    'Unable to delete Attendance.'
                );
            }
        });

    GlobalSelect.init('#typeFilter', {
        placeholder:'All Types'
    });

    courseFilterGlobal =
        GlobalSelect.init('#courseFilter', {
            placeholder:'All Courses'
        });

    batchFilterGlobal =
        GlobalSelect.init('#batchFilter', {
            placeholder:'All Batches'
        });

    GlobalSelect.init('#statusFilter', {
        placeholder:'All Status'
    });
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
