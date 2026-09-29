<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Unit Master';
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
        <h1>Unit Master</h1>
        <p>Maintain reusable units. Product records store only Primary Unit ID and Secondary Unit ID.</p>
    </div>
    <button class="btn btn-primary" id="newUnit" type="button">
        <i data-lucide="plus"></i>Add Unit
    </button>
</div>

<?php require __DIR__ . '/modal/unit.php'; ?>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="ruler"></i></span>
        <div><div class="kpi-label">Total Units</div><div class="kpi-value" id="kpiTotal">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active Units</div><div class="kpi-value" id="kpiActive">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div><div class="kpi-label">Inactive Units</div><div class="kpi-value" id="kpiInactive">0</div></div>
    </article>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-8">
                <label for="unitSearch">Search</label>
                <input id="unitSearch" type="text" autocomplete="off" placeholder="Unit code, name or symbol...">
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
        <table id="unitTable" class="display data-table" style="width:100%">
            <thead>
            <tr>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Symbol</th>
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
    var newButton = document.getElementById("newUnit");
    var searchField = document.getElementById("unitSearch");
    var statusFilter = document.getElementById("statusFilter");
    var searchTimer = null;

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById("kpiTotal").textContent =
            Number(summary.total_units || 0).toLocaleString("en-IN");

        document.getElementById("kpiActive").textContent =
            Number(summary.active_units || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactive").textContent =
            Number(summary.inactive_units || 0).toLocaleString("en-IN");
    }

    function createUnit() {
        if (!window.AppUnitForm) {
            App.showError(null, "Reusable Unit modal is unavailable.");
            return;
        }

        AppUnitForm.openCreate({
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    function editUnit(id) {
        if (!window.AppUnitForm) {
            App.showError(null, "Reusable Unit modal is unavailable.");
            return;
        }

        AppUnitForm.openEdit(id, {
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    async function changeStatus(row) {
        var next = Number(row.status) === 1 ? 0 : 1;

        try {
            var result = await App.api("api/unit.php", {
                method: "PATCH",
                body: { id: Number(row.id), status: next }
            });

            showToast(result.message, { type: "success", duration: 2 });
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change Unit status.");
        }
    }

    var table = AppDataTable.init("#unitTable", {
        serverSide: true,
        searching: true,
        searchDelay: 300,
        appSearch: false,
        appLoaderText: "Loading Unit records...",
        pageLength: 25,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0,"asc"]],
        scrollX: true,
        autoWidth: false,

        buttons: [
            {extend:"copyHtml5",text:"Copy",title:"Unit Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3]}},
            {extend:"csvHtml5",text:"CSV",title:"Unit Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3]}},
            {extend:"excelHtml5",text:"Excel",title:"Unit Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3]}},
            {extend:"pdfHtml5",text:"PDF",title:"Unit Master",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3]}},
            {extend:"print",text:"Print",title:"Unit Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3]}}
        ],

        ajax: function (data, callback) {
            var params = new URLSearchParams();

            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");

            if (statusFilter.value !== "") params.set("status",statusFilter.value);

            if (data.order && data.order[0]) {
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }

            App.api("api/unit.php?" + params.toString()).then(function (result) {
                actions = (result.data.allowed_actions || []).map(Number);
                newButton.style.display = has(actions,2) ? "inline-flex" : "none";

                setSummary(result.data.summary);
                AppDataTable.applyExportPermissions(table, actions);
                callback(result.data.datatable);
            }).catch(function (error) {
                newButton.style.display = "none";
                setSummary({});
                App.showError(error, "Unable to load Unit records.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },

        columns: [
            {data:"unit_code"},
            {data:"unit_name",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"unit_symbol",render:function(v,t){return t==="display"?escapeHtml(v||"-"):v;}},
            {data:"status",render:function(v,t){if(t !== "display") return Number(v); return Number(v) === 1 ? '<span class="dt-status active">Active</span>' : '<span class="dt-status inactive">Inactive</span>';}},
            {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
                if (type !== "display") return "";

                var html = "";

                if (has(actions,3)) {
                    html += '<button type="button" class="table-icon-action js-edit" data-id="' + Number(row.id) + '" title="Edit Unit" aria-label="Edit Unit"><i data-lucide="pencil"></i></button>';
                }

                if (Number(row.status) === 1 && has(actions,28)) {
                    html += '<button type="button" class="table-icon-action danger js-status" title="Deactivate Unit" aria-label="Deactivate Unit"><i data-lucide="circle-off"></i></button>';
                }

                if (Number(row.status) === 0 && has(actions,27)) {
                    html += '<button type="button" class="table-icon-action success js-status" title="Activate Unit" aria-label="Activate Unit"><i data-lucide="circle-check"></i></button>';
                }

                return html || '<span class="muted">View only</span>';
            }}
        ],

        language:{
            emptyTable:"No Unit records found.",
            zeroRecords:"No matching Unit records found.",
            processing:"Loading Unit records..."
        },

        drawCallback:function(){
            if(window.lucide) window.lucide.createIcons();
        }
    });

    (function removeDefaultSearchRow() {
        var tableElement = document.getElementById("unitTable");
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

    statusFilter.addEventListener("change",function(){
        table.ajax.reload(null,true);
    });

    $(document).on("click", ".js-edit", function () {
        editUnit(Number(this.getAttribute("data-id")));
    });

    $(document).on("click", ".js-status", function () {
        var row = table.row($(this).closest("tr")).data();
        if (row) changeStatus(row);
    });

    newButton.addEventListener("click", createUnit);

})(window.jQuery, window);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
