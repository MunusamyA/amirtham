<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Course Subject Master';
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
                    <h1>Course Subject / Module Master</h1>
                    <p>Manage multiple subjects/modules under each Community College Course.</p>
                </div>
                <a class="btn btn-primary" id="addSubjectButton" href="course-list.php">
                    <i data-lucide="plus"></i>
                    Manage Courses
                </a>
            </div>

            <div class="kpi-grid">
                <article class="card kpi-card">
                    <span class="kpi-icon blue"><i data-lucide="library-big"></i></span>
                    <div><div class="kpi-label">Total Subjects</div><div class="kpi-value" id="kpiSubjectTotal">0</div></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
                    <div><div class="kpi-label">Active</div><div class="kpi-value" id="kpiSubjectActive">0</div></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
                    <div><div class="kpi-label">Inactive</div><div class="kpi-value" id="kpiSubjectInactive">0</div></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon teal"><i data-lucide="badge-check"></i></span>
                    <div><div class="kpi-label">Mandatory</div><div class="kpi-value" id="kpiSubjectMandatory">0</div></div>
                </article>
            </div>


            <div class="card table-card">
                <div class="card-header">
                    <div class="form-row" style="width:100%;margin:0;">
                        <div class="field col-5">
                            <label for="subjectSearch">Search</label>
                            <input id="subjectSearch" type="text"
                                   placeholder="Search subject code, subject name, course...">
                        </div>

                        <div class="field col-3">
                            <label for="courseFilter">Course</label>
                            <select id="courseFilter">
                                <option value="">All Courses</option>
                            </select>
                        </div>

                        <div class="field col-2">
                            <label for="typeFilter">Subject Type</label>
                            <select id="typeFilter">
                                <option value="">All Types</option>
                                <option value="1">Theory</option>
                                <option value="2">Practical</option>
                                <option value="3">Theory + Practical</option>
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
                    <table id="subjectTable" class="display data-table" style="width:100%">
                        <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Course</th>
                            <th>Subject / Module</th>
                            <th>Type</th>
                            <th>Max Marks</th>
                            <th>Pass Marks</th>
                            <th>Mandatory</th>
                            <th>Sort</th>
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

                if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

                var listActions = [];
                var formActions = [];
                var searchTimer = null;
                var has = AppDataTable.has;
                var filtersReady = false;
                var courseFilterSelect = null;

                function esc(value) {
                    return $('<div>').text(value == null ? '' : String(value)).html();
                }

                function setSummary(summary) {
                    summary = summary || {};
                    document.getElementById('kpiSubjectTotal').textContent =
                        Number(summary.total_count || 0).toLocaleString('en-IN');
                    document.getElementById('kpiSubjectActive').textContent =
                        Number(summary.active_count || 0).toLocaleString('en-IN');
                    document.getElementById('kpiSubjectInactive').textContent =
                        Number(summary.inactive_count || 0).toLocaleString('en-IN');
                    document.getElementById('kpiSubjectMandatory').textContent =
                        Number(summary.mandatory_count || 0).toLocaleString('en-IN');
                }

                function removeDefaultSearch() {
                    var tableElement = document.getElementById('subjectTable');
                    var card = tableElement ? tableElement.closest('.table-card') : null;
                    var wrapper = document.getElementById('subjectTable_wrapper');

                    [card,wrapper].forEach(function(root){
                        if(!root)return;
                        root.querySelectorAll(
                            '#subjectTable_filter,.dataTables_filter,.app-table-search-row'
                        ).forEach(function(node){node.remove();});
                    });
                }

                function formatDateTime(value) {
                    if (!value) return '-';
                    var date = new Date(String(value).replace(' ', 'T'));
                    if (Number.isNaN(date.getTime())) return esc(value);
                    return date.toLocaleString('en-IN', {
                        day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'
                    });
                }

                function formatNumber(value) {
                    if (value === null || value === undefined || value === '') return '-';
                    var n = Number(value);
                    if (!isFinite(n)) return esc(value);
                    return n.toLocaleString('en-IN',{maximumFractionDigits:2});
                }

                function populateCourseFilter(rows) {
                    var select = document.getElementById('courseFilter');
                    var current = select.value;
                    select.innerHTML = '<option value="">All Courses</option>';

                    (rows || []).forEach(function(row){
                        var option = document.createElement('option');
                        option.value = String(row.id);
                        option.textContent = row.course_code + ' - ' + row.course_name;
                        select.appendChild(option);
                    });

                    if (current) select.value = current;
                    if (courseFilterSelect && typeof courseFilterSelect.sync === 'function') {
                        courseFilterSelect.sync();
                    }
                }

                var table = AppDataTable.init('#subjectTable',{
                    serverSide:true,
                    searching:true,
                    appSearch:false,
                    pageLength:10,
                    lengthMenu:[[10,25,50,100],[10,25,50,100]],
                    order:[[1,'asc'],[7,'asc'],[2,'asc']],
                    scrollX:true,
                    autoWidth:false,
                    buttons:[
                        {extend:'copyHtml5',text:'Copy',title:'Course Subject Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
                        {extend:'csvHtml5',text:'CSV',title:'Course Subject Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
                        {extend:'excelHtml5',text:'Excel',title:'Course Subject Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
                        {extend:'pdfHtml5',text:'PDF',title:'Course Subject Master',orientation:'landscape',pageSize:'A3',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
                        {extend:'print',text:'Print',title:'Course Subject Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}
                    ],
                    ajax:function(data,callback){
                        var params = new URLSearchParams();
                        params.set('datatable','1');
                        params.set('resource','subjects');
                        params.set('draw',data.draw);
                        params.set('start',data.start);
                        params.set('length',data.length);
                        params.set('search[value]',data.search.value || '');
                        params.set('course_id',document.getElementById('courseFilter').value);
                        params.set('subject_type',document.getElementById('typeFilter').value);
                        params.set('status',document.getElementById('statusFilter').value);

                        if (data.order && data.order[0]) {
                            params.set('order[0][column]',data.order[0].column);
                            params.set('order[0][dir]',data.order[0].dir);
                        }

                        App.api('api/courses.php?' + params.toString())
                            .then(function(response){
                                listActions = (response.data.list_actions || []).map(Number);
                                formActions = (response.data.form_actions || []).map(Number);
                                setSummary(response.data.summary);

                                document.getElementById('addSubjectButton').style.display =
                                    has(formActions,2) ? 'inline-flex' : 'none';

                                if (!filtersReady) {
                                    populateCourseFilter(response.data.courses || []);
                                    filtersReady = true;
                                }

                                AppDataTable.applyExportPermissions(table,listActions);
                                callback(response.data.datatable);
                            })
                            .catch(function(error){
                                setSummary({});
                                App.showError(error,'Unable to load Course Subjects.');
                                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                            });
                    },
                    columns:[
                        {data:'subject_code',defaultContent:'-'},
                        {data:'course_label',defaultContent:'-'},
                        {data:'subject_name',defaultContent:'-'},
                        {data:'subject_type_label',defaultContent:'-'},
                        {data:'maximum_marks',className:'dt-body-right',render:function(v,t){return t==='display'?formatNumber(v):v;}},
                        {data:'pass_marks',className:'dt-body-right',render:function(v,t){return t==='display'?formatNumber(v):v;}},
                        {data:'mandatory_label',render:function(v,t,row){
                            if (t !== 'display') return Number(row.mandatory || 0);
                            return Number(row.mandatory) === 1
                                ? '<span class="dt-status active">Yes</span>'
                                : '<span class="dt-status inactive">No</span>';
                        }},
                        {data:'sort_order',className:'dt-body-right',render:function(v,t){return t==='display'?formatNumber(v):Number(v||0);}},
                        {data:'status_label',render:function(v,t,row){
                            if (t !== 'display') return Number(row.status || 0);
                            return Number(row.status) === 1
                                ? '<span class="dt-status active">Active</span>'
                                : '<span class="dt-status inactive">Inactive</span>';
                        }},
                        {data:'created_at',defaultContent:'-',render:function(v,t){return t==='display'?formatDateTime(v):v;}},
                        {data:null,orderable:false,searchable:false,className:'table-action-icons',render:function(data,type,row){
                            if (type !== 'display') return '';
                            var actions = [];

                            if (has(formActions,1)) {
                                actions.push(App.iconActionHtml({href:row.view_url,icon:'eye',label:'View Subject'}));
                            }
                            if (has(formActions,3)) {
                                actions.push(App.iconActionHtml({href:row.edit_url,icon:'pencil',label:'Edit Subject'}));
                            }
                            if (has(formActions,4)) {
                                actions.push(
                                    '<button type="button" class="table-icon-action delete-subject" ' +
                                    'data-ref="' + esc(row.ref) + '" title="Delete Subject" aria-label="Delete Subject">' +
                                    '<i data-lucide="trash-2"></i></button>'
                                );
                            }
                            return actions.join(' ') || '<span class="muted">View only</span>';
                        }}
                    ],
                    drawCallback:function(){
                        removeDefaultSearch();
                        if (window.lucide) window.lucide.createIcons();
                    }
                });

                removeDefaultSearch();

                if (typeof MutationObserver !== 'undefined') {
                    var subjectTableElement = document.getElementById('subjectTable');
                    var subjectCard = subjectTableElement ? subjectTableElement.closest('.table-card') : null;

                    if (subjectCard) {
                        new MutationObserver(function(){
                            removeDefaultSearch();
                        }).observe(subjectCard,{childList:true,subtree:true});
                    }
                }

                document.getElementById('subjectSearch').addEventListener('input',function(){
                    var input = this;
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function(){table.search(input.value.trim()).draw();},300);
                });

                ['courseFilter','typeFilter','statusFilter'].forEach(function(id){
                    document.getElementById(id).addEventListener('change',function(){
                        if (filtersReady) table.ajax.reload();
                    });
                });

                document.getElementById('subjectTable').addEventListener('click',function(event){
                    var button = event.target.closest('.delete-subject');
                    if (!button) return;

                    if (!window.confirm('Delete this Subject / Module? If it is already used, deletion will be blocked.')) return;
                    button.disabled = true;

                    App.api('api/courses.php',{
                        method:'POST',
                        body:{action:'delete_subject',ref:button.dataset.ref}
                    }).then(function(response){
                        showToast(response.message || 'Course Subject deleted successfully.',{type:'success',duration:2});
                        table.ajax.reload(null,false);
                    }).catch(function(error){
                        button.disabled = false;
                        App.showError(error,'Unable to delete Course Subject.');
                    });
                });

                if (window.GlobalSelect) {
                    courseFilterSelect = GlobalSelect.init(document.getElementById('courseFilter'),{placeholder:'All Courses'});
                    GlobalSelect.init(document.getElementById('typeFilter'),{placeholder:'All Subject Types'});
                    GlobalSelect.init(document.getElementById('statusFilter'),{placeholder:'All Status'});
                }
            })(jQuery);
            </script>
        </section>

        <?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
        