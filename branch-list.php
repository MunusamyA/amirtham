<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Branch List';

$headStyles = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css'
];

$headScripts = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js'
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

    <?php foreach ($headStyles as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>

    <?php foreach ($headScripts as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
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
        <h1>Branch List</h1>
        <p>Manage business branches, plans and Branch Admin assignment.</p>
    </div>

    <a class="btn btn-primary" id="addButton" href="branch-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Branch
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="git-branch"></i></span>
        <div>
            <div class="kpi-label">Total Branches</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active Branches</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive Branches</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="building-2"></i></span>
        <div>
            <div class="kpi-label">Businesses</div>
            <div class="kpi-value" id="kpiBusinesses">0</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-4">
                <label for="branchSearch">Search</label>
                <input id="branchSearch" type="text" autocomplete="off"
                       placeholder="Business, branch, code, plan, admin...">
            </div>

            <div class="field col-3 platform-filter" id="businessFilterField">
                <label for="businessFilter">Business</label>
                <select id="businessFilter">
                    <option value="">All Businesses</option>
                </select>
            </div>

            <div class="field col-3 platform-filter" id="planFilterField">
                <label for="planFilter">Plan</label>
                <select id="planFilter">
                    <option value="">All Plans</option>
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
        <table id="branchTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Business</th>
                <th>Branch</th>
                <th>Code</th>
                <th>Plan</th>
                <th>Branch Admin</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($){
    "use strict";

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var allowed = [];
    var current = {};
    var searchTimer = null;
    var filtersLoaded = false;
    var has = AppDataTable.has;

    function esc(v){
        return $("<div>").text(v == null ? "" : String(v)).html();
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_branches || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_branches || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_branches || 0).toLocaleString("en-IN");

        document.getElementById("kpiBusinesses").textContent =
            Number(summary.business_count || 0).toLocaleString("en-IN");
    }

    function fillFilters(data){
        if(filtersLoaded) return;
        filtersLoaded = true;

        var business = document.getElementById("businessFilter");
        var plan = document.getElementById("planFilter");

        (data.filter_businesses || []).forEach(function(row){
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = row.company_name;
            business.appendChild(option);
        });

        (data.filter_plans || []).forEach(function(row){
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = row.role_name;
            plan.appendChild(option);
        });
    }

    function applyCurrentUser(){
        var platform = Number(current.role_type) === 2;

        document.getElementById("addButton").style.display =
            has(allowed,2) && platform
                ? "inline-flex"
                : "none";

        ["businessFilterField","planFilterField"].forEach(function(id){
            document.getElementById(id).style.display =
                platform ? "" : "none";
        });
    }

    var table = AppDataTable.init("#branchTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading branches...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[1,"asc"]],
        scrollX:true,
        autoWidth:false,
        buttons:[],

        ajax:function(data,callback){
            var p = new URLSearchParams();

            p.set("datatable","1");
            p.set("draw",data.draw);
            p.set("start",data.start);
            p.set("length",data.length);
            p.set("search[value]",data.search.value || "");

            var business = document.getElementById("businessFilter").value;
            var plan = document.getElementById("planFilter").value;
            var status = document.getElementById("statusFilter").value;

            if(business !== "") p.set("company_id",business);
            if(plan !== "") p.set("plan_role_id",plan);
            if(status !== "") p.set("status",status);

            if(data.order && data.order[0]){
                p.set("order[0][column]",data.order[0].column);
                p.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/branches.php?" + p.toString())
                .then(function(result){
                    allowed = (result.data.allowed_actions || []).map(Number);
                    current = result.data.current_user || {};

                    fillFilters(result.data);
                    applyCurrentUser();
                    setSummary(result.data.summary);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,"Unable to load branches.");

                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {data:"company_name",defaultContent:"-"},
            {data:"branch_name",defaultContent:"-"},
            {data:"branch_code",defaultContent:"-"},
            {data:"plan_name",defaultContent:"-"},
            {data:"admin_name",defaultContent:"-"},
            {
                data:"status",
                render:function(value,type){
                    if(type !== "display") return Number(value);

                    return Number(value) === 1
                        ? '<span class="dt-status active">Active</span>'
                        : '<span class="dt-status inactive">Inactive</span>';
                }
            },
            {
                data:null,
                orderable:false,
                searchable:false,
                className:"table-action-icons",
                render:function(data,type,row){
                    if(type !== "display") return "";

                    var html = [];

                    if(has(allowed,3)){
                        html.push(
                            '<a class="table-icon-action" href="branch-form.php?id=' +
                            Number(row.id) +
                            '" title="Edit branch" aria-label="Edit branch">' +
                            '<i data-lucide="pencil"></i></a>'
                        );
                    }

                    if(
                        has(allowed,3) &&
                        Number(current.role_type) === 2
                    ){
                        html.push(
                            '<button type="button" class="table-icon-action js-branch-status" ' +
                            'data-id="' + Number(row.id) + '" ' +
                            'data-name="' + esc(row.branch_name) + '" ' +
                            'data-status="' + Number(row.status) + '" ' +
                            'title="' + (Number(row.status) === 1 ? "Deactivate branch" : "Activate branch") + '">' +
                            '<i data-lucide="' + (Number(row.status) === 1 ? "circle-off" : "circle-check") + '"></i>' +
                            '</button>'
                        );
                    }

                    return html.join(" ") || '<span class="muted">View only</span>';
                }
            }
        ],

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        },

        language:{
            emptyTable:"No branches found.",
            zeroRecords:"No matching branches found.",
            processing:"Loading branches..."
        }
    });

    (function removeDefaultSearch(){
        var element = document.getElementById("branchTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if(row) row.remove();
    })();

    var search = document.getElementById("branchSearch");

    search.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(search.value.trim()).draw();
        },350);
    });

    ["businessFilter","planFilter","statusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    $("#branchTable").on("click",".js-branch-status",async function(){
        var button = this;
        var id = Number(button.dataset.id || 0);
        var name = button.dataset.name || "Branch";
        var next = Number(button.dataset.status) === 1 ? 0 : 1;

        if(!id) return;

        if(!confirm((next ? "Activate " : "Deactivate ") + '"' + name + '"?')){
            return;
        }

        button.disabled = true;

        try{
            var result = await App.api("api/branches.php",{
                method:"PATCH",
                body:{
                    id:id,
                    status:next
                }
            });

            showToast(result.message,{
                type:"success",
                duration:3
            });

            table.ajax.reload(null,false);
        }catch(error){
            App.showError(error,"Unable to update branch status.");
        }finally{
            button.disabled = false;
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
