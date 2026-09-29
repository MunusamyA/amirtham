<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Clinic Consultation & Billing';
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
        <h1 id="pageHeading">Clinic Consultation & Billing</h1>
        <p>
            Select Patient. One pending Consultation is auto-selected;
            multiple pending Consultations require selection.
            If none exists, create the Consultation here and then bill it.
        </p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="bill-list.php">
            <i data-lucide="list"></i>
            Bill List
        </a>

        <a
            class="btn gray"
            id="printButton"
            href="#"
            target="_blank"
            hidden
        >
            <i data-lucide="printer"></i>
            Print Bill
        </a>
    </div>
</div>

<div class="card form-card">
<form id="clinicBillingForm" novalidate>

<input
    id="billItemsJson"
    name="bill_items_json"
    type="hidden"
    value="[]"
>

<input
    id="paymentBreakdownJson"
    name="payment_breakdown_json"
    type="hidden"
    value="[]"
>

<div class="card-header">
    <div>
        <h2>Patient & Consultation</h2>
        <p>
            Billing is linked to one Consultation only.
        </p>
    </div>
</div>

<div class="card-body">

    <div class="form-row">
        <div class="field col-6">
            <label for="patientRef" class="required">Patient</label>
            <select
                id="patientRef"
                name="patient_ref"
                required
                data-required-message="Patient is required."
            >
                <option value="">Select Patient</option>
            </select>
        </div>

        <div class="field col-6">
            <label for="consultationRef">Pending Consultation</label>
            <div class="input-group">
                <div class="input-group-control">
                    <select id="consultationRef">
                        <option value="">Select Consultation</option>
                    </select>
                </div>

                <button
                    class="btn btn-primary input-group-button"
                    id="newConsultationButton"
                    type="button"
                    title="Start New Consultation"
                    aria-label="Start New Consultation"
                >
                    <i data-lucide="plus"></i>
                </button>
            </div>
            <small id="consultationHint" class="muted">
                Select Patient first.
            </small>
        </div>
    </div>

    <div id="patientSummary" class="muted">
        Select a Patient to view details.
    </div>

    <div class="card-section-title">Visit Details</div>

    <div class="form-row">
        <div class="field col-3">
            <label>Consultation No.</label>
            <input
                id="consultationNo"
                type="text"
                readonly
                placeholder="Auto generated"
            >
        </div>

        <div class="field col-3">
            <label for="visitSource" class="required">Visit Source</label>
            <select id="visitSource">
                <option value="walkin">Walk-in / Direct</option>
                <option value="appointment">Appointment</option>
            </select>
        </div>

        <div class="field col-6" id="appointmentField" hidden>
            <label for="appointmentRef" class="required">Appointment</label>
            <select id="appointmentRef">
                <option value="">Select Appointment</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="field col-3">
            <label for="visitDate" class="required">Visit Date</label>
            <input id="visitDate" type="date" required>
        </div>

        <div class="field col-3">
            <label for="visitTime" class="required">Visit Time</label>
            <input id="visitTime" type="time" required>
        </div>

        <div class="field col-3">
            <label for="consultantName">Doctor / Consultant</label>
            <input
                id="consultantName"
                type="text"
                maxlength="150"
                placeholder="Doctor / Consultant"
            >
        </div>

        <div class="field col-3">
            <label for="consultationFee" class="required">Consultation Fee</label>
            <input
                id="consultationFee"
                type="text"
                inputmode="decimal"
                value="0.00"
                required
            >
        </div>
    </div>

    <div class="card-section-title">Consultation</div>

    <div class="form-row">
        <div class="field col-4">
            <label for="chiefComplaint">Chief Complaint</label>
            <textarea
                id="chiefComplaint"
                rows="3"
                maxlength="5000"
                placeholder="Chief Complaint"
            ></textarea>
        </div>

        <div class="field col-4">
            <label for="symptoms">Symptoms</label>
            <textarea
                id="symptoms"
                rows="3"
                maxlength="5000"
                placeholder="Symptoms"
            ></textarea>
        </div>

        <div class="field col-4">
            <label for="clinicalNotes">Clinical Notes</label>
            <textarea
                id="clinicalNotes"
                rows="3"
                maxlength="5000"
                placeholder="Clinical Notes"
            ></textarea>
        </div>
    </div>

    <div class="card-section-title">Diagnosis & Treatment Plan</div>

    <div class="form-row">
        <div class="field col-3">
            <label for="treatmentBasis" class="required">Treatment Basis</label>
            <select id="treatmentBasis">
                <option value="protocol">Diagnosis Treatment</option>
                <option value="general">General Treatment</option>
            </select>
        </div>

        <div class="field col-5" id="diagnosisField">
            <label for="diagnosisRef" class="required">Diagnosis</label>
            <select id="diagnosisRef">
                <option value="">Select Diagnosis</option>
            </select>
        </div>

        <div class="field col-4">
            <label for="planName" class="required">Treatment Plan Name</label>
            <input
                id="planName"
                type="text"
                maxlength="180"
                placeholder="Treatment Plan Name"
                required
            >
        </div>
    </div>

    <div class="form-row">
        <div class="field col-9">
            <label for="planNotes">Treatment Plan Notes</label>
            <input
                id="planNotes"
                type="text"
                maxlength="5000"
                placeholder="Optional Notes"
            >
        </div>

        <div class="field col-3">
            <label for="consultationStatus">Consultation Status</label>
            <select id="consultationStatus">
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Follow-up Required">Follow-up Required</option>
            </select>
        </div>
    </div>

    <div class="card-section-title card-section-title-actions">
        <span>Patient Treatment Plan</span>

        <button
            class="btn btn-primary small"
            id="addTreatmentDayButton"
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
            <tbody id="treatmentDayRows"></tbody>
        </table>
    </div>

    <div id="emptyTreatmentDays" class="muted">
        No Treatment Day added.
    </div>

    <div class="card-section-title card-section-title-actions">
        <span>Ayurveda Prescription</span>

        <button
            class="btn btn-primary small"
            id="addMedicineButton"
            type="button"
        >
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
        <div id="emptyPrescription" class="muted">
            Prescription is optional.
        </div>

        <div class="muted">
            Medicine Total:
            <strong>₹<span id="medicineTotal">0.00</span></strong>
            &nbsp; | &nbsp;
            Current Visit Charges:
            <strong>₹<span id="visitChargeTotal">0.00</span></strong>
        </div>
    </div>

    <div class="buttons">
        <button
            class="btn gray"
            id="saveConsultationButton"
            type="button"
        >
            <i data-lucide="save"></i>
            Save Consultation
        </button>

        <button
            class="btn btn-primary"
            id="completeConsultationButton"
            type="button"
        >
            <i data-lucide="check-circle"></i>
            Complete Consultation
        </button>
    </div>

    <div class="card-section-title">Billing Details</div>

    <div class="form-row">
        <div class="field col-4">
            <label>Bill No.</label>
            <input id="billNo" type="text" readonly>
        </div>

        <div class="field col-4">
            <label>Billing Consultation</label>
            <input id="billingConsultation" type="text" readonly>
        </div>

        <div class="field col-4">
            <label for="billDate" class="required">Bill Date</label>
            <input
                id="billDate"
                name="bill_date"
                type="date"
                required
            >
        </div>
    </div>

    <div class="card-section-title card-section-title-actions">
        <span>Consultation Pending Charges</span>

        <div class="buttons">
            <button
                class="btn gray small"
                id="selectAllCharges"
                type="button"
            >
                Select All
            </button>

            <button
                class="btn gray small"
                id="clearAllCharges"
                type="button"
            >
                Clear
            </button>
        </div>
    </div>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
            <tr>
                <th>Include</th>
                <th>Type</th>
                <th>Date</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
            </thead>
            <tbody id="chargeRows"></tbody>
        </table>
    </div>

    <div id="emptyCharges" class="muted">
        Complete the Consultation to load billable charges.
    </div>

    <div class="card-section-title card-section-title-actions">
        <span>Optional Lab Add-on</span>

        <button
            class="btn btn-primary small"
            id="addLabButton"
            type="button"
        >
            <i data-lucide="plus"></i>
            Add Lab Test
        </button>
    </div>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
            <tr>
                <th>Lab Test / Report</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="labRows"></tbody>
        </table>
    </div>

    <div id="emptyLabs" class="muted">
        No Lab Test added. Lab is optional.
    </div>

    <div class="card-section-title">Payment & Bill Summary</div>

    <div class="app-split-grid">

        <div class="app-side-card">
            <div class="app-side-card-head">Payment Details</div>

            <div class="app-side-card-body">
                <div class="card table-card app-allocation-wrap">
                    <table
                        class="app-allocation-table"
                        id="paymentAllocationTable"
                    >
                        <thead>
                        <tr>
                            <th class="col-mode">Mode</th>
                            <th class="col-account">Account</th>
                            <th class="col-amount">Amount</th>
                            <th class="col-reference">Reference No</th>
                            <th class="col-date">Date</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr data-payment-row data-mode="CASH">
                            <td class="app-allocation-mode">Cash</td>
                            <td>
                                <select
                                    id="cashAccountId"
                                    data-payment-field="account_id"
                                >
                                    <option value="">Select Cash Account</option>
                                </select>
                            </td>
                            <td>
                                <input
                                    data-payment-field="amount"
                                    type="text"
                                    inputmode="decimal"
                                    value="0.00"
                                >
                            </td>
                            <td>
                                <input
                                    data-payment-field="reference_no"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Optional"
                                >
                            </td>
                            <td class="muted">—</td>
                        </tr>

                        <tr data-payment-row data-mode="UPI">
                            <td class="app-allocation-mode">UPI</td>
                            <td>
                                <select
                                    id="upiAccountId"
                                    data-payment-field="account_id"
                                >
                                    <option value="">Select Bank Account</option>
                                </select>
                            </td>
                            <td>
                                <input
                                    data-payment-field="amount"
                                    type="text"
                                    inputmode="decimal"
                                    value="0.00"
                                >
                            </td>
                            <td>
                                <input
                                    data-payment-field="reference_no"
                                    type="text"
                                    maxlength="150"
                                    placeholder="UTR / Ref No"
                                >
                            </td>
                            <td class="muted">—</td>
                        </tr>

                        <tr data-payment-row data-mode="BANK">
                            <td class="app-allocation-mode">Bank</td>
                            <td>
                                <select
                                    id="bankAccountId"
                                    data-payment-field="account_id"
                                >
                                    <option value="">Select Bank Account</option>
                                </select>
                            </td>
                            <td>
                                <input
                                    data-payment-field="amount"
                                    type="text"
                                    inputmode="decimal"
                                    value="0.00"
                                >
                            </td>
                            <td>
                                <input
                                    data-payment-field="reference_no"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Transaction / Ref No"
                                >
                            </td>
                            <td class="muted">—</td>
                        </tr>

                        <tr data-payment-row data-mode="CHEQUE">
                            <td class="app-allocation-mode">Cheque</td>
                            <td>
                                <select
                                    id="chequeAccountId"
                                    data-payment-field="account_id"
                                >
                                    <option value="">Select Bank Account</option>
                                </select>
                            </td>
                            <td>
                                <input
                                    data-payment-field="amount"
                                    type="text"
                                    inputmode="decimal"
                                    value="0.00"
                                >
                            </td>
                            <td>
                                <input
                                    data-payment-field="reference_no"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Cheque No"
                                >
                            </td>
                            <td>
                                <input
                                    id="chequeDate"
                                    data-payment-field="payment_date"
                                    type="date"
                                >
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="app-total-line">
                    <span>Paid Now</span>
                    <strong id="paidNow">₹0.00</strong>
                </div>

                <div class="app-total-line">
                    <span>Balance</span>
                    <strong id="summaryBalance">₹0.00</strong>
                </div>
            </div>
        </div>

        <div class="app-side-card">
            <div class="app-side-card-head">Bill Summary</div>

            <div class="app-side-card-body">
                <div class="app-summary-row">
                    <span>Subtotal</span>
                    <strong id="sumSubtotal">₹0.00</strong>
                </div>

                <div class="field">
                    <label for="discountAmount">Discount</label>
                    <input
                        id="discountAmount"
                        name="discount_amount"
                        type="text"
                        inputmode="decimal"
                        value="0.00"
                    >
                </div>

                <div class="app-summary-row total">
                    <span>Grand Total</span>
                    <strong id="sumGrand">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Paid Amount</span>
                    <strong id="sumPaid">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Balance</span>
                    <strong id="sumBalance">₹0.00</strong>
                </div>

                <div class="app-summary-row">
                    <span>Payment Status</span>
                    <strong id="sumPaymentStatus">Unpaid</strong>
                </div>
            </div>
        </div>

    </div>

    <input id="subtotal" type="hidden" value="0.00">
    <input id="grandTotal" type="hidden" value="0.00">
    <input id="paidAmount" name="paid_amount" type="hidden" value="0.00">
    <input id="balanceAmount" type="hidden" value="0.00">
    <input id="paymentStatus" type="hidden" value="Unpaid">

    <div class="card-section-title">Notes</div>

    <div class="form-row">
        <div class="field col-12">
            <label for="billNotes">Bill Notes</label>
            <input
                id="billNotes"
                name="notes"
                type="text"
                maxlength="5000"
                placeholder="Bill Notes"
            >
        </div>
    </div>

</div>

<div class="card-footer">
    <div class="buttons">
        <button
            class="btn btn-primary"
            id="saveBillButton"
            type="submit"
        >
            <i data-lucide="save"></i>
            <span id="saveBillText">Save Bill</span>
        </button>

        <a class="btn gray" href="bill-list.php">
            Cancel
        </a>
    </div>
</div>

</form>
</div>

<template id="treatmentDayTemplate">
<tr data-treatment-day>
    <td>
        <strong data-day-number>Day 1</strong>
    </td>

    <td>
        <select data-day-field="treatment_procedure_ref">
            <option value="">Select Treatment / Procedure</option>
        </select>
    </td>

    <td>
        <textarea
            data-day-field="instructions"
            rows="2"
            maxlength="5000"
            placeholder="Instructions"
        ></textarea>
    </td>

    <td>
        <textarea
            data-day-field="notes"
            rows="2"
            maxlength="5000"
            placeholder="Notes"
        ></textarea>
    </td>

    <td>
        <select data-day-field="status">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </td>

    <td class="table-action-icons">
        <button
            class="table-icon-action danger remove-treatment-day"
            type="button"
            title="Remove Treatment"
            aria-label="Remove Treatment"
        >
            <i data-lucide="trash-2"></i>
        </button>
    </td>
</tr>
</template>

<template id="prescriptionTemplate">
<tr data-prescription-row>
    <td>
        <strong data-prescription-number>1</strong>
    </td>

    <td>
        <select data-rx-field="medicine_ref">
            <option value="">Select Medicine</option>
        </select>
        <input data-rx-field="unit_price" type="hidden" value="0.00">
        <input data-rx-field="amount" type="hidden" value="0.00">
    </td>

    <td>
        <input
            data-rx-field="quantity"
            type="text"
            inputmode="decimal"
            value="1"
        >
    </td>

    <td>
        <input
            data-rx-field="dosage"
            type="text"
            maxlength="100"
            placeholder="15 ml / 1 Tablet"
        >
    </td>

    <td>
        <select data-rx-field="frequency">
            <option value="">Select Frequency</option>
            <option value="Once Daily">Once Daily</option>
            <option value="Twice Daily">Twice Daily</option>
            <option value="Thrice Daily">Thrice Daily</option>
            <option value="Four Times Daily">Four Times Daily</option>
            <option value="As Required">As Required</option>
            <option value="Custom">Custom</option>
        </select>
    </td>

    <td>
        <select data-rx-field="timing">
            <option value="">Select Timing</option>
            <option value="Before Food">Before Food</option>
            <option value="After Food">After Food</option>
            <option value="With Food">With Food</option>
            <option value="Empty Stomach">Empty Stomach</option>
            <option value="Bedtime">Bedtime</option>
            <option value="Custom">Custom</option>
        </select>
    </td>

    <td>
        <input
            data-rx-field="duration"
            type="text"
            maxlength="100"
            placeholder="7 Days"
        >
    </td>

    <td>
        <select data-rx-field="anupana">
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

<template id="labRowTemplate">
<tr data-lab-row>
    <td>
        <select data-lab-field="source_ref">
            <option value="">Select Lab Test</option>
        </select>
    </td>

    <td>
        <input
            data-lab-field="quantity"
            type="text"
            inputmode="decimal"
            value="1"
        >
    </td>

    <td>
        <input
            data-lab-field="unit_price"
            type="text"
            value="0.00"
            readonly
        >
    </td>

    <td>
        <input
            data-lab-field="amount"
            type="text"
            value="0.00"
            readonly
        >
    </td>

    <td class="table-action-icons">
        <button
            class="table-icon-action danger remove-lab-row"
            type="button"
            title="Remove Lab Test"
            aria-label="Remove Lab Test"
        >
            <i data-lucide="trash-2"></i>
        </button>
    </td>
</tr>
</template>

<script>
(function(window,document){
    'use strict';

    var form =
        document.getElementById(
            'clinicBillingForm'
        );

    var params =
        new URLSearchParams(
            window.location.search
        );

    var billRef =
        params.get('ref') || '';

    var viewMode =
        params.get('view') === '1';

    var currentConsultationRef = '';
    var currentConsultationStatus = '';
    var currentBillRecord = null;

    var billingOptions = {};
    var consultationOptions = {};

    var patients = [];
    var pendingConsultations = [];
    var appointments = [];
    var diagnoses = [];
    var treatmentProcedures = [];
    var medicines = [];
    var labTests = [];
    var paymentAccounts = {
        cash:[],
        bank:[]
    };

    var sourceItems = [];

    var patientSelect =
        document.getElementById(
            'patientRef'
        );

    var consultationSelect =
        document.getElementById(
            'consultationRef'
        );

    var appointmentSelect =
        document.getElementById(
            'appointmentRef'
        );

    var diagnosisSelect =
        document.getElementById(
            'diagnosisRef'
        );

    var dayContainer =
        document.getElementById(
            'treatmentDayRows'
        );

    var prescriptionContainer =
        document.getElementById(
            'prescriptionRows'
        );

    var labContainer =
        document.getElementById(
            'labRows'
        );

    var chargeRows =
        document.getElementById(
            'chargeRows'
        );

    function dataOf(response) {
        return (
            response &&
            response.data &&
            typeof response.data === 'object'
        )
            ? response.data
            : {};
    }

    function esc(value) {
        return String(
            value == null
                ? ''
                : value
        )
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
    }

    function moneyNumber(value) {
        var n =
            Number(
                String(
                    value == null
                        ? '0'
                        : value
                ).replace(/,/g,'')
            );

        return Number.isFinite(n)
            ? n
            : 0;
    }

    function money(value) {
        return '₹' +
            moneyNumber(value)
                .toLocaleString(
                    'en-IN',
                    {
                        minimumFractionDigits:2,
                        maximumFractionDigits:2
                    }
                );
    }

    function today() {
        var d = new Date();

        return (
            d.getFullYear() +
            '-' +
            String(
                d.getMonth() + 1
            ).padStart(2,'0') +
            '-' +
            String(
                d.getDate()
            ).padStart(2,'0')
        );
    }

    function currentTime() {
        var d = new Date();

        return (
            String(
                d.getHours()
            ).padStart(2,'0') +
            ':' +
            String(
                d.getMinutes()
            ).padStart(2,'0')
        );
    }

    function setOptions(
        select,
        rows,
        valueKey,
        labelBuilder,
        selected
    ) {
        var value =
            String(
                selected || ''
            );

        select.innerHTML =
            '<option value="">Select</option>';

        (rows || []).forEach(
            function(row) {
                var option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    String(
                        row[valueKey] || ''
                    );

                option.textContent =
                    labelBuilder(row);

                if (option.value === value) {
                    option.selected = true;
                }

                select.appendChild(option);
            }
        );

        select.value = value;
    }

    function patientLabel(row) {
        var text =
            (row.patient_code || '') +
            ' - ' +
            (row.patient_name || '');

        if (row.mobile) {
            text +=
                ' - ' +
                row.mobile;
        }

        return text;
    }

    function fillPatientSummary(row) {
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
                'Code: ' +
                row.patient_code
            );
        }

        if (row.patient_name) {
            parts.push(
                'Patient: ' +
                row.patient_name
            );
        }

        if (row.mobile) {
            parts.push(
                'Mobile: ' +
                row.mobile
            );
        }

        if (
            row.age !== null &&
            row.age !== undefined &&
            row.age !== ''
        ) {
            parts.push(
                'Age: ' +
                row.age
            );
        }

        if (row.gender) {
            parts.push(
                'Gender: ' +
                row.gender
            );
        }

        summary.textContent =
            parts.join(' | ');
    }

    function selectedPatientOption() {
        var ref =
            String(
                patientSelect.value || ''
            );

        return (
            patients.find(
                function(row) {
                    return (
                        String(
                            row.ref || ''
                        ) === ref
                    );
                }
            ) ||
            null
        );
    }

    function consultationLabel(row) {
        return (
            row.label ||
            (
                (row.consultation_no || '') +
                ' - ' +
                (row.visit_date || '')
            )
        );
    }

    function treatmentProcedureLabel(row) {
        return (
            row.label ||
            (
                (row.procedure_code || '') +
                ' - ' +
                (row.procedure_name || '')
            )
        );
    }

    function medicineLabel(row) {
        return (
            row.label ||
            (
                (row.medicine_code || '') +
                ' - ' +
                (row.medicine_name || '')
            )
        );
    }

    function labLabel(row) {
        return (
            row.label ||
            (
                (row.lab_test_code || '') +
                ' - ' +
                (row.lab_test_name || '')
            )
        );
    }

    function clearBillingCharges() {
        sourceItems = [];
        chargeRows.innerHTML = '';

        document.getElementById(
            'emptyCharges'
        ).hidden = false;

        document.getElementById(
            'billingConsultation'
        ).value = '';

        recalcBillSummary();
    }

    function clearClinicalForm() {
        currentConsultationRef = '';
        currentConsultationStatus = '';

        document.getElementById(
            'consultationNo'
        ).value =
            consultationOptions
                .next_consultation_no ||
            '';

        document.getElementById(
            'visitSource'
        ).value = 'walkin';

        document.getElementById(
            'appointmentField'
        ).hidden = true;

        appointmentSelect.value = '';

        document.getElementById(
            'visitDate'
        ).value = today();

        document.getElementById(
            'visitTime'
        ).value = currentTime();

        document.getElementById(
            'consultantName'
        ).value = '';

        document.getElementById(
            'consultationFee'
        ).value = '0.00';

        document.getElementById(
            'chiefComplaint'
        ).value = '';

        document.getElementById(
            'symptoms'
        ).value = '';

        document.getElementById(
            'clinicalNotes'
        ).value = '';

        document.getElementById(
            'treatmentBasis'
        ).value = 'protocol';

        diagnosisSelect.value = '';

        document.getElementById(
            'diagnosisField'
        ).hidden = false;

        document.getElementById(
            'planName'
        ).value = '';

        document.getElementById(
            'planNotes'
        ).value = '';

        document.getElementById(
            'consultationStatus'
        ).value =
            'In Progress';

        dayContainer.innerHTML = '';
        prescriptionContainer.innerHTML = '';

        addTreatmentDay({});
        updateTreatmentDayNumbers();
        updatePrescriptionNumbers();
        recalcPrescriptionTotal();
        clearBillingCharges();
    }

    function treatmentDayRows() {
        return Array.prototype.slice.call(
            dayContainer.querySelectorAll(
                '[data-treatment-day]'
            )
        );
    }

    function dayField(row,name) {
        return row.querySelector(
            '[data-day-field="' +
            name +
            '"]'
        );
    }

    function addTreatmentDay(data) {
        data = data || {};

        var template =
            document.getElementById(
                'treatmentDayTemplate'
            );

        var row =
            template.content
                .firstElementChild
                .cloneNode(true);

        var procedure =
            dayField(
                row,
                'treatment_procedure_ref'
            );

        setOptions(
            procedure,
            treatmentProcedures,
            'ref',
            treatmentProcedureLabel,
            data.treatment_procedure_ref || ''
        );

        dayField(
            row,
            'instructions'
        ).value =
            data.instructions || '';

        dayField(
            row,
            'notes'
        ).value =
            data.notes || '';

        dayField(
            row,
            'status'
        ).value =
            String(
                data.status === 0
                    ? 0
                    : 1
            );

        dayContainer.appendChild(row);

        updateTreatmentDayNumbers();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function updateTreatmentDayNumbers() {
        var rows =
            treatmentDayRows();

        rows.forEach(
            function(row,index) {
                row.querySelector(
                    '[data-day-number]'
                ).textContent =
                    'Day ' +
                    (index + 1);
            }
        );

        document.getElementById(
            'emptyTreatmentDays'
        ).hidden =
            rows.length > 0;
    }

    function collectTreatmentDays() {
        return treatmentDayRows()
            .map(
                function(row,index) {
                    return {
                        day_number:
                            index + 1,
                        treatment_procedure_ref:
                            dayField(
                                row,
                                'treatment_procedure_ref'
                            ).value,
                        instructions:
                            dayField(
                                row,
                                'instructions'
                            ).value.trim(),
                        notes:
                            dayField(
                                row,
                                'notes'
                            ).value.trim(),
                        status:
                            Number(
                                dayField(
                                    row,
                                    'status'
                                ).value || 1
                            )
                    };
                }
            )
            .filter(
                function(row) {
                    return Boolean(
                        row.treatment_procedure_ref
                    );
                }
            );
    }

    function prescriptionRows() {
        return Array.prototype.slice.call(
            prescriptionContainer.querySelectorAll(
                '[data-prescription-row]'
            )
        );
    }

    function rxField(row,name) {
        return row.querySelector(
            '[data-rx-field="' +
            name +
            '"]'
        );
    }

    function medicineByRef(ref) {
        return (
            medicines.find(
                function(row) {
                    return (
                        String(
                            row.ref || ''
                        ) ===
                        String(
                            ref || ''
                        )
                    );
                }
            ) ||
            null
        );
    }

    function recalcPrescriptionRow(row) {
        var medicine =
            medicineByRef(
                rxField(
                    row,
                    'medicine_ref'
                ).value
            );

        var price =
            medicine
                ? moneyNumber(
                    medicine.selling_price
                )
                : moneyNumber(
                    rxField(
                        row,
                        'unit_price'
                    ).value
                );

        var qty =
            moneyNumber(
                rxField(
                    row,
                    'quantity'
                ).value
            );

        if (qty <= 0) {
            qty = 1;
        }

        rxField(
            row,
            'unit_price'
        ).value =
            price.toFixed(2);

        rxField(
            row,
            'amount'
        ).value =
            (
                price * qty
            ).toFixed(2);
    }

    function addPrescription(data) {
        data = data || {};

        var template =
            document.getElementById(
                'prescriptionTemplate'
            );

        var row =
            template.content
                .firstElementChild
                .cloneNode(true);

        setOptions(
            rxField(
                row,
                'medicine_ref'
            ),
            medicines,
            'ref',
            medicineLabel,
            data.medicine_ref || ''
        );

        rxField(
            row,
            'quantity'
        ).value =
            data.quantity || '1';

        rxField(
            row,
            'unit_price'
        ).value =
            data.unit_price || '0.00';

        rxField(
            row,
            'amount'
        ).value =
            data.amount || '0.00';

        rxField(
            row,
            'dosage'
        ).value =
            data.dosage || '';

        rxField(
            row,
            'frequency'
        ).value =
            data.frequency || '';

        rxField(
            row,
            'timing'
        ).value =
            data.timing || '';

        rxField(
            row,
            'duration'
        ).value =
            data.duration || '';

        rxField(
            row,
            'anupana'
        ).value =
            Object.prototype
                .hasOwnProperty.call(
                    data,
                    'anupana'
                )
                ? String(
                    data.anupana || ''
                )
                : 'Warm Water';

        rxField(
            row,
            'instructions'
        ).value =
            data.instructions || '';

        prescriptionContainer
            .appendChild(row);

        recalcPrescriptionRow(row);
        updatePrescriptionNumbers();
        recalcPrescriptionTotal();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function updatePrescriptionNumbers() {
        var rows =
            prescriptionRows();

        rows.forEach(
            function(row,index) {
                row.querySelector(
                    '[data-prescription-number]'
                ).textContent =
                    String(
                        index + 1
                    );
            }
        );

        document.getElementById(
            'emptyPrescription'
        ).hidden =
            rows.length > 0;
    }

    function collectPrescriptions() {
        return prescriptionRows()
            .map(
                function(row) {
                    recalcPrescriptionRow(row);

                    return {
                        medicine_ref:
                            rxField(
                                row,
                                'medicine_ref'
                            ).value,
                        quantity:
                            rxField(
                                row,
                                'quantity'
                            ).value || '1',
                        unit_price:
                            rxField(
                                row,
                                'unit_price'
                            ).value || '0.00',
                        amount:
                            rxField(
                                row,
                                'amount'
                            ).value || '0.00',
                        dosage:
                            rxField(
                                row,
                                'dosage'
                            ).value.trim(),
                        frequency:
                            rxField(
                                row,
                                'frequency'
                            ).value,
                        timing:
                            rxField(
                                row,
                                'timing'
                            ).value,
                        duration:
                            rxField(
                                row,
                                'duration'
                            ).value.trim(),
                        anupana:
                            rxField(
                                row,
                                'anupana'
                            ).value,
                        instructions:
                            rxField(
                                row,
                                'instructions'
                            ).value.trim()
                    };
                }
            )
            .filter(
                function(row) {
                    return Boolean(
                        row.medicine_ref
                    );
                }
            );
    }

    function recalcPrescriptionTotal() {
        var total = 0;

        prescriptionRows()
            .forEach(
                function(row) {
                    recalcPrescriptionRow(row);

                    total +=
                        moneyNumber(
                            rxField(
                                row,
                                'amount'
                            ).value
                        );
                }
            );

        document.getElementById(
            'medicineTotal'
        ).textContent =
            total.toFixed(2);

        var fee =
            moneyNumber(
                document.getElementById(
                    'consultationFee'
                ).value
            );

        document.getElementById(
            'visitChargeTotal'
        ).textContent =
            (
                total + fee
            ).toFixed(2);
    }

    async function loadDiagnosisPlan() {
        if (
            document.getElementById(
                'treatmentBasis'
            ).value !== 'protocol'
        ) {
            return;
        }

        var diagnosisRef =
            diagnosisSelect.value;

        if (!diagnosisRef) {
            return;
        }

        try {
            var response =
                await App.api(
                    'api/consultations.php?' +
                    new URLSearchParams({
                        treatment_plan:'1',
                        diagnosis_ref:
                            diagnosisRef
                    }).toString()
                );

            var data =
                dataOf(response);

            var plan =
                data.treatment_plan ||
                data.protocol ||
                null;

            if (plan) {
                document.getElementById(
                    'planName'
                ).value =
                    plan.diagnosis_name ||
                    plan.protocol_name ||
                    '';
            }

            dayContainer.innerHTML = '';

            (
                Array.isArray(
                    data.days
                )
                    ? data.days
                    : []
            ).forEach(
                function(day) {
                    addTreatmentDay(day);
                }
            );

            if (
                treatmentDayRows().length === 0
            ) {
                addTreatmentDay({});
            }

            updateTreatmentDayNumbers();
        } catch (error) {
            App.showError(
                error,
                'Unable to load Diagnosis Treatment Plan.'
            );
        }
    }

    function applyTreatmentBasis() {
        var diagnosisBased =
            document.getElementById(
                'treatmentBasis'
            ).value === 'protocol';

        document.getElementById(
            'diagnosisField'
        ).hidden =
            !diagnosisBased;

        if (!diagnosisBased) {
            diagnosisSelect.value = '';

            if (
                treatmentDayRows().length === 0
            ) {
                addTreatmentDay({});
            }
        }
    }

    function filterAppointmentsForPatient() {
        var patientRef =
            patientSelect.value;

        var rows =
            appointments.filter(
                function(row) {
                    return (
                        String(
                            row.patient_ref || ''
                        ) ===
                        String(
                            patientRef || ''
                        )
                    );
                }
            );

        setOptions(
            appointmentSelect,
            rows,
            'ref',
            function(row) {
                return (
                    (row.appointment_no || '') +
                    ' - ' +
                    (row.appointment_date || '') +
                    (
                        row.appointment_time
                            ? ' ' +
                              row.appointment_time
                            : ''
                    )
                );
            },
            appointmentSelect.value
        );
    }

    function applyAppointmentSelection() {
        var ref =
            appointmentSelect.value;

        var row =
            appointments.find(
                function(item) {
                    return (
                        String(
                            item.ref || ''
                        ) ===
                        String(
                            ref || ''
                        )
                    );
                }
            );

        if (!row) {
            return;
        }

        if (row.appointment_date) {
            document.getElementById(
                'visitDate'
            ).value =
                row.appointment_date;
        }

        if (row.appointment_time) {
            document.getElementById(
                'visitTime'
            ).value =
                row.appointment_time;
        }

        if (row.consultant_name) {
            document.getElementById(
                'consultantName'
            ).value =
                row.consultant_name;
        }
    }

    function clinicalPayload(
        statusOverride
    ) {
        var visitSource =
            document.getElementById(
                'visitSource'
            ).value;

        return {
            action:'save',
            ref:
                currentConsultationRef,
            visit_source:
                visitSource,
            appointment_ref:
                visitSource ===
                    'appointment'
                    ? appointmentSelect.value
                    : '',
            patient_ref:
                patientSelect.value,
            visit_date:
                document.getElementById(
                    'visitDate'
                ).value,
            visit_time:
                document.getElementById(
                    'visitTime'
                ).value,
            consultant_name:
                document.getElementById(
                    'consultantName'
                ).value.trim(),
            chief_complaint:
                document.getElementById(
                    'chiefComplaint'
                ).value.trim(),
            symptoms:
                document.getElementById(
                    'symptoms'
                ).value.trim(),
            clinical_notes:
                document.getElementById(
                    'clinicalNotes'
                ).value.trim(),
            treatment_basis:
                document.getElementById(
                    'treatmentBasis'
                ).value,
            diagnosis_ref:
                diagnosisSelect.value,
            plan_name:
                document.getElementById(
                    'planName'
                ).value.trim(),
            plan_notes:
                document.getElementById(
                    'planNotes'
                ).value.trim(),
            consultation_fee:
                document.getElementById(
                    'consultationFee'
                ).value,
            consultation_status:
                statusOverride ||
                document.getElementById(
                    'consultationStatus'
                ).value,
            status:'1',
            treatment_days_json:
                JSON.stringify(
                    collectTreatmentDays()
                ),
            prescription_items_json:
                JSON.stringify(
                    collectPrescriptions()
                )
        };
    }

    async function saveConsultation(
        statusOverride
    ) {
        if (!patientSelect.value) {
            showToast(
                'Select Patient first.',
                {
                    type:'danger',
                    duration:3
                }
            );

            return null;
        }

        var payload =
            clinicalPayload(
                statusOverride
            );

        var body =
            new FormData();

        Object.keys(payload)
            .forEach(
                function(key) {
                    if (
                        key === 'ref' &&
                        !payload[key]
                    ) {
                        return;
                    }

                    body.set(
                        key,
                        payload[key]
                    );
                }
            );

        try {
            var response =
                await App.api(
                    'api/consultations.php',
                    {
                        method:'POST',
                        body:body
                    }
                );

            var data =
                dataOf(response);

            currentConsultationRef =
                data.ref ||
                currentConsultationRef;

            currentConsultationStatus =
                payload.consultation_status;

            document.getElementById(
                'consultationStatus'
            ).value =
                currentConsultationStatus;

            showToast(
                response.message ||
                'Consultation saved successfully.',
                {
                    type:'success',
                    duration:2
                }
            );

            await reloadCurrentConsultation();

            if (
                currentConsultationStatus ===
                'Completed'
            ) {
                await loadBillingSources();
            } else {
                clearBillingCharges();

                document.getElementById(
                    'emptyCharges'
                ).textContent =
                    'Complete the Consultation to load billable charges.';
            }

            await loadPendingConsultations(
                patientSelect.value,
                currentConsultationRef
            );

            return currentConsultationRef;
        } catch (error) {
            App.showError(
                error,
                'Unable to save Consultation.'
            );

            return null;
        }
    }

    async function reloadCurrentConsultation() {
        if (!currentConsultationRef) {
            return;
        }

        try {
            var response =
                await App.api(
                    'api/consultations.php?' +
                    new URLSearchParams({
                        ref:
                            currentConsultationRef
                    }).toString()
                );

            var data =
                dataOf(response);

            if (data.record) {
                fillConsultation(
                    data.record,
                    data
                );
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to reload Consultation.'
            );
        }
    }

    function fillConsultation(
        record,
        payload
    ) {
        currentConsultationRef =
            record.ref || '';

        currentConsultationStatus =
            record.consultation_status ||
            '';

        if (
            payload &&
            Array.isArray(
                payload.appointments
            )
        ) {
            appointments =
                payload.appointments;
        }

        if (
            payload &&
            Array.isArray(
                payload.diagnoses
            )
        ) {
            diagnoses =
                payload.diagnoses;
        }

        if (
            payload &&
            Array.isArray(
                payload.treatment_procedures
            )
        ) {
            treatmentProcedures =
                payload.treatment_procedures;
        }

        if (
            payload &&
            Array.isArray(
                payload.medicines
            )
        ) {
            medicines =
                payload.medicines;
        }

        document.getElementById(
            'consultationNo'
        ).value =
            record.consultation_no || '';

        document.getElementById(
            'visitSource'
        ).value =
            record.visit_source ||
            'walkin';

        document.getElementById(
            'appointmentField'
        ).hidden =
            document.getElementById(
                'visitSource'
            ).value !== 'appointment';

        filterAppointmentsForPatient();

        if (
            record.appointment_ref
        ) {
            if (
                !Array.prototype.some.call(
                    appointmentSelect.options,
                    function(option) {
                        return (
                            option.value ===
                            String(
                                record.appointment_ref
                            )
                        );
                    }
                )
            ) {
                var opt =
                    document.createElement(
                        'option'
                    );

                opt.value =
                    record.appointment_ref;

                opt.textContent =
                    record.appointment_no ||
                    'Current Appointment';

                appointmentSelect
                    .appendChild(opt);
            }

            appointmentSelect.value =
                record.appointment_ref;
        } else {
            appointmentSelect.value = '';
        }

        document.getElementById(
            'visitDate'
        ).value =
            record.visit_date || today();

        document.getElementById(
            'visitTime'
        ).value =
            record.visit_time ||
            currentTime();

        document.getElementById(
            'consultantName'
        ).value =
            record.consultant_name || '';

        document.getElementById(
            'consultationFee'
        ).value =
            record.consultation_fee ||
            '0.00';

        document.getElementById(
            'chiefComplaint'
        ).value =
            record.chief_complaint || '';

        document.getElementById(
            'symptoms'
        ).value =
            record.symptoms || '';

        document.getElementById(
            'clinicalNotes'
        ).value =
            record.clinical_notes || '';

        document.getElementById(
            'treatmentBasis'
        ).value =
            record.treatment_basis ||
            'protocol';

        setOptions(
            diagnosisSelect,
            diagnoses,
            'ref',
            function(row) {
                return (
                    row.label ||
                    (
                        (row.diagnosis_code || '') +
                        ' - ' +
                        (row.diagnosis_name || '')
                    )
                );
            },
            record.diagnosis_ref || ''
        );

        document.getElementById(
            'planName'
        ).value =
            record.plan_name || '';

        document.getElementById(
            'planNotes'
        ).value =
            record.plan_notes || '';

        document.getElementById(
            'consultationStatus'
        ).value =
            record.consultation_status ||
            'In Progress';

        dayContainer.innerHTML = '';

        (
            Array.isArray(
                record.days
            )
                ? record.days
                : []
        ).forEach(
            function(day) {
                addTreatmentDay(day);
            }
        );

        if (
            treatmentDayRows().length === 0
        ) {
            addTreatmentDay({});
        }

        prescriptionContainer.innerHTML = '';

        (
            Array.isArray(
                record.prescriptions
            )
                ? record.prescriptions
                : []
        ).forEach(
            function(item) {
                addPrescription(item);
            }
        );

        applyTreatmentBasis();
        updateTreatmentDayNumbers();
        updatePrescriptionNumbers();
        recalcPrescriptionTotal();

        document.getElementById(
            'billingConsultation'
        ).value =
            (
                record.consultation_no ||
                ''
            ) +
            (
                record.visit_date
                    ? ' - ' +
                      record.visit_date
                    : ''
            );
    }

    async function loadConsultation(
        ref
    ) {
        if (!ref) {
            return;
        }

        try {
            var response =
                await App.api(
                    'api/consultations.php?' +
                    new URLSearchParams({
                        ref:ref
                    }).toString()
                );

            var data =
                dataOf(response);

            if (!data.record) {
                throw new Error(
                    'Consultation record is missing.'
                );
            }

            fillConsultation(
                data.record,
                data
            );

            if (
                currentConsultationStatus ===
                'Completed'
            ) {
                await loadBillingSources();
            } else {
                clearBillingCharges();

                document.getElementById(
                    'emptyCharges'
                ).textContent =
                    'Complete the Consultation to load billable charges.';
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to load Consultation.'
            );
        }
    }

    function startNewConsultation() {
        if (!patientSelect.value) {
            showToast(
                'Select Patient first.',
                {
                    type:'danger',
                    duration:3
                }
            );

            return;
        }

        consultationSelect.value = '';
        clearClinicalForm();
        filterAppointmentsForPatient();

        document.getElementById(
            'consultationHint'
        ).textContent =
            'New Consultation started for selected Patient.';
    }

    async function loadPendingConsultations(
        patientRef,
        selectedRef
    ) {
        if (!patientRef) {
            pendingConsultations = [];

            setOptions(
                consultationSelect,
                [],
                'ref',
                consultationLabel,
                ''
            );

            document.getElementById(
                'consultationHint'
            ).textContent =
                'Select Patient first.';

            clearClinicalForm();

            return;
        }

        try {
            var response =
                await App.api(
                    'api/bills.php?' +
                    new URLSearchParams({
                        consultations:'1',
                        patient_ref:
                            patientRef
                    }).toString()
                );

            var data =
                dataOf(response);

            pendingConsultations =
                Array.isArray(
                    data.consultations
                )
                    ? data.consultations
                    : [];

            if (data.patient) {
                fillPatientSummary(
                    data.patient
                );
            }

            /*
             * A just-saved Consultation disappears from the
             * "unbilled" query only after a Bill exists.
             * When selectedRef is supplied, keep it selectable.
             */
            if (
                selectedRef &&
                !pendingConsultations.some(
                    function(row) {
                        return (
                            String(
                                row.ref || ''
                            ) ===
                            String(
                                selectedRef
                            )
                        );
                    }
                )
            ) {
                pendingConsultations.unshift({
                    ref:selectedRef,
                    consultation_no:
                        document.getElementById(
                            'consultationNo'
                        ).value,
                    visit_date:
                        document.getElementById(
                            'visitDate'
                        ).value,
                    label:
                        (
                            document.getElementById(
                                'consultationNo'
                            ).value ||
                            'Current Consultation'
                        ) +
                        ' - ' +
                        document.getElementById(
                            'visitDate'
                        ).value
                });
            }

            setOptions(
                consultationSelect,
                pendingConsultations,
                'ref',
                consultationLabel,
                selectedRef || ''
            );

            if (selectedRef) {
                consultationSelect.value =
                    selectedRef;

                document.getElementById(
                    'consultationHint'
                ).textContent =
                    'Current Consultation selected.';

                return;
            }

            if (
                pendingConsultations.length === 0
            ) {
                document.getElementById(
                    'consultationHint'
                ).textContent =
                    'No pending Consultation. A new Consultation is ready below.';

                startNewConsultation();

                return;
            }

            if (
                pendingConsultations.length === 1
            ) {
                consultationSelect.value =
                    pendingConsultations[0].ref;

                document.getElementById(
                    'consultationHint'
                ).textContent =
                    'One pending Consultation found and auto-selected.';

                await loadConsultation(
                    pendingConsultations[0].ref
                );

                return;
            }

            document.getElementById(
                'consultationHint'
            ).textContent =
                pendingConsultations.length +
                ' pending Consultations found. Select one.';

            currentConsultationRef = '';
            currentConsultationStatus = '';
            clearBillingCharges();

        } catch (error) {
            App.showError(
                error,
                'Unable to load pending Consultations.'
            );
        }
    }

    function typeLabel(type) {
        var map = {
            CONSULTATION:'Consultation',
            MEDICINE:'Medicine',
            TREATMENT:'Treatment',
            LAB:'Lab'
        };

        return (
            map[type] ||
            type ||
            ''
        );
    }

    function renderSourceItems(items) {
        sourceItems =
            Array.isArray(items)
                ? items
                : [];

        chargeRows.innerHTML = '';

        sourceItems.forEach(
            function(item,index) {
                var row =
                    document.createElement(
                        'tr'
                    );

                row.dataset.index =
                    String(index);

                row.innerHTML =
                    '<td>' +
                        '<input ' +
                            'type="checkbox" ' +
                            'class="include-charge" ' +
                            'data-index="' +
                            index +
                            '" checked>' +
                    '</td>' +
                    '<td>' +
                        esc(
                            typeLabel(
                                item.item_type
                            )
                        ) +
                    '</td>' +
                    '<td>' +
                        esc(
                            item.source_date ||
                            '-'
                        ) +
                    '</td>' +
                    '<td>' +
                        esc(
                            item.description ||
                            ''
                        ) +
                    '</td>' +
                    '<td>' +
                        esc(
                            item.quantity ||
                            '1.000'
                        ) +
                    '</td>' +
                    '<td>' +
                        esc(
                            item.unit_price ||
                            '0.00'
                        ) +
                    '</td>' +
                    '<td>' +
                        esc(
                            item.amount ||
                            '0.00'
                        ) +
                    '</td>';

                chargeRows.appendChild(row);
            }
        );

        document.getElementById(
            'emptyCharges'
        ).hidden =
            sourceItems.length > 0;

        recalcBillSummary();
    }

    async function loadBillingSources() {
        if (!currentConsultationRef) {
            clearBillingCharges();
            return;
        }

        if (
            currentConsultationStatus !==
            'Completed'
        ) {
            clearBillingCharges();

            document.getElementById(
                'emptyCharges'
            ).textContent =
                'Complete the Consultation to load billable charges.';

            return;
        }

        try {
            var response =
                await App.api(
                    'api/bills.php?' +
                    new URLSearchParams({
                        source:'1',
                        consultation_ref:
                            currentConsultationRef
                    }).toString()
                );

            var data =
                dataOf(response);

            if (data.patient) {
                fillPatientSummary(
                    data.patient
                );
            }

            if (data.consultation) {
                document.getElementById(
                    'billingConsultation'
                ).value =
                    (
                        data.consultation
                            .consultation_no ||
                        ''
                    ) +
                    ' - ' +
                    (
                        data.consultation
                            .visit_date ||
                        ''
                    );
            }

            if (
                Array.isArray(
                    data.lab_tests
                )
            ) {
                labTests =
                    data.lab_tests;
            }

            if (
                data.payment_accounts
            ) {
                paymentAccounts =
                    data.payment_accounts;
                populatePaymentAccounts();
            }

            renderSourceItems(
                data.items || []
            );
        } catch (error) {
            App.showError(
                error,
                'Unable to load Consultation Billing charges.'
            );
        }
    }

    function labRows() {
        return Array.prototype.slice.call(
            labContainer.querySelectorAll(
                '[data-lab-row]'
            )
        );
    }

    function labField(row,name) {
        return row.querySelector(
            '[data-lab-field="' +
            name +
            '"]'
        );
    }

    function labByRef(ref) {
        return (
            labTests.find(
                function(row) {
                    return (
                        String(
                            row.ref || ''
                        ) ===
                        String(
                            ref || ''
                        )
                    );
                }
            ) ||
            null
        );
    }

    function recalcLabRow(row) {
        var item =
            labByRef(
                labField(
                    row,
                    'source_ref'
                ).value
            );

        var qty =
            moneyNumber(
                labField(
                    row,
                    'quantity'
                ).value
            );

        if (qty <= 0) {
            qty = 1;
        }

        var price =
            item
                ? moneyNumber(
                    item.price
                )
                : moneyNumber(
                    labField(
                        row,
                        'unit_price'
                    ).value
                );

        labField(
            row,
            'unit_price'
        ).value =
            price.toFixed(2);

        labField(
            row,
            'amount'
        ).value =
            (
                qty * price
            ).toFixed(2);
    }

    function addLabRow(data) {
        data = data || {};

        var template =
            document.getElementById(
                'labRowTemplate'
            );

        var row =
            template.content
                .firstElementChild
                .cloneNode(true);

        setOptions(
            labField(
                row,
                'source_ref'
            ),
            labTests,
            'ref',
            labLabel,
            data.source_ref || ''
        );

        labField(
            row,
            'quantity'
        ).value =
            data.quantity || '1';

        labField(
            row,
            'unit_price'
        ).value =
            data.unit_price || '0.00';

        labField(
            row,
            'amount'
        ).value =
            data.amount || '0.00';

        labContainer.appendChild(row);

        recalcLabRow(row);

        document.getElementById(
            'emptyLabs'
        ).hidden =
            labRows().length > 0;

        recalcBillSummary();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function collectItems() {
        var items = [];

        chargeRows.querySelectorAll(
            '.include-charge:checked'
        ).forEach(
            function(input) {
                var index =
                    Number(
                        input.dataset.index
                    );

                var item =
                    sourceItems[index];

                if (!item) {
                    return;
                }

                items.push({
                    item_type:
                        item.item_type,
                    source_ref:
                        item.source_ref,
                    quantity:
                        item.quantity ||
                        '1'
                });
            }
        );

        labRows().forEach(
            function(row) {
                recalcLabRow(row);

                var ref =
                    labField(
                        row,
                        'source_ref'
                    ).value;

                if (!ref) {
                    return;
                }

                items.push({
                    item_type:'LAB',
                    source_ref:ref,
                    quantity:
                        labField(
                            row,
                            'quantity'
                        ).value || '1'
                });
            }
        );

        return items;
    }

    function paymentRows() {
        return Array.prototype.slice.call(
            document.querySelectorAll(
                '[data-payment-row]'
            )
        );
    }

    function paymentField(row,name) {
        return row.querySelector(
            '[data-payment-field="' +
            name +
            '"]'
        );
    }

    function populateSelect(
        select,
        rows,
        selected
    ) {
        var current =
            String(
                selected || ''
            );

        var firstText =
            select.id ===
                'cashAccountId'
                ? 'Select Cash Account'
                : 'Select Bank Account';

        select.innerHTML =
            '<option value="">' +
            firstText +
            '</option>';

        (rows || []).forEach(
            function(item) {
                var option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    String(
                        item.id || ''
                    );

                option.textContent =
                    item.label ||
                    item.account_name ||
                    '';

                select.appendChild(option);
            }
        );

        select.value = current;
    }

    function populatePaymentAccounts() {
        populateSelect(
            document.getElementById(
                'cashAccountId'
            ),
            paymentAccounts.cash || [],
            document.getElementById(
                'cashAccountId'
            ).value
        );

        [
            'upiAccountId',
            'bankAccountId',
            'chequeAccountId'
        ].forEach(
            function(id) {
                var select =
                    document.getElementById(id);

                populateSelect(
                    select,
                    paymentAccounts.bank || [],
                    select.value
                );
            }
        );
    }

    function collectPayments() {
        return paymentRows()
            .map(
                function(row) {
                    return {
                        mode:
                            row.dataset.mode,
                        account_id:
                            paymentField(
                                row,
                                'account_id'
                            ).value,
                        amount:
                            paymentField(
                                row,
                                'amount'
                            ).value || '0.00',
                        reference_no:
                            paymentField(
                                row,
                                'reference_no'
                            )
                                ? paymentField(
                                    row,
                                    'reference_no'
                                  ).value.trim()
                                : '',
                        payment_date:
                            paymentField(
                                row,
                                'payment_date'
                            )
                                ? paymentField(
                                    row,
                                    'payment_date'
                                  ).value
                                : ''
                    };
                }
            )
            .filter(
                function(item) {
                    return (
                        moneyNumber(
                            item.amount
                        ) > 0
                    );
                }
            );
    }

    function recalcBillSummary() {
        var subtotal = 0;

        chargeRows.querySelectorAll(
            '.include-charge:checked'
        ).forEach(
            function(input) {
                var index =
                    Number(
                        input.dataset.index
                    );

                if (
                    sourceItems[index]
                ) {
                    subtotal +=
                        moneyNumber(
                            sourceItems[index]
                                .amount
                        );
                }
            }
        );

        labRows().forEach(
            function(row) {
                recalcLabRow(row);

                subtotal +=
                    moneyNumber(
                        labField(
                            row,
                            'amount'
                        ).value
                    );
            }
        );

        var discount =
            moneyNumber(
                document.getElementById(
                    'discountAmount'
                ).value
            );

        var grand =
            Math.max(
                0,
                subtotal - discount
            );

        var paid = 0;

        paymentRows()
            .forEach(
                function(row) {
                    paid +=
                        moneyNumber(
                            paymentField(
                                row,
                                'amount'
                            ).value
                        );
                }
            );

        var balance =
            Math.max(
                0,
                grand - paid
            );

        var status =
            balance <= 0.0001
                ? 'Paid'
                : (
                    paid > 0
                        ? 'Partially Paid'
                        : 'Unpaid'
                );

        document.getElementById(
            'subtotal'
        ).value =
            subtotal.toFixed(2);

        document.getElementById(
            'grandTotal'
        ).value =
            grand.toFixed(2);

        document.getElementById(
            'paidAmount'
        ).value =
            paid.toFixed(2);

        document.getElementById(
            'balanceAmount'
        ).value =
            balance.toFixed(2);

        document.getElementById(
            'paymentStatus'
        ).value =
            status;

        document.getElementById(
            'sumSubtotal'
        ).textContent =
            money(subtotal);

        document.getElementById(
            'sumGrand'
        ).textContent =
            money(grand);

        document.getElementById(
            'sumPaid'
        ).textContent =
            money(paid);

        document.getElementById(
            'sumBalance'
        ).textContent =
            money(balance);

        document.getElementById(
            'summaryBalance'
        ).textContent =
            money(balance);

        document.getElementById(
            'paidNow'
        ).textContent =
            money(paid);

        document.getElementById(
            'sumPaymentStatus'
        ).textContent =
            status;
    }

    function applyPayments(payments) {
        var map = {};

        (
            Array.isArray(payments)
                ? payments
                : []
        ).forEach(
            function(item) {
                map[
                    String(
                        item.mode || ''
                    ).toUpperCase()
                ] = item;
            }
        );

        paymentRows()
            .forEach(
                function(row) {
                    var mode =
                        String(
                            row.dataset.mode || ''
                        ).toUpperCase();

                    var item =
                        map[mode] || {};

                    paymentField(
                        row,
                        'account_id'
                    ).value =
                        item.account_id || '';

                    paymentField(
                        row,
                        'amount'
                    ).value =
                        item.amount || '0.00';

                    if (
                        paymentField(
                            row,
                            'reference_no'
                        )
                    ) {
                        paymentField(
                            row,
                            'reference_no'
                        ).value =
                            item.reference_no || '';
                    }

                    if (
                        paymentField(
                            row,
                            'payment_date'
                        )
                    ) {
                        paymentField(
                            row,
                            'payment_date'
                        ).value =
                            item.payment_date || '';
                    }
                }
            );

        recalcBillSummary();
    }

    function setClinicalDisabled(disabled) {
        [
            'visitSource',
            'appointmentRef',
            'visitDate',
            'visitTime',
            'consultantName',
            'consultationFee',
            'chiefComplaint',
            'symptoms',
            'clinicalNotes',
            'treatmentBasis',
            'diagnosisRef',
            'planName',
            'planNotes',
            'consultationStatus'
        ].forEach(
            function(id) {
                var element =
                    document.getElementById(
                        id
                    );

                if (element) {
                    element.disabled =
                        disabled;
                }
            }
        );

        document.getElementById(
            'addTreatmentDayButton'
        ).disabled =
            disabled;

        document.getElementById(
            'addMedicineButton'
        ).disabled =
            disabled;

        document.getElementById(
            'saveConsultationButton'
        ).disabled =
            disabled;

        document.getElementById(
            'completeConsultationButton'
        ).disabled =
            disabled;
    }

    async function loadOptions() {
        var results =
            await Promise.all([
                App.api(
                    'api/bills.php?options=1'
                ),
                App.api(
                    'api/consultations.php?options=1'
                )
            ]);

        billingOptions =
            dataOf(
                results[0]
            );

        consultationOptions =
            dataOf(
                results[1]
            );

        patients =
            Array.isArray(
                billingOptions.patients
            )
                ? billingOptions.patients
                : [];

        appointments =
            Array.isArray(
                consultationOptions.appointments
            )
                ? consultationOptions.appointments
                : [];

        diagnoses =
            Array.isArray(
                consultationOptions.diagnoses
            )
                ? consultationOptions.diagnoses
                : [];

        treatmentProcedures =
            Array.isArray(
                consultationOptions
                    .treatment_procedures
            )
                ? consultationOptions
                    .treatment_procedures
                : [];

        medicines =
            Array.isArray(
                consultationOptions.medicines
            )
                ? consultationOptions.medicines
                : [];

        labTests =
            Array.isArray(
                billingOptions.lab_tests
            )
                ? billingOptions.lab_tests
                : [];

        paymentAccounts =
            billingOptions
                .payment_accounts ||
            {
                cash:[],
                bank:[]
            };

        setOptions(
            patientSelect,
            patients,
            'ref',
            patientLabel,
            ''
        );

        setOptions(
            diagnosisSelect,
            diagnoses,
            'ref',
            function(row) {
                return (
                    row.label ||
                    (
                        (row.diagnosis_code || '') +
                        ' - ' +
                        (row.diagnosis_name || '')
                    )
                );
            },
            ''
        );

        populatePaymentAccounts();

        document.getElementById(
            'billNo'
        ).value =
            billingOptions
                .next_bill_no ||
            '';

        document.getElementById(
            'consultationNo'
        ).value =
            consultationOptions
                .next_consultation_no ||
            '';

        document.getElementById(
            'billDate'
        ).value =
            today();

        document.getElementById(
            'visitDate'
        ).value =
            today();

        document.getElementById(
            'visitTime'
        ).value =
            currentTime();
    }

    async function loadExistingBill() {
        var response =
            await App.api(
                'api/bills.php?' +
                new URLSearchParams({
                    ref:billRef
                }).toString()
            );

        var data =
            dataOf(response);

        var record =
            data.record;

        if (!record) {
            throw new Error(
                'Bill record is missing.'
            );
        }

        currentBillRecord = record;

        if (
            Array.isArray(
                data.patients
            )
        ) {
            patients =
                data.patients;
        }

        if (
            Array.isArray(
                data.lab_tests
            )
        ) {
            labTests =
                data.lab_tests;
        }

        if (
            data.payment_accounts
        ) {
            paymentAccounts =
                data.payment_accounts;
        }

        setOptions(
            patientSelect,
            patients,
            'ref',
            patientLabel,
            record.patient_ref
        );

        fillPatientSummary(record);

        document.getElementById(
            'billNo'
        ).value =
            record.bill_no || '';

        document.getElementById(
            'billDate'
        ).value =
            record.bill_date ||
            today();

        document.getElementById(
            'discountAmount'
        ).value =
            record.discount_amount ||
            '0.00';

        document.getElementById(
            'billNotes'
        ).value =
            record.notes || '';

        populatePaymentAccounts();

        var billItems =
            Array.isArray(
                record.items
            )
                ? record.items
                : [];

        sourceItems =
            billItems.filter(
                function(item) {
                    return (
                        item.item_type !==
                        'LAB'
                    );
                }
            );

        renderSourceItems(
            sourceItems
        );

        labContainer.innerHTML = '';

        billItems
            .filter(
                function(item) {
                    return (
                        item.item_type ===
                        'LAB'
                    );
                }
            )
            .forEach(
                function(item) {
                    addLabRow(item);
                }
            );

        applyPayments(
            record.payments || []
        );

        currentConsultationRef =
            record.consultation_ref || '';

        if (
            currentConsultationRef
        ) {
            await loadPendingConsultations(
                record.patient_ref,
                currentConsultationRef
            );

            await loadConsultation(
                currentConsultationRef
            );
        }

        document.getElementById(
            'printButton'
        ).href =
            'bill-print.php?ref=' +
            encodeURIComponent(
                record.ref
            );

        document.getElementById(
            'printButton'
        ).hidden = false;

        document.getElementById(
            'pageHeading'
        ).textContent =
            viewMode
                ? 'View Patient Bill'
                : 'Edit Patient Bill';

        document.getElementById(
            'saveBillText'
        ).textContent =
            'Update Bill';

        if (viewMode) {
            form.querySelectorAll(
                'input,select,textarea,button'
            ).forEach(
                function(element) {
                    element.disabled =
                        true;
                }
            );

            document.getElementById(
                'printButton'
            ).disabled = false;
        }
    }

    async function initialize() {
        try {
            await loadOptions();

            clearClinicalForm();

            if (billRef) {
                await loadExistingBill();
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to initialize Clinic Billing.'
            );
        }
    }

    patientSelect.addEventListener(
        'change',
        function() {
            var patient =
                selectedPatientOption();

            fillPatientSummary(
                patient
            );

            filterAppointmentsForPatient();

            if (billRef) {
                return;
            }

            loadPendingConsultations(
                patientSelect.value,
                ''
            );
        }
    );

    consultationSelect.addEventListener(
        'change',
        function() {
            var ref =
                consultationSelect.value;

            if (!ref) {
                currentConsultationRef = '';
                currentConsultationStatus = '';
                clearBillingCharges();
                return;
            }

            loadConsultation(ref);
        }
    );

    document.getElementById(
        'newConsultationButton'
    ).addEventListener(
        'click',
        startNewConsultation
    );

    document.getElementById(
        'visitSource'
    ).addEventListener(
        'change',
        function() {
            var appointment =
                this.value ===
                'appointment';

            document.getElementById(
                'appointmentField'
            ).hidden =
                !appointment;

            if (!appointment) {
                appointmentSelect.value = '';
            }
        }
    );

    appointmentSelect.addEventListener(
        'change',
        applyAppointmentSelection
    );

    document.getElementById(
        'treatmentBasis'
    ).addEventListener(
        'change',
        applyTreatmentBasis
    );

    diagnosisSelect.addEventListener(
        'change',
        loadDiagnosisPlan
    );

    document.getElementById(
        'consultationFee'
    ).addEventListener(
        'input',
        recalcPrescriptionTotal
    );

    document.getElementById(
        'addTreatmentDayButton'
    ).addEventListener(
        'click',
        function() {
            addTreatmentDay({});
        }
    );

    dayContainer.addEventListener(
        'click',
        function(event) {
            var remove =
                event.target.closest(
                    '.remove-treatment-day'
                );

            if (!remove) {
                return;
            }

            var row =
                remove.closest(
                    '[data-treatment-day]'
                );

            if (row) {
                row.remove();
                updateTreatmentDayNumbers();
            }
        }
    );

    document.getElementById(
        'addMedicineButton'
    ).addEventListener(
        'click',
        function() {
            addPrescription({});
        }
    );

    prescriptionContainer
        .addEventListener(
            'click',
            function(event) {
                var remove =
                    event.target.closest(
                        '.remove-prescription-row'
                    );

                if (!remove) {
                    return;
                }

                var row =
                    remove.closest(
                        '[data-prescription-row]'
                    );

                if (row) {
                    row.remove();
                    updatePrescriptionNumbers();
                    recalcPrescriptionTotal();
                }
            }
        );

    prescriptionContainer
        .addEventListener(
            'input',
            function(event) {
                var row =
                    event.target.closest(
                        '[data-prescription-row]'
                    );

                if (row) {
                    recalcPrescriptionRow(row);
                    recalcPrescriptionTotal();
                }
            }
        );

    prescriptionContainer
        .addEventListener(
            'change',
            function(event) {
                var row =
                    event.target.closest(
                        '[data-prescription-row]'
                    );

                if (row) {
                    recalcPrescriptionRow(row);
                    recalcPrescriptionTotal();
                }
            }
        );

    document.getElementById(
        'saveConsultationButton'
    ).addEventListener(
        'click',
        function() {
            saveConsultation(
                document.getElementById(
                    'consultationStatus'
                ).value
            );
        }
    );

    document.getElementById(
        'completeConsultationButton'
    ).addEventListener(
        'click',
        function() {
            saveConsultation(
                'Completed'
            );
        }
    );

    document.getElementById(
        'selectAllCharges'
    ).addEventListener(
        'click',
        function() {
            chargeRows.querySelectorAll(
                '.include-charge'
            ).forEach(
                function(input) {
                    input.checked = true;
                }
            );

            recalcBillSummary();
        }
    );

    document.getElementById(
        'clearAllCharges'
    ).addEventListener(
        'click',
        function() {
            chargeRows.querySelectorAll(
                '.include-charge'
            ).forEach(
                function(input) {
                    input.checked = false;
                }
            );

            recalcBillSummary();
        }
    );

    chargeRows.addEventListener(
        'change',
        function(event) {
            if (
                event.target.matches(
                    '.include-charge'
                )
            ) {
                recalcBillSummary();
            }
        }
    );

    document.getElementById(
        'addLabButton'
    ).addEventListener(
        'click',
        function() {
            addLabRow({});
        }
    );

    labContainer.addEventListener(
        'click',
        function(event) {
            var remove =
                event.target.closest(
                    '.remove-lab-row'
                );

            if (!remove) {
                return;
            }

            var row =
                remove.closest(
                    '[data-lab-row]'
                );

            if (row) {
                row.remove();

                document.getElementById(
                    'emptyLabs'
                ).hidden =
                    labRows().length > 0;

                recalcBillSummary();
            }
        }
    );

    labContainer.addEventListener(
        'input',
        function(event) {
            var row =
                event.target.closest(
                    '[data-lab-row]'
                );

            if (row) {
                recalcLabRow(row);
                recalcBillSummary();
            }
        }
    );

    labContainer.addEventListener(
        'change',
        function(event) {
            var row =
                event.target.closest(
                    '[data-lab-row]'
                );

            if (row) {
                recalcLabRow(row);
                recalcBillSummary();
            }
        }
    );

    document.getElementById(
        'discountAmount'
    ).addEventListener(
        'input',
        recalcBillSummary
    );

    document.getElementById(
        'paymentAllocationTable'
    ).addEventListener(
        'input',
        recalcBillSummary
    );

    document.getElementById(
        'paymentAllocationTable'
    ).addEventListener(
        'change',
        recalcBillSummary
    );

    form.addEventListener(
        'submit',
        async function(event) {
            event.preventDefault();

            if (viewMode) {
                return;
            }

            if (!patientSelect.value) {
                showToast(
                    'Patient is required.',
                    {
                        type:'danger',
                        duration:3
                    }
                );

                return;
            }

            if (!currentConsultationRef) {
                showToast(
                    'Save the Consultation before creating the Bill.',
                    {
                        type:'danger',
                        duration:3
                    }
                );

                return;
            }

            if (
                currentConsultationStatus !==
                'Completed'
            ) {
                showToast(
                    'Complete the Consultation before creating the Bill.',
                    {
                        type:'danger',
                        duration:3
                    }
                );

                return;
            }

            var items =
                collectItems();

            if (!items.length) {
                showToast(
                    'Add at least one Billing item.',
                    {
                        type:'danger',
                        duration:3
                    }
                );

                return;
            }

            var payments =
                collectPayments();

            recalcBillSummary();

            var body =
                new FormData();

            body.set(
                'action',
                'save'
            );

            if (billRef) {
                body.set(
                    'ref',
                    billRef
                );
            }

            body.set(
                'patient_ref',
                patientSelect.value
            );

            body.set(
                'consultation_ref',
                currentConsultationRef
            );

            body.set(
                'bill_date',
                document.getElementById(
                    'billDate'
                ).value
            );

            body.set(
                'discount_amount',
                document.getElementById(
                    'discountAmount'
                ).value || '0.00'
            );

            body.set(
                'notes',
                document.getElementById(
                    'billNotes'
                ).value.trim()
            );

            body.set(
                'bill_items_json',
                JSON.stringify(items)
            );

            body.set(
                'payment_breakdown_json',
                JSON.stringify(payments)
            );

            body.set(
                'paid_amount',
                document.getElementById(
                    'paidAmount'
                ).value || '0.00'
            );

            var button =
                document.getElementById(
                    'saveBillButton'
                );

            button.disabled = true;

            try {
                var response =
                    await App.api(
                        'api/bills.php',
                        {
                            method:'POST',
                            body:body
                        }
                    );

                showToast(
                    response.message ||
                    'Bill saved successfully.',
                    {
                        type:'success',
                        duration:2
                    }
                );

                var data =
                    dataOf(response);

                var savedRef =
                    data.ref ||
                    billRef;

                window.setTimeout(
                    function() {
                        window.location.href =
                            'bill-form.php?ref=' +
                            encodeURIComponent(
                                savedRef
                            );
                    },
                    600
                );
            } catch (error) {
                App.showError(
                    error,
                    'Unable to save Bill.'
                );

                button.disabled = false;
            }
        }
    );

    initialize();

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
