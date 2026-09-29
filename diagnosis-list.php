<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Diagnosis Master';

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

            <div class="page-head">
                <div>
                    <h1>Diagnosis Master</h1>
                    <p>Maintain Diseases / Diagnoses and their day-wise Treatment / Procedure plans.</p>
                </div>

                <a class="btn btn-primary" id="addDiagnosisButton" href="diagnosis-form.php">
                    <i data-lucide="plus"></i>
                    Add Diagnosis
                </a>
            </div>

            <div class="kpi-grid">
                <article class="card kpi-card">
                    <span class="kpi-icon blue"><i data-lucide="stethoscope"></i></span>
                    <div>
                        <div class="kpi-label">Total Diagnoses</div>
                        <div class="kpi-value" id="kpiTotal">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
                    <div>
                        <div class="kpi-label">Active Diagnoses</div>
                        <div class="kpi-value" id="kpiActive">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
                    <div>
                        <div class="kpi-label">Inactive Diagnoses</div>
                        <div class="kpi-value" id="kpiInactive">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon teal"><i data-lucide="tags"></i></span>
                    <div>
                        <div class="kpi-label">Categories</div>
                        <div class="kpi-value" id="kpiCategories">0</div>
                    </div>
                </article>
            </div>

            <div class="card table-card">
                <div class="card-header">
                    <div class="form-row" style="width:100%;margin:0;">
                        <div class="field col-4">
                            <label for="diagnosisSearch">Search</label>
                            <input
                                id="diagnosisSearch"
                                type="text"
                                autocomplete="off"
                                placeholder="Diagnosis code, name or category..."
                            >
                        </div>

                        <div class="field col-4">
                            <label for="categoryFilter">Category</label>
                            <select id="categoryFilter">
                                <option value="">All Categories</option>
                            </select>
                        </div>

                        <div class="field col-4">
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
                    <table
                        id="diagnosisTable"
                        class="display data-table"
                        style="width:100%"
                    >
                        <thead>
                        <tr>
                            <th>Diagnosis Code</th>
                            <th>Diagnosis Name</th>
                            <th>Category</th>
                            <th>Treatment Days</th>
                            <th>Status</th>
                            <th>Created On</th>
                            <th>Manage</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <script>
            (function ($, window) {
                'use strict';

                if (
                    !window.AppDataTable ||
                    !AppDataTable.ensureAvailable()
                ) {
                    return;
                }

                var actions = [];
                var has = AppDataTable.has;
                var addButton =
                    document.getElementById('addDiagnosisButton');
                var searchField =
                    document.getElementById('diagnosisSearch');
                var categoryFilter =
                    document.getElementById('categoryFilter');
                var statusFilter =
                    document.getElementById('statusFilter');

                var searchTimer = null;
                var categoriesLoaded = false;

                function esc(value) {
                    return $('<div>')
                        .text(value == null ? '' : String(value))
                        .html();
                }

                function formatDateTime(value) {
                    if (!value) return '-';

                    var text =
                        String(value).replace(' ', 'T');
                    var date = new Date(text);

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

                function setSummary(summary) {
                    summary = summary || {};

                    document.getElementById('kpiTotal').textContent =
                        Number(
                            summary.total_diagnoses || 0
                        ).toLocaleString('en-IN');

                    document.getElementById('kpiActive').textContent =
                        Number(
                            summary.active_diagnoses || 0
                        ).toLocaleString('en-IN');

                    document.getElementById('kpiInactive').textContent =
                        Number(
                            summary.inactive_diagnoses || 0
                        ).toLocaleString('en-IN');

                    document.getElementById('kpiCategories').textContent =
                        Number(
                            summary.categories || 0
                        ).toLocaleString('en-IN');
                }

                function fillCategories(rows) {
                    if (categoriesLoaded) return;
                    categoriesLoaded = true;

                    (rows || []).forEach(function (category) {
                        var option =
                            document.createElement('option');

                        option.value = category;
                        option.textContent = category;
                        categoryFilter.appendChild(option);
                    });
                }

                async function changeStatus(row) {
                    var next =
                        Number(row.status) === 1 ? 0 : 1;

                    try {
                        var result =
                            await App.api(
                                'api/diagnoses.php',
                                {
                                    method:'PATCH',
                                    body:{
                                        ref:row.ref,
                                        status:next
                                    }
                                }
                            );

                        showToast(
                            result.message,
                            {
                                type:'success',
                                duration:2
                            }
                        );

                        table.ajax.reload(null, false);
                    } catch (error) {
                        App.showError(
                            error,
                            'Unable to change Diagnosis status.'
                        );
                    }
                }

                var table =
                    AppDataTable.init(
                        '#diagnosisTable',
                        {
                            serverSide:true,
                            searching:true,
                            searchDelay:300,
                            appSearch:false,
                            appLoaderText:'Loading Diagnosis records...',
                            pageLength:25,
                            lengthMenu:[
                                [10,25,50,100],
                                [10,25,50,100]
                            ],
                            order:[[1,'asc']],
                            scrollX:true,
                            autoWidth:false,

                            buttons:[
                                {
                                    extend:'copyHtml5',
                                    text:'Copy',
                                    title:'Diagnosis Master',
                                    action:AppDataTable.serverSideExportAction,
                                    exportOptions:{columns:[0,1,2,3,4,5]}
                                },
                                {
                                    extend:'csvHtml5',
                                    text:'CSV',
                                    title:'Diagnosis Master',
                                    action:AppDataTable.serverSideExportAction,
                                    exportOptions:{columns:[0,1,2,3,4,5]}
                                },
                                {
                                    extend:'excelHtml5',
                                    text:'Excel',
                                    title:'Diagnosis Master',
                                    action:AppDataTable.serverSideExportAction,
                                    exportOptions:{columns:[0,1,2,3,4,5]}
                                },
                                {
                                    extend:'pdfHtml5',
                                    text:'PDF',
                                    title:'Diagnosis Master',
                                    orientation:'landscape',
                                    pageSize:'A4',
                                    action:AppDataTable.serverSideExportAction,
                                    exportOptions:{columns:[0,1,2,3,4,5]}
                                },
                                {
                                    extend:'print',
                                    text:'Print',
                                    title:'Diagnosis Master',
                                    action:AppDataTable.serverSideExportAction,
                                    exportOptions:{columns:[0,1,2,3,4,5]}
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

                                if (categoryFilter.value !== '') {
                                    params.set(
                                        'category',
                                        categoryFilter.value
                                    );
                                }

                                if (statusFilter.value !== '') {
                                    params.set(
                                        'status',
                                        statusFilter.value
                                    );
                                }

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
                                    'api/diagnoses.php?' +
                                    params.toString()
                                )
                                .then(function (result) {
                                    actions =
                                        (
                                            result.data.allowed_actions ||
                                            []
                                        ).map(Number);

                                    addButton.style.display =
                                        has(actions,2)
                                            ? 'inline-flex'
                                            : 'none';

                                    fillCategories(
                                        result.data.filter_categories || []
                                    );

                                    setSummary(
                                        result.data.summary || {}
                                    );

                                    AppDataTable.applyExportPermissions(
                                        table,
                                        actions
                                    );

                                    callback(
                                        result.data.datatable
                                    );
                                })
                                .catch(function (error) {
                                    addButton.style.display = 'none';
                                    setSummary({});

                                    App.showError(
                                        error,
                                        'Unable to load Diagnosis records.'
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
                                    data:'diagnosis_code',
                                    defaultContent:'-'
                                },
                                {
                                    data:'diagnosis_name',
                                    defaultContent:'-'
                                },
                                {
                                    data:'category',
                                    defaultContent:'-',
                                    render:function (v,t) {
                                        return t === 'display'
                                            ? esc(v || '-')
                                            : (v || '');
                                    }
                                },
                                {
                                    data:'treatment_days',
                                    className:'dt-body-right',
                                    render:function (v,t) {
                                        var days = Number(v || 0);

                                        if (t !== 'display') {
                                            return days;
                                        }

                                        return days +
                                            ' Day' +
                                            (days === 1 ? '' : 's');
                                    }
                                },
                                {
                                    data:'status',
                                    render:function (v,t) {
                                        if (t !== 'display') {
                                            return Number(v);
                                        }

                                        return Number(v) === 1
                                            ? '<span class="dt-status active">Active</span>'
                                            : '<span class="dt-status inactive">Inactive</span>';
                                    }
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
                                    render:function (
                                        data,
                                        type,
                                        row
                                    ) {
                                        if (type !== 'display') {
                                            return '';
                                        }

                                        var html = '';

                                        if (has(actions,1)) {
                                            html +=
                                                App.iconActionHtml({
                                                    href:row.view_url,
                                                    icon:'eye',
                                                    label:'View Diagnosis'
                                                });
                                        }

                                        if (has(actions,3)) {
                                            html +=
                                                App.iconActionHtml({
                                                    href:row.edit_url,
                                                    icon:'pencil',
                                                    label:'Edit Diagnosis'
                                                });
                                        }

                                        if (
                                            Number(row.status) === 1 &&
                                            has(actions,28)
                                        ) {
                                            html +=
                                                '<button type="button" class="table-icon-action danger js-status" title="Deactivate Diagnosis" aria-label="Deactivate Diagnosis">' +
                                                '<i data-lucide="circle-off"></i></button>';
                                        }

                                        if (
                                            Number(row.status) === 0 &&
                                            has(actions,27)
                                        ) {
                                            html +=
                                                '<button type="button" class="table-icon-action success js-status" title="Activate Diagnosis" aria-label="Activate Diagnosis">' +
                                                '<i data-lucide="circle-check"></i></button>';
                                        }

                                        return html ||
                                            '<span class="muted">View only</span>';
                                    }
                                }
                            ],

                            language:{
                                emptyTable:'No Diagnosis records found.',
                                zeroRecords:'No matching Diagnosis records found.',
                                processing:'Loading Diagnosis records...'
                            },

                            drawCallback:function () {
                                if (window.lucide) {
                                    window.lucide.createIcons();
                                }
                            }
                        }
                    );

                (function removeDefaultSearchRow() {
                    var tableElement =
                        document.getElementById(
                            'diagnosisTable'
                        );

                    var card =
                        tableElement
                            ? tableElement.closest(
                                '.table-card'
                            )
                            : null;

                    var row =
                        card
                            ? card.querySelector(
                                '.app-table-search-row'
                            )
                            : null;

                    if (row) row.remove();
                })();

                searchField.addEventListener(
                    'input',
                    function () {
                        clearTimeout(searchTimer);

                        searchTimer =
                            setTimeout(
                                function () {
                                    table.search(
                                        searchField.value.trim()
                                    ).draw();
                                },
                                300
                            );
                    }
                );

                [categoryFilter,statusFilter].forEach(
                    function (field) {
                        field.addEventListener(
                            'change',
                            function () {
                                table.ajax.reload(null, true);
                            }
                        );
                    }
                );

                $(document).on(
                    'click',
                    '.js-status',
                    function () {
                        var row =
                            table.row(
                                $(this).closest('tr')
                            ).data();

                        if (row) changeStatus(row);
                    }
                );
            })(window.jQuery, window);
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
<script src="assets/js/appearance.js"></script>
</body>
</html>
