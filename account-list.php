<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Account List';

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
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>

    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>

    <?php foreach ($headStyles as $u): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $u): ?>
    <script src="<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>"></script>
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

<div class="page-head">
    <div>
        <h1>Account List</h1>
        <p>Your branch cash and bank accounts. Account ledger will be connected later.</p>
    </div>

    <a class="btn btn-primary" id="addButton" href="account-form.php">
        <i data-lucide="plus"></i>Add Account
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Total Accounts</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="banknote"></i></span>
        <div>
            <div class="kpi-label">Cash Accounts</div>
            <div class="kpi-value" id="kpiCash">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="landmark"></i></span>
        <div>
            <div class="kpi-label">Bank Accounts</div>
            <div class="kpi-value" id="kpiBank">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Opening Balance</div>
            <div class="kpi-value" id="kpiOpening">₹0.00</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-5">
                <label for="accountSearch">Search</label>
                <input id="accountSearch" type="text" autocomplete="off"
                       placeholder="Code, account, bank, account no...">
            </div>

            <div class="field col-3">
                <label for="typeFilter">Account Type</label>
                <select id="typeFilter">
                    <option value="">All Types</option>
                    <option value="1">Cash</option>
                    <option value="2">Bank</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="table-scroll">
        <table id="accountTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Code</th>
                <th>Account Name</th>
                <th>Type</th>
                <th>Bank / Type</th>
                <th>Account No</th>
                <th>Opening Balance</th>
                <th>Status</th>
                <th>Manage</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($){
    "use strict";

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var listActions = [];
    var formActions = [];
    var has = AppDataTable.has;
    var searchTimer = null;

    function esc(v){
        return $("<div>").text(v == null ? "" : String(v)).html();
    }

    function money(v){
        var n = Number(v || 0);
        return "₹" + n.toLocaleString("en-IN", {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_accounts || 0).toLocaleString("en-IN");

        document.getElementById("kpiCash").textContent =
            Number(summary.cash_accounts || 0).toLocaleString("en-IN");

        document.getElementById("kpiBank").textContent =
            Number(summary.bank_accounts || 0).toLocaleString("en-IN");

        document.getElementById("kpiOpening").textContent =
            money(summary.opening_balance || 0);
    }

    var table = AppDataTable.init("#accountTable", {
        serverSide:true,
        searching:true,
        searchDelay:300,
        appSearch:false,
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[],
        scrollX:true,
        autoWidth:false,

        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Account List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"csvHtml5",text:"CSV",title:"Account List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"excelHtml5",text:"Excel",title:"Account List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"pdfHtml5",text:"PDF",title:"Account List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"print",text:"Print",title:"Account List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}}
        ],

        ajax:function(data,cb){
            var p = new URLSearchParams();

            p.set("datatable","1");
            p.set("draw",data.draw);
            p.set("start",data.start);
            p.set("length",data.length);
            p.set("search[value]",data.search.value || "");
            p.set("account_type",document.getElementById("typeFilter").value);
            p.set("status",document.getElementById("statusFilter").value);

            if(data.order && data.order[0]){
                p.set("order[0][column]",data.order[0].column);
                p.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/accounts.php?" + p.toString())
                .then(function(r){
                    listActions = (r.data.list_actions || []).map(Number);
                    formActions = (r.data.form_actions || []).map(Number);

                    document.getElementById("addButton").style.display =
                        has(formActions,2) ? "inline-flex" : "none";

                    setSummary(r.data.summary);
                    AppDataTable.applyExportPermissions(table,listActions);
                    cb(r.data.datatable);
                })
                .catch(function(e){
                    setSummary({});
                    App.showError(e,"Unable to load accounts.");
                    cb({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {data:"account_code"},
            {data:"account_name"},
            {data:"type_label"},
            {
                data:null,
                render:function(d,t,r){
                    var v = Number(r.account_type) === 2
                        ? [(r.bank_name || "-"), r.bank_type_label].join(" / ")
                        : "-";

                    return t === "display" ? esc(v) : v;
                }
            },
            {
                data:"account_number",
                defaultContent:"-",
                render:function(v,t){
                    if(!v) return "-";
                    var s = String(v);
                    var masked = s.length > 4 ? "****" + s.slice(-4) : s;
                    return t === "display" ? esc(masked) : s;
                }
            },
            {
                data:"opening_balance",
                className:"dt-body-right",
                render:function(v,t){
                    return t === "display" ? money(v) : Number(v || 0);
                }
            },
            {
                data:"status",
                render:function(v,t){
                    if(t !== "display") return Number(v);

                    return Number(v) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:"table-action-icons",
                render:function(d,t,r){
                    if(t !== "display") return "";

                    var a = [];

                    if(has(formActions,3)){
                        a.push(App.iconActionHtml({
                            href:r.edit_url,
                            icon:"pencil",
                            label:"Edit account"
                        }));
                    }

                    if(Number(r.status) === 1 && has(listActions,28)){
                        a.push(
                            '<button class="btn small gray js-status" data-ref="' +
                            esc(r.ref) +
                            '" data-action="deactivate">Deactivate</button>'
                        );
                    }

                    if(Number(r.status) === 0 && has(listActions,27)){
                        a.push(
                            '<button class="btn small gray js-status" data-ref="' +
                            esc(r.ref) +
                            '" data-action="activate">Activate</button>'
                        );
                    }

                    return a.join(" ") || '<span class="muted">View only</span>';
                }
            }
        ],

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        },

        language:{
            emptyTable:"No accounts found.",
            zeroRecords:"No matching accounts found.",
            processing:"Loading accounts..."
        }
    });

    (function removeDefaultSearch(){
        var element = document.getElementById("accountTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;

        if(row) row.remove();
    })();

    var q = document.getElementById("accountSearch");

    q.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(q.value.trim()).draw();
        },300);
    });

    ["typeFilter","statusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    $("#accountTable").on("click",".js-status",async function(){
        var b = this;
        var ref = b.dataset.ref;
        var action = b.dataset.action;

        if(!ref) return;

        b.disabled = true;

        try{
            var r = await App.api("api/accounts.php",{
                method:"PATCH",
                headers:{"Content-Type":"application/json"},
                body:JSON.stringify({
                    ref:ref,
                    action:action
                })
            });

            showToast(r.message,{
                type:"success",
                duration:2
            });

            table.ajax.reload(null,false);
        }catch(e){
            App.showError(e,"Unable to update account status.");
        }finally{
            b.disabled = false;
        }
    });

})(jQuery);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script>
if(window.lucide){window.lucide.createIcons();}
</script>

</body>
</html>
