<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Disease / Diagnosis';
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
                    <h1 id="pageHeading">Add Disease / Diagnosis</h1>
                    <p id="pageDescription">Maintain the Diagnosis and its day-wise Treatment / Procedure plan.</p>
                </div>

                <div class="buttons">
                    <a class="btn gray" href="diagnosis-list.php">
                        <i data-lucide="list"></i>
                        Diagnosis List
                    </a>

                    <a class="btn btn-primary" id="editButton" href="#" hidden>
                        <i data-lucide="pencil"></i>
                        Edit
                    </a>
                </div>
            </div>

            <div class="card form-card">
                <form id="diagnosisForm" novalidate>
                    <div class="card-header">
                        <div>
                            <h2>Disease / Diagnosis Details</h2>
                            <p>Protocol Code, Protocol Name, Duration, Protocol Status and Protocol Description are not required in this form.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="form-row">
                            <div class="field col-3">
                                <label for="diagnosisCode" class="required">Diagnosis Code</label>
                                <input
                                    id="diagnosisCode"
                                    type="text"
                                    placeholder="Auto generated"
                                    readonly
                                    aria-readonly="true"
                                >
                            </div>

                            <div class="field col-5">
                                <label for="diagnosisName" class="required">Disease / Diagnosis Name</label>
                                <input
                                    id="diagnosisName"
                                    name="diagnosis_name"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Enter Disease / Diagnosis Name"
                                    required
                                    data-required-message="Disease / Diagnosis Name is required."
                                >
                            </div>

                            <div class="field col-2">
                                <label for="diagnosisCategory">Category</label>
                                <input
                                    id="diagnosisCategory"
                                    name="category"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Category"
                                >
                            </div>

                            <div class="field col-2">
                                <label for="diagnosisStatus" class="required">Status</label>
                                <select
                                    id="diagnosisStatus"
                                    name="status"
                                    required
                                    data-required-message="Status is required."
                                >
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-12">
                                <label for="diagnosisDescription">Description</label>
                                <textarea
                                    id="diagnosisDescription"
                                    name="description"
                                    rows="3"
                                    placeholder="Diagnosis description or notes"
                                ></textarea>
                            </div>
                        </div>

                        <div
                            class="card-section-title"
                        >
                            <span>Day-wise Treatment / Procedure Plan</span>

                            <button
                                class="btn btn-primary small"
                                id="addTreatmentButton"
                                type="button"
                            >
                                <i data-lucide="plus"></i>
                                Add Treatment
                            </button>
                        </div>

                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Treatment / Procedure</th>
                                    <th>Instructions</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody id="treatmentRows"></tbody>
                            </table>
                        </div>

                        <div id="emptyTreatments" class="muted">
                            No Treatment rows added yet. Click Add Treatment.
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
                        <a class="btn gray" href="diagnosis-list.php">
                            <i data-lucide="x"></i>
                            Cancel
                        </a>

                        <button
                            class="btn btn-primary"
                            id="saveButton"
                            type="submit"
                        >
                            <i data-lucide="save"></i>
                            <span id="saveButtonText">Save Diagnosis</span>
                        </button>
                    </div>
                </form>
            </div>

            <?php require __DIR__ . '/modal/treatment-procedure.php'; ?>

            <template id="treatmentRowTemplate">
                <tr data-treatment-row>
                    <td>
                        <strong data-day-label>Day 1</strong>
                    </td>

                    <td>
                        <div class="input-group">
                            <div class="input-group-control">
                                <select
                                    data-field="treatment_procedure_ref"
                                    required
                                    data-required-message="Treatment / Procedure is required."
                                >
                                    <option value="">Select Treatment / Procedure</option>
                                </select>
                            </div>

                            <button
                                class="btn btn-primary input-group-button add-treatment-procedure"
                                type="button"
                                title="Add Treatment / Procedure"
                                aria-label="Add Treatment / Procedure"
                            >
                                <i data-lucide="plus"></i>
                            </button>
                        </div>
                    </td>

                    <td>
                        <textarea
                            data-field="instructions"
                            rows="2"
                            placeholder="Instructions"
                        ></textarea>
                    </td>

                    <td>
                        <textarea
                            data-field="notes"
                            rows="2"
                            placeholder="Notes"
                        ></textarea>
                    </td>

                    <td>
                        <select
                            data-field="status"
                            required
                            data-required-message="Status is required."
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </td>

                    <td>
                        <button
                            class="table-icon-action danger remove-treatment-row"
                            type="button"
                            title="Remove"
                            aria-label="Remove Treatment"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>
                    </td>
                </tr>
            </template>

            <script>
            (function () {
                'use strict';

                var form = document.getElementById('diagnosisForm');
                var params = new URLSearchParams(window.location.search);
                var ref = params.get('ref') || '';
                var viewMode = params.get('view') === '1';
                var actions = [];
                var saving = false;
                var treatmentProcedureRows = [];
                var procedureSelectCounter = 0;
                var diagnosisStatusSelect = null;

                var rowContainer = document.getElementById('treatmentRows');
                var rowTemplate = document.getElementById('treatmentRowTemplate');

                function el(id) {
                    return document.getElementById(id);
                }

                function has(actionId) {
                    return actions.map(Number).indexOf(Number(actionId)) !== -1;
                }

                function field(row, name) {
                    return row.querySelector('[data-field="' + name + '"]');
                }

                function extractErrors(error) {
                    if (error && error.errors && typeof error.errors === 'object') {
                        return error.errors;
                    }

                    if (
                        error &&
                        error.data &&
                        error.data.errors &&
                        typeof error.data.errors === 'object'
                    ) {
                        return error.data.errors;
                    }

                    return {};
                }

                function setSelectValue(select, value, instance) {
                    select.value = value == null ? '' : String(value);
                    select.dispatchEvent(new Event('change', {bubbles:true}));

                    if (instance && typeof instance.sync === 'function') {
                        instance.sync();
                    }
                }

                function procedureItems(rows) {
                    return (rows || []).map(function (row) {
                        return {
                            value:row.ref,
                            text:row.label || (
                                (row.procedure_code || '') +
                                ' - ' +
                                (row.procedure_name || '')
                            )
                        };
                    });
                }

                function updateEmptyState() {
                    el('emptyTreatments').hidden =
                        rowContainer.querySelectorAll('[data-treatment-row]').length > 0;
                }

                function reindexRows() {
                    var rows = Array.prototype.slice.call(
                        rowContainer.querySelectorAll('[data-treatment-row]')
                    );

                    rows.forEach(function (row, index) {
                        var dayNo = index + 1;
                        row.dataset.index = String(index);
                        row.querySelector('[data-day-label]').textContent =
                            'Day ' + dayNo;

                        [
                            'treatment_procedure_ref',
                            'instructions',
                            'notes',
                            'status'
                        ].forEach(function (name) {
                            var control = field(row, name);
                            if (control) {
                                control.name =
                                    'treatment_days[' +
                                    index +
                                    '][' +
                                    name +
                                    ']';
                            }
                        });
                    });

                    updateEmptyState();
                }

                function initProcedureSelect(row, selectedRef) {
                    var select = field(row, 'treatment_procedure_ref');

                    procedureSelectCounter += 1;
                    select.id =
                        'diagnosisTreatmentProcedure_' +
                        procedureSelectCounter;

                    var instance = null;

                    if (window.GlobalSelect) {
                        instance =
                            GlobalSelect.init(
                                '#' + select.id,
                                {
                                    placeholder:
                                        'Select or type Treatment / Procedure'
                                }
                            );
                    }

                    row._treatmentProcedureSelect = instance;

                    if (
                        instance &&
                        typeof instance.setOptions === 'function'
                    ) {
                        instance.setOptions(
                            procedureItems(treatmentProcedureRows),
                            selectedRef || ''
                        );
                    } else {
                        select.innerHTML =
                            '<option value="">Select Treatment / Procedure</option>';

                        treatmentProcedureRows.forEach(function (item) {
                            var option = document.createElement('option');
                            option.value = item.ref || '';
                            option.textContent =
                                item.label || (
                                    (item.procedure_code || '') +
                                    ' - ' +
                                    (item.procedure_name || '')
                                );
                            select.appendChild(option);
                        });

                        select.value = selectedRef || '';
                    }

                    return instance;
                }

                function refreshProcedureSelects(selectedRef, targetRow) {
                    Array.prototype.forEach.call(
                        rowContainer.querySelectorAll('[data-treatment-row]'),
                        function (row) {
                            var select = field(
                                row,
                                'treatment_procedure_ref'
                            );

                            var keep =
                                row === targetRow
                                    ? (selectedRef || '')
                                    : select.value;

                            if (
                                row._treatmentProcedureSelect &&
                                typeof row._treatmentProcedureSelect.setOptions === 'function'
                            ) {
                                row._treatmentProcedureSelect.setOptions(
                                    procedureItems(treatmentProcedureRows),
                                    keep
                                );
                            } else {
                                var old = keep;
                                select.innerHTML =
                                    '<option value="">Select Treatment / Procedure</option>';

                                treatmentProcedureRows.forEach(function (item) {
                                    var option = document.createElement('option');
                                    option.value = item.ref || '';
                                    option.textContent =
                                        item.label || (
                                            (item.procedure_code || '') +
                                            ' - ' +
                                            (item.procedure_name || '')
                                        );
                                    select.appendChild(option);
                                });

                                select.value = old;
                            }
                        }
                    );
                }

                async function reloadTreatmentProcedures(selectedRef, targetRow) {
                    var response =
                        await App.api(
                            'api/treatment-procedures.php?options=1'
                        );

                    var data =
                        response &&
                        response.data &&
                        typeof response.data === 'object'
                            ? response.data
                            : {};

                    treatmentProcedureRows =
                        Array.isArray(data.procedures)
                            ? data.procedures
                            : [];

                    refreshProcedureSelects(
                        selectedRef || '',
                        targetRow || null
                    );
                }

                function setRowReadOnly(row, readOnly) {
                    ['instructions','notes'].forEach(function (name) {
                        field(row, name).readOnly = readOnly;
                    });

                    ['treatment_procedure_ref','status'].forEach(function (name) {
                        field(row, name).disabled = readOnly;
                    });

                    var addButton =
                        row.querySelector('.add-treatment-procedure');

                    if (addButton) addButton.hidden = readOnly;

                    var removeButton =
                        row.querySelector('.remove-treatment-row');

                    if (removeButton) removeButton.hidden = readOnly;
                }

                function addTreatmentRow(data) {
                    var fragment =
                        rowTemplate.content.cloneNode(true);

                    rowContainer.appendChild(fragment);

                    var row =
                        rowContainer.lastElementChild;

                    data = data || {};

                    initProcedureSelect(
                        row,
                        data.treatment_procedure_ref || ''
                    );

                    field(row, 'instructions').value =
                        data.instructions || '';

                    field(row, 'notes').value =
                        data.notes || '';

                    field(row, 'status').value =
                        Number(data.status) === 0
                            ? '0'
                            : '1';

                    setRowReadOnly(row, viewMode);
                    reindexRows();

                    if (
                        window.Validation &&
                        typeof Validation.init === 'function'
                    ) {
                        Validation.init(row);
                    }

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    return row;
                }

                function removeTreatmentRow(row) {
                    if (!row || viewMode) return;

                    if (
                        row._treatmentProcedureSelect &&
                        typeof row._treatmentProcedureSelect.destroy === 'function'
                    ) {
                        try {
                            row._treatmentProcedureSelect.destroy();
                        } catch (ignore) {}
                    }

                    row.remove();
                    reindexRows();
                }

                function normalizeFields() {
                    [
                        'diagnosisName',
                        'diagnosisCategory',
                        'diagnosisDescription'
                    ].forEach(function (id) {
                        el(id).value =
                            String(el(id).value || '').trim();
                    });

                    Array.prototype.forEach.call(
                        rowContainer.querySelectorAll('[data-treatment-row]'),
                        function (row) {
                            ['instructions','notes'].forEach(function (name) {
                                field(row, name).value =
                                    String(
                                        field(row, name).value || ''
                                    ).trim();
                            });
                        }
                    );
                }

                function validateChangedField(event) {
                    var control = event.target;

                    if (
                        !control ||
                        !control.matches(
                            '[data-validation], [data-regex], [required]'
                        )
                    ) {
                        return;
                    }

                    if (
                        window.Validation &&
                        typeof Validation.validateField === 'function'
                    ) {
                        Validation.validateField(control, false);
                    }
                }

                function validateBusinessRules() {
                    var rows =
                        rowContainer.querySelectorAll('[data-treatment-row]');

                    if (rows.length === 0) {
                        showToast(
                            'Add at least one Treatment / Procedure row.',
                            {
                                type:'danger',
                                duration:3
                            }
                        );
                        return false;
                    }

                    return true;
                }

                function setReadOnly(readOnly) {
                    el('diagnosisCode').readOnly = true;

                    [
                        'diagnosisName',
                        'diagnosisCategory',
                        'diagnosisDescription'
                    ].forEach(function (id) {
                        el(id).readOnly = readOnly;
                    });

                    el('diagnosisStatus').disabled = readOnly;

                    Array.prototype.forEach.call(
                        rowContainer.querySelectorAll('[data-treatment-row]'),
                        function (row) {
                            setRowReadOnly(row, readOnly);
                        }
                    );

                    el('addTreatmentButton').hidden = readOnly;
                }

                function applyMode() {
                    var heading = 'Add Disease / Diagnosis';
                    var description =
                        'Create the Diagnosis and its day-wise Treatment / Procedure plan.';

                    if (ref && viewMode) {
                        heading = 'View Disease / Diagnosis';
                        description =
                            'View the Diagnosis and its Treatment / Procedure plan.';
                    } else if (ref) {
                        heading = 'Edit Disease / Diagnosis';
                        description =
                            'Update the Diagnosis and its Treatment / Procedure plan.';
                    }

                    el('pageHeading').textContent = heading;
                    el('pageDescription').textContent = description;

                    el('saveButtonText').textContent =
                        ref ? 'Update Diagnosis' : 'Save Diagnosis';

                    if (viewMode) {
                        setReadOnly(true);
                        el('saveButton').hidden = true;

                        if (ref && has(3)) {
                            el('editButton').href =
                                'diagnosis-form.php?ref=' +
                                encodeURIComponent(ref);
                            el('editButton').hidden = false;
                        }
                    } else {
                        setReadOnly(false);
                        el('saveButton').hidden = false;
                        el('saveButton').disabled =
                            ref ? !has(3) : !has(2);

                        el('addTreatmentButton').hidden =
                            ref ? !has(3) : !has(2);
                    }
                }

                function applyRecord(record) {
                    el('diagnosisCode').value =
                        record.diagnosis_code || '';

                    el('diagnosisName').value =
                        record.diagnosis_name || '';

                    el('diagnosisCategory').value =
                        record.category || '';

                    el('diagnosisDescription').value =
                        record.description || '';

                    setSelectValue(
                        el('diagnosisStatus'),
                        Number(record.status) === 0 ? '0' : '1',
                        diagnosisStatusSelect
                    );

                    el('createdByName').value =
                        record.created_by_name || '-';

                    el('createdAt').value =
                        record.created_at || '-';

                    el('updatedAt').value =
                        record.updated_at || '-';

                    el('auditRow').hidden = false;
                }

                function collectTreatments() {
                    reindexRows();

                    return Array.prototype.map.call(
                        rowContainer.querySelectorAll('[data-treatment-row]'),
                        function (row, index) {
                            return {
                                day_number:index + 1,
                                treatment_procedure_ref:
                                    String(
                                        field(
                                            row,
                                            'treatment_procedure_ref'
                                        ).value || ''
                                    ).trim(),
                                instructions:
                                    String(
                                        field(
                                            row,
                                            'instructions'
                                        ).value || ''
                                    ).trim(),
                                notes:
                                    String(
                                        field(
                                            row,
                                            'notes'
                                        ).value || ''
                                    ).trim(),
                                status:
                                    field(row, 'status').value
                            };
                        }
                    );
                }

                function collect() {
                    return {
                        action:'save',
                        ref:ref || undefined,
                        diagnosis_name:
                            el('diagnosisName').value.trim(),
                        category:
                            el('diagnosisCategory').value.trim(),
                        description:
                            el('diagnosisDescription').value.trim(),
                        status:
                            el('diagnosisStatus').value,
                        treatment_days:
                            collectTreatments()
                    };
                }

                async function saveDiagnosis(event) {
                    event.preventDefault();

                    if (viewMode || saving) return;

                    if (
                        window.Validation &&
                        typeof Validation.clearForm === 'function'
                    ) {
                        Validation.clearForm(form);
                    }

                    normalizeFields();
                    reindexRows();

                    if (
                        window.Validation &&
                        typeof Validation.validateForm === 'function' &&
                        !Validation.validateForm(form)
                    ) {
                        return;
                    }

                    if (!validateBusinessRules()) return;

                    saving = true;
                    el('saveButton').disabled = true;

                    try {
                        var response =
                            await App.api(
                                'api/diagnoses.php',
                                {
                                    method:'POST',
                                    body:collect()
                                }
                            );

                        showToast(
                            response.message ||
                            'Diagnosis saved successfully.',
                            {
                                type:'success',
                                duration:2
                            }
                        );

                        window.location.href =
                            'diagnosis-list.php';
                    } catch (error) {
                        saving = false;
                        el('saveButton').disabled = false;

                        if (
                            window.Validation &&
                            typeof Validation.applyErrors === 'function'
                        ) {
                            Validation.applyErrors(
                                form,
                                extractErrors(error)
                            );
                        }

                        App.showError(
                            error,
                            'Unable to save Diagnosis.'
                        );
                    }
                }

                async function load() {
                    try {
                        if (window.GlobalSelect) {
                            diagnosisStatusSelect =
                                GlobalSelect.init(
                                    el('diagnosisStatus'),
                                    {
                                        placeholder:'Select Status'
                                    }
                                );
                        }

                        var options =
                            await App.api(
                                'api/diagnoses.php?options=1'
                            );

                        actions =
                            (
                                options.data.allowed_actions ||
                                []
                            ).map(Number);

                        treatmentProcedureRows =
                            Array.isArray(
                                options.data.treatment_procedures
                            )
                                ? options.data.treatment_procedures
                                : [];

                        if (!ref) {
                            el('diagnosisCode').value =
                                options.data.next_diagnosis_code || '';

                            setSelectValue(
                                el('diagnosisStatus'),
                                '1',
                                diagnosisStatusSelect
                            );

                            addTreatmentRow({status:1});
                        } else {
                            var response =
                                await App.api(
                                    'api/diagnoses.php?ref=' +
                                    encodeURIComponent(ref)
                                );

                            actions =
                                (
                                    response.data.allowed_actions ||
                                    actions
                                ).map(Number);

                            treatmentProcedureRows =
                                Array.isArray(
                                    response.data.treatment_procedures
                                )
                                    ? response.data.treatment_procedures
                                    : treatmentProcedureRows;

                            applyRecord(
                                response.data.record || {}
                            );

                            var days =
                                response.data.treatment_days ||
                                response.data.protocol_days ||
                                [];

                            (days || []).forEach(function (day) {
                                addTreatmentRow(day);
                            });

                            if (
                                rowContainer.querySelectorAll(
                                    '[data-treatment-row]'
                                ).length === 0
                            ) {
                                addTreatmentRow({status:1});
                            }
                        }

                        applyMode();

                        if (
                            window.Validation &&
                            typeof Validation.init === 'function'
                        ) {
                            Validation.init(form);
                        }

                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    } catch (error) {
                        el('saveButton').disabled = true;
                        el('addTreatmentButton').disabled = true;

                        App.showError(
                            error,
                            'Unable to prepare Diagnosis Form.'
                        );
                    }
                }

                el('addTreatmentButton').addEventListener(
                    'click',
                    function () {
                        var row = addTreatmentRow({status:1});
                        var control =
                            field(
                                row,
                                'treatment_procedure_ref'
                            );

                        if (control) control.focus();
                    }
                );

                rowContainer.addEventListener(
                    'click',
                    function (event) {
                        var addButton =
                            event.target.closest(
                                '.add-treatment-procedure'
                            );

                        if (addButton) {
                            var targetRow =
                                addButton.closest(
                                    '[data-treatment-row]'
                                );

                            if (window.AppTreatmentProcedureForm) {
                                AppTreatmentProcedureForm.openCreate({
                                    onSaved:function(saved) {
                                        var newRef =
                                            saved && saved.ref
                                                ? saved.ref
                                                : '';

                                        reloadTreatmentProcedures(
                                            newRef,
                                            targetRow
                                        ).catch(function (error) {
                                            App.showError(
                                                error,
                                                'Treatment / Procedure was created, but options could not be refreshed.'
                                            );
                                        });
                                    }
                                });
                            }

                            return;
                        }

                        var removeButton =
                            event.target.closest(
                                '.remove-treatment-row'
                            );

                        if (!removeButton) return;

                        removeTreatmentRow(
                            removeButton.closest(
                                '[data-treatment-row]'
                            )
                        );
                    }
                );

                rowContainer.addEventListener(
                    'change',
                    validateChangedField
                );

                form.addEventListener(
                    'change',
                    validateChangedField
                );

                form.addEventListener(
                    'submit',
                    saveDiagnosis
                );

                load();
            })();
            </script>
        </section>

        <?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>
</body>
</html>