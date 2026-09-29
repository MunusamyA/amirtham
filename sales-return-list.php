        <?php
        require_once __DIR__ . '/include/web-config.php';
        $pageTitle = 'Sales Return List';
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
            <?php foreach ($headStyles as $styleUrl): ?>
            <link rel="stylesheet" href="<?php echo web_h($styleUrl); ?>">
            <?php endforeach; ?>
            <link rel="stylesheet" href="assets/css/core.css">
            <link rel="stylesheet" href="assets/css/components.css">
            <link rel="stylesheet" href="assets/css/theme.css">
<style>
/* Use only the custom Search field in the Sales Return filter row. */
#returnTable_filter,
#returnTable_wrapper .dataTables_filter,
#returnTable_wrapper .app-table-search-row,
.table-card .dataTables_filter,
.table-card .app-table-search-row {
    display: none !important;
}
</style>
            <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
            <?php foreach ($headScripts as $scriptUrl): ?>
            <script src="<?php echo web_h($scriptUrl); ?>"></script>
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
                <h1>Sales Return List</h1>
                <p>Posted Sales Returns, refunds and customer credit generated.</p>
            </div>
            <a class="btn btn-primary" id="addButton" href="sales-return.php" style="display:none">
                <i data-lucide="rotate-ccw"></i>Add Sales Return
            </a>
        </div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="rotate-ccw"></i></span>
        <div>
            <div class="kpi-label">Sales Returns</div>
            <div class="kpi-value" id="kpiReturnCount">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="indian-rupee"></i></span>
        <div>
            <div class="kpi-label">Return Total</div>
            <div class="kpi-value" id="kpiReturnTotal">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="hand-coins"></i></span>
        <div>
            <div class="kpi-label">Refund</div>
            <div class="kpi-value" id="kpiRefund">₹0.00</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="wallet-cards"></i></span>
        <div>
            <div class="kpi-label">Customer Credit</div>
            <div class="kpi-value" id="kpiCredit">₹0.00</div>
        </div>
    </article>
</div>


        <div class="card table-card">
            <div class="card-header">
                <div class="form-row">
                    <div class="field col-4">
                        <label for="returnSearch">Search</label>
                        <input id="returnSearch" type="text" autocomplete="off" placeholder="Search sales returns...">
                    </div>

                    <div class="field col-2">
                        <label for="settlementTypeFilter">Settlement</label>
                        <select id="settlementTypeFilter">
                            <option value="">All</option>
                            <option value="1">Customer Credit</option>
                            <option value="2">Refund</option>
                            <option value="3">Credit + Refund</option>
                        </select>
                    </div>

                    <div class="field col-3">
                        <label for="dateFromFilter">From Date</label>
                        <input id="dateFromFilter" type="date">
                    </div>

                    <div class="field col-3">
                        <label for="dateToFilter">To Date</label>
                        <input id="dateToFilter" type="date">
                    </div>
                </div>
            </div>

            <table id="returnTable" class="display data-table" style="width:100%">
                <thead>
                <tr>
                    <th>Return No</th>
                    <th>Date</th>
                    <th>Final Invoice</th>
                    <th>Customer</th>
                    <th>Return Total</th>
                    <th>Refund</th>
                    <th>Stored in Credit</th>
                    <th>Stock Return</th>
                    <th>Settlement</th>
                    <th>Manage</th>
                </tr>
                </thead>
            </table>
        </div>

        <script>
        (function ($, window, document) {
            "use strict";
            if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

            var listActions = [];
            var formActions = [];
            var searchTimer = null;
            var returnSearch = document.getElementById("returnSearch");
            var settlementTypeFilter = document.getElementById("settlementTypeFilter");
            var dateFromFilter = document.getElementById("dateFromFilter");
            var dateToFilter = document.getElementById("dateToFilter");
            var addButton = document.getElementById("addButton");
            var has = AppDataTable.has;
            var ACTION_VIEW = 1;
            var ACTION_UPDATE = 3;
            var ACTION_DELETE = 4;
            var ACTION_RETURN = 32;

            function escapeHtml(value) {
                return String(value === null || value === undefined ? "" : value)
                    .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
                    .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
            }

            function money(value) {
                var number = Number(value || 0);
                if (!Number.isFinite(number)) number = 0;
                return "₹" + number.toLocaleString("en-IN", {minimumFractionDigits:2,maximumFractionDigits:2});
            }

            function stockStatus(row, type) {
                var items = Number(row.item_count || 0);
                var stockItems = Number(row.stock_item_count || 0);
                if (type !== "display") return stockItems;
                if (items <= 0) return '<span class="badge rounded-pill badge-soft-muted">-</span>';
                if (stockItems <= 0) return '<span class="badge rounded-pill badge-soft-danger">No Stock Added</span>';
                if (stockItems >= items) return '<span class="badge rounded-pill badge-soft-success">All Added</span>';
                return '<span class="badge rounded-pill badge-soft-warning">Partial ' + escapeHtml(stockItems + "/" + items) + '</span>';
            }

            function settlementStatus(row, type) {
                if (type !== "display") return Number(row.settlement_type || 0);
                var settlementType = Number(row.settlement_type || 0);
                var cls = settlementType === 2 ? "badge-soft-primary" : (settlementType === 3 ? "badge-soft-warning" : "badge-soft-success");
                return '<span class="badge rounded-pill ' + cls + '">' + escapeHtml(row.settlement_label || "-") + '</span>';
            }

            function setSummary(summary) {
                summary = summary || {};

                document.getElementById("kpiReturnCount").textContent =
                    Number(summary.return_count || 0).toLocaleString("en-IN");

                document.getElementById("kpiReturnTotal").textContent =
                    money(summary.return_total || 0);

                document.getElementById("kpiRefund").textContent =
                    money(summary.refund_total || 0);

                document.getElementById("kpiCredit").textContent =
                    money(summary.credit_total || 0);
            }

            function removeDefaultSearch() {
                var tableElement=document.getElementById("returnTable");
                var card=tableElement?tableElement.closest(".table-card"):null;
                var wrapper=document.getElementById("returnTable_wrapper");

                [card,wrapper].forEach(function(root){
                    if(!root)return;

                    root.querySelectorAll(
                        "#returnTable_filter,.dataTables_filter,.app-table-search-row"
                    ).forEach(function(node){
                        node.remove();
                    });
                });
            }

            var table = AppDataTable.init("#returnTable", {
                serverSide: true,
                searching: true,
                searchDelay: 350,
                appSearch: false,
                appSearchPlaceholder: "Search Sales Returns...",
                appLoaderText: "Loading Sales Returns...",
                pageLength: 10,
                lengthMenu: [[10,25,50,100],[10,25,50,100]],
                order: [[1,"desc"]],
                scrollX: true,
                autoWidth: false,
                buttons: [
                    {extend:"copyHtml5",text:"Copy",title:"Sales Return List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
                    {extend:"csvHtml5",text:"CSV",title:"Sales Return List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
                    {extend:"excelHtml5",text:"Excel",title:"Sales Return List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
                    {extend:"pdfHtml5",text:"PDF",title:"Sales Return List",orientation:"landscape",pageSize:"A4",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}},
                    {extend:"print",text:"Print",title:"Sales Return List",action:AppDataTable.serverSideExportAction,exportOptions:{columns:[0,1,2,3,4,5,6,7,8]}}
                ],
                ajax: function (data, callback) {
                    var params = new URLSearchParams();
                    params.set("datatable", "1");
                    params.set("draw", data.draw);
                    params.set("start", data.start);
                    params.set("length", data.length);
                    params.set("search[value]", data.search.value || "");
                    if (settlementTypeFilter.value !== "") params.set("settlement_type", settlementTypeFilter.value);
                    if (dateFromFilter.value) params.set("date_from", dateFromFilter.value);
                    if (dateToFilter.value) params.set("date_to", dateToFilter.value);
                    if (data.order && data.order[0]) {
                        params.set("order[0][column]", data.order[0].column);
                        params.set("order[0][dir]", data.order[0].dir);
                    }

                    App.api("api/sales-returns.php?" + params.toString()).then(function (result) {
                        listActions = (result.data.allowed_actions || []).map(Number);
                        formActions = (result.data.form_actions || []).map(Number);
                        setSummary(result.data.summary);
                        addButton.style.display = has(formActions, ACTION_RETURN) ? "inline-flex" : "none";
                        AppDataTable.applyExportPermissions(table, listActions);
                        callback(result.data.datatable);
                    }).catch(function (error) {
                        setSummary({});
                        addButton.style.display = "none";
                        App.showError(error, "Unable to load Sales Returns.");
                        callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]});
                    });
                },
                columns: [
                    {data:"return_no",defaultContent:"-"},
                    {data:"return_date",defaultContent:"-"},
                    {data:"sales_no",defaultContent:"-"},
                    {
                        data:null,
                        render:function(data,type,row){
                            var name=row.customer_name || "Walk-in Customer";
                            var code=row.customer_code || "";
                            var text=code ? code+" - "+name : name;
                            return type==="display" ? escapeHtml(text) : text;
                        }
                    },
                    {data:"grand_total",className:"dt-body-right",render:function(value,type){var text=money(value);return type==="display"?escapeHtml(text):Number(value||0);}},
                    {data:"refund_amount",className:"dt-body-right",render:function(value,type){var amount=Number(value||0);if(type!=="display")return amount;return amount>0?'<span class="badge rounded-pill badge-soft-primary">'+escapeHtml(money(amount))+'</span>':escapeHtml(money(0));}},
                    {data:"credit_generated",className:"dt-body-right",render:function(value,type){var amount=Number(value||0);if(type!=="display")return amount;return amount>0?'<span class="badge rounded-pill badge-soft-success">'+escapeHtml(money(amount))+'</span>':escapeHtml(money(0));}},
                    {data:null,className:"dt-body-center",render:function(data,type,row){return stockStatus(row,type);}},
                    {data:"settlement_label",render:function(value,type,row){return settlementStatus(row,type);}},
                    {
                        data:null,orderable:false,searchable:false,className:"table-action-icons",
                        render:function(data,type,row){
                            if(type!=="display") return "";
                            var html="";
                            if(has(formActions,ACTION_VIEW)) {
                                html += App.iconActionHtml({href:row.view_url,icon:"eye",label:"View Sales Return"});
                            }
                            if(has(formActions,ACTION_UPDATE)) {
                                html += App.iconActionHtml({href:row.edit_url,icon:"pencil",label:"Edit Sales Return"});
                            }
                            if(has(formActions,ACTION_DELETE)) {
                                html += '<button type="button" class="table-icon-action danger js-delete-return" data-ref="'+escapeHtml(row.ref)+'" title="Delete Sales Return" aria-label="Delete Sales Return"><i data-lucide="trash-2"></i></button>';
                            }
                            return html || '<span class="muted">View only</span>';
                        }
                    }
                ],
                language:{emptyTable:"No Sales Returns found.",zeroRecords:"No matching Sales Returns found."},
                drawCallback:function(){
                    removeDefaultSearch();
                    if(window.lucide)window.lucide.createIcons();
                }
            });

            removeDefaultSearch();

            if(typeof MutationObserver!=="undefined"){
                var returnTableElement=document.getElementById("returnTable");
                var returnCard=returnTableElement?returnTableElement.closest(".table-card"):null;

                if(returnCard){
                    new MutationObserver(function(){
                        removeDefaultSearch();
                    }).observe(returnCard,{childList:true,subtree:true});
                }
            }

            returnSearch.addEventListener("input",function(){
                window.clearTimeout(searchTimer);
                searchTimer=window.setTimeout(function(){table.search(returnSearch.value.trim()).draw();},350);
            });
            settlementTypeFilter.addEventListener("change",function(){table.ajax.reload(null,true);});
            dateFromFilter.addEventListener("change",function(){table.ajax.reload(null,true);});
            dateToFilter.addEventListener("change",function(){table.ajax.reload(null,true);});

            document.addEventListener("click",function(event){
                var button=event.target.closest(".js-delete-return");
                if(!button || !has(formActions,ACTION_DELETE)) return;
                if(!window.confirm("Delete this Sales Return? Stock, allocation, refund and customer-credit effects will be reversed internally.")) return;
                button.disabled=true;
                App.api("api/sales-returns.php",{method:"DELETE",body:{ref:button.getAttribute("data-ref")}})
                    .then(function(result){
                        if(window.showToast)showToast(result.message||"Sales Return deleted successfully.",{type:"success",duration:2});
                        table.ajax.reload(null,false);
                    })
                    .catch(function(error){App.showError(error,"Unable to delete Sales Return.");})
                    .finally(function(){button.disabled=false;});
            });
        })(window.jQuery,window,document);
        </script>
        </section>
        <?php require __DIR__ . '/include/footer.php'; ?>
        </main>
        </div>
        <script>if(window.lucide){window.lucide.createIcons();}</script>
        <script src="assets/js/appearance.js"></script>
        </body>
        </html>
