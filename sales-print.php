<?php
declare(strict_types=1);

/**
 * AMIRTHAM Sales Print - direct FPDF through an authenticated POST.
 *
 * Sales List and Sales POS can retain their existing links:
 * sales-print.php?ref=<encrypted sale ref>
 * The GET response is a data-free bridge. It posts the selected reference and
 * the existing Bearer token as form fields, then PHP streams the PDF directly.
 * No Blob, base64 payload, token in URL, extra JS/CSS, or SQL migration.
 */

$httpMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($httpMethod === 'GET') {
    require_once __DIR__ . '/include/web-config.php';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');

    $bridgeRef = trim((string)($_GET['ref'] ?? ''));
    if ($bridgeRef === '' || strlen($bridgeRef) > 2048) {
        http_response_code(400);
        exit('Sales reference is required. Open Print from Sales List.');
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Preparing Sales PDF</title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body>
    <p id="salesPrintStatus" role="status">Preparing the selected Sales PDF…</p>
    <script>
    (function () {
        "use strict";
        var status = document.getElementById("salesPrintStatus");
        var ref = <?php echo json_encode($bridgeRef, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var token = "";
        try {
            if (window.App && typeof App.getToken === "function") {
                token = String(App.getToken() || "");
            }
            if (!token && window.AppRuntime && typeof AppRuntime.key === "function") {
                token = String(localStorage.getItem(AppRuntime.key("auth_token")) || "");
            }
        } catch (error) {
            token = "";
        }
        if (!token) {
            status.textContent = "Login required. Please sign in to the ERP and print again.";
            var link = document.createElement("a");
            link.href = "login.php";
            link.textContent = "Go to Login";
            document.body.appendChild(link);
            return;
        }

        // A normal link does not send the Authorization header. Like the
        // existing Community College receipt, submit it in a POST body.
        var form = document.createElement("form");
        form.method = "POST";
        form.action = window.location.pathname;
        form.style.display = "none";
        function field(name, value) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        field("ref", ref);
        field("auth_token", token);
        document.body.appendChild(form);
        form.submit();
    })();
    </script>
</body>
</html>
    <?php
    exit;
}

if ($httpMethod !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Unsupported Sales Print request.');
}

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

// Accept only a valid-looking token from the POST body, never from the URL.
// This mirrors the existing student-fee-receipt.php browser POST architecture.
$postedToken = trim((string)($_POST['auth_token'] ?? ''));
if ($postedToken !== '' && empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (strlen($postedToken) <= 4096 && !preg_match('/[\x00-\x20\x7f]/', $postedToken)) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $postedToken;
    }
}
unset($_POST['auth_token'], $postedToken);

require_once __DIR__ . '/include/bootstrap.php';

// Always authorize on the server, regardless of any client-side button state.
$printAccess = require_permission('sales-list.php', ACTION_PRINT);
$printUser = $printAccess['user'];
$printBranchId = (int)($printUser['branch_id'] ?? 0);
if ((int)($printUser['role_type'] ?? 0) === 2 || $printBranchId < 1) {
    http_response_code(403);
    exit('A tenant branch is required to print a sales document.');
}

if (!defined('APP_ROOT')) define('APP_ROOT', __DIR__);

// The project's Composer autoloader may already have loaded FPDF.
if (!class_exists('FPDF')) {
    foreach ([
        APP_ROOT . '/vendor/setasign/fpdf/fpdf.php',
        APP_ROOT . '/vendor/fpdf/fpdf.php',
        APP_ROOT . '/vendor/fpdf.php',
    ] as $fpdfPath) {
        if (is_file($fpdfPath)) {
            require_once $fpdfPath;
            if (class_exists('FPDF')) break;
        }
    }
}
if (!class_exists('FPDF')) {
    http_response_code(500);
    exit('FPDF is not available. Check the project vendor installation.');
}

/* -------------------------------------------------------------------------- */

/* Helpers                                                                     */

/* -------------------------------------------------------------------------- */

function inv_date(?string $date): string

{

    if (!$date || $date === '0000-00-00') {

        return '-';

    }

    $ts = strtotime($date);

    return $ts ? date('d-m-Y', $ts) : '-';

}

function inv_payment_status(int $status): string

{

    return match ($status) {

        3 => 'Paid',

        2 => 'Partially Paid',

        default => 'Unpaid',

    };

}

function inv_qty($value): string

{

    $v = number_format((float) $value, 3, '.', '');

    return rtrim(rtrim($v, '0'), '.');

}

function inv_percent($value): string

{

    $v = number_format((float) $value, 2, '.', '');

    return rtrim(rtrim($v, '0'), '.') . '%';

}

function inv_setting(int $branchId, string $key, string $fallback = ''): string

{

    $value = app_setting($key, $fallback, $branchId);

    return trim((string) $value);

}

function inv_asset_path(string $storedPath): string

{

    $storedPath = trim(str_replace('\\', '/', $storedPath));

    if ($storedPath === '') {

        return '';

    }

    /* FPDF should use a local filesystem path. */

    if (preg_match('~^(?:https?:)?//~i', $storedPath) || strpos($storedPath, 'data:') === 0) {

        return '';

    }

    $uploadBase = trim((string) env_value('UPLOAD_PATH', ''));

    if ($uploadBase === '') {

        $uploadBase = APP_ROOT . '/uploads';

    }

    if (strpos($storedPath, 'uploads/') === 0) {

        $relative = substr($storedPath, strlen('uploads/'));

        $candidate = rtrim($uploadBase, '/\\\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return is_file($candidate) ? $candidate : '';

    }

    $candidate = APP_ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR);

    return is_file($candidate) ? $candidate : '';

}

function inv_address_from_settings(int $branchId, string $branchName): string

{

    $parts = array_filter([

        inv_setting($branchId, 'address_line_1'),

        inv_setting($branchId, 'address_line_2'),

        trim(implode(', ', array_filter([

            inv_setting($branchId, 'city'),

            inv_setting($branchId, 'state'),

        ]))),

        trim(implode(' - ', array_filter([

            inv_setting($branchId, 'pincode'),

            inv_setting($branchId, 'country'),

        ]))),

    ], static fn($v) => trim((string) $v) !== '');

    if (!$parts) {

        return $branchName !== '' ? $branchName : '-';

    }

    return implode("\n", $parts);

}

function inv_under_100(int $n): string

{

    $ones = [

        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',

        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',

        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',

        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',

    ];

    $tens = [

        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',

        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',

    ];

    if ($n < 20) {

        return $ones[$n] ?? '';

    }

    $t = intdiv($n, 10) * 10;

    return trim(($tens[$t] ?? '') . ' ' . ($ones[$n % 10] ?? ''));

}

function inv_under_1000(int $n): string

{

    $out = [];

    if ($n >= 100) {

        $out[] = inv_under_100(intdiv($n, 100)) . ' Hundred';

        $n %= 100;

    }

    if ($n > 0) {

        $out[] = inv_under_100($n);

    }

    return trim(implode(' ', $out));

}

function inv_amount_words(float $amount): string

{

    $amount = round($amount, 2);

    $rupees = (int) floor($amount);

    $paise = (int) round(($amount - $rupees) * 100);

    if ($rupees === 0) {

        $words = 'Zero';

    } else {

        $parts = [];

        $crore = intdiv($rupees, 10000000);

        if ($crore > 0) {

            $parts[] = inv_under_1000($crore) . ' Crore';

            $rupees %= 10000000;

        }

        $lakh = intdiv($rupees, 100000);

        if ($lakh > 0) {

            $parts[] = inv_under_1000($lakh) . ' Lakh';

            $rupees %= 100000;

        }

        $thousand = intdiv($rupees, 1000);

        if ($thousand > 0) {

            $parts[] = inv_under_1000($thousand) . ' Thousand';

            $rupees %= 1000;

        }

        if ($rupees > 0) {

            $parts[] = inv_under_1000($rupees);

        }

        $words = implode(' ', $parts);

    }

    $out = 'Rupees ' . trim($words);

    if ($paise > 0) {

        $out .= ' and ' . inv_under_100($paise) . ' Paise';

    }

    return $out . ' Only';

}

/* -------------------------------------------------------------------------- */

/* Resolve sale reference                                                      */

/* -------------------------------------------------------------------------- */

$ref = trim((string)($_POST['ref'] ?? ''));
if ($ref === '') {
    http_response_code(400);
    exit('Sales reference is required.');
}
try {
    $saleId = (int)decryptReference($ref, 'sale');
} catch (Throwable $exception) {
    http_response_code(422);
    exit('Invalid Sales reference.');
}
if ($saleId < 1) {
    http_response_code(422);
    exit('Invalid Sales reference.');
}

/* -------------------------------------------------------------------------- */

/* Sale header + customer + branch + company                                  */

/* -------------------------------------------------------------------------- */

$stmt = db()->prepare(

    "SELECT

        s.*,

        c.customer_code,

        c.customer_name AS current_customer_name,

        c.mobile AS customer_mobile,

        c.email AS customer_email,

        c.gstin AS current_customer_gstin,

        c.address AS customer_address,

        b.branch_name,

        b.branch_code,

        b.state_code AS current_branch_state_code,

        co.company_name,

        co.company_code,

        co.email AS company_email,

        co.mobile AS company_mobile

     FROM food_sales s

     LEFT JOIN food_customers c

        ON c.id=s.customer_id

       AND c.branch_id=s.branch_id

     INNER JOIN branches b

        ON b.id=s.branch_id

     INNER JOIN companies co

        ON co.id=b.company_id

     WHERE s.id=:sale_id
       AND s.branch_id=:branch_id
       AND s.status=1

     LIMIT 1"

);

$stmt->execute([':sale_id' => $saleId, ':branch_id' => $printBranchId]);

$sale = $stmt->fetch();

if (!$sale) {

    http_response_code(404);

    exit('Sales invoice not found.');

}

$documentTitles = [

    1 => 'QUOTATION',

    2 => 'PROFORMA BILL',

    3 => 'SALES BILL',

    4 => 'FINAL INVOICE',

];

$documentTitle = $documentTitles[(int) $sale['document_type']] ?? 'SALES DOCUMENT';
$isFinal = (int)$sale['document_type'] === 4;

$branchId = (int) $sale['branch_id'];

/* -------------------------------------------------------------------------- */

/* Sale items                                                                  */

/* -------------------------------------------------------------------------- */

$itemStmt = db()->prepare(

    "SELECT

        si.id,

        si.product_id,

        si.source_purchase_id,

        si.selected_unit_id,

        si.primary_unit_id,

        si.secondary_unit_id,

        si.primary_quantity,

        si.secondary_quantity,

        si.quantity,

        si.base_quantity,

        si.unit_price,

        si.discount_amount,

        si.tax_rate,

        si.cgst_amount,

        si.sgst_amount,

        si.igst_amount,

        si.line_total,

        p.product_code,

        p.product_name,

        h.hsn_code,

        u.unit_name,

        u.unit_symbol,

        pur.purchase_no,

        pur.batch_number

     FROM food_sale_items si

     INNER JOIN food_products p

        ON p.id=si.product_id

     LEFT JOIN hsn_master h

        ON h.id=si.hsn_id

     LEFT JOIN food_units u

        ON u.id=si.selected_unit_id

     LEFT JOIN food_purchases pur

        ON pur.id=si.source_purchase_id

     WHERE si.sale_id=:sale_id

       AND si.branch_id=:branch_id

       AND si.status=1

     ORDER BY si.id"

);

$itemStmt->execute([

    ':sale_id' => $saleId,

    ':branch_id' => $branchId,

]);

$dbItems = $itemStmt->fetchAll();

/* -------------------------------------------------------------------------- */

/* Current split-payment model: one header + detail rows                       */

/* -------------------------------------------------------------------------- */

$paymentStmt = db()->prepare(

    "SELECT

        d.id,

        cp.id AS customer_payment_id,

        cp.payment_no,

        cp.payment_date,

        d.account_id,

        d.amount,

        LOWER(pm.method_code) AS payment_mode,

        pm.method_name,

        d.payment_reference,

        d.cheque_no,

        d.cheque_date,

        a.account_name,

        a.account_type,

        a.bank_name,

        a.account_number,

        a.ifsc_code,

        a.upi_id

     FROM food_customer_payments cp

     INNER JOIN food_customer_payment_details d

        ON d.customer_payment_id=cp.id

       AND d.branch_id=cp.branch_id

       AND d.status=1

     INNER JOIN food_payment_methods pm

        ON pm.id=d.payment_method_id

       AND pm.branch_id=d.branch_id

       AND pm.status=1

     LEFT JOIN accounts a

        ON a.id=d.account_id

       AND a.branch_id=d.branch_id

     WHERE cp.source_sale_id=:sale_id

       AND cp.branch_id=:branch_id

       AND cp.status=1

       AND cp.posting_status=1

     ORDER BY pm.sort_order,pm.id,d.id"

);

$paymentStmt->execute([

    ':sale_id' => $saleId,

    ':branch_id' => $branchId,

]);

$dbPayments = $paymentStmt->fetchAll();

/* Compatibility with invoices saved by older POS versions. */

if (!$dbPayments) {

    $legacyStmt = db()->prepare(

        "SELECT

            cp.id,

            cp.id AS customer_payment_id,

            cp.payment_no,

            cp.payment_date,

            cp.account_id,

            cp.amount,

            LOWER(cp.payment_mode) AS payment_mode,

            cp.payment_mode AS method_name,

            cp.payment_reference,

            NULL AS cheque_no,

            NULL AS cheque_date,

            a.account_name,

            a.account_type,

            a.bank_name,

            a.account_number,

            a.ifsc_code,

            a.upi_id

         FROM food_customer_payments cp

         LEFT JOIN accounts a

            ON a.id=cp.account_id

           AND a.branch_id=cp.branch_id

         WHERE cp.source_sale_id=:sale_id

           AND cp.branch_id=:branch_id

           AND cp.status=1

           AND cp.posting_status=1

           AND cp.amount>0

           AND cp.account_id IS NOT NULL

           AND cp.payment_mode IS NOT NULL

         ORDER BY cp.id"

    );

    $legacyStmt->execute([

        ':sale_id' => $saleId,

        ':branch_id' => $branchId,

    ]);

    $dbPayments = $legacyStmt->fetchAll();

}

/* POS-owned Customer Credit. */

$headerStmt = db()->prepare(

    "SELECT id,credit_applied

     FROM food_customer_payments

     WHERE branch_id=:branch_id

       AND source_sale_id=:sale_id

       AND posting_status=1

       AND status=1

     ORDER BY id

     LIMIT 1"

);

$headerStmt->execute([

    ':branch_id' => $branchId,

    ':sale_id' => $saleId,

]);

$posHeader = $headerStmt->fetch() ?: [];

$creditApplied = round((float) ($posHeader['credit_applied'] ?? 0), 2);

/* Later Customer Payments allocated to this invoice. */

$externalStmt = db()->prepare(

    "SELECT

        p.payment_date,

        p.payment_no,

        p.payment_reference,

        a.allocated_amount,

        a.credit_amount,

        a.discount_amount

     FROM food_customer_payment_allocations a

     INNER JOIN food_customer_payments p

        ON p.id=a.customer_payment_id

       AND p.branch_id=a.branch_id

       AND p.status=1

       AND p.posting_status=1

     WHERE a.sale_id=:sale_id

       AND a.branch_id=:branch_id

       AND a.status=1

       AND (p.source_sale_id IS NULL OR p.source_sale_id<>:sale_id_compare)

     ORDER BY p.payment_date,p.id,a.id"

);

$externalStmt->execute([

    ':sale_id' => $saleId,

    ':branch_id' => $branchId,

    ':sale_id_compare' => $saleId,

]);

$externalPayments = $externalStmt->fetchAll();

/* -------------------------------------------------------------------------- */

/* Settings                                                                    */

/* -------------------------------------------------------------------------- */

$businessName = inv_setting($branchId, 'business_name', (string) $sale['company_name']);

$companyLogo = inv_asset_path(inv_setting($branchId, 'company_logo'));

$digitalSignature = inv_asset_path(inv_setting($branchId, 'digital_signature'));

$companyAddress = inv_address_from_settings($branchId, (string) $sale['branch_name']);

$gstNumber = inv_setting($branchId, 'gst_number');

$panNumber = inv_setting($branchId, 'pan_number');

$businessEmail = inv_setting($branchId, 'business_email', (string) ($sale['company_email'] ?? ''));

$businessMobile = inv_setting($branchId, 'business_mobile', (string) ($sale['company_mobile'] ?? ''));

$authorizedSignatory = inv_setting($branchId, 'authorized_signatory');

$invoiceFooter = inv_setting($branchId, 'invoice_footer');

$termsSetting = inv_setting($branchId, 'terms_conditions');

/* -------------------------------------------------------------------------- */

/* Build printable data                                                        */

/* -------------------------------------------------------------------------- */

$customerName = trim((string) ($sale['customer_name_snapshot'] ?? ''));

if ($customerName === '') {

    $customerName = trim((string) ($sale['current_customer_name'] ?? ''));

}

if ($customerName === '') {

    $customerName = 'Walk-in Customer';

}

$customerGstin = trim((string) ($sale['customer_gstin_snapshot'] ?? ''));

if ($customerGstin === '') {

    $customerGstin = trim((string) ($sale['current_customer_gstin'] ?? ''));

}

$company = [

    'name' => $businessName !== '' ? strtoupper($businessName) : strtoupper((string) $sale['company_name']),

    'logo' => $companyLogo,

    'signature' => $digitalSignature,

    'address' => $companyAddress,

    'gstin' => $gstNumber,

    'pan' => $panNumber,

    'email' => $businessEmail,

    'mobile' => $businessMobile,

    'authorized_signatory' => $authorizedSignatory,

];

$invoice = [

    'document_title' => $documentTitle,

    'invoice_no' => (string) $sale['sales_no'],

    'invoice_date' => inv_date($sale['invoice_date']),

    'due_date' => inv_date($sale['due_date']),

    'payment_status' => $isFinal ? inv_payment_status((int)$sale['payment_status']) : 'Document Only',

];

$customer = [

    'code' => trim((string) ($sale['customer_code'] ?? '')) ?: '-',

    'name' => $customerName,

    'mobile' => trim((string) ($sale['customer_mobile'] ?? '')) ?: '-',

    'gstin' => $customerGstin !== '' ? $customerGstin : '-',

    'billing_address' => trim((string) ($sale['customer_address'] ?? '')) ?: '-',

];

$items = [];

foreach ($dbItems as $row) {

    $items[] = [

        'product' => (string) $row['product_name'],

        'hsn' => trim((string) ($row['hsn_code'] ?? '')) ?: '-',

        'batch' => trim((string) ($row['batch_number'] ?? '')) ?: '-',

        'qty' => inv_qty($row['quantity']),

        'unit' => trim((string) ($row['unit_symbol'] ?? '')) ?: (trim((string) ($row['unit_name'] ?? '')) ?: '-'),

        'rate' => (float) $row['unit_price'],

        'discount' => (float) $row['discount_amount'],

        'tax' => inv_percent($row['tax_rate']),

        'amount' => (float) $row['line_total'],

    ];

}

$payments = [];

foreach ($dbPayments as $row) {

    $mode = trim((string) ($row['method_name'] ?? ''));

    if ($mode === '') {

        $mode = ucfirst((string) ($row['payment_mode'] ?? 'Payment'));

    }

    $reference = trim((string) ($row['payment_reference'] ?? ''));

    if ($reference === '' && !empty($row['cheque_no'])) {

        $reference = 'Cheque ' . trim((string) $row['cheque_no']);

    }

    if ($reference === '' && !empty($row['payment_no'])) {

        $reference = (string) $row['payment_no'];

    }

    $payments[] = [

        'mode' => $mode,

        'reference' => $reference !== '' ? $reference : '-',

        'amount' => (float) $row['amount'],

    ];

}

if ($creditApplied > 0) {

    $payments[] = [

        'mode' => 'Customer Credit',

        'reference' => 'CREDIT',

        'amount' => $creditApplied,

    ];

}

foreach ($externalPayments as $row) {

    $reference = trim((string) ($row['payment_reference'] ?? ''));

    if ($reference === '') {

        $reference = trim((string) ($row['payment_no'] ?? '')) ?: '-';

    }

    if ((float) $row['allocated_amount'] > 0) {

        $payments[] = [

            'mode' => 'Customer Payment',

            'reference' => $reference,

            'amount' => (float) $row['allocated_amount'],

        ];

    }

    if ((float) $row['credit_amount'] > 0) {

        $payments[] = [

            'mode' => 'Customer Credit',

            'reference' => $reference,

            'amount' => (float) $row['credit_amount'],

        ];

    }

    if ((float) $row['discount_amount'] > 0) {

        $payments[] = [

            'mode' => 'Settlement Discount',

            'reference' => $reference,

            'amount' => (float) $row['discount_amount'],

        ];

    }

}

if (!$isFinal) $payments = [];
if (!$payments) {

    $payments[] = [

        'mode' => $isFinal ? 'Unpaid' : 'Not applicable',

        'reference' => '-',

        'amount' => 0.0,

    ];

}

$summary = [

    'subtotal' => (float) $sale['subtotal'],

    'discount' => (float)$sale['discount_total'],
    'item_discount' => (float)$sale['item_discount_total'],
    'overall_discount' => (float)$sale['overall_discount_amount'],

    'taxable_amount' => (float) $sale['taxable_total'],

    'cgst' => (float) $sale['cgst_total'],

    'sgst' => (float) $sale['sgst_total'],

    'igst' => (float)$sale['igst_total'],
    'cess' => (float)$sale['cess_total'],

    'round_off' => (float) $sale['round_off'],

    'grand_total' => (float) $sale['grand_total'],

    'paid_amount' => $isFinal ? (float)$sale['paid_amount'] : 0.0,

    'balance' => $isFinal ? (float)$sale['balance_amount'] : 0.0,

    'amount_in_words' => inv_amount_words((float) $sale['grand_total']),

];

/* Use first bank used by the invoice; otherwise first active Bank account. */

$bank = [

    'bank_name' => '-',

    'account_no' => '-',

    'ifsc' => '-',

    'upi_id' => '-',

];

foreach ($dbPayments as $row) {

    if (!empty($row['bank_name']) || !empty($row['account_number']) || !empty($row['upi_id'])) {

        $bank = [

            'bank_name' => trim((string) ($row['bank_name'] ?? '')) ?: '-',

            'account_no' => trim((string) ($row['account_number'] ?? '')) ?: '-',

            'ifsc' => trim((string) ($row['ifsc_code'] ?? '')) ?: '-',

            'upi_id' => trim((string) ($row['upi_id'] ?? '')) ?: '-',

        ];

        break;

    }

}

if ($bank['bank_name'] === '-' && $bank['account_no'] === '-' && $bank['upi_id'] === '-') {

    $bankStmt = db()->prepare(

        "SELECT bank_name,account_number,ifsc_code,upi_id

         FROM accounts

         WHERE branch_id=:branch_id

           AND status=1

           AND account_type=2

         ORDER BY id

         LIMIT 1"

    );

    $bankStmt->execute([':branch_id' => $branchId]);

    $bankRow = $bankStmt->fetch();

    if ($bankRow) {

        $bank = [

            'bank_name' => trim((string) ($bankRow['bank_name'] ?? '')) ?: '-',

            'account_no' => trim((string) ($bankRow['account_number'] ?? '')) ?: '-',

            'ifsc' => trim((string) ($bankRow['ifsc_code'] ?? '')) ?: '-',

            'upi_id' => trim((string) ($bankRow['upi_id'] ?? '')) ?: '-',

        ];

    }

}

$notes = [];

if (trim((string) ($sale['notes'] ?? '')) !== '') {

    $notes[] = trim((string) $sale['notes']);

}

if ($invoiceFooter !== '') {

    $notes[] = $invoiceFooter;

}

if (!$notes) {

    $notes = [

        'Goods once sold cannot be returned.',

        'For any queries, contact our office.',

    ];

}

$terms = [];

if ($termsSetting !== '') {

    foreach (preg_split('/\r\n|\r|\n/', $termsSetting) ?: [] as $line) {

        $line = trim($line);

        if ($line !== '') {

            $terms[] = $line;

        }

    }

}

if (!$terms) {

    $terms = [

        'Subject to applicable jurisdiction.',

        'Payment to be made within due date.',

        'All disputes are subject to our terms.',

    ];

}

/* -------------------------------------------------------------------------- */

/* FPDF                                                                        */

/* -------------------------------------------------------------------------- */

class SalesInvoicePDF extends FPDF
{
    private const X = 7.0;
    private const W = 196.0;
    private const BOTTOM = 290.0;

    private function printable($value): string
    {
        $value = str_replace(["\r", "\t", '₹'], ['', ' ', 'Rs. '], (string)$value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
            if ($converted !== false) return $converted;
        }
        return preg_replace('/[^\x20-\x7E\xA0-\xFF\n]/', '', $value) ?? '';
    }

    private function fit($value, float $width, float $size = 8, string $style = ''): string
    {
        $text = preg_replace('/\s+/', ' ', $this->printable($value)) ?? '';
        $this->SetFont('Arial', $style, $size);
        if ($this->GetStringWidth($text) <= $width - 2) return $text;
        while ($text !== '' && $this->GetStringWidth($text . '...') > $width - 2) {
            $text = substr($text, 0, -1);
        }
        return rtrim($text) . '...';
    }

    private function cellAt(float $x, float $y, float $w, float $h, $text,
                            float $font = 8, string $style = '', string $align = 'L', $border = 0): void
    {
        $this->SetFont('Arial', $style, $font);
        $this->SetXY($x, $y);
        $this->Cell($w, $h, $this->fit($text, $w, $font, $style), $border, 0, $align);
    }

    private function blockTitle(float $x, float $y, float $w, string $title, float $h = 7): void
    {
        $this->SetFillColor(235, 235, 235);
        $this->Rect($x, $y, $w, $h, 'DF');
        $this->cellAt($x + 2.5, $y + 1, $w - 5, $h - 1, $title, 8, 'B');
    }

    private function labelValue(float $x, float $y, float $labelW, float $valueW, string $label, $value): void
    {
        $this->cellAt($x, $y, $labelW, 5, $label, 7.6, 'B');
        $this->cellAt($x + $labelW, $y, 3, 5, ':', 7.6);
        $this->cellAt($x + $labelW + 3, $y, $valueW, 5, $value, 7.6);
    }

    private function shortLines($value, float $w, int $maxLines = 2, float $font = 7.2): array
    {
        $text = trim($this->printable($value));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $this->SetFont('Arial', '', $font);
        $words = explode(' ', $text);
        $result = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            if ($line !== '' && $this->GetStringWidth($candidate) > $w - 1) {
                $result[] = $this->fit($line, $w, $font);
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') $result[] = $this->fit($line, $w, $font);
        if (count($result) > $maxLines) {
            $result = array_slice($result, 0, $maxLines);
            $result[$maxLines - 1] = $this->fit($result[$maxLines - 1] . '...', $w, $font);
        }
        return $result ?: ['-'];
    }

    private function money($value): string
    {
        return number_format((float)$value, 2, '.', ',');
    }

    private function safeImage($path, float $x, float $y, float $w, float $h): bool
    {
        if (!is_string($path) || !is_file($path)) return false;
        $type = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($type, ['jpg', 'jpeg', 'png'], true)) return false;
        try {
            $this->Image($path, $x, $y, $w, $h);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function startInvoicePage(array $company, array $invoice, array $customer, bool $continued = false): float
    {
        $this->AddPage();
        $this->SetAutoPageBreak(false);
        $this->SetDrawColor(30, 30, 30);
        $this->SetLineWidth(0.22);
        $x = self::X;
        $w = self::W;
        if ($continued) {
            $this->Rect($x, 8, $w, 18);
            $this->cellAt($x + 3, 9.5, 140, 7, $company['name'], 11, 'B');
            $this->cellAt($x + 3, 17, 140, 5, $invoice['document_title'] . ' - CONTINUED', 8, 'B');
            $this->cellAt($x + 145, 12, 47, 6, $invoice['invoice_no'], 9, 'B', 'R');
            return 29.0;
        }
        $this->Rect($x, 7, $w, 25);
        if (!$this->safeImage($company['logo'] ?? '', $x + 4, 9, 23, 20)) {
            $this->cellAt($x + 4, 16, 23, 5, 'LOGO', 7, '', 'C');
        }
        $this->cellAt($x + 31, 11, 158, 8, $company['name'], 14, 'B', 'C');
        $this->cellAt($x + 31, 21, 158, 6, $invoice['document_title'], 11, 'B', 'C');
        $this->Rect($x, 32, $w, 44);
        $this->Line($x + 98, 32, $x + 98, 76);
        $y = 34;
        foreach ([
            ['Customer Code', $customer['code']],
            ['Customer Name', $customer['name']],
            ['Mobile', $customer['mobile']],
            ['GSTIN', $customer['gstin']],
        ] as $pair) {
            $this->labelValue($x + 3, $y, 27, 63, $pair[0], $pair[1]);
            $y += 6;
        }
        $this->cellAt($x + 3, 58.2, 92, 4.7, 'Customer Billing Address:', 7.3, 'B');
        foreach ($this->shortLines($customer['billing_address'], 91, 2) as $i => $line) {
            $this->cellAt($x + 4, 64 + $i * 4.2, 90, 4.1, $line, 7.2);
        }
        $rx = $x + 98;
        $y = 34;
        foreach ([
            ['Document No', $invoice['invoice_no']],
            ['Date', $invoice['invoice_date']],
            ['Due Date', $invoice['due_date']],
            ['Payment Status', $invoice['payment_status']],
        ] as $pair) {
            $this->labelValue($rx + 3, $y, 26, 64, $pair[0], $pair[1]);
            $y += 6;
        }
        $this->cellAt($rx + 3, 58.2, 91, 4.7, 'Business Address:', 7.3, 'B');
        foreach ($this->shortLines($company['address'], 91, 2) as $i => $line) {
            $this->cellAt($rx + 4, 64 + $i * 4.2, 90, 4.1, $line, 7.2);
        }
        return 76.0;
    }

    private function tableColumns(): array
    {
        return [
            ['S.No', 11, 'C'], ['Product', 41, 'L'], ['HSN', 19, 'C'], ['Batch', 21, 'C'],
            ['Qty', 15, 'R'], ['Unit', 14, 'C'], ['Rate', 19, 'R'], ['Disc', 15, 'R'],
            ['Tax', 13, 'C'], ['Amount', 28, 'R'],
        ]; // Total: 196 mm.
    }

    private function itemHead(float $y): float
    {
        $x = self::X;
        $this->SetFillColor(235, 235, 235);
        foreach ($this->tableColumns() as $col) {
            $this->SetFont('Arial', 'B', 7.2);
            $this->SetXY($x, $y);
            $this->Cell($col[1], 8.5, $col[0], 1, 0, 'C', true);
            $x += $col[1];
        }
        return $y + 8.5;
    }

    private function itemRow(float $y, array $item, int $serial): float
    {
        $values = [
            $serial, $item['product'], $item['hsn'], $item['batch'], $item['qty'],
            $item['unit'], $this->money($item['rate']), $this->money($item['discount']),
            $item['tax'], $this->money($item['amount']),
        ];
        $x = self::X;
        foreach ($this->tableColumns() as $i => $col) {
            $this->cellAt($x, $y, $col[1], 7.2, $values[$i], 7.1, '', $col[2], 1);
            $x += $col[1];
        }
        return $y + 7.2;
    }

    private function extendTable(float $from, float $to): void
    {
        if ($to <= $from) return;
        $x = self::X;
        $this->Line($x, $from, $x, $to);
        foreach ($this->tableColumns() as $col) {
            $x += $col[1];
            $this->Line($x, $from, $x, $to);
        }
    }

    private function summaryHeight(int $shownPayments): float
    {
        return max(58.0, 9 + 7 + $shownPayments * 5.6 + 8);
    }

    private function summary(float $y, array $summary, array $payments, array $bank,
                             array $company, array $notes, array $terms): void
    {
        $x = self::X;
        $w1 = 65.33;
        $w2 = 65.33;
        $w3 = self::W - $w1 - $w2;
        $px = $x + $w1 + $w2;
        $shown = array_slice($payments, 0, 7);
        $height = $this->summaryHeight(count($shown));
        $this->Rect($x, $y, self::W, $height);
        $this->Line($x + $w1, $y, $x + $w1, $y + $height);
        $this->Line($px, $y, $px, $y + $height);
        $this->blockTitle($x, $y, $w1, 'Amount Summary', 8);
        $this->blockTitle($x + $w1, $y, $w2, 'Final Total', 8);
        $this->blockTitle($px, $y, $w3, 'Payment Details', 8);
        $sy = $y + 8.8;
        foreach ([
            ['Subtotal', $summary['subtotal']],
            ['Item Discount', $summary['item_discount']],
            ['Overall Discount', $summary['overall_discount']],
            ['Taxable Amount', $summary['taxable_amount']],
            ['CGST', $summary['cgst']], ['SGST', $summary['sgst']],
            ['IGST', $summary['igst']], ['Cess', $summary['cess']],
        ] as $line) {
            $this->cellAt($x + 2.5, $sy, 38, 5.7, $line[0], 7.0);
            $this->cellAt($x + 41, $sy, $w1 - 43, 5.7, $this->money($line[1]), 7.0, '', 'R');
            $sy += 5.65;
        }
        $fx = $x + $w1;
        $fy = $y + 10;
        foreach ([
            ['Round Off', $summary['round_off']],
            ['Grand Total', $summary['grand_total']],
            ['Paid Amount', $summary['paid_amount']],
            ['Balance', $summary['balance']],
        ] as $i => $line) {
            $bold = ($i === 1 || $i === 3) ? 'B' : '';
            $this->cellAt($fx + 3, $fy, 30, 7.5, $line[0], $i === 1 ? 8.5 : 7.6, $bold);
            $this->cellAt($fx + 33, $fy, $w2 - 36, 7.5, $this->money($line[1]), $i === 1 ? 8.5 : 7.6, $bold, 'R');
            if ($i === 0) $this->Line($fx + 3, $fy + 8, $fx + $w2 - 3, $fy + 8);
            $fy += ($i === 0 ? 12 : 10);
        }
        $py = $y + 8;
        $cols = [8, 19, 22, $w3 - 49];
        foreach (['#', 'Mode', 'Reference', 'Amount'] as $i => $label) {
            $cw = $cols[$i];
            $this->cellAt($px + array_sum(array_slice($cols, 0, $i)), $py, $cw, 7, $label, 6.6, 'B', 'C', 1);
        }
        $py += 7;
        foreach ($shown as $i => $payment) {
            foreach ([$i + 1, $payment['mode'], $payment['reference'], $this->money($payment['amount'])] as $j => $val) {
                $this->cellAt($px + array_sum(array_slice($cols, 0, $j)), $py,
                    $cols[$j], 5.6, $val, 6.6, '', $j === 3 ? 'R' : 'C', 1);
            }
            $py += 5.6;
        }
        $this->cellAt($px + 2, $y + $height - 7, 34, 5, 'Total Paid', 7.3, 'B');
        $this->cellAt($px + 36, $y + $height - 7, $w3 - 38, 5, $this->money($summary['paid_amount']), 7.3, 'B', 'R');
        $y += $height;
        $this->Rect($x, $y, self::W, 13);
        $this->blockTitle($x, $y, self::W, 'Amount in Words', 6.5);
        $this->cellAt($x + 3, $y + 6.6, self::W - 6, 5.5, $summary['amount_in_words'], 8);
        $y += 13;
        $this->Rect($x, $y, self::W, 25);
        $this->blockTitle($x, $y, self::W, 'Bank / Payment Information', 6.5);
        $this->labelValue($x + 3, $y + 7.5, 25, 59, 'Bank Name', $bank['bank_name']);
        $this->labelValue($x + 3, $y + 13.5, 25, 59, 'Account No', $bank['account_no']);
        $this->labelValue($x + 98, $y + 7.5, 22, 68, 'IFSC', $bank['ifsc']);
        $this->labelValue($x + 98, $y + 13.5, 22, 68, 'UPI ID', $bank['upi_id']);
        $y += 25;
        $footerH = max(30.0, self::BOTTOM - $y);
        $nW = 68.0; $tW = 67.0; $sW = self::W - $nW - $tW;
        $this->Rect($x, $y, self::W, $footerH);
        $this->Line($x + $nW, $y, $x + $nW, $y + $footerH);
        $this->Line($x + $nW + $tW, $y, $x + $nW + $tW, $y + $footerH);
        $this->blockTitle($x, $y, $nW, 'Notes', 6.5);
        $this->blockTitle($x + $nW, $y, $tW, 'Terms & Conditions', 6.5);
        $sx = $x + $nW + $tW;
        $this->blockTitle($sx, $y, $sW, 'Authorized Signatory', 6.5);
        foreach (array_slice($notes, 0, 2) as $i => $note) {
            foreach ($this->shortLines(($i + 1) . '. ' . $note, $nW - 8, 2, 6.6) as $lineNo => $line) {
                $this->cellAt($x + 3, $y + 7.3 + $i * 10 + $lineNo * 3.8, $nW - 6, 3.8, $line, 6.6);
            }
        }
        foreach (array_slice($terms, 0, 3) as $i => $term) {
            foreach ($this->shortLines(($i + 1) . '. ' . $term, $tW - 8, 1, 6.5) as $line) {
                $this->cellAt($x + $nW + 3, $y + 7.3 + $i * 6.6, $tW - 6, 4.2, $line, 6.5);
            }
        }
        $this->safeImage($company['signature'] ?? '', $sx + 11, $y + 7.8, $sW - 22, 10);
        $this->cellAt($sx + 4, $y + 19.5, $sW - 8, 4, $company['authorized_signatory'] ?? '', 7, '', 'C');
        $this->Line($sx + 8, $y + 25, $sx + $sW - 8, $y + 25);
    }

    private function remainingPayments(array $company, array $invoice, array $payments): void
    {
        $rows = array_slice($payments, 7);
        if (!$rows) return;
        $y = 0;
        foreach ($rows as $i => $row) {
            if ($y < 1 || $y + 7 > 283) {
                $this->AddPage();
                $this->Rect(self::X, 8, self::W, 18);
                $this->cellAt(10, 10, 140, 6, $company['name'], 11, 'B');
                $this->cellAt(10, 18, 125, 5, 'PAYMENT DETAILS - CONTINUED', 8, 'B');
                $this->cellAt(157, 12, 43, 6, $invoice['invoice_no'], 8, 'B', 'R');
                $y = 29;
                $this->blockTitle(self::X, $y, self::W, 'Payment details continued', 8);
                $y += 8;
                $xx = self::X;
                foreach ([[15, '#'], [55, 'Payment Mode'], [77, 'Reference'], [49, 'Amount']] as [$ww, $label]) {
                    $this->cellAt($xx, $y, $ww, 7, $label, 7.5, 'B', 'C', 1);
                    $xx += $ww;
                }
                $y += 7;
            }
            $xx = self::X;
            foreach ([[15, $i + 8], [55, $row['mode']], [77, $row['reference']], [49, $this->money($row['amount'])]] as $j => [$ww, $value]) {
                $this->cellAt($xx, $y, $ww, 7, $value, 7.5, '', $j === 3 ? 'R' : 'L', 1);
                $xx += $ww;
            }
            $y += 7;
        }
    }

    public function createInvoice($company, $invoice, $customer, $items, $summary, $payments, $bank, $notes, $terms): void
    {
        $this->SetMargins(7, 7, 7);
        $this->AliasNbPages();
        $y = $this->itemHead($this->startInvoicePage($company, $invoice, $customer));
        if (!$items) {
            $this->cellAt(self::X, $y, self::W, 7.2, 'No active products found.', 7.5, '', 'C', 1);
            $y += 7.2;
        }
        foreach ($items as $i => $item) {
            if ($y + 7.2 > 284) {
                $y = $this->itemHead($this->startInvoicePage($company, $invoice, $customer, true));
            }
            $y = $this->itemRow($y, $item, $i + 1);
        }
        $reserve = $this->summaryHeight(min(7, count($payments))) + 13 + 25 + 30;
        if ($y + $reserve > self::BOTTOM) {
            $y = $this->itemHead($this->startInvoicePage($company, $invoice, $customer, true));
        }
        $bottomOfTable = max($y, self::BOTTOM - $reserve);
        $this->extendTable($y, $bottomOfTable);
        $this->summary($bottomOfTable, $summary, $payments, $bank, $company, $notes, $terms);
        $this->remainingPayments($company, $invoice, $payments);
    }
}

/* -------------------------------------------------------------------------- */

/* Generate PDF                                                                */

/* -------------------------------------------------------------------------- */

$pdf = new SalesInvoicePDF('P', 'mm', 'A4');

$pdf->createInvoice(

    $company,

    $invoice,

    $customer,

    $items,

    $summary,

    $payments,

    $bank,

    $notes,

    $terms

);

$fileName = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $sale['sales_no']);

if (!$fileName) {

    $fileName = 'sales-invoice';

}

$pdf->Output('I', $fileName . '.pdf');
