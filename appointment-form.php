<?php
require_once __DIR__ . '/include/web-config.php';

$pageTitle = 'Appointment Form';
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
        <h1 id="pageHeading">Add Appointment</h1>
        <p id="pageDescription">Schedule a Clinic Appointment.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="appointment-calendar.php">
            <i data-lucide="calendar-days"></i>
            Calendar
        </a>

        <a class="btn gray" href="appointment-list.php">
            <i data-lucide="list"></i>
            Appointment List
        </a>
    </div>
</div>

<div class="card form-card">
<form id="appointmentForm" novalidate>

<div class="card-header">
    <div>
        <h2>Appointment Information</h2>
        <p>Select an existing Patient or create a new Patient without leaving this page.</p>
    </div>
</div>

<div class="card-body">

<div class="card-section-title">Patient Details</div>

<div class="form-row">
    <div class="field col-8">
        <label for="patientRef" class="required">Patient</label>

        <div class="input-group">
            <div class="input-group-control">
                <select
                    id="patientRef"
                    name="patient_ref"
                    required
                    data-placeholder="Select or type Patient"
                    data-error-id="patientRefError"
                    data-required-message="Patient is required."
                >
                    <option value="">Select Patient</option>
                </select>
            </div>

            <button
                class="btn btn-primary input-group-button"
                id="addPatientButton"
                type="button"
                title="Add New Patient"
                aria-label="Add New Patient"
            >
                <i data-lucide="plus"></i>
            </button>
        </div>

        <small id="patientRefError" class="validation-error"></small>
    </div>

    <div class="field col-4">
        <label>Patient Name</label>
        <input id="patientNameDisplay" type="text" readonly aria-readonly="true">
    </div>
</div>

<div class="form-row">
    <div class="field col-3">
        <label>Patient Code</label>
        <input id="patientCode" type="text" readonly>
    </div>

    <div class="field col-3">
        <label>Mobile</label>
        <input id="patientMobile" type="text" readonly>
    </div>

    <div class="field col-3">
        <label>Age</label>
        <input id="patientAge" type="text" readonly>
    </div>

    <div class="field col-3">
        <label>Gender</label>
        <input id="patientGender" type="text" readonly>
    </div>
</div>

<div class="card-section-title">Appointment Details</div>

<div class="form-row">
    <div class="field col-3">
        <label>Appointment No.</label>
        <input
            id="appointmentNo"
            type="text"
            readonly
            aria-readonly="true"
            placeholder="Auto generated"
        >
    </div>

    <div class="field col-3">
        <label for="appointmentDate" class="required">Appointment Date</label>
        <input
            id="appointmentDate"
            name="appointment_date"
            type="date"
            required
            data-required-message="Appointment Date is required."
        >
    </div>

    <div class="field col-3">
        <label for="appointmentTime" class="required">Appointment Time</label>
        <input
            id="appointmentTime"
            name="appointment_time"
            type="time"
            required
            data-required-message="Appointment Time is required."
        >
    </div>

    <div class="field col-3">
        <label for="visitType" class="required">Visit Type</label>
        <select
            id="visitType"
            name="visit_type"
            required
            data-required-message="Visit Type is required."
        >
            <option value="">Select Visit Type</option>
            <option value="New Patient">New Patient</option>
            <option value="Follow-up">Follow-up</option>
            <option value="Treatment Visit">Treatment Visit</option>
            <option value="Review">Review</option>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="field col-6">
        <label for="consultantName">Doctor / Consultant</label>
        <input
            id="consultantName"
            name="consultant_name"
            type="text"
            maxlength="150"
            placeholder="Enter Doctor / Consultant Name"
        >
    </div>

    <div class="field col-3">
        <label for="appointmentStatus" class="required">Appointment Status</label>
        <select
            id="appointmentStatus"
            name="appointment_status"
            required
            data-required-message="Appointment Status is required."
        >
            <option value="Scheduled">Scheduled</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Checked In">Checked In</option>
            <option value="In Consultation">In Consultation</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
            <option value="No Show">No Show</option>
        </select>
    </div>

    <div class="field col-3">
        <label for="recordStatus" class="required">Status</label>
        <select
            id="recordStatus"
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
    <div class="field col-6">
        <label for="reasonComplaint">Reason / Complaint</label>
        <textarea
            id="reasonComplaint"
            name="reason_complaint"
            rows="3"
            placeholder="Reason for appointment / patient complaint"
        ></textarea>
    </div>

    <div class="field col-6">
        <label for="notes">Notes</label>
        <textarea
            id="notes"
            name="notes"
            rows="3"
            placeholder="Appointment notes"
        ></textarea>
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
    <a class="btn gray" href="appointment-list.php">
        <i data-lucide="x"></i>
        Cancel
    </a>

    <button
        class="btn btn-primary"
        id="saveButton"
        type="submit"
    >
        <i data-lucide="save"></i>
        <span id="saveButtonText">Save Appointment</span>
    </button>
</div>

</form>
</div>

<?php require __DIR__ . '/modal/patient.php'; ?>

<script>
(function (window, document) {
    'use strict';

    var form = document.getElementById('appointmentForm');
    var queryParams = new URLSearchParams(window.location.search);
    var reference = queryParams.get('ref') || '';
    var viewMode = queryParams.get('view') === '1';
    var prefillDate = queryParams.get('date') || '';
    var prefillTime = queryParams.get('time') || '';
    var saveButton = document.getElementById('saveButton');
    var patientRows = [];
    var allowedActions = [];
    var isLoading = false;

    var patientSelect = GlobalSelect.init('#patientRef', {
        placeholder: 'Select or type Patient'
    });

    var visitTypeSelect = GlobalSelect.init('#visitType', {
        placeholder: 'Select Visit Type'
    });

    var appointmentStatusSelect = GlobalSelect.init('#appointmentStatus', {
        placeholder: 'Select Appointment Status'
    });

    var recordStatusSelect = GlobalSelect.init('#recordStatus', {
        placeholder: 'Select Status'
    });

    function apiData(response) {
        if (!response || typeof response !== 'object') return {};
        return response.data && typeof response.data === 'object'
            ? response.data
            : response;
    }

    function hasAction(actions, actionId) {
        return (actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function patientItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value: row.ref,
                text: row.label || (
                    (row.patient_code || '') +
                    ' - ' +
                    (row.patient_name || '') +
                    (row.mobile ? ' - ' + row.mobile : '')
                )
            };
        });
    }

    function patientByRef(ref) {
        ref = String(ref || '');

        return patientRows.find(function (row) {
            return String(row.ref || '') === ref;
        }) || null;
    }

    function setPatientOptions(selectedRef) {
        patientSelect.setOptions(
            patientItems(patientRows),
            selectedRef || ''
        );

        applyPatientDetails();
    }

    function applyPatientDetails() {
        var row = patientByRef(form.patient_ref.value);

        document.getElementById('patientNameDisplay').value =
            row ? row.patient_name || '' : '';

        document.getElementById('patientCode').value =
            row ? row.patient_code || '' : '';

        document.getElementById('patientMobile').value =
            row ? row.mobile || '' : '';

        document.getElementById('patientAge').value =
            row && row.age !== null && row.age !== undefined
                ? String(row.age)
                : '';

        document.getElementById('patientGender').value =
            row ? row.gender || '' : '';
    }

    async function reloadPatients(selectedRef) {
        var response = await App.api('api/appointments.php?patients=1');
        var data = apiData(response);

        patientRows = Array.isArray(data.patients)
            ? data.patients
            : [];

        setPatientOptions(selectedRef || '');
    }

    function setSelectValue(instance, value) {
        value = value == null ? '' : String(value);

        if (instance && typeof instance.setValue === 'function') {
            instance.setValue(value);
            return;
        }

        if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }
    }

    function fillAppointment(record) {
        document.getElementById('appointmentNo').value =
            record.appointment_no || '';

        setPatientOptions(record.patient_ref || '');

        form.appointment_date.value =
            record.appointment_date || '';

        form.appointment_time.value =
            record.appointment_time || '';

        setSelectValue(
            visitTypeSelect,
            record.visit_type || ''
        );

        form.consultant_name.value =
            record.consultant_name || '';

        form.reason_complaint.value =
            record.reason_complaint || '';

        form.notes.value =
            record.notes || '';

        setSelectValue(
            appointmentStatusSelect,
            record.appointment_status || 'Scheduled'
        );

        setSelectValue(
            recordStatusSelect,
            Number(record.status) === 0 ? '0' : '1'
        );

        document.getElementById('createdByName').value =
            record.created_by_name || '-';

        document.getElementById('createdAt').value =
            record.created_at || '-';

        document.getElementById('updatedAt').value =
            record.updated_at || '-';

        document.getElementById('auditRow').hidden = false;
    }

    function setViewMode() {
        if (!viewMode) return;

        document.getElementById('pageHeading').textContent =
            'View Appointment';

        document.getElementById('pageDescription').textContent =
            'View Clinic Appointment details.';

        [
            'appointmentDate',
            'appointmentTime',
            'consultantName',
            'reasonComplaint',
            'notes'
        ].forEach(function (id) {
            document.getElementById(id).readOnly = true;
        });

        [
            'patientRef',
            'visitType',
            'appointmentStatus',
            'recordStatus'
        ].forEach(function (id) {
            document.getElementById(id).disabled = true;
        });

        document.getElementById('addPatientButton').hidden = true;
        saveButton.hidden = true;
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;

        try {
            if (reference) {
                document.getElementById('pageHeading').textContent =
                    viewMode ? 'View Appointment' : 'Edit Appointment';

                document.getElementById('pageDescription').textContent =
                    viewMode
                        ? 'View Clinic Appointment details.'
                        : 'Update Clinic Appointment details.';

                document.getElementById('saveButtonText').textContent =
                    'Update Appointment';

                var response = await App.api(
                    'api/appointments.php?ref=' +
                    encodeURIComponent(reference)
                );

                var data = apiData(response);
                var record = data.record || {};

                allowedActions =
                    Array.isArray(data.allowed_actions)
                        ? data.allowed_actions
                        : [];

                patientRows =
                    Array.isArray(data.patients)
                        ? data.patients
                        : [];

                fillAppointment(record);

                if (!viewMode && !hasAction(allowedActions, 3)) {
                    saveButton.disabled = true;
                }
            } else {
                var response = await App.api(
                    'api/appointments.php?options=1'
                );

                var data = apiData(response);

                allowedActions =
                    Array.isArray(data.allowed_actions)
                        ? data.allowed_actions
                        : [];

                patientRows =
                    Array.isArray(data.patients)
                        ? data.patients
                        : [];

                document.getElementById('appointmentNo').value =
                    data.next_appointment_no || '';

                setPatientOptions('');

                var today = new Date();
                var todayText =
                    today.getFullYear() +
                    '-' +
                    String(today.getMonth() + 1).padStart(2, '0') +
                    '-' +
                    String(today.getDate()).padStart(2, '0');

                form.appointment_date.value =
                    /^\d{4}-\d{2}-\d{2}$/.test(prefillDate)
                        ? prefillDate
                        : todayText;

                if (/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(prefillTime)) {
                    form.appointment_time.value = prefillTime;
                }

                setSelectValue(
                    appointmentStatusSelect,
                    'Scheduled'
                );

                setSelectValue(
                    recordStatusSelect,
                    '1'
                );

                if (!hasAction(allowedActions, 2)) {
                    saveButton.disabled = true;
                }
            }

            setViewMode();

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            saveButton.disabled = true;
            document.getElementById('addPatientButton').disabled = true;

            App.showError(
                error,
                'Unable to prepare Appointment Form.'
            );
        } finally {
            isLoading = false;
        }
    }

    document.getElementById('patientRef')
        .addEventListener('change', applyPatientDetails);

    document.getElementById('addPatientButton')
        .addEventListener('click', function () {
            if (!window.AppPatientForm) {
                App.showError(
                    null,
                    'Reusable Patient modal is unavailable.'
                );
                return;
            }

            AppPatientForm.openCreate({
                onSaved: function (saved) {
                    var newRef =
                        saved && saved.ref
                            ? saved.ref
                            : '';

                    reloadPatients(newRef)
                        .then(function () {
                            setSelectValue(
                                visitTypeSelect,
                                'New Patient'
                            );
                        })
                        .catch(function (error) {
                            App.showError(
                                error,
                                'Patient was created, but the Patient list could not be refreshed.'
                            );
                        });
                }
            });
        });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (viewMode) return;

        Validation.clearForm(form);

        if (!Validation.validateForm(form)) {
            return;
        }

        var data = new FormData(form);

        if (reference) {
            data.set('ref', reference);
        }

        data.set('action', 'save');

        saveButton.disabled = true;

        try {
            var result = await App.api(
                'api/appointments.php',
                {
                    method: 'POST',
                    body: data
                }
            );

            showToast(
                result.message || 'Appointment saved successfully.',
                {
                    type: 'success',
                    duration: 2
                }
            );

            window.setTimeout(function () {
                window.location.href = 'appointment-list.php';
            }, 700);
        } catch (error) {
            Validation.applyErrors(
                form,
                error.errors || {}
            );

            App.showError(
                error,
                'Unable to save Appointment.'
            );

            saveButton.disabled = false;
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
<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>
</body>
</html>
