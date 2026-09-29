<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);

function cl_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Customer Ledger is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function cl_require_schema(): void
{
    $tables = [
        'food_customers',
        'food_sales',
        'food_sale_returns',
        'food_sales_return_refunds',
        'food_customer_payments',
        'food_customer_payment_details',
        'food_customer_payment_allocations',
        'food_customer_credit_ledger',
        'food_payment_methods',
        'accounts',
        'users',
    ];

    foreach ($tables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error('Customer Ledger schema is incomplete. Missing table: ' . $table . '.', 500);
        }
    }

    $stmt = db()->query("SHOW COLUMNS FROM food_customers LIKE 'opening_balance_date'");
    if (!$stmt || !$stmt->fetchColumn()) {
        json_error('Customer Ledger update is required. Run sql/customer-ledger-schema.sql first.', 500);
    }
}

function cl_customer_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Customer reference is required.', 422, ['customer_ref' => 'Select a Customer.']);
    }
    try {
        $id = (int)decryptReference(trim($value), 'customer');
    } catch (Throwable $e) {
        json_error('Invalid Customer reference.', 422, ['customer_ref' => 'Select a valid Customer.']);
        return 0;
    }
    if ($id < 1) json_error('Invalid Customer reference.', 422);
    return $id;
}

function cl_valid_date($value, string $field): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}

function cl_customer(PDO $pdo, int $branchId, int $customerId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,customer_code,customer_name,mobile,email,gstin,address,state_code,
                credit_limit,opening_balance,opening_balance_date,status,created_at
         FROM food_customers
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $customerId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Customer was not found in your branch.', 404);

    $row['id'] = (int)$row['id'];
    $row['credit_limit'] = round((float)$row['credit_limit'], 2);
    $row['opening_balance'] = round((float)$row['opening_balance'], 2);
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('customer', (int)$row['id']);
    return $row;
}

function cl_datetime(string $date, ?string $createdAt, string $fallbackTime = '00:00:00'): string
{
    $time = $fallbackTime;
    if ($createdAt && preg_match('/\d{4}-\d{2}-\d{2}[ T](\d{2}:\d{2}:\d{2})/', $createdAt, $match)) {
        $time = $match[1];
    }
    return $date . ' ' . $time;
}

function cl_event(
    string $type,
    int $priority,
    int $id,
    string $date,
    ?string $createdAt,
    string $reference,
    string $label,
    string $description,
    float $movement,
    string $modeAccount = '',
    string $userName = '',
    string $viewUrl = '',
    array $extra = []
): array {
    return array_merge([
        'event_key' => $type . ':' . $id,
        'event_id' => $id,
        'event_type' => $type,
        'event_priority' => $priority,
        'event_datetime' => cl_datetime($date, $createdAt),
        'transaction_date' => $date,
        'reference_no' => $reference,
        'transaction_label' => $label,
        'description' => $description,
        'movement' => round($movement, 2),
        'mode_account' => $modeAccount,
        'user_name' => $userName,
        'view_url' => $viewUrl,
        'balance' => 0.0,
    ], $extra);
}

function cl_load_events(PDO $pdo, int $branchId, array $customer): array
{
    $customerId = (int)$customer['id'];
    $events = [];

    $opening = round((float)$customer['opening_balance'], 2);
    if (abs($opening) > 0.0001) {
        $openingDate = (string)($customer['opening_balance_date'] ?? '');
        if ($openingDate === '') $openingDate = substr((string)$customer['created_at'], 0, 10);
        $events[] = cl_event(
            'opening', 0, 0, $openingDate, null,
            'OPENING', 'Opening Balance', 'Customer opening balance', $opening,
            '', 'Opening', 'customer-form.php?ref=' . rawurlencode((string)$customer['ref']),
            ['actual_amount' => 0.0, 'discount_amount' => 0.0]
        );
    }

    $stmt = $pdo->prepare(
        'SELECT s.id,s.sales_no,s.invoice_date,s.grand_total,s.created_at,
                COALESCE(u.name,\'\') AS user_name
         FROM food_sales s
         LEFT JOIN users u ON u.id=s.created_by
         WHERE s.branch_id=:branch_id
           AND s.customer_id=:customer_id
           AND s.document_type=4
           AND s.posting_status=1
           AND s.status=1
           AND s.reversed_at IS NULL
         ORDER BY s.invoice_date,s.created_at,s.id'
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    foreach ($stmt->fetchAll() as $row) {
        $amount = round((float)$row['grand_total'], 2);
        $events[] = cl_event(
            'sale', 10, (int)$row['id'], (string)$row['invoice_date'], (string)$row['created_at'],
            (string)($row['sales_no'] ?: ('SALE-' . $row['id'])),
            'Final Invoice', 'Final Invoice ' . (string)$row['sales_no'], $amount,
            '', (string)$row['user_name'],
            'sales.php?ref=' . rawurlencode(encryptReference('sale', (int)$row['id'])),
            ['actual_amount' => 0.0, 'discount_amount' => 0.0]
        );
    }

    $stmt = $pdo->prepare(
        "SELECT p.id,p.source_sale_id,p.payment_no,p.payment_date,p.amount,p.discount_amount,p.credit_applied,
                p.payment_mode,p.notes,p.created_at,COALESCE(u.name,'') AS user_name,
                COALESCE(ds.detail_amount,0) AS detail_amount,
                COALESCE(ds.mode_account,'') AS mode_account,
                COALESCE(s.sales_no,'') AS source_sales_no
         FROM food_customer_payments p
         LEFT JOIN users u ON u.id=p.created_by
         LEFT JOIN food_sales s ON s.id=p.source_sale_id AND s.branch_id=p.branch_id
         LEFT JOIN (
            SELECT d.customer_payment_id,
                   SUM(CASE WHEN d.status=1 THEN d.amount ELSE 0 END) AS detail_amount,
                   GROUP_CONCAT(
                     CASE WHEN d.status=1 THEN CONCAT(
                         COALESCE(pm.method_name,UPPER(pm.method_code),'Payment'),
                         CASE WHEN a.account_name IS NOT NULL AND a.account_name<>'' THEN CONCAT(' / ',a.account_name) ELSE '' END,
                         ' ₹',FORMAT(d.amount,2)
                     ) END
                     ORDER BY d.id SEPARATOR ', '
                   ) AS mode_account
            FROM food_customer_payment_details d
            LEFT JOIN food_payment_methods pm ON pm.id=d.payment_method_id AND pm.branch_id=d.branch_id
            LEFT JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
            GROUP BY d.customer_payment_id
         ) ds ON ds.customer_payment_id=p.id
         WHERE p.branch_id=:branch_id
           AND p.customer_id=:customer_id
           AND p.posting_status=1
           AND p.status=1
           AND p.reversed_at IS NULL
         ORDER BY p.payment_date,p.created_at,p.id"
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    foreach ($stmt->fetchAll() as $row) {
        $detailAmount = round((float)$row['detail_amount'], 2);
        $headerAmount = round((float)$row['amount'], 2);
        $actual = $detailAmount > 0.0001 ? $detailAmount : $headerAmount;
        $discount = round((float)$row['discount_amount'], 2);
        $creditApplied = round((float)$row['credit_applied'], 2);
        $movement = -round($actual + $discount, 2);

        $isPos = (int)$row['source_sale_id'] > 0;
        $reference = trim((string)$row['payment_no']);
        if ($reference === '') {
            $reference = $isPos && (string)$row['source_sales_no'] !== ''
                ? 'POS-' . (string)$row['source_sales_no']
                : 'PAY-' . (int)$row['id'];
        }

        $parts = [];
        if ($actual > 0) $parts[] = 'Actual Payment ₹' . number_format($actual, 2, '.', ',');
        if ($discount > 0) $parts[] = 'Settlement Discount ₹' . number_format($discount, 2, '.', ',');
        if ($creditApplied > 0) $parts[] = 'Credit Applied ₹' . number_format($creditApplied, 2, '.', ',') . ' (internal)';
        $description = ($isPos ? 'Sales POS Payment' : 'Customer Payment') . ($parts ? ' · ' . implode(' · ', $parts) : '');

        $modeAccount = trim((string)$row['mode_account']);
        if ($modeAccount === '') {
            $modeAccount = trim((string)$row['payment_mode']);
        }

        $viewUrl = $isPos
            ? 'sales.php?ref=' . rawurlencode(encryptReference('sale', (int)$row['source_sale_id']))
            : 'customer-payment.php?ref=' . rawurlencode(encryptReference('customer_payment', (int)$row['id']));

        $events[] = cl_event(
            'payment', 20, (int)$row['id'], (string)$row['payment_date'], (string)$row['created_at'],
            $reference, $isPos ? 'Sales POS Payment' : 'Customer Payment', $description, $movement,
            $modeAccount, (string)$row['user_name'], $viewUrl,
            ['actual_amount' => $actual, 'discount_amount' => $discount, 'credit_applied' => $creditApplied]
        );
    }

    $stmt = $pdo->prepare(
        "SELECT r.id,r.return_no,r.return_date,r.grand_total,r.released_discount_amount,r.created_at,
                COALESCE(u.name,'') AS user_name
         FROM food_sale_returns r
         LEFT JOIN users u ON u.id=r.created_by
         WHERE r.branch_id=:branch_id
           AND r.customer_id=:customer_id
           AND r.posting_status=1
           AND r.status=1
           AND r.reversed_at IS NULL
         ORDER BY r.return_date,r.created_at,r.id"
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    foreach ($stmt->fetchAll() as $row) {
        $returnTotal = round((float)$row['grand_total'], 2);
        $releasedDiscount = round((float)$row['released_discount_amount'], 2);
        // The original Customer Payment event already reduced balance by settlement discount.
        // When a Sales Return releases/removes part of that discount, add it back here.
        $movement = round(-$returnTotal + $releasedDiscount, 2);
        $description = 'Sales Return ' . (string)$row['return_no'];
        if ($releasedDiscount > 0) {
            $description .= ' · Released Discount +₹' . number_format($releasedDiscount, 2, '.', ',');
        }

        $events[] = cl_event(
            'sale_return', 30, (int)$row['id'], (string)$row['return_date'], (string)$row['created_at'],
            (string)($row['return_no'] ?: ('SRT-' . $row['id'])),
            'Sales Return', $description, $movement,
            '', (string)$row['user_name'],
            'sales-return.php?ref=' . rawurlencode(encryptReference('sale_return', (int)$row['id'])),
            ['return_total' => $returnTotal, 'released_discount_amount' => $releasedDiscount, 'actual_amount' => 0.0, 'discount_amount' => 0.0]
        );
    }

    $stmt = $pdo->prepare(
        "SELECT rf.id,rf.sale_return_id,rf.refund_date,rf.amount,rf.created_at,
                r.return_no,COALESCE(pm.method_name,pm.method_code,'Refund') AS method_name,
                COALESCE(a.account_name,'') AS account_name,
                COALESCE(u.name,'') AS user_name
         FROM food_sales_return_refunds rf
         INNER JOIN food_sale_returns r
            ON r.id=rf.sale_return_id AND r.branch_id=rf.branch_id
           AND r.customer_id=:customer_id AND r.posting_status=1 AND r.status=1 AND r.reversed_at IS NULL
         LEFT JOIN food_payment_methods pm ON pm.id=rf.payment_method_id AND pm.branch_id=rf.branch_id
         LEFT JOIN accounts a ON a.id=rf.account_id AND a.branch_id=rf.branch_id
         LEFT JOIN users u ON u.id=rf.created_by
         WHERE rf.branch_id=:branch_id
           AND rf.status=1
           AND rf.reversed_at IS NULL
         ORDER BY rf.refund_date,rf.created_at,rf.id"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':customer_id' => $customerId,
    ]);
    foreach ($stmt->fetchAll() as $row) {
        $amount = round((float)$row['amount'], 2);
        $mode = trim((string)$row['method_name']);
        if ((string)$row['account_name'] !== '') $mode .= ' / ' . (string)$row['account_name'];
        $events[] = cl_event(
            'refund', 40, (int)$row['id'], (string)$row['refund_date'], (string)$row['created_at'],
            (string)($row['return_no'] ?: ('SRT-' . $row['sale_return_id'])),
            'Refund', 'Refund against Sales Return ' . (string)$row['return_no'], $amount,
            $mode, (string)$row['user_name'],
            'sales-return.php?ref=' . rawurlencode(encryptReference('sale_return', (int)$row['sale_return_id'])),
            ['actual_amount' => $amount, 'discount_amount' => 0.0]
        );
    }

    usort($events, static function (array $a, array $b): int {
        $cmp = strcmp((string)$a['event_datetime'], (string)$b['event_datetime']);
        if ($cmp !== 0) return $cmp;
        $cmp = ((int)$a['event_priority']) <=> ((int)$b['event_priority']);
        if ($cmp !== 0) return $cmp;
        return ((int)$a['event_id']) <=> ((int)$b['event_id']);
    });

    $balance = 0.0;
    foreach ($events as &$event) {
        $balance = round($balance + (float)$event['movement'], 2);
        $event['balance'] = $balance;
    }
    unset($event);

    return $events;
}

function cl_available_credit(PDO $pdo, int $branchId, int $customerId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_in-amount_out),0)
         FROM food_customer_credit_ledger
         WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1'
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function cl_opening_outstanding(PDO $pdo, int $branchId, int $customerId, float $openingBalance): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(a.allocated_amount+a.credit_amount+a.discount_amount),0)
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
            ON p.id=a.customer_payment_id AND p.branch_id=a.branch_id
           AND p.customer_id=:customer_id
           AND p.status=1 AND p.posting_status=1 AND p.reversed_at IS NULL
         WHERE a.branch_id=:branch_id AND a.allocation_type=2 AND a.status=1'
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    return max(0.0, round($openingBalance - (float)$stmt->fetchColumn(), 2));
}

function cl_invoice_outstanding(PDO $pdo, int $branchId, int $customerId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(balance_amount),0)
         FROM food_sales
         WHERE branch_id=:branch_id AND customer_id=:customer_id
           AND document_type=4 AND posting_status=1 AND status=1 AND reversed_at IS NULL'
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function cl_summary(PDO $pdo, int $branchId, array $customer, array $events): array
{
    $sales = $payments = $discounts = $returns = $refunds = 0.0;
    foreach ($events as $event) {
        switch ($event['event_type']) {
            case 'sale':
                $sales += (float)$event['movement'];
                break;
            case 'payment':
                $payments += (float)($event['actual_amount'] ?? 0);
                $discounts += (float)($event['discount_amount'] ?? 0);
                break;
            case 'sale_return':
                $returns += (float)($event['return_total'] ?? abs((float)$event['movement']));
                break;
            case 'refund':
                $refunds += (float)$event['movement'];
                break;
        }
    }

    $availableCredit = cl_available_credit($pdo, $branchId, (int)$customer['id']);
    $openingOutstanding = cl_opening_outstanding($pdo, $branchId, (int)$customer['id'], (float)$customer['opening_balance']);
    $invoiceOutstanding = cl_invoice_outstanding($pdo, $branchId, (int)$customer['id']);
    $currentOutstanding = round($openingOutstanding + $invoiceOutstanding, 2);
    $netPosition = round($currentOutstanding - $availableCredit, 2);
    $ledgerBalance = $events ? round((float)$events[count($events)-1]['balance'], 2) : 0.0;

    return [
        'opening_balance' => round((float)$customer['opening_balance'], 2),
        'total_sales' => round($sales, 2),
        'total_payments' => round($payments, 2),
        'total_discounts' => round($discounts, 2),
        'total_returns' => round($returns, 2),
        'total_refunds' => round($refunds, 2),
        'opening_outstanding' => $openingOutstanding,
        'invoice_outstanding' => $invoiceOutstanding,
        'current_outstanding' => $currentOutstanding,
        'available_credit' => $availableCredit,
        'net_position' => $netPosition,
        'ledger_balance' => $ledgerBalance,
        'reconciliation_difference' => round($ledgerBalance - $netPosition, 2),
    ];
}

function cl_period_summary(
    array $customer,
    array $events,
    ?string $fromDate,
    ?string $toDate
): array {
    $openingBalance = 0.0;

    if ($fromDate === null) {
        $openingBalance = round(
            (float)$customer['opening_balance'],
            2
        );
    } else {
        $cutoff = $fromDate . ' 00:00:00';

        foreach ($events as $event) {
            if ((string)$event['event_datetime'] >= $cutoff) {
                break;
            }

            $openingBalance = round(
                (float)$event['balance'],
                2
            );
        }
    }

    $sales = 0.0;
    $payments = 0.0;
    $returns = 0.0;
    $discounts = 0.0;
    $refunds = 0.0;
    $periodMovement = 0.0;

    foreach ($events as $event) {
        $date = (string)$event['transaction_date'];

        if (
            $fromDate !== null &&
            $date < $fromDate
        ) {
            continue;
        }

        if (
            $toDate !== null &&
            $date > $toDate
        ) {
            continue;
        }

        /*
         * Without a From Date, the master opening balance is already the
         * starting balance, so do not add the Opening event a second time.
         * With a From Date, an Opening event inside the selected period must
         * be included in period movement.
         */
        if (
            (string)$event['event_type'] === 'opening' &&
            $fromDate === null
        ) {
            continue;
        }

        $movement = round(
            (float)$event['movement'],
            2
        );

        $periodMovement = round(
            $periodMovement + $movement,
            2
        );

        switch ((string)$event['event_type']) {
            case 'sale':
                $sales += max(
                    0.0,
                    $movement
                );
                break;

            case 'payment':
                $payments += round(
                    (float)($event['actual_amount'] ?? 0),
                    2
                );

                $discounts += round(
                    (float)($event['discount_amount'] ?? 0),
                    2
                );
                break;

            case 'sale_return':
                $returns += round(
                    (float)(
                        $event['return_total'] ??
                        abs($movement)
                    ),
                    2
                );
                break;

            case 'refund':
                $refunds += max(
                    0.0,
                    $movement
                );
                break;
        }
    }

    $closingBalance = round(
        $openingBalance + $periodMovement,
        2
    );

    return [
        'opening_balance' => round(
            $openingBalance,
            2
        ),

        'sales' => round(
            $sales,
            2
        ),

        'payments' => round(
            $payments,
            2
        ),

        'returns' => round(
            $returns,
            2
        ),

        'discounts' => round(
            $discounts,
            2
        ),

        'refunds' => round(
            $refunds,
            2
        ),

        'closing_balance' => round(
            $closingBalance,
            2
        ),
    ];
}

function cl_filter_events(array $events, ?string $fromDate, ?string $toDate, string $type, string $search): array
{
    $filtered = [];
    $broughtForward = null;

    if ($fromDate !== null) {
        $cutoff = $fromDate . ' 00:00:00';
        $balanceBefore = 0.0;
        foreach ($events as $event) {
            if ((string)$event['event_datetime'] < $cutoff) {
                $balanceBefore = (float)$event['balance'];
            }
        }
        $broughtForward = [
            'event_key' => 'brought_forward:0',
            'event_id' => 0,
            'event_type' => 'brought_forward',
            'event_priority' => -1,
            'event_datetime' => $cutoff,
            'transaction_date' => $fromDate,
            'reference_no' => 'B/F',
            'transaction_label' => 'Brought Forward',
            'description' => 'Balance before selected period',
            'movement' => 0.0,
            'mode_account' => '',
            'user_name' => '',
            'view_url' => '',
            'balance' => round($balanceBefore, 2),
        ];
    }

    $search = mb_strtolower(trim($search));
    foreach ($events as $event) {
        $date = (string)$event['transaction_date'];
        if ($fromDate !== null && $date < $fromDate) continue;
        if ($toDate !== null && $date > $toDate) continue;
        if ($type !== '' && $type !== 'all' && (string)$event['event_type'] !== $type) continue;

        if ($search !== '') {
            $haystack = mb_strtolower(implode(' ', [
                (string)$event['reference_no'],
                (string)$event['transaction_label'],
                (string)$event['description'],
                (string)$event['mode_account'],
                (string)$event['user_name'],
            ]));
            if (mb_strpos($haystack, $search) === false) continue;
        }
        $filtered[] = $event;
    }

    if ($broughtForward !== null) {
        array_unshift($filtered, $broughtForward);
    }
    return $filtered;
}

$access = require_permission('customer-ledger.php', ACTION_VIEW);
$user = $access['user'];
$context = cl_tenant_context($user);
cl_require_schema();
$pdo = db();
$branchId = (int)$context['branch_id'];

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

if (isset($_GET['options'])) {
    $stmt = $pdo->prepare(
        'SELECT id,customer_code,customer_name,mobile,status
         FROM food_customers
         WHERE branch_id=:branch_id
         ORDER BY customer_name,customer_code'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $customers = [];
    foreach ($stmt->fetchAll() as $row) {
        $customers[] = [
            'ref' => encryptReference('customer', (int)$row['id']),
            'customer_code' => (string)$row['customer_code'],
            'customer_name' => (string)$row['customer_name'],
            'mobile' => (string)($row['mobile'] ?? ''),
            'status' => (int)$row['status'],
        ];
    }

    json_success('Customer Ledger options loaded.', [
        'customers' => $customers,
        'allowed_actions' => $access['actions'],
        'transaction_types' => [
            ['value' => 'all', 'label' => 'All Transactions'],
            ['value' => 'opening', 'label' => 'Opening Balance'],
            ['value' => 'sale', 'label' => 'Final Invoice'],
            ['value' => 'payment', 'label' => 'Customer Payment'],
            ['value' => 'sale_return', 'label' => 'Sales Return'],
            ['value' => 'refund', 'label' => 'Refund'],
        ],
    ]);
}

$customerRef = trim((string)($_GET['customer_ref'] ?? $_GET['ref'] ?? ''));
if ($customerRef === '') {
    json_success('Select a Customer to view Ledger.', [
        'datatable' => [
            'draw' => max(0, (int)($_GET['draw'] ?? 0)),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ],
        'customer' => null,
        'summary' => null,
        'period_summary' => [
            'opening_balance' => 0,
            'sales' => 0,
            'payments' => 0,
            'returns' => 0,
            'discounts' => 0,
            'refunds' => 0,
            'closing_balance' => 0,
        ],
        'allowed_actions' => $access['actions'],
    ]);
}

$customer = cl_customer($pdo, $branchId, cl_customer_id($customerRef));
$events = cl_load_events($pdo, $branchId, $customer);
$summary = cl_summary($pdo, $branchId, $customer, $events);

$fromDate = cl_valid_date($_GET['date_from'] ?? '', 'date_from');
$toDate = cl_valid_date($_GET['date_to'] ?? '', 'date_to');
if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    json_error('From Date cannot be after To Date.', 422, ['date_to' => 'Select a date on or after From Date.']);
}
$periodSummary = cl_period_summary(
    $customer,
    $events,
    $fromDate,
    $toDate
);

$type = strtolower(trim((string)($_GET['transaction_type'] ?? 'all')));
$allowedTypes = ['all','opening','sale','payment','sale_return','refund'];
if (!in_array($type, $allowedTypes, true)) $type = 'all';

$search = '';
if (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = trim((string)($_GET['search']['value'] ?? ''));
} else {
    $search = trim((string)($_GET['search_value'] ?? ''));
}

$filtered = cl_filter_events($events, $fromDate, $toDate, $type, $search);
$draw = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));
$lengthRaw = (int)($_GET['length'] ?? 10);
$length = $lengthRaw < 0
    ? 100000
    : max(1, min(100000, $lengthRaw));

$totalCount = count($events) + ($fromDate !== null ? 1 : 0);
$filteredCount = count($filtered);
$rows = array_slice($filtered, $start, $length);

json_success('Customer Ledger loaded.', [
    'datatable' => [
        'draw' => $draw,
        'recordsTotal' => $totalCount,
        'recordsFiltered' => $filteredCount,
        'data' => array_values($rows),
    ],
    'customer' => [
        'ref' => $customer['ref'],
        'customer_code' => $customer['customer_code'],
        'customer_name' => $customer['customer_name'],
        'mobile' => $customer['mobile'],
        'email' => $customer['email'],
        'credit_limit' => $customer['credit_limit'],
        'opening_balance' => $customer['opening_balance'],
        'opening_balance_date' => $customer['opening_balance_date'],
        'status' => $customer['status'],
    ],
    'summary' => $summary,
    'period_summary' => $periodSummary,
    'allowed_actions' => $access['actions'],
]);
