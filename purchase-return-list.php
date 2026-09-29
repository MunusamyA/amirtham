<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Purchase Return List';
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
                <script src="assets/js/global-select.js"></script>

                <div class="page-head">
                    <div>
                        <h1>Purchase Return List</h1>
                        <p>Draft and posted Purchase Returns linked to the original Purchase and Purchase Batch.</p>
                    </div>
                    <a class="btn btn-primary" id="addButton" href="purchase-return-form.php">
                        <i data-lucide="plus"></i>
                        Add Purchase Return
                    </a>
                </div>

                <div class="kpi-grid">
                    <article class="card kpi-card">
                        <span class="kpi-icon blue"><i data-lucide="rotate-ccw"></i></span>
                        <div>
                            <div class="kpi-label">Purchase Returns</div>
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
                        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
                        <div>
                            <div class="kpi-label">Posted</div>
                            <div class="kpi-value" id="kpiPosted">0</div>
                        </div>
                    </article>

                    <article class="card kpi-card">
                        <span class="kpi-icon orange"><i data-lucide="file-pen-line"></i></span>
                        <div>
                            <div class="kpi-label">Draft</div>
                            <div class="kpi-value" id="kpiDraft">0</div>
                        </div>
                    </article>
                </div>


                <div class="card table-card">
                    <div class="card-header">
                        <div class="form-row" style="width:100%;margin:0;">
                            <div class="field col-4">
                                <label for="returnSearch">Search</label>
                                <input id="returnSearch" type="text" placeholder="Search return, purchase, batch or supplier...">
                            </div>
                            <div class="field col-2">
                                <label for="postingFilter">Status</label>
                                <select id="postingFilter">
                                    <option value="">All</option>
                                    <option value="0">Draft</option>
                                    <option value="1">Posted</option>
                                </select>
                            </div>
                            <div class="field col-3">
                                <label for="dateFrom">Date From</label>
                                <input id="dateFrom" type="date">
                            </div>
                            <div class="field col-3">
                                <label for="dateTo">Date To</label>
                                <input id="dateTo" type="date">
                            </div>
                        </div>
                    </div>

                    <table id="returnTable" class="display data-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Return No</th>
                                <th>Return Date</th>
                                <th>Purchase No</th>
                                <th>Batch No</th>
                                <th>Supplier</th>
                                <th>Grand Total</th>
                                <th>Status</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <script>
                    (function($) {
                        'use strict';

                        if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

                        var listActions = [];
                        var formActions = [];
                        var searchTimer = null;
                        var postingGlobal = null;

                        function has(actions, id) {
                            return (actions || []).indexOf(Number(id)) !== -1;
                        }

                        function money(value) {
                            return '₹' + Number(value || 0).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }

                        function setSummary(summary) {
                            summary = summary || {};

                            document.getElementById('kpiReturnCount').textContent =
                                Number(summary.return_count || 0).toLocaleString('en-IN');

                            document.getElementById('kpiReturnTotal').textContent =
                                money(summary.return_total || 0);

                            document.getElementById('kpiPosted').textContent =
                                Number(summary.posted_count || 0).toLocaleString('en-IN');

                            document.getElementById('kpiDraft').textContent =
                                Number(summary.draft_count || 0).toLocaleString('en-IN');
                        }

                        function removeDefaultSearch() {
                            var tableElement = document.getElementById('returnTable');
                            var card = tableElement ? tableElement.closest('.table-card') : null;
                            var wrapper = document.getElementById('returnTable_wrapper');

                            [card, wrapper].forEach(function(root) {
                                if (!root) return;

                                root.querySelectorAll(
                                    '#returnTable_filter,.dataTables_filter,.app-table-search-row'
                                ).forEach(function(node) {
                                    node.remove();
                                });
                            });
                        }

                        if (window.GlobalSelect) {
                            postingGlobal = GlobalSelect.init(
                                document.getElementById('postingFilter'),
                                { placeholder: 'All' }
                            );
                        }

                        var table = AppDataTable.init('#returnTable', {
                            serverSide: true,
                            searching: true,
                            searchDelay: 300,
                            appSearch: false,
                            appSearchPlaceholder: 'Search Purchase Returns...',
                            pageLength: 10,
                            lengthMenu: [
                                [10, 25, 50, 100],
                                [10, 25, 50, 100]
                            ],
                            order: [
                                [1, 'desc']
                            ],
                            scrollX: true,
                            autoWidth: false,
                            buttons: [{
                                    extend: 'copyHtml5',
                                    text: 'Copy',
                                    title: 'Purchase Return List',
                                    action: AppDataTable.serverSideExportAction,
                                    exportOptions: {
                                        columns: [0, 1, 2, 3, 4, 5, 6]
                                    }
                                },
                                {
                                    extend: 'csvHtml5',
                                    text: 'CSV',
                                    title: 'Purchase Return List',
                                    action: AppDataTable.serverSideExportAction,
                                    exportOptions: {
                                        columns: [0, 1, 2, 3, 4, 5, 6]
                                    }
                                },
                                {
                                    extend: 'excelHtml5',
                                    text: 'Excel',
                                    title: 'Purchase Return List',
                                    action: AppDataTable.serverSideExportAction,
                                    exportOptions: {
                                        columns: [0, 1, 2, 3, 4, 5, 6]
                                    }
                                },
                                {
                                    extend: 'pdfHtml5',
                                    text: 'PDF',
                                    title: 'Purchase Return List',
                                    orientation: 'landscape',
                                    pageSize: 'A4',
                                    action: AppDataTable.serverSideExportAction,
                                    exportOptions: {
                                        columns: [0, 1, 2, 3, 4, 5, 6]
                                    }
                                },
                                {
                                    extend: 'print',
                                    text: 'Print',
                                    title: 'Purchase Return List',
                                    action: AppDataTable.serverSideExportAction,
                                    exportOptions: {
                                        columns: [0, 1, 2, 3, 4, 5, 6]
                                    }
                                }
                            ],
                            ajax: function(data, callback) {
                                var params = new URLSearchParams();
                                params.set('datatable', '1');
                                params.set('draw', data.draw);
                                params.set('start', data.start);
                                params.set('length', data.length);
                                params.set('search[value]', data.search.value || '');
                                params.set('posting_status', document.getElementById('postingFilter').value);
                                params.set('date_from', document.getElementById('dateFrom').value);
                                params.set('date_to', document.getElementById('dateTo').value);

                                if (data.order && data.order[0]) {
                                    params.set('order[0][column]', data.order[0].column);
                                    params.set('order[0][dir]', data.order[0].dir);
                                }

                                App.api('api/purchase-returns.php?' + params.toString())
                                    .then(function(result) {
                                        listActions = (result.data.allowed_actions || []).map(Number);
                                        formActions = (result.data.form_actions || []).map(Number);
                                        setSummary(result.data.summary);

                                        document.getElementById('addButton').style.display = has(formActions, 2) ?
                                            'inline-flex' :
                                            'none';

                                        AppDataTable.applyExportPermissions(table, listActions);
                                        callback(result.data.datatable);
                                    })
                                    .catch(function(errorObject) {
                                        setSummary({});
                                        App.showError(errorObject, 'Unable to load Purchase Returns.');
                                        callback({
                                            draw: data.draw,
                                            recordsTotal: 0,
                                            recordsFiltered: 0,
                                            data: []
                                        });
                                    });
                            },
                            columns: [{
                                    data: 'return_no',
                                    defaultContent: '-'
                                },
                                {
                                    data: 'return_date',
                                    defaultContent: '-'
                                },
                                {
                                    data: 'purchase_no',
                                    defaultContent: '-'
                                },
                                {
                                    data: 'batch_number',
                                    defaultContent: '-'
                                },
                                {
                                    data: 'supplier_name',
                                    defaultContent: '-'
                                },
                                {
                                    data: 'grand_total',
                                    className: 'dt-body-right',
                                    render: function(value, type) {
                                        return type === 'display' ? money(value) : Number(value || 0);
                                    }
                                },
                                {
                                    data: 'posting_status',
                                    render: function(value, type) {
                                        if (type !== 'display') return Number(value);
                                        return Number(value) === 1 ?
                                            '<span class="dt-status active">Posted</span>' :
                                            '<span class="dt-status inactive">Draft</span>';
                                    }
                                },
                                {
                                    data: null,
                                    orderable: false,
                                    searchable: false,
                                    className: 'table-action-icons',
                                    render: function(data, type, row) {
                                        if (type !== 'display') return '';

                                        if (Number(row.posting_status) === 0 && has(formActions, 3)) {
                                            return App.iconActionHtml({
                                                href: row.open_url,
                                                icon: 'pencil',
                                                label: 'Edit Purchase Return'
                                            });
                                        }

                                        return App.iconActionHtml({
                                            href: row.open_url,
                                            icon: 'eye',
                                            label: 'View Purchase Return'
                                        });
                                    }
                                }
                            ],
                            drawCallback: function() {
                                removeDefaultSearch();
                                if (window.lucide) window.lucide.createIcons();
                            }
                        });

                        /* Keep DataTables search engine enabled, but show only
                           the custom Search field in the filter header. */
                        removeDefaultSearch();

                        if (typeof MutationObserver !== 'undefined') {
                            var returnTableElement = document.getElementById('returnTable');
                            var returnCard = returnTableElement
                                ? returnTableElement.closest('.table-card')
                                : null;

                            if (returnCard) {
                                new MutationObserver(function() {
                                    removeDefaultSearch();
                                }).observe(
                                    returnCard,
                                    { childList: true, subtree: true }
                                );
                            }
                        }

                        var searchInput = document.getElementById('returnSearch');
                        searchInput.addEventListener('input', function() {
                            clearTimeout(searchTimer);
                            searchTimer = setTimeout(function() {
                                table.search(searchInput.value.trim()).draw();
                            }, 300);
                        });

                        document.getElementById('postingFilter').addEventListener('change', function() {
                            table.ajax.reload();
                        });
                        document.getElementById('dateFrom').addEventListener('change', function() {
                            table.ajax.reload();
                        });
                        document.getElementById('dateTo').addEventListener('change', function() {
                            table.ajax.reload();
                        });
                    })(jQuery);
                </script>
            </section>
            <?php require __DIR__ . '/include/footer.php'; ?>
        </main>
    </div>
    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>

</html>