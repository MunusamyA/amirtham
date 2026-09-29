<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Role List';

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
        <h1>Role List</h1>
        <p>Manage Plans, Platform Roles and Tenant Roles.</p>
    </div>

    <a class="btn btn-primary" id="addRole" href="role-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Role
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="shield"></i></span>
        <div>
            <div class="kpi-label">Total Roles</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active Roles</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive Roles</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="badge-check"></i></span>
        <div>
            <div class="kpi-label">Plans</div>
            <div class="kpi-value" id="kpiPlans">0</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-4">
                <label for="roleSearch">Search</label>
                <input id="roleSearch" type="text" autocomplete="off"
                       placeholder="Role or owner...">
            </div>

            <div class="field col-4">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="1">Plan</option>
                    <option value="2">Platform Role</option>
                    <option value="3">Tenant Role</option>
                </select>
            </div>

            <div class="field col-4">
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
        <table id="roleTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Role</th>
                <th>Category</th>
                <th>Owner</th>
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
    var has = AppDataTable.has;

    function esc(v){
        return $("<div>").text(v == null ? "" : String(v)).html();
    }

    function typeName(type){
        return {
            1:"Plan",
            2:"Platform Role",
            3:"Tenant Role"
        }[Number(type)] || "Unknown";
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_roles || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_roles || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_roles || 0).toLocaleString("en-IN");

        document.getElementById("kpiPlans").textContent =
            Number(summary.plan_roles || 0).toLocaleString("en-IN");
    }

    function canManage(role){
        var platformManage =
            Number(current.role_type) === 2 &&
            [1,2].indexOf(Number(role.role_type)) !== -1;

        var tenantManage =
            Number(current.role_type) !== 2 &&
            Number(role.role_type) === 3 &&
            Number(role.company_id) === Number(current.company_id);

        return has(allowed,3) && (platformManage || tenantManage);
    }

    var table = AppDataTable.init("#roleTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading roles...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[[0,"asc"]],
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

            var category = document.getElementById("categoryFilter").value;
            var status = document.getElementById("statusFilter").value;

            if(category !== "") p.set("role_type",category);
            if(status !== "") p.set("status",status);

            if(data.order && data.order[0]){
                p.set("order[0][column]",data.order[0].column);
                p.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/roles.php?" + p.toString())
                .then(function(result){
                    allowed = (result.data.allowed_actions || []).map(Number);
                    current = result.data.current_user || {};

                    document.getElementById("addRole").style.display =
                        has(allowed,2)
                            ? "inline-flex"
                            : "none";

                    setSummary(result.data.summary);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,"Unable to load roles.");

                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {data:"role_name",defaultContent:"-"},
            {
                data:"role_type",
                render:function(value,type){
                    var label = typeName(value);
                    return type === "display"
                        ? esc(label)
                        : label;
                }
            },
            {
                data:"company_name",
                defaultContent:"Platform",
                render:function(value,type){
                    var owner = value || "Platform";

                    return type === "display"
                        ? esc(owner)
                        : owner;
                }
            },
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

                    if(!canManage(row)){
                        return '<span class="muted">' +
                            (Number(row.role_type) === 1 ? "Read only" : "View only") +
                            '</span>';
                    }

                    var html = [];

                    html.push(
                        '<a class="table-icon-action" href="' +
                        esc(row.edit_url) +
                        '" title="Edit role" aria-label="Edit role">' +
                        '<i data-lucide="pencil"></i></a>'
                    );

                    html.push(
                        '<a class="table-icon-action primary" href="' +
                        esc(row.permission_url) +
                        '" title="Permissions" aria-label="Permissions">' +
                        '<i data-lucide="key-round"></i></a>'
                    );

                    if(!row.is_current_role){
                        html.push(
                            '<button type="button" class="table-icon-action js-role-status" ' +
                            'data-ref="' + esc(row.ref) + '" ' +
                            'data-name="' + esc(row.role_name) + '" ' +
                            'data-status="' + Number(row.status) + '" ' +
                            'title="' + (Number(row.status) === 1 ? "Deactivate role" : "Activate role") + '">' +
                            '<i data-lucide="' + (Number(row.status) === 1 ? "circle-off" : "circle-check") + '"></i>' +
                            '</button>'
                        );
                    }

                    return html.join(" ");
                }
            }
        ],

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        },

        language:{
            emptyTable:"No roles found.",
            zeroRecords:"No matching roles found.",
            processing:"Loading roles..."
        }
    });

    (function removeDefaultSearch(){
        var element = document.getElementById("roleTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if(row) row.remove();
    })();

    var search = document.getElementById("roleSearch");

    search.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(search.value.trim()).draw();
        },350);
    });

    ["categoryFilter","statusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    $("#roleTable").on("click",".js-role-status",async function(){
        var button = this;
        var ref = button.dataset.ref || "";
        var name = button.dataset.name || "Role";
        var next = Number(button.dataset.status) === 1 ? 0 : 1;

        if(!ref) return;

        if(!confirm((next ? "Activate " : "Deactivate ") + '"' + name + '"?')){
            return;
        }

        button.disabled = true;

        try{
            var result = await App.api("api/roles.php",{
                method:"PATCH",
                body:{
                    ref:ref,
                    status:next
                }
            });

            showToast(result.message,{
                type:"success",
                duration:3
            });

            table.ajax.reload(null,false);
        }catch(error){
            App.showError(error,"Unable to update role status.");
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
