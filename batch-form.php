<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Batch Form';
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
        <h1 id="pageHeading">Add Batches</h1>
        <p id="pageDescription">Create one or multiple Course batches in the same form.</p>
    </div>

    <div>
        <a class="btn gray" href="batch-list.php">
            <i data-lucide="list"></i>
            Batch List
        </a>

        <a class="btn btn-primary" id="editButton" href="#" hidden>
            <i data-lucide="pencil"></i>
            Edit Batch
        </a>
    </div>
</div>

<div class="card form-card">
    <form id="batchForm" novalidate>
        <div class="card-header">
            <div>
                <h2>Batch Management</h2>
                <p>Each row is an independent Batch. Use Add More to create multiple Course batches together.</p>
            </div>

            <button class="btn btn-primary small" id="addBatchButton" type="button">
                <i data-lucide="plus"></i>
                Add More
            </button>
        </div>

        <div class="card-body">
            <div id="batchRows"></div>

            <div id="emptyRows" class="muted" hidden>
                No Batch rows. Click Add More to add a Batch.
            </div>
        </div>

        <div class="card-footer">
            <a class="btn gray" href="batch-list.php">
                <i data-lucide="x"></i>
                Cancel
            </a>

            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                <span id="saveButtonText">Save All Batches</span>
            </button>
        </div>
    </form>
</div>

<template id="batchRowTemplate">
    <div class="batch-row" data-batch-row>
        <input type="hidden" data-field="ref">

        <div class="form-row">
            <div class="field col-3">
                <label class="required">Course</label>
                <select data-field="course_id" required
                        data-required-message="Course is required.">
                    <option value="">Select Course</option>
                </select>
            </div>

            <div class="field col-2">
                <label>Batch Code</label>
                <input data-field="batch_code"
                       type="text"
                       readonly
                       aria-readonly="true"
                       tabindex="-1">
            </div>

            <div class="field col-3">
                <label class="required">Batch Name</label>
                <input data-field="batch_name"
                       type="text"
                       maxlength="150"
                       placeholder="Example: DCA JAN 2027"
                       required
                       data-required-message="Batch Name is required.">
            </div>

            <div class="field col-2">
                <label class="required">Start Date</label>
                <input data-field="start_date"
                       type="date"
                       required
                       data-required-message="Start Date is required.">
            </div>

            <div class="field col-2">
                <label class="required">End Date</label>
                <input data-field="end_date"
                       type="date"
                       readonly
                       aria-readonly="true"
                       tabindex="-1"
                       required
                       data-required-message="End Date is required.">
                <div class="muted">Auto calculated from Course duration.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-5">
                <label class="required">Working Days</label>

                <div data-field="working_days" class="check-row">
                    <label class="option">
                        <input type="checkbox" value="1">
                        <span>Mon</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="2">
                        <span>Tue</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="3">
                        <span>Wed</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="4">
                        <span>Thu</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="5">
                        <span>Fri</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="6">
                        <span>Sat</span>
                    </label>

                    <label class="option">
                        <input type="checkbox" value="7">
                        <span>Sun</span>
                    </label>
                </div>

                <div class="validation-error"
                     data-working-days-error
                     hidden></div>
            </div>

            <div class="field col-2">
                <label>Maximum Students</label>
                <input data-field="maximum_students"
                       type="text"
                       inputmode="numeric"
                       maxlength="10"
                       placeholder="Example: 30"
                       data-validation="integer"
                       data-regex="^[1-9][0-9]*$"
                       data-integer-message="Maximum Students must be a whole number."
                       data-regex-message="Maximum Students must be greater than zero.">

                <div class="muted">
                    Defaults from selected Course.
                </div>
            </div>

            <div class="field col-2">
                <label class="required">Status</label>
                <select data-field="status"
                        required
                        data-required-message="Status is required.">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="field col-2">
                <label>Notes</label>
                <input data-field="notes"
                       type="text"
                       maxlength="255"
                       placeholder="Optional notes">
            </div>

            <div class="field col-1">
                <label>&nbsp;</label>

                <button class="btn red small remove-batch-row"
                        type="button"
                        title="Remove Row"
                        aria-label="Remove Batch Row">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        </div>

        <div class="form-row batch-audit-row" hidden>
            <div class="field col-4">
                <label>Created By</label>
                <input data-field="created_by_name"
                       type="text"
                       readonly>
            </div>

            <div class="field col-4">
                <label>Created At</label>
                <input data-field="created_at"
                       type="text"
                       readonly>
            </div>

            <div class="field col-4">
                <label>Updated At</label>
                <input data-field="updated_at"
                       type="text"
                       readonly>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    'use strict';

    var form = document.getElementById('batchForm');
    var rowContainer = document.getElementById('batchRows');
    var rowTemplate = document.getElementById('batchRowTemplate');
    var params = new URLSearchParams(window.location.search);
    var pageRef = params.get('ref') || '';
    var viewMode = params.get('view') === '1';

    var actions = [];
    var courses = [];
    var removedBatches = [];
    var saving = false;
    var baseNextBatchNumber = 1;

    function el(id) {
        return document.getElementById(id);
    }

    function has(actionId) {
        return actions.map(Number).indexOf(Number(actionId)) !== -1;
    }

    function errorMessage(error, fallback) {
        if (error && typeof error.message === 'string' && error.message.trim() !== '') {
            return error.message.trim();
        }

        if (
            error &&
            error.data &&
            typeof error.data.message === 'string' &&
            error.data.message.trim() !== ''
        ) {
            return error.data.message.trim();
        }

        return fallback;
    }

    function dangerToast(message) {
        showToast(message, {
            type: 'danger',
            duration: 4
        });
    }

    function field(row, name) {
        return row.querySelector('[data-field="' + name + '"]');
    }

    function selectedWorkingDays(row) {
        return Array.prototype.map.call(
            field(row, 'working_days').querySelectorAll('input[type="checkbox"]:checked'),
            function (checkbox) {
                return String(checkbox.value);
            }
        );
    }

    function setWorkingDays(row, values) {
        var wanted = (values || []).map(String);
        Array.prototype.forEach.call(
            field(row, 'working_days').querySelectorAll('input[type="checkbox"]'),
            function (checkbox) {
                checkbox.checked = wanted.indexOf(String(checkbox.value)) !== -1;
            }
        );
    }

    function clearWorkingDaysError(row) {
        var error = row.querySelector('[data-working-days-error]');
        error.textContent = '';
        error.hidden = true;
        field(row, 'working_days').removeAttribute('aria-invalid');
    }

    function showWorkingDaysError(row, message) {
        var error = row.querySelector('[data-working-days-error]');
        error.textContent = message;
        error.hidden = false;
        field(row, 'working_days').setAttribute('aria-invalid', 'true');
    }

    function populateCourseSelect(select, selectedValue) {
        select.innerHTML = '<option value="">Select Course</option>';

        courses.forEach(function (course) {
            var option = document.createElement('option');
            option.value = String(course.id);
            option.textContent = course.course_code + ' - ' + course.course_name +
                (Number(course.status) === 1 ? '' : ' (Inactive)');
            option.dataset.maximumStudents = course.maximum_students == null
                ? ''
                : String(course.maximum_students);
            option.dataset.durationValue = course.duration_value == null
                ? ''
                : String(course.duration_value);
            option.dataset.durationUnit = course.duration_unit || '';
            select.appendChild(option);
        });

        if (selectedValue != null && selectedValue !== '') {
            select.value = String(selectedValue);
        }
    }

    function courseById(id) {
        var wanted = String(id || '');
        for (var i = 0; i < courses.length; i++) {
            if (String(courses[i].id) === wanted) {
                return courses[i];
            }
        }
        return null;
    }

    function formatBatchCode(number) {
        return 'BAT' + String(number).padStart(4, '0');
    }

    function refreshNewBatchCodes() {
        var next = Number(baseNextBatchNumber || 1);

        Array.prototype.forEach.call(
            rowContainer.querySelectorAll('[data-batch-row]'),
            function (row) {
                var rowRef = String(field(row, 'ref').value || '').trim();

                if (rowRef === '') {
                    field(row, 'batch_code').value = formatBatchCode(next);
                    next++;
                }
            }
        );
    }

    function parseIsoDate(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));

        if (!match) {
            return null;
        }

        return {
            year: Number(match[1]),
            month: Number(match[2]),
            day: Number(match[3])
        };
    }

    function daysInMonth(year, month) {
        return new Date(Date.UTC(year, month, 0)).getUTCDate();
    }

    function isoFromUtcTime(time) {
        var date = new Date(time);

        return [
            date.getUTCFullYear(),
            String(date.getUTCMonth() + 1).padStart(2, '0'),
            String(date.getUTCDate()).padStart(2, '0')
        ].join('-');
    }

    function calculateInclusiveEndDate(startDate, durationValue, durationUnit) {
        var start = parseIsoDate(startDate);
        var value = Number(durationValue || 0);
        var unit = String(durationUnit || '').toLowerCase();

        if (!start || !Number.isInteger(value) || value < 1) {
            return '';
        }

        var startTime = Date.UTC(start.year, start.month - 1, start.day);
        var endTime;

        if (unit === 'day' || unit === 'days') {
            endTime = startTime + ((value - 1) * 86400000);
            return isoFromUtcTime(endTime);
        }

        if (unit === 'week' || unit === 'weeks') {
            endTime = startTime + (((value * 7) - 1) * 86400000);
            return isoFromUtcTime(endTime);
        }

        if (unit === 'month' || unit === 'months') {
            var totalMonths =
                (start.year * 12) +
                (start.month - 1) +
                value;

            var targetYear = Math.floor(totalMonths / 12);
            var targetMonthZero = totalMonths % 12;
            var targetDay = Math.min(
                start.day,
                daysInMonth(targetYear, targetMonthZero + 1)
            );

            endTime =
                Date.UTC(targetYear, targetMonthZero, targetDay) -
                86400000;

            return isoFromUtcTime(endTime);
        }

        if (unit === 'year' || unit === 'years') {
            var targetYearOnly = start.year + value;
            var targetDayOnly = Math.min(
                start.day,
                daysInMonth(targetYearOnly, start.month)
            );

            endTime =
                Date.UTC(
                    targetYearOnly,
                    start.month - 1,
                    targetDayOnly
                ) -
                86400000;

            return isoFromUtcTime(endTime);
        }

        return '';
    }

    function calculateEndDateForRow(row) {
        var course = courseById(field(row, 'course_id').value);
        var startDate = field(row, 'start_date').value;

        if (!course || !startDate) {
            field(row, 'end_date').value = '';
            return;
        }

        field(row, 'end_date').value =
            calculateInclusiveEndDate(
                startDate,
                course.duration_value,
                course.duration_unit
            );
    }

    function reindexRows() {
        Array.prototype.forEach.call(
            rowContainer.querySelectorAll('[data-batch-row]'),
            function (row, index) {
                row.dataset.index = String(index);

                ['ref','course_id','batch_code','batch_name','start_date','end_date','maximum_students','status','notes']
                    .forEach(function (name) {
                        var control = field(row, name);
                        if (control) {
                            control.name = 'batches[' + index + '][' + name + ']';
                        }
                    });

                Array.prototype.forEach.call(
                    field(row, 'working_days').querySelectorAll('input[type="checkbox"]'),
                    function (checkbox) {
                        checkbox.name = 'batches[' + index + '][working_days][]';
                    }
                );
            }
        );

        el('emptyRows').hidden = rowContainer.querySelectorAll('[data-batch-row]').length > 0;
    }

    function applyRowState(row) {
        var isExisting = String(field(row, 'ref').value || '').trim() !== '';
        var admissionCount = Number(row.dataset.admissionCount || 0);
        var attendanceCount = Number(row.dataset.attendanceSessionCount || 0);
        var hasCourse = String(field(row, 'course_id').value || '').trim() !== '';

        field(row, 'batch_code').readOnly = true;
        field(row, 'end_date').readOnly = true;

        ['batch_name','maximum_students','notes'].forEach(function (name) {
            field(row, name).readOnly = viewMode;
        });

        field(row, 'course_id').disabled =
            viewMode || (isExisting && admissionCount > 0);

        field(row, 'start_date').disabled =
            viewMode || !hasCourse || (isExisting && attendanceCount > 0);

        field(row, 'status').disabled = viewMode;

        Array.prototype.forEach.call(
            field(row, 'working_days').querySelectorAll('input[type="checkbox"]'),
            function (checkbox) {
                checkbox.disabled = viewMode;
            }
        );

        row.querySelector('.remove-batch-row').hidden =
            viewMode ||
            (isExisting && (admissionCount > 0 || attendanceCount > 0));

        syncRowSelects(row);
    }

    function initRowSelects(row) {
        if (!window.GlobalSelect) return;

        row._courseSelect = GlobalSelect.init(
            field(row, 'course_id'),
            {placeholder:'Select Course'}
        );

        row._statusSelect = GlobalSelect.init(
            field(row, 'status'),
            {placeholder:'Select Status'}
        );
    }

    function syncRowSelects(row) {
        if (row._courseSelect && typeof row._courseSelect.sync === 'function') {
            row._courseSelect.sync();
        }
        if (row._statusSelect && typeof row._statusSelect.sync === 'function') {
            row._statusSelect.sync();
        }
    }

    function addRow(data) {
        data = data || {};
        rowContainer.appendChild(rowTemplate.content.cloneNode(true));
        var row = rowContainer.lastElementChild;

        field(row, 'ref').value = data.ref || '';
        populateCourseSelect(field(row, 'course_id'), data.course_id || '');
        field(row, 'batch_code').value = data.batch_code || '';
        field(row, 'batch_name').value = data.batch_name || '';
        field(row, 'start_date').value = data.start_date || '';
        field(row, 'end_date').value = data.end_date || '';
        field(row, 'maximum_students').value = data.maximum_students == null ? '' : String(data.maximum_students);
        field(row, 'maximum_students').dataset.autofilled = data.ref ? '0' : '1';
        field(row, 'status').value = Number(data.status) === 0 ? '0' : '1';
        field(row, 'notes').value = data.notes || '';
        setWorkingDays(row, data.working_days || []);

        row.dataset.admissionCount =
            String(Number(data.admission_count || 0));
        row.dataset.activeAdmissionCount =
            String(Number(data.active_admission_count || 0));
        row.dataset.attendanceSessionCount =
            String(Number(data.attendance_session_count || 0));

        if (data.ref) {
            var audit = row.querySelector('.batch-audit-row');
            audit.hidden = false;
            field(row, 'created_by_name').value = data.created_by_name || '-';
            field(row, 'created_at').value = data.created_at || '-';
            field(row, 'updated_at').value = data.updated_at || '-';
        }

        initRowSelects(row);
        syncRowSelects(row);
        applyRowState(row);
        reindexRows();
        refreshNewBatchCodes();

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

        var ref = String(field(row, 'ref').value || '').trim();

        if (ref !== '') {
            if (!has(4)) {
                showToast('You do not have Delete permission for this Batch.', {type:'danger',duration:3});
                return;
            }

            if (!window.confirm('Remove this existing Batch? It will be deleted when you save.')) {
                return;
            }

            removedBatches.push(ref);
        }

        row.remove();
        reindexRows();
        refreshNewBatchCodes();
    }

    function applyCourseSelection(row, forceMaximumDefault) {
        var course = courseById(field(row, 'course_id').value);
        var maxInput = field(row, 'maximum_students');

        if (!course) {
            if (maxInput.dataset.autofilled === '1') {
                maxInput.value = '';
            }

            field(row, 'end_date').value = '';
            applyRowState(row);
            return;
        }

        if (
            forceMaximumDefault ||
            maxInput.value.trim() === '' ||
            maxInput.dataset.autofilled === '1'
        ) {
            maxInput.value =
                course.maximum_students == null
                    ? ''
                    : String(course.maximum_students);

            maxInput.dataset.autofilled = '1';
        }

        calculateEndDateForRow(row);
        applyRowState(row);
    }

    function normalizeRows() {
        Array.prototype.forEach.call(
            rowContainer.querySelectorAll('[data-batch-row]'),
            function (row) {
                field(row, 'batch_name').value = String(field(row, 'batch_name').value || '').trim();
                field(row, 'notes').value = String(field(row, 'notes').value || '').trim();

                if (window.Validation && typeof Validation.formatField === 'function') {
                    Validation.formatField(field(row, 'maximum_students'));
                }
            }
        );
    }

    function validateRows() {
        var rows = Array.prototype.slice.call(
            rowContainer.querySelectorAll('[data-batch-row]')
        );
        var valid = true;
        var duplicateKeys = {};

        if (!rows.length && !removedBatches.length) {
            showToast(
                'Add at least one Batch row.',
                {type:'danger',duration:3}
            );
            return false;
        }

        rows.forEach(function (row, index) {
            clearWorkingDaysError(row);

            var courseId = String(field(row, 'course_id').value || '');
            var batchName =
                String(field(row, 'batch_name').value || '')
                    .trim()
                    .toLocaleLowerCase();
            var startDate = field(row, 'start_date').value;
            var endDate = field(row, 'end_date').value;
            var activeAdmissionCount =
                Number(row.dataset.activeAdmissionCount || 0);
            var maximumStudents =
                String(field(row, 'maximum_students').value || '').trim();

            if (courseId && !field(row, 'start_date').disabled && !startDate) {
                valid = false;
            }

            if (selectedWorkingDays(row).length < 1) {
                showWorkingDaysError(
                    row,
                    'Select at least one Working Day.'
                );

                dangerToast(
                    'Batch Row ' +
                    (index + 1) +
                    ': Select at least one Working Day.'
                );

                valid = false;
            }

            if (courseId && startDate && !endDate) {
                if (
                    window.Validation &&
                    typeof Validation.applyErrors === 'function'
                ) {
                    var endError = {};
                    endError[field(row, 'end_date').name] =
                        'Unable to calculate End Date from Course duration.';
                    Validation.applyErrors(form, endError);
                }

                dangerToast(
                    'Batch Row ' +
                    (index + 1) +
                    ': Unable to calculate End Date from Course duration.'
                );

                valid = false;
            }

            if (
                activeAdmissionCount > 0 &&
                maximumStudents !== '' &&
                Number(maximumStudents) < activeAdmissionCount
            ) {
                if (
                    window.Validation &&
                    typeof Validation.applyErrors === 'function'
                ) {
                    var capacityError = {};
                    capacityError[field(row, 'maximum_students').name] =
                        'Maximum Students cannot be less than current active admissions (' +
                        activeAdmissionCount +
                        ').';
                    Validation.applyErrors(form, capacityError);
                }

                dangerToast(
                    'Batch Row ' +
                    (index + 1) +
                    ': Maximum Students cannot be less than current active admissions (' +
                    activeAdmissionCount +
                    ').'
                );

                valid = false;
            }

            if (courseId && batchName && startDate && endDate) {
                var duplicateKey = [
                    courseId,
                    batchName,
                    startDate,
                    endDate
                ].join('|');

                if (duplicateKeys[duplicateKey] !== undefined) {
                    if (
                        window.Validation &&
                        typeof Validation.applyErrors === 'function'
                    ) {
                        var duplicateError = {};
                        duplicateError[field(row, 'batch_name').name] =
                            'This exact Course Batch is already entered above.';
                        Validation.applyErrors(form, duplicateError);
                    }

                    dangerToast(
                        'Batch Row ' +
                        (index + 1) +
                        ': This exact Course Batch is already entered above.'
                    );

                    valid = false;
                } else {
                    duplicateKeys[duplicateKey] = index;
                }
            }
        });

        return valid;
    }

    function collectRows() {
        reindexRows();

        return Array.prototype.map.call(
            rowContainer.querySelectorAll('[data-batch-row]'),
            function (row) {
                return {
                    ref: String(field(row, 'ref').value || '').trim() || null,
                    course_id: field(row, 'course_id').value,
                    batch_code: field(row, 'batch_code').value,
                    batch_name: field(row, 'batch_name').value.trim(),
                    start_date: field(row, 'start_date').value,
                    end_date: field(row, 'end_date').value,
                    working_days: selectedWorkingDays(row),
                    maximum_students: field(row, 'maximum_students').value,
                    status: field(row, 'status').value,
                    notes: field(row, 'notes').value.trim()
                };
            }
        );
    }

    function applyMode() {
        if (pageRef && viewMode) {
            el('pageHeading').textContent = 'View Batch';
            el('pageDescription').textContent = 'View Batch details.';
            el('saveButton').hidden = true;
            el('addBatchButton').hidden = true;

            if (has(3)) {
                el('editButton').href = 'batch-form.php?ref=' + encodeURIComponent(pageRef);
                el('editButton').hidden = false;
            }
            return;
        }

        if (pageRef) {
            el('pageHeading').textContent = 'Edit Batch';
            el('pageDescription').textContent = 'Update the Batch and optionally add more new Batches.';
            el('saveButtonText').textContent = 'Save Changes';
            el('saveButton').disabled = !has(3);
            el('addBatchButton').hidden = !has(2);
        } else {
            el('pageHeading').textContent = 'Add Batches';
            el('pageDescription').textContent = 'Create one or multiple Course batches in the same form.';
            el('saveButtonText').textContent = 'Save All Batches';
            el('saveButton').disabled = !has(2);
            el('addBatchButton').hidden = !has(2);
        }
    }

    async function save(event) {
        event.preventDefault();

        if (viewMode || saving) return;

        if (window.Validation && typeof Validation.clearForm === 'function') {
            Validation.clearForm(form);
        }

        normalizeRows();
        reindexRows();

        var formValid = true;
        if (window.Validation && typeof Validation.validateForm === 'function') {
            formValid = Validation.validateForm(form);
        }

        var rowValid = validateRows();

        if (!formValid || !rowValid) {
            if (!formValid) {
                dangerToast(
                    'Please complete all required Batch fields correctly.'
                );
            }

            return;
        }

        saving = true;
        el('saveButton').disabled = true;

        try {
            var response = await App.api('api/batches.php', {
                method: 'POST',
                body: {
                    action: 'save',
                    batches: collectRows(),
                    removed_batches: removedBatches.slice()
                }
            });

            showToast(response.message || 'Batches saved successfully.', {type:'success',duration:2});
            window.location.href = 'batch-list.php';
        } catch (error) {
            saving = false;
            el('saveButton').disabled = false;

            if (window.Validation && typeof Validation.applyErrors === 'function') {
                var errors = (error && error.errors) || (error && error.data && error.data.errors) || {};
                Validation.applyErrors(form, errors);
            }

            dangerToast(
                errorMessage(
                    error,
                    'Unable to save Batches.'
                )
            );
        }
    }

    async function load() {
        try {
            var response;

            if (pageRef) {
                response = await App.api('api/batches.php?ref=' + encodeURIComponent(pageRef));
                actions = (response.data.allowed_actions || []).map(Number);
                courses = response.data.courses || [];
                baseNextBatchNumber = Number(response.data.next_batch_number || 1);
                addRow(response.data.batch || {});
            } else {
                response = await App.api('api/batches.php?options=1');
                actions = (response.data.allowed_actions || []).map(Number);
                courses = response.data.courses || [];
                baseNextBatchNumber = Number(response.data.next_batch_number || 1);
                addRow({status:1,working_days:['1','2','3','4','5']});
            }

            applyMode();

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            el('saveButton').disabled = true;
            el('addBatchButton').disabled = true;
            dangerToast(
                errorMessage(
                    error,
                    'Unable to load Batch form.'
                )
            );
        }
    }

    el('addBatchButton').addEventListener('click', function () {
        var row = addRow({status:1,working_days:['1','2','3','4','5']});
        field(row, 'course_id').focus();
    });

    rowContainer.addEventListener('click', function (event) {
        var button = event.target.closest('.remove-batch-row');
        if (!button) return;
        removeRow(button.closest('[data-batch-row]'));
    });

    rowContainer.addEventListener('change', function (event) {
        var row = event.target.closest('[data-batch-row]');
        if (!row) return;

        if (event.target.matches('[data-field="course_id"]')) {
            applyCourseSelection(row, true);
        }

        if (event.target.matches('[data-field="start_date"]')) {
            calculateEndDateForRow(row);
        }

        if (event.target.matches('[data-field="working_days"] input[type="checkbox"]')) {
            clearWorkingDaysError(row);
        }
    });

    rowContainer.addEventListener('input', function (event) {
        var row = event.target.closest('[data-batch-row]');
        if (!row) return;

        if (event.target.matches('[data-field="maximum_students"]')) {
            event.target.dataset.autofilled = '0';
        }
    });

    form.addEventListener('submit', save);
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