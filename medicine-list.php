<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Medicine Master';
$headStyles = ['https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css', 'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'];
$headScripts = ['https://code.jquery.com/jquery-3.7.1.min.js', 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title><?php render_frontend_config_script(); ?><script src="assets/js/runtime.js"></script><?php foreach ($headStyles as $url): ?>
        <link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script><?php foreach ($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
</head>

<body>
    <div class="app-shell"><?php require __DIR__ . '/include/sidebar.php'; ?><main class="main-stage"><?php require __DIR__ . '/include/topbar.php'; ?><section class="page-content">
                <script src="assets/js/toaster.js"></script>
                <script src="assets/js/app.js"></script>
                <script src="assets/js/theme.js"></script>
                <script src="assets/js/layout.js"></script>
                <script src="assets/js/datatable.js"></script>
                <script src="assets/js/global-select.js"></script>
                <div class="page-head">
                    <div>
                        <h1>Medicine Master</h1>
                        <p>Clinic master data used for automatic price calculation.</p>
                    </div><a class="btn btn-primary" id="addButton" href="medicine-form.php"><i data-lucide="plus"></i> Add Medicine</a>
                </div>

                <div class="kpi-grid">
                    <article class="card kpi-card">
                        <span class="kpi-icon blue"><i data-lucide="pill"></i></span>
                        <div>
                            <div class="kpi-label">Total Medicines</div>
                            <div class="kpi-value" id="kpiMedicineTotal">0</div>
                        </div>
                    </article>

                    <article class="card kpi-card">
                        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
                        <div>
                            <div class="kpi-label">Active</div>
                            <div class="kpi-value" id="kpiMedicineActive">0</div>
                        </div>
                    </article>

                    <article class="card kpi-card">
                        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
                        <div>
                            <div class="kpi-label">Inactive</div>
                            <div class="kpi-value" id="kpiMedicineInactive">0</div>
                        </div>
                    </article>

                    <article class="card kpi-card">
                        <span class="kpi-icon teal"><i data-lucide="layers-3"></i></span>
                        <div>
                            <div class="kpi-label">Medicine Types</div>
                            <div class="kpi-value" id="kpiMedicineTypes">0</div>
                        </div>
                    </article>
                </div>

                <div class="card table-card">
                    <div class="card-header">
                        <div class="form-row" style="width:100%;margin:0;">
                            <div class="field col-6">
                                <label for="masterSearch">Search</label>
                                <input id="masterSearch" type="text" placeholder="Code / Medicine / Type">
                            </div>

                            <div class="field col-3">
                                <label for="medicineTypeFilter">Medicine Type</label>
                                <select id="medicineTypeFilter">
                                    <option value="">All Types</option>
                                </select>
                            </div>

                            <div class="field col-3">
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
                        <table id="masterTable" class="display data-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Medicine Code</th>
                                    <th>Medicine Name</th>
                                    <th>Type</th>
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
                    (function($, window, document) {
                        'use strict';
                        if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;
                        var listActions = [],
                            formActions = [],
                            timer = null,
                            has = AppDataTable.has,
                            medicineTypesLoaded = false,
                            medicineTypeGlobal = null,
                            statusGlobal = null;

                        function esc(v) {
                            return $('<div>').text(v == null ? '' : String(v)).html();
                        }

                        function setSummary(summary) {
                            summary = summary || {};

                            document.getElementById('kpiMedicineTotal').textContent =
                                Number(summary.total_count || 0).toLocaleString('en-IN');

                            document.getElementById('kpiMedicineActive').textContent =
                                Number(summary.active_count || 0).toLocaleString('en-IN');

                            document.getElementById('kpiMedicineInactive').textContent =
                                Number(summary.inactive_count || 0).toLocaleString('en-IN');

                            document.getElementById('kpiMedicineTypes').textContent =
                                Number(summary.type_count || 0).toLocaleString('en-IN');
                        }

                        function removeDefaultSearch() {
                            var tableElement=document.getElementById('masterTable');
                            var card=tableElement?tableElement.closest('.table-card'):null;
                            var wrapper=document.getElementById('masterTable_wrapper');

                            [card,wrapper].forEach(function(root){
                                if(!root)return;

                                root.querySelectorAll(
                                    '#masterTable_filter,.dataTables_filter,.app-table-search-row'
                                ).forEach(function(node){
                                    node.remove();
                                });
                            });
                        }

                        if(window.GlobalSelect){
                            medicineTypeGlobal=GlobalSelect.init(
                                document.getElementById('medicineTypeFilter'),
                                {placeholder:'All Types'}
                            );

                            statusGlobal=GlobalSelect.init(
                                document.getElementById('statusFilter'),
                                {placeholder:'All'}
                            );
                        }

                        var table = AppDataTable.init('#masterTable', {
                            serverSide: true,
                            searching: true,
                            appSearch: false,
                            pageLength: 10,
                            order: [
                                [1, 'asc']
                            ],
                            scrollX: true,
                            autoWidth: false,
                            buttons: [{
                                extend: 'copyHtml5',
                                text: 'Copy',
                                title: 'Medicine Master',
                                action: AppDataTable.serverSideExportAction
                            }, {
                                extend: 'csvHtml5',
                                text: 'CSV',
                                title: 'Medicine Master',
                                action: AppDataTable.serverSideExportAction
                            }, {
                                extend: 'excelHtml5',
                                text: 'Excel',
                                title: 'Medicine Master',
                                action: AppDataTable.serverSideExportAction
                            }, {
                                extend: 'pdfHtml5',
                                text: 'PDF',
                                title: 'Medicine Master',
                                action: AppDataTable.serverSideExportAction
                            }, {
                                extend: 'print',
                                text: 'Print',
                                title: 'Medicine Master',
                                action: AppDataTable.serverSideExportAction
                            }],
                            ajax: function(data, callback) {
                                var p = new URLSearchParams();
                                p.set('datatable', '1');
                                p.set('draw', data.draw);
                                p.set('start', data.start);
                                p.set('length', data.length);
                                p.set('search[value]', data.search.value || '');
                                p.set(
                                    'medicine_type',
                                    document.getElementById('medicineTypeFilter').value
                                );
                                p.set(
                                    'status',
                                    document.getElementById('statusFilter').value
                                );

                                if (data.order && data.order[0]) {
                                    p.set('order[0][column]', data.order[0].column);
                                    p.set('order[0][dir]', data.order[0].dir);
                                }
                                App.api('api/medicines.php?' + p.toString()).then(function(r) {
                                    listActions = (r.data.list_actions || []).map(Number);
                                    formActions = (r.data.form_actions || []).map(Number);
                                    setSummary(r.data.summary);

                                    if(
                                        !medicineTypesLoaded &&
                                        r.data.filter_options &&
                                        Array.isArray(r.data.filter_options.medicine_types)
                                    ){
                                        var currentType =
                                            document.getElementById('medicineTypeFilter').value;

                                        var items =
                                            r.data.filter_options.medicine_types.map(function(type){
                                                return {
                                                    value:type,
                                                    text:type
                                                };
                                            });

                                        if(
                                            medicineTypeGlobal &&
                                            typeof medicineTypeGlobal.setOptions === 'function'
                                        ){
                                            medicineTypeGlobal.setOptions(items,currentType);
                                        }else{
                                            var select =
                                                document.getElementById('medicineTypeFilter');

                                            items.forEach(function(item){
                                                var option=document.createElement('option');
                                                option.value=item.value;
                                                option.textContent=item.text;
                                                select.appendChild(option);
                                            });
                                        }

                                        medicineTypesLoaded=true;
                                    }

                                    document.getElementById('addButton').style.display = has(formActions, 2) ? 'inline-flex' : 'none';
                                    AppDataTable.applyExportPermissions(table, listActions);
                                    callback(r.data.datatable);
                                }).catch(function(error) {
                                    setSummary({});
                                    App.showError(error, 'Unable to load Medicine Master.');
                                    callback({
                                        draw: data.draw,
                                        recordsTotal: 0,
                                        recordsFiltered: 0,
                                        data: []
                                    });
                                });
                            },
                            columns: [{
                                data: 'medicine_code',
                                defaultContent: '-'
                            }, {
                                data: 'medicine_name',
                                defaultContent: '-'
                            }, {
                                data: 'medicine_type',
                                defaultContent: '-'
                            }, {
                                data: 'selling_price',
                                defaultContent: '0.00',
                                className: 'text-end',
                                render: function(v) {
                                    return '₹ ' + (v || '0.00');
                                }
                            }, {
                                data: 'status_label',
                                defaultContent: '-'
                            }, {
                                data: 'created_at',
                                defaultContent: '-'
                            }, {
                                data: null,
                                orderable: false,
                                searchable: false,
                                className: 'table-action-icons',
                                render: function(data, type, row) {
                                    if (type !== 'display') return '';
                                    var a = [];
                                    if (has(formActions, 1)) a.push(App.iconActionHtml({
                                        href: row.view_url,
                                        icon: 'eye',
                                        label: 'View'
                                    }));
                                    if (has(formActions, 3)) a.push(App.iconActionHtml({
                                        href: row.edit_url,
                                        icon: 'pencil',
                                        label: 'Edit'
                                    }));
                                    if (has(formActions, 4) && Number(row.status) === 1) a.push('<button type="button" class="table-icon-action deactivate-record" data-ref="' + esc(row.ref) + '" title="Deactivate"><i data-lucide="trash-2"></i></button>');
                                    return a.join(' ');
                                }
                            }],
                            drawCallback: function() {
                                removeDefaultSearch();
                                if (window.lucide) window.lucide.createIcons();
                            }
                        });

                        removeDefaultSearch();

                        if(typeof MutationObserver!=='undefined'){
                            var medicineTableElement =
                                document.getElementById('masterTable');

                            var medicineCard =
                                medicineTableElement
                                    ?medicineTableElement.closest('.table-card')
                                    :null;

                            if(medicineCard){
                                new MutationObserver(function(){
                                    removeDefaultSearch();
                                }).observe(
                                    medicineCard,
                                    {childList:true,subtree:true}
                                );
                            }
                        }

                        [
                            'medicineTypeFilter',
                            'statusFilter'
                        ].forEach(function(id){
                            document.getElementById(id)
                                .addEventListener('change',function(){
                                    table.ajax.reload(null,true);
                                });
                        });

                        document.getElementById('masterSearch').addEventListener('input', function() {
                            clearTimeout(timer);
                            timer = setTimeout(function() {
                                table.search(document.getElementById('masterSearch').value.trim()).draw();
                            }, 300);
                        });
                        document.getElementById('masterTable').addEventListener('click', function(event) {
                            var b = event.target.closest('.deactivate-record');
                            if (!b) return;
                            if (!confirm('Deactivate this record?')) return;
                            b.disabled = true;
                            App.api('api/medicines.php', {
                                method: 'POST',
                                body: {
                                    action: 'delete',
                                    ref: b.dataset.ref
                                }
                            }).then(function(r) {
                                showToast(r.message || 'Record deactivated successfully.', {
                                    type: 'success',
                                    duration: 2
                                });
                                table.ajax.reload(null, false);
                            }).catch(function(error) {
                                b.disabled = false;
                                App.showError(error, 'Unable to deactivate record.');
                            });
                        });
                    })(jQuery, window, document);
                </script>
            </section><?php require __DIR__ . '/include/footer.php'; ?></main>
    </div>
    <script src="assets/js/appearance.js"></script>
    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>

</html>