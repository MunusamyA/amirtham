<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Patient Treatment / Progress';

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
        <h1>Patient Treatment / Progress</h1>
        <p>Pending, In Progress, Completed and Skipped treatment days for Diagnosis Treatment and General Treatment plans.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="consultation-list.php">
            <i data-lucide="clipboard-list"></i>
            Consultations
        </a>

        <a
            class="btn btn-primary"
            id="addProgressButton"
            href="treatment-form.php"
        >
            <i data-lucide="activity"></i>
            Update Treatment
        </a>
    </div>
</div>

<div class="card table-card">

<div class="card-header">
    <div class="form-row">
        <div class="field col-3">
            <label for="treatmentSearch">Search</label>
            <input
                id="treatmentSearch"
                type="text"
                placeholder="Patient, consultation, diagnosis, treatment..."
            >
        </div>

        <div class="field col-3">
            <label for="patientFilter">Patient</label>
            <select id="patientFilter">
                <option value="">All Patients</option>
            </select>
        </div>

        <div class="field col-3">
            <label for="treatmentBasisFilter">Treatment Basis</label>
            <select id="treatmentBasisFilter">
                <option value="">All</option>
                <option value="protocol">Diagnosis Treatment</option>
                <option value="general">General</option>
            </select>
        </div>

        <div class="field col-3">
            <label for="progressStatusFilter">Progress Status</label>
            <select id="progressStatusFilter">
                <option value="">All</option>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Skipped">Skipped</option>
            </select>
        </div>
    </div>
</div>

<div class="table-scroll">
<table
    id="treatmentTable"
    class="display data-table"
>
    <thead>
    <tr>
        <th>Patient</th>
        <th>Consultation</th>
        <th>Diagnosis</th>
        <th>Basis</th>
        <th>Plan</th>
        <th>Day</th>
        <th>Treatment / Procedure</th>
        <th>Treatment Date</th>
        <th>Progress Status</th>
        <th>Manage</th>
    </tr>
    </thead>
</table>
</div>

</div>

<script>
(function ($,window,document) {
    'use strict';

    if (
        !window.AppDataTable ||
        !AppDataTable.ensureAvailable()
    ) {
        return;
    }

    var listActions = [];
    var formActions = [];
    var patientSelect = null;
    var searchTimer = null;
    var has = AppDataTable.has;

    var queryParams =
        new URLSearchParams(
            window.location.search
        );

    var consultationRef =
        queryParams.get(
            'consultation_ref'
        ) || '';

    function esc(value) {
        return $('<div>')
            .text(value == null ? '' : String(value))
            .html();
    }

    function formatDate(value) {
        if (!value) return '-';

        var parts =
            String(value).split('-');

        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : esc(value);
    }

    function patientItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value:row.ref,
                text:row.label
            };
        });
    }

    async function loadFilters() {
        try {
            var response =
                await App.api(
                    'api/treatments.php?options=1'
                );

            var data =
                response &&
                response.data
                    ? response.data
                    : {};

            if (patientSelect) {
                patientSelect.setOptions(
                    patientItems(
                        Array.isArray(data.patients)
                            ? data.patients
                            : []
                    ),
                    ''
                );
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to load Treatment filters.'
            );
        }
    }

    var table =
        AppDataTable.init(
            '#treatmentTable',
            {
                serverSide:true,
                searching:true,
                appSearch:false,
                pageLength:10,
                lengthMenu:[
                    [10,25,50,100],
                    [10,25,50,100]
                ],
                order:[[0,'asc']],
                scrollX:true,
                autoWidth:false,

                buttons:[
                    {
                        extend:'copyHtml5',
                        text:'Copy',
                        title:'Patient Treatment Progress',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'csvHtml5',
                        text:'CSV',
                        title:'Patient Treatment Progress',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'excelHtml5',
                        text:'Excel',
                        title:'Patient Treatment Progress',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'pdfHtml5',
                        text:'PDF',
                        title:'Patient Treatment Progress',
                        orientation:'landscape',
                        pageSize:'A4',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    },
                    {
                        extend:'print',
                        text:'Print',
                        title:'Patient Treatment Progress',
                        action:AppDataTable.serverSideExportAction,
                        exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}
                    }
                ],

                ajax:function (data,callback) {
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

                    var patientRef =
                        document.getElementById(
                            'patientFilter'
                        ).value;

                    var basis =
                        document.getElementById(
                            'treatmentBasisFilter'
                        ).value;

                    var progress =
                        document.getElementById(
                            'progressStatusFilter'
                        ).value;

                    if (patientRef) {
                        params.set(
                            'patient_ref',
                            patientRef
                        );
                    }

                    if (basis) {
                        params.set(
                            'treatment_basis',
                            basis
                        );
                    }

                    if (progress) {
                        params.set(
                            'progress_status',
                            progress
                        );
                    }

                    if (consultationRef) {
                        params.set(
                            'consultation_ref',
                            consultationRef
                        );
                    }

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
                        'api/treatments.php?' +
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

                        document.getElementById(
                            'addProgressButton'
                        ).style.display =
                            has(formActions,2) ||
                            has(formActions,3)
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
                        App.showError(
                            error,
                            'Unable to load Patient Treatment Progress.'
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
                        data:'patient_name',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            var text =
                                (row.patient_code || '') +
                                ' - ' +
                                (v || '');

                            if (row.mobile) {
                                text +=
                                    ' - ' +
                                    row.mobile;
                            }

                            return esc(text);
                        }
                    },
                    {
                        data:'consultation_no',
                        defaultContent:'-'
                    },
                    {
                        data:'diagnosis_name',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            return v
                                ? esc(
                                    (row.diagnosis_code || '') +
                                    ' - ' +
                                    v
                                  )
                                : '<span class="muted">Not selected</span>';
                        }
                    },
                    {
                        data:'treatment_basis',
                        defaultContent:'-',
                        render:function (v,t) {
                            var value =
                                String(v || '').toLowerCase();

                            if (value === 'protocol') {
                                return 'Diagnosis Treatment';
                            }

                            if (value === 'general') {
                                return 'General Treatment';
                            }

                            return v || '-';
                        }
                    },
                    {
                        data:'plan_name',
                        defaultContent:'-'
                    },
                    {
                        data:'day_number',
                        render:function (v,t) {
                            return t === 'display'
                                ? 'Day ' + esc(v)
                                : v;
                        }
                    },
                    {
                        data:'procedure_name',
                        defaultContent:'-',
                        render:function(v,t,row) {
                            if (t !== 'display') return v || '';

                            return esc(
                                (row.procedure_code || '') +
                                ' - ' +
                                (v || '')
                            );
                        }
                    },
                    {
                        data:'treatment_date',
                        defaultContent:'-',
                        render:function (v,t,row) {
                            if (t !== 'display') return v || '';

                            var value = formatDate(v);

                            if (row.treatment_time) {
                                value +=
                                    ' ' +
                                    esc(row.treatment_time);
                            }

                            return value;
                        }
                    },
                    {
                        data:'progress_status',
                        defaultContent:'Pending'
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

                            if (
                                !has(formActions,2) &&
                                !has(formActions,3)
                            ) {
                                return '<span class="muted">View only</span>';
                            }

                            return App.iconActionHtml({
                                href:row.form_url,
                                icon:
                                    row.progress_status === 'Pending'
                                        ? 'play'
                                        : 'pencil',
                                label:
                                    row.progress_status === 'Pending'
                                        ? 'Record Treatment'
                                        : 'Update Treatment Progress'
                            });
                        }
                    }
                ],

                drawCallback:function () {
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            }
        );

    if (window.GlobalSelect) {
        patientSelect =
            GlobalSelect.init(
                '#patientFilter',
                {
                    placeholder:'All Patients'
                }
            );

        GlobalSelect.init(
            '#treatmentBasisFilter',
            {
                placeholder:'All'
            }
        );

        GlobalSelect.init(
            '#progressStatusFilter',
            {
                placeholder:'All'
            }
        );
    }

    var search =
        document.getElementById(
            'treatmentSearch'
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
        'patientFilter',
        'treatmentBasisFilter',
        'progressStatusFilter'
    ].forEach(function (id) {
        document.getElementById(id)
            .addEventListener(
                'change',
                function () {
                    table.ajax.reload();
                }
            );
    });

    loadFilters();

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
