<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Student';
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
        <h1 id="pageHeading">Add Student</h1>
        <p id="pageDescription">Create a Student record before Admission, or update existing Student information.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="student-list.php">
            <i data-lucide="list"></i>
            Student List
        </a>

        <a class="btn gray" id="profileButton" href="#" hidden>
            <i data-lucide="user-round"></i>
            Student Profile
        </a>
    </div>
</div>

<div class="card form-card">
<form id="studentForm" novalidate>
    <input id="studentRef" type="hidden">

    <div class="card-header">
        <div>
            <h2>Student Information</h2>
            <p>Student Code is generated automatically and cannot be changed after creation.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label for="studentCode">Student Code</label>
                <input id="studentCode" type="text" value="Auto Generated" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label for="studentName" class="required">Student Name</label>
                <input
                    id="studentName"
                    name="student_name"
                    type="text"
                    maxlength="150"
                    autocomplete="off"
                    required
                    data-required-message="Student Name is required.">
            </div>

            <div class="field col-4">
                <label for="dateOfBirth">Date of Birth</label>
                <input id="dateOfBirth" name="date_of_birth" type="date">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="mobile">Mobile</label>
                <input
                    id="mobile"
                    name="mobile"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    autocomplete="off"
                    data-validation="mobile">
            </div>

            <div class="field col-4">
                <label for="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="190"
                    autocomplete="off"
                    data-validation="email">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="qualification">Qualification</label>
                <input id="qualification" name="qualification" type="text" maxlength="150" autocomplete="off">
            </div>

            <div class="field col-4">
                <label for="guardianName">Guardian Name</label>
                <input id="guardianName" name="guardian_name" type="text" maxlength="150" autocomplete="off">
            </div>

            <div class="field col-4">
                <label for="guardianMobile">Guardian Mobile</label>
                <input
                    id="guardianMobile"
                    name="guardian_mobile"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    autocomplete="off"
                    data-validation="mobile">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="identityNumber">Identity Number</label>
                <input id="identityNumber" name="identity_number" type="text" maxlength="100" autocomplete="off">
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
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" placeholder="Student Address"></textarea>
            </div>
        </div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <a class="btn gray" href="student-list.php">Cancel</a>

            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                Save Student
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
    var form = document.getElementById('studentForm');
    var allowedActions = [];
    var saving = false;
    var genderSelect;
    var statusSelect;

    function el(id) {
        return document.getElementById(id);
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

    function normalizeFields() {
        ['studentName','email','qualification','guardianName','identityNumber','address'].forEach(function (id) {
            el(id).value = el(id).value.trim();
        });

        el('mobile').value = el('mobile').value.replace(/\D+/g,'');
        el('guardianMobile').value = el('guardianMobile').value.replace(/\D+/g,'');
    }

    function payload() {
        return {
            action:'save',
            ref:el('studentRef').value || '',
            student_name:el('studentName').value,
            date_of_birth:el('dateOfBirth').value,
            gender:el('gender').value,
            mobile:el('mobile').value,
            email:el('email').value,
            qualification:el('qualification').value,
            guardian_name:el('guardianName').value,
            guardian_mobile:el('guardianMobile').value,
            identity_number:el('identityNumber').value,
            status:Number(el('status').value),
            address:el('address').value
        };
    }

    function fillStudent(student) {
        el('studentRef').value = student.ref || '';
        el('studentCode').value = student.student_code || '';
        el('studentName').value = student.student_name || '';
        el('dateOfBirth').value = student.date_of_birth || '';
        genderSelect.selectValue(student.gender || '');
        el('mobile').value = student.mobile || '';
        el('email').value = student.email || '';
        el('qualification').value = student.qualification || '';
        el('guardianName').value = student.guardian_name || '';
        el('guardianMobile').value = student.guardian_mobile || '';
        el('identityNumber').value = student.identity_number || '';
        statusSelect.selectValue(String(student.status == null ? 1 : student.status));
        el('address').value = student.address || '';

        el('pageHeading').textContent = 'Edit Student';
        el('pageDescription').textContent = (student.student_code || 'Student') + ' - ' + (student.student_name || '');
        el('profileButton').hidden = false;
        el('profileButton').href = 'student-profile.php?ref=' + encodeURIComponent(student.ref);

        if (!has(3)) el('saveButton').hidden = true;
    }

    async function load() {
        genderSelect = GlobalSelect.init('#gender',{placeholder:'Select Gender'});
        statusSelect = GlobalSelect.init('#status',{placeholder:'Select Status'});

        try {
            if (pageRef) {
                var response = await App.api('api/students.php?ref=' + encodeURIComponent(pageRef));
                allowedActions = response.data.allowed_actions || [];
                fillStudent(response.data.student || {});
            } else {
                var optionsResponse = await App.api('api/students.php?options=1');
                allowedActions = optionsResponse.data.allowed_actions || [];
                genderSelect.selectValue('');
                statusSelect.selectValue('1');

                if (!has(2)) el('saveButton').hidden = true;
            }

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showError(error,'Unable to load Student.');
            el('saveButton').hidden = true;
        }
    }

    form.addEventListener('submit',async function (event) {
        event.preventDefault();
        if (saving) return;

        if (window.Validation) Validation.clearForm(form);
        normalizeFields();

        if (window.Validation && !Validation.validateForm(form)) return;

        saving = true;
        el('saveButton').disabled = true;

        try {
            var response = await App.api('api/students.php',{
                method:'POST',
                body:payload()
            });

            showToast(response.message || 'Student saved successfully.',{type:'success',duration:2});

            var saved = response.data.student || {};
            window.location.href = saved.ref
                ? 'student-profile.php?ref=' + encodeURIComponent(saved.ref)
                : 'student-list.php';
        } catch (error) {
            if (window.Validation && error && error.errors) {
                Validation.applyErrors(form,error.errors);
            }
            showError(error,'Unable to save Student.');
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
