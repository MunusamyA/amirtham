<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Course Fee Structure Form';
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
        <h1 id="pageHeading">Add Course Fee Structure</h1>
        <p id="pageDescription">Define the Course fee and its fee components.</p>
    </div>

    <div class="page-head-actions">
        <a class="btn gray" href="course-fee-list.php">
            <i data-lucide="list"></i>
            Fee Structure List
        </a>

        <a class="btn btn-primary" id="editButton" href="#" hidden>
            <i data-lucide="pencil"></i>
            Edit Fee Structure
        </a>
    </div>
</div>

<div class="card form-card">
    <form id="feeStructureForm" novalidate>
        <div class="card-header">
            <div>
                <h2>Course Fee Structure</h2>
                <p>Structure Code and Total Amount are calculated by the system.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="card-section-title">Structure Details</div>

            <div class="form-row">
                <div class="field col-3">
                    <label for="structureCode" class="required">Structure Code</label>
                    <input id="structureCode" name="structure_code" type="text" maxlength="50"
                           placeholder="Auto generated" readonly aria-readonly="true" required
                           data-required-message="Structure Code is required.">
                </div>

                <div class="field col-5">
                    <label for="courseId" class="required">Course</label>
                    <select id="courseId" name="course_id" required
                            data-required-message="Course is required.">
                        <option value="">Select Course</option>
                    </select>
                </div>

                <div class="field col-4">
                    <label for="structureName" class="required">Structure Name</label>
                    <input id="structureName" name="structure_name" type="text" maxlength="150"
                           placeholder="Example: Regular Fee 2026" required
                           data-required-message="Structure Name is required.">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-3">
                    <label for="effectiveFrom" class="required">Effective From</label>
                    <input id="effectiveFrom" name="effective_from" type="date" required
                           data-required-message="Effective From date is required.">
                </div>

                <div class="field col-3">
                    <label for="defaultInstallmentCount" class="required">Default Installment Count</label>
                    <input id="defaultInstallmentCount" name="default_installment_count"
                           type="text" inputmode="numeric" maxlength="4" value="1"
                           placeholder="1" required
                           data-validation="integer"
                           data-regex="^[1-9][0-9]*$"
                           data-required-message="Default Installment Count is required."
                           data-integer-message="Default Installment Count must be a whole number."
                           data-regex-message="Default Installment Count must be greater than zero.">
                    <div class="muted">This is only the default suggestion. Student fee plans can use a different installment count.</div>
                </div>

                <div class="field col-3">
                    <label for="totalAmount">Total Amount</label>
                    <input id="totalAmount" name="total_amount" type="text"
                           value="0.00" readonly aria-readonly="true">
                    <div class="muted">Auto calculated from active fee components.</div>
                </div>

                <div class="field col-3">
                    <label for="structureStatus" class="required">Status</label>
                    <select id="structureStatus" name="status" required
                            data-required-message="Status is required.">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="field col-12">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="255"
                              placeholder="Optional notes"></textarea>
                </div>
            </div>

            <div class="card-section-title card-section-title-actions">
                <span>Fee Components</span>

                <button class="btn btn-primary small" id="addFeeItemButton" type="button">
                    <i data-lucide="plus"></i>
                    Add More
                </button>
            </div>

            <div id="feeItemRows"></div>

            <div id="emptyFeeItems" class="muted app-inline-empty">
                No fee components added yet. Click Add More.
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
            <a class="btn gray" href="course-fee-list.php">
                <i data-lucide="x"></i>
                Cancel
            </a>

            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                <span id="saveButtonText">Save Fee Structure</span>
            </button>
        </div>
    </form>
</div>

<template id="feeItemTemplate">
    <div class="fee-item-row app-repeat-row" data-fee-item-row>
        <input type="hidden" data-field="ref">

        <div class="app-fee-item-grid">
            <div class="field">
                <label class="required">Fee Head</label>
                <select data-field="fee_head" required
                        data-required-message="Fee Head is required.">
                    <option value="">Select Fee Head</option>
                    <option value="Admission Fee">Admission Fee</option>
                    <option value="Tuition Fee">Tuition Fee</option>
                    <option value="Book Fee">Book Fee</option>
                    <option value="Exam Fee">Exam Fee</option>
                    <option value="Lab Fee">Lab Fee</option>
                    <option value="Registration Fee">Registration Fee</option>
                    <option value="Material Fee">Material Fee</option>
                    <option value="Certificate Fee">Certificate Fee</option>
                    <option value="Other Fee">Other Fee</option>
                </select>
            </div>

            <div class="field">
                <label class="required">Fee Amount</label>
                <input data-field="amount" type="text" inputmode="decimal" maxlength="15"
                       placeholder="0.00" required
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$"
                       data-required-message="Fee Amount is required."
                       data-decimal-message="Enter a valid Fee Amount."
                       data-regex-message="Fee Amount cannot be negative.">
            </div>

            <div class="field">
                <label class="required">Mandatory</label>
                <select data-field="mandatory" required
                        data-required-message="Mandatory selection is required.">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="field">
                <label>Sort Order</label>
                <input data-field="sort_order" type="text" inputmode="numeric" maxlength="10"
                       value="0" placeholder="0"
                       data-validation="integer"
                       data-regex="^[0-9]+$"
                       data-integer-message="Sort Order must be a whole number."
                       data-regex-message="Sort Order cannot be negative.">
            </div>

            <div class="field">
                <label class="required">Status</label>
                <select data-field="status" required
                        data-required-message="Status is required.">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="app-repeat-action">
                <button class="btn red small app-icon-only-button remove-fee-item"
                        type="button"
                        title="Remove Fee Component"
                        aria-label="Remove Fee Component">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    'use strict';

    var form = document.getElementById('feeStructureForm');
    var params = new URLSearchParams(window.location.search);
    var ref = params.get('ref') || '';
    var viewMode = params.get('view') === '1';

    var actions = [];
    var saving = false;
    var removedItems = [];

    var courseSelect = null;
    var statusSelect = null;

    var rowContainer = document.getElementById('feeItemRows');
    var rowTemplate = document.getElementById('feeItemTemplate');

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

        if (error && error.data && error.data.errors &&
            typeof error.data.errors === 'object') {
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

    function populateCourses(rows, selectedValue) {
        var select = el('courseId');
        select.innerHTML = '<option value="">Select Course</option>';

        (rows || []).forEach(function (row) {
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent =
                row.course_code + ' - ' + row.course_name +
                (Number(row.status) === 1 ? '' : ' (Inactive)');

            select.appendChild(option);
        });

        if (selectedValue != null && selectedValue !== '') {
            select.value = String(selectedValue);
        }

        if (courseSelect && typeof courseSelect.sync === 'function') {
            courseSelect.sync();
        }
    }

    function updateEmptyState() {
        el('emptyFeeItems').hidden =
            rowContainer.querySelectorAll('[data-fee-item-row]').length > 0;
    }

    function reindexRows() {
        var rows = Array.prototype.slice.call(
            rowContainer.querySelectorAll('[data-fee-item-row]')
        );

        rows.forEach(function (row, index) {
            row.dataset.index = String(index);

            ['ref','fee_head','amount','mandatory','sort_order','status'].forEach(function (name) {
                var control = field(row, name);

                if (control) {
                    control.name = 'items[' + index + '][' + name + ']';
                }
            });
        });

        updateEmptyState();
    }

    function setRowReadOnly(row, readOnly) {
        ['amount','sort_order'].forEach(function (name) {
            field(row, name).readOnly = readOnly;
        });

        field(row, 'fee_head').disabled = readOnly;
        field(row, 'mandatory').disabled = readOnly;
        field(row, 'status').disabled = readOnly;
        row.querySelector('.remove-fee-item').hidden = readOnly;
    }

    function addRow(data) {
        var fragment = rowTemplate.content.cloneNode(true);
        rowContainer.appendChild(fragment);

        var row = rowContainer.lastElementChild;
        data = data || {};

        field(row, 'ref').value = data.ref || '';

        var feeHeadSelect = field(row, 'fee_head');
        var feeHeadValue = data.fee_head || '';

        if (feeHeadValue) {
            var exists = Array.prototype.some.call(
                feeHeadSelect.options,
                function (option) {
                    return option.value === String(feeHeadValue);
                }
            );

            if (!exists) {
                var customOption = document.createElement('option');
                customOption.value = String(feeHeadValue);
                customOption.textContent = String(feeHeadValue);
                feeHeadSelect.appendChild(customOption);
            }
        }

        feeHeadSelect.value = feeHeadValue;

        field(row, 'mandatory').value =
            Number(data.mandatory) === 0 ? '0' : '1';

        field(row, 'amount').value =
            data.amount == null ? '' : String(data.amount);
        field(row, 'sort_order').value =
            data.sort_order == null
                ? String(rowContainer.querySelectorAll('[data-fee-item-row]').length - 1)
                : String(data.sort_order);
        field(row, 'status').value =
            Number(data.status) === 0 ? '0' : '1';

        setRowReadOnly(row, viewMode);
        reindexRows();
        calculateTotal();

        if (window.Validation && typeof Validation.init === 'function') {
            Validation.init(row);
        }

        if (window.lucide) {
            window.lucide.createIcons();
        }

        return row;
    }

    function removeRow(row) {
        if (!row) return;

        var itemRef = String(field(row, 'ref').value || '').trim();

        if (itemRef !== '') {
            if (!has(4)) {
                showToast(
                    'You do not have Delete permission for existing Fee Components.',
                    {type:'danger',duration:3}
                );
                return;
            }

            if (!window.confirm(
                'Remove this existing Fee Component? It will be deleted when you save.'
            )) {
                return;
            }

            removedItems.push(itemRef);
        }

        row.remove();
        reindexRows();
        calculateTotal();
    }

    function amountValue(control) {
        var value = String(control.value || '').trim();

        if (value === '' || !isFinite(Number(value))) {
            return 0;
        }

        return Number(value);
    }

    function calculateTotal() {
        var total = 0;

        Array.prototype.forEach.call(
            rowContainer.querySelectorAll('[data-fee-item-row]'),
            function (row) {
                if (String(field(row, 'status').value) !== '1') {
                    return;
                }

                var amount = amountValue(field(row, 'amount'));

                if (isFinite(amount) && amount > 0) {
                    total += amount;
                }
            }
        );

        el('totalAmount').value = total.toFixed(2);
    }

    function normalizeFields() {
        el('structureName').value =
            String(el('structureName').value || '').trim();
        el('notes').value =
            String(el('notes').value || '').trim();

        if (window.Validation && typeof Validation.formatField === 'function') {
            Validation.formatField(el('defaultInstallmentCount'));

            Array.prototype.forEach.call(
                rowContainer.querySelectorAll('[data-fee-item-row]'),
                function (row) {
                    Validation.formatField(field(row, 'amount'));
                    Validation.formatField(field(row, 'sort_order'));
                }
            );
        }

        calculateTotal();
    }

    function validateBusinessRules() {
        var errors = {};
        var seen = {};
        var activeCount = 0;

        var rows = Array.prototype.slice.call(
            rowContainer.querySelectorAll('[data-fee-item-row]')
        );

        if (!rows.length) {
            showToast(
                'Add at least one Fee Component.',
                {type:'danger',duration:3}
            );
            return false;
        }

        rows.forEach(function (row, index) {
            var feeHead =
                String(field(row, 'fee_head').value || '').trim();
            var key = feeHead.toLocaleLowerCase();

            if (key) {
                if (seen[key] !== undefined) {
                    errors['items[' + index + '][fee_head]'] =
                        'This Fee Head is already entered above.';
                } else {
                    seen[key] = index;
                }
            }

            var amount = amountValue(field(row, 'amount'));

            if (!isFinite(amount) || amount <= 0) {
                errors['items[' + index + '][amount]'] =
                    'Amount must be greater than zero.';
            }

            if (String(field(row, 'status').value) === '1') {
                activeCount++;
            }
        });

        if (activeCount < 1) {
            showToast(
                'At least one Fee Component must be Active.',
                {type:'danger',duration:3}
            );
            return false;
        }

        if (Object.keys(errors).length) {
            if (window.Validation &&
                typeof Validation.applyErrors === 'function') {
                Validation.applyErrors(form, errors);
            }

            return false;
        }

        return true;
    }

    function validateChangedField(event) {
        var control = event.target;

        if (!control) return;

        if (control.matches('[data-validation], [data-regex], [required]') &&
            window.Validation &&
            typeof Validation.validateField === 'function') {
            Validation.validateField(control, false);
        }

        if (control.matches('[data-field="amount"], [data-field="status"]')) {
            calculateTotal();
        }
    }

    function setReadOnly(readOnly) {
        el('structureCode').readOnly = true;
        el('structureCode').setAttribute('readonly', 'readonly');
        el('structureCode').setAttribute('aria-readonly', 'true');

        ['structureName','effectiveFrom','defaultInstallmentCount','notes']
            .forEach(function (id) {
                el(id).readOnly = readOnly;
            });

        el('courseId').disabled = readOnly;
        el('structureStatus').disabled = readOnly;

        Array.prototype.forEach.call(
            rowContainer.querySelectorAll('[data-fee-item-row]'),
            function (row) {
                setRowReadOnly(row, readOnly);
            }
        );

        el('addFeeItemButton').hidden = readOnly;
    }

    function applyMode() {
        var heading = 'Add Course Fee Structure';
        var description =
            'Define the Course fee and its fee components.';

        if (ref && viewMode) {
            heading = 'View Course Fee Structure';
            description =
                'View Course Fee Structure and fee components.';
        } else if (ref) {
            heading = 'Edit Course Fee Structure';
            description =
                'Update Course Fee Structure and fee components.';
        }

        el('pageHeading').textContent = heading;
        el('pageDescription').textContent = description;
        el('saveButtonText').textContent =
            ref ? 'Update Fee Structure' : 'Save Fee Structure';

        if (viewMode) {
            setReadOnly(true);
            el('saveButton').hidden = true;

            if (ref && has(3)) {
                el('editButton').href =
                    'course-fee-form.php?ref=' + encodeURIComponent(ref);
                el('editButton').hidden = false;
            }
        } else {
            setReadOnly(false);
            el('saveButton').hidden = false;
            el('saveButton').disabled =
                ref ? !has(3) : !has(2);

            el('addFeeItemButton').hidden =
                ref ? !has(3) : !has(2);
        }
    }

    function applyStructure(structure) {
        el('structureCode').value =
            structure.structure_code || '';
        el('structureName').value =
            structure.structure_name || '';

        setSelectValue(
            el('courseId'),
            structure.course_id || '',
            courseSelect
        );

        el('effectiveFrom').value =
            structure.effective_from || '';
        el('defaultInstallmentCount').value =
            structure.default_installment_count == null
                ? '1'
                : String(structure.default_installment_count);

        el('notes').value =
            structure.notes || '';

        setSelectValue(
            el('structureStatus'),
            Number(structure.status) === 0 ? '0' : '1',
            statusSelect
        );

        el('totalAmount').value =
            Number(structure.total_amount || 0).toFixed(2);

        el('createdByName').value =
            structure.created_by_name || '-';
        el('createdAt').value =
            structure.created_at || '-';
        el('updatedAt').value =
            structure.updated_at || '-';
        el('auditRow').hidden = false;
    }

    function collectItems() {
        reindexRows();

        return Array.prototype.map.call(
            rowContainer.querySelectorAll('[data-fee-item-row]'),
            function (row) {
                return {
                    ref:
                        String(field(row, 'ref').value || '').trim() || null,
                    fee_head:
                        String(field(row, 'fee_head').value || '').trim(),
                    amount: field(row, 'amount').value,
                    mandatory: field(row, 'mandatory').value,
                    sort_order: field(row, 'sort_order').value,
                    status: field(row, 'status').value
                };
            }
        );
    }

    function collect() {
        return {
            action: 'save',
            ref: ref || undefined,
            structure_code:
                el('structureCode').value.trim(),
            course_id:
                el('courseId').value,
            structure_name:
                el('structureName').value.trim(),
            effective_from:
                el('effectiveFrom').value,
            default_installment_count:
                el('defaultInstallmentCount').value,
            total_amount:
                el('totalAmount').value,
            notes:
                el('notes').value.trim(),
            status:
                el('structureStatus').value,
            items:
                collectItems(),
            removed_items:
                removedItems.slice()
        };
    }

    async function saveFeeStructure(event) {
        event.preventDefault();

        if (viewMode || saving) return;

        if (window.Validation &&
            typeof Validation.clearForm === 'function') {
            Validation.clearForm(form);
        }

        normalizeFields();
        reindexRows();

        if (window.Validation &&
            typeof Validation.validateForm === 'function') {
            if (!Validation.validateForm(form)) {
                return;
            }
        }

        if (!validateBusinessRules()) {
            return;
        }

        saving = true;
        el('saveButton').disabled = true;

        try {
            var response = await App.api('api/course-fees.php', {
                method: 'POST',
                body: collect()
            });

            showToast(
                response.message ||
                'Course Fee Structure saved successfully.',
                {type:'success',duration:2}
            );

            window.location.href = 'course-fee-list.php';
        } catch (error) {
            saving = false;
            el('saveButton').disabled = false;

            if (window.Validation &&
                typeof Validation.applyErrors === 'function') {
                Validation.applyErrors(form, extractErrors(error));
            }

            App.showError(
                error,
                'Unable to save Course Fee Structure.'
            );
        }
    }

    async function load() {
        try {
            if (window.GlobalSelect) {
                courseSelect = GlobalSelect.init(
                    el('courseId'),
                    {placeholder:'Select Course'}
                );

                statusSelect = GlobalSelect.init(
                    el('structureStatus'),
                    {placeholder:'Select Status'}
                );
            }

            if (ref) {
                var response = await App.api(
                    'api/course-fees.php?ref=' +
                    encodeURIComponent(ref)
                );

                actions =
                    (response.data.allowed_actions || []).map(Number);

                populateCourses(
                    response.data.courses || [],
                    response.data.structure.course_id
                );

                applyStructure(response.data.structure);

                (response.data.items || []).forEach(function (item) {
                    addRow(item);
                });
            } else {
                var options = await App.api(
                    'api/course-fees.php?options=1'
                );

                actions =
                    (options.data.allowed_actions || []).map(Number);

                populateCourses(
                    options.data.courses || [],
                    ''
                );

                el('structureCode').value =
                    options.data.next_structure_code || '';
                el('defaultInstallmentCount').value = '1';

                setSelectValue(
                    el('structureStatus'),
                    '1',
                    statusSelect
                );

                addRow({
                    fee_head:'',
                    amount:'',
                    mandatory:1,
                    sort_order:0,
                    status:1
                });
            }

            applyMode();
            calculateTotal();

            if (window.Validation &&
                typeof Validation.init === 'function') {
                Validation.init(form);
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            el('saveButton').disabled = true;
            el('addFeeItemButton').disabled = true;

            App.showError(
                error,
                'Unable to load Course Fee Structure form.'
            );
        }
    }

    el('addFeeItemButton').addEventListener('click', function () {
        var row = addRow({
            fee_head:'',
            amount:'',
            mandatory:1,
            sort_order:
                rowContainer.querySelectorAll('[data-fee-item-row]').length,
            status:1
        });

        field(row, 'fee_head').focus();
    });

    rowContainer.addEventListener('click', function (event) {
        var button = event.target.closest('.remove-fee-item');

        if (!button) return;

        removeRow(
            button.closest('[data-fee-item-row]')
        );
    });

    form.addEventListener('input', function (event) {
        if (event.target &&
            event.target.matches('[data-field="amount"]')) {
            calculateTotal();
        }
    });

    form.addEventListener('change', validateChangedField);
    form.addEventListener('submit', saveFeeStructure);

    load();
})();
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
