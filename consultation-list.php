<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Consultations';

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
        <h1>Consultations</h1>
        <p>Appointment and Walk-in Consultations with Protocol or General Treatment.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="appointment-calendar.php">
            <i data-lucide="calendar-days"></i>
            Appointment Calendar
        </a>

        <a
            class="btn btn-primary"
            id="addConsultationButton"
            href="consultation-form.php"
        >
            <i data-lucide="plus"></i>
            Add Consultation / Walk-in
        </a>
    </div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="stethoscope"></i></span>
        <div>
            <div class="kpi-label">Consultations</div>
            <div class="kpi-value" id="kpiConsultationTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="clock-3"></i></span>
        <div>
            <div class="kpi-label">In Progress</div>
            <div class="kpi-value" id="kpiConsultationProgress">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Completed</div>
            <div class="kpi-value" id="kpiConsultationCompleted">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="calendar-clock"></i></span>
        <div>
            <div class="kpi-label">Follow-up Required</div>
            <div class="kpi-value" id="kpiConsultationFollowup">0</div>
        </div>
    </article>
</div>


<div class="card table-card">

<div class="card-header">
    <div class="form-row" style="width:100%;margin:0;">
        <div class="field col-4">
            <label for="consultationSearch">Search</label>
            <input
                id="consultationSearch"
                type="text"
                placeholder="Consultation, patient, diagnosis, plan..."
            >
        </div>

        <div class="field col-2">
            <label for="visitSourceFilter">Visit Source</label>
            <select id="visitSourceFilter">
                <option value="">All</option>
                <option value="appointment">Appointment</option>
                <option value="walkin">Walk-in</option>
            </select>
        </div>

        <div class="field col-3">
            <label for="treatmentBasisFilter">Treatment Basis</label>
            <select id="treatmentBasisFilter">
                <option value="">All</option>
                <option value="protocol">Protocol</option>
                <option value="general">General</option>
            </select>
        </div>

        <div class="field col-3">
            <label for="consultationStatusFilter">Consultation Status</label>
            <select id="consultationStatusFilter">
                <option value="">All Status</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Follow-up Required">Follow-up Required</option>
            </select>
        </div>
    </div>
</div>

<div class="table-scroll">
<table
    id="consultationTable"
    class="display data-table"
    style="width:100%"
>
    <thead>
    <tr>
        <th>Consultation No.</th>
        <th>Visit Date</th>
        <th>Patient</th>
        <th>Source</th>
        <th>Diagnosis</th>
        <th>Treatment Basis</th>
        <th>Plan / Protocol</th>
        <th>Days</th>
        <th>Consultation Status</th>
        <th>Created On</th>
        <th>Manage</th>
    </tr>
    </thead>
</table>
</div>

</div>

<script>
(function ($, window, document) {
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
            .text(value == null ? '' : String(value))
            .html();
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiConsultationTotal').textContent =
            Number(summary.total_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiConsultationProgress').textContent =
            Number(summary.in_progress_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiConsultationCompleted').textContent =
            Number(summary.completed_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiConsultationFollowup').textContent =
            Number(summary.followup_count || 0).toLocaleString('en-IN');
    }

    function removeDefaultSearch() {
        var tableElement =
            document.getElementById(
                'consultationTable'
            );

        var card =
            tableElement
                ? tableElement.closest('.table-card')
                : null;

        var wrapper =
            document.getElementById(
                'consultationTable_wrapper'
            );

        [card,wrapper].forEach(
            function(root) {
                if (!root) return;

                root.querySelectorAll(
                    '#consultationTable_filter,.dataTables_filter,.app-table-search-row'
                ).forEach(
                    function(node) {
                        node.remove();
                    }
                );
            }
        );
    }

    function formatDate(value) {
        if (!value) return '-';

        var parts = String(value).split('-');

        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : esc(value);
    }

    function formatDateTime(value) {
        if (!value) return '-';

        var date =
            new Date(
                String(value).replace(' ', 'T')
            );

        if (Number.isNaN(date.getTime())) {
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
            '#consultationTable',
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
                        title:'Clinic Consultations',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
                    },
                    {
                        extend:'csvHtml5',
                        text:'CSV',
                        title:'Clinic Consultations',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
                    },
                    {
                        extend:'excelHtml5',
                        text:'Excel',
                        title:'Clinic Consultations',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
                    },
                    {
                        extend:'pdfHtml5',
                        text:'PDF',
                        title:'Clinic Consultations',
                        orientation:'landscape',
                        pageSize:'A4',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
                    },
                    {
                        extend:'print',
                        text:'Print',
                        title:'Clinic Consultations',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}
                    }
                ],

                ajax:function (data, callback) {
                    var params =
                        new URLSearchParams();

                    params.set('datatable','1');
                    params.set('draw',data.draw);
                    params.set('start',data.start);
                    params.set('length',data.length);
                    params.set(
                        'search[value]',
                        data.search.value || ''
                    );

                    params.set(
                        'visit_source',
                        document.getElementById(
                            'visitSourceFilter'
                        ).value
                    );

                    params.set(
                        'treatment_basis',
                        document.getElementById(
                            'treatmentBasisFilter'
                        ).value
                    );

                    params.set(
                        'consultation_status',
                        document.getElementById(
                            'consultationStatusFilter'
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
                        'api/consultations.php?' +
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

                        document.getElementById(
                            'addConsultationButton'
                        ).style.display =
                            has(formActions,2)
                                ? 'inline-flex'
                                : 'none';

                        AppDataTable.applyExportPermissions(
                            table,
                            listActions
                        );

                        callback(
                            response.data.datatable
                        );
                    })
                    .catch(function (error) {
                        setSummary({});

                        App.showError(
                            error,
                            'Unable to load Consultations.'
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
                        data:'consultation_no',
                        defaultContent:'-'
                    },
                    {
                        data:'visit_date',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v;

                            var value = formatDate(v);

                            if (row.visit_time) {
                                value += ' ' + esc(row.visit_time);
                            }

                            return value;
                        }
                    },
                    {
                        data:'patient_name',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            var value =
                                (row.patient_code || '') +
                                ' - ' +
                                (v || '');

                            if (row.mobile) {
                                value +=
                                    ' - ' +
                                    row.mobile;
                            }

                            return esc(value);
                        }
                    },
                    {
                        data:'visit_source',
                        defaultContent:'-'
                    },
                    {
                        data:'diagnosis_name',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            if (!v) {
                                return '<span class="muted">Not selected</span>';
                            }

                            return esc(
                                (row.diagnosis_code || '') +
                                ' - ' +
                                v
                            );
                        }
                    },
                    {
                        data:'treatment_basis',
                        defaultContent:'-'
                    },
                    {
                        data:'plan_name',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            var text = v || '-';

                            if (
                                row.treatment_basis === 'Protocol' &&
                                row.protocol_name
                            ) {
                                text +=
                                    ' / ' +
                                    row.protocol_name;
                            }

                            return esc(text);
                        }
                    },
                    {
                        data:'duration_days',
                        defaultContent:'0'
                    },
                    {
                        data:'consultation_status',
                        defaultContent:'-'
                    },
                    {
                        data:'created_at',
                        defaultContent:'-',
                        render:function (v,t) {
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

                        render:function (data,type,row) {
                            if (type !== 'display') {
                                return '';
                            }

                            var actions = [];

                            if (has(formActions,1)) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:row.view_url,
                                        icon:'eye',
                                        label:'View Consultation'
                                    })
                                );
                            }

                            if (has(formActions,3)) {
                                actions.push(
                                    App.iconActionHtml({
                                        href:row.edit_url,
                                        icon:'pencil',
                                        label:'Edit Consultation'
                                    })
                                );
                            }

                            actions.push(
                                App.iconActionHtml({
                                    href:
                                        'treatment-list.php?consultation_ref=' +
                                        encodeURIComponent(row.ref),
                                    icon:'activity',
                                    label:'Treatment Progress'
                                })
                            );

                            actions.push(
                                App.iconActionHtml({
                                    href:
                                        'bill-form.php?consultation_ref=' +
                                        encodeURIComponent(row.ref),
                                    icon:'receipt',
                                    label:'Create / Open Bill'
                                })
                            );

                            if (
                                has(formActions,4) &&
                                Number(row.status) === 1
                            ) {
                                actions.push(
                                    '<button type="button" ' +
                                    'class="table-icon-action delete-consultation" ' +
                                    'data-ref="' +
                                    esc(row.ref) +
                                    '" title="Deactivate Consultation" ' +
                                    'aria-label="Deactivate Consultation">' +
                                    '<i data-lucide="trash-2"></i>' +
                                    '</button>'
                                );
                            }

                            return actions.join(' ');
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

    if (typeof MutationObserver !== 'undefined') {
        var consultationTableElement =
            document.getElementById(
                'consultationTable'
            );

        var consultationCard =
            consultationTableElement
                ? consultationTableElement.closest(
                    '.table-card'
                )
                : null;

        if (consultationCard) {
            new MutationObserver(
                function () {
                    removeDefaultSearch();
                }
            ).observe(
                consultationCard,
                {
                    childList:true,
                    subtree:true
                }
            );
        }
    }

    var search =
        document.getElementById(
            'consultationSearch'
        );

    search.addEventListener(
        'input',
        function () {
            clearTimeout(searchTimer);

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
        'visitSourceFilter',
        'treatmentBasisFilter',
        'consultationStatusFilter'
    ].forEach(function (id) {
        document.getElementById(id)
            .addEventListener(
                'change',
                function () {
                    table.ajax.reload();
                }
            );
    });

    document.getElementById(
        'consultationTable'
    ).addEventListener(
        'click',
        function (event) {
            var button =
                event.target.closest(
                    '.delete-consultation'
                );

            if (!button) return;

            if (
                !window.confirm(
                    'Deactivate this Consultation?'
                )
            ) {
                return;
            }

            button.disabled = true;

            App.api(
                'api/consultations.php',
                {
                    method:'POST',
                    body:{
                        action:'delete',
                        ref:button.dataset.ref
                    }
                }
            )
            .then(function (response) {
                showToast(
                    response.message ||
                    'Consultation deactivated successfully.',
                    {
                        type:'success',
                        duration:2
                    }
                );

                table.ajax.reload(
                    null,
                    false
                );
            })
            .catch(function (error) {
                button.disabled = false;

                App.showError(
                    error,
                    'Unable to deactivate Consultation.'
                );
            });
        }
    );

    if (window.GlobalSelect) {
        GlobalSelect.init(
            '#visitSourceFilter',
            {
                placeholder:'All'
            }
        );

        GlobalSelect.init(
            '#treatmentBasisFilter',
            {
                placeholder:'All'
            }
        );

        GlobalSelect.init(
            '#consultationStatusFilter',
            {
                placeholder:'All Status'
            }
        );
    }

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
