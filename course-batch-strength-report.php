<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Course / Batch Strength Report';

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
        <h1>Course / Batch Strength Report</h1>
        <p>Course-wise and Batch-wise Student strength overview.</p>
    </div>
</div>
<div class="kpi-grid">
    <div class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="users"></i></span>
        <div>
            <div class="kpi-label">Courses</div>
            <div class="kpi-value" id="sumCourses">0</div>
            <div class="kpi-meta">Filtered courses</div>
        </div>
    </div>
    <div class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="graduation-cap"></i></span>
        <div>
            <div class="kpi-label">Batches</div>
            <div class="kpi-value" id="sumBatches">0</div>
            <div class="kpi-meta">Filtered batches</div>
        </div>
    </div>
    <div class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="badge-indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Active Students</div>
            <div class="kpi-value" id="sumActive">0</div>
            <div class="kpi-meta">Active admissions</div>
        </div>
    </div>
    <div class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Total Students</div>
            <div class="kpi-value" id="sumTotal">0</div>
            <div class="kpi-meta">All admissions</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        
<div class="form-row">
    <div class="field col-4"><label for="reportSearch">Search</label><input id="reportSearch" type="text" placeholder="Course or Batch..."></div>
    <div class="field col-4"><label for="courseFilter">Course</label><select id="courseFilter"><option value="">All Courses</option></select></div>
    <div class="field col-4"><label for="batchFilter">Batch</label><select id="batchFilter"><option value="">All Batches</option></select></div>
</div>

    </div>
    <div class="table-scroll">
        <table id="reportTable" class="display data-table">
            <thead><tr><th>Course</th><th>Batch</th><th>Start Date</th><th>End Date</th><th>Total Students</th><th>Active Students</th><th>Completed Students</th><th>Status</th></tr></thead>
        </table>
    </div>
</div>

<script>
(function($){
    'use strict';
    if(!window.AppDataTable||!AppDataTable.ensureAvailable()) return;

    var actions = [];
    var courses = [];
    var batches = [];
    var subjects = [];
    var optionsLoaded = false;
    var searchTimer = null;
    var courseSelect = null;
    var batchSelect = null;

    function el(id){ return document.getElementById(id); }
    function money(v){ return Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function esc(v){ return $('<div>').text(v==null?'':String(v)).html(); }
    function formatDate(v){
        if(!v) return '-';
        var p=String(v).split('-');
        return p.length===3 ? p[2]+'/'+p[1]+'/'+p[0] : esc(v);
    }
    function showError(error,fallback){
        var message=error&&error.message?String(error.message):(error&&error.data&&error.data.message?String(error.data.message):fallback);
        showToast(message,{type:'danger',duration:4});
    }
    function populateCourses(){
        var s=el('courseFilter'), cur=s.value;
        s.innerHTML='<option value="">All Courses</option>';
        courses.forEach(function(r){
            var o=document.createElement('option');
            o.value=String(r.id);
            o.textContent=r.course_code+' - '+r.course_name;
            s.appendChild(o);
        });
        s.value=cur||'';
        if(courseSelect&&typeof courseSelect.sync==='function') courseSelect.sync();
    }
    function populateBatches(){
        var s=el('batchFilter'), cur=s.value, courseId=Number(el('courseFilter').value||0);
        s.innerHTML='<option value="">All Batches</option>';
        batches.filter(function(r){return courseId===0||Number(r.course_id)===courseId;}).forEach(function(r){
            var o=document.createElement('option');
            o.value=String(r.id);
            o.textContent=r.batch_code+' - '+r.batch_name;
            s.appendChild(o);
        });
        if(cur&&s.querySelector('option[value="'+cur+'"]')) s.value=cur; else s.value='';
        if(batchSelect&&typeof batchSelect.sync==='function') batchSelect.sync();
    }
    function loadOptions(options){
        if(optionsLoaded) return;
        courses=options.courses||[];
        batches=options.batches||[];
        subjects=options.subjects||[];
        populateCourses();
        populateBatches();
        optionsLoaded=true;
    }

    function updateSummary(s){
        s=s||{};

        el('sumCourses').textContent=String(s.courses||0);
        el('sumBatches').textContent=String(s.batches||0);
        el('sumActive').textContent=String(s.active_students||0);
        el('sumTotal').textContent=String(s.total_students||0);

    }

    var table=AppDataTable.init('#reportTable',{
        serverSide:true,
        searching:true,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,'desc']],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:'copyHtml5',text:'Copy',title:"Course / Batch Strength Report",action:AppDataTable.serverSideExportAction,exportOptions:{columns:':visible'}},
            {extend:'csvHtml5',text:'CSV',title:"Course / Batch Strength Report",action:AppDataTable.serverSideExportAction,exportOptions:{columns:':visible'}},
            {extend:'excelHtml5',text:'Excel',title:"Course / Batch Strength Report",action:AppDataTable.serverSideExportAction,exportOptions:{columns:':visible'}},
            {extend:'pdfHtml5',text:'PDF',title:"Course / Batch Strength Report",orientation:'landscape',pageSize:'A3',action:AppDataTable.serverSideExportAction,exportOptions:{columns:':visible'}},
            {extend:'print',text:'Print',title:"Course / Batch Strength Report",action:AppDataTable.serverSideExportAction,exportOptions:{columns:':visible'}}
        ],
        ajax:function(data,callback){
            var p=new URLSearchParams();
            p.set('report',"batch_strength");
            p.set('datatable','1');
            p.set('draw',data.draw);
            p.set('start',data.start);
            p.set('length',data.length);
            p.set('search[value]',data.search.value||'');
            ['course_id','batch_id','attendance_type','subject_id','attendance_code','payment_status','admission_state','date_from','date_to'].forEach(function(k){
                var id={
                    course_id:'courseFilter',
                    batch_id:'batchFilter',
                    attendance_type:'attendanceTypeFilter',
                    subject_id:'subjectFilter',
                    attendance_code:'attendanceStatusFilter',
                    payment_status:'paymentStatusFilter',
                    admission_state:'admissionStateFilter',
                    date_from:'dateFrom',
                    date_to:'dateTo'
                }[k];
                if(id&&el(id)) p.set(k,el(id).value||'');
            });
            if(data.order&&data.order[0]){
                p.set('order[0][column]',data.order[0].column);
                p.set('order[0][dir]',data.order[0].dir);
            }
            App.api('api/college-reports.php?'+p.toString())
                .then(function(r){
                    actions=(r.data.allowed_actions||[]).map(Number);
                    loadOptions(r.data.options||{});
                    updateSummary(r.data.summary||{});
                    AppDataTable.applyExportPermissions(table,actions);
                    callback(r.data.datatable);
                })
                .catch(function(e){
                    updateSummary({});
                    showError(e,'Unable to load report.');
                    callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                });
        },
        columns:[

            {data:'course_label'},
            {data:'batch_label'},
            {data:'start_date',render:function(v,t){return t==='display'?formatDate(v):v;}},
            {data:'end_date',render:function(v,t){return t==='display'?formatDate(v):v;}},
            {data:'total_students',className:'dt-body-right'},
            {data:'active_students',className:'dt-body-right'},
            {data:'completed_students',className:'dt-body-right'},
            {data:'status',render:function(v,t){return t==='display'?(Number(v)===1?'Active':'Inactive'):Number(v);}}

        ],
        drawCallback:function(){ if(window.lucide) window.lucide.createIcons(); }
    });

    var card=el('reportTable')?el('reportTable').closest('.table-card'):null;
    var dup=card?card.querySelector('.app-table-search-row'):null;
    if(dup) dup.remove();

    if(el('reportSearch')) el('reportSearch').addEventListener('input',function(){
        var input=this;
        clearTimeout(searchTimer);
        searchTimer=setTimeout(function(){table.search(input.value.trim()).draw();},300);
    });

    if(el('courseFilter')) el('courseFilter').addEventListener('change',function(){populateBatches();table.ajax.reload();});
    ['batchFilter','attendanceTypeFilter','subjectFilter','attendanceStatusFilter','paymentStatusFilter','admissionStateFilter','dateFrom','dateTo'].forEach(function(id){
        if(el(id)) el(id).addEventListener('change',function(){table.ajax.reload();});
    });

    courseSelect=el('courseFilter')?GlobalSelect.init('#courseFilter',{placeholder:'All Courses'}):null;
    batchSelect=el('batchFilter')?GlobalSelect.init('#batchFilter',{placeholder:'All Batches'}):null;
    if(el('paymentStatusFilter')) GlobalSelect.init('#paymentStatusFilter',{placeholder:'All Status'});
    if(el('admissionStateFilter')) GlobalSelect.init('#admissionStateFilter',{placeholder:'All States'});
    if(el('attendanceTypeFilter')) GlobalSelect.init('#attendanceTypeFilter',{placeholder:'All Types'});
    if(el('attendanceStatusFilter')) GlobalSelect.init('#attendanceStatusFilter',{placeholder:'All Attendance'});

    if(window.lucide) window.lucide.createIcons();
})(jQuery);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
