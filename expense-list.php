<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Expense List';
$headStyles=[
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts=[
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
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
    <?php foreach($headStyles as $u): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($u,ENT_QUOTES,'UTF-8'); ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach($headScripts as $u): ?><script src="<?php echo htmlspecialchars($u,ENT_QUOTES,'UTF-8'); ?>"></script><?php endforeach; ?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__.'/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__.'/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h1>Expense List</h1>
            <span class="dt-status inactive" id="nonGstBadge" hidden>NON GST</span>
        </div>
        <p>Press <strong>Ctrl + Shift + U</strong> to switch the list between GST and NON GST Expenses. Payment Status is calculated automatically.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="expense-form.php"><i data-lucide="plus"></i>Add Expense</a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="receipt"></i></span>
        <div><div class="kpi-label">Expense Records</div><div class="kpi-value" id="kpiExpenseCount">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="indian-rupee"></i></span>
        <div><div class="kpi-label">Total Amount</div><div class="kpi-value" id="kpiExpenseTotal">₹0.00</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>
        <div><div class="kpi-label">Balance Amount</div><div class="kpi-value" id="kpiExpenseBalance">₹0.00</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-3"><label for="expenseSearch">Search</label><input id="expenseSearch" type="text" placeholder="Search expense, payee, category..."></div>
            <div class="field col-2"><label for="dateFrom">From Date</label><input id="dateFrom" type="date"></div>
            <div class="field col-2"><label for="dateTo">To Date</label><input id="dateTo" type="date"></div>
            <div class="field col-3"><label for="categoryFilter">Category</label><select id="categoryFilter"><option value="">All Categories</option></select></div>
            <div class="field col-2"><label for="paymentFilter">Payment</label><select id="paymentFilter"><option value="">All</option><option value="1">Unpaid</option><option value="2">Partially Paid</option><option value="3">Paid</option></select></div>
        </div>
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-3"><label for="postingFilter">Posting</label><select id="postingFilter"><option value="">All</option><option value="0">Draft</option><option value="1">Posted</option></select></div>
        </div>
    </div>
    <div class="table-scroll">
        <table id="expenseTable" class="display data-table" style="width:100%">
            <thead><tr><th>Expense No</th><th>Date</th><th>Category</th><th>Payee</th><th>Total</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Tax</th><th>Posting</th><th>Manage</th></tr></thead>
        </table>
    </div>
</div>

<script>
(function($){
'use strict';
if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;
var listActions=[],formActions=[],paymentActions=[],has=AppDataTable.has,searchTimer=null,categoriesLoaded=false,categorySelect=null,taxModeFilter=1;
function esc(v){return $('<div>').text(v==null?'':String(v)).html();}
function money(v){return'₹'+Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function setSummary(s){s=s||{};document.getElementById('kpiExpenseCount').textContent=Number(s.expense_count||0).toLocaleString('en-IN');document.getElementById('kpiExpenseTotal').textContent=money(s.total_amount||0);document.getElementById('kpiExpenseBalance').textContent=money(s.balance_amount||0);}
function applyTaxMode(){document.getElementById('nonGstBadge').hidden=taxModeFilter!==0;}
function toggleTaxMode(){taxModeFilter=taxModeFilter===0?1:0;applyTaxMode();table.ajax.reload();}

var table=AppDataTable.init('#expenseTable',{
    serverSide:true,searching:true,appSearch:false,pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[1,'desc']],scrollX:true,autoWidth:false,
    buttons:[
        {extend:'copyHtml5',text:'Copy',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
        {extend:'csvHtml5',text:'CSV',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
        {extend:'excelHtml5',text:'Excel',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
        {extend:'pdfHtml5',text:'PDF',title:'Expense List',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},
        {extend:'print',text:'Print',title:'Expense List',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}
    ],
    ajax:function(data,cb){
        var p=new URLSearchParams();
        p.set('datatable','1');p.set('draw',data.draw);p.set('start',data.start);p.set('length',data.length);p.set('search[value]',data.search.value||'');
        p.set('date_from',document.getElementById('dateFrom').value);p.set('date_to',document.getElementById('dateTo').value);p.set('category_id',document.getElementById('categoryFilter').value);p.set('payment_status',document.getElementById('paymentFilter').value);p.set('posting_status',document.getElementById('postingFilter').value);p.set('tax_mode',String(taxModeFilter));
        if(data.order&&data.order[0]){p.set('order[0][column]',data.order[0].column);p.set('order[0][dir]',data.order[0].dir);}
        App.api('api/expenses.php?'+p.toString()).then(function(r){
            listActions=(r.data.list_actions||[]).map(Number);formActions=(r.data.form_actions||[]).map(Number);paymentActions=(r.data.payment_actions||[]).map(Number);
            setSummary(r.data.summary);
            document.getElementById('addButton').style.display=has(formActions,2)?'inline-flex':'none';
            AppDataTable.applyExportPermissions(table,listActions);
            if(!categoriesLoaded){
                var s=document.getElementById('categoryFilter');
                (r.data.categories||[]).forEach(function(x){var o=document.createElement('option');o.value=x.id;o.textContent=(x.category_code?x.category_code+' - ':'')+x.category_name;s.appendChild(o);});
                categoriesLoaded=true;if(window.GlobalSelect)categorySelect=GlobalSelect.init(s,{placeholder:'All Categories'});
            }
            cb(r.data.datatable);
        }).catch(function(e){setSummary({});App.showError(e,'Unable to load Expenses.');cb({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});});
    },
    columns:[
        {data:'expense_no',defaultContent:'-'},{data:'expense_date',defaultContent:'-'},{data:'category_name',defaultContent:'-'},{data:'payee_name',defaultContent:'-'},
        {data:'total_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},
        {data:'paid_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},
        {data:'balance_amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},
        {data:'payment_status_label',render:function(v,t,r){if(t!=='display')return Number(r.payment_status||0);var cls=Number(r.payment_status)===3?'active':(Number(r.payment_status)===2?'warning':'inactive');return'<span class="dt-status '+cls+'">'+esc(v)+'</span>'; }},
        {data:'tax_mode_label',render:function(v,t,r){if(t!=='display')return Number(r.tax_mode||0);return Number(r.tax_mode)===1?'<span class="dt-status active">GST</span>':'<span class="dt-status inactive">NON GST</span>'; }},
        {data:'posting_status_label',render:function(v,t,r){if(t!=='display')return Number(r.posting_status||0);return Number(r.posting_status)===1?'<span class="dt-status active">Posted</span>':'<span class="dt-status inactive">Draft</span>'; }},
        {data:null,orderable:false,searchable:false,className:'table-action-icons',render:function(d,t,r){
            if(t!=='display')return'';var a=[];
            if(Number(r.posting_status)===0&&has(formActions,3))a.push(App.iconActionHtml({href:r.edit_url,icon:'pencil',label:'Edit Expense Draft'}));
            else a.push(App.iconActionHtml({href:r.view_url,icon:'eye',label:'View Expense'}));
            if(Number(r.posting_status)===1&&Number(r.balance_amount)>0.001&&has(paymentActions,2))a.push(App.iconActionHtml({href:r.payment_url,icon:'hand-coins',label:'Make Expense Payment'}));
            if(has(formActions,4))a.push('<button type="button" class="table-icon-action delete-expense" data-ref="'+esc(r.ref)+'" title="Delete Expense" aria-label="Delete Expense"><i data-lucide="trash-2"></i></button>');
            return a.join(' ')||'<span class="muted">View only</span>';
        }}
    ],
    drawCallback:function(){if(window.lucide)window.lucide.createIcons();}
});

(function(){var e=document.getElementById('expenseTable'),card=e?e.closest('.table-card'):null,row=card?card.querySelector('.app-table-search-row'):null;if(row)row.remove();})();
var q=document.getElementById('expenseSearch');
q.addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){table.search(q.value.trim()).draw();},300);});
['dateFrom','dateTo','categoryFilter','paymentFilter','postingFilter'].forEach(function(id){document.getElementById(id).addEventListener('change',function(){table.ajax.reload();});});
document.addEventListener('keydown',function(event){if(event.ctrlKey&&event.shiftKey&&String(event.key).toLowerCase()==='u'){event.preventDefault();toggleTaxMode();}});
document.getElementById('expenseTable').addEventListener('click',function(e){var b=e.target.closest('.delete-expense');if(!b)return;if(!window.confirm('Delete this Expense? Posted Expenses with active payments cannot be deleted until those payments are reversed.'))return;b.disabled=true;App.api('api/expenses.php',{method:'POST',body:{action:'delete',ref:b.dataset.ref}}).then(function(r){showToast(r.message||'Expense deleted.',{type:'success',duration:2});table.ajax.reload(null,false);}).catch(function(err){b.disabled=false;App.showError(err,'Unable to delete Expense.');});});
applyTaxMode();
})(jQuery);
</script>
</section>
<?php require __DIR__.'/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
