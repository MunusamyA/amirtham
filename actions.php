<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Action Master';
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
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ((isset($headStyles) && is_array($headStyles) ? $headStyles : []) as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ((isset($headScripts) && is_array($headScripts) ? $headScripts : []) as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars((string)$scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
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
        <h1>Permission Action Master</h1>
        <p>One numeric action ID is reused across every module. New actions receive the next ID automatically.</p>
    </div>
    <button class="btn btn-primary" id="newAction" type="button"><i data-lucide="plus"></i>Add Action</button>
</div>

<div class="card form-card hidden" id="actionEditor" style="margin-bottom:14px">
    <form id="actionForm" novalidate>
        <input type="hidden" name="id" value="">
        <div class="card-header">
            <div><h2 id="editorTitle">Add Action</h2><p id="actionIdLabel">ID will be generated automatically.</p></div>
            <button class="btn gray small" id="closeEditor" type="button">Close</button>
        </div>
        <div class="card-body"><div class="form-grid">
            <div class="field">
                <label>Action name</label>
                <input name="action_name" maxlength="120" required data-required-message="Action name is required." placeholder="Example: Generate QR">
            </div>
            <div class="field">
                <label>Action group</label>
                <select name="group_id" id="groupSelect" required data-required-message="Action group is required."></select>
            </div>
            <div class="field">
                <label>Sort order</label>
                <input name="sort_order" type="number" min="0" step="1" value="0" data-validation="integer">
            </div>
            <div class="field full">
                <label>Purpose</label>
                <textarea name="purpose" maxlength="255" rows="3" placeholder="Describe exactly what this action controls."></textarea>
            </div>
        </div></div>
        <div class="card-footer"><div class="buttons">
            <button class="btn btn-primary" id="saveAction" type="submit">Save Action</button>
            <button class="btn gray" id="cancelAction" type="button">Cancel</button>
        </div></div>
    </form>
</div>

<div class="kpi-grid">
    <article class="card kpi-card">
        <span class="kpi-icon blue"><i data-lucide="list-checks"></i></span>
        <div>
            <div class="kpi-label">Total Actions</div>
            <div class="kpi-value" id="kpiTotalActions">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon green"><i data-lucide="circle-check-big"></i></span>
        <div>
            <div class="kpi-label">Active Actions</div>
            <div class="kpi-value" id="kpiActiveActions">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon orange"><i data-lucide="circle-off"></i></span>
        <div>
            <div class="kpi-label">Inactive Actions</div>
            <div class="kpi-value" id="kpiInactiveActions">0</div>
        </div>
    </article>

    <article class="card kpi-card">
        <span class="kpi-icon teal"><i data-lucide="lock-keyhole"></i></span>
        <div>
            <div class="kpi-label">Built-in Actions</div>
            <div class="kpi-value" id="kpiCoreActions">0</div>
        </div>
    </article>
</div>

<div class="card table-card" style="overflow:hidden">
    <div class="card-header">
        <div class="form-row" style="width:100%;margin:0;">
            <div class="field col-4">
                <label for="actionSearch">Search</label>
                <input id="actionSearch" type="text" autocomplete="off"
                       placeholder="ID, action name, purpose...">
            </div>

            <div class="field col-4">
                <label for="groupFilter">Action Group</label>
                <select id="groupFilter">
                    <option value="">All Groups</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="actionStatusFilter">Status</label>
                <select id="actionStatusFilter">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    <table id="actionTable" class="display data-table" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Action</th>
                <th>Group</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Sort</th>
                <th>Manage</th>
            </tr>
        </thead>
    </table>
</div>
<script src="assets/js/validation.js"></script>
<script>
(function ($, window, document) {
    "use strict";
    var allowed = [];
    var groups = {};
    var form = document.getElementById("actionForm");
    var editor = document.getElementById("actionEditor");
    var newButton = document.getElementById("newAction");
    var saveButton = document.getElementById("saveAction");
    var actionSearch = document.getElementById("actionSearch");
    var groupFilter = document.getElementById("groupFilter");
    var actionStatusFilter = document.getElementById("actionStatusFilter");
    var searchTimer = null;
    var filterGroupsLoaded = false;

    function has(id) {
        return allowed.map(Number).indexOf(Number(id)) !== -1;
    }

    function fillGroups() {
        var select = document.getElementById("groupSelect");
        select.innerHTML = '<option value="">Select group</option>';

        Object.keys(groups).forEach(function (id) {
            var option = document.createElement("option");
            option.value = id;
            option.textContent = id + " - " + groups[id];
            select.appendChild(option);
        });

        if (!filterGroupsLoaded) {
            filterGroupsLoaded = true;
            groupFilter.innerHTML = '<option value="">All Groups</option>';

            Object.keys(groups).forEach(function (id) {
                var option = document.createElement("option");
                option.value = id;
                option.textContent = id + " - " + groups[id];
                groupFilter.appendChild(option);
            });
        }
    }

    function setSummary(summary) {
        summary = summary || {};

        document.getElementById("kpiTotalActions").textContent =
            Number(summary.total_actions || 0).toLocaleString("en-IN");

        document.getElementById("kpiActiveActions").textContent =
            Number(summary.active_actions || 0).toLocaleString("en-IN");

        document.getElementById("kpiInactiveActions").textContent =
            Number(summary.inactive_actions || 0).toLocaleString("en-IN");

        document.getElementById("kpiCoreActions").textContent =
            Number(summary.core_actions || 0).toLocaleString("en-IN");
    }

    function openNew() {
        form.reset();
        form.id.value = "";
        form.sort_order.value = "0";
        document.getElementById("editorTitle").textContent = "Add Action";
        document.getElementById("actionIdLabel").textContent = "ID will be generated automatically.";
        editor.classList.remove("hidden");
        form.action_name.focus();
    }

    function closeEditor() {
        editor.classList.add("hidden");
        Validation.clearForm(form);
    }

    async function editAction(id) {
        try {
            var result = await App.api("api/actions.php?id=" + Number(id));
            groups = result.data.groups || groups;
            fillGroups();
            var action = result.data.action;
            form.id.value = action.id;
            form.action_name.value = action.action_name || "";
            form.group_id.value = String(action.group_id || "");
            form.sort_order.value = String(action.sort_order || 0);
            form.purpose.value = action.purpose || "";
            document.getElementById("editorTitle").textContent = "Edit Action";
            document.getElementById("actionIdLabel").textContent = "Action ID: " + action.id + (action.is_core ? " (built-in ID - never reused)" : "");
            editor.classList.remove("hidden");
            form.action_name.focus();
        } catch (error) {
            App.showError(error, "Unable to load action.");
        }
    }

    async function changeStatus(row) {
        var next = Number(row.status) === 1 ? 0 : 1;
        try {
            var result = await App.api("api/actions.php", {
                method: "PATCH",
                body: { id: Number(row.id), status: next }
            });
            showToast(result.message, { type: "success", duration: 2 }); if (App.clearSidebarCache) App.clearSidebarCache();
            table.ajax.reload(null, false);
        } catch (error) {
            App.showError(error, "Unable to change action status.");
        }
    }

    if (!window.AppDataTable || !AppDataTable.ensureAvailable()) return;

    var table = AppDataTable.init("#actionTable", {
        serverSide: true,
        searching: true,
        searchDelay: 300,
        appSearch: false,
        appLoaderText: "Loading actions...",
        pageLength: 25,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0, "asc"]],
        scrollX: true,
        autoWidth: false,
        ajax: function (data, callback) {
            var params = new URLSearchParams();
            params.set("datatable", "1");
            params.set("draw", data.draw);
            params.set("start", data.start);
            params.set("length", data.length);
            params.set("search[value]", data.search.value || "");
            params.set("group_id", groupFilter.value);
            params.set("status", actionStatusFilter.value);

            if (data.order && data.order[0]) {
                params.set("order[0][column]", data.order[0].column);
                params.set("order[0][dir]", data.order[0].dir);
            }
            App.api("api/actions.php?" + params.toString()).then(function (result) {
                allowed = (result.data.allowed_actions || []).map(Number);
                groups = result.data.groups || {};
                fillGroups();
                newButton.style.display = has(2) ? "inline-flex" : "none";
                setSummary(result.data.summary);
                callback(result.data.datatable);
            }).catch(function (error) {
                setSummary({});
                App.showError(error, "Unable to load permission actions.");
                callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
            });
        },
        columns: [
            { data: "id" },
            { data: "action_name" },
            { data: "group_name" },
            { data: "purpose", defaultContent: "-", orderable: false },
            { data: "status", render: function (value, type) {
                if (type !== "display") return Number(value);
                return Number(value) === 1
                    ? '<span class="dt-status active">Active</span>'
                    : '<span class="dt-status inactive">Inactive</span>';
            }},
            { data: "sort_order" },
            { data: null, orderable: false, searchable: false, render: function (data, type, row) {
                if (type !== "display") return "";
                var html = "";
                if (has(3)) {
                    html += '<button type="button" class="table-icon-action js-edit" data-id="' + row.id + '" title="Edit action" aria-label="Edit action"><i data-lucide="pencil"></i></button>';
                }
                if (!row.is_core) {
                    if (Number(row.status) === 1 && has(28)) {
                        html += '<button type="button" class="table-icon-action danger js-status" data-id="' + row.id + '" title="Deactivate action" aria-label="Deactivate action"><i data-lucide="circle-off"></i></button>';
                    }
                    if (Number(row.status) === 0 && has(27)) {
                        html += '<button type="button" class="table-icon-action success js-status" data-id="' + row.id + '" title="Activate action" aria-label="Activate action"><i data-lucide="circle-check"></i></button>';
                    }
                }
                return html || '<span class="muted">View only</span>';
            }}
        ],
        language: { emptyTable: "No permission actions found.", zeroRecords: "No matching actions found." }
    });

    (function removeDefaultSearchRow() {
        var tableElement = document.getElementById("actionTable");
        var card = tableElement ? tableElement.closest(".table-card") : null;
        var row = card ? card.querySelector(".app-table-search-row") : null;

        if (row) row.remove();
    })();

    actionSearch.addEventListener("input", function () {
        var input = this;
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            table.search(input.value.trim()).draw();
        }, 300);
    });

    [groupFilter, actionStatusFilter].forEach(function (field) {
        field.addEventListener("change", function () {
            table.ajax.reload(null, true);
        });
    });

    $("#actionTable").on("click", ".js-edit", function () {
        editAction(Number(this.dataset.id));
    });
    $("#actionTable").on("click", ".js-status", function () {
        var row = table.row($(this).closest("tr")).data();
        if (row) changeStatus(row);
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        Validation.clearForm(form);
        if (!Validation.validateForm(form)) return;
        var id = Number(form.id.value || 0);
        var body = {
            id: id || undefined,
            action_name: form.action_name.value.trim(),
            group_id: Number(form.group_id.value),
            sort_order: Number(form.sort_order.value || 0),
            purpose: form.purpose.value.trim()
        };
        saveButton.disabled = true;
        try {
            var result = await App.api("api/actions.php", {
                method: id ? "PUT" : "POST",
                body: body
            });
            showToast(result.message, { type: "success", duration: 2 }); if (App.clearSidebarCache) App.clearSidebarCache();
            closeEditor();
            table.ajax.reload(null, false);
        } catch (error) {
            Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save action.");
        } finally {
            saveButton.disabled = false;
        }
    });

    newButton.addEventListener("click", openNew);
    document.getElementById("closeEditor").addEventListener("click", closeEditor);
    document.getElementById("cancelAction").addEventListener("click", closeEditor);
    window.addEventListener((window.AppRuntime ? AppRuntime.event("layout-resize") : "amirtham:layout-resize"), function () { table.columns.adjust(); });
})(window.jQuery, window, document);
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
