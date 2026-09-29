<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Business List';

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
        <h1>Business List</h1>
        <p>Manage businesses and their branch coverage.</p>
    </div>

    <a class="btn btn-primary" id="addButton" href="business-form.php" style="display:none">
        <i data-lucide="plus"></i>Add Business
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="building-2"></i></span>
        <div>
            <div class="kpi-label">Total Businesses</div>
            <div class="kpi-value" id="kpiTotal">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active Businesses</div>
            <div class="kpi-value" id="kpiActive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive Businesses</div>
            <div class="kpi-value" id="kpiInactive">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="git-branch"></i></span>
        <div>
            <div class="kpi-label">Total Branches</div>
            <div class="kpi-value" id="kpiBranches">0</div>
        </div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-8">
                <label for="businessSearch">Search</label>
                <input id="businessSearch" type="text" autocomplete="off"
                       placeholder="Business, code, email, mobile...">
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
        <table id="businessTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Business</th>
                <th>Code</th>
                <th>Contact</th>
                <th>Branches</th>
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

    function setSummary(summary){
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_businesses || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_businesses || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_businesses || 0).toLocaleString("en-IN");

        document.getElementById("kpiBranches").textContent =
            Number(summary.total_branches || 0).toLocaleString("en-IN");
    }

    var table = AppDataTable.init("#businessTable",{
        serverSide:true,
        searching:true,
        searchDelay:350,
        appSearch:false,
        appLoaderText:"Loading businesses...",
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
            p.set("status",document.getElementById("statusFilter").value);

            if(data.order && data.order[0]){
                p.set("order[0][column]",data.order[0].column);
                p.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/businesses.php?" + p.toString())
                .then(function(result){
                    allowed = (result.data.allowed_actions || []).map(Number);
                    current = result.data.current_user || {};

                    document.getElementById("addButton").style.display =
                        has(allowed,2) && Number(current.role_type) === 2
                            ? "inline-flex"
                            : "none";

                    setSummary(result.data.summary);
                    callback(result.data.datatable);
                })
                .catch(function(error){
                    setSummary({});
                    App.showError(error,"Unable to load businesses.");

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
            {data:"company_code",defaultContent:"-"},
            {
                data:null,
                orderable:false,
                render:function(data,type,row){
                    var value = [row.email,row.mobile]
                        .filter(Boolean)
                        .join(" · ") || "-";

                    return type === "display"
                        ? esc(value)
                        : value;
                }
            },
            {
                data:"branch_count",
                className:"dt-body-right"
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

                    var html = [];

                    if(has(allowed,3)){
                        html.push(
                            '<a class="table-icon-action" href="business-form.php?id=' +
                            Number(row.id) +
                            '" title="Edit business" aria-label="Edit business">' +
                            '<i data-lucide="pencil"></i></a>'
                        );
                    }

                    if(
                        has(allowed,3) &&
                        Number(current.role_type) === 2
                    ){
                        html.push(
                            '<button type="button" class="table-icon-action js-business-status" ' +
                            'data-id="' + Number(row.id) + '" ' +
                            'data-name="' + esc(row.company_name) + '" ' +
                            'data-status="' + Number(row.status) + '" ' +
                            'title="' + (Number(row.status) === 1 ? "Deactivate business" : "Activate business") + '">' +
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
            emptyTable:"No businesses found.",
            zeroRecords:"No matching businesses found.",
            processing:"Loading businesses..."
        }
    });

    (function removeDefaultSearch(){
        var element = document.getElementById("businessTable");
        var card = element ? element.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if(row) row.remove();
    })();

    var search = document.getElementById("businessSearch");

    search.addEventListener("input",function(){
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function(){
            table.search(search.value.trim()).draw();
        },350);
    });

    document.getElementById("statusFilter").addEventListener("change",function(){
        table.ajax.reload(null,true);
    });

    $("#businessTable").on("click",".js-business-status",async function(){
        var button = this;
        var id = Number(button.dataset.id || 0);
        var name = button.dataset.name || "Business";
        var next = Number(button.dataset.status) === 1 ? 0 : 1;

        if(!id) return;

        if(!confirm((next ? "Activate " : "Deactivate ") + '"' + name + '"?')){
            return;
        }

        button.disabled = true;

        try{
            var result = await App.api("api/businesses.php",{
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
            App.showError(error,"Unable to update business status.");
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
