<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Student Profile';
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

<div class="page-head">
    <div>
        <h1 id="profileHeading">Student Profile</h1>
        <p id="profileDescription">Complete Student, Admission, Fee, Payment and Attendance history.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="student-list.php">
            <i data-lucide="list"></i>
            Student List
        </a>

        <a class="btn btn-primary" id="editStudentButton" href="#" hidden>
            <i data-lucide="pencil"></i>
            Edit Student
        </a>
    </div>
</div>

<div class="card form-card">
    <div class="card-header">
        <div>
            <h2>Personal Information</h2>
            <p id="studentStatusText">Student details</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label>Student Code</label>
                <input id="studentCode" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Student Name</label>
                <input id="studentName" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Date of Birth</label>
                <input id="dateOfBirth" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label>Gender</label>
                <input id="gender" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Mobile</label>
                <input id="mobile" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Email</label>
                <input id="email" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label>Qualification</label>
                <input id="qualification" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Guardian Name</label>
                <input id="guardianName" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Guardian Mobile</label>
                <input id="guardianMobile" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label>Identity Number</label>
                <input id="identityNumber" type="text" readonly tabindex="-1">
            </div>
            <div class="field col-4">
                <label>Status</label>
                <input id="status" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-12">
                <label>Address</label>
                <textarea id="address" rows="3" readonly tabindex="-1"></textarea>
            </div>
        </div>
    </div>
</div>

<div id="admissionHistory"></div>

<script>
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search);
    var pageRef = params.get('ref') || '';
    var allowedActions = [];

    function el(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-IN',{
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function formatDate(value) {
        if (!value) return '-';
        var parts = String(value).split('-');
        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : String(value);
    }

    function has(actionId) {
        return (allowedActions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function showError(error, fallback) {
        var message = error && error.message
            ? String(error.message)
            : (error && error.data && error.data.message
                ? String(error.data.message)
                : fallback);

        showToast(message,{type:'danger',duration:4});
    }

    function fillPersonal(student) {
        el('studentCode').value = student.student_code || '';
        el('studentName').value = student.student_name || '';
        el('dateOfBirth').value = formatDate(student.date_of_birth);
        el('gender').value = student.gender || '-';
        el('mobile').value = student.mobile || '-';
        el('email').value = student.email || '-';
        el('qualification').value = student.qualification || '-';
        el('guardianName').value = student.guardian_name || '-';
        el('guardianMobile').value = student.guardian_mobile || '-';
        el('identityNumber').value = student.identity_number || '-';
        el('status').value = Number(student.status) === 1 ? 'Active' : 'Inactive';
        el('address').value = student.address || '-';

        el('profileHeading').textContent = (student.student_code || 'Student') + ' - ' + (student.student_name || '');
        el('studentStatusText').textContent = 'Student Status: ' + (Number(student.status) === 1 ? 'Active' : 'Inactive');

        if (has(3)) {
            el('editStudentButton').hidden = false;
            el('editStudentButton').href = 'student-form.php?ref=' + encodeURIComponent(student.ref);
        }
    }

    function summaryField(label, value) {
        return (
            '<div class="field col-4">' +
                '<label>' + esc(label) + '</label>' +
                '<input type="text" readonly tabindex="-1" value="' + esc(value) + '">' +
            '</div>'
        );
    }

    function feeSummary(admission) {
        var fee = admission.fee || {};
        return (
            '<div class="form-row">' +
                summaryField('Total Fee','₹' + money(fee.net_payable)) +
                summaryField('Admission Payment','₹' + money(fee.admission_payment)) +
                summaryField('Later Payments','₹' + money(fee.later_payments)) +
            '</div>' +
            '<div class="form-row">' +
                summaryField('Total Paid','₹' + money(fee.total_paid)) +
                summaryField('Balance','₹' + money(fee.balance)) +
                summaryField('Installment Count',String(fee.installment_count || 0)) +
            '</div>'
        );
    }

    function attendanceSummaryBlock(summary) {
        var regular = summary.regular || {};
        var practical = summary.practical || {};

        return (
            '<div class="form-row">' +
                summaryField('Regular Attendance',Number(regular.percentage || 0).toFixed(2) + '%') +
                summaryField('Regular Present / Total',String(regular.present || 0) + ' / ' + String(regular.total || 0)) +
                summaryField('Practical Attendance',Number(practical.percentage || 0).toFixed(2) + '%') +
            '</div>' +
            '<div class="form-row">' +
                summaryField('Practical Present / Total',String(practical.present || 0) + ' / ' + String(practical.total || 0)) +
                summaryField('Regular A / L / Late',String(regular.absent || 0) + ' / ' + String(regular.leave || 0) + ' / ' + String(regular.late || 0)) +
                summaryField('Practical A / L / Late',String(practical.absent || 0) + ' / ' + String(practical.leave || 0) + ' / ' + String(practical.late || 0)) +
            '</div>'
        );
    }

    function installmentsTable(rows) {
        if (!rows || rows.length === 0) {
            return '<div class="table-scroll"><table class="data-table"><tbody><tr><td>No EMI schedule available.</td></tr></tbody></table></div>';
        }

        return (
            '<div class="table-scroll">' +
                '<table class="data-table">' +
                    '<thead><tr><th>EMI</th><th>Due Date</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>' +
                    '<tbody>' +
                        rows.map(function (row) {
                            return (
                                '<tr>' +
                                    '<td>EMI ' + esc(row.installment_number) + '</td>' +
                                    '<td>' + esc(formatDate(row.due_date)) + '</td>' +
                                    '<td>₹' + esc(money(row.due_amount)) + '</td>' +
                                    '<td>₹' + esc(money(row.paid_amount)) + '</td>' +
                                    '<td>₹' + esc(money(row.balance_amount)) + '</td>' +
                                    '<td>' + esc(row.status_label) + '</td>' +
                                '</tr>'
                            );
                        }).join('') +
                    '</tbody>' +
                '</table>' +
            '</div>'
        );
    }

    function paymentsTable(rows) {
        if (!rows || rows.length === 0) {
            return '<div class="table-scroll"><table class="data-table"><tbody><tr><td>No Payment history available.</td></tr></tbody></table></div>';
        }

        return (
            '<div class="table-scroll">' +
                '<table class="data-table">' +
                    '<thead><tr><th>Receipt</th><th>Date</th><th>Type</th><th>Against</th><th>Mode</th><th>Allocation</th><th>Amount</th><th>Balance After</th></tr></thead>' +
                    '<tbody>' +
                        rows.map(function (row) {
                            return (
                                '<tr>' +
                                    '<td>' + esc(row.receipt_no) + '</td>' +
                                    '<td>' + esc(formatDate(row.receipt_date)) + '</td>' +
                                    '<td>' + esc(row.receipt_type === 'admission' ? 'Admission' : 'Payment') + '</td>' +
                                    '<td>' + esc(row.payment_against || '-') + '</td>' +
                                    '<td>' + esc(row.payment_mode_label || '-') + '</td>' +
                                    '<td>' + esc(row.allocation_label || '-') + '</td>' +
                                    '<td>₹' + esc(money(row.amount)) + '</td>' +
                                    '<td>₹' + esc(money(row.balance_after)) + '</td>' +
                                '</tr>'
                            );
                        }).join('') +
                    '</tbody>' +
                '</table>' +
            '</div>'
        );
    }

    function attendanceTable(rows) {
        if (!rows || rows.length === 0) {
            return '<div class="table-scroll"><table class="data-table"><tbody><tr><td>No Attendance history available.</td></tr></tbody></table></div>';
        }

        return (
            '<div class="table-scroll">' +
                '<table class="data-table">' +
                    '<thead><tr><th>Date</th><th>Type</th><th>Practical / Subject</th><th>Attendance</th><th>Remarks</th></tr></thead>' +
                    '<tbody>' +
                        rows.map(function (row) {
                            return (
                                '<tr>' +
                                    '<td>' + esc(formatDate(row.attendance_date)) + '</td>' +
                                    '<td>' + esc(row.attendance_type_label) + '</td>' +
                                    '<td>' + esc(row.subject_label || '-') + '</td>' +
                                    '<td>' + esc(row.attendance_label) + '</td>' +
                                    '<td>' + esc(row.remarks || '-') + '</td>' +
                                '</tr>'
                            );
                        }).join('') +
                    '</tbody>' +
                '</table>' +
            '</div>'
        );
    }

    function renderAdmissions(admissions) {
        var container = el('admissionHistory');

        if (!admissions || admissions.length === 0) {
            container.innerHTML =
                '<div class="card table-card">' +
                    '<div class="card-header"><div><h2>Admissions</h2><p>No Admission has been created for this Student.</p></div></div>' +
                '</div>';
            return;
        }

        container.innerHTML = admissions.map(function (admission,index) {
            return (
                '<div class="card form-card">' +
                    '<div class="card-header">' +
                        '<div>' +
                            '<h2>' + esc(admission.admission_no || ('Admission ' + (index + 1))) + '</h2>' +
                            '<p>' + esc(admission.course_code + ' - ' + admission.course_name + ' · ' + admission.batch_code + ' - ' + admission.batch_name) + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="card-body">' +
                        '<div class="card-section-title">Admission Details</div>' +
                        '<div class="form-row">' +
                            summaryField('Admission No',admission.admission_no || '-') +
                            summaryField('Admission Date',formatDate(admission.admission_date)) +
                            summaryField('Completion Date',formatDate(admission.completion_date)) +
                        '</div>' +
                        '<div class="form-row">' +
                            summaryField('Course',admission.course_code + ' - ' + admission.course_name) +
                            summaryField('Batch',admission.batch_code + ' - ' + admission.batch_name) +
                            summaryField('Admission State',String(admission.admission_state || '-').replace(/_/g,' ')) +
                        '</div>' +

                        '<div class="card-section-title">Fee Summary</div>' +
                        feeSummary(admission) +

                        '<div class="card-section-title">EMI Details</div>' +
                        installmentsTable(admission.installments || []) +

                        '<div class="card-section-title">Payment History</div>' +
                        paymentsTable(admission.payments || []) +

                        '<div class="card-section-title">Attendance Summary</div>' +
                        attendanceSummaryBlock(admission.attendance_summary || {}) +

                        '<div class="card-section-title">Recent Attendance</div>' +
                        attendanceTable(admission.attendance_history || []) +
                    '</div>' +
                '</div>'
            );
        }).join('');
    }

    async function load() {
        if (!pageRef) {
            showToast('Student reference is required.',{type:'danger',duration:4});
            return;
        }

        try {
            var response = await App.api('api/students.php?profile=1&ref=' + encodeURIComponent(pageRef));
            allowedActions = response.data.allowed_actions || [];
            var profile = response.data.profile || {};

            fillPersonal(profile.student || {});
            renderAdmissions(profile.admissions || []);

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showError(error,'Unable to load Student Profile.');
        }
    }

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
