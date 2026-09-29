<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Dashboard';
$headScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js',
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
<script src="assets/js/global-select.js"></script>

<div class="page-head">
  <div><h1>Dashboard Overview</h1><p>Live activity, collections and quick access across your authorised modules.</p></div>
  <div><span class="badge" id="branchLabel">Current Branch</span></div>
</div>

<div class="card table-card">
  <div class="card-header">
    <div class="form-row">
      <div class="field col-3"><label for="periodFilter">Reporting Period</label>
        <select id="periodFilter"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="week">Last 7 Days</option><option value="month" selected>This Month</option><option value="last30">Last 30 Days</option><option value="last90">Last 90 Days</option><option value="year">This Financial Year</option><option value="custom">Custom Date</option></select></div>
      <div class="field col-3"><label for="branchFilter">Branch</label><select id="branchFilter"><option value="current">My Assigned Branch</option></select></div>
      <div class="field col-2"><label for="dateFrom">From Date</label><input type="date" id="dateFrom" value="<?php echo web_h(date('Y-m-01')); ?>"></div>
      <div class="field col-2"><label for="dateTo">To Date</label><input type="date" id="dateTo" value="<?php echo web_h(date('Y-m-d')); ?>"></div>
      <div class="field col-2"><label>&nbsp;</label><button class="btn btn-primary" type="button" id="applyFilters"><i data-lucide="refresh-cw"></i> Apply</button></div>
    </div>
  </div>
</div>

<div id="dashboardLoading" class="muted" role="status">Loading dashboard…</div>
<div class="kpi-grid" id="dashboardKpis"></div>

<div class="card table-card">
  <div class="card-header"><div><h3>Quick Actions</h3><p class="muted">Only pages you have permission to view are shown.</p></div></div>
  <div class="kpi-grid" id="quickActions"></div>
</div>

<div class="form-row">
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Collection Trends</h3></div><canvas id="collectionChart" height="220" role="img" aria-label="Collection trends"></canvas><p class="muted" id="collectionChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Billing vs Collections</h3></div><canvas id="billingChart" height="220" role="img" aria-label="Billed and collected amounts"></canvas><p class="muted" id="billingChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Payment Mode Distribution</h3></div><canvas id="paymentChart" height="220" role="img" aria-label="Payment mode distribution"></canvas><p class="muted" id="paymentChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Collection Contribution</h3></div><canvas id="contributionChart" height="220" role="img" aria-label="Sources of collection"></canvas><p class="muted" id="contributionChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Patient Activity & Admissions</h3></div><canvas id="activityChart" height="220" role="img" aria-label="Patient activity and admissions"></canvas><p class="muted" id="activityChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Collections vs Recorded Outflows</h3></div><canvas id="movementChart" height="220" role="img" aria-label="Collections and recorded outflows"></canvas><p class="muted" id="movementChartNote"></p></article></div>
  <div class="field col-6"><article class="card table-card"><div class="card-header"><h3>Appointment Status</h3></div><canvas id="appointmentsChart" height="220" role="img" aria-label="Appointment status distribution"></canvas><p class="muted" id="appointmentsChartNote"></p></article></div>
</div>

<div class="form-row">
  <div class="field col-6"><div class="card table-card"><div class="card-header"><h3>Pending & Attention Required</h3></div><div class="table-scroll"><table class="display data-table" width="100%"><thead><tr><th>Item</th><th>Count</th><th>Open</th></tr></thead><tbody id="pendingRows"><tr><td colspan="3">Loading…</td></tr></tbody></table></div></div></div>
  <div class="field col-6"><div class="card table-card"><div class="card-header"><h3>Recent Transactions</h3></div><div class="table-scroll"><table class="display data-table" width="100%"><thead><tr><th>Date</th><th>Activity</th><th>Reference</th></tr></thead><tbody id="recentRows"><tr><td colspan="3">Loading…</td></tr></tbody></table></div></div></div>
</div>
<div class="card"><h3>Reporting Notes</h3><div id="reportNotes" class="muted"></div></div>

<script>
(function(window,document){
'use strict';
var graphs={},loading=false;
var colors=['#2676d9','#159a8c','#e29a27','#42a05c','#8c65d3','#cf6a76'];
function el(id){return document.getElementById(id);}
function node(tag, text, className){var n=document.createElement(tag); if(text!==undefined)n.textContent=String(text);if(className)n.className=className;return n;}
function money(value){var v=Number(value||0);return (v<0?'− ':'')+'₹'+Math.abs(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function number(value){return Number(value||0).toLocaleString('en-IN');}
function dateLocal(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
function startOfDay(){var n=new Date();return new Date(n.getFullYear(),n.getMonth(),n.getDate());}
function preset(v){if(v==='custom')return;var end=startOfDay(),from=new Date(end);
 if(v==='yesterday'){end.setDate(end.getDate()-1);from=new Date(end);}
 else if(v==='week')from.setDate(from.getDate()-6);
 else if(v==='month')from.setDate(1);
 else if(v==='last30')from.setDate(from.getDate()-29);
 else if(v==='last90')from.setDate(from.getDate()-89);
 else if(v==='year')from=new Date(end.getFullYear()-(end.getMonth()<3?1:0),3,1);
 el('dateFrom').value=dateLocal(from);el('dateTo').value=dateLocal(end);
}
function renderCards(cards){var root=el('dashboardKpis');root.replaceChildren();if(!cards.length){root.appendChild(node('p','No statistics are permitted for your role.','muted'));return;}
 cards.forEach(function(c,i){var art=node('article',undefined,'card kpi-card');var ico=node('span',undefined,'kpi-icon '+['blue','orange','teal','green'][i%4]);var icon=node('i');icon.dataset.lucide=c.icon||'chart-no-axes-combined';ico.appendChild(icon);art.appendChild(ico);
 var wrap=node('div');wrap.appendChild(node('div',c.label,'kpi-label'));wrap.appendChild(node('div',c.format==='money'?money(c.value):number(c.value),'kpi-value'));art.appendChild(wrap);root.appendChild(art);});lucideIcons();}
function renderActions(actions){var root=el('quickActions');root.replaceChildren();if(!actions.length){root.appendChild(node('p','No quick actions available.','muted'));return;}
 // Reuse the existing KPI icon background classes from the application's theme.
 // Assign by page so permissions do not change each shortcut's icon colour.
 var actionColors={'sales.php':'blue','bill-list.php':'teal','customer-payment-list.php':'green',
 'appointment-list.php':'orange','patient-list.php':'blue','admission-list.php':'teal',
 'purchase-list.php':'orange','expense-list.php':'green','report-overview.php':'blue'};
 var fallbackColors=['blue','orange','teal','green','orange'];
 actions.forEach(function(a,i){var link=node('a',undefined,'card kpi-card');link.href=a.url;
 var color=actionColors[a.url]||fallbackColors[i%fallbackColors.length];
 var span=node('span',undefined,'kpi-icon '+color);var icon=node('i');icon.dataset.lucide=a.icon;
 span.appendChild(icon);link.appendChild(span);link.appendChild(node('span',a.label,'kpi-label'));root.appendChild(link);});lucideIcons();}
function lucideIcons(){if(window.lucide&&window.lucide.createIcons)window.lucide.createIcons();}
function renderRows(id,rows,type){var root=el(id);root.replaceChildren();if(!rows.length){var tr=node('tr'),td=node('td','No records available.');td.colSpan=3;tr.appendChild(td);root.appendChild(tr);return;}
 rows.forEach(function(r){var tr=node('tr');if(type==='pending'){
 tr.appendChild(node('td',r.label));tr.appendChild(node('td',number(r.value)+' '+r.unit));var td=node('td'),a=node('a','View');a.href=r.url;a.className='link';td.appendChild(a);tr.appendChild(td);
 }else{tr.appendChild(node('td',r.date));tr.appendChild(node('td',r.type));var td2=node('td'),a2=node('a',r.reference);a2.href=r.url;a2.className='link';td2.appendChild(a2);tr.appendChild(td2);}root.appendChild(tr);});}
function renderNotes(notes){var root=el('reportNotes');root.replaceChildren();(notes||[]).forEach(function(t){root.appendChild(node('p',t));});}
function chart(id,type,labels,datasets,opts){if(graphs[id]){graphs[id].destroy();delete graphs[id];}
 var note=el(id+'Note');if(!window.Chart){note.textContent='Chart library could not load. Statistics and tables are still available.';return;}
 if(!labels.length || datasets.every(function(s){return (s.data||[]).every(function(v){return !Number(v);});})){note.textContent='No data in the selected period.';return;}
 note.textContent='';
 var axis=!(type==='pie'||type==='doughnut');
 var options={responsive:true,maintainAspectRatio:true,aspectRatio:2,plugins:{legend:{display:true,position:'bottom'},tooltip:{callbacks:{label:function(c){var v=c.parsed; if(typeof v==='object')v=(v.y!==undefined?v.y:0);return c.dataset.label+': '+((opts&&opts.money)?money(v):number(v));}}}}};
 if(axis)options.scales={y:{beginAtZero:true,ticks:{callback:function(v){return (opts&&opts.money)?'₹'+Number(v).toLocaleString('en-IN'):Number(v).toLocaleString('en-IN');}}},x:{ticks:{maxRotation:0,autoSkip:true}}};
 graphs[id]=new window.Chart(el(id).getContext('2d'),{type:type,data:{labels:labels,datasets:datasets},options:options});}
function doughnut(id,data,moneyLabels){chart(id,'doughnut',data.map(function(x){return x.name;}),[{label:moneyLabels?'Collected':'Appointments',data:data.map(function(x){return Number(x.value||0);}),backgroundColor:colors,hoverOffset:5}],{money:moneyLabels});}
function plot(data){var t=data.charts.trend||[],labels=t.map(function(x){return x.period;}),visible=data.visible||{};
 chart('collectionChart','line',labels,[
  {label:'Sales',data:t.map(function(x){return x.food;}),borderColor:colors[0],backgroundColor:colors[0],tension:.3},
  {label:'Clinic',data:t.map(function(x){return x.clinic;}),borderColor:colors[1],backgroundColor:colors[1],tension:.3},
  {label:'College',data:t.map(function(x){return x.college;}),borderColor:colors[2],backgroundColor:colors[2],tension:.3}
 ].filter(function(x){return (x.label==='Sales'&&visible.food_cash)||(x.label==='Clinic'&&visible.clinic_cash)||(x.label==='College'&&visible.college_cash);}),{money:true});
 chart('billingChart','bar',labels,[{label:'Billed (Sales + Clinic)',data:t.map(function(x){return x.billed;}),backgroundColor:colors[0]}, {label:'Receipts',data:t.map(function(x){return x.collections;}),backgroundColor:colors[1]}].filter(function(x){return x.label==='Receipts'?(visible.food_cash||visible.clinic_cash||visible.college_cash):(visible.food||visible.clinic_cash);}),{money:true});
 doughnut('paymentChart',data.charts.payment_modes||[],true);
 doughnut('contributionChart',data.charts.contribution||[],true);
 chart('activityChart','bar',labels,[{label:'New Patients',data:t.map(function(x){return x.patients;}),backgroundColor:colors[0]}, {label:'Consultation Visits',data:t.map(function(x){return x.visits;}),backgroundColor:colors[1]}, {label:'Student Admissions',data:t.map(function(x){return x.admissions;}),backgroundColor:colors[2]}].filter(function(x){return x.label==='Student Admissions'?visible.college:(x.label==='New Patients'?visible.clinic_patients:visible.clinic_visits);}),{money:false});
 chart('movementChart','bar',labels,[{label:'Collections',data:t.map(function(x){return x.collections;}),backgroundColor:colors[1]},{label:'Recorded Outflows',data:t.map(function(x){return x.outflows;}),backgroundColor:colors[2]}].filter(function(x){return x.label==='Collections'?(visible.food_cash||visible.clinic_cash||visible.college_cash):visible.outflows;}),{money:true});
 doughnut('appointmentsChart',data.charts.appointments||[],false);
}
async function load(){if(loading)return;var from=el('dateFrom').value,to=el('dateTo').value;
 if(!from||!to||from>to){if(window.showToast)showToast('Select a valid From/To date range.',{type:'error',duration:3});return;}
 loading=true;el('applyFilters').disabled=true;el('dashboardLoading').textContent='Loading dashboard…';
 try{var q=new URLSearchParams({date_from:from,date_to:to});var response=await App.api('api/dashboard.php?'+q.toString());var d=response.data||{};
 el('branchLabel').textContent=d.branch?(d.branch.company+' · '+d.branch.name):'My Branch';renderCards(d.cards||[]);renderActions(d.quick_actions||[]);renderRows('pendingRows',d.pending||[],'pending');renderRows('recentRows',d.recent||[],'recent');renderNotes(d.notes||[]);plot(d);el('dashboardLoading').textContent='Showing '+(d.date_from||from)+' to '+(d.date_to||to)+' · Current outstanding is not date-filtered.';
 }catch(e){el('dashboardLoading').textContent='Could not load dashboard data.';if(window.App&&App.showError)App.showError(e,'Unable to load dashboard.');}
 finally{loading=false;el('applyFilters').disabled=false;}
}
el('periodFilter').addEventListener('change',function(){preset(this.value);load();});
['dateFrom','dateTo'].forEach(function(id){el(id).addEventListener('change',function(){el('periodFilter').value='custom';});});
el('applyFilters').addEventListener('click',load);
if(window.GlobalSelect){try{window.GlobalSelect.init(el('periodFilter'),{placeholder:'Reporting Period'});}catch(ignore){}}
load();
})(window,document);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main></div>
<script src="assets/js/appearance.js"></script>
<script>if(window.lucide)window.lucide.createIcons();</script>
</body></html>
