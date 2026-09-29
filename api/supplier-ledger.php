<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) {
    define('ACTION_VIEW', 1);
}

const SUPPLIER_LEDGER_PERMISSION_PATH = 'supplier-ledger.php';

function supplier_ledger_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Supplier Ledger is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT
            b.id AS branch_id,
            b.company_id,
            b.branch_name,
            c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :branch_id
           AND b.status = 1
           AND c.status = 1
         LIMIT 1'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function supplier_ledger_optional_date($value, string $field): ?string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        (
            $errors !== false &&
            (
                (int)$errors['warning_count'] > 0 ||
                (int)$errors['error_count'] > 0
            )
        ) ||
        $date->format('Y-m-d') !== $value
    ) {
        json_error('Enter a valid date.', 422, [
            $field => 'Enter a valid date.',
        ]);
    }

    return $value;
}

function supplier_ledger_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Supplier reference is required.', 422);
    }

    try {
        $id = (int)decryptReference(trim($value), 'supplier');
    } catch (Throwable $exception) {
        json_error('Invalid Supplier reference.', 422);
    }

    if ($id < 1) {
        json_error('Invalid Supplier reference.', 422);
    }

    return $id;
}

function supplier_ledger_suppliers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT
            id,
            supplier_code,
            supplier_name,
            mobile,
            status
         FROM food_suppliers
         WHERE branch_id = :branch_id
           AND status = 1
         ORDER BY supplier_name ASC, supplier_code ASC, id ASC'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
    ]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference('supplier', (int)$row['id']),
            'supplier_code' => (string)$row['supplier_code'],
            'supplier_name' => (string)$row['supplier_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'status' => (int)$row['status'],
        ];
    }

    return $rows;
}

function supplier_ledger_supplier(
    PDO $pdo,
    int $branchId,
    int $supplierId
): array {
    $stmt = $pdo->prepare(
        'SELECT
            id,
            supplier_code,
            supplier_name,
            mobile,
            email,
            gstin,
            opening_balance,
            opening_balance_date,
            status
         FROM food_suppliers
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $supplierId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Supplier was not found in your branch.', 404);
    }

    return [
        'id' => (int)$row['id'],
        'ref' => encryptReference('supplier', (int)$row['id']),
        'supplier_code' => (string)$row['supplier_code'],
        'supplier_name' => (string)$row['supplier_name'],
        'mobile' => (string)($row['mobile'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'gstin' => (string)($row['gstin'] ?? ''),
        'opening_balance' => round((float)$row['opening_balance'], 2),
        'opening_balance_date' => $row['opening_balance_date'],
        'status' => (int)$row['status'],
    ];
}

function supplier_ledger_payment_mode_label(int $mode): string
{
    return match ($mode) {
        1 => 'Cash',
        2 => 'UPI',
        3 => 'Bank Transfer',
        4 => 'Cheque',
        default => 'Payment',
    };
}

function supplier_ledger_transactions(
    PDO $pdo,
    int $branchId,
    int $supplierId
): array {
    $transactions = [];

    /*
     * Posted Purchases.
     * Debit increases supplier payable.
     */
    $purchaseStmt = $pdo->prepare(
        'SELECT
            id,
            purchase_no,
            purchase_date,
            supplier_invoice_number,
            grand_total
         FROM food_purchases
         WHERE branch_id = :branch_id
           AND supplier_id = :supplier_id
           AND posting_status = 1
           AND status = 1
         ORDER BY purchase_date ASC, id ASC'
    );

    $purchaseStmt->execute([
        ':branch_id' => $branchId,
        ':supplier_id' => $supplierId,
    ]);

    foreach ($purchaseStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $transactions[] = [
            'date' => (string)$row['purchase_date'],
            'sort_order' => 10,
            'sort_id' => (int)$row['id'],
            'type_key' => 'purchase',
            'reference' => (string)($row['purchase_no'] ?? ''),
            'transaction_label' => 'Purchase',
            'description' =>
                trim((string)($row['supplier_invoice_number'] ?? '')) !== ''
                    ? 'Supplier Invoice: ' . (string)$row['supplier_invoice_number']
                    : 'Posted Purchase',
            'debit' => round((float)$row['grand_total'], 2),
            'credit' => 0.0,
            'running_balance' => 0.0,
        ];
    }

    /*
     * Purchase Payments.
     * Credit decreases supplier payable.
     * These belong to the Purchase date because the current schema
     * does not store a separate payment date in food_purchase_payments.
     */
    $purchasePaymentStmt = $pdo->prepare(
        'SELECT
            pp.id,
            pp.amount,
            pp.payment_mode,
            pp.reference_no,
            p.purchase_id,
            p.purchase_no,
            p.purchase_date,
            a.account_name
         FROM (
            SELECT
                fp.id,
                fp.purchase_id,
                fp.amount,
                fp.payment_mode,
                fp.reference_no,
                fp.account_id
            FROM food_purchase_payments fp
            WHERE fp.branch_id = :branch_id
              AND fp.status = 1
         ) pp
         INNER JOIN (
            SELECT
                id AS purchase_id,
                purchase_no,
                purchase_date,
                supplier_id
            FROM food_purchases
            WHERE branch_id = :purchase_branch_id
              AND supplier_id = :supplier_id
              AND posting_status = 1
              AND status = 1
         ) p ON p.purchase_id = pp.purchase_id
         LEFT JOIN accounts a
           ON a.id = pp.account_id
          AND a.branch_id = :account_branch_id
         ORDER BY p.purchase_date ASC, pp.id ASC'
    );

    $purchasePaymentStmt->execute([
        ':branch_id' => $branchId,
        ':purchase_branch_id' => $branchId,
        ':supplier_id' => $supplierId,
        ':account_branch_id' => $branchId,
    ]);

    foreach ($purchasePaymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $parts = [
            supplier_ledger_payment_mode_label((int)$row['payment_mode']),
        ];

        if (!empty($row['account_name'])) {
            $parts[] = (string)$row['account_name'];
        }

        if (!empty($row['reference_no'])) {
            $parts[] = 'Ref: ' . (string)$row['reference_no'];
        }

        $transactions[] = [
            'date' => (string)$row['purchase_date'],
            'sort_order' => 20,
            'sort_id' => (int)$row['id'],
            'type_key' => 'purchase_payment',
            'reference' => (string)($row['purchase_no'] ?? ''),
            'transaction_label' => 'Purchase Payment',
            'description' => implode(' · ', $parts),
            'debit' => 0.0,
            'credit' => round((float)$row['amount'], 2),
            'running_balance' => 0.0,
        ];
    }

    /*
     * Posted Purchase Returns.
     * Credit decreases supplier payable.
     */
    $returnStmt = $pdo->prepare(
        'SELECT
            id,
            return_no,
            return_date,
            purchase_id,
            reason,
            grand_total
         FROM food_purchase_returns
         WHERE branch_id = :branch_id
           AND supplier_id = :supplier_id
           AND posting_status = 1
           AND status = 1
         ORDER BY return_date ASC, id ASC'
    );

    $returnStmt->execute([
        ':branch_id' => $branchId,
        ':supplier_id' => $supplierId,
    ]);

    foreach ($returnStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $description = trim((string)($row['reason'] ?? ''));

        if ($description === '') {
            $description = 'Posted Purchase Return';
        }

        $transactions[] = [
            'date' => (string)$row['return_date'],
            'sort_order' => 30,
            'sort_id' => (int)$row['id'],
            'type_key' => 'purchase_return',
            'reference' => (string)($row['return_no'] ?? ''),
            'transaction_label' => 'Purchase Return',
            'description' => $description,
            'debit' => 0.0,
            'credit' => round((float)$row['grand_total'], 2),
            'running_balance' => 0.0,
        ];
    }

    /*
     * Posted Supplier Payments.
     * Credit decreases supplier payable.
     */
    $supplierPaymentStmt = $pdo->prepare(
        'SELECT
            id,
            payment_no,
            payment_type,
            payment_date,
            amount,
            payment_mode,
            payment_reference,
            notes
         FROM food_supplier_payments
         WHERE branch_id = :branch_id
           AND supplier_id = :supplier_id
           AND posting_status = 1
           AND status = 1
           AND reversed_at IS NULL
         ORDER BY payment_date ASC, id ASC'
    );

    $supplierPaymentStmt->execute([
        ':branch_id' => $branchId,
        ':supplier_id' => $supplierId,
    ]);

    foreach ($supplierPaymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $typeLabel = match ((int)$row['payment_type']) {
            1 => 'Opening Balance Payment',
            2 => 'Supplier Payment',
            3 => 'Invoice Payment',
            default => 'Supplier Payment',
        };

        $parts = [];

        if (!empty($row['payment_mode'])) {
            $parts[] = (string)$row['payment_mode'];
        }

        if (!empty($row['payment_reference'])) {
            $parts[] = 'Ref: ' . (string)$row['payment_reference'];
        }

        if (!empty($row['notes'])) {
            $parts[] = (string)$row['notes'];
        }

        $transactions[] = [
            'date' => (string)$row['payment_date'],
            'sort_order' => 40,
            'sort_id' => (int)$row['id'],
            'type_key' => 'supplier_payment',
            'reference' => (string)($row['payment_no'] ?? ''),
            'transaction_label' => $typeLabel,
            'description' => $parts !== [] ? implode(' · ', $parts) : 'Supplier Payment',
            'debit' => 0.0,
            'credit' => round((float)$row['amount'], 2),
            'running_balance' => 0.0,
        ];
    }

    usort(
        $transactions,
        static function (array $a, array $b): int {
            $dateCompare = strcmp($a['date'], $b['date']);

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $sortCompare = $a['sort_order'] <=> $b['sort_order'];

            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return $a['sort_id'] <=> $b['sort_id'];
        }
    );

    return $transactions;
}

$method = request_method();

if ($method !== 'GET') {
    json_error('Method not allowed.', 405);
}

$access = require_permission(
    SUPPLIER_LEDGER_PERMISSION_PATH,
    ACTION_VIEW
);

$context = supplier_ledger_context($access['user']);
$branchId = (int)$context['branch_id'];
$pdo = db();

$suppliers = supplier_ledger_suppliers($branchId);

if (isset($_GET['options'])) {
    json_success('Supplier Ledger options loaded.', [
        'suppliers' => $suppliers,
        'allowed_actions' => $access['actions'],
    ]);
}

if (!isset($_GET['datatable'])) {
    json_error('Unsupported Supplier Ledger request.', 404);
}

$draw = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));

$lengthRaw = (int)($_GET['length'] ?? 25);
$length = $lengthRaw < 0
    ? 100000
    : max(1, min(100000, $lengthRaw));

$search = trim((string)($_GET['search']['value'] ?? ''));

$dateFrom = supplier_ledger_optional_date(
    $_GET['date_from'] ?? '',
    'date_from'
);

$dateTo = supplier_ledger_optional_date(
    $_GET['date_to'] ?? '',
    'date_to'
);

if (
    $dateFrom !== null &&
    $dateTo !== null &&
    $dateFrom > $dateTo
) {
    json_error('From Date cannot be after To Date.', 422, [
        'date_from' => 'From Date cannot be after To Date.',
    ]);
}

$transactionType =
    strtolower(
        trim(
            (string)($_GET['transaction_type'] ?? '')
        )
    );

$allowedTypes = [
    '',
    'purchase',
    'purchase_payment',
    'purchase_return',
    'supplier_payment',
];

if (!in_array($transactionType, $allowedTypes, true)) {
    json_error('Invalid transaction type.', 422);
}

$supplierRef = trim((string)($_GET['supplier_ref'] ?? ''));

if ($supplierRef === '') {
    json_success('Select a Supplier to view Ledger.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ],
        'supplier' => null,
        'summary' => [
            'opening_balance' => 0,
            'purchases' => 0,
            'payments' => 0,
            'returns' => 0,
            'closing_balance' => 0,
        ],
        'suppliers' => $suppliers,
        'allowed_actions' => $access['actions'],
    ]);
}

$supplierId = supplier_ledger_ref_to_id($supplierRef);

$supplier = supplier_ledger_supplier(
    $pdo,
    $branchId,
    $supplierId
);

$transactions = supplier_ledger_transactions(
    $pdo,
    $branchId,
    $supplierId
);

$openingBalance = round(
    (float)$supplier['opening_balance'],
    2
);

$running = $openingBalance;

/*
 * Running balance always follows the complete chronological account.
 */
foreach ($transactions as &$transaction) {
    $running = round(
        $running +
        (float)$transaction['debit'] -
        (float)$transaction['credit'],
        2
    );

    $transaction['running_balance'] = $running;
}
unset($transaction);

/*
 * Balance brought forward before From Date.
 */
$openingBroughtForward = $openingBalance;

if ($dateFrom !== null) {
    foreach ($transactions as $transaction) {
        if ($transaction['date'] >= $dateFrom) {
            break;
        }

        $openingBroughtForward = round(
            $openingBroughtForward +
            (float)$transaction['debit'] -
            (float)$transaction['credit'],
            2
        );
    }
}

/*
 * Date-range transactions.
 */
$periodRows = array_values(
    array_filter(
        $transactions,
        static function (array $row) use ($dateFrom, $dateTo): bool {
            if (
                $dateFrom !== null &&
                $row['date'] < $dateFrom
            ) {
                return false;
            }

            if (
                $dateTo !== null &&
                $row['date'] > $dateTo
            ) {
                return false;
            }

            return true;
        }
    )
);

$periodPurchases = 0.0;
$periodPayments = 0.0;
$periodReturns = 0.0;

foreach ($periodRows as $row) {
    if ($row['type_key'] === 'purchase') {
        $periodPurchases += (float)$row['debit'];
    }

    if (
        $row['type_key'] === 'purchase_payment' ||
        $row['type_key'] === 'supplier_payment'
    ) {
        $periodPayments += (float)$row['credit'];
    }

    if ($row['type_key'] === 'purchase_return') {
        $periodReturns += (float)$row['credit'];
    }
}

$closingBalance = round(
    $openingBroughtForward +
    $periodPurchases -
    $periodPayments -
    $periodReturns,
    2
);

/*
 * Transaction Type and Search only affect visible rows.
 * Running balance remains the true chronological balance.
 */
$filteredRows = array_values(
    array_filter(
        $periodRows,
        static function (array $row) use (
            $transactionType,
            $search
        ): bool {
            if (
                $transactionType !== '' &&
                $row['type_key'] !== $transactionType
            ) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(
                implode(
                    ' ',
                    [
                        (string)$row['reference'],
                        (string)$row['transaction_label'],
                        (string)$row['description'],
                        (string)$row['date'],
                    ]
                )
            );

            return str_contains(
                $haystack,
                strtolower($search)
            );
        }
    )
);

$recordsTotal = count($periodRows);
$recordsFiltered = count($filteredRows);

$pageRows = array_slice(
    $filteredRows,
    $start,
    $length
);

foreach ($pageRows as &$row) {
    unset(
        $row['sort_order'],
        $row['sort_id'],
        $row['type_key']
    );
}
unset($row);

json_success('Supplier Ledger loaded.', [
    'datatable' => [
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $pageRows,
    ],

    'supplier' => $supplier,

    'summary' => [
        'opening_balance' => round(
            $openingBroughtForward,
            2
        ),

        'purchases' => round(
            $periodPurchases,
            2
        ),

        'payments' => round(
            $periodPayments,
            2
        ),

        'returns' => round(
            $periodReturns,
            2
        ),

        'closing_balance' => round(
            $closingBalance,
            2
        ),
    ],

    'suppliers' => $suppliers,

    'allowed_actions' => $access['actions'],
]);
