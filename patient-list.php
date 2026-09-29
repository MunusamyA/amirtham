<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Patient Master';

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
        <h1>Patient Master</h1>
        <p>Create and manage Clinic Patients.</p>
    </div>

    <a class="btn btn-primary" id="addPatientButton" href="patient-form.php">
        <i data-lucide="plus"></i>
        Add Patient
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="users"></i></span>
        <div>
            <div class="kpi-label">Total Patients</div>
            <div class="kpi-value" id="kpiPatientTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active</div>
            <div class="kpi-value" id="kpiPatientActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive</div>
            <div class="kpi-value" id="kpiPatientInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="calendar-clock"></i></span>
        <div>
            <div class="kpi-label">Average Age</div>
            <div class="kpi-value" id="kpiPatientAverageAge">0</div>
        </div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-6">
                <label for="patientSearch">Search</label>
                <input id="patientSearch" type="text" placeholder="Search code, patient, mobile, email...">
            </div>

            <div class="field col-3">
                <label for="genderFilter">Gender</label>
                <select id="genderFilter">
                    <option value="">All Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
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
        <table id="patientTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Patient Code</th>
                <th>Patient Name</th>
                <th>DOB / Age</th>
                <th>Gender</th>
                <th>Mobile</th>
                <th>Blood Group</th>
                <th>Status</th>
                <th>Created On</th>
                <th>Manage</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($){
    'use strict';

    if(!window.AppDataTable||!AppDataTable.ensureAvailable()) return;

    var listActions=[],formActions=[],searchTimer=null;
    var has=AppDataTable.has;

    function esc(v){return $('<div>').text(v==null?'':String(v)).html();}

    function setSummary(summary){
        summary=summary||{};

        document.getElementById('kpiPatientTotal').textContent=
            Number(summary.total_count||0).toLocaleString('en-IN');

        document.getElementById('kpiPatientActive').textContent=
            Number(summary.active_count||0).toLocaleString('en-IN');

        document.getElementById('kpiPatientInactive').textContent=
            Number(summary.inactive_count||0).toLocaleString('en-IN');

        var averageAge=Number(summary.average_age||0);
        document.getElementById('kpiPatientAverageAge').textContent=
            Number.isFinite(averageAge)
                ? averageAge.toLocaleString('en-IN',{maximumFractionDigits:1})
                : '0';
    }

    function removeDefaultSearch(){
        var tableEl=document.getElementById('patientTable');
        var card=tableEl?tableEl.closest('.table-card'):null;
        var wrapper=document.getElementById('patientTable_wrapper');

        [card,wrapper].forEach(function(root){
            if(!root)return;

            root.querySelectorAll(
                '#patientTable_filter,.dataTables_filter,.app-table-search-row'
            ).forEach(function(node){
                node.remove();
            });
        });
    }

    function formatDate(v){
        if(!v) return '-';
        var p=String(v).split('-');
        return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:esc(v);
    }
    function formatDateTime(v){
        if(!v) return '-';
        var d=new Date(String(v).replace(' ','T'));
        return Number.isNaN(d.getTime())?esc(v):d.toLocaleString('en-IN',{
            day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'
        });
    }

    var table=AppDataTable.init('#patientTable',{
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,'asc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:'copyHtml5',text:'Copy',title:'Patient Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:'csvHtml5',text:'CSV',title:'Patient Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:'excelHtml5',text:'Excel',title:'Patient Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:'pdfHtml5',text:'PDF',title:'Patient Master',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:'print',text:'Print',title:'Patient Master',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}}
        ],
        ajax:function(data,callback){
            var p=new URLSearchParams();
            p.set('datatable','1');
            p.set('draw',data.draw);
            p.set('start',data.start);
            p.set('length',data.length);
            p.set('search[value]',data.search.value||'');
            p.set('gender',document.getElementById('genderFilter').value);
            p.set('status',document.getElementById('statusFilter').value);

            if(data.order&&data.order[0]){
                p.set('order[0][column]',data.order[0].column);
                p.set('order[0][dir]',data.order[0].dir);
            }

            App.api('api/patients.php?'+p.toString())
            .then(function(response){
                listActions=(response.data.list_actions||[]).map(Number);
                formActions=(response.data.form_actions||[]).map(Number);
                setSummary(response.data.summary);
                document.getElementById('addPatientButton').style.display=
                    has(formActions,2)?'inline-flex':'none';
                AppDataTable.applyExportPermissions(table,listActions);
                callback(response.data.datatable);
            })
            .catch(function(error){
                setSummary({});
                App.showError(error,'Unable to load Patients.');
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:'patient_code',defaultContent:'-'},
            {data:'patient_name',defaultContent:'-'},
            {data:'date_of_birth',render:function(v,t,row){
                if(t!=='display') return v||'';
                var age=(row.age===null||row.age===undefined)?'':' ('+row.age+' yrs)';
                return formatDate(v)+age;
            }},
            {data:'gender',defaultContent:'-',render:function(v,t){return t==='display'?esc(v||'-'):(v||'');}},
            {data:'mobile',defaultContent:'-'},
            {data:'blood_group',defaultContent:'-',render:function(v,t){return t==='display'?esc(v||'-'):(v||'');}},
            {data:'status_label',render:function(v,t,row){
                if(t!=='display') return Number(row.status||0);
                return Number(row.status)===1
                    ?'<span class="dt-status active">Active</span>'
                    :'<span class="dt-status inactive">Inactive</span>';
            }},
            {data:'created_at',defaultContent:'-',render:function(v,t){return t==='display'?formatDateTime(v):v;}},
            {data:null,orderable:false,searchable:false,className:'table-action-icons',render:function(data,type,row){
                if(type!=='display') return '';
                var actions=[];
                if(has(formActions,1)) actions.push(App.iconActionHtml({href:row.view_url,icon:'eye',label:'View Patient Profile'}));
                if(has(formActions,3)) actions.push(App.iconActionHtml({href:row.edit_url,icon:'pencil',label:'Edit Patient'}));
                if(has(formActions,4)){
                    actions.push('<button type="button" class="table-icon-action delete-patient" data-ref="'+esc(row.ref)+'" title="Delete Patient" aria-label="Delete Patient"><i data-lucide="trash-2"></i></button>');
                }
                return actions.join(' ')||'<span class="muted">View only</span>';
            }}
        ],
        drawCallback:function(){
            removeDefaultSearch();
            if(window.lucide) window.lucide.createIcons();
        }
    });

    removeDefaultSearch();

    if(typeof MutationObserver!=='undefined'){
        var patientTableEl=document.getElementById('patientTable');
        var patientCard=patientTableEl?patientTableEl.closest('.table-card'):null;

        if(patientCard){
            new MutationObserver(function(){
                removeDefaultSearch();
            }).observe(
                patientCard,
                {childList:true,subtree:true}
            );
        }
    }

    var search=document.getElementById('patientSearch');
    search.addEventListener('input',function(){
        clearTimeout(searchTimer);
        searchTimer=setTimeout(function(){table.search(search.value.trim()).draw();},300);
    });

    ['genderFilter','statusFilter'].forEach(function(id){
        document.getElementById(id).addEventListener('change',function(){table.ajax.reload();});
    });

    document.getElementById('patientTable').addEventListener('click',function(event){
        var button=event.target.closest('.delete-patient');
        if(!button) return;

        if(!window.confirm('Delete this Patient? A Patient already used in Appointment, Consultation, Treatment, Billing or other Clinic records cannot be deleted.')){
            return;
        }

        button.disabled=true;

        App.api('api/patients.php',{
            method:'POST',
            body:{action:'delete',ref:button.dataset.ref}
        })
        .then(function(response){
            showToast(response.message||'Patient deleted successfully.',{type:'success',duration:2});
            table.ajax.reload(null,false);
        })
        .catch(function(error){
            button.disabled=false;
            App.showError(error,'Unable to delete Patient.');
        });
    });

    if(window.GlobalSelect){
        GlobalSelect.init(document.getElementById('genderFilter'),{placeholder:'All Gender'});
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
