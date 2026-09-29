<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Student Admission';
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
<script src="assets/js/app.js?v=20260918-receipt-pdf-2"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1 id="pageHeading">Add Student Admission</h1>
        <p id="pageDescription">
            Create Student, Admission, Fee Plan, Admission Payment and EMI Schedule in one flow.
        </p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="admission-list.php">
            <i data-lucide="list"></i>
            Admission List
        </a>

        <a class="btn btn-primary" id="editButton" href="#" hidden>
            <i data-lucide="pencil"></i>
            Edit Admission
        </a>
    </div>
</div>

<div class="card form-card" id="admissionCard">
<form id="admissionForm" novalidate>
    <input id="admissionRef" type="hidden">

    <div class="card-header">
        <div>
            <h2>Admission Information</h2>
            <p id="formModeText">
                New Student is selected by default. Use Existing Student only when the Student already exists.
            </p>
        </div>
    </div>

    <div class="card-body">
        <div class="card-section-title">Student Details</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="studentMode" class="required">Student Entry</label>
                <select id="studentMode" required>
                    <option value="new">New Student</option>
                    <option value="existing">Existing Student</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="admissionNo">Admission No</label>
                <input
                    id="admissionNo"
                    type="text"
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                    placeholder="Auto generated">
            </div>

            <div class="field col-4">
                <label for="admissionDate" class="required">Admission Date</label>
                <input
                    id="admissionDate"
                    name="admission_date"
                    type="date"
                    required
                    data-required-message="Admission Date is required.">
            </div>
        </div>

        <div id="newStudentSection">
            <div class="form-row">
                <div class="field col-4">
                    <label for="studentName" class="required">Student Name</label>
                    <input
                        id="studentName"
                        name="student_name"
                        type="text"
                        maxlength="150"
                        autocomplete="off"
                        placeholder="Enter Student Name"
                        required
                        data-required-message="Student Name is required.">
                </div>

                <div class="field col-4">
                    <label for="studentMobile">Mobile Number</label>
                    <input
                        id="studentMobile"
                        name="student_mobile"
                        type="text"
                        inputmode="numeric"
                        maxlength="10"
                        autocomplete="off"
                        placeholder="10 digit Mobile Number"
                        data-validation="mobile">
                </div>

                <div class="field col-4">
                    <label for="studentGender">Gender</label>
                    <select id="studentGender" name="student_gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="studentDob">Date of Birth</label>
                    <input id="studentDob" name="student_dob" type="date">
                </div>

                <div class="field col-4">
                    <label for="studentEmail">Email</label>
                    <input
                        id="studentEmail"
                        name="student_email"
                        type="email"
                        maxlength="190"
                        autocomplete="off"
                        placeholder="Email Address"
                        data-validation="email">
                </div>

                <div class="field col-4">
                    <label for="studentQualification">Qualification</label>
                    <input
                        id="studentQualification"
                        name="qualification"
                        type="text"
                        maxlength="150"
                        autocomplete="off"
                        placeholder="Qualification">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-4">
                    <label for="guardianName">Guardian Name</label>
                    <input
                        id="guardianName"
                        name="guardian_name"
                        type="text"
                        maxlength="150"
                        autocomplete="off"
                        placeholder="Guardian Name">
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
                        placeholder="10 digit Mobile Number"
                        data-validation="mobile">
                </div>

                <div class="field col-4">
                    <label for="identityNumber">Identity Number</label>
                    <input
                        id="identityNumber"
                        name="identity_number"
                        type="text"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Aadhaar / Other ID">
                </div>
            </div>

            <div class="form-row">
                <div class="field col-12">
                    <label for="studentAddress">Address</label>
                    <textarea
                        id="studentAddress"
                        name="student_address"
                        rows="3"
                        placeholder="Student Address"></textarea>
                </div>
            </div>
        </div>

        <div id="existingStudentSection" hidden>
            <div class="form-row">
                <div class="field col-12">
                    <label for="studentId" class="required">Existing Student</label>
                    <select
                        id="studentId"
                        name="student_id"
                        data-placeholder="Search Existing Student">
                        <option value="">Select Existing Student</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-section-title">Course & Batch</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="courseId" class="required">Course</label>
                <select
                    id="courseId"
                    name="course_id"
                    required
                    data-placeholder="Select Course"
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
                    data-placeholder="Select Batch"
                    data-required-message="Batch is required.">
                    <option value="">Select Batch</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="completionDate">Completion Date</label>
                <input
                    id="completionDate"
                    type="date"
                    readonly
                    tabindex="-1"
                    aria-readonly="true">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label for="admissionState" class="required">Admission State</label>
                <select id="admissionState" name="admission_state" required>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="discontinued">Discontinued</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="status" class="required">Status</label>
                <select id="status" name="status" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="remarks">Remarks</label>
                <input
                    id="remarks"
                    name="remarks"
                    type="text"
                    maxlength="255"
                    autocomplete="off"
                    placeholder="Optional remarks">
            </div>
        </div>

        <div class="card-section-title">Fee Structure</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="feeStructureId" class="required">Fee Structure</label>
                <select
                    id="feeStructureId"
                    name="fee_structure_id"
                    required
                    data-placeholder="Select Fee Structure"
                    data-required-message="Fee Structure is required.">
                    <option value="">Select Fee Structure</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="selectedFeeTotal">Selected Fee Total</label>
                <input
                    id="selectedFeeTotal"
                    type="text"
                    value="0.00"
                    readonly
                    tabindex="-1"
                    aria-readonly="true">
            </div>

            <div class="field col-4">
                <label for="defaultInstallments">Default Installments</label>
                <input
                    id="defaultInstallments"
                    type="text"
                    value="1"
                    readonly
                    tabindex="-1"
                    aria-readonly="true">
            </div>
        </div>

        <div class="card table-card app-allocation-wrap">
            <table class="data-table" id="feeItemsTable">
                <thead>
                <tr>
                    <th>Select</th>
                    <th>Fee Head</th>
                    <th>Amount</th>
                    <th>Mandatory</th>
                </tr>
                </thead>
                <tbody id="feeItemsBody">
                <tr>
                    <td colspan="4" class="empty">Select a Fee Structure.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="card-section-title">Admission Payment & Summary</div>

        <div class="app-split-grid">
            <div class="app-side-card">
                <div class="app-side-card-head">Admission Payment</div>

                <div class="app-side-card-body">
                    <div class="card table-card app-allocation-wrap">
                        <table class="app-allocation-table" id="admissionPaymentTable">
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
                            <tr data-payment-mode="1">
                                <td class="app-allocation-mode">Cash</td>
                                <td>
                                    <select id="cashAccountId" class="payment-account" data-mode="1">
                                        <option value="">Select Cash Account</option>
                                    </select>
                                </td>
                                <td>
                                    <input class="payment-amount" data-mode="1" type="text" inputmode="decimal" placeholder="0.00">
                                </td>
                                <td class="muted">—</td>
                                <td class="muted">—</td>
                            </tr>

                            <tr data-payment-mode="2">
                                <td class="app-allocation-mode">UPI</td>
                                <td>
                                    <select id="upiAccountId" class="payment-account" data-mode="2">
                                        <option value="">Select Bank Account</option>
                                    </select>
                                </td>
                                <td>
                                    <input class="payment-amount" data-mode="2" type="text" inputmode="decimal" placeholder="0.00">
                                </td>
                                <td>
                                    <input class="payment-reference" data-mode="2" type="text" maxlength="150" placeholder="UTR / Ref No">
                                </td>
                                <td class="muted">—</td>
                            </tr>

                            <tr data-payment-mode="3">
                                <td class="app-allocation-mode">Bank</td>
                                <td>
                                    <select id="bankAccountId" class="payment-account" data-mode="3">
                                        <option value="">Select Bank Account</option>
                                    </select>
                                </td>
                                <td>
                                    <input class="payment-amount" data-mode="3" type="text" inputmode="decimal" placeholder="0.00">
                                </td>
                                <td>
                                    <input class="payment-reference" data-mode="3" type="text" maxlength="150" placeholder="Transaction / Ref No">
                                </td>
                                <td class="muted">—</td>
                            </tr>

                            <tr data-payment-mode="4">
                                <td class="app-allocation-mode">Cheque</td>
                                <td>
                                    <select id="chequeAccountId" class="payment-account" data-mode="4">
                                        <option value="">Select Bank Account</option>
                                    </select>
                                </td>
                                <td>
                                    <input class="payment-amount" data-mode="4" type="text" inputmode="decimal" placeholder="0.00">
                                </td>
                                <td>
                                    <input id="chequeNo" class="payment-reference" data-mode="4" type="text" maxlength="100" placeholder="Cheque No">
                                </td>
                                <td>
                                    <input id="chequeDate" class="payment-cheque-date" type="date">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="app-total-line">
                        <span>Admission Payment</span>
                        <strong id="admissionPaymentText">₹0.00</strong>
                    </div>

                    <div class="buttons" id="admissionPaymentActions" hidden>
                        <button
                            class="btn gray"
                            id="updateAdmissionPaymentButton"
                            type="button">
                            <i data-lucide="save"></i>
                            Update Payment
                        </button>

                        <button
                            class="btn gray"
                            id="deleteAdmissionPaymentButton"
                            type="button"
                            hidden>
                            <i data-lucide="trash-2"></i>
                            Delete Payment
                        </button>
                    </div>
                </div>
            </div>

            <div class="app-side-card">
                <div class="app-side-card-head">Fee Summary</div>

                <div class="app-side-card-body">
                    <div class="app-summary-row">
                        <span>Total Fee</span>
                        <strong id="totalFeeText">₹0.00</strong>
                    </div>

                    <div class="app-summary-row">
                        <span>Admission Payment</span>
                        <strong id="admissionPaidSummaryText">₹0.00</strong>
                    </div>

                    <div class="app-summary-row total">
                        <span>Remaining Balance</span>
                        <strong id="remainingBalanceText">₹0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <input id="remainingBalance" type="hidden" value="0.00">

        <div class="card-section-title">Payment Plan / EMI</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="paymentType" class="required">Payment Type</label>
                <select id="paymentType" name="payment_type" required>
                    <option value="emi">EMI</option>
                    <option value="full">Full Payment</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="installmentCount">EMI Count</label>
                <input
                    id="installmentCount"
                    name="installment_count"
                    type="text"
                    inputmode="numeric"
                    maxlength="3"
                    value="1"
                    placeholder="Example: 3">
            </div>

            <div class="field col-4">
                <label for="firstDueDate">First EMI Due Date</label>
                <input id="firstDueDate" name="first_due_date" type="date">
            </div>
        </div>

        <div class="card table-card app-allocation-wrap">
            <table class="data-table" id="emiTable">
                <thead>
                <tr>
                    <th>EMI</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody id="emiBody">
                <tr>
                    <td colspan="3" class="empty">Enter EMI Count and First EMI Due Date.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <a class="btn gray" href="admission-list.php">Cancel</a>

            <button class="btn btn-primary" id="saveButton" type="submit">
                <i data-lucide="save"></i>
                <span id="saveButtonText">Save Admission</span>
            </button>

            <button class="btn btn-primary" id="savePrintButton" type="submit">
                <i data-lucide="printer"></i>
                <span>Save &amp; Print</span>
            </button>
        </div>
    </div>
</form>
</div>

<script>
(function () {
    'use strict';


    function openReceiptPdf(url, existingWindow) {
        var token = window.App && typeof App.getToken === 'function'
            ? App.getToken()
            : '';

        if (!token) {
            if (window.App && typeof App.goToLogin === 'function') {
                App.goToLogin('Your login has expired. Please login again.');
            } else if (typeof window.showToast === 'function') {
                showToast('Authentication token is missing. Please login again.', {
                    type:'danger',
                    duration:4
                });
            }
            return false;
        }

        var receiptRef = '';
        try {
            var parsedUrl = new URL(url, window.location.href);
            receiptRef = parsedUrl.searchParams.get('ref') || '';
        } catch (error) {
            receiptRef = '';
        }

        if (!receiptRef) {
            if (typeof window.showToast === 'function') {
                showToast('Receipt reference is missing.', {
                    type:'danger',
                    duration:4
                });
            }
            return false;
        }

        var pdfWindow = existingWindow || window.open('', '_blank');

        if (!pdfWindow) {
            if (typeof window.showToast === 'function') {
                showToast('Please allow popups to open the receipt.', {
                    type:'warning',
                    duration:4
                });
            }
            return false;
        }

        var targetName = 'amirtham_receipt_window';

        try {
            pdfWindow.name = targetName;
        } catch (ignoreName) {}

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'student-fee-receipt.php';
        form.target = targetName;
        form.style.display = 'none';

        var refInput = document.createElement('input');
        refInput.type = 'hidden';
        refInput.name = 'ref';
        refInput.value = receiptRef;
        form.appendChild(refInput);

        var tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'auth_token';
        tokenInput.value = token;
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
        form.remove();

        return true;
    }


    var form = document.getElementById('admissionForm');
    var params = new URLSearchParams(window.location.search);
    var pageRef = params.get('ref') || '';
    var viewMode = params.get('view') === '1';

    var actions = [];
    var students = [];
    var courses = [];
    var batches = [];
    var feeStructures = [];
    var feeItems = [];
    var accounts = [];
    var saving = false;
    var feeRequestSequence = 0;
    var currentAdmissionReceiptRef = '';

    var studentSelect;
    var courseSelect;
    var batchSelect;
    var feeStructureSelect;
    var accountSelects = {};

    function el(id) {
        return document.getElementById(id);
    }

    function has(actionId) {
        return actions.indexOf(Number(actionId)) !== -1;
    }

    function n(value) {
        var number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    function r2(value) {
        return Math.round((n(value) + Number.EPSILON) * 100) / 100;
    }

    function money(value) {
        return '₹' + n(value).toLocaleString('en-IN', {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        });
    }

    function decimal(value) {
        return n(value).toFixed(2);
    }

    function esc(value) {
        if (window.App && typeof App.escapeHtml === 'function') {
            return App.escapeHtml(String(value == null ? '' : value));
        }

        return String(value == null ? '' : value)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
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

        showToast(message,{type:'danger',duration:4});
    }

    function option(rows, valueKey, labelFn) {
        return (rows || []).map(function (row) {
            return {
                value:row[valueKey],
                text:labelFn(row)
            };
        });
    }

    function setStudentOptions(selected) {
        studentSelect.setOptions(
            option(students,'id',function (row) {
                return (
                    row.student_code + ' - ' + row.student_name +
                    (row.mobile ? ' - ' + row.mobile : '')
                );
            }),
            selected || ''
        );
    }

    function setCourseOptions(selected) {
        courseSelect.setOptions(
            option(courses,'id',function (row) {
                return row.course_code + ' - ' + row.course_name;
            }),
            selected || ''
        );
    }

    function setBatchOptions(selected) {
        var courseId = Number(el('courseId').value || 0);

        var rows = batches.filter(function (row) {
            return Number(row.course_id) === courseId;
        });

        batchSelect.setOptions(
            option(rows,'id',function (row) {
                return row.batch_code + ' - ' + row.batch_name;
            }),
            selected || ''
        );
    }

    function setFeeStructureOptions(selected) {
        var courseId = Number(el('courseId').value || 0);
        var admissionDate = el('admissionDate').value || '';

        var rows = feeStructures.filter(function (row) {
            if (Number(row.course_id) !== courseId) {
                return false;
            }

            if (
                admissionDate &&
                row.effective_from &&
                String(row.effective_from) > admissionDate
            ) {
                return false;
            }

            return true;
        });

        feeStructureSelect.setOptions(
            option(rows,'id',function (row) {
                return (
                    row.structure_code + ' - ' + row.structure_name +
                    ' (' + money(row.total_amount) + ')'
                );
            }),
            selected || ''
        );
    }

    function accountOptions(mode) {
        var wantedType = Number(mode) === 1 ? 1 : 2;

        return option(
            accounts.filter(function (row) {
                return Number(row.account_type) === wantedType;
            }),
            'id',
            function (row) {
                return (
                    (row.account_code ? row.account_code + ' - ' : '') +
                    row.account_name +
                    (row.bank_name ? ' - ' + row.bank_name : '')
                );
            }
        );
    }

    function setAccountOptions(mode, selected) {
        if (!accountSelects[mode]) return;

        accountSelects[mode].setOptions(
            accountOptions(mode),
            selected || ''
        );
    }

    function selectedBatch() {
        var id = Number(el('batchId').value || 0);

        return batches.find(function (row) {
            return Number(row.id) === id;
        }) || null;
    }

    async function loadFeeStructures(selectedId, includeStructureId) {
        var courseId = Number(el('courseId').value || 0);
        var admissionDate = el('admissionDate').value || '';
        var requestId = ++feeRequestSequence;

        feeStructures = [];
        feeItems = [];

        if (!courseId) {
            feeStructureSelect.setOptions([], '');
            el('defaultInstallments').value = '1';
            renderFeeItems([]);
            return;
        }

        try {
            var query = new URLSearchParams({
                fee_options:'1',
                course_id:String(courseId),
                admission_date:admissionDate
            });

            if (Number(includeStructureId || 0) > 0) {
                query.set(
                    'include_structure_id',
                    String(Number(includeStructureId))
                );
            }

            var response = await App.api(
                'api/admissions.php?' + query.toString()
            );

            if (requestId !== feeRequestSequence) {
                return;
            }

            feeStructures = response.data.fee_structures || [];
            feeItems = response.data.fee_items || [];

            setFeeStructureOptions(selectedId || '');

            var structure = selectedFeeStructure();

            el('defaultInstallments').value =
                structure
                    ? String(structure.default_installment_count || 1)
                    : '1';

            if (!selectedId) {
                renderFeeItems([]);
            }
        } catch (error) {
            if (requestId !== feeRequestSequence) {
                return;
            }

            feeStructures = [];
            feeItems = [];
            feeStructureSelect.setOptions([], '');
            el('defaultInstallments').value = '1';
            renderFeeItems([]);

            showError(
                error,
                'Unable to load Fee Structure for the selected Course.'
            );
        }
    }

    function selectedFeeStructure() {
        var id = Number(el('feeStructureId').value || 0);

        return feeStructures.find(function (row) {
            return Number(row.id) === id;
        }) || null;
    }

    function updateStudentMode() {
        var mode = el('studentMode').value;

        el('newStudentSection').hidden = mode !== 'new';
        el('existingStudentSection').hidden = mode !== 'existing';

        el('studentName').required = mode === 'new';
        el('studentId').required = mode === 'existing';

        if (pageRef) {
            el('studentMode').disabled = true;
        }
    }

    function updateCompletionDate() {
        var batch = selectedBatch();

        el('completionDate').value =
            batch && batch.end_date ? batch.end_date : '';
    }

    function renderFeeItems(selectedIds) {
        var structureId = Number(el('feeStructureId').value || 0);
        var selectedMap = {};

        (selectedIds || []).forEach(function (id) {
            selectedMap[String(id)] = true;
        });

        if (!structureId) {
            el('feeItemsBody').innerHTML =
                '<tr><td colspan="4" class="empty">Select a Fee Structure.</td></tr>';
            calculate();
            return;
        }

        var rows = feeItems.filter(function (row) {
            return Number(row.fee_structure_id) === structureId;
        });

        if (!rows.length) {
            el('feeItemsBody').innerHTML =
                '<tr><td colspan="4" class="empty">No active Fee items available.</td></tr>';
            calculate();
            return;
        }

        el('feeItemsBody').innerHTML = rows.map(function (row) {
            var mandatory = Number(row.mandatory) === 1;
            var checked = mandatory || selectedMap[String(row.id)];

            return (
                '<tr data-fee-item-id="' + Number(row.id) + '" ' +
                    'data-amount="' + esc(row.amount) + '" ' +
                    'data-mandatory="' + (mandatory ? '1' : '0') + '">' +
                    '<td><input type="checkbox" class="fee-select" ' +
                        (checked ? 'checked ' : '') +
                        ((mandatory || viewMode) ? 'disabled ' : '') +
                        'aria-label="Select ' + esc(row.fee_head) + '"></td>' +
                    '<td>' + esc(row.fee_head) + '</td>' +
                    '<td>' + money(row.amount) + '</td>' +
                    '<td>' + (mandatory ? 'Yes' : 'No') + '</td>' +
                '</tr>'
            );
        }).join('');

        calculate();
    }

    function selectedFeeIds() {
        var ids = [];

        el('feeItemsBody')
            .querySelectorAll('tr[data-fee-item-id]')
            .forEach(function (row) {
                var checkbox = row.querySelector('.fee-select');

                if (checkbox && checkbox.checked) {
                    ids.push(Number(row.dataset.feeItemId));
                }
            });

        return ids;
    }

    function selectedFeeTotal() {
        var total = 0;

        el('feeItemsBody')
            .querySelectorAll('tr[data-fee-item-id]')
            .forEach(function (row) {
                var checkbox = row.querySelector('.fee-select');

                if (checkbox && checkbox.checked) {
                    total += n(row.dataset.amount);
                }
            });

        return r2(total);
    }

    function paymentRows() {
        var output = [];

        [1,2,3,4].forEach(function (mode) {
            var account = document.querySelector(
                '.payment-account[data-mode="' + mode + '"]'
            );

            var amount = document.querySelector(
                '.payment-amount[data-mode="' + mode + '"]'
            );

            var reference = document.querySelector(
                '.payment-reference[data-mode="' + mode + '"]'
            );

            var row = {
                payment_mode:mode,
                account_id:account ? account.value || '' : '',
                amount:amount ? amount.value || 0 : 0,
                reference_no:'',
                cheque_no:'',
                cheque_date:''
            };

            if (mode === 2 || mode === 3) {
                row.reference_no = reference ? reference.value.trim() : '';
            }

            if (mode === 4) {
                row.cheque_no = el('chequeNo').value.trim();
                row.cheque_date = el('chequeDate').value;
            }

            output.push(row);
        });

        return output;
    }

    function admissionPaymentTotal() {
        var total = 0;

        document.querySelectorAll('.payment-amount').forEach(function (input) {
            total += Math.max(0,n(input.value));
        });

        return r2(total);
    }

    function addMonths(dateText, offset) {
        if (!dateText) return '';

        var parts = dateText.split('-').map(Number);
        if (parts.length !== 3) return '';

        var totalMonths = (parts[0] * 12) + (parts[1] - 1) + offset;
        var year = Math.floor(totalMonths / 12);
        var monthZero = totalMonths % 12;
        var lastDay = new Date(Date.UTC(year,monthZero + 1,0)).getUTCDate();
        var day = Math.min(parts[2],lastDay);

        return [
            year,
            String(monthZero + 1).padStart(2,'0'),
            String(day).padStart(2,'0')
        ].join('-');
    }

    function emiRows() {
        var balance = n(el('remainingBalance').value);
        var count = Number(el('installmentCount').value || 0);
        var firstDueDate = el('firstDueDate').value;

        if (
            balance <= 0 ||
            el('paymentType').value !== 'emi' ||
            !Number.isInteger(count) ||
            count < 1 ||
            !firstDueDate
        ) {
            return [];
        }

        var cents = Math.round(balance * 100);
        var base = Math.floor(cents / count);
        var allocated = 0;
        var rows = [];

        for (var i = 1; i <= count; i++) {
            var amountCents = i === count ? cents - allocated : base;
            allocated += amountCents;

            rows.push({
                installment_number:i,
                due_date:addMonths(firstDueDate,i - 1),
                due_amount:amountCents / 100
            });
        }

        return rows;
    }

    function renderEmi() {
        var rows = emiRows();

        if (!rows.length) {
            el('emiBody').innerHTML =
                '<tr><td colspan="3" class="empty">' +
                (
                    n(el('remainingBalance').value) <= 0
                        ? 'No EMI required. Fee is fully paid during Admission.'
                        : 'Enter EMI Count and First EMI Due Date.'
                ) +
                '</td></tr>';
            return;
        }

        el('emiBody').innerHTML = rows.map(function (row) {
            return (
                '<tr>' +
                    '<td>EMI ' + row.installment_number + '</td>' +
                    '<td>' + esc(row.due_date) + '</td>' +
                    '<td>' + money(row.due_amount) + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function calculate() {
        var totalFee = selectedFeeTotal();
        var paid = admissionPaymentTotal();
        var balance = Math.max(0,r2(totalFee - paid));

        el('selectedFeeTotal').value = decimal(totalFee);
        el('remainingBalance').value = decimal(balance);

        el('totalFeeText').textContent = money(totalFee);
        el('admissionPaymentText').textContent = money(paid);
        el('admissionPaidSummaryText').textContent = money(paid);
        el('remainingBalanceText').textContent = money(balance);

        if (balance <= 0.009) {
            el('paymentType').value = 'full';
            el('paymentType').disabled = true;
            el('installmentCount').disabled = true;
            el('firstDueDate').disabled = true;
        } else {
            el('paymentType').disabled = viewMode;

            if (el('paymentType').value === 'full') {
                el('paymentType').value = 'emi';
            }

            var useEmi = el('paymentType').value === 'emi';

            el('installmentCount').disabled = viewMode || !useEmi;
            el('firstDueDate').disabled = viewMode || !useEmi;
        }

        renderEmi();
    }

    function normalizeFields() {
        el('studentMobile').value =
            el('studentMobile').value.replace(/\D+/g,'').slice(0,10);

        el('guardianMobile').value =
            el('guardianMobile').value.replace(/\D+/g,'').slice(0,10);

        document.querySelectorAll('.payment-amount').forEach(function (input) {
            input.value = input.value.trim();
        });
    }

    function validateAdmissionPaymentOnly() {
        normalizeFields();

        var totalFee = selectedFeeTotal();
        var paid = admissionPaymentTotal();

        if (paid > totalFee + 0.009) {
            showToast(
                'Admission Payment cannot exceed Total Fee.',
                {type:'danger',duration:4}
            );
            return false;
        }

        var rows = paymentRows();

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];

            if (n(row.amount) <= 0) {
                continue;
            }

            if (!Number(row.account_id || 0)) {
                showToast(
                    'Select Account for each Payment Mode having an amount.',
                    {type:'danger',duration:4}
                );
                return false;
            }

            if (
                row.payment_mode === 2 &&
                !row.reference_no
            ) {
                showToast(
                    'UPI Reference No is required.',
                    {type:'danger',duration:4}
                );
                return false;
            }

            if (
                row.payment_mode === 3 &&
                !row.reference_no
            ) {
                showToast(
                    'Bank Reference No is required.',
                    {type:'danger',duration:4}
                );
                return false;
            }

            if (
                row.payment_mode === 4 &&
                (
                    !row.cheque_no ||
                    !row.cheque_date
                )
            ) {
                showToast(
                    'Cheque No and Cheque Date are required.',
                    {type:'danger',duration:4}
                );
                return false;
            }
        }

        return true;
    }

    function validateBusinessRules() {
        var mode = el('studentMode').value;

        if (mode === 'existing' && !el('studentId').value) {
            showToast('Select Existing Student.',{type:'danger',duration:4});
            return false;
        }

        var batch = selectedBatch();
        var date = el('admissionDate').value;

        if (batch && date && batch.start_date && date < batch.start_date) {
            showToast(
                'Admission Date cannot be before Batch Start Date.',
                {type:'danger',duration:4}
            );
            return false;
        }

        if (batch && date && batch.end_date && date > batch.end_date) {
            showToast(
                'Admission Date cannot be after Batch End Date.',
                {type:'danger',duration:4}
            );
            return false;
        }

        if (!selectedFeeIds().length) {
            showToast(
                'Select at least one Fee item.',
                {type:'danger',duration:4}
            );
            return false;
        }

        var totalFee = selectedFeeTotal();
        var paid = admissionPaymentTotal();

        if (paid > totalFee + 0.009) {
            showToast(
                'Admission Payment cannot exceed Total Fee.',
                {type:'danger',duration:4}
            );
            return false;
        }

        var rows = paymentRows();

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];

            if (n(row.amount) <= 0) continue;

            if (!Number(row.account_id || 0)) {
                showToast(
                    'Select Account for each Payment Mode having an amount.',
                    {type:'danger',duration:4}
                );
                return false;
            }

            if (row.payment_mode === 2 && !row.reference_no) {
                showToast('UPI Reference No is required.',{type:'danger',duration:4});
                return false;
            }

            if (row.payment_mode === 3 && !row.reference_no) {
                showToast('Bank Reference No is required.',{type:'danger',duration:4});
                return false;
            }

            if (row.payment_mode === 4 && (!row.cheque_no || !row.cheque_date)) {
                showToast(
                    'Cheque No and Cheque Date are required.',
                    {type:'danger',duration:4}
                );
                return false;
            }
        }

        var balance = n(el('remainingBalance').value);

        if (balance > 0.009) {
            var count = Number(el('installmentCount').value || 0);

            if (!Number.isInteger(count) || count < 1 || count > 120) {
                showToast(
                    'EMI Count must be between 1 and 120.',
                    {type:'danger',duration:4}
                );
                return false;
            }

            if (!el('firstDueDate').value) {
                showToast(
                    'First EMI Due Date is required.',
                    {type:'danger',duration:4}
                );
                return false;
            }
        }

        return true;
    }

    function newStudentPayload() {
        return {
            student_name:el('studentName').value.trim(),
            mobile:el('studentMobile').value.trim(),
            gender:el('studentGender').value,
            date_of_birth:el('studentDob').value,
            email:el('studentEmail').value.trim(),
            qualification:el('studentQualification').value.trim(),
            guardian_name:el('guardianName').value.trim(),
            guardian_mobile:el('guardianMobile').value.trim(),
            identity_number:el('identityNumber').value.trim(),
            address:el('studentAddress').value.trim()
        };
    }

    function payload() {
        return {
            action:'save',
            ref:el('admissionRef').value || '',
            student_mode:pageRef ? 'existing' : el('studentMode').value,
            student_id:el('studentId').value || '',
            new_student:newStudentPayload(),
            admission_date:el('admissionDate').value,
            course_id:el('courseId').value || '',
            batch_id:el('batchId').value || '',
            admission_state:el('admissionState').value,
            status:el('status').value,
            remarks:el('remarks').value.trim(),
            fee_structure_id:el('feeStructureId').value || '',
            selected_fee_item_ids:selectedFeeIds(),
            payment_rows:paymentRows(),
            payment_type:el('paymentType').value || 'emi',
            installment_count:el('installmentCount').value || '0',
            first_due_date:el('firstDueDate').value || ''
        };
    }

    function applyPaymentDetails(details) {
        var map = {};

        (details || []).forEach(function (row) {
            map[String(row.payment_mode)] = row;
        });

        [1,2,3,4].forEach(function (mode) {
            var row = map[String(mode)] || {};

            setAccountOptions(
                mode,
                row.account_id ? String(row.account_id) : ''
            );

            var amount = document.querySelector(
                '.payment-amount[data-mode="' + mode + '"]'
            );

            if (amount) {
                amount.value = row.amount ? decimal(row.amount) : '';
            }

            if (mode === 2 || mode === 3) {
                var reference = document.querySelector(
                    '.payment-reference[data-mode="' + mode + '"]'
                );

                if (reference) {
                    reference.value = row.reference_no || '';
                }
            }

            if (mode === 4) {
                el('chequeNo').value = row.cheque_no || '';
                el('chequeDate').value = row.cheque_date || '';
            }
        });
    }

    async function loadRecord(data) {
        var admission = data.admission || {};
        var plan = data.fee_plan || {};
        var receipt = data.admission_receipt || {};

        currentAdmissionReceiptRef =
            receipt.ref || '';

        el('admissionRef').value = admission.ref || '';
        el('admissionNo').value = admission.admission_no || '';
        el('admissionDate').value = admission.admission_date || '';
        el('admissionState').value = admission.admission_state || 'active';
        el('status').value = String(admission.status == null ? 1 : admission.status);
        el('remarks').value = admission.remarks || '';

        el('studentMode').value = 'existing';
        updateStudentMode();

        setStudentOptions(admission.student_id || '');
        setCourseOptions(admission.course_id || '');
        setBatchOptions(admission.batch_id || '');
        updateCompletionDate();

        await loadFeeStructures(
            plan.fee_structure_id || '',
            plan.fee_structure_id || 0
        );

        var structure = selectedFeeStructure();

        el('defaultInstallments').value =
            structure ? String(structure.default_installment_count || 1) : '1';

        renderFeeItems(
            (data.fee_plan_items || []).map(function (row) {
                return row.source_fee_structure_item_id;
            })
        );

        applyPaymentDetails(receipt.payment_details || []);

        el('paymentType').value = plan.payment_type || 'emi';
        el('installmentCount').value =
            String(plan.installment_count == null ? 1 : plan.installment_count);
        el('firstDueDate').value = plan.first_due_date || '';

        calculate();

        if (!viewMode && pageRef && has(3)) {
            el('admissionPaymentActions').hidden = false;

            el('deleteAdmissionPaymentButton').hidden =
                !currentAdmissionReceiptRef;
        }

        if (viewMode) {
            el('pageHeading').textContent = 'View Student Admission';
            el('pageDescription').textContent =
                'View Student, Course, Fee Plan and Admission Payment.';

            if (has(3)) {
                el('editButton').hidden = false;
                el('editButton').href =
                    'admission-form.php?ref=' + encodeURIComponent(pageRef);
            }
        } else {
            el('pageHeading').textContent = 'Edit Student Admission';
            el('saveButtonText').textContent = 'Update Admission';
        }
    }

    function applyViewMode() {
        if (!viewMode) return;

        form.querySelectorAll('input,select,textarea,button').forEach(function (control) {
            if (
                control.tagName === 'SELECT' ||
                control.type === 'checkbox' ||
                control.type === 'date'
            ) {
                control.disabled = true;
            } else if (
                control.tagName === 'INPUT' ||
                control.tagName === 'TEXTAREA'
            ) {
                control.readOnly = true;
            }
        });

        el('saveButton').hidden = true;
    }

    function bindEvents() {
        el('studentMode').addEventListener('change',updateStudentMode);

        el('courseId').addEventListener('change',async function () {
            setBatchOptions('');
            el('completionDate').value = '';
            await loadFeeStructures('',0);
        });

        el('batchId').addEventListener('change',updateCompletionDate);

        el('admissionDate').addEventListener('change',async function () {
            var current = el('feeStructureId').value || '';

            await loadFeeStructures(
                current,
                pageRef ? Number(current || 0) : 0
            );

            if (current && el('feeStructureId').value !== current) {
                renderFeeItems([]);
            }
        });

        el('feeStructureId').addEventListener('change',function () {
            var structure = selectedFeeStructure();

            el('defaultInstallments').value =
                structure ? String(structure.default_installment_count || 1) : '1';

            if (structure) {
                el('installmentCount').value =
                    String(structure.default_installment_count || 1);
            }

            renderFeeItems([]);
        });

        el('feeItemsBody').addEventListener('change',function (event) {
            if (event.target.matches('.fee-select')) {
                calculate();
            }
        });

        document.querySelectorAll('[data-payment-mode]').forEach(function (row) {
            row.addEventListener('input',calculate);
            row.addEventListener('change',calculate);
        });

        el('paymentType').addEventListener('change',calculate);
        el('installmentCount').addEventListener('input',renderEmi);
        el('firstDueDate').addEventListener('change',renderEmi);

        el('updateAdmissionPaymentButton')
            .addEventListener(
                'click',
                async function () {
                    if (
                        !pageRef ||
                        viewMode ||
                        !has(3)
                    ) {
                        return;
                    }

                    if (!validateAdmissionPaymentOnly()) {
                        return;
                    }

                    var button = this;
                    button.disabled = true;

                    try {
                        var response =
                            await App.api(
                                'api/college-fee-payments.php',
                                {
                                    method:'POST',
                                    body:{
                                        action:'save',
                                        receipt_type:'admission',
                                        admission_ref:pageRef,
                                        receipt_ref:
                                            currentAdmissionReceiptRef ||
                                            '',
                                        receipt_date:
                                            el('admissionDate').value,
                                        payment_rows:
                                            paymentRows()
                                    }
                                }
                            );

                        showToast(
                            response.message ||
                            'Admission Payment updated successfully.',
                            {
                                type:'success',
                                duration:2
                            }
                        );

                        window.location.reload();
                    } catch (error) {
                        showError(
                            error,
                            'Unable to update Admission Payment.'
                        );
                    } finally {
                        button.disabled = false;
                    }
                }
            );

        el('deleteAdmissionPaymentButton')
            .addEventListener(
                'click',
                async function () {
                    if (
                        !currentAdmissionReceiptRef ||
                        viewMode ||
                        !has(3)
                    ) {
                        return;
                    }

                    if (
                        !window.confirm(
                            'Delete this Admission Payment? EMI amounts, Student Payment allocations, Total Paid and Balance will be recalculated automatically.'
                        )
                    ) {
                        return;
                    }

                    var button = this;
                    button.disabled = true;

                    try {
                        var response =
                            await App.api(
                                'api/college-fee-payments.php',
                                {
                                    method:'POST',
                                    body:{
                                        action:'delete',
                                        receipt_ref:
                                            currentAdmissionReceiptRef
                                    }
                                }
                            );

                        showToast(
                            response.message ||
                            'Admission Payment deleted successfully.',
                            {
                                type:'success',
                                duration:2
                            }
                        );

                        window.location.reload();
                    } catch (error) {
                        button.disabled = false;

                        showError(
                            error,
                            'Unable to delete Admission Payment.'
                        );
                    }
                }
            );

        form.addEventListener('submit',async function (event) {
            event.preventDefault();

            if (saving || viewMode) return;

            var printAfterSave = Boolean(
                event.submitter && event.submitter.id === 'savePrintButton'
            );
            var receiptWindow = null;

            if (window.Validation) {
                Validation.clearForm(form);
                normalizeFields();

                if (!Validation.validateForm(form)) {
                    showToast(
                        'Please complete all required Admission fields.',
                        {type:'danger',duration:4}
                    );
                    return;
                }
            } else {
                normalizeFields();
            }

            if (!validateBusinessRules()) return;

            if (printAfterSave) {
                receiptWindow = window.open('', '_blank');
                if (!receiptWindow) {
                    showToast(
                        'Please allow popups to open the receipt after saving.',
                        {type:'warning',duration:4}
                    );
                }
            }

            saving = true;
            el('saveButton').disabled = true;
            el('savePrintButton').disabled = true;

            try {
                var response = await App.api('api/admissions.php',{
                    method:'POST',
                    body:payload()
                });

                showToast(
                    response.message || 'Admission saved successfully.',
                    {type:'success',duration:2}
                );

                if (printAfterSave) {
                    var receiptRef = response && response.data
                        ? String(response.data.receipt_ref || '')
                        : '';

                    if (receiptRef) {
                        await openReceiptPdf(
                            'student-fee-receipt.php?ref=' +
                            encodeURIComponent(receiptRef),
                            receiptWindow
                        );
                    } else {
                        if (receiptWindow) {
                            try { receiptWindow.close(); } catch (ignoreClose) {}
                        }
                        showToast(
                            'Admission saved. No receipt was created because Admission Payment is zero.',
                            {type:'info',duration:4}
                        );
                    }
                }

                window.location.href = 'admission-list.php';
            } catch (error) {
                if (receiptWindow) {
                    try { receiptWindow.close(); } catch (ignoreClose) {}
                }

                if (
                    window.Validation &&
                    typeof Validation.applyErrors === 'function'
                ) {
                    var errors =
                        (error && error.errors) ||
                        (error && error.data && error.data.errors) ||
                        {};

                    Validation.applyErrors(form,errors);
                }

                showError(error,'Unable to save Admission.');
            } finally {
                saving = false;
                el('saveButton').disabled = false;
                el('savePrintButton').disabled = false;
            }
        });
    }

    async function load() {
        try {
            var response = pageRef
                ? await App.api(
                    'api/admissions.php?ref=' + encodeURIComponent(pageRef)
                )
                : await App.api('api/admissions.php?options=1');

            actions = (response.data.allowed_actions || []).map(Number);
            students = response.data.students || [];
            courses = response.data.courses || [];
            batches = response.data.batches || [];
            feeStructures = response.data.fee_structures || [];
            feeItems = response.data.fee_items || [];
            accounts = response.data.accounts || [];

            studentSelect = GlobalSelect.init('#studentId',{
                placeholder:'Search Existing Student'
            });

            courseSelect = GlobalSelect.init('#courseId',{
                placeholder:'Select Course'
            });

            batchSelect = GlobalSelect.init('#batchId',{
                placeholder:'Select Batch'
            });

            feeStructureSelect = GlobalSelect.init('#feeStructureId',{
                placeholder:'Select Fee Structure'
            });

            accountSelects = {
                1:GlobalSelect.init('#cashAccountId',{placeholder:'Select Cash Account'}),
                2:GlobalSelect.init('#upiAccountId',{placeholder:'Select Bank Account'}),
                3:GlobalSelect.init('#bankAccountId',{placeholder:'Select Bank Account'}),
                4:GlobalSelect.init('#chequeAccountId',{placeholder:'Select Bank Account'})
            };

            setStudentOptions('');
            setCourseOptions('');
            setBatchOptions('');
            setFeeStructureOptions('');

            [1,2,3,4].forEach(function (mode) {
                setAccountOptions(mode,'');
            });

            if (pageRef) {
                await loadRecord(response.data);
            } else {
                el('studentMode').value =
                    response.data.student_mode_default || 'new';

                el('admissionNo').value =
                    response.data.next_admission_no || 'AUTO';

                el('admissionDate').value =
                    response.data.today || '';

                updateStudentMode();
                calculate();
            }

            bindEvents();

            if (viewMode) {
                applyViewMode();
            } else if (pageRef && !has(3)) {
                showToast(
                    'You do not have Update permission for Admission.',
                    {type:'danger',duration:4}
                );
                el('saveButton').hidden = true;
                el('savePrintButton').hidden = true;
            } else if (!pageRef && !has(2)) {
                showToast(
                    'You do not have Create permission for Admission.',
                    {type:'danger',duration:4}
                );
                el('saveButton').hidden = true;
                el('savePrintButton').hidden = true;
            }

            if (viewMode) {
                el('savePrintButton').hidden = true;
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            showError(error,'Unable to load Admission form.');
            el('saveButton').hidden = true;
            el('savePrintButton').hidden = true;
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
