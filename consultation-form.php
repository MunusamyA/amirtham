<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Consultation Form';
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
        <h1 id="pageHeading">Add Consultation</h1>
        <p id="pageDescription">
            Appointment / Walk-in Consultation with Diagnosis Treatment or General Treatment.
        </p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="appointment-calendar.php">
            <i data-lucide="calendar-days"></i>
            Appointment Calendar
        </a>

        <a class="btn gray" href="consultation-list.php">
            <i data-lucide="list"></i>
            Consultation List
        </a>
    </div>
</div>

<div class="card form-card">

<form id="consultationForm" novalidate>
    <input
        type="hidden"
        id="treatmentDaysJson"
        name="treatment_days_json"
        value="[]"
    >

    <input
        type="hidden"
        id="prescriptionItemsJson"
        name="prescription_items_json"
        value="[]"
    >

    <div class="card-header">
        <div>
            <h2>Consultation Details</h2>
            <p>
                Appointment is optional for Walk-in. Diagnosis Treatment loads the predefined Treatment / Procedure plan automatically.
            </p>
        </div>
    </div>

    <div class="card-body">

        <div class="card-section-title">Visit Source & Patient</div>

        <div class="form-row">
            <div class="field col-2">
                <label for="visitSource" class="required">Visit Source</label>
                <select
                    id="visitSource"
                    name="visit_source"
                    required
                    data-required-message="Visit Source is required."
                >
                    <option value="appointment">Appointment</option>
                    <option value="walkin">Walk-in / Direct</option>
                </select>
            </div>

            <div class="field col-7" id="appointmentField">
                <label for="appointmentRef" class="required">Appointment</label>
                <select
                    id="appointmentRef"
                    name="appointment_ref"
                    data-placeholder="Select or type Appointment"
                    data-error-id="appointmentRefError"
                >
                    <option value="">Select Appointment</option>
                </select>
                <small id="appointmentRefError" class="validation-error"></small>
            </div>

            <div class="field col-7" id="patientSelectRow">
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

            <div class="field col-3">
                <label>Consultation No.</label>
                <input
                    id="consultationNo"
                    type="text"
                    readonly
                    aria-readonly="true"
                    placeholder="Auto generated"
                >
            </div>
        </div>

        <div id="patientSummary" class="muted">
            Select a Patient to view details.
        </div>

        <div class="card-section-title">Visit Information</div>

        <div class="form-row">
            <div class="field col-2">
                <label for="visitDate" class="required">Visit Date</label>
                <input
                    id="visitDate"
                    name="visit_date"
                    type="date"
                    required
                    data-required-message="Visit Date is required."
                >
            </div>

            <div class="field col-2">
                <label for="visitTime" class="required">Visit Time</label>
                <input
                    id="visitTime"
                    name="visit_time"
                    type="time"
                    required
                    data-required-message="Visit Time is required."
                >
            </div>

            <div class="field col-3">
                <label for="consultantName">Doctor / Consultant</label>
                <input
                    id="consultantName"
                    name="consultant_name"
                    type="text"
                    maxlength="150"
                    placeholder="Doctor / Consultant Name"
                >
            </div>

            <div class="field col-2">
                <label for="consultationFee" class="required">Consultation Fee</label>
                <input
                    id="consultationFee"
                    name="consultation_fee"
                    type="text"
                    inputmode="decimal"
                    value="0.00"
                    required
                    data-required-message="Consultation Fee is required."
                >
            </div>

            <div class="field col-3">
                <label for="consultationStatus" class="required">Consultation Status</label>
                <select
                    id="consultationStatus"
                    name="consultation_status"
                    required
                    data-required-message="Consultation Status is required."
                >
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Follow-up Required">Follow-up Required</option>
                </select>

                <small class="muted" id="consultationStatusHelp">
                    In Progress = consultation is ongoing.
                </small>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="chiefComplaint">Chief Complaint</label>
                <textarea
                    id="chiefComplaint"
                    name="chief_complaint"
                    rows="2"
                    placeholder="Main complaint / reason for consultation"
                ></textarea>
            </div>

            <div class="field col-4">
                <label for="symptoms">Symptoms</label>
                <textarea
                    id="symptoms"
                    name="symptoms"
                    rows="2"
                    placeholder="Symptoms observed / reported"
                ></textarea>
            </div>

            <div class="field col-4">
                <label for="clinicalNotes">Clinical Notes</label>
                <textarea
                    id="clinicalNotes"
                    name="clinical_notes"
                    rows="2"
                    placeholder="Clinical observations and notes"
                ></textarea>
            </div>
        </div>

        <div class="card-section-title">Diagnosis & Treatment Basis</div>

        <div class="form-row">
            <div class="field col-3">
                <label for="treatmentBasis" class="required">Treatment Basis</label>
                <select
                    id="treatmentBasis"
                    name="treatment_basis"
                    required
                    data-required-message="Treatment Basis is required."
                >
                    <option value="protocol">Diagnosis Treatment</option>
                    <option value="general">General Treatment</option>
                </select>
            </div>

            <div class="field col-9" id="diagnosisField">
                <label for="diagnosisRef" id="diagnosisLabel">Diagnosis</label>
                <select
                    id="diagnosisRef"
                    name="diagnosis_ref"
                    data-placeholder="Select or type Diagnosis"
                    data-error-id="diagnosisRefError"
                >
                    <option value="">Select Diagnosis</option>
                </select>
                <small id="diagnosisRefError" class="validation-error"></small>
            </div>
        </div>
<div
            class="card-section-title"
        >
            <span>Ayurveda Prescription</span>
            <button class="btn btn-primary small" id="addMedicineButton" type="button">
                <i data-lucide="plus"></i>
                Add Medicine
            </button>
        </div>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>No.</th>
                    <th>Medicine</th>
                    <th>Qty</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Timing</th>
                    <th>Duration</th>
                    <th>Anupana</th>
                    <th>Instructions</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="prescriptionRows"></tbody>
            </table>
        </div>

        <div class="card-section-title-actions">
            <div class="muted" id="emptyPrescription">
                Prescription is optional. Add Medicine only when required.
            </div>

            <div class="muted">
                Medicine Total:
                <strong>₹<span id="prescriptionTotal">0.00</span></strong>
                &nbsp; | &nbsp;
                Current Visit Charges:
                <strong>₹<span id="currentVisitCharges">0.00</span></strong>
            </div>
        </div>

        <div class="card-section-title">Patient-specific Treatment Plan</div>

        <div class="form-row">
            <div class="field col-8">
                <label for="planName" class="required">Treatment Plan Name</label>
                <input
                    id="planName"
                    name="plan_name"
                    type="text"
                    maxlength="180"
                    required
                    placeholder="Treatment Plan Name"
                    data-required-message="Treatment Plan Name is required."
                >
            </div>

            <div class="field col-4">
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
            <div class="field col-12">
                <label for="planNotes">Treatment Plan Notes</label>
                <textarea
                    id="planNotes"
                    name="plan_notes"
                    rows="2"
                    placeholder="Notes for this Patient-specific Treatment Plan"
                ></textarea>
            </div>
        </div>

        <div class="card-section-title card-section-title-actions">
            <span>Day-wise Patient Treatment Plan</span>

            <button
                class="btn btn-primary small"
                id="addDayButton"
                type="button"
            >
                <i data-lucide="plus"></i>
                Add Day
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
                <tbody id="treatmentDayRows"></tbody>
            </table>
        </div>

        <div id="emptyDays" class="muted app-inline-empty">
            Select a Diagnosis Treatment or click Add Day for General Treatment.
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
            <button
                class="btn btn-primary"
                id="saveButton"
                type="submit"
            >
                <i data-lucide="save"></i>
                <span id="saveButtonText">Save Consultation</span>
            </button>

            <a class="btn gray" href="consultation-list.php">
                Cancel
            </a>
        </div>
    </div>

</form>

</div>

<?php require __DIR__ . '/modal/patient.php'; ?>
<?php require __DIR__ . '/modal/treatment-procedure.php'; ?>
<?php require __DIR__ . '/modal/medicine.php'; ?>

<template id="prescriptionTemplate">
    <tr data-prescription-row>
        <td>
            <strong data-prescription-title>1</strong>
        </td>

        <td>
            <div class="input-group">
                <div class="input-group-control">
                    <select data-rx-field="medicine_ref" required>
                        <option value="">Select Medicine</option>
                    </select>
                </div>

                <button
                    class="btn btn-primary input-group-button add-medicine-master"
                    type="button"
                    title="Add Medicine"
                    aria-label="Add Medicine"
                >
                    <i data-lucide="plus"></i>
                </button>
            </div>
            <small class="validation-error" data-medicine-error></small>

            <input data-rx-field="unit_price" type="hidden" value="0.00">
            <input data-rx-field="amount" type="hidden" value="0.00">
        </td>

        <td>
            <input
                class="input"
                data-rx-field="quantity"
                type="text"
                inputmode="decimal"
                value="1"
                required
            >
        </td>

        <td>
            <input
                class="input"
                data-rx-field="dosage"
                type="text"
                maxlength="100"
                placeholder="15 ml / 1 Tablet"
            >
        </td>

        <td>
            <select class="select" data-rx-field="frequency">
                <option value="">Select Frequency</option>
                <option>Once Daily</option>
                <option>Twice Daily</option>
                <option>Thrice Daily</option>
                <option>Four Times Daily</option>
                <option>As Required</option>
                <option>Custom</option>
            </select>
        </td>

        <td>
            <select class="select" data-rx-field="timing">
                <option value="">Select Timing</option>
                <option>Before Food</option>
                <option>After Food</option>
                <option>With Food</option>
                <option>Empty Stomach</option>
                <option>Bedtime</option>
                <option>Custom</option>
            </select>
        </td>

        <td>
            <input
                class="input"
                data-rx-field="duration"
                type="text"
                maxlength="100"
                placeholder="7 Days"
            >
        </td>

        <td>
            <select class="select" data-rx-field="anupana">
                <option value="">Select Anupana</option>
                <option value="Warm Water">Warm Water</option>
                <option value="Honey">Honey</option>
                <option value="Milk">Milk</option>
                <option value="Ghee">Ghee</option>
                <option value="Buttermilk">Buttermilk</option>
                <option value="Plain Water">Plain Water</option>
                <option value="Other">Other</option>
            </select>
        </td>

        <td>
            <input
                class="input"
                data-rx-field="instructions"
                type="text"
                maxlength="5000"
                placeholder="Instructions"
            >
        </td>

        <td class="table-action-icons">
            <button
                class="table-icon-action danger remove-prescription-row"
                type="button"
                title="Remove Medicine"
                aria-label="Remove Medicine"
            >
                <i data-lucide="trash-2"></i>
            </button>
        </td>
    </tr>
</template>

<template id="treatmentDayTemplate">
    <tr data-treatment-day>
        <td>
            <strong data-day-title>Day 1</strong>
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
            <small class="validation-error" data-procedure-error></small>
        </td>

        <td>
            <textarea
                class="textarea"
                data-field="instructions"
                rows="2"
                placeholder="Instructions"
            ></textarea>
        </td>

        <td>
            <textarea
                class="textarea"
                data-field="notes"
                rows="2"
                placeholder="Notes"
            ></textarea>
        </td>

        <td>
            <select
                class="select"
                data-field="status"
                required
                data-required-message="Status is required."
            >
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </td>

        <td class="table-action-icons">
            <button
                class="table-icon-action danger remove-day-row"
                type="button"
                title="Remove Treatment Day"
                aria-label="Remove Treatment Day"
            >
                <i data-lucide="trash-2"></i>
            </button>
        </td>
    </tr>
</template>

<script>
(function (window, document) {
    'use strict';

    var form = document.getElementById('consultationForm');
    var params = new URLSearchParams(window.location.search);
    var reference = params.get('ref') || '';
    var viewMode = params.get('view') === '1';
    var preselectAppointment = params.get('appointment_ref') || '';
    var saveButton = document.getElementById('saveButton');

    var patientRows = [];
    var appointmentRows = [];
    var diagnosisRows = [];
    var treatmentProcedureRows = [];
    var medicineRows = [];
    var procedureSelectCounter = 0;
    var medicineSelectCounter = 0;
    var allowedActions = [];
    var isLoading = false;
    var isFilling = false;
    var treatmentPlanLoadSequence = 0;

    var dayContainer = document.getElementById('treatmentDayRows');
    var dayTemplate = document.getElementById('treatmentDayTemplate');
    var prescriptionContainer = document.getElementById('prescriptionRows');
    var prescriptionTemplate = document.getElementById('prescriptionTemplate');

    var visitSourceSelect = GlobalSelect.init('#visitSource', {
        placeholder: 'Select Visit Source'
    });

    var appointmentSelect = GlobalSelect.init('#appointmentRef', {
        placeholder: 'Select or type Appointment'
    });

    var patientSelect = GlobalSelect.init('#patientRef', {
        placeholder: 'Select or type Patient'
    });

    var treatmentBasisSelect = GlobalSelect.init('#treatmentBasis', {
        placeholder: 'Select Treatment Basis'
    });

    var diagnosisSelect = GlobalSelect.init('#diagnosisRef', {
        placeholder: 'Select or type Diagnosis'
    });

    var consultationStatusSelect = GlobalSelect.init('#consultationStatus', {
        placeholder: 'Select Consultation Status'
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

    function hasAction(actions, id) {
        return (actions || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function optionItems(rows, valueKey, textBuilder) {
        return (rows || []).map(function (row) {
            return {
                value: row[valueKey],
                text: textBuilder(row)
            };
        });
    }

    function setSelect(instance, value, element) {
        value = value == null ? '' : String(value);

        if (element) {
            element.value = value;
        }

        if (instance && typeof instance.setValue === 'function') {
            instance.setValue(value);
        } else if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }

        if (element) {
            element.value = value;
        }
    }

    function patientText(row) {
        return row.label || (
            (row.patient_code || '') +
            ' - ' +
            (row.patient_name || '')
        );
    }

    function appointmentText(row) {
        return row.label || (
            (row.appointment_no || '') +
            ' - ' +
            (row.patient_name || '')
        );
    }

    function diagnosisText(row) {
        return row.label || (
            (row.diagnosis_code || '') +
            ' - ' +
            (row.diagnosis_name || '')
        );
    }

    function patientByRef(value) {
        value = String(value || '');

        return patientRows.find(function (row) {
            return String(row.ref || '') === value;
        }) || null;
    }

    function appointmentByRef(value) {
        value = String(value || '');

        return appointmentRows.find(function (row) {
            return String(row.ref || '') === value;
        }) || null;
    }

    function setPatientOptions(selected) {
        patientSelect.setOptions(
            optionItems(patientRows, 'ref', patientText),
            selected || ''
        );

        form.patient_ref.value =
            selected || '';

        if (
            patientSelect &&
            typeof patientSelect.sync === 'function'
        ) {
            patientSelect.sync();
        }

        form.patient_ref.value =
            selected || '';

        applyPatientDetails();
    }

    function setAppointmentOptions(selected) {
        appointmentSelect.setOptions(
            optionItems(appointmentRows, 'ref', appointmentText),
            selected || ''
        );

        form.appointment_ref.value =
            selected || '';

        if (
            appointmentSelect &&
            typeof appointmentSelect.sync === 'function'
        ) {
            appointmentSelect.sync();
        }

        form.appointment_ref.value =
            selected || '';
    }

    function setDiagnosisOptions(selected) {
        selected =
            selected == null
                ? ''
                : String(selected);

        diagnosisSelect.setOptions(
            optionItems(
                diagnosisRows,
                'ref',
                diagnosisText
            ),
            selected
        );

        form.diagnosis_ref.value =
            selected;

        if (
            diagnosisSelect &&
            typeof diagnosisSelect.sync === 'function'
        ) {
            diagnosisSelect.sync();
        }

        form.diagnosis_ref.value =
            selected;
    }

    function fillPatientDetails(row) {
        var summary =
            document.getElementById(
                'patientSummary'
            );

        if (!row) {
            summary.textContent =
                'Select a Patient to view details.';
            return;
        }

        var parts = [];

        if (row.patient_code) {
            parts.push(
                'Code: ' + row.patient_code
            );
        }

        if (row.patient_name) {
            parts.push(
                'Patient: ' + row.patient_name
            );
        }

        if (row.mobile) {
            parts.push(
                'Mobile: ' + row.mobile
            );
        }

        if (
            row.age !== null &&
            row.age !== undefined &&
            row.age !== ''
        ) {
            parts.push(
                'Age: ' + row.age
            );
        }

        if (row.gender) {
            parts.push(
                'Gender: ' + row.gender
            );
        }

        summary.textContent =
            parts.join(' | ');
    }

    function applyPatientDetails() {
        var row =
            patientByRef(
                form.patient_ref.value
            );

        fillPatientDetails(row);
    }

    function applyAppointmentDetails() {
        var row =
            appointmentByRef(
                form.appointment_ref.value
            );

        if (!row) {
            setPatientOptions('');
            fillPatientDetails(null);
            return;
        }

        /*
         * Appointment is the authoritative Patient source.
         * Do not depend on a disabled Patient GlobalSelect to
         * preserve its native SELECT value.
         */
        setPatientOptions(
            row.patient_ref || ''
        );

        form.patient_ref.value =
            row.patient_ref || '';

        if (
            patientSelect &&
            typeof patientSelect.setOptions === 'function'
        ) {
            patientSelect.setOptions(
                optionItems(
                    patientRows,
                    'ref',
                    patientText
                ),
                row.patient_ref || ''
            );
        }

        form.patient_ref.value =
            row.patient_ref || '';

        /*
         * Fill Patient summary directly from Appointment data.
         * This guarantees Code / Name / Mobile / Age / Gender
         * even if GlobalSelect redraws while disabled.
         */
        fillPatientDetails({
            patient_code:
                row.patient_code || '',

            patient_name:
                row.patient_name || '',

            mobile:
                row.mobile || '',

            age:
                row.age,

            gender:
                row.gender || ''
        });

        if (!isFilling) {
            if (
                row.appointment_date
            ) {
                form.visit_date.value =
                    row.appointment_date;
            }

            if (
                row.appointment_time
            ) {
                form.visit_time.value =
                    row.appointment_time;
            }

            if (
                !form.consultant_name.value.trim() &&
                row.consultant_name
            ) {
                form.consultant_name.value =
                    row.consultant_name;
            }
        }
    }

    function applyVisitSource() {
        var walkin =
            form.visit_source.value ===
            'walkin';

        document.getElementById(
            'appointmentField'
        ).hidden =
            walkin;

        document.getElementById(
            'patientSelectRow'
        ).hidden =
            !walkin;

        form.appointment_ref.required =
            !walkin;

        form.patient_ref.required =
            walkin;

        document.getElementById(
            'addPatientButton'
        ).hidden =
            !walkin ||
            viewMode;

        if (walkin) {
            form.patient_ref.disabled =
                false;

            setSelect(
                appointmentSelect,
                '',
                form.appointment_ref
            );

            setPatientOptions('');
            fillPatientDetails(null);
        } else {
            /*
             * Appointment already owns the Patient.
             * Hide Patient selector and show only Patient details.
             */
            form.patient_ref.disabled =
                false;

            applyAppointmentDetails();

            form.patient_ref.disabled =
                true;
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

    function resolveTreatmentProcedureRef(day) {
        day = day || {};

        var procedureCode =
            String(
                day.procedure_code ||
                ''
            ).trim();

        var procedureName =
            String(
                day.procedure_name ||
                ''
            ).trim();

        var directRef =
            String(
                day.treatment_procedure_ref ||
                ''
            ).trim();

        /*
         * Encrypted references can be regenerated separately by
         * different API responses. Therefore use the stable Procedure
         * Code first, then Procedure Name, and finally the supplied ref.
         */
        if (procedureCode) {
            for (
                var i = 0;
                i < treatmentProcedureRows.length;
                i += 1
            ) {
                if (
                    String(
                        treatmentProcedureRows[i].procedure_code ||
                        ''
                    ).trim() === procedureCode
                ) {
                    return String(
                        treatmentProcedureRows[i].ref ||
                        ''
                    );
                }
            }
        }

        if (procedureName) {
            for (
                var j = 0;
                j < treatmentProcedureRows.length;
                j += 1
            ) {
                if (
                    String(
                        treatmentProcedureRows[j].procedure_name ||
                        ''
                    ).trim().toLowerCase() ===
                    procedureName.toLowerCase()
                ) {
                    return String(
                        treatmentProcedureRows[j].ref ||
                        ''
                    );
                }
            }
        }

        return directRef;
    }

    function initProcedureSelect(row, selectedRef) {
        var select =
            row.querySelector(
                '[data-field="treatment_procedure_ref"]'
            );

        procedureSelectCounter += 1;

        select.id =
            'consultationTreatmentProcedure_' +
            procedureSelectCounter;

        var procedureError =
            row.querySelector(
                '[data-procedure-error]'
            );

        if (procedureError) {
            procedureError.id =
                select.id + '_error';

            select.setAttribute(
                'data-error-id',
                procedureError.id
            );
        }

        var instance =
            GlobalSelect.init(
                '#' + select.id,
                {
                    placeholder:
                        'Select or type Treatment / Procedure'
                }
            );

        row._treatmentProcedureSelect =
            instance;

        if (
            instance &&
            typeof instance.setOptions === 'function'
        ) {
            instance.setOptions(
                procedureItems(
                    treatmentProcedureRows
                ),
                selectedRef || ''
            );
        }

        select.value =
            selectedRef ||
            '';

        if (
            instance &&
            typeof instance.sync === 'function'
        ) {
            instance.sync();
        }

        select.value =
            selectedRef ||
            '';

        return instance;
    }

    function refreshProcedureSelects(
        selectedRef,
        targetRow
    ) {
        treatmentRows().forEach(function (row) {
            var select =
                row.querySelector(
                    '[data-field="treatment_procedure_ref"]'
                );

            if (!select) return;

            var selected =
                row === targetRow
                    ? (selectedRef || '')
                    : select.value;

            /*
             * If the encrypted ref no longer matches after an options
             * refresh, restore selection from stable Procedure Code.
             */
            if (
                !selected &&
                row.dataset.procedureCode
            ) {
                selected =
                    resolveTreatmentProcedureRef({
                        procedure_code:
                            row.dataset.procedureCode
                    });
            }

            if (
                row._treatmentProcedureSelect &&
                typeof row._treatmentProcedureSelect.setOptions === 'function'
            ) {
                row._treatmentProcedureSelect.setOptions(
                    procedureItems(
                        treatmentProcedureRows
                    ),
                    selected
                );
            }

            select.value =
                selected ||
                '';

            if (
                row._treatmentProcedureSelect &&
                typeof row._treatmentProcedureSelect.sync === 'function'
            ) {
                row._treatmentProcedureSelect.sync();
            }

            select.value =
                selected ||
                '';
        });
    }

    async function reloadTreatmentProcedures(
        selectedRef,
        targetRow
    ) {
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

    function medicineItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value:row.ref,
                text:row.label || (
                    (row.medicine_code || '') +
                    ' - ' +
                    (row.medicine_name || '')
                )
            };
        });
    }

    function medicineByRef(ref) {
        ref=String(ref || '');
        return medicineRows.find(function(row){
            return String(row.ref || '')===ref;
        }) || null;
    }

    function resolveMedicineRef(data) {
        data=data || {};
        var code=String(data.medicine_code || '').trim();
        var name=String(data.medicine_name || '').trim().toLowerCase();
        var direct=String(data.medicine_ref || '').trim();
        var found=null;
        if (code) found=medicineRows.find(function(row){return String(row.medicine_code || '').trim()===code;});
        if (!found && name) found=medicineRows.find(function(row){return String(row.medicine_name || '').trim().toLowerCase()===name;});
        return found ? String(found.ref || '') : direct;
    }

    function rxField(row,name) {
        return row.querySelector('[data-rx-field="'+name+'"]');
    }

    function prescriptionRows() {
        return Array.prototype.slice.call(
            prescriptionContainer.querySelectorAll('[data-prescription-row]')
        );
    }

    function refreshPrescriptionTotal() {
        var total=0;

        prescriptionRows().forEach(function(row,index){
            row.querySelector(
                '[data-prescription-title]'
            ).textContent =
                String(index + 1);

            total +=
                parseFloat(
                    rxField(
                        row,
                        'amount'
                    ).value || '0'
                ) || 0;
        });

        document.getElementById(
            'prescriptionTotal'
        ).textContent =
            total.toFixed(2);

        var consultationFee =
            parseFloat(
                document.getElementById(
                    'consultationFee'
                ).value || '0'
            ) || 0;

        document.getElementById(
            'currentVisitCharges'
        ).textContent =
            (
                Math.max(0,consultationFee) +
                Math.max(0,total)
            ).toFixed(2);

        document.getElementById(
            'emptyPrescription'
        ).hidden =
            prescriptionRows().length > 0;
    }

    function recalcPrescriptionRow(row) {
        var qty=parseFloat(rxField(row,'quantity').value || '0') || 0;
        var price=parseFloat(rxField(row,'unit_price').value || '0') || 0;
        rxField(row,'amount').value=(Math.max(0,qty)*Math.max(0,price)).toFixed(2);
        refreshPrescriptionTotal();
    }

    function initMedicineSelect(row,selectedRef) {
        var select=rxField(row,'medicine_ref');
        medicineSelectCounter+=1;
        select.id='prescriptionMedicine_'+medicineSelectCounter;

        var medicineError=row.querySelector('[data-medicine-error]');
        if(medicineError){
            medicineError.id=select.id+'_error';
            select.setAttribute('data-error-id',medicineError.id);
        }

        var instance=GlobalSelect.init('#'+select.id,{placeholder:'Select or type Medicine'});
        row._medicineSelect=instance;
        if (instance && typeof instance.setOptions==='function') {
            instance.setOptions(medicineItems(medicineRows),selectedRef || '');
        }
        select.value=selectedRef || '';
        if (instance && typeof instance.sync==='function') instance.sync();
        select.value=selectedRef || '';
        return instance;
    }

    function initPrescriptionChoice(select,placeholder) {
        medicineSelectCounter+=1;
        select.id='prescriptionChoice_'+medicineSelectCounter;
        return GlobalSelect.init('#'+select.id,{placeholder:placeholder});
    }

    function applyMedicinePrice(row) {
        var medicine=medicineByRef(rxField(row,'medicine_ref').value);
        rxField(row,'unit_price').value=medicine ? String(medicine.selling_price || '0.00') : '0.00';
        row.dataset.medicineCode=medicine ? String(medicine.medicine_code || '') : '';
        recalcPrescriptionRow(row);
    }

    function addPrescription(data) {
        data=data || {};
        var fragment=prescriptionTemplate.content.cloneNode(true);
        var row=fragment.querySelector('[data-prescription-row]');
        prescriptionContainer.appendChild(fragment);
        var selectedRef=resolveMedicineRef(data);
        initMedicineSelect(row,selectedRef);
        row._frequencySelect=initPrescriptionChoice(rxField(row,'frequency'),'Select Frequency');
        row._timingSelect=initPrescriptionChoice(rxField(row,'timing'),'Select Timing');
        row._anupanaSelect=initPrescriptionChoice(rxField(row,'anupana'),'Select Anupana');
        rxField(row,'quantity').value=data.quantity || '1';
        rxField(row,'dosage').value=data.dosage || '';
        rxField(row,'frequency').value=data.frequency || '';
        rxField(row,'timing').value=data.timing || '';

        var defaultAnupana =
            Object.prototype.hasOwnProperty.call(
                data,
                'anupana'
            )
                ? String(data.anupana || '')
                : 'Warm Water';

        rxField(row,'anupana').value =
            defaultAnupana;

        rxField(row,'duration').value=data.duration || '';
        rxField(row,'instructions').value=data.instructions || '';
        if(row._frequencySelect&&typeof row._frequencySelect.sync==='function')row._frequencySelect.sync();
        if(row._timingSelect&&typeof row._timingSelect.sync==='function')row._timingSelect.sync();
        if(row._anupanaSelect&&typeof row._anupanaSelect.sync==='function')row._anupanaSelect.sync();
        var medicine=medicineByRef(selectedRef);
        row.dataset.medicineCode=data.medicine_code || (medicine ? String(medicine.medicine_code || '') : '');
        rxField(row,'unit_price').value=data.unit_price || (medicine ? medicine.selling_price : '0.00') || '0.00';
        recalcPrescriptionRow(row);
        if(window.lucide)window.lucide.createIcons();
    }

    function replacePrescriptions(items) {
        prescriptionContainer.innerHTML='';
        (items || []).forEach(function(item){addPrescription(item);});
        refreshPrescriptionTotal();
    }

    function collectPrescriptions() {
        return prescriptionRows().map(function(row){
            return {
                medicine_ref:String(rxField(row,'medicine_ref').value || '').trim(),
                quantity:String(rxField(row,'quantity').value || '1').trim(),
                unit_price:String(rxField(row,'unit_price').value || '0.00').trim(),
                amount:String(rxField(row,'amount').value || '0.00').trim(),
                dosage:String(rxField(row,'dosage').value || '').trim(),
                frequency:String(rxField(row,'frequency').value || '').trim(),
                timing:String(rxField(row,'timing').value || '').trim(),
                anupana:String(rxField(row,'anupana').value || '').trim(),
                duration:String(rxField(row,'duration').value || '').trim(),
                instructions:String(rxField(row,'instructions').value || '').trim()
            };
        }).filter(function(row){return row.medicine_ref;});
    }

    async function reloadMedicines(saved,targetRow) {
        var response=await App.api('api/medicines.php?options=1');
        var data=response&&response.data?response.data:{};
        medicineRows=Array.isArray(data.medicines)?data.medicines:[];
        var selected='';
        if(saved){
            selected=resolveMedicineRef(saved);
        }
        prescriptionRows().forEach(function(row){
            var keep=row===targetRow ? selected : rxField(row,'medicine_ref').value;
            var keepExists=medicineRows.some(function(item){return String(item.ref || '')===String(keep || '');});
            if((!keep || !keepExists) && row.dataset.medicineCode) keep=resolveMedicineRef({medicine_code:row.dataset.medicineCode});
            if(row._medicineSelect&&typeof row._medicineSelect.setOptions==='function')row._medicineSelect.setOptions(medicineItems(medicineRows),keep || '');
            rxField(row,'medicine_ref').value=keep || '';
            if(row._medicineSelect&&typeof row._medicineSelect.sync==='function')row._medicineSelect.sync();
            rxField(row,'medicine_ref').value=keep || '';
            applyMedicinePrice(row);
        });
    }

    function rowField(row, name) {
        return row.querySelector('[data-field="' + name + '"]');
    }

    function treatmentRows() {
        return Array.prototype.slice.call(
            dayContainer.querySelectorAll('[data-treatment-day]')
        );
    }

    function reindexDays() {
        treatmentRows().forEach(function (row, index) {
            row.querySelector('[data-day-title]').textContent =
                'Day ' + (index + 1);
        });

        document.getElementById('emptyDays').hidden =
            treatmentRows().length > 0;
    }

    function addDay(data) {
        data = data || {};

        var fragment = dayTemplate.content.cloneNode(true);
        var row = fragment.querySelector('[data-treatment-day]');

        dayContainer.appendChild(fragment);

        row.dataset.procedureCode =
            data.procedure_code ||
            '';

        initProcedureSelect(
            row,
            resolveTreatmentProcedureRef(
                data
            )
        );

        rowField(row, 'instructions').value =
            data.instructions || '';

        rowField(row, 'notes').value =
            data.notes || '';

        rowField(row, 'status').value =
            Number(data.status) === 0
                ? '0'
                : '1';

        reindexDays();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function replaceDays(days) {
        dayContainer.innerHTML = '';

        (days || []).forEach(function (day) {
            addDay(day);
        });

        reindexDays();
    }

    function collectDays() {
        return treatmentRows().map(function (row, index) {
            return {
                day_number: index + 1,
                treatment_procedure_ref:
                    String(
                        rowField(
                            row,
                            'treatment_procedure_ref'
                        ).value || ''
                    ).trim(),
                instructions:
                    String(
                        rowField(row, 'instructions').value ||
                        ''
                    ).trim(),
                notes:
                    String(
                        rowField(row, 'notes').value ||
                        ''
                    ).trim(),
                status:
                    rowField(row, 'status').value
            };
        });
    }

    function applyTreatmentBasis() {
        var diagnosisBased =
            form.treatment_basis.value ===
            'protocol';

        form.diagnosis_ref.required =
            diagnosisBased;

        document.getElementById(
            'diagnosisLabel'
        ).classList.toggle(
            'required',
            diagnosisBased
        );

        document.getElementById(
            'diagnosisField'
        ).hidden =
            !diagnosisBased;

        if (!diagnosisBased) {
            /*
             * General Treatment has no Diagnosis relationship.
             * The API stores diagnosis_id as NULL.
             */
            setDiagnosisOptions('');

            if (!treatmentRows().length) {
                addDay({});
            }
        }
    }

    async function loadDiagnosisTreatmentPlan(
        replaceExisting
    ) {
        if (
            form.treatment_basis.value !==
            'protocol'
        ) {
            return;
        }

        var diagnosisRef =
            String(
                form.diagnosis_ref.value ||
                ''
            ).trim();

        var requestSequence =
            ++treatmentPlanLoadSequence;

        if (!diagnosisRef) {
            if (replaceExisting) {
                replaceDays([]);
            }

            return;
        }

        try {
            var response =
                await App.api(
                    'api/consultations.php?treatment_plan=1&diagnosis_ref=' +
                    encodeURIComponent(
                        diagnosisRef
                    )
                );

            /*
             * Ignore an older response if Diagnosis changed
             * before the request completed.
             */
            if (
                requestSequence !==
                treatmentPlanLoadSequence
            ) {
                return;
            }

            var data =
                apiData(response);

            var treatmentPlan =
                data.treatment_plan ||
                data.protocol ||
                null;

            var days =
                Array.isArray(data.days)
                    ? data.days
                    : [];

            if (!treatmentPlan || !days.length) {
                if (replaceExisting) {
                    replaceDays([]);
                }

                showToast(
                    'No active Treatment / Procedure Plan is configured for this Diagnosis.',
                    {
                        type:'warning',
                        duration:4
                    }
                );

                return;
            }

            if (replaceExisting) {
                replaceDays(days);

                /*
                 * The Diagnosis becomes the default Patient
                 * Treatment Plan name. User can still edit it.
                 */
                form.plan_name.value =
                    treatmentPlan.diagnosis_name ||
                    treatmentPlan.protocol_name ||
                    '';
            }
        } catch (error) {
            if (
                requestSequence !==
                treatmentPlanLoadSequence
            ) {
                return;
            }

            App.showError(
                error,
                'Unable to load Diagnosis Treatment Plan.'
            );
        }
    }

    async function reloadPatients(selectedRef) {
        var response =
            await App.api(
                'api/consultations.php?options=1'
            );

        var data = apiData(response);

        patientRows =
            Array.isArray(data.patients)
                ? data.patients
                : [];

        setPatientOptions(selectedRef || '');
    }

    function setViewMode() {
        if (!viewMode) {
            return;
        }

        [
            'visitSource',
            'appointmentRef',
            'patientRef',
            'visitDate',
            'visitTime',
            'consultantName',
            'consultationStatus',
            'chiefComplaint',
            'symptoms',
            'clinicalNotes',
            'treatmentBasis',
            'diagnosisRef',
            'planName',
            'recordStatus',
            'planNotes'
        ].forEach(function (id) {
            var element = document.getElementById(id);

            if (!element) return;

            if (
                element.tagName === 'SELECT' ||
                element.type === 'date' ||
                element.type === 'time'
            ) {
                element.disabled = true;
            } else {
                element.readOnly = true;
            }
        });

        treatmentRows().forEach(function (row) {
            row.querySelectorAll('input,textarea,select,button')
                .forEach(function (control) {
                    control.disabled = true;
                });
        });

        prescriptionRows().forEach(function (row) {
            row.querySelectorAll('input,textarea,select,button')
                .forEach(function (control) {
                    control.disabled = true;
                });
        });

        document.getElementById('addMedicineButton').hidden = true;
        document.getElementById('addDayButton').hidden = true;
        document.getElementById('addPatientButton').hidden = true;
        saveButton.hidden = true;
    }

    function fillRecord(record) {
        isFilling = true;

        document.getElementById('consultationNo').value =
            record.consultation_no || '';

        setSelect(
            visitSourceSelect,
            record.visit_source || 'appointment',
            form.visit_source
        );

        applyVisitSource();

        setAppointmentOptions(
            record.appointment_ref || ''
        );

        setPatientOptions(
            record.patient_ref || ''
        );

        form.visit_date.value =
            record.visit_date || '';

        form.visit_time.value =
            record.visit_time || '';

        form.consultant_name.value =
            record.consultant_name || '';

        form.chief_complaint.value =
            record.chief_complaint || '';

        form.symptoms.value =
            record.symptoms || '';

        form.clinical_notes.value =
            record.clinical_notes || '';

        form.consultation_fee.value =
            record.consultation_fee || '0.00';

        setSelect(
            consultationStatusSelect,
            record.consultation_status ||
                'In Progress',
            form.consultation_status
        );

        updateConsultationStatusHelp();

        setSelect(
            treatmentBasisSelect,
            record.treatment_basis ||
                'general',
            form.treatment_basis
        );

        applyTreatmentBasis();

        setDiagnosisOptions(
            record.diagnosis_ref || ''
        );

        form.plan_name.value =
            record.plan_name || '';

        form.plan_notes.value =
            record.plan_notes || '';

        setSelect(
            recordStatusSelect,
            Number(record.status) === 0
                ? '0'
                : '1',
            form.status
        );

        replacePrescriptions(
            Array.isArray(record.prescriptions)
                ? record.prescriptions
                : []
        );

        replaceDays(
            Array.isArray(record.days)
                ? record.days
                : []
        );

        document.getElementById('createdByName').value =
            record.created_by_name || '-';

        document.getElementById('createdAt').value =
            record.created_at || '-';

        document.getElementById('updatedAt').value =
            record.updated_at || '-';

        document.getElementById('auditRow').hidden =
            false;

        isFilling = false;
    }

    function updateConsultationStatusHelp() {
        var value =
            form.consultation_status.value;

        var message =
            'In Progress = consultation is ongoing.';

        if (value === 'Completed') {
            message =
                'Completed = today\'s consultation is finished. The linked Appointment becomes Completed.';
        } else if (value === 'Follow-up Required') {
            message =
                'Follow-up Required = today\'s consultation is finished, but the Patient needs another review / follow-up. The current linked Appointment becomes Completed.';
        }

        document.getElementById(
            'consultationStatusHelp'
        ).textContent =
            message;
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;

        try {
            if (reference) {
                var response =
                    await App.api(
                        'api/consultations.php?ref=' +
                        encodeURIComponent(reference)
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

                appointmentRows =
                    Array.isArray(data.appointments)
                        ? data.appointments
                        : [];

                diagnosisRows =
                    Array.isArray(data.diagnoses)
                        ? data.diagnoses
                        : [];

                treatmentProcedureRows =
                    Array.isArray(data.treatment_procedures)
                        ? data.treatment_procedures
                        : [];

                medicineRows =
                    Array.isArray(data.medicines)
                        ? data.medicines
                        : [];

                document.getElementById('pageHeading').textContent =
                    viewMode
                        ? 'View Consultation'
                        : 'Edit Consultation';

                document.getElementById('saveButtonText').textContent =
                    'Update Consultation';

                fillRecord(
                    data.record || {}
                );

                if (
                    !viewMode &&
                    !hasAction(allowedActions, 3)
                ) {
                    saveButton.disabled = true;
                }
            } else {
                var response =
                    await App.api(
                        'api/consultations.php?options=1'
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

                appointmentRows =
                    Array.isArray(data.appointments)
                        ? data.appointments
                        : [];

                diagnosisRows =
                    Array.isArray(data.diagnoses)
                        ? data.diagnoses
                        : [];

                treatmentProcedureRows =
                    Array.isArray(data.treatment_procedures)
                        ? data.treatment_procedures
                        : [];

                medicineRows =
                    Array.isArray(data.medicines)
                        ? data.medicines
                        : [];

                document.getElementById('consultationNo').value =
                    data.next_consultation_no || '';

                setAppointmentOptions(
                    preselectAppointment || ''
                );

                setPatientOptions('');

                setDiagnosisOptions('');
                replacePrescriptions([]);

                var now = new Date();

                form.visit_date.value =
                    now.getFullYear() +
                    '-' +
                    String(now.getMonth() + 1).padStart(2, '0') +
                    '-' +
                    String(now.getDate()).padStart(2, '0');

                form.visit_time.value =
                    String(now.getHours()).padStart(2, '0') +
                    ':' +
                    String(now.getMinutes()).padStart(2, '0');

                setSelect(
                    visitSourceSelect,
                    preselectAppointment
                        ? 'appointment'
                        : 'walkin',
                    form.visit_source
                );

                setSelect(
                    treatmentBasisSelect,
                    'protocol',
                    form.treatment_basis
                );

                setSelect(
                    consultationStatusSelect,
                    'In Progress',
                    form.consultation_status
                );

                updateConsultationStatusHelp();

                setSelect(
                    recordStatusSelect,
                    '1',
                    form.status
                );

                applyVisitSource();
                applyTreatmentBasis();
                refreshPrescriptionTotal();

                if (preselectAppointment) {
                    applyAppointmentDetails();
                }

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

            App.showError(
                error,
                'Unable to prepare Consultation Form.'
            );
        } finally {
            isLoading = false;
        }
    }

    document.getElementById('visitSource')
        .addEventListener('change', function () {
            if (isFilling) return;
            applyVisitSource();
        });

    document.getElementById('appointmentRef')
        .addEventListener('change', function () {
            if (isFilling) return;

            if (
                form.visit_source.value ===
                'appointment'
            ) {
                form.patient_ref.disabled =
                    false;

                applyAppointmentDetails();

                form.patient_ref.disabled =
                    true;
            }
        });

    document.getElementById('patientRef')
        .addEventListener('change', function () {
            applyPatientDetails();
        });

    document.getElementById('consultationStatus')
        .addEventListener('change', function () {
            updateConsultationStatusHelp();
        });

    document.getElementById('treatmentBasis')
        .addEventListener('change', function () {
            if (isFilling) return;

            applyTreatmentBasis();

            if (
                form.treatment_basis.value ===
                'protocol'
            ) {
                window.setTimeout(
                    function () {
                        loadDiagnosisTreatmentPlan(true);
                    },
                    0
                );
            } else {
                if (!treatmentRows().length) {
                    addDay({});
                }
            }
        });

    document.getElementById('diagnosisRef')
        .addEventListener('change', function () {
            if (isFilling) return;

            var selected =
                String(
                    this.value ||
                    ''
                );

            form.diagnosis_ref.value =
                selected;

            if (
                form.treatment_basis.value ===
                'protocol'
            ) {
                window.setTimeout(
                    function () {
                        loadDiagnosisTreatmentPlan(true);
                    },
                    0
                );
            }
        });

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
                    var ref =
                        saved && saved.ref
                            ? saved.ref
                            : '';

                    reloadPatients(ref)
                        .catch(function (error) {
                            App.showError(
                                error,
                                'Patient was created, but Patient options could not be refreshed.'
                            );
                        });
                }
            });
        });

    document.getElementById(
        'consultationFee'
    ).addEventListener(
        'input',
        refreshPrescriptionTotal
    );

    document.getElementById('addMedicineButton')
        .addEventListener('click', function () {
            addPrescription({});
        });

    prescriptionContainer.addEventListener('change', function(event){
        var row=event.target.closest('[data-prescription-row]');
        if(!row)return;
        if(event.target.matches('[data-rx-field="medicine_ref"]'))applyMedicinePrice(row);
        if(event.target.matches('[data-rx-field="quantity"]'))recalcPrescriptionRow(row);
    });

    prescriptionContainer.addEventListener('input', function(event){
        var row=event.target.closest('[data-prescription-row]');
        if(row && event.target.matches('[data-rx-field="quantity"]'))recalcPrescriptionRow(row);
    });

    prescriptionContainer.addEventListener('click', function(event){
        var row=event.target.closest('[data-prescription-row]');
        if(!row)return;
        var addMaster=event.target.closest('.add-medicine-master');
        if(addMaster){
            if(window.AppMedicineForm){
                AppMedicineForm.openCreate({onSaved:function(saved){reloadMedicines(saved,row).catch(function(error){App.showError(error,'Medicine was created, but options could not be refreshed.');});}});
            }
            return;
        }
        var remove=event.target.closest('.remove-prescription-row');
        if(remove){row.remove();refreshPrescriptionTotal();}
    });

    document.getElementById('addDayButton')
        .addEventListener('click', function () {
            addDay({});
        });

    dayContainer.addEventListener('click', function (event) {
        var addProcedureButton =
            event.target.closest(
                '.add-treatment-procedure'
            );

        if (addProcedureButton) {
            var targetRow =
                addProcedureButton.closest(
                    '[data-treatment-day]'
                );

            if (
                window.AppTreatmentProcedureForm
            ) {
                AppTreatmentProcedureForm.openCreate({
                    onSaved:function(saved) {
                        var newRef =
                            saved &&
                            saved.ref
                                ? saved.ref
                                : '';

                        reloadTreatmentProcedures(
                            newRef,
                            targetRow
                        ).catch(
                            function(error) {
                                App.showError(
                                    error,
                                    'Treatment / Procedure was created, but options could not be refreshed.'
                                );
                            }
                        );
                    }
                });
            }

            return;
        }

        var button =
            event.target.closest(
                '.remove-day-row'
            );

        if (!button) return;

        var row =
            button.closest(
                '[data-treatment-day]'
            );

        if (row) {
            row.remove();
            reindexDays();
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (viewMode) {
            return;
        }

        Validation.clearForm(form);

        applyVisitSource();
        applyTreatmentBasis();

        if (!Validation.validateForm(form)) {
            return;
        }

        var days = collectDays();

        if (!days.length) {
            showToast(
                'Add at least one Treatment Plan day.',
                {
                    type: 'danger',
                    duration: 3
                }
            );
            return;
        }

        for (var i = 0; i < days.length; i++) {
            if (!days[i].treatment_procedure_ref) {
                showToast(
                    'Treatment / Procedure is required for Day ' +
                    (i + 1) +
                    '.',
                    {
                        type: 'danger',
                        duration: 3
                    }
                );
                return;
            }
        }

        var prescriptions = collectPrescriptions();

        document.getElementById('prescriptionItemsJson').value =
            JSON.stringify(prescriptions);

        document.getElementById('treatmentDaysJson').value =
            JSON.stringify(days);

        /*
         * Appointment Consultation derives Patient from Appointment in API.
         * Keep the visible Patient selection synchronized before building
         * FormData.
         */
        if (
            form.visit_source.value ===
            'appointment'
        ) {
            var appointmentRow =
                appointmentByRef(
                    form.appointment_ref.value
                );

            if (appointmentRow) {
                form.patient_ref.value =
                    appointmentRow.patient_ref ||
                    '';
            }
        }

        var data =
            new FormData(form);

        if (reference) {
            data.set('ref', reference);
        }

        data.set('action', 'save');

        if (form.visit_source.value === 'appointment') {
            data.delete('patient_ref');
        } else {
            data.delete('appointment_ref');
        }

        saveButton.disabled = true;

        try {
            var response =
                await App.api(
                    'api/consultations.php',
                    {
                        method: 'POST',
                        body: data
                    }
                );

            showToast(
                response.message ||
                'Consultation saved successfully.',
                {
                    type: 'success',
                    duration: 2
                }
            );

            window.setTimeout(function () {
                window.location.href =
                    'consultation-list.php';
            }, 700);
        } catch (error) {
            Validation.applyErrors(
                form,
                error.errors || {}
            );

            App.showError(
                error,
                'Unable to save Consultation.'
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
