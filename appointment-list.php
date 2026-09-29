<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Appointments';

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
        <h1>Appointments</h1>
        <p>Create and manage Clinic Appointments.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="appointment-calendar.php">
            <i data-lucide="calendar-days"></i>
            Calendar
        </a>

        <a
            class="btn btn-primary"
            id="addAppointmentButton"
            href="appointment-form.php"
        >
            <i data-lucide="plus"></i>
            Add Appointment
        </a>
    </div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="calendar-days"></i></span>
        <div>
            <div class="kpi-label">Appointments</div>
            <div class="kpi-value" id="kpiAppointments">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="clock-3"></i></span>
        <div>
            <div class="kpi-label">Scheduled</div>
            <div class="kpi-value" id="kpiScheduled">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Completed</div>
            <div class="kpi-value" id="kpiCompleted">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-x"></i></span>
        <div>
            <div class="kpi-label">Cancelled</div>
            <div class="kpi-value" id="kpiCancelled">0</div>
        </div>
    </article>
</div>

<div class="card table-card">

<div class="card-header">
    <div
        class="form-row"
        style="width:100%;margin:0;"
    >
        <div class="field col-4">
            <label for="appointmentSearch">Search</label>
            <input
                id="appointmentSearch"
                type="text"
                placeholder="Appointment no, patient, mobile, consultant..."
            >
        </div>

        <div class="field col-2">
            <label for="visitTypeFilter">Visit Type</label>
            <select id="visitTypeFilter">
                <option value="">All Visit Types</option>
                <option value="New Patient">New Patient</option>
                <option value="Follow-up">Follow-up</option>
                <option value="Treatment Visit">Treatment Visit</option>
                <option value="Review">Review</option>
            </select>
        </div>

        <div class="field col-2">
            <label for="appointmentStatusFilter">Appointment Status</label>
            <select id="appointmentStatusFilter">
                <option value="">All Appointment Status</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Confirmed">Confirmed</option>
                <option value="Checked In">Checked In</option>
                <option value="In Consultation">In Consultation</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
                <option value="No Show">No Show</option>
            </select>
        </div>

        <div class="field col-2">
            <label for="dateFromFilter">Date From</label>
            <input
                id="dateFromFilter"
                type="date"
            >
        </div>

        <div class="field col-2">
            <label for="dateToFilter">Date To</label>
            <input
                id="dateToFilter"
                type="date"
            >
        </div>
    </div>
</div>

<div class="table-scroll">
    <table
        id="appointmentTable"
        class="display data-table"
        style="width:100%"
    >
        <thead>
        <tr>
            <th>Appointment No.</th>
            <th>Date</th>
            <th>Time</th>
            <th>Patient</th>
            <th>Visit Type</th>
            <th>Doctor / Consultant</th>
            <th>Appointment Status</th>
            <th>Status</th>
            <th>Created On</th>
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
    var searchTimer = null;
    var has = AppDataTable.has;

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

        document.getElementById('kpiAppointments').textContent =
            Number(summary.total_appointments || 0).toLocaleString('en-IN');

        document.getElementById('kpiScheduled').textContent =
            Number(summary.scheduled_appointments || 0).toLocaleString('en-IN');

        document.getElementById('kpiCompleted').textContent =
            Number(summary.completed_appointments || 0).toLocaleString('en-IN');

        document.getElementById('kpiCancelled').textContent =
            Number(summary.cancelled_appointments || 0).toLocaleString('en-IN');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var parts =
            String(value).split('-');

        return parts.length === 3
            ? parts[2] +
              '/' +
              parts[1] +
              '/' +
              parts[0]
            : esc(value);
    }

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        var date =
            new Date(
                String(value).replace(
                    ' ',
                    'T'
                )
            );

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return esc(value);
        }

        return date.toLocaleString(
            'en-IN',
            {
                day:'2-digit',
                month:'2-digit',
                year:'numeric',
                hour:'2-digit',
                minute:'2-digit'
            }
        );
    }

    var table =
        AppDataTable.init(
            '#appointmentTable',
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
                        title:'Clinic Appointments',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'csvHtml5',
                        text:'CSV',
                        title:'Clinic Appointments',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'excelHtml5',
                        text:'Excel',
                        title:'Clinic Appointments',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'pdfHtml5',
                        text:'PDF',
                        title:'Clinic Appointments',
                        orientation:'landscape',
                        pageSize:'A4',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'print',
                        text:'Print',
                        title:'Clinic Appointments',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    }
                ],

                ajax:function (
                    data,
                    callback
                ) {
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
                        data.search.value || ''
                    );

                    params.set(
                        'visit_type',
                        document.getElementById(
                            'visitTypeFilter'
                        ).value
                    );

                    params.set(
                        'appointment_status',
                        document.getElementById(
                            'appointmentStatusFilter'
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
                        'api/appointments.php?' +
                        params.toString()
                    )
                    .then(
                        function (response) {
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

                            document.getElementById(
                                'addAppointmentButton'
                            ).style.display =
                                has(
                                    formActions,
                                    2
                                )
                                    ? 'inline-flex'
                                    : 'none';

                            setSummary(
                                response.data.summary
                            );

                            AppDataTable.applyExportPermissions(
                                table,
                                listActions
                            );

                            callback(
                                response.data.datatable
                            );
                        }
                    )
                    .catch(
                        function (error) {
                            setSummary({});
                            App.showError(
                                error,
                                'Unable to load Appointments.'
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
                        data:'appointment_no',
                        defaultContent:'-'
                    },
                    {
                        data:'appointment_date',
                        defaultContent:'-',
                        render:function(v,t) {
                            return t === 'display'
                                ? formatDate(v)
                                : v;
                        }
                    },
                    {
                        data:'appointment_time',
                        defaultContent:'-'
                    },
                    {
                        data:'patient_name',
                        defaultContent:'-',
                        render:function(v,t,row) {
                            if (t !== 'display') {
                                return v || '';
                            }

                            var text =
                                (
                                    row.patient_code ||
                                    ''
                                ) +
                                ' - ' +
                                (
                                    v ||
                                    ''
                                );

                            if (row.mobile) {
                                text +=
                                    ' - ' +
                                    row.mobile;
                            }

                            return esc(text);
                        }
                    },
                    {
                        data:'visit_type',
                        defaultContent:'-'
                    },
                    {
                        data:'consultant_name',
                        defaultContent:'-',
                        render:function(v,t) {
                            return t === 'display'
                                ? esc(v || '-')
                                : (v || '');
                        }
                    },
                    {
                        data:'appointment_status',
                        defaultContent:'-',
                        render:function(v,t) {
                            return t === 'display'
                                ? esc(v || '-')
                                : (v || '');
                        }
                    },
                    {
                        data:'status_label',
                        render:function(v,t,row) {
                            if (t !== 'display') {
                                return Number(
                                    row.status ||
                                    0
                                );
                            }

                            return Number(
                                row.status
                            ) === 1
                                ? '<span class="dt-status active">Active</span>'
                                : '<span class="dt-status inactive">Inactive</span>';
                        }
                    },
                    {
                        data:'created_at',
                        defaultContent:'-',
                        render:function(v,t) {
                            return t === 'display'
                                ? formatDateTime(v)
                                : v;
                        }
                    },
                    {
                        data:null,
                        orderable:false,
                        searchable:false,
                        className:'table-action-icons',

                        render:function(
                            data,
                            type,
                            row
                        ) {
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
                                        href:row.view_url,
                                        icon:'eye',
                                        label:'View Appointment'
                                    })
                                );
                            }

                            if (
                                has(
                                    formActions,
                                    3
                                )
                            ) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:row.edit_url,
                                        icon:'pencil',
                                        label:'Edit Appointment'
                                    })
                                );
                            }

                            if (
                                has(
                                    formActions,
                                    4
                                ) &&
                                Number(row.status) === 1
                            ) {
                                actions.push(
                                    '<button type="button" class="table-icon-action delete-appointment" data-ref="' +
                                    esc(row.ref) +
                                    '" title="Deactivate Appointment" aria-label="Deactivate Appointment">' +
                                    '<i data-lucide="trash-2"></i></button>'
                                );
                            }

                            return actions.join(' ') ||
                                '<span class="muted">View only</span>';
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

    function removeDefaultSearch() {
        var tableElement =
            document.getElementById(
                'appointmentTable'
            );

        var card =
            tableElement
                ? tableElement.closest(
                    '.table-card'
                )
                : null;

        var wrapper =
            document.getElementById(
                'appointmentTable_wrapper'
            );

        [card,wrapper].forEach(function(root){
            if(!root)return;

            root.querySelectorAll(
                '#appointmentTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){
                node.remove();
            });
        });
    }

    removeDefaultSearch();

    if (typeof MutationObserver !== 'undefined') {
        var appointmentTableElement =
            document.getElementById('appointmentTable');

        var appointmentCard =
            appointmentTableElement
                ? appointmentTableElement.closest('.table-card')
                : null;

        if (appointmentCard) {
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(
                appointmentCard,
                {childList:true,subtree:true}
            );
        }
    }

    var search =
        document.getElementById(
            'appointmentSearch'
        );

    search.addEventListener(
        'input',
        function () {
            clearTimeout(
                searchTimer
            );

            searchTimer =
                setTimeout(
                    function () {
                        table.search(
                            search.value.trim()
                        ).draw();
                    },
                    300
                );
        }
    );

    [
        'visitTypeFilter',
        'appointmentStatusFilter',
        'dateFromFilter',
        'dateToFilter'
    ].forEach(
        function (id) {
            document.getElementById(id)
                .addEventListener(
                    'change',
                    function () {
                        table.ajax.reload();
                    }
                );
        }
    );

    document.getElementById(
        'appointmentTable'
    ).addEventListener(
        'click',
        function (event) {
            var button =
                event.target.closest(
                    '.delete-appointment'
                );

            if (!button) {
                return;
            }

            if (
                !window.confirm(
                    'Deactivate this Appointment?'
                )
            ) {
                return;
            }

            button.disabled =
                true;

            App.api(
                'api/appointments.php',
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
                function (response) {
                    showToast(
                        response.message ||
                        'Appointment deactivated successfully.',
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
                function (error) {
                    button.disabled =
                        false;

                    App.showError(
                        error,
                        'Unable to deactivate Appointment.'
                    );
                }
            );
        }
    );

    if (window.GlobalSelect) {
        GlobalSelect.init(
            document.getElementById(
                'visitTypeFilter'
            ),
            {
                placeholder:
                    'All Visit Types'
            }
        );

        GlobalSelect.init(
            document.getElementById(
                'appointmentStatusFilter'
            ),
            {
                placeholder:
                    'All Appointment Status'
            }
        );
    }
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
        