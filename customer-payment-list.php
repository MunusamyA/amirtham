<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Customer Payment List';
$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css'
];
$headScripts = [
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
    <title><?php echo web_h((string)$pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach($headStyles as $url): ?><link rel="stylesheet" href="<?php echo web_h($url); ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
<style>
/* Customer Payment uses only the custom filter-bar search above. */
#paymentTable_filter,
#paymentTable_wrapper .dataTables_filter,
#paymentTable_wrapper .app-table-search-row,
#paymentTable_wrapper ~ .app-table-search-row,
.table-card .dataTables_filter,
.table-card .app-table-search-row {
    display: none !important;
}
</style>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach($headScripts as $url): ?><script src="<?php echo web_h($url); ?>"></script><?php endforeach; ?>
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

<div class="page-head">
    <div>
        <h1>Customer Payment List</h1>
        <p>Common payment register for Customer Payment and Sales POS transactions.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="customer-payment.php" hidden>
        <i data-lucide="hand-coins"></i>Receive Payment
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="receipt-indian-rupee"></i></span>
        <div><div class="kpi-label">Payment Records</div><div class="kpi-value" id="kpiPaymentCount">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="indian-rupee"></i></span>
        <div><div class="kpi-label">Actual Payment</div><div class="kpi-value" id="kpiActualPayment">₹0.00</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="badge-indian-rupee"></i></span>
        <div><div class="kpi-label">Total Settlement</div><div class="kpi-value" id="kpiSettlement">₹0.00</div></div>
    </article>
</div>


<div class="card table-card">
    <div class="card-header">
        <div class="form-row">
            <div class="field col-4">
                <label for="paymentSearch">Search</label>
                <input id="paymentSearch" type="text" autocomplete="off" placeholder="Search payment, invoice or customer...">
            </div>
            <div class="field col-2">
                <label for="sourceFilter">Source</label>
                <select id="sourceFilter">
                    <option value="">All</option>
                    <option value="customer">Customer Payment</option>
                    <option value="pos">Sales POS</option>
                </select>
            </div>
            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="posted" selected>Posted</option>
                    <option value="draft">Draft</option>
                    <option value="inactive">Inactive</option>
                    <option value="reversed">Reversed</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="field col-2">
                <label for="dateFromFilter">From Date</label>
                <input id="dateFromFilter" type="date">
            </div>
            <div class="field col-2">
                <label for="dateToFilter">To Date</label>
                <input id="dateToFilter" type="date">
            </div>
        </div>
    </div>

    <table id="paymentTable" class="display data-table" style="width:100%">
        <thead>
        <tr>
            <th>Payment No</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Source</th>
            <th>Payment For</th>
            <th>Payment</th>
            <th>Credit</th>
            <th>Discount</th>
            <th>Mode / Account</th>
            <th>Status</th>
            <th>Balance</th>
            <th>Manage</th>
        </tr>
        </thead>
    </table>
</div>

<script>
(function($,window){
    "use strict";
    if(!window.AppDataTable||!AppDataTable.ensureAvailable())return;

    var listActions=[];
    var formActions=[];
    var salesActions=[];
    var has=AppDataTable.has;
    var searchTimer=null;
    var paymentSearch=document.getElementById("paymentSearch");
    var sourceFilter=document.getElementById("sourceFilter");
    var statusFilter=document.getElementById("statusFilter");
    var dateFromFilter=document.getElementById("dateFromFilter");
    var dateToFilter=document.getElementById("dateToFilter");
    var addButton=document.getElementById("addButton");

    function esc(v){return $("<div>").text(v==null?"":String(v)).html();}
    function money(v){var n=Number(v||0);if(!Number.isFinite(n))n=0;return "₹"+n.toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});}
    function setSummary(summary){
        summary=summary||{};
        document.getElementById("kpiPaymentCount").textContent=
            Number(summary.payment_count||0).toLocaleString("en-IN");
        document.getElementById("kpiActualPayment").textContent=
            money(summary.actual_payment||0);
        document.getElementById("kpiSettlement").textContent=
            money(summary.settlement_total||0);
    }
    function badge(text,cls){return '<span class="badge rounded-pill '+cls+'">'+esc(text)+'</span>';}
    function removeCommonSearchRow(){
        var tableElement=document.getElementById("paymentTable");
        var card=tableElement?tableElement.closest(".table-card"):null;
        var wrapper=document.getElementById("paymentTable_wrapper");

        [card,wrapper].forEach(function(root){
            if(!root)return;

            root.querySelectorAll(
                "#paymentTable_filter,.dataTables_filter,.app-table-search-row"
            ).forEach(function(node){
                node.remove();
            });
        });
    }

    function watchForDefaultSearch(){
        var tableElement=document.getElementById("paymentTable");
        var card=tableElement?tableElement.closest(".table-card"):null;
        if(!card||typeof MutationObserver==="undefined")return;

        var observer=new MutationObserver(function(){
            removeCommonSearchRow();
        });

        observer.observe(card,{
            childList:true,
            subtree:true
        });
    }

    var table=AppDataTable.init("#paymentTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appSearchPlaceholder:"Search payments...",
        appLoaderText:"Loading Customer Payments...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,"desc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Customer Payment List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}},
            {extend:"csvHtml5",text:"CSV",title:"Customer Payment List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}},
            {extend:"excelHtml5",text:"Excel",title:"Customer Payment List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}},
            {extend:"pdfHtml5",text:"PDF",title:"Customer Payment List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}},
            {extend:"print",text:"Print",title:"Customer Payment List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9,10]}}
        ],
        ajax:function(data,callback){
            var p=new URLSearchParams();
            p.set("datatable","1");
            p.set("draw",data.draw);
            p.set("start",data.start);
            p.set("length",data.length);
            p.set("search[value]",data.search.value||"");
            p.set("status",statusFilter.value||"posted");
            if(sourceFilter.value)p.set("source",sourceFilter.value);
            if(dateFromFilter.value)p.set("date_from",dateFromFilter.value);
            if(dateToFilter.value)p.set("date_to",dateToFilter.value);
            if(data.order&&data.order[0]){
                p.set("order[0][column]",data.order[0].column);
                p.set("order[0][dir]",data.order[0].dir);
            }
            App.api("api/customer-payments.php?"+p.toString()).then(function(r){
                listActions=(r.data.allowed_actions||[]).map(Number);
                formActions=(r.data.form_actions||[]).map(Number);
                salesActions=(r.data.sales_actions||[]).map(Number);
                setSummary(r.data.summary);
                addButton.hidden=!has(formActions,29);
                AppDataTable.applyExportPermissions(table,listActions);
                callback(r.data.datatable);
                setTimeout(removeCommonSearchRow,0);
            }).catch(function(e){
                setSummary({});
                App.showError(e,"Unable to load Customer Payments.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:"payment_no",defaultContent:"-"},
            {data:"payment_date",defaultContent:"-"},
            {data:null,render:function(d,t,r){
                var name=r.customer_name||"Walk-in Customer";
                var x=(r.customer_code?r.customer_code+" - ":"")+name;
                return t==="display"?esc(x):x;
            }},
            {data:"source_label",render:function(v,t,r){
                if(t!=="display")return v||"";
                return badge(v||"-",r.source==="pos"?"badge-soft-primary":"badge-soft-success");
            }},
            {data:"payment_for_label",defaultContent:"-",render:function(v,t,r){
                if(t!=="display")return v||"";
                var cls=r.source==="pos"?"badge-soft-primary":(Number(r.payment_type)===1?"badge-soft-warning":(Number(r.payment_type)===3?"badge-soft-success":"badge-soft-primary"));
                return badge(v||"-",cls);
            }},
            {data:"amount",className:"dt-body-right",render:function(v,t){return t==="display"?esc(money(v)):Number(v||0);}},
            {data:"credit_applied",className:"dt-body-right",render:function(v,t){return t==="display"?esc(money(v)):Number(v||0);}},
            {data:"discount_amount",className:"dt-body-right",render:function(v,t){return t==="display"?esc(money(v)):Number(v||0);}},
            {data:"payment_summary",defaultContent:"-",render:function(v,t){var x=v||"-";return t==="display"?esc(x):x;}},
            {data:"status_label",render:function(v,t){
                if(t!=="display")return v||"";
                var cls="badge-soft-secondary";
                if(v==="Posted")cls="badge-soft-success";
                else if(v==="Draft")cls="badge-soft-warning";
                else if(v==="Reversed")cls="badge-soft-danger";
                return badge(v||"-",cls);
            }},
            {data:"balance_amount",className:"dt-body-right",render:function(v,t){
                var n=Number(v||0);
                if(t!=="display")return n;
                return badge(money(n),n<=0.001?"badge-soft-success":"badge-soft-warning");
            }},
            {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(d,t,r){
                if(t!=="display")return "";
                var h="";
                if(r.source==="pos"){
                    if(r.sale_url&&has(salesActions,1))h+='<a class="table-icon-action" href="'+esc(r.sale_url)+'" title="View Final Invoice / Payment" aria-label="View Final Invoice / Payment"><i data-lucide="eye"></i></a>';
                    if(r.sale_url&&has(salesActions,3))h+='<a class="table-icon-action" href="'+esc(r.sale_url)+'" title="Edit POS Payment" aria-label="Edit POS Payment"><i data-lucide="pencil"></i></a>';
                    if(r.can_delete&&has(salesActions,3))h+='<button type="button" class="table-icon-action js-delete" data-ref="'+esc(r.ref)+'" data-source="pos" title="Delete POS Payment" aria-label="Delete POS Payment"><i data-lucide="trash-2"></i></button>';
                }else{
                    if(r.view_url&&has(formActions,1))h+='<a class="table-icon-action" href="'+esc(r.view_url)+'" title="View Customer Payment" aria-label="View Customer Payment"><i data-lucide="eye"></i></a>';
                    if(r.edit_url&&has(formActions,3))h+='<a class="table-icon-action" href="'+esc(r.edit_url)+'" title="Edit Customer Payment" aria-label="Edit Customer Payment"><i data-lucide="pencil"></i></a>';
                    if(r.can_delete&&has(formActions,4))h+='<button type="button" class="table-icon-action js-delete" data-ref="'+esc(r.ref)+'" data-source="customer" title="Delete Customer Payment" aria-label="Delete Customer Payment"><i data-lucide="trash-2"></i></button>';
                }
                return h||'<span class="muted">-</span>';
            }}
        ],
        language:{emptyTable:"No Customer Payments found.",zeroRecords:"No matching Customer Payments found."},
        drawCallback:function(){removeCommonSearchRow();if(window.lucide)window.lucide.createIcons();},
        initComplete:function(){removeCommonSearchRow();}
    });

    removeCommonSearchRow();
    watchForDefaultSearch();

    paymentSearch.addEventListener("input",function(){
        window.clearTimeout(searchTimer);
        searchTimer=window.setTimeout(function(){table.search(paymentSearch.value.trim()).draw();},350);
    });
    sourceFilter.addEventListener("change",function(){table.ajax.reload(null,true);});
    statusFilter.addEventListener("change",function(){table.ajax.reload(null,true);});
    dateFromFilter.addEventListener("change",function(){table.ajax.reload(null,true);});
    dateToFilter.addEventListener("change",function(){table.ajax.reload(null,true);});

    $(document).on("click",".js-delete",async function(){
        var ref=this.dataset.ref;
        var source=this.dataset.source||"customer";
        if(!ref)return;
        var message=source==="pos"
            ? "Delete this Sales POS payment? The linked Final Invoice paid amount, balance and Customer Credit will be recalculated automatically."
            : "Delete this Customer Payment? Its invoice allocation and Customer Credit effect will be recalculated automatically.";
        if(!window.confirm(message))return;
        this.disabled=true;
        try{
            var r=await App.api("api/customer-payments.php",{method:"POST",body:{action:"delete",ref:ref}});
            showToast(r.message||"Payment deleted successfully.",{type:"success",duration:2});
            table.ajax.reload(null,false);
        }catch(e){
            App.showError(e,"Unable to delete Payment.");
            this.disabled=false;
        }
    });
})(window.jQuery,window);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
        