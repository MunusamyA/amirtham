<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'GST Sales Report';

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
        <h1>GST Sales Report</h1>
        <p>Detailed GST final-invoice report with item-wise CGST, SGST, IGST and Cess.</p>
    </div>
                <span class="badge" id="taxModeBadge">GST</span>
</div>

<div class="kpi-grid">

                <article class="card kpi-card">
                    <span class="kpi-icon blue"><i data-lucide="receipt-text"></i></span>
                    <div>
                        <div class="kpi-label">Invoices</div>
                        <div class="kpi-value" id="kpi_documents">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="indian-rupee"></i></span>
                    <div>
                        <div class="kpi-label">Taxable</div>
                        <div class="kpi-value" id="kpi_taxable_total">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon teal"><i data-lucide="percent"></i></span>
                    <div>
                        <div class="kpi-label">GST</div>
                        <div class="kpi-value" id="kpi_gst_total">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon green"><i data-lucide="receipt-text"></i></span>
                    <div>
                        <div class="kpi-label">Invoice Total</div>
                        <div class="kpi-value" id="kpi_grand_total">0</div>
                    </div>
                </article>

                <article class="card kpi-card">
                    <span class="kpi-icon orange"><i data-lucide="scale"></i></span>
                    <div>
                        <div class="kpi-label">Balance</div>
                        <div class="kpi-value" id="kpi_balance_amount">0</div>
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
                        <div class="field col-3"><label for="customer">Customer</label><select data-param="customer_id" data-source="customers" data-text='["customer_code", "customer_name"]' data-value="id" id="customer"><option value="">All</option></select></div>
                        <div class="field col-3"><label for="product">Product</label><select data-param="product_id" data-source="products" data-text='["product_code", "product_name"]' data-value="id" id="product"><option value="">All</option></select></div>
                        <div class="field col-2"><label for="paymentStatus">Payment Status</label><select data-param="payment_status" data-source="payment_statuses" data-text='["label"]' data-value="id" id="paymentStatus"><option value="">All</option></select></div>
                        <div class="field col-2"><label for="postingStatus">Posting</label><select data-param="posting_status" data-source="" data-text="[]" data-value="" id="postingStatus"><option value="-1">All</option><option selected="" value="1">Posted</option><option value="0">Draft</option></select></div>
        </div>
    </div>

    <div class="table-scroll">
        <table class="display data-table" id="reportTable" style="width:100%"><thead><tr><th>Date</th><th>Invoice No</th><th>Customer Code</th><th>Customer</th><th>GSTIN</th><th>Tax Mode</th><th>Supply</th><th>Product Code</th><th>Product</th><th>Category</th><th>Subcategory</th><th>Batch</th><th>Primary Qty</th><th>Primary Unit</th><th>Secondary Qty</th><th>Secondary Unit</th><th>Base Qty</th><th>Rate</th><th>Seller Type</th><th>Item Discount</th><th>Overall Disc. Share</th><th>Taxable</th><th>GST %</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Cess</th><th>Line Total</th><th>Invoice Total</th><th>Paid</th><th>Balance</th><th>Payment Status</th><th>Posting</th><th>Due Date</th><th>Created By</th><th>Created At</th></tr></thead></table>
    </div>
</div>

<p id="resultMeta" class="muted">Loading report...</p>

<script>
(function($){
'use strict';
var CONFIG={"scope":"gst_sales","taxFixed":1,"taxToggle":false,"columns":[{"data":"invoice_date","title":"Date","type":"date"},{"data":"sales_no","title":"Invoice No","type":"link"},{"data":"customer_code","title":"Customer Code","type":"text"},{"data":"customer_name","title":"Customer","type":"text"},{"data":"customer_gstin","title":"GSTIN","type":"text"},{"data":"tax_mode_label","title":"Tax Mode","type":"badge"},{"data":"supply_type","title":"Supply","type":"text"},{"data":"product_code","title":"Product Code","type":"text"},{"data":"product_name","title":"Product","type":"text"},{"data":"category_name","title":"Category","type":"text"},{"data":"subcategory_name","title":"Subcategory","type":"text"},{"data":"batch_number","title":"Batch","type":"text"},{"data":"primary_quantity","title":"Primary Qty","type":"qty"},{"data":"primary_unit","title":"Primary Unit","type":"text"},{"data":"secondary_quantity","title":"Secondary Qty","type":"qty"},{"data":"secondary_unit","title":"Secondary Unit","type":"text"},{"data":"base_quantity","title":"Base Qty","type":"qty"},{"data":"unit_price","title":"Rate","type":"money"},{"data":"seller_type_name","title":"Seller Type","type":"text"},{"data":"discount_amount","title":"Item Discount","type":"money"},{"data":"overall_discount_share","title":"Overall Disc. Share","type":"money"},{"data":"item_taxable","title":"Taxable","type":"money"},{"data":"gst_rate","title":"GST %","type":"percent"},{"data":"item_cgst","title":"CGST","type":"money"},{"data":"item_sgst","title":"SGST","type":"money"},{"data":"item_igst","title":"IGST","type":"money"},{"data":"item_cess","title":"Cess","type":"money"},{"data":"line_total","title":"Line Total","type":"money"},{"data":"grand_total","title":"Invoice Total","type":"money"},{"data":"paid_amount","title":"Paid","type":"money"},{"data":"balance_amount","title":"Balance","type":"money"},{"data":"payment_status_label","title":"Payment Status","type":"badge"},{"data":"posting_status_label","title":"Posting","type":"badge"},{"data":"due_date","title":"Due Date","type":"date"},{"data":"created_by_name","title":"Created By","type":"text"},{"data":"created_at","title":"Created At","type":"datetime"}],"kpis":[{"key":"documents","type":"number"},{"key":"taxable_total","type":"money"},{"key":"gst_total","type":"money"},{"key":"grand_total","type":"money"},{"key":"balance_amount","type":"money"}],"derivedKpis":{"gst_total":"cgst_total+sgst_total+igst_total"},"filters":[{"id":"dateFrom","name":"date_from","label":"From Date","type":"date","col":"col-2"},{"id":"dateTo","name":"date_to","label":"To Date","type":"date","col":"col-2"},{"id":"customer","name":"customer_id","label":"Customer","type":"select","source":"customers","value":"id","text":["customer_code","customer_name"],"col":"col-3"},{"id":"product","name":"product_id","label":"Product","type":"select","source":"products","value":"id","text":["product_code","product_name"],"col":"col-3"},{"id":"paymentStatus","name":"payment_status","label":"Payment Status","type":"select","source":"payment_statuses","value":"id","text":["label"],"col":"col-2"},{"id":"postingStatus","name":"posting_status","label":"Posting","type":"static","options":[[-1,"All"],[1,"Posted"],[0,"Draft"]],"default":1,"col":"col-2"}],"exportTitle":"GST Sales Report"};
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
