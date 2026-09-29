<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function dl_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Daily Ledger is available only for tenant users.', 403);
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

function dl_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
    $cache[$table] = (bool)($stmt && $stmt->fetchColumn());
    return $cache[$table];
}

function dl_valid_date($value, string $fallback): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $d->format('Y-m-d') !== $value) {
        json_error('Enter a valid Ledger date.', 422);
    }
    return $value;
}

function dl_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,opening_balance,opening_balance_date,status
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => (int)$row['account_type'],
            'opening_balance' => (float)$row['opening_balance'],
            'opening_balance_date' => (string)($row['opening_balance_date'] ?? ''),
        ];
    }
    return $rows;
}

function dl_account_name_map(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name FROM accounts WHERE branch_id=:branch_id'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['id']] = trim((string)$row['account_code']) !== ''
            ? (string)$row['account_code'] . ' - ' . (string)$row['account_name']
            : (string)$row['account_name'];
    }
    return $map;
}

function dl_opening_balance(int $branchId, int $accountId, string $fromDate): float
{
    $sql = 'SELECT COALESCE(SUM(opening_balance),0)
            FROM accounts
            WHERE branch_id=:branch_id
              AND COALESCE(opening_balance_date,DATE(created_at))<=:from_date';
    $params = [':branch_id' => $branchId, ':from_date' => $fromDate];
    if ($accountId > 0) {
        $sql .= ' AND id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function dl_add_row(array &$rows, array $row): void
{
    $row['money_in'] = round((float)($row['money_in'] ?? 0), 2);
    $row['money_out'] = round((float)($row['money_out'] ?? 0), 2);
    $row['account_id'] = (int)($row['account_id'] ?? 0);
    $row['sort_key'] = (string)$row['business_date'] . ' ' . substr((string)($row['created_at'] ?? '00:00:00'), 11, 8) . ' ' . str_pad((string)($row['source_id'] ?? 0), 12, '0', STR_PAD_LEFT);
    $rows[] = $row;
}

function dl_collect_customer_payments(array &$rows, int $branchId, int $accountId, string $fromDate, string $toDate): void
{
    if (!dl_table_exists('food_customer_payment_details') || !dl_table_exists('food_customer_payments')) {
        return;
    }
    $sql = 'SELECT d.id AS source_id,d.account_id,d.amount,d.created_at,
                   p.id AS payment_id,p.payment_no,p.payment_date,p.source_sale_id,
                   c.customer_code,c.customer_name,a.account_code,a.account_name,
                   pm.method_name
            FROM food_customer_payment_details d
            INNER JOIN food_customer_payments p ON p.id=d.customer_payment_id AND p.branch_id=d.branch_id
            LEFT JOIN food_customers c ON c.id=p.customer_id AND c.branch_id=p.branch_id
            INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
            LEFT JOIN food_payment_methods pm ON pm.id=d.payment_method_id AND pm.branch_id=d.branch_id
            WHERE d.branch_id=:branch_id
              AND d.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL
              AND p.payment_date BETWEEN :date_from AND :date_to';
    $params = [':branch_id' => $branchId, ':date_from' => $fromDate, ':date_to' => $toDate];
    if ($accountId > 0) {
        $sql .= ' AND d.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $isSale = (int)($r['source_sale_id'] ?? 0) > 0;
        $reference = trim((string)($r['payment_no'] ?? ''));
        if ($reference === '') {
            $reference = $isSale ? 'Sales Receipt' : ('CP#' . (int)$r['payment_id']);
        }
        $customer = trim((string)($r['customer_code'] ?? ''));
        $customer .= $customer !== '' ? ' - ' . (string)($r['customer_name'] ?? '') : (string)($r['customer_name'] ?? '');
        $viewUrl = $isSale
            ? 'sales.php?ref=' . rawurlencode(encryptReference('sale', (int)$r['source_sale_id']))
            : 'customer-payment.php?ref=' . rawurlencode(encryptReference('customer_payment', (int)$r['payment_id'])) . '&view=1';

        dl_add_row($rows, [
            'source_id' => (int)$r['source_id'],
            'business_date' => (string)$r['payment_date'],
            'created_at' => (string)$r['created_at'],
            'reference' => $reference,
            'transaction_type' => $isSale ? 'sales_receipt' : 'customer_payment',
            'transaction' => $isSale ? 'Sales Receipt' : 'Customer Payment',
            'particular' => $customer !== '' ? $customer : 'Customer Receipt',
            'account_id' => (int)$r['account_id'],
            'account' => trim((string)$r['account_code']) !== '' ? $r['account_code'] . ' - ' . $r['account_name'] : $r['account_name'],
            'mode' => (string)($r['method_name'] ?? 'Receipt'),
            'money_in' => (float)$r['amount'],
            'money_out' => 0.0,
            'view_url' => $viewUrl,
        ]);
    }
}

function dl_collect_sale_refunds(array &$rows, int $branchId, int $accountId, string $fromDate, string $toDate): void
{
    if (!dl_table_exists('food_sales_return_refunds')) {
        return;
    }
    $sql = 'SELECT rf.id AS source_id,rf.account_id,rf.amount,rf.refund_date,rf.created_at,
                   rf.sale_return_id,sr.return_no,c.customer_code,c.customer_name,
                   a.account_code,a.account_name,pm.method_name
            FROM food_sales_return_refunds rf
            INNER JOIN food_sale_returns sr ON sr.id=rf.sale_return_id AND sr.branch_id=rf.branch_id
            LEFT JOIN food_customers c ON c.id=rf.customer_id AND c.branch_id=rf.branch_id
            INNER JOIN accounts a ON a.id=rf.account_id AND a.branch_id=rf.branch_id
            LEFT JOIN food_payment_methods pm ON pm.id=rf.payment_method_id AND pm.branch_id=rf.branch_id
            WHERE rf.branch_id=:branch_id
              AND rf.status=1
              AND rf.reversed_at IS NULL
              AND sr.posting_status=1
              AND sr.status=1
              AND sr.reversed_at IS NULL
              AND rf.refund_date BETWEEN :date_from AND :date_to';
    $params = [':branch_id' => $branchId, ':date_from' => $fromDate, ':date_to' => $toDate];
    if ($accountId > 0) {
        $sql .= ' AND rf.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $customer = trim((string)($r['customer_code'] ?? ''));
        $customer .= $customer !== '' ? ' - ' . (string)($r['customer_name'] ?? '') : (string)($r['customer_name'] ?? '');
        dl_add_row($rows, [
            'source_id' => (int)$r['source_id'],
            'business_date' => (string)$r['refund_date'],
            'created_at' => (string)$r['created_at'],
            'reference' => (string)($r['return_no'] ?: ('Return #' . (int)$r['sale_return_id'])),
            'transaction_type' => 'sales_refund',
            'transaction' => 'Sales Return Refund',
            'particular' => $customer !== '' ? $customer : 'Customer Refund',
            'account_id' => (int)$r['account_id'],
            'account' => trim((string)$r['account_code']) !== '' ? $r['account_code'] . ' - ' . $r['account_name'] : $r['account_name'],
            'mode' => (string)($r['method_name'] ?? 'Refund'),
            'money_in' => 0.0,
            'money_out' => (float)$r['amount'],
            'view_url' => 'sales-return.php?ref=' . rawurlencode(encryptReference('sale_return', (int)$r['sale_return_id'])),
        ]);
    }
}

function dl_collect_purchase_payments(array &$rows, int $branchId, int $accountId, string $fromDate, string $toDate): void
{
    if (!dl_table_exists('food_purchase_payments')) {
        return;
    }
    $sql = 'SELECT pp.id AS source_id,pp.account_id,pp.payment_mode,pp.amount,pp.created_at,
                   p.id AS purchase_id,p.purchase_no,p.purchase_date,
                   s.supplier_code,s.supplier_name,a.account_code,a.account_name
            FROM food_purchase_payments pp
            INNER JOIN food_purchases p ON p.id=pp.purchase_id AND p.branch_id=pp.branch_id
            INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
            INNER JOIN accounts a ON a.id=pp.account_id AND a.branch_id=pp.branch_id
            WHERE pp.branch_id=:branch_id
              AND pp.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.purchase_date BETWEEN :date_from AND :date_to';
    $params = [':branch_id' => $branchId, ':date_from' => $fromDate, ':date_to' => $toDate];
    if ($accountId > 0) {
        $sql .= ' AND pp.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mode = [1=>'Cash',2=>'UPI',3=>'Bank Transfer',4=>'Cheque'][(int)$r['payment_mode']] ?? 'Payment';
        dl_add_row($rows, [
            'source_id' => (int)$r['source_id'],
            'business_date' => (string)$r['purchase_date'],
            'created_at' => (string)$r['created_at'],
            'reference' => (string)$r['purchase_no'],
            'transaction_type' => 'purchase_payment',
            'transaction' => 'Purchase Payment',
            'particular' => trim((string)$r['supplier_code']) !== '' ? $r['supplier_code'] . ' - ' . $r['supplier_name'] : $r['supplier_name'],
            'account_id' => (int)$r['account_id'],
            'account' => trim((string)$r['account_code']) !== '' ? $r['account_code'] . ' - ' . $r['account_name'] : $r['account_name'],
            'mode' => $mode,
            'money_in' => 0.0,
            'money_out' => (float)$r['amount'],
            'view_url' => 'purchase-form.php?ref=' . rawurlencode(encryptReference('purchase', (int)$r['purchase_id'])),
        ]);
    }
}

function dl_collect_supplier_payments(array &$rows, int $branchId, int $accountId, string $fromDate, string $toDate): void
{
    if (!dl_table_exists('food_supplier_payment_details') || !dl_table_exists('food_supplier_payments')) {
        return;
    }
    $sql = 'SELECT d.id AS source_id,d.account_id,d.payment_mode,d.amount,d.created_at,
                   p.id AS payment_id,p.payment_no,p.payment_date,
                   s.supplier_code,s.supplier_name,a.account_code,a.account_name
            FROM food_supplier_payment_details d
            INNER JOIN food_supplier_payments p ON p.id=d.supplier_payment_id AND p.branch_id=d.branch_id
            INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
            INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
            WHERE d.branch_id=:branch_id
              AND d.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL
              AND p.payment_date BETWEEN :date_from AND :date_to';
    $params = [':branch_id' => $branchId, ':date_from' => $fromDate, ':date_to' => $toDate];
    if ($accountId > 0) {
        $sql .= ' AND d.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mode = [1=>'Cash',2=>'UPI',3=>'Bank Transfer',4=>'Cheque'][(int)$r['payment_mode']] ?? 'Payment';
        dl_add_row($rows, [
            'source_id' => (int)$r['source_id'],
            'business_date' => (string)$r['payment_date'],
            'created_at' => (string)$r['created_at'],
            'reference' => (string)$r['payment_no'],
            'transaction_type' => 'supplier_payment',
            'transaction' => 'Supplier Payment',
            'particular' => trim((string)$r['supplier_code']) !== '' ? $r['supplier_code'] . ' - ' . $r['supplier_name'] : $r['supplier_name'],
            'account_id' => (int)$r['account_id'],
            'account' => trim((string)$r['account_code']) !== '' ? $r['account_code'] . ' - ' . $r['account_name'] : $r['account_name'],
            'mode' => $mode,
            'money_in' => 0.0,
            'money_out' => (float)$r['amount'],
            'view_url' => 'supplier-payment.php?ref=' . rawurlencode(encryptReference('supplier_payment', (int)$r['payment_id'])) . '&view=1',
        ]);
    }
}

function dl_collect_expense_payments(array &$rows, int $branchId, int $accountId, string $fromDate, string $toDate): void
{
    if (!dl_table_exists('food_expense_payment_details') || !dl_table_exists('food_expense_payments')) {
        return;
    }
    $sql = 'SELECT d.id AS source_id,d.account_id,d.payment_mode,d.amount,d.created_at,
                   p.id AS payment_id,p.payment_no,p.payment_date,
                   e.expense_no,e.payee_name,ec.category_name,a.account_code,a.account_name
            FROM food_expense_payment_details d
            INNER JOIN food_expense_payments p ON p.id=d.expense_payment_id AND p.branch_id=d.branch_id
            INNER JOIN expenses e ON e.id=p.expense_id AND e.branch_id=p.branch_id
            INNER JOIN expense_categories ec ON ec.id=e.expense_category_id
            INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
            WHERE d.branch_id=:branch_id
              AND d.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL
              AND e.posting_status=1
              AND e.status=1
              AND e.reversed_at IS NULL
              AND p.payment_date BETWEEN :date_from AND :date_to';
    $params = [':branch_id' => $branchId, ':date_from' => $fromDate, ':date_to' => $toDate];
    if ($accountId > 0) {
        $sql .= ' AND d.account_id=:account_id';
        $params[':account_id'] = $accountId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mode = [1=>'Cash',2=>'UPI',3=>'Bank Transfer',4=>'Cheque'][(int)$r['payment_mode']] ?? 'Payment';
        dl_add_row($rows, [
            'source_id' => (int)$r['source_id'],
            'business_date' => (string)$r['payment_date'],
            'created_at' => (string)$r['created_at'],
            'reference' => (string)$r['payment_no'],
            'transaction_type' => 'expense_payment',
            'transaction' => 'Expense Payment',
            'particular' => (string)$r['category_name'] . ' · ' . (string)$r['payee_name'] . ' · ' . (string)$r['expense_no'],
            'account_id' => (int)$r['account_id'],
            'account' => trim((string)$r['account_code']) !== '' ? $r['account_code'] . ' - ' . $r['account_name'] : $r['account_name'],
            'mode' => $mode,
            'money_in' => 0.0,
            'money_out' => (float)$r['amount'],
            'view_url' => 'expense-payment.php?ref=' . rawurlencode(encryptReference('expense_payment', (int)$r['payment_id'])) . '&view=1',
        ]);
    }
}

function dl_collect(int $branchId, int $accountId, string $fromDate, string $toDate): array
{
    $rows = [];
    dl_collect_customer_payments($rows, $branchId, $accountId, $fromDate, $toDate);
    dl_collect_sale_refunds($rows, $branchId, $accountId, $fromDate, $toDate);
    dl_collect_purchase_payments($rows, $branchId, $accountId, $fromDate, $toDate);
    dl_collect_supplier_payments($rows, $branchId, $accountId, $fromDate, $toDate);
    dl_collect_expense_payments($rows, $branchId, $accountId, $fromDate, $toDate);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string)$a['sort_key'], (string)$b['sort_key']);
    });
    return $rows;
}

function dl_sum_before(string $sql, array $params): float
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function dl_movement_before(int $branchId, int $accountId, string $fromDate): float
{
    $net = 0.0;

    if (dl_table_exists('food_customer_payment_details') && dl_table_exists('food_customer_payments')) {
        $sql = 'SELECT COALESCE(SUM(d.amount),0)
                FROM food_customer_payment_details d
                INNER JOIN food_customer_payments p ON p.id=d.customer_payment_id AND p.branch_id=d.branch_id
                WHERE d.branch_id=:branch_id AND d.status=1
                  AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
                  AND p.payment_date<:from_date';
        $params = [':branch_id'=>$branchId, ':from_date'=>$fromDate];
        if ($accountId > 0) { $sql .= ' AND d.account_id=:account_id'; $params[':account_id']=$accountId; }
        $net += dl_sum_before($sql, $params);
    }

    if (dl_table_exists('food_sales_return_refunds')) {
        $sql = 'SELECT COALESCE(SUM(rf.amount),0)
                FROM food_sales_return_refunds rf
                INNER JOIN food_sale_returns sr ON sr.id=rf.sale_return_id AND sr.branch_id=rf.branch_id
                WHERE rf.branch_id=:branch_id AND rf.status=1 AND rf.reversed_at IS NULL
                  AND sr.posting_status=1 AND sr.status=1 AND sr.reversed_at IS NULL
                  AND rf.refund_date<:from_date';
        $params = [':branch_id'=>$branchId, ':from_date'=>$fromDate];
        if ($accountId > 0) { $sql .= ' AND rf.account_id=:account_id'; $params[':account_id']=$accountId; }
        $net -= dl_sum_before($sql, $params);
    }

    if (dl_table_exists('food_purchase_payments')) {
        $sql = 'SELECT COALESCE(SUM(pp.amount),0)
                FROM food_purchase_payments pp
                INNER JOIN food_purchases p ON p.id=pp.purchase_id AND p.branch_id=pp.branch_id
                WHERE pp.branch_id=:branch_id AND pp.status=1
                  AND p.posting_status=1 AND p.status=1
                  AND p.purchase_date<:from_date';
        $params = [':branch_id'=>$branchId, ':from_date'=>$fromDate];
        if ($accountId > 0) { $sql .= ' AND pp.account_id=:account_id'; $params[':account_id']=$accountId; }
        $net -= dl_sum_before($sql, $params);
    }

    if (dl_table_exists('food_supplier_payment_details') && dl_table_exists('food_supplier_payments')) {
        $sql = 'SELECT COALESCE(SUM(d.amount),0)
                FROM food_supplier_payment_details d
                INNER JOIN food_supplier_payments p ON p.id=d.supplier_payment_id AND p.branch_id=d.branch_id
                WHERE d.branch_id=:branch_id AND d.status=1
                  AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
                  AND p.payment_date<:from_date';
        $params = [':branch_id'=>$branchId, ':from_date'=>$fromDate];
        if ($accountId > 0) { $sql .= ' AND d.account_id=:account_id'; $params[':account_id']=$accountId; }
        $net -= dl_sum_before($sql, $params);
    }

    if (dl_table_exists('food_expense_payment_details') && dl_table_exists('food_expense_payments')) {
        $sql = 'SELECT COALESCE(SUM(d.amount),0)
                FROM food_expense_payment_details d
                INNER JOIN food_expense_payments p ON p.id=d.expense_payment_id AND p.branch_id=d.branch_id
                INNER JOIN expenses e ON e.id=p.expense_id AND e.branch_id=p.branch_id
                WHERE d.branch_id=:branch_id AND d.status=1
                  AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
                  AND e.posting_status=1 AND e.status=1 AND e.reversed_at IS NULL
                  AND p.payment_date<:from_date';
        $params = [':branch_id'=>$branchId, ':from_date'=>$fromDate];
        if ($accountId > 0) { $sql .= ' AND d.account_id=:account_id'; $params[':account_id']=$accountId; }
        $net -= dl_sum_before($sql, $params);
    }

    return round($net, 2);
}

$method = request_method();
if ($method !== 'GET') { json_error('Method not allowed.', 405); }

$access = require_permission('daily-ledger.php', ACTION_VIEW);
$ctx = dl_tenant_context($access['user']);
$branchId = (int)$ctx['branch_id'];

if (isset($_GET['options'])) {
    json_success('Daily Ledger options loaded.', [
        'allowed_actions' => $access['actions'],
        'accounts' => dl_accounts($branchId),
        'transaction_types' => [
            ['value' => '', 'label' => 'All Transactions'],
            ['value' => 'customer_payment', 'label' => 'Customer Payment'],
            ['value' => 'sales_receipt', 'label' => 'Sales Receipt'],
            ['value' => 'sales_refund', 'label' => 'Sales Return Refund'],
            ['value' => 'purchase_payment', 'label' => 'Purchase Payment'],
            ['value' => 'supplier_payment', 'label' => 'Supplier Payment'],
            ['value' => 'expense_payment', 'label' => 'Expense Payment'],
        ],
        'today' => date('Y-m-d'),
        'branch' => $ctx,
    ]);
}

$today = date('Y-m-d');
$fromDate = dl_valid_date($_GET['date_from'] ?? '', $today);
$toDate = dl_valid_date($_GET['date_to'] ?? '', $fromDate);
if ($toDate < $fromDate) {
    json_error('To Date cannot be earlier than From Date.', 422);
}
$accountId = max(0, (int)($_GET['account_id'] ?? 0));
$type = trim((string)($_GET['transaction_type'] ?? ''));
$validTypes = ['', 'customer_payment','sales_receipt','sales_refund','purchase_payment','supplier_payment','expense_payment'];
if (!in_array($type, $validTypes, true)) {
    $type = '';
}

$opening = round(dl_opening_balance($branchId, $accountId, $fromDate) + dl_movement_before($branchId, $accountId, $fromDate), 2);
$rows = dl_collect($branchId, $accountId, $fromDate, $toDate);
if ($type !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($type): bool {
        return (string)$row['transaction_type'] === $type;
    }));
}

$running = $opening;
$totalIn = 0.0;
$totalOut = 0.0;
foreach ($rows as &$row) {
    $totalIn = round($totalIn + (float)$row['money_in'], 2);
    $totalOut = round($totalOut + (float)$row['money_out'], 2);
    $running = round($running + (float)$row['money_in'] - (float)$row['money_out'], 2);
    $row['balance'] = $running;
    $time = substr((string)$row['created_at'], 11, 8);
    $row['date_display'] = (string)$row['business_date'] . ($time !== '' ? ' ' . $time : '');
    $row['mode_account'] = trim((string)$row['mode']) . ((string)$row['account'] !== '' ? ' · ' . (string)$row['account'] : '');
    unset($row['sort_key']);
}
unset($row);

json_success('Daily Ledger loaded.', [
    'allowed_actions' => $access['actions'],
    'transactions' => $rows,
    'summary' => [
        'opening_balance' => $opening,
        'money_in' => $totalIn,
        'money_out' => $totalOut,
        'net_movement' => round($totalIn - $totalOut, 2),
        'closing_balance' => $running,
    ],
    'date_from' => $fromDate,
    'date_to' => $toDate,
]);
