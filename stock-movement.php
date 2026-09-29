<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle='Stock Movement';

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

    <?php foreach($headStyles as $url): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($url,ENT_QUOTES,'UTF-8'); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach($headScripts as $url): ?>
        <script src="<?php echo htmlspecialchars($url,ENT_QUOTES,'UTF-8'); ?>"></script>
    <?php endforeach; ?>
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
        <h1>Stock Movement</h1>
        <p>Batch-wise live stock ledger from Purchase, Sale, Purchase Return, Sale Return and Adjustment movements.</p>
    </div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="activity"></i></span>
        <div>
            <div class="kpi-label" id="k1Label">Movements</div>
            <div class="kpi-value" id="k1">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="package-plus"></i></span>
        <div>
            <div class="kpi-label" id="k2Label">Purchase In</div>
            <div class="kpi-value" id="k2">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="package-minus"></i></span>
        <div>
            <div class="kpi-label" id="k3Label">Sale Out</div>
            <div class="kpi-value" id="k3">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="rotate-ccw"></i></span>
        <div>
            <div class="kpi-label" id="k4Label">Returns</div>
            <div class="kpi-value" id="k4">0</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">

            <div class="field col-4">
                <label for="movementSearch">Search</label>
                <input
                    id="movementSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Search product, batch, reference..."
                >
            </div>

            <div class="field col-3">
                <label for="productFilter">Product</label>
                <select id="productFilter">
                    <option value="">All Products</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="batchFilter">Batch</label>
                <select id="batchFilter">
                    <option value="">All Batches</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="typeFilter">Movement</label>
                <select id="typeFilter">
                    <option value="">All Movements</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="dateFrom">From Date</label>
                <input id="dateFrom" type="date">
            </div>

            <div class="field col-2">
                <label for="dateTo">To Date</label>
                <input id="dateTo" type="date">
            </div>

        </div>
    </div>

    <div class="table-scroll">
        <table id="movementTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Batch</th>
                <th>Movement</th>
                <th>Reference</th>
                <th>In</th>
                <th>Out</th>
                <th>Balance</th>
                <th>Unit</th>
                <th>Remarks</th>
                <th>View</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<p id="movementHelp" class="muted">
    Select a Product for Opening / In / Out / Closing quantity summary.
</p>

<script>
(function($){'use strict';if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;var productSelect=null,batchSelect=null,typeSelect=null,allBatches=[],allowedActions=[],searchTimer=null;function el(id){return document.getElementById(id);}function qty(v){return Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:3,maximumFractionDigits:3});}function esc(v){return $('<div>').text(v==null?'':String(v)).html();}function setText(id,v){el(id).textContent=v;}
function fillBatches(){var productId=Number(el('productFilter').value||0),current=el('batchFilter').value;el('batchFilter').innerHTML='<option value="">All Batches</option>';allBatches.filter(function(b){return !productId||Number(b.product_id)===productId;}).forEach(function(b){var o=document.createElement('option');o.value=b.source_purchase_id;o.textContent=(b.batch_number||b.purchase_no||('Purchase #'+b.source_purchase_id))+' · '+b.product_name;el('batchFilter').appendChild(o);});if(batchSelect&&batchSelect.destroy){try{batchSelect.destroy();}catch(ignore){}}if(window.GlobalSelect)batchSelect=GlobalSelect.init(el('batchFilter'),{placeholder:'All Batches'});if(current&&Array.from(el('batchFilter').options).some(function(o){return o.value===current;}))el('batchFilter').value=current;}
function summary(s){s=s||{};if(s.single_product){var unit=s.unit||'Base Qty';setText('k1Label','Opening '+unit);setText('k1',qty(s.opening_qty));setText('k2Label','Stock In '+unit);setText('k2',qty(s.quantity_in));setText('k3Label','Stock Out '+unit);setText('k3',qty(s.quantity_out));setText('k4Label','Closing '+unit);setText('k4',qty(s.closing_qty));el('movementHelp').textContent='Opening + Stock In − Stock Out = Closing for the selected Product.';}else{setText('k1Label','Movements');setText('k1',String(s.movement_count||0));setText('k2Label','Purchase In');setText('k2',String(s.purchase_in_count||0));setText('k3Label','Sale Out');setText('k3',String(s.sale_out_count||0));setText('k4Label','Returns');setText('k4',String((s.purchase_return_count||0)+(s.sale_return_count||0)));el('movementHelp').textContent='Select a Product for Opening / In / Out / Closing quantity summary.';}}
var table=AppDataTable.init('#movementTable',{serverSide:true,searching:true,appSearch:false,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],order:[[0,'desc']],scrollX:true,autoWidth:false,buttons:[{extend:'copyHtml5',text:'Copy',title:'Stock Movement',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},{extend:'csvHtml5',text:'CSV',title:'Stock Movement',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},{extend:'excelHtml5',text:'Excel',title:'Stock Movement',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},{extend:'pdfHtml5',text:'PDF',title:'Stock Movement',orientation:'landscape',pageSize:'A4',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}},{extend:'print',text:'Print',title:'Stock Movement',action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}],ajax:function(data,cb){var p=new URLSearchParams();p.set('datatable','1');p.set('draw',data.draw);p.set('start',data.start);p.set('length',data.length);p.set('date_from',el('dateFrom').value);p.set('date_to',el('dateTo').value);p.set('product_id',el('productFilter').value);p.set('source_purchase_id',el('batchFilter').value);p.set('movement_type',el('typeFilter').value);p.set('search[value]',data.search.value||'');if(data.order&&data.order[0]){p.set('order[0][column]',data.order[0].column);p.set('order[0][dir]',data.order[0].dir);}App.api('api/stock-movement.php?'+p.toString()).then(function(r){allowedActions=(r.data.allowed_actions||[]).map(Number);AppDataTable.applyExportPermissions(table,allowedActions);summary(r.data.summary);cb(r.data.datatable);}).catch(function(e){summary({});App.showError(e,'Unable to load Stock Movement.');cb({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});});},columns:[{data:'movement_date'},{data:'product'},{data:'batch'},{data:'movement_type_label',render:function(v,t,r){if(t!=='display')return v;var cls=Number(r.movement_type)===1||Number(r.movement_type)===4?'active':(Number(r.movement_type)===2||Number(r.movement_type)===3?'inactive':'warning');return'<span class="dt-status '+cls+'">'+esc(v)+'</span>';}},{data:'reference'},{data:'quantity_in',className:'dt-body-right',render:function(v,t){return t==='display'?(Number(v)>0?'<span class="trend-up">+'+qty(v)+'</span>':'<span class="muted">—</span>'):Number(v||0);}},{data:'quantity_out',className:'dt-body-right',render:function(v,t){return t==='display'?(Number(v)>0?'<span class="trend-down">−'+qty(v)+'</span>':'<span class="muted">—</span>'):Number(v||0);}},{data:'balance',className:'dt-body-right',render:function(v,t){return t==='display'?'<strong>'+qty(v)+'</strong>':Number(v||0);}},{data:'unit',defaultContent:'-'},{data:'remarks',defaultContent:''},{data:'view_url',orderable:false,searchable:false,className:'table-action-icons',render:function(v,t){if(t!=='display'||!v)return t==='display'?'<span class="muted">—</span>':'';return App.iconActionHtml({href:v,icon:'eye',label:'View Source'});}}],drawCallback:function(){if(window.lucide)lucide.createIcons();}});(function(){var e=el('movementTable'),card=e?e.closest('.table-card'):null,wrapper=el('movementTable_wrapper');[card,wrapper].forEach(function(root){if(!root)return;root.querySelectorAll('#movementTable_filter,.dataTables_filter,.dt-search,.app-table-search-row').forEach(function(node){node.remove();});});})();
App.api('api/stock-movement.php?options=1').then(function(r){allowedActions=(r.data.allowed_actions||[]).map(Number);el('dateFrom').value=r.data.date_from;el('dateTo').value=r.data.date_to;(r.data.products||[]).forEach(function(x){var o=document.createElement('option');o.value=x.id;o.textContent=(x.product_code?x.product_code+' - ':'')+x.product_name+(Number(x.status)===0?' (Inactive)':'');el('productFilter').appendChild(o);});allBatches=r.data.batches||[];(r.data.movement_types||[]).forEach(function(x){var o=document.createElement('option');o.value=x.value;o.textContent=x.label;el('typeFilter').appendChild(o);});if(window.GlobalSelect){productSelect=GlobalSelect.init(el('productFilter'),{placeholder:'All Products'});typeSelect=GlobalSelect.init(el('typeFilter'),{placeholder:'All Movements'});}fillBatches();AppDataTable.applyExportPermissions(table,allowedActions);table.ajax.reload();}).catch(function(e){App.showError(e,'Unable to load Stock Movement options.');});el('productFilter').addEventListener('change',function(){fillBatches();table.ajax.reload();});['dateFrom','dateTo','batchFilter','typeFilter'].forEach(function(id){el(id).addEventListener('change',function(){table.ajax.reload();});});el('movementSearch').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){table.search(el('movementSearch').value.trim()).draw();},250);});
})(jQuery);
</script>

</section>

<?php require __DIR__.'/include/footer.php'; ?>
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
