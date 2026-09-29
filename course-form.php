<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Course Form';
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
                    <h1 id="pageHeading">Add Course</h1>
                    <p id="pageDescription">Create the Course and its Subjects / Modules in the same form.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn gray" href="course-list.php">
                        <i data-lucide="list"></i>
                        Course List
                    </a>
                    <a class="btn btn-primary" id="editButton" href="#" hidden>
                        <i data-lucide="pencil"></i>
                        Edit Course
                    </a>
                </div>
            </div>

            <div class="card form-card">
                <form id="courseForm" novalidate>
                    <div class="card-header">
                        <div>
                            <h2>Course Information</h2>
                            <p>Course Master and Course Subjects / Modules are maintained together.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="card-section-title">Course Details</div>

                        <div class="form-row">
                            <div class="field col-4">
                                <label for="courseCode" class="required">Course Code</label>
                                <input id="courseCode" name="course_code" type="text" maxlength="50"
                                       placeholder="Auto generated" required readonly aria-readonly="true"
                                       data-required-message="Course Code is required.">
                            </div>

                            <div class="field col-8">
                                <label for="courseName" class="required">Course Name</label>
                                <input id="courseName" name="course_name" type="text" maxlength="180"
                                       placeholder="Enter Course Name" required
                                       data-required-message="Course Name is required.">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-3">
                                <label for="durationValue" class="required">Duration</label>
                                <input id="durationValue" name="duration_value" type="text" inputmode="numeric"
                                       maxlength="10" required placeholder="6"
                                       data-validation="integer"
                                       data-regex="^[1-9][0-9]*$"
                                       data-required-message="Duration is required."
                                       data-integer-message="Duration must be a whole number."
                                       data-regex-message="Duration must be greater than zero.">
                            </div>

                            <div class="field col-3">
                                <label for="durationUnit" class="required">Duration Unit</label>
                                <select id="durationUnit" name="duration_unit" required
                                        data-required-message="Duration Unit is required.">
                                    <option value="">Select</option>
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                    <option value="years">Years</option>
                                </select>
                            </div>

                            <div class="field col-3">
                                <label for="maximumStudents">Maximum Students</label>
                                <input id="maximumStudents" name="maximum_students" type="text" inputmode="numeric"
                                       maxlength="10" placeholder="Optional"
                                       data-validation="integer"
                                       data-regex="^[1-9][0-9]*$"
                                       data-integer-message="Maximum Students must be a whole number."
                                       data-regex-message="Maximum Students must be greater than zero.">
                            </div>

                            <div class="field col-3">
                                <label for="courseStatus" class="required">Status</label>
                                <select id="courseStatus" name="status" required
                                        data-required-message="Status is required.">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-12">
                                <label for="eligibility">Eligibility</label>
                                <input id="eligibility" name="eligibility" type="text" maxlength="255"
                                       placeholder="Example: 10th Pass / 12th Pass / Any Graduate">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field col-12">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"
                                          placeholder="Course description, objective or notes"></textarea>
                            </div>
                        </div>

                        <div class="card-section-title"
                             style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <span>Subjects / Modules</span>
                            <button class="btn btn-primary small" id="addSubjectButton" type="button">
                                <i data-lucide="plus"></i>
                                Add More
                            </button>
                        </div>

                        <div class="muted" style="margin-bottom:12px;">
                            Subject Code is generated automatically. Add any number of subjects/modules for this Course.
                        </div>

                        <div id="subjectRows"></div>

                        <div id="emptySubjects" class="muted" style="padding:12px 0;">
                            No Subjects / Modules added yet. Click Add More to add one.
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
                        <a class="btn gray" href="course-list.php">
                            <i data-lucide="x"></i>
                            Cancel
                        </a>
                        <button class="btn btn-primary" id="saveButton" type="submit">
                            <i data-lucide="save"></i>
                            <span id="saveButtonText">Save Course</span>
                        </button>
                    </div>
                </form>
            </div>

            <template id="subjectRowTemplate">
                <div class="subject-row-card" data-subject-row
                     style="border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;margin-bottom:14px;background:var(--surface);">
                    <input type="hidden" data-field="ref">

                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
                        <strong data-row-title>Subject 1</strong>
                        <button class="btn red small remove-subject-row" type="button">
                            <i data-lucide="trash-2"></i>
                            Remove
                        </button>
                    </div>

                    <div class="form-row">
                        <div class="field col-3">
                            <label>Subject Code</label>
                            <input data-field="subject_code" type="text"
                                   placeholder="Auto generated" readonly aria-readonly="true">
                        </div>

                        <div class="field col-5">
                            <label class="required">Subject / Module Name</label>
                            <input data-field="subject_name" type="text" maxlength="180" required
                                   placeholder="Enter Subject / Module Name"
                                   data-required-message="Subject / Module Name is required.">
                        </div>

                        <div class="field col-4">
                            <label class="required">Subject Type</label>
                            <select data-field="subject_type" required
                                    data-required-message="Subject Type is required.">
                                <option value="">Select Type</option>
                                <option value="1">Theory</option>
                                <option value="2">Practical</option>
                                <option value="3">Theory + Practical</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field col-3">
                            <label>Maximum Marks</label>
                            <input data-field="maximum_marks" type="text" inputmode="decimal" maxlength="12"
                                   placeholder="Optional"
                                   data-validation="decimal" data-decimal-places="2"
                                   data-regex="^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$"
                                   data-decimal-message="Enter valid Maximum Marks."
                                   data-regex-message="Maximum Marks cannot be negative.">
                        </div>

                        <div class="field col-3">
                            <label>Pass Marks</label>
                            <input data-field="pass_marks" type="text" inputmode="decimal" maxlength="12"
                                   placeholder="Optional"
                                   data-validation="decimal" data-decimal-places="2"
                                   data-regex="^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$"
                                   data-decimal-message="Enter valid Pass Marks."
                                   data-regex-message="Pass Marks cannot be negative.">
                        </div>

                        <div class="field col-2">
                            <label class="required">Mandatory</label>
                            <select data-field="mandatory" required
                                    data-required-message="Mandatory selection is required.">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="field col-2">
                            <label>Sort Order</label>
                            <input data-field="sort_order" type="text" inputmode="numeric" maxlength="10"
                                   value="0" placeholder="0"
                                   data-validation="integer"
                                   data-regex="^[0-9]+$"
                                   data-integer-message="Sort Order must be a whole number."
                                   data-regex-message="Sort Order cannot be negative.">
                        </div>

                        <div class="field col-2">
                            <label class="required">Status</label>
                            <select data-field="status" required
                                    data-required-message="Status is required.">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field col-12">
                            <label>Description</label>
                            <textarea data-field="description" rows="3"
                                      placeholder="Subject / module description, syllabus summary or notes"></textarea>
                        </div>
                    </div>
                </div>
            </template>

            <script>
            (function () {
                'use strict';

                var form = document.getElementById('courseForm');
                var params = new URLSearchParams(window.location.search);
                var ref = params.get('ref') || '';
                var viewMode = params.get('view') === '1';
                var actions = [];
                var saving = false;
                var durationSelect = null;
                var statusSelect = null;
                var removedSubjects = [];

                var rowContainer = document.getElementById('subjectRows');
                var rowTemplate = document.getElementById('subjectRowTemplate');

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
                    if (error && error.data && error.data.errors && typeof error.data.errors === 'object') {
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

                function updateEmptyState() {
                    el('emptySubjects').hidden =
                        rowContainer.querySelectorAll('[data-subject-row]').length > 0;
                }

                function reindexSubjects() {
                    var rows = Array.prototype.slice.call(
                        rowContainer.querySelectorAll('[data-subject-row]')
                    );

                    rows.forEach(function (row, index) {
                        row.dataset.index = String(index);
                        row.querySelector('[data-row-title]').textContent =
                            'Subject / Module ' + (index + 1);

                        [
                            'ref','subject_name','subject_type','maximum_marks',
                            'pass_marks','mandatory','sort_order','status','description'
                        ].forEach(function (name) {
                            var control = field(row, name);
                            if (control) {
                                control.name = 'subjects[' + index + '][' + name + ']';
                            }
                        });
                    });

                    updateEmptyState();
                }

                function setSubjectRowReadOnly(row, readOnly) {
                    ['subject_name','maximum_marks','pass_marks','sort_order','description']
                        .forEach(function (name) {
                            field(row, name).readOnly = readOnly;
                        });

                    ['subject_type','mandatory','status'].forEach(function (name) {
                        field(row, name).disabled = readOnly;
                    });

                    row.querySelector('.remove-subject-row').hidden = readOnly;
                }

                function addSubjectRow(data) {
                    var fragment = rowTemplate.content.cloneNode(true);
                    rowContainer.appendChild(fragment);

                    var row = rowContainer.lastElementChild;
                    data = data || {};

                    field(row, 'ref').value = data.ref || '';
                    field(row, 'subject_code').value = data.subject_code || '';
                    field(row, 'subject_name').value = data.subject_name || '';
                    field(row, 'subject_type').value =
                        data.subject_type == null ? '1' : String(data.subject_type);
                    field(row, 'maximum_marks').value =
                        data.maximum_marks == null ? '' : String(data.maximum_marks);
                    field(row, 'pass_marks').value =
                        data.pass_marks == null ? '' : String(data.pass_marks);
                    field(row, 'mandatory').value =
                        Number(data.mandatory) === 0 ? '0' : '1';
                    field(row, 'sort_order').value =
                        data.sort_order == null
                            ? String(rowContainer.querySelectorAll('[data-subject-row]').length - 1)
                            : String(data.sort_order);
                    field(row, 'status').value =
                        Number(data.status) === 0 ? '0' : '1';
                    field(row, 'description').value = data.description || '';

                    setSubjectRowReadOnly(row, viewMode);
                    reindexSubjects();

                    if (window.Validation && typeof Validation.init === 'function') {
                        Validation.init(row);
                    }

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    return row;
                }

                function removeSubjectRow(row) {
                    if (!row) return;

                    var subjectRef = String(field(row, 'ref').value || '').trim();

                    if (subjectRef !== '') {
                        if (!has(4)) {
                            showToast('You do not have Delete permission for existing Subjects.', {
                                type:'danger',
                                duration:3
                            });
                            return;
                        }

                        if (!window.confirm(
                            'Remove this existing Subject / Module? This will be deleted when you save the Course.'
                        )) {
                            return;
                        }

                        removedSubjects.push(subjectRef);
                    }

                    row.remove();
                    reindexSubjects();
                }

                function normalizeFields() {
                    el('courseName').value = String(el('courseName').value || '').trim();
                    el('eligibility').value = String(el('eligibility').value || '').trim();
                    el('description').value = String(el('description').value || '').trim();

                    if (window.Validation && typeof Validation.formatField === 'function') {
                        Validation.formatField(el('durationValue'));
                        Validation.formatField(el('maximumStudents'));

                        Array.prototype.forEach.call(
                            rowContainer.querySelectorAll('[data-subject-row]'),
                            function (row) {
                                ['maximum_marks','pass_marks','sort_order'].forEach(function (name) {
                                    Validation.formatField(field(row, name));
                                });
                            }
                        );
                    }

                    Array.prototype.forEach.call(
                        rowContainer.querySelectorAll('[data-subject-row]'),
                        function (row) {
                            field(row, 'subject_name').value =
                                String(field(row, 'subject_name').value || '').trim();
                            field(row, 'description').value =
                                String(field(row, 'description').value || '').trim();
                        }
                    );
                }

                function decimalValue(control) {
                    var value = String(control.value || '').trim();
                    if (value === '' || !isFinite(Number(value))) return null;
                    return Number(value);
                }

                function validateSubjectBusinessRules() {
                    var errors = {};
                    var seenNames = {};
                    var rows = Array.prototype.slice.call(
                        rowContainer.querySelectorAll('[data-subject-row]')
                    );

                    rows.forEach(function (row, index) {
                        var name = String(field(row, 'subject_name').value || '').trim();
                        var key = name.toLocaleLowerCase();
                        var maximum = decimalValue(field(row, 'maximum_marks'));
                        var pass = decimalValue(field(row, 'pass_marks'));

                        if (key) {
                            if (seenNames[key] !== undefined) {
                                errors['subjects[' + index + '][subject_name]'] =
                                    'This Subject / Module is already entered above.';
                            } else {
                                seenNames[key] = index;
                            }
                        }

                        if ((maximum === null) !== (pass === null)) {
                            if (maximum === null) {
                                errors['subjects[' + index + '][maximum_marks]'] =
                                    'Enter Maximum Marks when Pass Marks is entered.';
                            }
                            if (pass === null) {
                                errors['subjects[' + index + '][pass_marks]'] =
                                    'Enter Pass Marks when Maximum Marks is entered.';
                            }
                        }

                        if (maximum !== null && maximum <= 0) {
                            errors['subjects[' + index + '][maximum_marks]'] =
                                'Maximum Marks must be greater than zero.';
                        }

                        if (maximum !== null && pass !== null && pass > maximum) {
                            errors['subjects[' + index + '][pass_marks]'] =
                                'Pass Marks cannot be greater than Maximum Marks.';
                        }
                    });

                    if (Object.keys(errors).length) {
                        if (window.Validation && typeof Validation.applyErrors === 'function') {
                            Validation.applyErrors(form, errors);
                        }
                        return false;
                    }

                    return true;
                }

                function validateChangedField(event) {
                    var control = event.target;
                    if (!control ||
                        !control.matches('[data-validation], [data-regex], [required]')) {
                        return;
                    }

                    if (window.Validation && typeof Validation.validateField === 'function') {
                        Validation.validateField(control, false);
                    }
                }

                function setReadOnly(readOnly) {
                    el('courseCode').readOnly = true;
                    el('courseCode').setAttribute('readonly', 'readonly');
                    el('courseCode').setAttribute('aria-readonly', 'true');

                    ['courseName','durationValue','maximumStudents','eligibility','description']
                        .forEach(function (id) {
                            el(id).readOnly = readOnly;
                        });

                    el('durationUnit').disabled = readOnly;
                    el('courseStatus').disabled = readOnly;

                    Array.prototype.forEach.call(
                        rowContainer.querySelectorAll('[data-subject-row]'),
                        function (row) {
                            setSubjectRowReadOnly(row, readOnly);
                        }
                    );

                    el('addSubjectButton').hidden = readOnly;
                }

                function applyMode() {
                    var heading = 'Add Course';
                    var description = 'Create the Course and its Subjects / Modules in the same form.';

                    if (ref && viewMode) {
                        heading = 'View Course';
                        description = 'View Course Master and its Subjects / Modules.';
                    } else if (ref) {
                        heading = 'Edit Course';
                        description = 'Update Course Master and manage its Subjects / Modules.';
                    }

                    el('pageHeading').textContent = heading;
                    el('pageDescription').textContent = description;
                    el('saveButtonText').textContent =
                        ref ? 'Update Course' : 'Save Course';

                    if (viewMode) {
                        setReadOnly(true);
                        el('saveButton').hidden = true;

                        if (ref && has(3)) {
                            el('editButton').href =
                                'course-form.php?ref=' + encodeURIComponent(ref);
                            el('editButton').hidden = false;
                        }
                    } else {
                        setReadOnly(false);
                        el('saveButton').hidden = false;
                        el('saveButton').disabled = ref ? !has(3) : !has(2);
                        el('addSubjectButton').hidden = ref ? !has(3) : !has(2);
                    }
                }

                function applyCourse(course) {
                    el('courseCode').value = course.course_code || '';
                    el('courseCode').readOnly = true;
                    el('courseName').value = course.course_name || '';
                    el('durationValue').value = course.duration_value || '';
                    setSelectValue(
                        el('durationUnit'),
                        course.duration_unit || '',
                        durationSelect
                    );
                    el('maximumStudents').value =
                        course.maximum_students == null ? '' : course.maximum_students;
                    el('eligibility').value = course.eligibility || '';
                    el('description').value = course.description || '';
                    setSelectValue(
                        el('courseStatus'),
                        Number(course.status) === 0 ? '0' : '1',
                        statusSelect
                    );
                    el('createdByName').value = course.created_by_name || '-';
                    el('createdAt').value = course.created_at || '-';
                    el('updatedAt').value = course.updated_at || '-';
                    el('auditRow').hidden = false;
                }

                function collectSubjects() {
                    reindexSubjects();

                    return Array.prototype.map.call(
                        rowContainer.querySelectorAll('[data-subject-row]'),
                        function (row) {
                            return {
                                ref: String(field(row, 'ref').value || '').trim() || null,
                                subject_name:
                                    String(field(row, 'subject_name').value || '').trim(),
                                subject_type: field(row, 'subject_type').value,
                                maximum_marks: field(row, 'maximum_marks').value,
                                pass_marks: field(row, 'pass_marks').value,
                                mandatory: field(row, 'mandatory').value,
                                sort_order: field(row, 'sort_order').value,
                                description:
                                    String(field(row, 'description').value || '').trim(),
                                status: field(row, 'status').value
                            };
                        }
                    );
                }

                function collect() {
                    return {
                        action: 'save',
                        ref: ref || undefined,
                        course_code: el('courseCode').value.trim(),
                        course_name: el('courseName').value.trim(),
                        duration_value: el('durationValue').value,
                        duration_unit: el('durationUnit').value,
                        maximum_students: el('maximumStudents').value,
                        eligibility: el('eligibility').value.trim(),
                        description: el('description').value.trim(),
                        status: el('courseStatus').value,
                        subjects: collectSubjects(),
                        removed_subjects: removedSubjects.slice()
                    };
                }

                async function saveCourse(event) {
                    event.preventDefault();
                    if (viewMode || saving) return;

                    if (window.Validation && typeof Validation.clearForm === 'function') {
                        Validation.clearForm(form);
                    }

                    normalizeFields();
                    reindexSubjects();

                    if (window.Validation && typeof Validation.validateForm === 'function') {
                        if (!Validation.validateForm(form)) return;
                    }

                    if (!validateSubjectBusinessRules()) return;

                    saving = true;
                    el('saveButton').disabled = true;

                    try {
                        var response = await App.api('api/courses.php', {
                            method: 'POST',
                            body: collect()
                        });

                        showToast(
                            response.message || 'Course saved successfully.',
                            {type:'success',duration:2}
                        );

                        window.location.href = 'course-list.php';
                    } catch (error) {
                        saving = false;
                        el('saveButton').disabled = false;

                        if (window.Validation && typeof Validation.applyErrors === 'function') {
                            Validation.applyErrors(form, extractErrors(error));
                        }

                        App.showError(error, 'Unable to save Course.');
                    }
                }

                async function load() {
                    try {
                        if (window.GlobalSelect) {
                            durationSelect = GlobalSelect.init(
                                el('durationUnit'),
                                {placeholder:'Select Duration Unit'}
                            );
                            statusSelect = GlobalSelect.init(
                                el('courseStatus'),
                                {placeholder:'Select Status'}
                            );
                        }

                        var options = await App.api('api/courses.php?options=1');
                        actions = (options.data.allowed_actions || []).map(Number);

                        if (!ref) {
                            el('courseCode').value = options.data.next_course_code || '';
                            el('durationValue').value = '6';
                            setSelectValue(el('durationUnit'), 'months', durationSelect);
                            setSelectValue(el('courseStatus'), '1', statusSelect);

                            addSubjectRow({
                                subject_type:1,
                                mandatory:1,
                                sort_order:0,
                                status:1
                            });
                        } else {
                            var response = await App.api(
                                'api/courses.php?ref=' + encodeURIComponent(ref)
                            );

                            actions =
                                (response.data.allowed_actions || actions).map(Number);

                            applyCourse(response.data.course || {});

                            (response.data.subjects || []).forEach(function (subject) {
                                addSubjectRow(subject);
                            });
                        }

                        applyMode();

                        if (window.Validation && typeof Validation.init === 'function') {
                            Validation.init(form);
                        }

                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    } catch (error) {
                        el('saveButton').disabled = true;
                        el('addSubjectButton').disabled = true;
                        App.showError(error, 'Unable to prepare Course Form.');
                    }
                }

                el('addSubjectButton').addEventListener('click', function () {
                    var row = addSubjectRow({
                        subject_type:1,
                        mandatory:1,
                        sort_order:
                            rowContainer.querySelectorAll('[data-subject-row]').length,
                        status:1
                    });
                    field(row, 'subject_name').focus();
                });

                rowContainer.addEventListener('click', function (event) {
                    var button = event.target.closest('.remove-subject-row');
                    if (!button) return;
                    removeSubjectRow(button.closest('[data-subject-row]'));
                });

                form.addEventListener('change', validateChangedField);
                form.addEventListener('submit', saveCourse);

                load();
            })();
            </script>
        </section>

        <?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
