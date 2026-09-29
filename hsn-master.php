<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'HSN Master';
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
        <h1>HSN Master</h1>
        <p>Maintain HSN codes and GST rates once, then reuse them anywhere HSN selection is required.</p>
    </div>
    <button class="btn btn-primary" id="newHsn" type="button"><i data-lucide="plus"></i>Add HSN</button>
</div>

<?php require __DIR__ . '/include/forms/hsn-form.php'; ?>

<div class="card table-card">
    <table id="hsnTable" class="display data-table" style="width:100%">
        <thead><tr>
            <th>HSN Code</th><th>Description</th><th>GST %</th><th>CGST %</th><th>SGST %</th><th>IGST %</th><th>Cess %</th><th>Status</th><th>Manage</th>
        </tr></thead>
    </table>
</div>

<script>
(function ($, window) {
    "use strict";
    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var actions = [];
    var has = AppDataTable.has;
    var newButton = document.getElementById("newHsn");
    var statusFilterValue = "";

    function formatRate(value) {
        return Number(value || 0).toFixed(2);
    }

    function createHsn() {
        if (!window.AppHSNForm) {
            App.showError(null, "Reusable HSN form component is unavailable.");
            return;
        }
        AppHSNForm.openCreate({
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    function editHsn(id) {
        if (!window.AppHSNForm) {
            App.showError(null, "Reusable HSN form component is unavailable.");
            return;
        }
        AppHSNForm.openEdit(id, {
            onSaved: function () {
                table.ajax.reload(null, false);
            }
        });
    }

    async function changeStatus(row) {
        var next = Number(row.status) === 1 ? 0 : 1;
        try {
            var result = await App.api("api/hsn.php", {
                method: "PATCH",
                body: { id: Number(row.id), status: next }
            });
            showToast(result.message, { type: "success", duration: 2 });
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change HSN status.");
        }
    }

    var table = AppDataTable.init("#hsnTable", {
        serverSide: true,
        searching: true,
        searchDelay: 300,
        appSearchPlaceholder: "Search HSN code or description...",
        appLoaderText: "Loading HSN records...",
        pageLength: 25,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0,"asc"]],
        scrollX: true,
        autoWidth: false,
        buttons: [
            {extend:"copyHtml5",text:"Copy",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"csvHtml5",text:"CSV",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"excelHtml5",text:"Excel",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"pdfHtml5",text:"PDF",title:"HSN Master",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}},
            {extend:"print",text:"Print",title:"HSN Master",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7]}}
        ],
        ajax: function (data, callback) {
            var params = new URLSearchParams();
            params.set("datatable","1");
            params.set("draw",data.draw);
            params.set("start",data.start);
            params.set("length",data.length);
            params.set("search[value]",data.search.value || "");
            if (statusFilterValue !== "") params.set("status",statusFilterValue);
            if (data.order && data.order[0]) {
                params.set("order[0][column]",data.order[0].column);
                params.set("order[0][dir]",data.order[0].dir);
            }
            App.api("api/hsn.php?" + params.toString()).then(function (result) {
                actions = (result.data.allowed_actions || []).map(Number);
                newButton.style.display = has(actions,2) ? "inline-flex" : "none";
                AppDataTable.applyExportPermissions(table, actions);
                callback(result.data.datatable);
            }).catch(function (error) {
                App.showError(error, "Unable to load HSN records.");
                callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns: [
            {data:"hsn_code"},
            {data:"description",defaultContent:"-",orderable:false},
            {data:"gst_rate",render:function(v,t){return t === "display" ? formatRate(v) : Number(v);}},
            {data:"cgst_rate",render:function(v,t){return t === "display" ? formatRate(v) : Number(v);}},
            {data:"sgst_rate",render:function(v,t){return t === "display" ? formatRate(v) : Number(v);}},
            {data:"igst_rate",render:function(v,t){return t === "display" ? formatRate(v) : Number(v);}},
            {data:"cess_rate",render:function(v,t){return t === "display" ? formatRate(v) : Number(v);}},
            {data:"status",render:function(v,t){if(t !== "display") return Number(v); return Number(v) === 1 ? '<span class="dt-status active">Active</span>' : '<span class="dt-status inactive">Inactive</span>'; }},
            {data:null,orderable:false,searchable:false,className:"table-action-icons",render:function(data,type,row){
                if (type !== "display") return "";
                var html = "";
                if (has(actions,3)) html += '<button type="button" class="table-icon-action js-edit" data-id="' + row.id + '" title="Edit HSN" aria-label="Edit HSN"><i data-lucide="pencil"></i></button>';
                if (Number(row.status) === 1 && has(actions,28)) html += '<button type="button" class="table-icon-action danger js-status" data-id="' + row.id + '" title="Deactivate HSN" aria-label="Deactivate HSN"><i data-lucide="circle-off"></i></button>';
                if (Number(row.status) === 0 && has(actions,27)) html += '<button type="button" class="table-icon-action success js-status" data-id="' + row.id + '" title="Activate HSN" aria-label="Activate HSN"><i data-lucide="circle-check"></i></button>';
                return html || '<span class="muted">View only</span>';
            }}
        ],
        language:{emptyTable:"No HSN records found.",zeroRecords:"No matching HSN records found."}
    });

    AppDataTable.addFilter(table, {
        label:"Status",
        options:[{value:"",label:"All"},{value:"1",label:"Active"},{value:"0",label:"Inactive"}],
        onChange:function(value){statusFilterValue=value; table.ajax.reload();}
    });

    $(document).on("click", ".js-edit", function () {
        editHsn(Number(this.getAttribute("data-id")));
    });
    $(document).on("click", ".js-status", function () {
        var row = table.row($(this).closest("tr")).data();
        if (row) changeStatus(row);
    });
    newButton.addEventListener("click", createHsn);
})(window.jQuery, window);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
</body>
</html>
