<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Print Patient Bill';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>

<section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>

<div class="page-head" id="printActions">
    <div>
        <h1>Patient Bill</h1>
        <p>Consultation + Medicine + Treatment + optional Lab.</p>
    </div>

    <div class="buttons">
        <button
            class="btn btn-primary"
            id="printNowButton"
            type="button"
        >
            <i data-lucide="printer"></i>
            Print
        </button>

        <button
            class="btn gray"
            type="button"
            onclick="window.close()"
        >
            Close
        </button>
    </div>
</div>

<div class="card form-card">
    <div class="card-header">
        <div>
            <h2 id="companyName">Clinic</h2>
            <p id="branchName"></p>
        </div>

        <div>
            <strong id="billNumber"></strong>
            <div id="billDate"></div>
        </div>
    </div>

    <div class="card-body">

        <div class="form-row">
            <div class="field col-3">
                <label>Patient Code</label>
                <div id="patientCode">-</div>
            </div>

            <div class="field col-3">
                <label>Patient Name</label>
                <div id="patientName">-</div>
            </div>

            <div class="field col-2">
                <label>Mobile</label>
                <div id="patientMobile">-</div>
            </div>

            <div class="field col-2">
                <label>Age</label>
                <div id="patientAge">-</div>
            </div>

            <div class="field col-2">
                <label>Gender</label>
                <div id="patientGender">-</div>
            </div>
        </div>

        <div class="card-section-title">Bill Items</div>

        <div class="table-scroll">
            <table class="data-table" style="width:100%">
                <thead>
                <tr>
                    <th>S.No.</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
                </thead>

                <tbody id="itemRows"></tbody>
            </table>
        </div>

        <div class="card-section-title">Payment Details</div>

        <div class="table-scroll">
            <table class="data-table" style="width:100%">
                <thead>
                <tr>
                    <th>Mode</th>
                    <th>Account</th>
                    <th>Amount</th>
                    <th>Reference No</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody id="paymentRows"></tbody>
            </table>
        </div>

        <div class="form-row" style="margin-top:12px;">
            <div class="field col-6">
                <label>Notes</label>
                <div id="billNotes">-</div>
            </div>

            <div class="field col-6">
                <div class="form-row">
                    <div class="field col-6">
                        <label>Subtotal</label>
                        <div id="subtotal">0.00</div>
                    </div>

                    <div class="field col-6">
                        <label>Discount</label>
                        <div id="discount">0.00</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-6">
                        <label>Grand Total</label>
                        <strong id="grandTotal">0.00</strong>
                    </div>

                    <div class="field col-6">
                        <label>Paid</label>
                        <div id="paidAmount">0.00</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-6">
                        <label>Balance</label>
                        <strong id="balanceAmount">0.00</strong>
                    </div>

                    <div class="field col-6">
                        <label>Payment Status</label>
                        <div id="paymentStatus">Unpaid</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function(window,document){
    'use strict';

    var params =
        new URLSearchParams(
            window.location.search
        );

    var reference =
        params.get('ref') ||
        '';

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

    function typeLabel(type) {
        var map = {
            CONSULTATION:
                'Consultation',

            MEDICINE:
                'Medicine',

            TREATMENT:
                'Treatment',

            LAB:
                'Lab'
        };

        return (
            map[type] ||
            type ||
            ''
        );
    }

    async function load() {
        if (!reference) {
            showToast(
                'Bill reference is required.',
                {
                    type:'danger',
                    duration:3
                }
            );

            return;
        }

        try {
            var response =
                await App.api(
                    'api/bills.php?print=1&ref=' +
                    encodeURIComponent(
                        reference
                    )
                );

            var data =
                response.data ||
                {};

            var record =
                data.record ||
                {};

            var branch =
                data.branch ||
                {};

            document.getElementById(
                'companyName'
            ).textContent =
                branch.company_name ||
                'Clinic';

            document.getElementById(
                'branchName'
            ).textContent =
                branch.branch_name ||
                '';

            document.getElementById(
                'billNumber'
            ).textContent =
                record.bill_no ||
                '';

            document.getElementById(
                'billDate'
            ).textContent =
                record.bill_date ||
                '';

            document.getElementById(
                'patientCode'
            ).textContent =
                record.patient_code ||
                '-';

            document.getElementById(
                'patientName'
            ).textContent =
                record.patient_name ||
                '-';

            document.getElementById(
                'patientMobile'
            ).textContent =
                record.mobile ||
                '-';

            document.getElementById(
                'patientAge'
            ).textContent =
                record.age == null
                    ? '-'
                    : String(
                        record.age
                    );

            document.getElementById(
                'patientGender'
            ).textContent =
                record.gender ||
                '-';

            var tbody =
                document.getElementById(
                    'itemRows'
                );

            tbody.innerHTML = '';

            (
                Array.isArray(
                    record.items
                )
                    ? record.items
                    : []
            ).forEach(
                function(item,index) {
                    var tr =
                        document.createElement(
                            'tr'
                        );

                    tr.innerHTML =
                        '<td>' +
                        (index + 1) +
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

                    tbody.appendChild(tr);
                }
            );

            var paymentBody =
                document.getElementById(
                    'paymentRows'
                );

            paymentBody.innerHTML = '';

            (
                Array.isArray(
                    record.payments
                )
                    ? record.payments
                    : []
            ).forEach(
                function(item) {
                    var tr =
                        document.createElement(
                            'tr'
                        );

                    tr.innerHTML =
                        '<td>' +
                        esc(item.mode || '') +
                        '</td>' +
                        '<td>' +
                        esc(item.account_name || '-') +
                        '</td>' +
                        '<td>' +
                        esc(item.amount || '0.00') +
                        '</td>' +
                        '<td>' +
                        esc(item.reference_no || '-') +
                        '</td>' +
                        '<td>' +
                        esc(item.payment_date || '-') +
                        '</td>';

                    paymentBody.appendChild(tr);
                }
            );

            if (!paymentBody.children.length) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="5">No payment breakup</td>';
                paymentBody.appendChild(tr);
            }

            document.getElementById(
                'billNotes'
            ).textContent =
                record.notes ||
                '-';

            document.getElementById(
                'subtotal'
            ).textContent =
                record.subtotal ||
                '0.00';

            document.getElementById(
                'discount'
            ).textContent =
                record.discount_amount ||
                '0.00';

            document.getElementById(
                'grandTotal'
            ).textContent =
                record.grand_total ||
                '0.00';

            document.getElementById(
                'paidAmount'
            ).textContent =
                record.paid_amount ||
                '0.00';

            document.getElementById(
                'balanceAmount'
            ).textContent =
                record.balance_amount ||
                '0.00';

            document.getElementById(
                'paymentStatus'
            ).textContent =
                record.payment_status ||
                'Unpaid';

            if (
                window.lucide
            ) {
                window.lucide.createIcons();
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to load Bill.'
            );
        }
    }

    document.getElementById(
        'printNowButton'
    ).addEventListener(
        'click',
        function() {
            window.print();
        }
    );

    load();

})(window,document);
</script>

</section>

<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>

</body>
</html>
