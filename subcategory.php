<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Subcategory Master';
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
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ($headStyles as $styleUrl): ?><link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ($headScripts as $scriptUrl): ?><script src="<?php echo web_h($scriptUrl); ?>"></script><?php endforeach; ?>
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
<script src="assets/js/validation.js"></script>

<div class="page-head">
    <div>
        <h1>Subcategory Master</h1>
        <p>Maintain Food Supplementary subcategories under the selected parent Category.</p>
    </div>
    <button class="btn btn-primary" id="newSubcategory" type="button">
        <i data-lucide="plus"></i>Add Subcategory
    </button>
</div>

<?php require __DIR__ . '/modal/subcategory.php'; ?>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="list-tree"></i></span>
        <div><div class="kpi-label">Total Subcategories</div><div class="kpi-value" id="kpiTotal">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active Subcategories</div><div class="kpi-value" id="kpiActive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div><div class="kpi-label">Inactive Subcategories</div><div class="kpi-value" id="kpiInactive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="folder-tree"></i></span>
        <div><div class="kpi-label">Categories</div><div class="kpi-value" id="kpiCategories">0</div></div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-4">
                <label for="subcategorySearch">Search</label>
                <input id="subcategorySearch" type="text" autocomplete="off" placeholder="Code, subcategory or category...">
            </div>

            <div class="field col-4">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
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
        <table id="subcategoryTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Subcategory Code</th>
                <th>Subcategory Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Status</th>
                <th>Manage</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
(function ($, window) {
    "use strict";

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var actions = [];
    var has = AppDataTable.has;
    var newButton = document.getElementById("newSubcategory");
    var searchField = document.getElementById("subcategorySearch");
    var categoryFilter = document.getElementById("categoryFilter");
    var statusFilter = document.getElementById("statusFilter");
    var searchTimer = null;
    var categoriesLoaded = false;

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_subcategories || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_subcategories || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_subcategories || 0).toLocaleString("en-IN");

        document.getElementById("kpiCategories").textContent =
            Number(summary.category_count || 0).toLocaleString("en-IN");
    }

    function fillCategories(rows) {
        if (categoriesLoaded) return;
        categoriesLoaded = true;

        (rows || []).forEach(function (row) {
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = row.category_name;
            categoryFilter.appendChild(option);
        });
    }

    function createSubcategory() {
        if (!window.AppSubcategoryForm) {
            App.showError(null, "Reusable Subcategory modal is unavailable.");
            return;
        }

        AppSubcategoryForm.openCreate({
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    function editSubcategory(id) {
        if (!window.AppSubcategoryForm) {
            App.showError(null, "Reusable Subcategory modal is unavailable.");
            return;
        }

        AppSubcategoryForm.openEdit(id, {
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    async function changeStatus(row) {
        var next = Number(row.status) === 1 ? 0 : 1;

        try {
            var result = await App.api("api/subcategory.php", {
                method: "PATCH",
                body: { id: Number(row.id), status: next }
            });

            showToast(result.message, { type: "success", duration: 2 });
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change Subcategory status.");
        }
    }

    var table = AppDataTable.init("#subcategoryTable", {
        serverSide: true,
        searching: true,
        searchDelay: 300,
        appSearch: false,
        appLoaderText: "Loading subcategory records...",
        pageLength: 25,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0,"asc"]],
        scrollX: true,
        autoWidth: false,

        buttons: [
            {extend:"copyHtml5",text:"Copy",title:"Subcategory Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4]}},
            {extend:"csvHtml5",text:"CSV",title:"Subcategory Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4]}},
            {extend:"excelHtml5",text:"Excel",title:"Subcategory Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4]}},
            {extend:"pdfHtml5",text:"PDF",title:"Subcategory Master",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4]}},
            {extend:"print",text:"Print",title:"Subcategory Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4]}}
        ],

        ajax: function (data, callback) {
            var params = new URLSearchParams();

            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            if (categoryFilter.value !== "") params.set("category_id",categoryFilter.value);
            if (statusFilter.value !== "") params.set("status",statusFilter.value);

            if (data.order && data.order[0]) {
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/subcategory.php?" + params.toString()).then(function (result) {
                actions = (result.data.allowed_actions || []).map(Number);
                newButton.style.display = has(actions,2) ? "inline-flex" : "none";

                fillCategories(result.data.filter_categories || []);
                setSummary(result.data.summary);
                AppDataTable.applyExportPermissions(table, actions);
                callback(result.data.datatable);
            }).catch(function (error) {
                newButton.style.display = "none";
                setSummary({});
                App.showError(error, "Unable to load Subcategory records.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },

        columns: [
            {data:"category_code"},
            {data:"category_name",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"parent_category_name",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"description",defaultContent:"-",orderable:false,render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"status",render:function(v,t){if(t !== "display") return Number(v); return Number(v) === 1 ? '<span class="dt-status active">Active</span>' : '<span class="dt-status inactive">Inactive</span>';}},
            {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
                if (type !== "display") return "";

                var html = "";

                if (has(actions,3)) {
                    html += '<button type="button" class="table-icon-action js-edit" data-id="' + Number(row.id) + '" title="Edit Subcategory" aria-label="Edit Subcategory"><i data-lucide="pencil"></i></button>';
                }

                if (Number(row.status) === 1 && has(actions,28)) {
                    html += '<button type="button" class="table-icon-action danger js-status" title="Deactivate Subcategory" aria-label="Deactivate Subcategory"><i data-lucide="circle-off"></i></button>';
                }

                if (Number(row.status) === 0 && has(actions,27)) {
                    html += '<button type="button" class="table-icon-action success js-status" title="Activate Subcategory" aria-label="Activate Subcategory"><i data-lucide="circle-check"></i></button>';
                }

                return html || '<span class="muted">View only</span>';
            }}
        ],

        language:{
            emptyTable:"No Subcategory records found.",
            zeroRecords:"No matching Subcategory records found.",
            processing:"Loading Subcategory records..."
        },

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearchRow() {
        var tableElement = document.getElementById("subcategoryTable");
        var card = tableElement ? tableElement.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;
        if (row) row.remove();
    })();

    searchField.addEventListener("input",function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){
            table.search(searchField.value.trim()).draw();
        },300);
    });

    [categoryFilter,statusFilter].forEach(function(field){
        field.addEventListener("change",function(){
            table.ajax.reload(null,true);
        });
    });

    $(document).on("click", ".js-edit", function () {
        editSubcategory(Number(this.getAttribute("data-id")));
    });

    $(document).on("click", ".js-status", function () {
        var row = table.row($(this).closest("tr")).data();
        if (row) changeStatus(row);
    });

    newButton.addEventListener("click", createSubcategory);

})(window.jQuery, window);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
