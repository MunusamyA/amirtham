<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Employee List';

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
        <h1>Employee List</h1>
        <p>Employee records, login accounts and role assignment.</p>
    </div>

    <a class="btn btn-primary" id="addButton" href="employee-form.php">
        <i data-lucide="user-plus"></i>Add Employee
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="users"></i></span>
        <div>
            <div class="kpi-label">Total Employees</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active Employees</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive Employees</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="log-in"></i></span>
        <div>
            <div class="kpi-label">Login Accounts</div>
            <div class="kpi-value" id="kpiLogin">0</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-4">
                <label for="employeeSearch">Search</label>
                <input id="employeeSearch" type="text" autocomplete="off"
                       placeholder="Code, employee, contact, username...">
            </div>

            <div class="field col-3" id="branchFilterField">
                <label for="branchFilter">Branch</label>
                <select id="branchFilter">
                    <option value="">All Branches</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="roleFilter">Role</label>
                <select id="roleFilter">
                    <option value="">All Roles</option>
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
        <table id="employeeTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Code</th>
                <th>Employee</th>
                <th>Branch</th>
                <th>Role</th>
                <th>Username</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function($,window,document){
    "use strict";

    if(!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var listActions = [];
    var formActions = [];
    var current = {};
    var has = AppDataTable.has;
    var searchTimer = null;
    var branchesLoaded = false;

    function esc(v){
        return $("<div>").text(v == null ? "" : String(v)).html();
    }

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_employees || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_employees || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_employees || 0).toLocaleString("en-IN");

        document.getElementById("kpiLogin").textContent =
            Number(summary.login_accounts || 0).toLocaleString("en-IN");
    }

    function fillBranches(rows){
        if(branchesLoaded) return;
        branchesLoaded = true;

        var select = document.getElementById("branchFilter");

        (rows || []).forEach(function(row){
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent =
                [row.company_name,row.branch_name]
                    .filter(Boolean)
                    .join(" — ");

            select.appendChild(option);
        });
    }

    function fillRoles(rows){
        var select = document.getElementById("roleFilter");
        var currentValue = select.value;

        select.innerHTML = '<option value="">All Roles</option>';

        (rows || []).forEach(function(row){
            var option = document.createElement("option");
            option.value = String(row.id);

            var label = row.role_name || "-";

            if(
                Number(current.role_type) === 2 &&
                row.company_name
            ){
                label += " — " + row.company_name;
            }

            option.textContent = label;
            select.appendChild(option);
        });

        if(
            currentValue &&
            Array.prototype.some.call(
                select.options,
                function(option){
                    return option.value === currentValue;
                }
            )
        ){
            select.value = currentValue;
        }
    }

    function applyCurrentUser(){
        document.getElementById("branchFilterField").style.display =
            Number(current.role_type) === 2
                ? ""
                : "none";
    }

    var table = AppDataTable.init("#employeeTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading employees...",
        pageLength:10,
        lengthMenu:[[10,25,50,100],[10,25,50,100]],
        order:[],
        scrollX:true,
        autoWidth:false,

        buttons:[
            {extend:"copyHtml5",text:"Copy",title:"Employee List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"csvHtml5",text:"CSV",title:"Employee List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"excelHtml5",text:"Excel",title:"Employee List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"pdfHtml5",text:"PDF",title:"Employee List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}},
            {extend:"print",text:"Print",title:"Employee List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6]}}
        ],

        ajax:function(data,callback){
            var params = new URLSearchParams();

            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            var branch = document.getElementById("branchFilter").value;
            var role = document.getElementById("roleFilter").value;
            var status = document.getElementById("statusFilter").value;

            if(branch !== "") params.set("branch_id",branch);
            if(role !== "") params.set("role_id",role);
            if(status !== "") params.set("status",status);

            if(data.order && data.order[0]){
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/employees.php?" + params.toString())
                .then(function(result){
                    listActions = (result.data.list_actions || []).map(Number);
                    formActions = (result.data.form_actions || []).map(Number);
                    current = result.data.current_user || {};

                    document.getElementById("addButton").style.display =
                        has(formActions,2)
                            ? "inline-flex"
                            : "none";

                    fillBranches(result.data.filter_branches || []);
                    fillRoles(result.data.filter_roles || []);
                    applyCurrentUser();
                    setSummary(result.data.summary);

                    AppDataTable.applyExportPermissions(
                        table,
                        listActions
                    );

                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,"Unable to load employees.");

                    callback({
                        draw:data.draw,
                        recordsTotal:0,
                        recordsFiltered:0,
                        data:[]
                    });
                });
        },

        columns:[
            {data:"employee_code",defaultContent:"-"},
            {data:"name",defaultContent:"-"},
            {
                data:null,
                render:function(data,type,row){
                    var value =
                        [row.company_name,row.branch_name]
                            .filter(Boolean)
                            .join(" — ") || "-";

                    return type === "display"
                        ? esc(value)
                        : value;
                }
            },
            {data:"role_name",defaultContent:"-"},
            {data:"username",defaultContent:"-"},
            {
                data:null,
                orderable:false,
                render:function(data,type,row){
                    var value =
                        [row.email,row.mobile]
                            .filter(Boolean)
                            .join(" · ") || "-";

                    return type === "display"
                        ? esc(value)
                        : value;
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

                    return has(formActions,3)
                        ? App.iconActionHtml({
                            href:row.edit_url,
                            icon:"pencil",
                            label:"Edit employee"
                        })
                        : '<span class="muted">View only</span>';
                }
            }
        ],

        language:{
            emptyTable:"No employees found.",
            zeroRecords:"No matching employees found.",
            processing:"Loading employees..."
        },

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearch(){
        var element = document.getElementById("employeeTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if(row) row.remove();
    })();

    var search = document.getElementById("employeeSearch");

    search.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(search.value.trim()).draw();
        },350);
    });

    document.getElementById("branchFilter").addEventListener("change",function(){
        document.getElementById("roleFilter").value = "";
        table.ajax.reload(null,true);
    });

    ["roleFilter","statusFilter"].forEach(function(id){
        document.getElementById(id).addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    if(window.lucide) window.lucide.createIcons();

})(window.jQuery,window,document);
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
