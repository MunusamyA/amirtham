<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Seller Type List';
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
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
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

<div class="page-head">
    <div>
        <h1>Seller Type List</h1>
        <p>Manage branch-wise Seller Types used in Product Sale Base Pricing.</p>
    </div>
    <a class="btn btn-primary" id="addButton" href="seller-type-form.php">
        <i data-lucide="plus"></i> Add Seller Type
    </a>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="users-round"></i></span>
        <div><div class="kpi-label">Total Seller Types</div><div class="kpi-value" id="kpiTotalSellerTypes">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div><div class="kpi-label">Active</div><div class="kpi-value" id="kpiActiveSellerTypes">0</div></div>
    </article>
    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div><div class="kpi-label">Inactive</div><div class="kpi-value" id="kpiInactiveSellerTypes">0</div></div>
    </article>
</div>


<div class="card form-card" style="margin-bottom:14px;">
    <div class="card-body">
        <div class="form-row">
            <div class="field col-6">
                <label for="searchInput">Search</label>
                <input id="searchInput" type="text" autocomplete="off" placeholder="Search Seller Type">
            </div>
            <div class="field col-3">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="field col-3" style="display:flex;align-items:flex-end;">
                <button class="btn gray" id="clearFilters" type="button" style="width:100%;">
                    <i data-lucide="rotate-ccw"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <table>
        <thead>
        <tr>
            <th>Seller Type</th>
            <th>Sort Order</th>
            <th>Product Prices</th>
            <th>Status</th>
            <th>Updated</th>
            <th>Manage</th>
        </tr>
        </thead>
        <tbody id="sellerTypeBody">
        <tr><td colspan="6" class="empty">Loading Seller Types...</td></tr>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:12px;">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div class="muted" id="paginationInfo">0 records</div>
        <div class="buttons">
            <button class="btn gray" id="prevButton" type="button"><i data-lucide="chevron-left"></i> Previous</button>
            <span class="muted" id="pageInfo">Page 1 of 1</span>
            <button class="btn gray" id="nextButton" type="button">Next <i data-lucide="chevron-right"></i></button>
        </div>
    </div>
</div>

<script>
(function (window, document) {
    "use strict";

    var body = document.getElementById("sellerTypeBody");
    var searchInput = document.getElementById("searchInput");
    var statusFilter = document.getElementById("statusFilter");
    var addButton = document.getElementById("addButton");
    var page = 1;
    var pages = 1;
    var perPage = 25;
    var allowedActions = [];
    var formActions = [];
    var searchTimer = null;

    function apiData(response) {
        if (!response || typeof response !== "object") return {};
        return response.data && typeof response.data === "object" ? response.data : response;
    }

    function hasAction(actionId) {
        return (allowedActions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function hasFormAction(actionId) {
        return (formActions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function escapeHtml(value) {
        if (window.App && typeof App.escapeHtml === "function") {
            return App.escapeHtml(String(value == null ? "" : value));
        }
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDateTime(value) {
        if (!value) return "-";
        var d = new Date(String(value).replace(" ", "T"));
        if (Number.isNaN(d.getTime())) return escapeHtml(value);
        return d.toLocaleString("en-IN");
    }

    function setSummary(summary) {
        summary = summary || {};
        document.getElementById("kpiTotalSellerTypes").textContent =
            Number(summary.total_seller_types || 0).toLocaleString("en-IN");
        document.getElementById("kpiActiveSellerTypes").textContent =
            Number(summary.active_seller_types || 0).toLocaleString("en-IN");
        document.getElementById("kpiInactiveSellerTypes").textContent =
            Number(summary.inactive_seller_types || 0).toLocaleString("en-IN");
    }

    function render(rows) {
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="empty">No Seller Types found.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            var active = Number(row.status) === 1;
            var manage = '';

            if (hasFormAction(3)) {
                manage += '<a class="table-icon-action" href="' + escapeHtml(row.edit_url) + '" title="Edit Seller Type" aria-label="Edit Seller Type"><i data-lucide="pencil"></i></a>';
            }
            if (active && hasAction(27)) {
                manage += '<button type="button" class="table-icon-action danger js-status" data-ref="' + escapeHtml(row.ref) + '" data-action="deactivate" title="Deactivate" aria-label="Deactivate"><i data-lucide="circle-off"></i></button>';
            }
            if (!active && hasAction(28)) {
                manage += '<button type="button" class="table-icon-action js-status" data-ref="' + escapeHtml(row.ref) + '" data-action="activate" title="Activate" aria-label="Activate"><i data-lucide="circle-check"></i></button>';
            }

            return '<tr>' +
                '<td><strong>' + escapeHtml(row.seller_type_name) + '</strong></td>' +
                '<td>' + Number(row.sort_order || 0) + '</td>' +
                '<td>' + Number(row.product_price_count || 0) + '</td>' +
                '<td><span class="badge ' + (active ? 'success' : 'gray') + '">' + (active ? 'Active' : 'Inactive') + '</span></td>' +
                '<td>' + formatDateTime(row.updated_at) + '</td>' +
                '<td class="table-action-icons">' + (manage || '-') + '</td>' +
            '</tr>';
        }).join('');

        if (window.lucide) window.lucide.createIcons();
    }

    async function load() {
        body.innerHTML = '<tr><td colspan="6" class="empty">Loading Seller Types...</td></tr>';
        try {
            var url = "api/seller-types.php?list=1&page=" + page + "&per_page=" + perPage;
            var search = String(searchInput.value || "").trim();
            var status = statusFilter.value;
            if (search) url += "&search=" + encodeURIComponent(search);
            if (status !== "") url += "&status=" + encodeURIComponent(status);

            var response = await App.api(url);
            var data = apiData(response);
            allowedActions = Array.isArray(data.allowed_actions) ? data.allowed_actions : [];
            formActions = Array.isArray(data.form_actions) ? data.form_actions : [];
            setSummary(data.summary);
            addButton.style.display = hasFormAction(2) ? "inline-flex" : "none";
            var pagination = data.pagination || {};
            page = Number(pagination.page || 1);
            pages = Number(pagination.pages || 1);

            render(Array.isArray(data.rows) ? data.rows : []);
            document.getElementById("paginationInfo").textContent = Number(pagination.total || 0) + " record" + (Number(pagination.total || 0) === 1 ? "" : "s");
            document.getElementById("pageInfo").textContent = "Page " + page + " of " + pages;
            document.getElementById("prevButton").disabled = page <= 1;
            document.getElementById("nextButton").disabled = page >= pages;

            // Create is controlled by Seller Type Form permission. Keep button visible; API will enforce permission.
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            setSummary({});
            body.innerHTML = '<tr><td colspan="6" class="empty">Unable to load Seller Types.</td></tr>';
            App.showError(error, "Unable to load Seller Types.");
        }
    }

    searchInput.addEventListener("input", function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            page = 1;
            load();
        }, 300);
    });

    statusFilter.addEventListener("change", function () {
        page = 1;
        load();
    });

    document.getElementById("clearFilters").addEventListener("click", function () {
        searchInput.value = "";
        statusFilter.value = "";
        page = 1;
        load();
    });

    document.getElementById("prevButton").addEventListener("click", function () {
        if (page > 1) {
            page--;
            load();
        }
    });

    document.getElementById("nextButton").addEventListener("click", function () {
        if (page < pages) {
            page++;
            load();
        }
    });

    body.addEventListener("click", async function (event) {
        var button = event.target.closest(".js-status");
        if (!button) return;

        var action = button.dataset.action || "";
        var payload = new FormData();
        payload.set("ref", button.dataset.ref || "");
        payload.set("action", action);
        payload.set("_method", "PATCH");

        button.disabled = true;
        try {
            var response = await App.api("api/seller-types.php", {
                method: "POST",
                body: payload
            });
            showToast((response && response.message) || "Seller Type status updated.", { type: "success", duration: 2 });
            await load();
        } catch (error) {
            App.showError(error, "Unable to update Seller Type status.");
            button.disabled = false;
        }
    });

    load();
})(window, document);
</script>

</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script src="assets/js/appearance.js"></script>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
