<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Treatment / Procedure Master';

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
        <h1>Treatment / Procedure Master</h1>
        <p>Reusable Treatment / Procedure values used in Protocols and Patient Treatment Plans.</p>
    </div>

    <a
        class="btn btn-primary"
        id="addProcedureButton"
        href="treatment-procedure-form.php"
    >
        <i data-lucide="plus"></i>
        Add Treatment / Procedure
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="clipboard-list"></i></span>
        <div>
            <div class="kpi-label">Total Procedures</div>
            <div class="kpi-value" id="kpiTreatmentTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active</div>
            <div class="kpi-value" id="kpiTreatmentActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive</div>
            <div class="kpi-value" id="kpiTreatmentInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Average Price</div>
            <div class="kpi-value" id="kpiTreatmentAverage">₹0.00</div>
        </div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-8">
                <label for="procedureSearch">Search</label>
                <input
                    id="procedureSearch"
                    type="text"
                    placeholder="Code or Treatment / Procedure Name"
                >
            </div>

            <div class="field col-4">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="table-scroll">
        <table id="procedureTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Procedure Code</th>
                <th>Treatment / Procedure Name</th>
                <th>Price</th>
                <th>Status</th>
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

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) {
        return;
    }

    var listActions = [];
    var formActions = [];
    var timer = null;
    var has = AppDataTable.has;

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function money(value) {
        var number = Number(value || 0);
        if (!Number.isFinite(number)) {
            number = 0;
        }

        return '₹' + number.toLocaleString('en-IN',{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById('kpiTreatmentTotal').textContent =
            Number(summary.total_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiTreatmentActive').textContent =
            Number(summary.active_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiTreatmentInactive').textContent =
            Number(summary.inactive_count || 0).toLocaleString('en-IN');

        document.getElementById('kpiTreatmentAverage').textContent =
            money(summary.average_price || 0);
    }

    function removeDefaultSearch() {
        var tableElement = document.getElementById('procedureTable');
        var card = tableElement ? tableElement.closest('.table-card') : null;
        var wrapper = document.getElementById('procedureTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root)return;

            root.querySelectorAll(
                '#procedureTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){
                node.remove();
            });
        });
    }

    if (window.GlobalSelect) {
        GlobalSelect.init(
            document.getElementById('statusFilter'),
            {placeholder:'All'}
        );
    }

    var table = AppDataTable.init(
        '#procedureTable',
        {
            serverSide:true,
            searching:true,
            appSearch:false,
            pageLength:10,
            order:[[1,'asc']],
            scrollX:true,
            autoWidth:false,

            buttons:[
                {
                    extend:'copyHtml5',
                    text:'Copy',
                    title:'Treatment Procedure Master',
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,1,2,3,4]}
                },
                {
                    extend:'csvHtml5',
                    text:'CSV',
                    title:'Treatment Procedure Master',
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,1,2,3,4]}
                },
                {
                    extend:'excelHtml5',
                    text:'Excel',
                    title:'Treatment Procedure Master',
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,1,2,3,4]}
                },
                {
                    extend:'pdfHtml5',
                    text:'PDF',
                    title:'Treatment Procedure Master',
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,1,2,3,4]}
                },
                {
                    extend:'print',
                    text:'Print',
                    title:'Treatment Procedure Master',
                    action:AppDataTable.serverSideExportAction,
                    exportOptions:{columns:[0,1,2,3,4]}
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
                    'status',
                    document.getElementById('statusFilter').value
                );

                if (data.order && data.order[0]) {
                    params.set('order[0][column]',data.order[0].column);
                    params.set('order[0][dir]',data.order[0].dir);
                }

                App.api(
                    'api/treatment-procedures.php?' +
                    params.toString()
                )
                .then(function(response){
                    listActions = (response.data.list_actions || []).map(Number);
                    formActions = (response.data.form_actions || []).map(Number);
                    setSummary(response.data.summary);

                    document.getElementById('addProcedureButton').style.display =
                        has(formActions,2) ? 'inline-flex' : 'none';

                    AppDataTable.applyExportPermissions(table,listActions);

                    callback(response.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,'Unable to load Treatment / Procedure Master.');
                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
            },

            columns:[
                {data:'procedure_code',defaultContent:'-'},
                {data:'procedure_name',defaultContent:'-'},
                {data:'price',defaultContent:'0.00',render:function(v){return '₹ '+(v||'0.00');}},
                {data:'status_label',defaultContent:'-'},
                {data:'created_at',defaultContent:'-'},
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
                                    label:'View Treatment / Procedure'
                                })
                            );
                        }

                        if (has(formActions,3)) {
                            actions.push(
                                App.iconActionHtml({
                                    href:row.edit_url,
                                    icon:'pencil',
                                    label:'Edit Treatment / Procedure'
                                })
                            );
                        }

                        if (has(formActions,4) && Number(row.status) === 1) {
                            actions.push(
                                '<button type="button" ' +
                                'class="table-icon-action delete-procedure" ' +
                                'data-ref="' + esc(row.ref) + '" ' +
                                'title="Deactivate Treatment / Procedure">' +
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
                if(window.lucide){window.lucide.createIcons();}
            }
        }
    );

    removeDefaultSearch();

    if (typeof MutationObserver !== 'undefined') {
        var procedureTableElement =
            document.getElementById('procedureTable');

        var procedureCard =
            procedureTableElement
                ? procedureTableElement.closest('.table-card')
                : null;

        if (procedureCard) {
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(
                procedureCard,
                {childList:true,subtree:true}
            );
        }
    }

    document.getElementById('statusFilter')
        .addEventListener('change',function(){
            table.ajax.reload(null,true);
        });

    document.getElementById('procedureSearch')
        .addEventListener('input',function(){
            clearTimeout(timer);
            timer = setTimeout(function(){
                table.search(
                    document.getElementById('procedureSearch').value.trim()
                ).draw();
            },300);
        });

    document.getElementById('procedureTable')
        .addEventListener('click',function(event){
            var button = event.target.closest('.delete-procedure');

            if (!button) return;

            if (!window.confirm('Deactivate this Treatment / Procedure?')) {
                return;
            }

            button.disabled = true;

            App.api(
                'api/treatment-procedures.php',
                {
                    method:'POST',
                    body:{
                        action:'delete',
                        ref:button.dataset.ref
                    }
                }
            )
            .then(function(response){
                showToast(
                    response.message || 'Treatment / Procedure deactivated successfully.',
                    {type:'success',duration:2}
                );
                table.ajax.reload(null,false);
            })
            .catch(function(error){
                button.disabled = false;
                App.showError(error,'Unable to deactivate Treatment / Procedure.');
            });
        });

})(jQuery,window,document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
