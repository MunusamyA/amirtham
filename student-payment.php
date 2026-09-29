<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Student Payment';
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
        <h1>Student Payment</h1>
        <p>Collect Student Fee Payment against Overall Outstanding or a Particular EMI.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="student-payment-list.php">
            <i data-lucide="history"></i>
            Payment History
        </a>

        <a class="btn gray" href="admission-list.php">
            <i data-lucide="arrow-left"></i>
            Admission List
        </a>
    </div>
</div>

<div class="card form-card" id="paymentCard">
<form id="paymentForm" novalidate>
    <input id="paymentRef" type="hidden">

    <div class="card-header">
        <div>
            <h2 id="studentHeading">Student Fee Payment</h2>
            <p id="studentSubheading">Loading Student Admission...</p>
        </div>
    </div>

    <div class="card-body">
        <div class="card-section-title">Select Student Admission</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="paymentStudentId" class="required">Student</label>
                <select
                    id="paymentStudentId"
                    data-placeholder="Search Student">
                    <option value="">Select Student</option>
                </select>
            </div>

            <div class="field col-8">
                <label for="paymentAdmissionId" class="required">Admission</label>
                <select
                    id="paymentAdmissionId"
                    data-placeholder="Select Admission">
                    <option value="">Select Admission</option>
                </select>
            </div>
        </div>

        <div class="card-section-title">Student & Admission</div>

        <div class="form-row">
            <div class="field col-4">
                <label>Admission No</label>
                <input id="admissionNo" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Student</label>
                <input id="studentName" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Mobile</label>
                <input id="studentMobile" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label>Course</label>
                <input id="courseName" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Batch</label>
                <input id="batchName" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Admission Date</label>
                <input id="admissionDate" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="card-section-title">Fee Summary</div>

        <div class="form-row">
            <div class="field col-4">
                <label>Total Fee</label>
                <input id="totalFee" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Admission Payment</label>
                <input id="admissionPayment" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Later Payments</label>
                <input id="laterPayments" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="form-row">
            <div class="field col-4">
                <label>Total Paid</label>
                <input id="totalPaid" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>Balance</label>
                <input id="balance" type="text" readonly tabindex="-1">
            </div>

            <div class="field col-4">
                <label>EMI Outstanding</label>
                <input id="emiOutstanding" type="text" readonly tabindex="-1">
            </div>
        </div>

        <div class="card-section-title">Payment Entry</div>

        <div class="form-row">
            <div class="field col-4">
                <label for="receiptDate" class="required">Payment Date</label>
                <input
                    id="receiptDate"
                    name="receipt_date"
                    type="date"
                    required
                    data-required-message="Payment Date is required.">
            </div>

            <div class="field col-4">
                <label for="paymentAgainst" class="required">Payment Against</label>
                <select id="paymentAgainst" name="payment_against" required>
                    <option value="overall">Overall Outstanding</option>
                    <option value="emi">Particular EMI</option>
                </select>
            </div>

            <div class="field col-4" id="installmentField" hidden>
                <label for="targetInstallment" class="required">EMI</label>
                <select
                    id="targetInstallment"
                    name="target_installment_ref"
                    data-placeholder="Select EMI">
                    <option value="">Select EMI</option>
                </select>
            </div>
        </div>

        <div class="card table-card app-allocation-wrap">
            <table class="app-allocation-table" id="paymentAllocationTable">
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
            <span>Payment Total</span>
            <strong id="paymentTotal">₹0.00</strong>
        </div>

        <div class="form-row">
            <div class="field col-12">
                <label for="paymentNotes">Notes</label>
                <input
                    id="paymentNotes"
                    name="notes"
                    type="text"
                    maxlength="255"
                    autocomplete="off"
                    placeholder="Optional Payment Notes">
            </div>
        </div>

        <div class="card-section-title">EMI Schedule</div>

        <div class="card table-card app-allocation-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>EMI</th>
                    <th>Due Date</th>
                    <th>Due Amount</th>
                    <th>Waived</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody id="installmentBody">
                <tr>
                    <td colspan="7" class="empty">No EMI Schedule.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="card-section-title">Payment History</div>

        <div class="card table-card app-allocation-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Against</th>
                    <th>Allocation</th>
                    <th>Mode</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Notes</th>
                    <th>Manage</th>
                </tr>
                </thead>
                <tbody id="paymentHistoryBody">
                <tr>
                    <td colspan="9" class="empty">No later Payments recorded.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer">
        <div class="buttons">
            <button class="btn gray" id="clearPaymentButton" type="button">
                <i data-lucide="rotate-ccw"></i>
                Clear
            </button>

            <button class="btn btn-primary" id="savePaymentButton" type="submit">
                <i data-lucide="save"></i>
                <span id="savePaymentText">Save Payment</span>
            </button>
        </div>
    </div>
</form>
</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('paymentForm');
    var params = new URLSearchParams(window.location.search);
    var admissionRef = params.get('ref') || '';
    var startPaymentRef = params.get('payment_ref') || '';
    var paymentViewMode = params.get('view_payment') === '1';
    var paymentStudents = [];
    var paymentAdmissions = [];

    var actions = [];
    var admission = {};
    var summary = {};
    var installments = [];
    var payments = [];
    var accounts = [];
    var today = '';
    var saving = false;

    var accountSelects = {};
    var installmentSelect;
    var paymentStudentSelect;
    var paymentAdmissionSelect;

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

    function formatDate(value) {
        if (!value) return '-';

        var parts = String(value).split('-');
        return parts.length === 3
            ? parts[2] + '/' + parts[1] + '/' + parts[0]
            : String(value);
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

    function setPaymentStudentOptions(selected) {
        paymentStudentSelect.setOptions(
            option(
                paymentStudents,
                'id',
                function (row) {
                    return (
                        row.student_code +
                        ' - ' +
                        row.student_name +
                        (row.mobile ? ' - ' + row.mobile : '')
                    );
                }
            ),
            selected || ''
        );
    }

    function setPaymentAdmissionOptions(studentId, selectedAdmissionId) {
        var wantedStudentId = Number(studentId || 0);

        var rows = paymentAdmissions.filter(function (row) {
            return Number(row.student_id) === wantedStudentId;
        });

        paymentAdmissionSelect.setOptions(
            option(
                rows,
                'id',
                function (row) {
                    return (
                        row.admission_no +
                        ' - ' +
                        row.course_label +
                        ' - ' +
                        row.batch_label +
                        ' - Balance ' +
                        money(row.balance_amount)
                    );
                }
            ),
            selectedAdmissionId || ''
        );
    }

    function selectedPaymentAdmission() {
        var id = Number(el('paymentAdmissionId').value || 0);

        return paymentAdmissions.find(function (row) {
            return Number(row.id) === id;
        }) || null;
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
        accountSelects[mode].setOptions(
            accountOptions(mode),
            selected || ''
        );
    }

    function setInstallmentOptions(selected) {
        installmentSelect.setOptions(
            option(
                installments,
                'ref',
                function (row) {
                    return (
                        'EMI ' + row.installment_number +
                        ' - ' + formatDate(row.due_date) +
                        ' - Outstanding ' + money(row.outstanding_amount)
                    );
                }
            ),
            selected || ''
        );
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

    function paymentTotal() {
        var total = 0;

        document.querySelectorAll('.payment-amount').forEach(function (input) {
            total += Math.max(0,n(input.value));
        });

        return r2(total);
    }

    function updatePaymentTotal() {
        el('paymentTotal').textContent = money(paymentTotal());
    }

    function updateAgainst() {
        var isEmi = el('paymentAgainst').value === 'emi';

        el('installmentField').hidden = !isEmi;
        el('targetInstallment').required = isEmi;

        if (!isEmi && installmentSelect) {
            installmentSelect.selectValue('');
        }
    }

    function renderHeader() {
        el('admissionNo').value = admission.admission_no || '';
        el('studentName').value =
            (admission.student_code ? admission.student_code + ' - ' : '') +
            (admission.student_name || '');
        el('studentMobile').value = admission.mobile || '';
        el('courseName').value =
            (admission.course_code ? admission.course_code + ' - ' : '') +
            (admission.course_name || '');
        el('batchName').value =
            (admission.batch_code ? admission.batch_code + ' - ' : '') +
            (admission.batch_name || '');
        el('admissionDate').value = formatDate(admission.admission_date);

        el('studentHeading').textContent =
            admission.student_name
                ? 'Student Payment - ' + admission.student_name
                : 'Student Fee Payment';

        el('studentSubheading').textContent =
            (admission.admission_no || '') +
            (admission.course_name ? ' · ' + admission.course_name : '') +
            (admission.batch_name ? ' · ' + admission.batch_name : '');
    }

    function renderSummary() {
        el('totalFee').value = money(summary.total_fee);
        el('admissionPayment').value = money(summary.admission_payment);
        el('laterPayments').value = money(summary.later_payments);
        el('totalPaid').value = money(summary.total_paid);
        el('balance').value = money(summary.balance);
        el('emiOutstanding').value = money(summary.emi_outstanding);
    }

    function renderInstallments() {
        if (!installments.length) {
            el('installmentBody').innerHTML =
                '<tr><td colspan="7" class="empty">No EMI Schedule.</td></tr>';
            setInstallmentOptions('');
            return;
        }

        el('installmentBody').innerHTML = installments.map(function (row) {
            return (
                '<tr>' +
                    '<td>EMI ' + Number(row.installment_number) + '</td>' +
                    '<td>' + esc(formatDate(row.due_date)) + '</td>' +
                    '<td>' + money(row.due_amount) + '</td>' +
                    '<td>' + money(row.waived_amount) + '</td>' +
                    '<td>' + money(row.paid_amount) + '</td>' +
                    '<td>' + money(row.outstanding_amount) + '</td>' +
                    '<td>' + esc(row.status_label) + '</td>' +
                '</tr>'
            );
        }).join('');

        setInstallmentOptions(el('targetInstallment').value || '');
    }

    function againstLabel(row) {
        if (row.payment_against !== 'emi') {
            return 'Overall Outstanding';
        }

        var target = installments.find(function (item) {
            return item.ref === row.target_installment_ref;
        });

        return target
            ? 'EMI ' + target.installment_number
            : 'Particular EMI';
    }

    function renderHistory() {
        if (!payments.length) {
            el('paymentHistoryBody').innerHTML =
                '<tr><td colspan="9" class="empty">No later Payments recorded.</td></tr>';
            return;
        }

        el('paymentHistoryBody').innerHTML = payments.map(function (row) {
            var manage = [];

            if (has(3) && !paymentViewMode) {
                manage.push(
                    '<button type="button" class="table-icon-action edit-payment" ' +
                    'data-ref="' + esc(row.ref) + '" title="Edit Payment" aria-label="Edit Payment">' +
                    '<i data-lucide="pencil"></i></button>'
                );
            }

            if (has(4) && !paymentViewMode) {
                manage.push(
                    '<button type="button" class="table-icon-action delete-payment" ' +
                    'data-ref="' + esc(row.ref) + '" title="Delete Payment" aria-label="Delete Payment">' +
                    '<i data-lucide="trash-2"></i></button>'
                );
            }

            return (
                '<tr>' +
                    '<td>' + esc(row.receipt_no) + '</td>' +
                    '<td>' + esc(formatDate(row.receipt_date)) + '</td>' +
                    '<td>' + esc(againstLabel(row)) + '</td>' +
                    '<td>' + esc(row.allocation_label || '-') + '</td>' +
                    '<td>' + esc(row.payment_mode_label || '-') + '</td>' +
                    '<td>' + money(row.amount) + '</td>' +
                    '<td>' + money(row.balance_after) + '</td>' +
                    '<td>' + esc(row.notes || '-') + '</td>' +
                    '<td class="table-action-icons">' +
                        (manage.join(' ') || '<span class="muted">View only</span>') +
                    '</td>' +
                '</tr>'
            );
        }).join('');

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function clearPayment() {
        el('paymentRef').value = '';
        el('receiptDate').value = today || '';
        el('paymentAgainst').value = 'overall';
        el('paymentNotes').value = '';

        setInstallmentOptions('');

        [1,2,3,4].forEach(function (mode) {
            setAccountOptions(mode,'');

            var amount = document.querySelector(
                '.payment-amount[data-mode="' + mode + '"]'
            );

            if (amount) {
                amount.value = '';
            }

            if (mode === 2 || mode === 3) {
                var reference = document.querySelector(
                    '.payment-reference[data-mode="' + mode + '"]'
                );

                if (reference) {
                    reference.value = '';
                }
            }
        });

        el('chequeNo').value = '';
        el('chequeDate').value = '';
        el('savePaymentText').textContent = 'Save Payment';

        updateAgainst();
        updatePaymentTotal();
    }

    function editPayment(ref) {
        var payment = payments.find(function (row) {
            return row.ref === ref;
        });

        if (!payment) return;

        el('paymentRef').value = payment.ref;
        el('receiptDate').value = payment.receipt_date || '';
        el('paymentAgainst').value = payment.payment_against || 'overall';
        el('paymentNotes').value = payment.notes || '';

        updateAgainst();

        if (payment.payment_against === 'emi') {
            setInstallmentOptions(payment.target_installment_ref || '');
        } else {
            setInstallmentOptions('');
        }

        var detailMap = {};

        (payment.payment_details || []).forEach(function (row) {
            detailMap[String(row.payment_mode)] = row;
        });

        [1,2,3,4].forEach(function (mode) {
            var detail = detailMap[String(mode)] || {};

            setAccountOptions(
                mode,
                detail.account_id ? String(detail.account_id) : ''
            );

            var amount = document.querySelector(
                '.payment-amount[data-mode="' + mode + '"]'
            );

            if (amount) {
                amount.value = detail.amount
                    ? Number(detail.amount).toFixed(2)
                    : '';
            }

            if (mode === 2 || mode === 3) {
                var reference = document.querySelector(
                    '.payment-reference[data-mode="' + mode + '"]'
                );

                if (reference) {
                    reference.value = detail.reference_no || '';
                }
            }

            if (mode === 4) {
                el('chequeNo').value = detail.cheque_no || '';
                el('chequeDate').value = detail.cheque_date || '';
            }
        });

        el('savePaymentText').textContent = 'Update Payment';
        updatePaymentTotal();

        window.scrollTo({top:0,behavior:'smooth'});
    }

    function applyPaymentViewMode() {
        if (!paymentViewMode) {
            return;
        }

        el('studentHeading').textContent =
            'View Student Payment';

        el('savePaymentButton').hidden = true;
        el('clearPaymentButton').hidden = true;

        [
            'paymentStudentId',
            'paymentAdmissionId',
            'receiptDate',
            'paymentAgainst',
            'targetInstallment',
            'cashAccountId',
            'upiAccountId',
            'bankAccountId',
            'chequeAccountId',
            'chequeDate'
        ].forEach(function (id) {
            var control = el(id);

            if (control) {
                control.disabled = true;
            }
        });

        document.querySelectorAll(
            '.payment-amount,.payment-reference'
        ).forEach(function (control) {
            control.readOnly = true;
        });

        el('chequeNo').readOnly = true;
        el('paymentNotes').readOnly = true;

        document.querySelectorAll(
            '.edit-payment,.delete-payment'
        ).forEach(function (button) {
            button.hidden = true;
        });
    }

    function normalizeFields() {
        document.querySelectorAll('.payment-amount').forEach(function (input) {
            input.value = input.value.trim();
        });
    }

    function validateRows() {
        var total = paymentTotal();

        if (total <= 0.009) {
            showToast('Enter a Payment Amount.',{type:'danger',duration:4});
            return false;
        }

        var available = n(summary.balance);

        if (el('paymentRef').value) {
            var current = payments.find(function (row) {
                return row.ref === el('paymentRef').value;
            });

            if (current) {
                available += n(current.amount);
            }
        }

        if (
            el('paymentAgainst').value === 'overall' &&
            total > available + 0.009
        ) {
            showToast(
                'Payment cannot exceed Student outstanding balance.',
                {type:'danger',duration:4}
            );
            return false;
        }

        if (
            el('paymentAgainst').value === 'emi' &&
            !el('targetInstallment').value
        ) {
            showToast('Select a Particular EMI.',{type:'danger',duration:4});
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

        return true;
    }

    async function reloadData(refValue) {
        if (refValue) {
            admissionRef = refValue;
        }

        if (!admissionRef) {
            return;
        }

        var response = await App.api(
            'api/student-payments.php?' +
            new URLSearchParams({admission_ref:admissionRef}).toString()
        );

        actions = (response.data.allowed_actions || []).map(Number);
        admission = response.data.admission || {};
        summary = response.data.summary || {};
        installments = response.data.installments || [];
        payments = response.data.payments || [];
        accounts = response.data.accounts || [];
        today = response.data.today || today;

        renderHeader();
        renderSummary();

        setPaymentStudentOptions(String(admission.student_id || ''));

        setPaymentAdmissionOptions(
            admission.student_id || '',
            admission.id || ''
        );

        [1,2,3,4].forEach(function (mode) {
            var current = document.querySelector(
                '.payment-account[data-mode="' + mode + '"]'
            );

            setAccountOptions(mode,current ? current.value || '' : '');
        });

        renderInstallments();
        renderHistory();

        if (!has(2) && !has(3)) {
            el('savePaymentButton').hidden = true;
        } else {
            el('savePaymentButton').hidden = false;
        }
    }

    async function load() {
        try {
            paymentStudentSelect = GlobalSelect.init(
                '#paymentStudentId',
                {placeholder:'Search Student'}
            );

            paymentAdmissionSelect = GlobalSelect.init(
                '#paymentAdmissionId',
                {placeholder:'Select Admission'}
            );

            var optionResponse = await App.api(
                'api/student-payments.php?options=1'
            );

            paymentStudents = optionResponse.data.students || [];
            paymentAdmissions = optionResponse.data.admissions || [];
            today = optionResponse.data.today || '';

            setPaymentStudentOptions('');
            setPaymentAdmissionOptions('', '');

            accountSelects = {
                1:GlobalSelect.init('#cashAccountId',{placeholder:'Select Cash Account'}),
                2:GlobalSelect.init('#upiAccountId',{placeholder:'Select Bank Account'}),
                3:GlobalSelect.init('#bankAccountId',{placeholder:'Select Bank Account'}),
                4:GlobalSelect.init('#chequeAccountId',{placeholder:'Select Bank Account'})
            };

            installmentSelect = GlobalSelect.init(
                '#targetInstallment',
                {placeholder:'Select EMI'}
            );

            if (admissionRef) {
                await reloadData(admissionRef);
                clearPayment();

                if (startPaymentRef) {
                    editPayment(startPaymentRef);
                    startPaymentRef = '';

                    if (paymentViewMode) {
                        applyPaymentViewMode();
                    }
                }
            } else {
                clearPayment();
                el('studentSubheading').textContent =
                    'Select Student and Admission to start Payment.';
                el('savePaymentButton').disabled = true;
            }

            el('paymentStudentId').addEventListener('change',function () {
                if (paymentViewMode) {
                    return;
                }

                admissionRef = '';
                setPaymentAdmissionOptions(this.value || '', '');
                clearPayment();
                el('savePaymentButton').disabled = true;
            });

            el('paymentAdmissionId').addEventListener('change',async function () {
                if (paymentViewMode) {
                    return;
                }

                var selected = selectedPaymentAdmission();

                if (!selected) {
                    admissionRef = '';
                    clearPayment();
                    el('savePaymentButton').disabled = true;
                    return;
                }

                try {
                    await reloadData(selected.ref);
                    clearPayment();
                    el('savePaymentButton').disabled = false;
                } catch (error) {
                    showError(
                        error,
                        'Unable to load the selected Student Admission.'
                    );
                    el('savePaymentButton').disabled = true;
                }
            });

            document.querySelectorAll('.payment-amount').forEach(function (input) {
                input.addEventListener('input',updatePaymentTotal);
            });

            el('paymentAgainst').addEventListener('change',updateAgainst);

            el('clearPaymentButton').addEventListener('click',clearPayment);

            el('paymentHistoryBody').addEventListener('click',async function (event) {
                var edit = event.target.closest('.edit-payment');

                if (edit) {
                    editPayment(edit.dataset.ref);
                    return;
                }

                var del = event.target.closest('.delete-payment');

                if (!del) return;

                if (!window.confirm(
                    'Delete this Student Payment? EMI allocations will be recalculated automatically.'
                )) {
                    return;
                }

                del.disabled = true;

                try {
                    var response = await App.api('api/college-fee-payments.php',{
                        method:'POST',
                        body:{
                            action:'delete',
                            receipt_ref:del.dataset.ref
                        }
                    });

                    showToast(
                        response.message || 'Student Payment deleted and balances recalculated successfully.',
                        {type:'success',duration:2}
                    );

                    clearPayment();
                    await reloadData();
                } catch (error) {
                    del.disabled = false;
                    showError(error,'Unable to delete Student Payment.');
                }
            });

            form.addEventListener('submit',async function (event) {
                event.preventDefault();

                if (saving || paymentViewMode) return;

                if (!admissionRef) {
                    showToast(
                        'Select Student and Admission first.',
                        {type:'danger',duration:4}
                    );
                    return;
                }

                if (window.Validation) {
                    Validation.clearForm(form);
                    normalizeFields();

                    if (!Validation.validateForm(form)) {
                        showToast(
                            'Please complete the required Payment fields.',
                            {type:'danger',duration:4}
                        );
                        return;
                    }
                } else {
                    normalizeFields();
                }

                if (!validateRows()) return;

                var isEdit = el('paymentRef').value !== '';

                if (isEdit && !has(3)) {
                    showToast(
                        'You do not have Update permission for Student Payment.',
                        {type:'danger',duration:4}
                    );
                    return;
                }

                if (!isEdit && !has(2)) {
                    showToast(
                        'You do not have Create permission for Student Payment.',
                        {type:'danger',duration:4}
                    );
                    return;
                }

                saving = true;
                el('savePaymentButton').disabled = true;

                try {
                    var response = await App.api('api/college-fee-payments.php',{
                        method:'POST',
                        body:{
                            action:'save',
                            receipt_type:'payment',
                            admission_ref:admissionRef,
                            receipt_ref:el('paymentRef').value || '',
                            receipt_date:el('receiptDate').value,
                            payment_against:el('paymentAgainst').value,
                            target_installment_ref:
                                el('targetInstallment').value || '',
                            notes:el('paymentNotes').value.trim(),
                            payment_rows:paymentRows()
                        }
                    });

                    showToast(
                        response.message || 'Student Payment saved successfully.',
                        {type:'success',duration:2}
                    );

                    clearPayment();
                    await reloadData();
                } catch (error) {
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

                    showError(error,'Unable to save Student Payment.');
                } finally {
                    saving = false;
                    el('savePaymentButton').disabled = false;
                }
            });

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            showError(error,'Unable to load Student Payment.');
            el('savePaymentButton').hidden = true;
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
