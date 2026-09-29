<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Financial Reports';
$clientConfig = json_decode('{"scope":"financial","title":"Financial Reports","subtitle":"Daily billing, actual receipts and recorded clinic expense payments.","kpis":[["bills","Bills","number","receipt-text"],["billed","Billed Total","money","badge-indian-rupee"],["discounts","Discounts","money","percent"],["receipts","Receipts","money","hand-coins"],["expense_paid_recorded","Paid Expenses Recorded","money","receipt"],["receipts_less_expense_paid_recorded","Receipt / Expense Difference","money","scale"]],"columns":[["report_date","Date","date"],["bills","Bills","number"],["billed","Billed","money"],["discounts","Discounts","money"],["receipts","Receipts","money"],["expense_paid_recorded","Paid Expenses Recorded","money"],["receipts_less_expense_paid_recorded","Difference","money"]],"filters":[],"filterLabels":{"patient_id":"Patient","doctor":"Practitioner","payment_mode":"Payment Mode","status":"Status"}}', true, 512, JSON_THROW_ON_ERROR);
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
<?php foreach ($headStyles as $url): ?><link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach ($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
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
<div class="page-head"><div><h1><?php echo web_h($pageTitle); ?></h1><p><?php echo web_h($clientConfig['subtitle']); ?></p></div></div>
<div class="kpi-grid">
<?php $kpiColors = ['blue', 'orange', 'teal', 'green', 'orange']; foreach($clientConfig['kpis'] as $index => $m): ?>
<article class="card kpi-card"><span class="kpi-icon <?php echo web_h($kpiColors[$index % count($kpiColors)]); ?>"><i data-lucide="<?php echo web_h($m[3]); ?>"></i></span><div>
<div class="kpi-label"><?php echo web_h($m[1]); ?></div>
<div class="kpi-value" id="kpi_<?php echo web_h($m[0]); ?>">—</div>
</div></article>
<?php endforeach; ?>
</div>
<div class="card table-card">
<div class="card-header"><div class="form-row">
<div class="field col-3"><label for="reportSearch">Search</label><input type="text" id="reportSearch" placeholder="Search report..."></div>
<div class="field col-3"><label for="dateFrom">From Date</label><input type="date" id="dateFrom"></div>
<div class="field col-3"><label for="dateTo">To Date</label><input type="date" id="dateTo"></div>
<?php foreach($clientConfig['filters'] as $filter): ?>
<div class="field col-3"><label for="filter_<?php echo web_h($filter); ?>"><?php echo web_h($clientConfig['filterLabels'][$filter]); ?></label>
<select id="filter_<?php echo web_h($filter); ?>" data-report-filter="<?php echo web_h($filter); ?>"><option value="">All</option></select></div>
<?php endforeach; ?>
</div></div>
<div class="table-scroll"><table id="reportTable" class="display data-table"><thead><tr>
<?php foreach($clientConfig['columns'] as $c): ?><th><?php echo web_h($c[1]); ?></th><?php endforeach; ?>
</tr></thead></table></div>
</div>
<p class="muted" id="resultMeta">Loading report…</p>
<script>var CLINIC_REPORT_CONFIG = <?php echo json_encode($clientConfig,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE); ?>;</script>
<script>
(function($,window,document){
'use strict';
var C=CLINIC_REPORT_CONFIG,table=null,allowedActions=[],searchTimer=null;
function el(id){return document.getElementById(id);}
function esc(v){return $('<div>').text(v==null?'':String(v)).html();}
function money(v){var n=Number(v||0);return (n<0?'− ':'')+'₹'+Math.abs(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(v){if(!v)return '—';var x=String(v).slice(0,10).split('-');return x.length===3?x[2]+'-'+x[1]+'-'+x[0]:String(v);}
function render(v,type,mode){if(mode!=='display')return v==null?'':v;
 if(v===null||v===undefined||v==='')return '—';
 if(type==='money')return money(v);if(type==='number')return Number(v||0).toLocaleString('en-IN',{maximumFractionDigits:3});
 if(type==='date')return fmtDate(v);if(type==='datetime')return fmtDate(v)+' '+esc(String(v).slice(11,19));
 if(type==='badge')return '<span class="badge">'+esc(v)+'</span>';return esc(v);
}
function renderKpis(s){C.kpis.forEach(function(k){var e=el('kpi_'+k[0]);if(!e)return;e.textContent=Object.prototype.hasOwnProperty.call(s||{},k[0])?(k[2]==='money'?money(s[k[0]]):Number(s[k[0]]||0).toLocaleString('en-IN',{maximumFractionDigits:3})):'—';});}
function removeDefaultSearch(){
 var tableElement=el('reportTable');
 var card=tableElement?tableElement.closest('.table-card'):null;
 var wrapper=el('reportTable_wrapper');
 [card,wrapper].forEach(function(root){
  if(!root)return;
  root.querySelectorAll('#reportTable_filter,.dataTables_filter,.dt-search,.app-table-search-row').forEach(function(node){node.remove();});
 });
}
function watchDefaultSearch(){
 var tableElement=el('reportTable');
 var card=tableElement?tableElement.closest('.table-card'):null;
 if(!card||typeof MutationObserver==='undefined')return;
 new MutationObserver(function(){removeDefaultSearch();}).observe(card,{childList:true,subtree:true});
}
function initTable(){if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;
 table=AppDataTable.init('#reportTable',{serverSide:false,processing:false,searching:true,appSearch:false,dom:'Brtip',pageLength:10,lengthMenu:[[10,25,50,100],[10,25,50,100]],scrollX:true,autoWidth:false,order:[],data:[],buttons:[
 {extend:'copyHtml5',text:'Copy',title:C.title}, {extend:'csvHtml5',text:'CSV',title:C.title},
 {extend:'excelHtml5',text:'Excel',title:C.title}, {extend:'pdfHtml5',text:'PDF',title:C.title,orientation:'landscape',pageSize:'A4'},
 {extend:'print',text:'Print',title:C.title}],
 columns:C.columns.map(function(col){return {data:col[0],defaultContent:'',className:(col[2]==='money'||col[2]==='number')?'dt-body-right':'',render:function(v,m){return render(v,col[2],m);}};}),
 drawCallback:function(){removeDefaultSearch();if(window.lucide)window.lucide.createIcons();}
 });removeDefaultSearch();watchDefaultSearch();
}
function params(){var q=new URLSearchParams();q.set('scope',C.scope);q.set('date_from',el('dateFrom').value);q.set('date_to',el('dateTo').value);
 C.filters.forEach(function(k){q.set(k,el('filter_'+k).value);});return q;}
function opts(data){var sources={patient_id:'patients',doctor:'doctors',payment_mode:'payment_modes',status:C.scope==='appointments'?'appointment_states':'consultation_states'};
 C.filters.forEach(function(k){var n=el('filter_'+k);var previous=n.value;var records=data[sources[k]]||[];n.innerHTML='<option value="">All</option>';
 records.forEach(function(x){var o=document.createElement('option');o.value=k==='patient_id'?x.id:x.id;o.textContent=k==='patient_id'?(x.patient_code+' - '+x.patient_name):(x.label||x.id);n.appendChild(o);});n.value=previous;
 if(window.GlobalSelect){try{GlobalSelect.init(n,{placeholder:'All'});}catch(ignore){}}
 });
}
async function load(){if(!table)return;var f=el('dateFrom').value,t=el('dateTo').value;if(f&&t&&f>t){el('resultMeta').textContent='From Date must not be after To Date.';return;}
 try{var r=await App.api('api/clinic-reports.php?'+params().toString());var d=r.data||{},rows=d.rows||[];allowedActions=(d.allowed_actions||[]).map(Number);
 table.clear().rows.add(rows).draw();renderKpis(d.summary||{});AppDataTable.applyExportPermissions(table,allowedActions);
 el('resultMeta').textContent=rows.length.toLocaleString('en-IN')+' detailed row(s) · '+(d.date_from||'')+' to '+(d.date_to||'')+(d.note?' · '+d.note:'');
 }catch(e){table.clear().draw();renderKpis({});el('resultMeta').textContent='Report could not be loaded.';App.showError(e,'Unable to load clinic report.');}
}
async function boot(){initTable();if(!table)return;try{var r=await App.api('api/clinic-reports.php?scope='+encodeURIComponent(C.scope)+'&options=1');var d=r.data||{};
 allowedActions=(d.allowed_actions||[]).map(Number);el('dateFrom').value=d.date_from||'';el('dateTo').value=d.date_to||'';opts(d);
 AppDataTable.applyExportPermissions(table,allowedActions);await load();
 }catch(e){el('resultMeta').textContent='Report access or options could not be loaded.';App.showError(e,'Unable to load clinic report options.');}}
 ['dateFrom','dateTo'].concat(C.filters.map(function(k){return 'filter_'+k;})).forEach(function(k){el(k).addEventListener('change',load);});
 el('reportSearch').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(el('reportSearch').value.trim()).draw();},250);});
 boot();
})(jQuery,window,document);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main></div>
<script src="assets/js/appearance.js"></script>
<script>if(window.lucide)window.lucide.createIcons();</script>
</body></html>
