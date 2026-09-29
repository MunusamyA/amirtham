<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Cash / Bank Report';

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
        <h1>Cash / Bank Report</h1>
        <p>Detailed account movement report with every receipt/payment, reference, payment mode and running balance.</p>
    </div>
</div>

<div class="kpi-grid">

                <article class="card kpi-card">
                    <span class="kpi-icon blue"><i data-lucide="history"></i></span>
                    <div>
                        <div class="kpi-label">Opening</div>
                        <div class="kpi-value" id="kpi_opening_balance">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="banknote"></i></span>
                    <div>
                        <div class="kpi-label">Money In</div>
                        <div class="kpi-value" id="kpi_money_in">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon teal"><i data-lucide="arrow-up-right"></i></span>
                    <div>
                        <div class="kpi-label">Money Out</div>
                        <div class="kpi-value" id="kpi_money_out">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon green"><i data-lucide="arrow-left-right"></i></span>
                    <div>
                        <div class="kpi-label">Net Movement</div>
                        <div class="kpi-value" id="kpi_net_movement">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="scale"></i></span>
                    <div>
                        <div class="kpi-label">Closing</div>
                        <div class="kpi-value" id="kpi_closing_balance">0</div>
                    </div>
                </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">

                        <div class="field col-4">
                            <label for="reportSearch">Search</label>
                            <input
                                id="reportSearch"
                                type="text"
                                autocomplete="off"
                                placeholder="Search this report..."
                            >
                        </div>
                        <div class="field col-2"><label for="dateFrom">From Date</label><input data-param="date_from" id="dateFrom" type="date"/></div>
                        <div class="field col-2"><label for="dateTo">To Date</label><input data-param="date_to" id="dateTo" type="date"/></div>
                        <div class="field col-4"><label for="account">Account</label><select data-param="account_id" data-source="accounts" data-text='["account_code", "account_name"]' data-value="id" id="account"><option value="">All</option></select></div>
                        <div class="field col-3"><label for="transactionType">Transaction Type</label><select data-param="transaction_type" data-source="transaction_types" data-text='["label"]' data-value="value" id="transactionType"><option value="">All</option></select></div>
        </div>
    </div>

    <div class="table-scroll">
        <table class="display data-table" id="reportTable" style="width:100%"><thead><tr><th>Date / Time</th><th>Account</th><th>Transaction</th><th>Reference</th><th>Particular</th><th>Mode</th><th>Payment Reference</th><th>Cheque No</th><th>Cheque Date</th><th>Money In</th><th>Money Out</th><th>Running Balance</th></tr></thead></table>
    </div>
</div>

<p id="resultMeta" class="muted">Loading report...</p>

<script>
(function($){
'use strict';
var CONFIG={"scope":"cash_bank","taxFixed":null,"taxToggle":false,"columns":[{"data":"date_time","title":"Date / Time","type":"datetime"},{"data":"account","title":"Account","type":"text"},{"data":"transaction","title":"Transaction","type":"badge"},{"data":"reference","title":"Reference","type":"link"},{"data":"particular","title":"Particular","type":"text"},{"data":"mode","title":"Mode","type":"text"},{"data":"payment_reference","title":"Payment Reference","type":"text"},{"data":"cheque_no","title":"Cheque No","type":"text"},{"data":"cheque_date","title":"Cheque Date","type":"date"},{"data":"money_in","title":"Money In","type":"money"},{"data":"money_out","title":"Money Out","type":"money"},{"data":"running_balance","title":"Running Balance","type":"money"}],"kpis":[{"key":"opening_balance","type":"money"},{"key":"money_in","type":"money"},{"key":"money_out","type":"money"},{"key":"net_movement","type":"money"},{"key":"closing_balance","type":"money"}],"derivedKpis":{},"filters":[{"id":"dateFrom","name":"date_from","label":"From Date","type":"date","col":"col-2"},{"id":"dateTo","name":"date_to","label":"To Date","type":"date","col":"col-2"},{"id":"account","name":"account_id","label":"Account","type":"select","source":"accounts","value":"id","text":["account_code","account_name"],"col":"col-4"},{"id":"transactionType","name":"transaction_type","label":"Transaction Type","type":"select","source":"transaction_types","value":"value","text":["label"],"col":"col-3"}],"exportTitle":"Cash / Bank Report"};
var allowedActions=[], table=null, taxMode=(CONFIG.taxFixed===0?0:1), searchTimer=null, selectInstances=[];
function el(id){return document.getElementById(id);}
function esc(v){return $('<div>').text(v==null?'':String(v)).html();}
function money(v){var n=Number(v||0),neg=n<0;return(neg?'− ':'')+'₹'+Math.abs(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function qty(v){return Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:0,maximumFractionDigits:3});}
function fmtDate(v){if(!v)return'';var s=String(v).slice(0,10),a=s.split('-');return a.length===3?a[2]+'-'+a[1]+'-'+a[0]:s;}
function fmtDateTime(v){if(!v)return'';var s=String(v),date=fmtDate(s.slice(0,10)),time=s.length>10?s.slice(11,19):'';return date+(time?' '+time:'');}
function badge(v){var s=String(v||'');return '<span class="badge">'+esc(s)+'</span>';}
function renderCell(c,v,type,row){if(type!=='display')return v==null?'':v;switch(c.type){case'money':return money(v);case'qty':return qty(v);case'number':return Number(v||0).toLocaleString('en-IN');case'percent':return Number(v||0).toFixed(2)+'%';case'date':return fmtDate(v);case'datetime':return fmtDateTime(v);case'badge':return badge(v);case'link':var label=v==null?'':String(v);return row.view_url?'<a class="link" href="'+esc(row.view_url)+'">'+esc(label)+'</a>':esc(label);default:return esc(v);}}
function dtColumns(){return CONFIG.columns.map(function(c){return{data:c.data,defaultContent:'',className:(c.type==='money'||c.type==='qty'||c.type==='number'||c.type==='percent')?'dt-body-right':'',render:function(v,t,r){return renderCell(c,v,t,r);}};});}
function initTable(){if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;table=AppDataTable.init('#reportTable',{serverSide:false,processing:false,searching:true,appSearch:false,pageLength:25,order:[],scrollX:true,autoWidth:false,data:[],buttons:[{extend:'copyHtml5',text:'Copy',title:CONFIG.exportTitle},{extend:'csvHtml5',text:'CSV',title:CONFIG.exportTitle},{extend:'excelHtml5',text:'Excel',title:CONFIG.exportTitle},{extend:'pdfHtml5',text:'PDF',title:CONFIG.exportTitle,orientation:'landscape',pageSize:'A4'},{extend:'print',text:'Print',title:CONFIG.exportTitle}],columns:dtColumns()});var card=el('reportTable').closest('.table-card'),wrapper=el('reportTable_wrapper');[card,wrapper].forEach(function(root){if(!root)return;root.querySelectorAll('#reportTable_filter,.dataTables_filter,.dt-search,.app-table-search-row').forEach(function(node){node.remove();});});}
function optionText(row,fields){return(fields||[]).map(function(k){return row[k]||'';}).filter(Boolean).join(' - ');}
function populateOptions(data){CONFIG.filters.forEach(function(f){if(f.type!=='select'||!f.source)return;var node=el(f.id),rows=data[f.source]||[];node.innerHTML='<option value="">All</option>';rows.forEach(function(r){var o=document.createElement('option');o.value=r[f.value];o.textContent=optionText(r,f.text)+(Number(r.status)===0?' (Inactive)':'');node.appendChild(o);});if(window.GlobalSelect){try{var inst=GlobalSelect.init(node,{placeholder:'All'});if(inst)selectInstances.push(inst);}catch(ignore){}}});}
function setDefaults(data){if(el('dateFrom'))el('dateFrom').value=data.date_from||'';if(el('dateTo'))el('dateTo').value=data.date_to||'';}
function params(){var p=new URLSearchParams();p.set('scope',CONFIG.scope);CONFIG.filters.forEach(function(f){var n=el(f.id);if(n)p.set(f.name,n.value||'');});if(CONFIG.taxToggle)p.set('tax_mode',String(taxMode));return p;}
function evalDerived(expr,s){if(!expr)return 0;return expr.split('+').reduce(function(a,k){return a+Number(s[k.trim()]||0);},0);}
function renderKpis(summary){summary=summary||{};CONFIG.kpis.forEach(function(k){var v=CONFIG.derivedKpis[k.key]?evalDerived(CONFIG.derivedKpis[k.key],summary):summary[k.key];var n=el('kpi_'+k.key);if(!n)return;n.textContent=k.type==='money'?money(v):(k.type==='qty'?qty(v):Number(v||0).toLocaleString('en-IN'));});}
function updateTaxBadge(){var b=el('taxModeBadge');if(b)b.textContent=taxMode===1?'GST':'NON GST';}
async function loadReport(){if(!table)return;try{var r=await App.api('api/reports.php?'+params().toString());allowedActions=(r.data.allowed_actions||allowedActions||[]).map(Number);var rows=r.data.rows||[];table.clear().rows.add(rows).draw();renderKpis(r.data.summary||{});el('resultMeta').textContent=rows.length.toLocaleString('en-IN')+' detailed row(s) · '+(r.data.date_from||'')+' to '+(r.data.date_to||'');AppDataTable.applyExportPermissions(table,allowedActions);}catch(e){App.showError(e,'Unable to load report.');}}
async function boot(){initTable();try{var r=await App.api('api/reports.php?scope='+encodeURIComponent(CONFIG.scope)+'&options=1');allowedActions=(r.data.allowed_actions||[]).map(Number);setDefaults(r.data);populateOptions(r.data);if(table)AppDataTable.applyExportPermissions(table,allowedActions);updateTaxBadge();await loadReport();}catch(e){App.showError(e,'Unable to load report options.');}}
CONFIG.filters.forEach(function(f){document.addEventListener('change',function(e){if(e.target&&e.target.id===f.id)loadReport();});if(f.type==='text')document.addEventListener('input',function(e){if(e.target&&e.target.id===f.id){clearTimeout(searchTimer);searchTimer=setTimeout(loadReport,300);}});});
el('reportSearch').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){if(table)table.search(el('reportSearch').value.trim()).draw();},220);});
if(CONFIG.taxToggle)document.addEventListener('keydown',function(e){if(e.ctrlKey&&e.shiftKey&&String(e.key).toLowerCase()==='u'){e.preventDefault();taxMode=taxMode===1?0:1;updateTaxBadge();loadReport();}});
boot();
})(jQuery);
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
