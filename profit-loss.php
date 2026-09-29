<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle='Profit & Loss';

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
        <h1>Profit &amp; Loss</h1>
        <p>Accrual P&amp;L from posted Final Sales, Sales Returns, actual Purchase Batch cost and posted Expenses. GST is excluded from revenue, COGS and expenses.</p>
    </div>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="badge-indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Net Sales</div>
            <div class="kpi-value" id="netSales">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="package"></i></span>
        <div>
            <div class="kpi-label">Net COGS</div>
            <div class="kpi-value" id="netCogs">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="trending-up"></i></span>
        <div>
            <div class="kpi-label">Gross Profit</div>
            <div class="kpi-value" id="grossProfit">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="receipt-text"></i></span>
        <div>
            <div class="kpi-label">Expenses</div>
            <div class="kpi-value" id="expenses">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="scale"></i></span>
        <div>
            <div class="kpi-label">Net Profit / Loss</div>
            <div class="kpi-value" id="netProfit">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">

            <div class="field col-4">
                <label for="productSearch">Search</label>
                <input
                    id="productSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Search product..."
                >
            </div>

            <div class="field col-4">
                <label for="productFilter">Product</label>
                <select id="productFilter">
                    <option value="">All Products</option>
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
        <table id="productTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Product</th>
                <th>Gross Sales</th>
                <th>Sales Returns</th>
                <th>Net Sales</th>
                <th>Sales COGS</th>
                <th>Return COGS Reversed</th>
                <th>Net COGS</th>
                <th>Gross Profit</th>
                <th>Margin</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="form-row" style="align-items:stretch;">
    <div class="col-6">
        <div class="card table-card" style="height:100%;">
            <div class="card-header">
                <div>
                    <h2>P&amp;L Statement</h2>
                    <p id="periodLabel">Selected period</p>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table" style="width:100%">
                    <tbody id="statementBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-6">
        <div class="card table-card" style="height:100%;">
            <div class="card-header">
                <div>
                    <h2>Inventory Reference</h2>
                    <p>Inventory values are informational; COGS below is calculated from actual quantities sold from each Purchase Batch.</p>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table" style="width:100%">
                    <tbody>
                    <tr>
                        <td>Opening Stock Value</td>
                        <td class="dt-body-right">
                            <strong id="openingStock">₹0.00</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Closing Stock Value</td>
                        <td class="dt-body-right">
                            <strong id="closingStock">₹0.00</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Gross Margin</td>
                        <td class="dt-body-right">
                            <strong id="grossMargin">0.00%</strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Net Margin</td>
                        <td class="dt-body-right">
                            <strong id="netMargin">0.00%</strong>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-body">
                <div id="reportNotes" class="muted"></div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div>
            <h2>Expense Breakdown</h2>
            <p>Posted Expense value excluding GST where taxable value is available.</p>
        </div>
    </div>

    <div class="table-scroll">
        <table id="expenseTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Category</th>
                <th>Amount</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($){'use strict';if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;var productSelect=null,allowedActions=[],searchTimer=null;function el(id){return document.getElementById(id);}function money(v){var n=Number(v||0),neg=n<0;return(neg?'− ':'')+'₹'+Math.abs(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function row(label,value,cls){return'<tr><td>'+label+'</td><td class="dt-body-right"><strong class="'+(cls||'')+'">'+money(value)+'</strong></td></tr>';}function setProfitClass(node,value){node.classList.remove('trend-up','trend-down');if(Number(value)>0)node.classList.add('trend-up');else if(Number(value)<0)node.classList.add('trend-down');}
var productTable=AppDataTable.init('#productTable',{serverSide:false,searching:true,appSearch:false,pageLength:25,order:[[7,'desc']],scrollX:true,autoWidth:false,data:[],buttons:[{extend:'copyHtml5',text:'Copy',title:'Product Profitability'},{extend:'csvHtml5',text:'CSV',title:'Product Profitability'},{extend:'excelHtml5',text:'Excel',title:'Product Profitability'},{extend:'pdfHtml5',text:'PDF',title:'Product Profitability',orientation:'landscape',pageSize:'A4'},{extend:'print',text:'Print',title:'Product Profitability'}],columns:[{data:null,render:function(d,t,r){var v=(r.product_code?r.product_code+' - ':'')+r.product_name;return v;}},{data:'gross_sales',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'sales_returns',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'net_sales',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'sales_cogs',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'return_cogs_reversed',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'net_cogs',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}},{data:'gross_profit',className:'dt-body-right',render:function(v,t){if(t!=='display')return Number(v||0);var cls=Number(v)>=0?'trend-up':'trend-down';return'<strong class="'+cls+'">'+money(v)+'</strong>'; }},{data:'gross_margin',className:'dt-body-right',render:function(v,t){return t==='display'?Number(v||0).toFixed(2)+'%':Number(v||0);}}]});
var expenseTable=AppDataTable.init('#expenseTable',{serverSide:false,searching:false,appSearch:false,paging:false,info:false,order:[[1,'desc']],data:[],buttons:[{extend:'copyHtml5',text:'Copy',title:'Expense Breakdown'},{extend:'csvHtml5',text:'CSV',title:'Expense Breakdown'},{extend:'excelHtml5',text:'Excel',title:'Expense Breakdown'},{extend:'pdfHtml5',text:'PDF',title:'Expense Breakdown'},{extend:'print',text:'Print',title:'Expense Breakdown'}],columns:[{data:null,render:function(d,t,r){return(r.category_code?r.category_code+' - ':'')+r.category_name;}},{data:'amount',className:'dt-body-right',render:function(v,t){return t==='display'?money(v):Number(v||0);}}]});(function(){['productTable','expenseTable'].forEach(function(id){var e=el(id),card=e?e.closest('.table-card'):null,wrapper=el(id+'_wrapper');[card,wrapper].forEach(function(root){if(!root)return;root.querySelectorAll('#'+id+'_filter,.dataTables_filter,.dt-search,.app-table-search-row').forEach(function(node){node.remove();});});});})();
function apply(s,data){s=s||{};el('netSales').textContent=money(s.net_sales);el('netCogs').textContent=money(s.net_cogs);el('grossProfit').textContent=money(s.gross_profit);el('expenses').textContent=money(s.operating_expenses);el('netProfit').textContent=money(s.net_profit);setProfitClass(el('grossProfit'),s.gross_profit);setProfitClass(el('netProfit'),s.net_profit);el('openingStock').textContent=money(s.opening_stock_value);el('closingStock').textContent=money(s.closing_stock_value);el('grossMargin').textContent=Number(s.gross_margin||0).toFixed(2)+'%';el('netMargin').textContent=Number(s.net_margin||0).toFixed(2)+'%';el('statementBody').innerHTML=row('Gross Sales',s.gross_sales)+row('Less: Sales Returns',-Number(s.sales_returns||0),'trend-down')+row('Net Sales',s.net_sales)+row('Sales COGS',-Number(s.sales_cogs||0),'trend-down')+row('Less: COGS Reversed on Restock Returns',Number(s.return_cogs_reversed||0),'trend-up')+row('Net Cost of Goods Sold',-Number(s.net_cogs||0),'trend-down')+row('Gross Profit',s.gross_profit,Number(s.gross_profit)>=0?'trend-up':'trend-down')+row('Operating Expenses',-Number(s.operating_expenses||0),'trend-down')+row('Net Profit / Loss',s.net_profit,Number(s.net_profit)>=0?'trend-up':'trend-down');el('periodLabel').textContent=(data.date_from||'')+' to '+(data.date_to||'');el('reportNotes').innerHTML=(data.notes||[]).map(function(n){return'<div>• '+$('<div>').text(n).html()+'</div>';}).join('');productTable.clear().rows.add(data.products||[]).draw();expenseTable.clear().rows.add(data.expenses||[]).draw();AppDataTable.applyExportPermissions(productTable,allowedActions);AppDataTable.applyExportPermissions(expenseTable,allowedActions);}
async function load(){var p=new URLSearchParams();p.set('date_from',el('dateFrom').value);p.set('date_to',el('dateTo').value);p.set('product_id',el('productFilter').value);try{var r=await App.api('api/profit-loss.php?'+p.toString());allowedActions=(r.data.allowed_actions||allowedActions||[]).map(Number);apply(r.data.summary,r.data);}catch(e){App.showError(e,'Unable to load Profit & Loss.');}}
App.api('api/profit-loss.php?options=1').then(function(r){allowedActions=(r.data.allowed_actions||[]).map(Number);el('dateFrom').value=r.data.date_from;el('dateTo').value=r.data.date_to;(r.data.products||[]).forEach(function(x){var o=document.createElement('option');o.value=x.id;o.textContent=(x.product_code?x.product_code+' - ':'')+x.product_name+(Number(x.status)===0?' (Inactive)':'');el('productFilter').appendChild(o);});if(window.GlobalSelect)productSelect=GlobalSelect.init(el('productFilter'),{placeholder:'All Products'});AppDataTable.applyExportPermissions(productTable,allowedActions);AppDataTable.applyExportPermissions(expenseTable,allowedActions);load();}).catch(function(e){App.showError(e,'Unable to load Profit & Loss options.');});['dateFrom','dateTo','productFilter'].forEach(function(id){el(id).addEventListener('change',load);});el('productSearch').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){productTable.search(el('productSearch').value.trim()).draw();},250);});
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
