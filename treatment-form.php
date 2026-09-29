<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Patient Treatment';
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
        <h1>Patient Treatment / Progress</h1>
        <p>Record actual treatment execution for an existing Patient-specific Treatment Day.</p>
    </div>

    <a class="btn gray" href="treatment-list.php">
        <i data-lucide="list"></i>
        Treatment Progress List
    </a>
</div>

<div class="card form-card">
<form id="treatmentForm" novalidate>

<div class="card-header">
    <div>
        <h2>Treatment Progress</h2>
        <p>Pending is automatic when no progress record exists.</p>
    </div>
</div>

<div class="card-body">

    <div class="card-section-title">Patient & Treatment Day</div>

    <div class="form-row">
        <div class="field col-4">
            <label for="patientFilter">Patient</label>
            <select id="patientFilter">
                <option value="">All Patients</option>
            </select>
        </div>

        <div class="field col-8">
            <label for="treatmentDayRef" class="required">Treatment Day</label>
            <select
                id="treatmentDayRef"
                name="treatment_day_ref"
                required
                data-required-message="Treatment Day is required."
            >
                <option value="">Select Treatment Day</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="field col-3">
            <label>Patient Code</label>
            <input id="patientCode" type="text" readonly>
        </div>

        <div class="field col-3">
            <label>Patient Name</label>
            <input id="patientName" type="text" readonly>
        </div>

        <div class="field col-3">
            <label>Consultation No.</label>
            <input id="consultationNo" type="text" readonly>
        </div>

        <div class="field col-3">
            <label>Treatment Basis</label>
            <input id="treatmentBasis" type="text" readonly>
        </div>
    </div>

    <div class="form-row">
        <div class="field col-4">
            <label>Diagnosis</label>
            <input id="diagnosisName" type="text" readonly>
        </div>

        <div class="field col-4">
            <label>Treatment Plan</label>
            <input id="planName" type="text" readonly>
        </div>

        <div class="field col-4">
            <label>Day</label>
            <input id="dayNumber" type="text" readonly>
        </div>
    </div>

    <div class="form-row">
        <div class="field col-6">
            <label>Treatment / Procedure</label>
            <select id="treatmentProcedureRef" disabled>
                <option value="">Select Treatment / Procedure</option>
            </select>
        </div>

        <div class="field col-6">
            <label>Instructions</label>
            <textarea id="instructions" rows="2" readonly></textarea>
        </div>
    </div>

    <div class="card-section-title">Progress Entry</div>

    <div class="form-row">
        <div class="field col-3">
            <label for="treatmentDate" class="required">Treatment Date</label>
            <input
                id="treatmentDate"
                name="treatment_date"
                type="date"
                required
                data-required-message="Treatment Date is required."
            >
        </div>

        <div class="field col-3">
            <label for="treatmentTime" class="required">Treatment Time</label>
            <input
                id="treatmentTime"
                name="treatment_time"
                type="time"
                required
                data-required-message="Treatment Time is required."
            >
        </div>

        <div class="field col-3">
            <label>Current Status</label>
            <input id="currentStatus" type="text" readonly value="Pending">
        </div>

        <div class="field col-3">
            <label for="treatmentStatus" class="required">Treatment Status</label>
            <select
                id="treatmentStatus"
                name="treatment_status"
                required
                data-required-message="Treatment Status is required."
            >
                <option value="">Select Status</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Skipped">Skipped</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="field col-12">
            <label for="treatmentNotes">Treatment Notes</label>
            <textarea
                id="treatmentNotes"
                name="treatment_notes"
                rows="3"
                placeholder="Actual treatment notes / response / observations"
            ></textarea>
        </div>
    </div>

    <div class="form-row" id="auditRow" hidden>
        <div class="field col-4">
            <label>Recorded By</label>
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
        <button
            class="btn btn-primary"
            id="saveButton"
            type="submit"
        >
            <i data-lucide="save"></i>
            Save Treatment Progress
        </button>

        <a class="btn gray" href="treatment-list.php">
            Cancel
        </a>
    </div>
</div>

</form>
</div>

<script>
(function (window, document) {
    'use strict';

    var form = document.getElementById('treatmentForm');
    var params = new URLSearchParams(window.location.search);
    var initialDayRef = params.get('day_ref') || '';
    var saveButton = document.getElementById('saveButton');

    var patientRows = [];
    var dayRows = [];
    var allowedActions = [];

    var patientSelect = GlobalSelect.init('#patientFilter', {
        placeholder: 'All Patients'
    });

    var daySelect = GlobalSelect.init('#treatmentDayRef', {
        placeholder: 'Select Treatment Day'
    });

    var treatmentStatusSelect = GlobalSelect.init('#treatmentStatus', {
        placeholder: 'Select Treatment Status'
    });

    var treatmentProcedureSelect = GlobalSelect.init('#treatmentProcedureRef', {
        placeholder: 'Treatment / Procedure'
    });

    function apiData(response) {
        if (!response || typeof response !== 'object') return {};
        return response.data && typeof response.data === 'object'
            ? response.data
            : response;
    }

    function hasAction(actions, id) {
        return (actions || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function setSelect(instance, value) {
        value = value == null ? '' : String(value);

        if (instance && typeof instance.setValue === 'function') {
            instance.setValue(value);
            return;
        }

        if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }
    }

    function patientItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value: row.ref,
                text: row.label
            };
        });
    }

    function dayItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value: row.ref,
                text: row.label
            };
        });
    }

    function dayByRef(value) {
        value = String(value || '');

        return dayRows.find(function (row) {
            return String(row.ref || '') === value;
        }) || null;
    }

    function filterDays(selectedRef) {
        var patientRef =
            document.getElementById('patientFilter').value;

        var rows =
            patientRef
                ? dayRows.filter(function (row) {
                    return String(row.patient_ref || '') ===
                        String(patientRef);
                })
                : dayRows;

        daySelect.setOptions(
            dayItems(rows),
            selectedRef || ''
        );
    }

    function clearDetails() {
        [
            'patientCode',
            'patientName',
            'consultationNo',
            'treatmentBasis',
            'diagnosisName',
            'planName',
            'dayNumber',
            'instructions'
        ].forEach(function (id) {
            document.getElementById(id).value = '';
        });

        if (
            treatmentProcedureSelect &&
            typeof treatmentProcedureSelect.setOptions === 'function'
        ) {
            treatmentProcedureSelect.setOptions([], '');
        }

        document.getElementById('currentStatus').value =
            'Pending';

        document.getElementById('auditRow').hidden =
            true;
    }

    function fillSummary(row) {
        if (!row) {
            clearDetails();
            return;
        }

        document.getElementById('patientCode').value =
            row.patient_code || '';

        document.getElementById('patientName').value =
            row.patient_name || '';

        document.getElementById('consultationNo').value =
            row.consultation_no || '';

        document.getElementById('treatmentBasis').value =
            row.treatment_basis || '';

        document.getElementById('diagnosisName').value =
            row.diagnosis_name || 'Not selected';

        document.getElementById('planName').value =
            row.plan_name || '';

        document.getElementById('dayNumber').value =
            row.day_number
                ? 'Day ' + row.day_number
                : '';

        if (
            treatmentProcedureSelect &&
            typeof treatmentProcedureSelect.setOptions === 'function'
        ) {
            treatmentProcedureSelect.setOptions(
                row.treatment_procedure_ref
                    ? [{
                        value:row.treatment_procedure_ref,
                        text:
                            (row.procedure_code || '') +
                            ' - ' +
                            (row.procedure_name || '')
                    }]
                    : [],
                row.treatment_procedure_ref || ''
            );
        }

        document.getElementById('instructions').value =
            row.instructions || '';

        document.getElementById('currentStatus').value =
            row.progress_status || 'Pending';
    }

    async function loadDay(ref) {
        if (!ref) {
            clearDetails();
            return;
        }

        try {
            var response =
                await App.api(
                    'api/treatments.php?day_ref=' +
                    encodeURIComponent(ref)
                );

            var data = apiData(response);
            var row = data.record || {};

            allowedActions =
                Array.isArray(data.allowed_actions)
                    ? data.allowed_actions
                    : allowedActions;

            fillSummary(row);

            if (row.patient_ref) {
                setSelect(
                    patientSelect,
                    row.patient_ref
                );

                filterDays(ref);
            }

            form.treatment_date.value =
                row.treatment_date || '';

            form.treatment_time.value =
                row.treatment_time || '';

            setSelect(
                treatmentStatusSelect,
                row.progress_id
                    ? row.treatment_status || ''
                    : ''
            );

            form.treatment_notes.value =
                row.treatment_notes || '';

            if (row.progress_id) {
                document.getElementById('createdByName').value =
                    row.progress_created_by_name || '-';

                document.getElementById('createdAt').value =
                    row.progress_created_at || '-';

                document.getElementById('updatedAt').value =
                    row.progress_updated_at || '-';

                document.getElementById('auditRow').hidden =
                    false;

                if (!hasAction(allowedActions,3)) {
                    saveButton.disabled = true;
                }
            } else {
                var now = new Date();

                form.treatment_date.value =
                    now.getFullYear() +
                    '-' +
                    String(now.getMonth() + 1).padStart(2, '0') +
                    '-' +
                    String(now.getDate()).padStart(2, '0');

                form.treatment_time.value =
                    String(now.getHours()).padStart(2, '0') +
                    ':' +
                    String(now.getMinutes()).padStart(2, '0');

                if (!hasAction(allowedActions,2)) {
                    saveButton.disabled = true;
                }
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to load Treatment Day.'
            );
        }
    }

    async function load() {
        try {
            var response =
                await App.api(
                    'api/treatments.php?options=1'
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

            dayRows =
                Array.isArray(data.treatment_days)
                    ? data.treatment_days
                    : [];

            patientSelect.setOptions(
                patientItems(patientRows),
                ''
            );

            filterDays(
                initialDayRef || ''
            );

            if (initialDayRef) {
                await loadDay(initialDayRef);
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            saveButton.disabled = true;

            App.showError(
                error,
                'Unable to prepare Patient Treatment form.'
            );
        }
    }

    document.getElementById('patientFilter')
        .addEventListener('change', function () {
            filterDays('');
            clearDetails();
        });

    document.getElementById('treatmentDayRef')
        .addEventListener('change', function () {
            loadDay(
                form.treatment_day_ref.value
            );
        });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        Validation.clearForm(form);

        if (!Validation.validateForm(form)) {
            return;
        }

        saveButton.disabled = true;

        try {
            var response =
                await App.api(
                    'api/treatments.php',
                    {
                        method:'POST',
                        body:new FormData(form)
                    }
                );

            showToast(
                response.message ||
                'Treatment Progress saved successfully.',
                {
                    type:'success',
                    duration:2
                }
            );

            window.setTimeout(function () {
                window.location.href =
                    'treatment-list.php';
            },700);
        } catch (error) {
            Validation.applyErrors(
                form,
                error.errors || {}
            );

            App.showError(
                error,
                'Unable to save Treatment Progress.'
            );

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
<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>

</body>
</html>
