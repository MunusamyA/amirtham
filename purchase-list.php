<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Purchase List';
$headStyles=['https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css','https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'];
$headScripts=['https://code.jquery.com/jquery-3.7.1.min.js','https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js','https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js','https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js','https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js','https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js','https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js','https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'];
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
<?php foreach($headStyles as $u):?><link rel="stylesheet" href="<?php echo htmlspecialchars($u,ENT_QUOTES,'UTF-8');?>"><?php endforeach;?>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<?php foreach($headScripts as $u):?><script src="<?php echo htmlspecialchars($u,ENT_QUOTES,'UTF-8');?>"></script><?php endforeach;?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__.'/include/sidebar.php';?>
<main class="main-stage">
<?php require __DIR__.'/include/topbar.php';?>
<section class="page-content">

<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/datatable.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1>Purchase List</h1>
        <p>Draft and posted purchases with supplier payment balance.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="purchase-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Purchase
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="shopping-cart"></i></span>
        <div>
            <div class="kpi-label">Purchases</div>
            <div class="kpi-value" id="kpiPurchaseCount">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Grand Total</div>
            <div class="kpi-value" id="kpiGrandTotal">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Paid</div>
            <div class="kpi-value" id="kpiPaid">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Balance</div>
            <div class="kpi-value" id="kpiBalance">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-5">
                <label for="purchaseSearch">Search</label>
                <input id="purchaseSearch" type="text" placeholder="Search purchase...">
            </div>

            <div class="field col-3">
                <label for="postingFilter">Posting</label>
                <select id="postingFilter">
                    <option value="">All</option>
                    <option value="0">Draft</option>
                    <option value="1">Posted</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="supplierFilter">Supplier</label>
                <select id="supplierFilter">
                    <option value="">All Suppliers</option>
                </select>
            </div>
        </div>
    </div>

    <table id="purchaseTable" class="display data-table" style="width:100%">
        <thead>
        <tr>
            <th>Purchase No</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Supplier Invoice</th>
            <th>Grand Total</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Posting</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<script>
(function($,window,document){
'use strict';

if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

var listActions=[];
var formActions=[];
var has=AppDataTable.has;
var searchTimer=null;
var suppliersLoaded=false;
var supplierGlobal=null;
var postingGlobal=null;

function money(v){
    return '₹'+Number(v||0).toLocaleString('en-IN',{
        minimumFractionDigits:2,
        maximumFractionDigits:2
    });
}

function textCell(v,t){
    return t==='display'
        ?$('<div>').text(v==null?'-':String(v)).html()
        :(v==null?'':v);
}

function amountCell(v,t){
    return t==='display'?money(v):Number(v||0);
}

function setSummary(summary){
    summary=summary||{};

    document.getElementById('kpiPurchaseCount').textContent=
        Number(summary.purchase_count||0).toLocaleString('en-IN');

    document.getElementById('kpiGrandTotal').textContent=
        money(summary.grand_total||0);

    document.getElementById('kpiPaid').textContent=
        money(summary.paid_amount||0);

    document.getElementById('kpiBalance').textContent=
        money(summary.balance_amount||0);
}

function removeDefaultSearch(){
    var tableElement=document.getElementById('purchaseTable');
    var card=tableElement?tableElement.closest('.table-card'):null;
    var wrapper=document.getElementById('purchaseTable_wrapper');

    [card,wrapper].forEach(function(root){
        if(!root)return;

        root.querySelectorAll(
            '#purchaseTable_filter,.dataTables_filter,.app-table-search-row'
        ).forEach(function(node){
            node.remove();
        });
    });
}

if(window.GlobalSelect){
    postingGlobal=GlobalSelect.init(
        document.getElementById('postingFilter'),
        {placeholder:'All'}
    );

    supplierGlobal=GlobalSelect.init(
        document.getElementById('supplierFilter'),
        {placeholder:'All Suppliers'}
    );
}

var buttons=[
    ['copyHtml5','Copy'],
    ['csvHtml5','CSV'],
    ['excelHtml5','Excel'],
    ['pdfHtml5','PDF'],
    ['print','Print']
].map(function(b){
    var cfg={
        extend:b[0],
        text:b[1],
        title:'Purchase List',
        action:AppDataTable.serverSideExportAction,
        exportOptions:{
            columns:[0,1,2,3,4,5,6,7],
            orthogonal:'export'
        }
    };

    if(b[0]==='pdfHtml5'){
        cfg.orientation='landscape';
        cfg.pageSize='A4';
    }

    return cfg;
});

var table=AppDataTable.init('#purchaseTable',{
    serverSide:true,
    searching:true,
    appSearch:false,
    pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],
    order:[[1,'desc']],
    scrollX:true,
    autoWidth:false,
    buttons:buttons,

    ajax:function(data,cb){
        var p=new URLSearchParams();

        p.set('datatable','1');
        p.set('draw',data.draw);
        p.set('start',data.start);
        p.set('length',data.length);
        p.set('search[value]',data.search.value||'');
        p.set('posting_status',document.getElementById('postingFilter').value);
        p.set('supplier_id',document.getElementById('supplierFilter').value);

        if(data.order&&data.order[0]){
            p.set('order[0][column]',data.order[0].column);
            p.set('order[0][dir]',data.order[0].dir);
        }

        App.api('api/purchases.php?'+p.toString()).then(function(r){
            var d=r.data;

            if(!d||!d.datatable){
                throw new Error('Purchase list response is incomplete.');
            }

            listActions=(d.allowed_actions||d.list_actions||[]).map(Number);
            formActions=(d.form_actions||[]).map(Number);

            setSummary(d.summary);

            document.getElementById('addButton').style.display=
                has(formActions,2)?'inline-flex':'none';

            AppDataTable.applyExportPermissions(table,listActions);

            if(!suppliersLoaded&&Array.isArray(d.suppliers)){
                var current=document.getElementById('supplierFilter').value;

                var options=d.suppliers.map(function(x){
                    return {
                        value:String(x.id),
                        text:(x.supplier_code?x.supplier_code+' - ':'')+x.supplier_name
                    };
                });

                if(supplierGlobal&&typeof supplierGlobal.setOptions==='function'){
                    supplierGlobal.setOptions(options,current);
                }else{
                    var s=document.getElementById('supplierFilter');

                    options.forEach(function(x){
                        var o=document.createElement('option');
                        o.value=x.value;
                        o.textContent=x.text;
                        s.appendChild(o);
                    });
                }

                suppliersLoaded=true;
            }

            cb(d.datatable);
        }).catch(function(e){
            setSummary({});
            App.showError(e,'Unable to load Purchases.');

            cb({
                draw:data.draw,
                recordsTotal:0,
                recordsFiltered:0,
                data:[]
            });
        });
    },

    columns:[
        {data:'purchase_no',defaultContent:'-',render:textCell},
        {data:'purchase_date',defaultContent:'-',render:textCell},
        {data:'supplier_name',defaultContent:'-',render:textCell},
        {data:'supplier_invoice_number',defaultContent:'-',render:textCell},
        {data:'grand_total',className:'dt-body-right',render:amountCell},
        {data:'paid_amount',className:'dt-body-right',render:amountCell},
        {data:'balance_amount',className:'dt-body-right',render:amountCell},
        {
            data:'posting_status',
            render:function(v,t){
                var posted=Number(v)===1;

                if(t!=='display'){
                    return t==='sort'||t==='type'
                        ?Number(v)
                        :(posted?'Posted':'Draft');
                }

                return posted
                    ?'<span class="dt-status active">Posted</span>'
                    :'<span class="dt-status inactive">Draft</span>';
            }
        },
        {
            data:null,
            orderable:false,
            searchable:false,
            className:'table-action-icons',
            render:function(d,t,r){
                if(t!=='display')return '';

                var href=r.open_url||r.edit_url;

                if(!href){
                    return '<span class="muted">Unavailable</span>';
                }

                if(
                    Number(r.posting_status)===0 &&
                    has(formActions,1) &&
                    has(formActions,3)
                ){
                    return App.iconActionHtml({
                        href:href,
                        icon:'pencil',
                        label:'Edit Purchase'
                    });
                }

                if(has(formActions,1)){
                    return App.iconActionHtml({
                        href:href,
                        icon:'eye',
                        label:'View Purchase'
                    });
                }

                return '<span class="muted">No access</span>';
            }
        }
    ],

    drawCallback:function(){
        removeDefaultSearch();
        if(window.lucide)window.lucide.createIcons();
    }
});

removeDefaultSearch();

if(typeof MutationObserver!=='undefined'){
    var purchaseTableElement=document.getElementById('purchaseTable');
    var purchaseCard=purchaseTableElement
        ?purchaseTableElement.closest('.table-card')
        :null;

    if(purchaseCard){
        new MutationObserver(function(){
            removeDefaultSearch();
        }).observe(
            purchaseCard,
            {childList:true,subtree:true}
        );
    }
}

var q=document.getElementById('purchaseSearch');

q.addEventListener('input',function(){
    clearTimeout(searchTimer);

    searchTimer=setTimeout(function(){
        table.search(q.value.trim()).draw();
    },300);
});

document.getElementById('postingFilter')
    .addEventListener('change',function(){
        table.ajax.reload(null,true);
    });

document.getElementById('supplierFilter')
    .addEventListener('change',function(){
        table.ajax.reload(null,true);
    });

})(window.jQuery,window,document);
</script>

</section>
<?php require __DIR__.'/include/footer.php';?>
</main>
</div>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>
</body>
</html>
