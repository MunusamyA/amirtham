<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Attendance';
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
        <h1 id="pageHeading">Add Attendance</h1>
        <p id="pageDescription">
            Mark Regular or Practical Attendance for all Students admitted to the selected Batch.
        </p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="attendance-list.php">
            <i data-lucide="list"></i>
            Attendance List
        </a>

        <a class="btn btn-primary" id="editButton" href="#" hidden>
            <i data-lucide="pencil"></i>
            Edit Attendance
        </a>
    </div>
</div>

<div class="card form-card">
<form id="attendanceForm" novalidate>
    <input id="attendanceRef" type="hidden">

    <div class="card-header">
        <div>
            <h2>Attendance Details</h2>
            <p>
                Use Regular Attendance for normal daily attendance. Use Practical Attendance when the attendance belongs to a Practical / Subject.
            </p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label for="attendanceType" class="required">Attendance Type</label>
                <select id="attendanceType" name="attendance_type" required>
                    <option value="1">Regular Attendance</option>
                    <option value="2">Practical Attendance</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="courseId" class="required">Course</label>
                <select
                    id="courseId"
                    name="course_id"
                    required
                    data-required-message="Course is required.">
                    <option value="">Select Course</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="batchId" class="required">Batch</label>
                <select
                    id="batchId"
                    name="batch_id"
                    required
                    data-required-message="Batch is required.">
                    <option value="">Select Batch</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="attendanceDate" class="required">Attendance Date</label>
                <input
                    id="attendanceDate"
                    name="attendance_date"
                    type="date"
                    required
                    data-required-message="Attendance Date is required.">
            </div>

            <div class="field col-4" id="subjectField" hidden>
                <label for="subjectId" class="required">Practical / Subject</label>
                <select id="subjectId" name="subject_id">
                    <option value="">Select Practical / Subject</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="status" class="required">Status</label>
                <select id="status" name="status" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-12">
                <label for="remarks">Remarks</label>
                <textarea
                    id="remarks"
                    name="remarks"
                    rows="2"
                    maxlength="255"
                    placeholder="Attendance remarks"></textarea>
            </div>
        </div>

        <div class="card-section-title">Student Attendance</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="studentSearch">Search Student</label>
                <input
                    id="studentSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Student Code, Name or Mobile">
            </div>

            <div class="field col-8">
                <label>Bulk Attendance</label>
                <div class="buttons">
                    <button class="btn gray bulk-mark" type="button" data-code="P">
                        Mark Selected Present
                    </button>
                    <button class="btn gray bulk-mark" type="button" data-code="A">
                        Mark Selected Absent
                    </button>
                    <button class="btn gray bulk-mark" type="button" data-code="L">
                        Mark Selected Leave
                    </button>
                    <button class="btn gray bulk-mark" type="button" data-code="LT">
                        Mark Selected Late
                    </button>
                </div>
            </div>
        </div>

        <div class="card table-card">
            <div class="table-scroll">
                <table class="data-table" id="studentAttendanceTable">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>
                            <input
                                id="selectAllStudents"
                                type="checkbox"
                                aria-label="Select all Students">
                        </th>
                        <th>Student Code</th>
                        <th>Student Name</th>
                        <th>Mobile</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Leave</th>
                        <th>Late</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody id="studentAttendanceBody">
                    <tr>
                        <td colspan="10">
                            Select Course, Batch and Attendance Date to load Students.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-section-title">Attendance Summary</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="totalStudents">Total Students</label>
                <input id="totalStudents" type="text" value="0" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label for="presentCount">Present</label>
                <input id="presentCount" type="text" value="0" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label for="absentCount">Absent</label>
                <input id="absentCount" type="text" value="0" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="leaveCount">Leave</label>
                <input id="leaveCount" type="text" value="0" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label for="lateCount">Late</label>
                <input id="lateCount" type="text" value="0" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label for="attendancePercentage">Attendance %</label>
                <input id="attendancePercentage" type="text" value="0.00%" readonly tabindex="-1">
            </div>
        </div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <a class="btn gray" href="attendance-list.php">Cancel</a>

            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                Save Attendance
            </button>
        </div>
    </div>
</form>
</div>

<script>
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search);
    var pageRef = params.get('ref') || '';
    var viewMode = params.get('view') === '1';

    var allowedActions = [];
    var courses = [];
    var batches = [];
    var subjects = [];
    var studentRows = [];
    var saving = false;
    var loadingStudents = false;

    var courseGlobal = null;
    var batchGlobal = null;
    var subjectGlobal = null;
    var typeGlobal = null;
    var statusGlobal = null;

    var form = document.getElementById('attendanceForm');

    function el(id) {
        return document.getElementById(id);
    }

    function has(actionId) {
        return (allowedActions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function showError(error, fallback) {
        var message =
            error && error.message
                ? String(error.message)
                : (
                    error &&
                    error.data &&
                    error.data.message
                        ? String(error.data.message)
                        : fallback
                );

        showToast(message, {
            type:'danger',
            duration:4
        });
    }

    function syncGlobal(instance) {
        if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }
    }

    function fillSelect(selectId, rows, selected, labelFn) {
        var select = el(selectId);

        select.innerHTML = '<option value="">Select</option>';

        (rows || []).forEach(function (row) {
            var option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = labelFn(row);
            select.appendChild(option);
        });

        select.value =
            selected == null
                ? ''
                : String(selected);

        if (selectId === 'courseId') {
            syncGlobal(courseGlobal);
        } else if (selectId === 'batchId') {
            syncGlobal(batchGlobal);
        } else if (selectId === 'subjectId') {
            syncGlobal(subjectGlobal);
        }
    }

    function setCourseOptions(selected) {
        var rows = courses.filter(function (row) {
            return pageRef || Number(row.status) === 1;
        });

        fillSelect(
            'courseId',
            rows,
            selected,
            function (row) {
                return row.course_code + ' - ' + row.course_name;
            }
        );
    }

    function setBatchOptions(selected) {
        var courseId = Number(el('courseId').value || 0);

        var rows = batches.filter(function (row) {
            return (
                Number(row.course_id) === courseId &&
                (pageRef || Number(row.status) === 1)
            );
        });

        fillSelect(
            'batchId',
            rows,
            selected,
            function (row) {
                return row.batch_code + ' - ' + row.batch_name;
            }
        );
    }

    function setSubjectOptions(selected) {
        var courseId = Number(el('courseId').value || 0);

        var rows = subjects.filter(function (row) {
            return (
                Number(row.course_id) === courseId &&
                [2,3].indexOf(Number(row.subject_type)) !== -1 &&
                (pageRef || Number(row.status) === 1)
            );
        });

        fillSelect(
            'subjectId',
            rows,
            selected,
            function (row) {
                return row.subject_code + ' - ' + row.subject_name;
            }
        );
    }

    function toggleSubject() {
        var practical =
            Number(el('attendanceType').value || 1) === 2;

        el('subjectField').hidden = !practical;
        el('subjectId').required = practical;

        if (!practical && !pageRef) {
            el('subjectId').value = '';
            syncGlobal(subjectGlobal);
        }
    }

    function identityReady() {
        return (
            Number(el('courseId').value || 0) > 0 &&
            Number(el('batchId').value || 0) > 0 &&
            !!el('attendanceDate').value
        );
    }

    function statusCell(index, code, current) {
        return (
            '<td>' +
                '<input class="attendance-status" type="checkbox" ' +
                'data-row="' + index + '" ' +
                'data-code="' + code + '" ' +
                (current === code ? 'checked ' : '') +
                'aria-label="' + code + '">' +
            '</td>'
        );
    }

    function renderStudents(rows) {
        studentRows = (rows || []).map(function (row) {
            return {
                admission_ref:row.admission_ref,
                admission_no:row.admission_no || '',
                student_code:row.student_code || '',
                student_name:row.student_name || '',
                mobile:row.mobile || '',
                attendance_code:row.attendance_code || 'P',
                remarks:row.remarks || '',
                selected:true
            };
        });

        var tbody = el('studentAttendanceBody');

        if (studentRows.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="10">No Students available for this Batch and Date.</td></tr>';

            el('selectAllStudents').checked = false;
            updateSummary();
            return;
        }

        tbody.innerHTML =
            studentRows.map(function (row, index) {
                return (
                    '<tr data-row="' + index + '">' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td>' +
                            '<input class="student-select" type="checkbox" ' +
                            'data-row="' + index + '" checked>' +
                        '</td>' +
                        '<td>' + esc(row.student_code) + '</td>' +
                        '<td>' + esc(row.student_name) + '</td>' +
                        '<td>' + esc(row.mobile || '-') + '</td>' +
                        statusCell(index, 'P', row.attendance_code) +
                        statusCell(index, 'A', row.attendance_code) +
                        statusCell(index, 'L', row.attendance_code) +
                        statusCell(index, 'LT', row.attendance_code) +
                        '<td>' +
                            '<input class="student-remarks" type="text" ' +
                            'maxlength="255" data-row="' + index + '" ' +
                            'value="' + esc(row.remarks) + '" ' +
                            'placeholder="Remarks">' +
                        '</td>' +
                    '</tr>'
                );
            }).join('');

        el('selectAllStudents').checked = true;
        bindStudentRows();

        if (viewMode) {
            applyStudentReadOnly();
        }

        updateSummary();
        applySearch();
    }

    function markRow(index, code) {
        if (!studentRows[index]) return;

        studentRows[index].attendance_code = code;

        document.querySelectorAll(
            '.attendance-status[data-row="' + index + '"]'
        ).forEach(function (input) {
            input.checked = input.dataset.code === code;
        });

        updateSummary();
    }

    function bindStudentRows() {
        document.querySelectorAll('.student-select')
            .forEach(function (input) {
                input.addEventListener('change', function () {
                    var index = Number(this.dataset.row);

                    if (studentRows[index]) {
                        studentRows[index].selected = this.checked;
                    }

                    syncSelectAll();
                });
            });

        document.querySelectorAll('.attendance-status')
            .forEach(function (input) {
                input.addEventListener('change', function () {
                    if (viewMode) return;

                    var index = Number(this.dataset.row);
                    var code = this.dataset.code;

                    if (this.checked) {
                        markRow(index, code);
                        return;
                    }

                    var rowChecks =
                        Array.from(
                            document.querySelectorAll(
                                '.attendance-status[data-row="' + index + '"]'
                            )
                        );

                    if (!rowChecks.some(function (item) {
                        return item.checked;
                    })) {
                        this.checked = true;
                    }
                });
            });

        document.querySelectorAll('.student-remarks')
            .forEach(function (input) {
                input.addEventListener('input', function () {
                    var index = Number(this.dataset.row);

                    if (studentRows[index]) {
                        studentRows[index].remarks = this.value;
                    }
                });
            });
    }

    function syncSelectAll() {
        el('selectAllStudents').checked =
            studentRows.length > 0 &&
            studentRows.every(function (row) {
                return row.selected;
            });
    }

    function updateSummary() {
        var counts = {P:0,A:0,L:0,LT:0};

        studentRows.forEach(function (row) {
            if (counts[row.attendance_code] !== undefined) {
                counts[row.attendance_code] += 1;
            }
        });

        var total = studentRows.length;

        el('totalStudents').value = String(total);
        el('presentCount').value = String(counts.P);
        el('absentCount').value = String(counts.A);
        el('leaveCount').value = String(counts.L);
        el('lateCount').value = String(counts.LT);
        el('attendancePercentage').value =
            total > 0
                ? ((counts.P / total) * 100).toFixed(2) + '%'
                : '0.00%';
    }

    function applySearch() {
        var search =
            el('studentSearch').value.trim().toLowerCase();

        document.querySelectorAll(
            '#studentAttendanceBody tr[data-row]'
        ).forEach(function (tr) {
            var row = studentRows[Number(tr.dataset.row)];

            var haystack = row
                ? [
                    row.student_code,
                    row.student_name,
                    row.mobile
                ].join(' ').toLowerCase()
                : '';

            tr.hidden =
                !!search &&
                haystack.indexOf(search) === -1;
        });
    }

    async function loadStudents() {
        if (
            pageRef ||
            viewMode ||
            loadingStudents ||
            !identityReady()
        ) {
            return;
        }

        loadingStudents = true;

        try {
            var query = new URLSearchParams({
                students:'1',
                course_id:el('courseId').value,
                batch_id:el('batchId').value,
                attendance_date:el('attendanceDate').value
            });

            var response =
                await App.api(
                    'api/attendance.php?' + query.toString()
                );

            renderStudents(response.data.students || []);
        } catch (error) {
            studentRows = [];
            el('studentAttendanceBody').innerHTML =
                '<tr><td colspan="10">Unable to load Students.</td></tr>';

            updateSummary();

            showError(
                error,
                'Unable to load Students.'
            );
        } finally {
            loadingStudents = false;
        }
    }

    function attendancePayloadStudents() {
        return studentRows.map(function (row) {
            return {
                admission_ref:row.admission_ref,
                attendance_code:row.attendance_code,
                remarks:String(row.remarks || '').trim()
            };
        });
    }

    function normalizeFields() {
        el('remarks').value = el('remarks').value.trim();

        studentRows.forEach(function (row) {
            row.remarks = String(row.remarks || '').trim();
        });
    }

    function validateBusinessRules() {
        if (studentRows.length === 0) {
            showToast(
                'No Students are available to save Attendance.',
                {type:'danger',duration:4}
            );
            return false;
        }

        if (
            Number(el('attendanceType').value) === 2 &&
            !el('subjectId').value
        ) {
            showToast(
                'Practical / Subject is required for Practical Attendance.',
                {type:'danger',duration:4}
            );
            return false;
        }

        return true;
    }

    function lockIdentity() {
        [
            'attendanceType',
            'courseId',
            'batchId',
            'attendanceDate',
            'subjectId'
        ].forEach(function (id) {
            el(id).disabled = true;
        });
    }

    function applyStudentReadOnly() {
        document.querySelectorAll(
            '.student-select,.attendance-status'
        ).forEach(function (input) {
            input.disabled = true;
        });

        document.querySelectorAll('.student-remarks')
            .forEach(function (input) {
                input.readOnly = true;
            });

        el('selectAllStudents').disabled = true;

        document.querySelectorAll('.bulk-mark')
            .forEach(function (button) {
                button.hidden = true;
            });
    }

    function applyViewMode() {
        el('pageHeading').textContent = 'View Attendance';
        el('pageDescription').textContent =
            'View saved Student Attendance.';

        el('saveButton').hidden = true;

        lockIdentity();
        el('status').disabled = true;
        el('remarks').readOnly = true;
        applyStudentReadOnly();

        if (has(3)) {
            el('editButton').hidden = false;
            el('editButton').href =
                'attendance-form.php?ref=' +
                encodeURIComponent(pageRef);
        }
    }

    async function loadRecord() {
        var response =
            await App.api(
                'api/attendance.php?ref=' +
                encodeURIComponent(pageRef)
            );

        allowedActions = response.data.allowed_actions || [];
        courses = response.data.options.courses || [];
        batches = response.data.options.batches || [];
        subjects = response.data.options.subjects || [];

        var session = response.data.session || {};

        el('attendanceType').value =
            String(session.attendance_type || 1);

        el('status').value =
            String(
                session.status == null
                    ? 1
                    : session.status
            );

        syncGlobal(typeGlobal);
        syncGlobal(statusGlobal);

        setCourseOptions(session.course_id || '');
        setBatchOptions(session.batch_id || '');
        setSubjectOptions(session.subject_id || '');

        el('attendanceRef').value = session.ref || '';
        el('attendanceDate').value = session.attendance_date || '';
        el('remarks').value = session.topic || '';

        toggleSubject();
        renderStudents(response.data.students || []);

        lockIdentity();

        el('pageHeading').textContent =
            viewMode
                ? 'View Attendance'
                : 'Edit Attendance';

        el('pageDescription').textContent =
            (
                Number(session.attendance_type) === 2
                    ? 'Practical Attendance'
                    : 'Regular Attendance'
            ) +
            ' · ' +
            (session.course_name || '') +
            ' · ' +
            (session.batch_name || '');

        if (viewMode) {
            applyViewMode();
        }
    }

    async function load() {
        try {
            typeGlobal =
                GlobalSelect.init('#attendanceType', {
                    placeholder:'Select Attendance Type'
                });

            courseGlobal =
                GlobalSelect.init('#courseId', {
                    placeholder:'Select Course'
                });

            batchGlobal =
                GlobalSelect.init('#batchId', {
                    placeholder:'Select Batch'
                });

            subjectGlobal =
                GlobalSelect.init('#subjectId', {
                    placeholder:'Select Practical / Subject'
                });

            statusGlobal =
                GlobalSelect.init('#status', {
                    placeholder:'Select Status'
                });

            if (pageRef) {
                await loadRecord();
            } else {
                var response =
                    await App.api(
                        'api/attendance.php?options=1'
                    );

                allowedActions = response.data.allowed_actions || [];
                courses = response.data.options.courses || [];
                batches = response.data.options.batches || [];
                subjects = response.data.options.subjects || [];

                setCourseOptions('');
                setBatchOptions('');
                setSubjectOptions('');

                el('attendanceType').value = '1';
                el('status').value = '1';
                el('attendanceDate').value = response.data.today || '';

                syncGlobal(typeGlobal);
                syncGlobal(statusGlobal);
                toggleSubject();

                if (!has(2)) {
                    el('saveButton').hidden = true;
                }
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            showError(error, 'Unable to load Attendance.');
            el('saveButton').hidden = true;
        }
    }

    el('attendanceType').addEventListener('change', function () {
        toggleSubject();

        if (!pageRef) {
            setSubjectOptions('');
        }
    });

    el('courseId').addEventListener('change', function () {
        if (pageRef) return;

        setBatchOptions('');
        setSubjectOptions('');

        studentRows = [];
        el('studentAttendanceBody').innerHTML =
            '<tr><td colspan="10">Select Batch and Attendance Date to load Students.</td></tr>';

        updateSummary();
    });

    el('batchId').addEventListener('change', loadStudents);
    el('attendanceDate').addEventListener('change', loadStudents);
    el('studentSearch').addEventListener('input', applySearch);

    el('selectAllStudents').addEventListener('change', function () {
        if (viewMode) return;

        var checked = this.checked;

        studentRows.forEach(function (row, index) {
            row.selected = checked;

            var input =
                document.querySelector(
                    '.student-select[data-row="' + index + '"]'
                );

            if (input) {
                input.checked = checked;
            }
        });
    });

    document.querySelectorAll('.bulk-mark')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                if (viewMode) return;

                var code = this.dataset.code;
                var changed = false;

                studentRows.forEach(function (row, index) {
                    if (row.selected) {
                        markRow(index, code);
                        changed = true;
                    }
                });

                if (!changed) {
                    showToast(
                        'Select at least one Student for bulk Attendance.',
                        {type:'warning',duration:3}
                    );
                }
            });
        });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (saving || viewMode) return;

        if (window.Validation) {
            Validation.clearForm(form);
        }

        normalizeFields();

        if (
            window.Validation &&
            !Validation.validateForm(form)
        ) {
            return;
        }

        if (!validateBusinessRules()) {
            return;
        }

        saving = true;
        el('saveButton').disabled = true;

        try {
            var response =
                await App.api(
                    'api/attendance.php',
                    {
                        method:'POST',
                        body:{
                            action:'save',
                            ref:el('attendanceRef').value || '',
                            attendance_type:Number(el('attendanceType').value),
                            course_id:Number(el('courseId').value),
                            batch_id:Number(el('batchId').value),
                            attendance_date:el('attendanceDate').value,
                            subject_id:
                                Number(el('attendanceType').value) === 2
                                    ? (el('subjectId').value || '')
                                    : '',
                            status:Number(el('status').value),
                            remarks:el('remarks').value,
                            students:attendancePayloadStudents()
                        }
                    }
                );

            showToast(
                response.message ||
                'Attendance saved successfully.',
                {type:'success',duration:2}
            );

            window.location.href = 'attendance-list.php';
        } catch (error) {
            if (
                window.Validation &&
                error &&
                error.errors
            ) {
                Validation.applyErrors(
                    form,
                    error.errors
                );
            }

            showError(
                error,
                'Unable to save Attendance.'
            );
        } finally {
            saving = false;
            el('saveButton').disabled = false;
        }
    });

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
