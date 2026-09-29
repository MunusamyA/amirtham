<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Treatment / Procedure';
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
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Treatment / Procedure</h1>
        <p>Create reusable Treatment / Procedure options for Protocol and Patient Treatment plans.</p>
    </div>
    <a class="btn gray" href="treatment-procedure-list.php">
        <i data-lucide="list"></i>
        Treatment / Procedure List
    </a>
</div>

<div class="card form-card">
<form id="procedureForm" novalidate>
    <div class="card-header">
        <div>
            <h2>Treatment / Procedure Details</h2>
            <p>Procedure Code is generated automatically.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label>Procedure Code</label>
                <input id="procedureCode" type="text" readonly>
            </div>

            <div class="field col-4">
                <label for="procedureName" class="required">Treatment / Procedure Name</label>
                <input
                    id="procedureName"
                    name="procedure_name"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Enter Treatment / Procedure Name"
                    data-required-message="Treatment / Procedure Name is required."
                >
            </div>

            <div class="field col-2">
                <label for="procedurePrice" class="required">Price</label>
                <input id="procedurePrice" name="price" type="text" inputmode="decimal" required value="0.00" placeholder="0.00">
            </div>

            <div class="field col-2">
                <label for="procedureStatus" class="required">Status</label>
                <select
                    id="procedureStatus"
                    name="status"
                    required
                    data-required-message="Status is required."
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div class="form-row" id="auditRow" hidden>
            <div class="field col-4">
                <label>Created By</label>
                <input id="createdByName" type="text" readonly>
            </div>
            <div class="field col-4">
                <label>Created At</label>
                <input id="createdAt" type="text" readonly>
            </div>
            <div class="field col-4">
                <label>Updated At</label>
                <input id="updatedAt" type="text" readonly>
            </div>
        </div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                <span id="saveButtonText">Save Treatment / Procedure</span>
            </button>
            <a class="btn gray" href="treatment-procedure-list.php">Cancel</a>
        </div>
    </div>
</form>
</div>

<script>
(function(window,document){
    'use strict';

    var form = document.getElementById('procedureForm');
    var params = new URLSearchParams(window.location.search);
    var reference = params.get('ref') || '';
    var viewMode = params.get('view') === '1';
    var saveButton = document.getElementById('saveButton');
    var allowedActions = [];

    var statusSelect = GlobalSelect.init('#procedureStatus', {
        placeholder:'Select Status'
    });

    function dataOf(response) {
        return response && response.data && typeof response.data === 'object'
            ? response.data
            : {};
    }

    function setSelect(instance, element, value) {
        element.value = value == null ? '' : String(value);
        element.dispatchEvent(new Event('change',{bubbles:true}));
        if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }
    }

    async function load() {
        try {
            if (reference) {
                var response = await App.api(
                    'api/treatment-procedures.php?ref=' +
                    encodeURIComponent(reference)
                );

                var data = dataOf(response);
                var row = data.record || {};

                allowedActions = Array.isArray(data.allowed_actions)
                    ? data.allowed_actions.map(Number)
                    : [];

                document.getElementById('pageHeading').textContent =
                    viewMode
                        ? 'View Treatment / Procedure'
                        : 'Edit Treatment / Procedure';

                document.getElementById('saveButtonText').textContent =
                    'Update Treatment / Procedure';

                document.getElementById('procedureCode').value =
                    row.procedure_code || '';

                form.procedure_name.value =
                    row.procedure_name || '';

                form.price.value = row.price || '0.00';

                setSelect(
                    statusSelect,
                    form.status,
                    Number(row.status) === 0 ? '0' : '1'
                );

                document.getElementById('createdByName').value =
                    row.created_by_name || '-';
                document.getElementById('createdAt').value =
                    row.created_at || '-';
                document.getElementById('updatedAt').value =
                    row.updated_at || '-';
                document.getElementById('auditRow').hidden = false;

                if (!viewMode && allowedActions.indexOf(3) === -1) {
                    saveButton.disabled = true;
                }
            } else {
                var response = await App.api(
                    'api/treatment-procedures.php?options=1'
                );

                var data = dataOf(response);

                allowedActions = Array.isArray(data.allowed_actions)
                    ? data.allowed_actions.map(Number)
                    : [];

                document.getElementById('procedureCode').value =
                    data.next_procedure_code || '';

                form.price.value = '0.00';

                setSelect(statusSelect, form.status, '1');

                if (allowedActions.indexOf(2) === -1) {
                    saveButton.disabled = true;
                }
            }

            if (viewMode) {
                form.procedure_name.readOnly = true;
                form.price.readOnly = true;
                form.status.disabled = true;
                saveButton.hidden = true;
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            saveButton.disabled = true;
            App.showError(error, 'Unable to prepare Treatment / Procedure form.');
        }
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (viewMode) return;

        Validation.clearForm(form);

        if (!Validation.validateForm(form)) {
            return;
        }

        var data = new FormData(form);
        data.set('action','save');

        if (reference) {
            data.set('ref',reference);
        }

        saveButton.disabled = true;

        try {
            var response = await App.api(
                'api/treatment-procedures.php',
                {
                    method:'POST',
                    body:data
                }
            );

            showToast(
                response.message || 'Treatment / Procedure saved successfully.',
                {type:'success',duration:2}
            );

            window.setTimeout(function(){
                window.location.href = 'treatment-procedure-list.php';
            },700);
        } catch (error) {
            Validation.applyErrors(form,error.errors || {});
            App.showError(error,'Unable to save Treatment / Procedure.');
            saveButton.disabled = false;
        }
    });

    load();

})(window,document);
</script>
</section>

<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>

<script src="assets/js/appearance.js"></script>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
