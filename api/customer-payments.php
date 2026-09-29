<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/*
 * AMIRTHAM - Customer Payment / Settlement API
 *
 * Customer Payment storage:
 *   food_customer_payments          = one logical payment header
 *   food_customer_payment_details   = Cash / UPI / Bank / Cheque split rows
 *   food_payment_methods            = payment method master
 *   food_customer_payment_allocations = invoice/opening-balance settlement
 *   food_customer_credit_ledger     = customer credit movement
 *
 * payment_type: 1 Opening Balance, 2 Overall FIFO, 3 Particular Invoice
 * allocation_type: 1 Invoice, 2 Opening Balance
 * Settlement priority: actual payment -> existing Customer Credit -> Settlement Discount.
 * Overall target order: oldest Final Invoice first, Opening Balance last.
 *
 * There is no public/manual Reverse action.
 * Edit/Delete internally undo the old allocation/detail/credit effects inside
 * the same database transaction before rebuilding or soft-deleting the header.
 */

function cp_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Customer Payment is available only for tenant users.', 403);
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

function cp_require_schema(): void
{
    $tables = [
        'food_customers',
        'food_sales',
        'food_sale_returns',
        'accounts',
        'food_payment_methods',
        'food_customer_payments',
        'food_customer_payment_details',
        'food_customer_payment_allocations',
        'food_customer_credit_ledger',
    ];

    foreach ($tables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error(
                'Customer Payment schema is incomplete. Missing table: ' . $table .
                '. Run sql/customer-payment-credit-schema.sql.',
                500
            );
        }
    }

    $columns = [
        'food_payment_methods' => [
            'method_code', 'method_name', 'account_type',
            'requires_reference', 'requires_cheque_details',
        ],
        'food_customer_payments' => [
            'payment_no', 'payment_type', 'discount_type', 'discount_value',
            'discount_amount', 'credit_applied', 'source_sale_id',
        ],
        'food_customer_payment_details' => [
            'customer_payment_id', 'branch_id', 'payment_method_id', 'account_id',
            'amount', 'payment_reference', 'cheque_no', 'cheque_date',
        ],
        'food_customer_payment_allocations' => [
            'allocation_type', 'credit_amount', 'discount_amount',
        ],
    ];

    foreach ($columns as $table => $list) {
        foreach ($list as $column) {
            $stmt = db()->query(
                'SHOW COLUMNS FROM `' . $table . '` LIKE ' . db()->quote($column)
            );
            if (!$stmt || !$stmt->fetchColumn()) {
                json_error(
                    'Customer Payment schema update is required. Missing column: ' .
                    $table . '.' . $column .
                    '. Run sql/customer-payment-credit-schema.sql.',
                    500
                );
            }
        }
    }
}

function cp_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error($label . ' reference is required.', 422);
    }

    try {
        $id = (int)decryptReference(trim($value), $purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    if ($id < 1) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }

    return $id;
}

function cp_type_ref(int $type): string
{
    return encryptReference('customer_payment_type', $type);
}

function cp_type_from_ref($value): int
{
    $type = cp_ref_to_id($value, 'customer_payment_type', 'Payment Type');
    if (!in_array($type, [1, 2, 3], true)) {
        json_error(
            'Invalid Payment Type.',
            422,
            ['payment_type_ref' => 'Select a valid Payment Type.']
        );
    }
    return $type;
}

function cp_type_label(int $type): string
{
    return [
        1 => 'Opening Balance',
        2 => 'Overall Outstanding',
        3 => 'Particular Invoice',
    ][$type] ?? 'Unknown';
}

function cp_valid_date($value, string $field = 'payment_date', bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if (!$required) {
            return null;
        }
        $label = $field === 'cheque_date' ? 'Cheque Date' : 'Payment Date';
        json_error($label . ' is required.', 422, [$field => $label . ' is required.']);
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (
        !$date ||
        ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) ||
        $date->format('Y-m-d') !== $value
    ) {
        $label = $field === 'cheque_date' ? 'Cheque Date' : 'Payment Date';
        json_error('Enter a valid ' . $label . '.', 422, [$field => 'Enter a valid ' . $label . '.']);
    }

    return $value;
}

function cp_nullable($value, int $max = 255): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $max);
}

function cp_money($value, string $label, string $field): float
{
    if ($value === '' || $value === null) {
        return 0.0;
    }
    if (!is_numeric($value)) {
        json_error($label . ' must be a valid amount.', 422, [$field => 'Enter a valid amount.']);
    }
    $amount = round((float)$value, 2);
    if ($amount < 0) {
        json_error($label . ' cannot be negative.', 422, [$field => 'Enter zero or greater.']);
    }
    return $amount;
}

function cp_customer(PDO $pdo, int $branchId, int $customerId, bool $active = true): array
{
    $sql = 'SELECT id,customer_code,customer_name,mobile,opening_balance,status
            FROM food_customers
            WHERE id=:id AND branch_id=:branch_id';
    if ($active) {
        $sql .= ' AND status=1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $customerId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error(
            'Selected Customer is inactive or unavailable.',
            422,
            ['customer_ref' => 'Select an active Customer.']
        );
    }

    $row['id'] = (int)$row['id'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function cp_customers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,customer_code,customer_name,mobile
         FROM food_customers
         WHERE branch_id=:branch_id AND status=1
         ORDER BY customer_name,customer_code'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'ref' => encryptReference('customer', (int)$r['id']),
            'customer_code' => (string)$r['customer_code'],
            'customer_name' => (string)$r['customer_name'],
            'mobile' => (string)($r['mobile'] ?? ''),
        ];
    }
    return $rows;
}

function cp_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,is_default_cash
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'account_code' => (string)$r['account_code'],
            'account_name' => (string)$r['account_name'],
            'account_type' => (int)$r['account_type'],
            'is_default_cash' => (int)($r['is_default_cash'] ?? 0),
        ];
    }
    return $rows;
}

function cp_account(PDO $pdo, int $branchId, int $accountId, int $requiredType): array
{
    $stmt = $pdo->prepare(
        'SELECT id,account_type,status
         FROM accounts
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['status'] !== 1) {
        json_error(
            'Selected Account is inactive or unavailable.',
            422,
            ['account_id' => 'Select an active Account.']
        );
    }

    if ((int)$row['account_type'] !== $requiredType) {
        $msg = $requiredType === 1
            ? 'This payment method requires a Cash Account.'
            : 'This payment method requires a Bank Account.';
        json_error($msg, 422, ['account_id' => $msg]);
    }

    return $row;
}

function cp_payment_methods(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,method_code,method_name,account_type,requires_reference,requires_cheque_details
         FROM food_payment_methods
         WHERE branch_id=:branch_id AND status=1
         ORDER BY sort_order,id'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'method_code' => (string)$r['method_code'],
            'method_name' => (string)$r['method_name'],
            'account_type' => (int)$r['account_type'],
            'requires_reference' => (int)$r['requires_reference'],
            'requires_cheque_details' => (int)$r['requires_cheque_details'],
        ];
    }
    return $rows;
}

function cp_payment_method(PDO $pdo, int $branchId, int $methodId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,method_code,method_name,account_type,requires_reference,requires_cheque_details,status
         FROM food_payment_methods
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $methodId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['status'] !== 1) {
        json_error('Selected Payment Method is inactive or unavailable.', 422);
    }

    $row['id'] = (int)$row['id'];
    $row['account_type'] = (int)$row['account_type'];
    $row['requires_reference'] = (int)$row['requires_reference'];
    $row['requires_cheque_details'] = (int)$row['requires_cheque_details'];
    return $row;
}

function cp_parse_payment_details(PDO $pdo, int $branchId, array $data): array
{
    $rawRows = $data['payments'] ?? [];
    if (!is_array($rawRows)) {
        json_error('Payment allocation must be a list.', 422);
    }

    $rows = [];
    $seen = [];
    $total = 0.0;

    foreach ($rawRows as $index => $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $amount = cp_money($raw['amount'] ?? '', 'Payment Amount', 'payments');
        if ($amount <= 0.001) {
            continue;
        }

        $methodId = (int)($raw['payment_method_id'] ?? 0);
        if ($methodId < 1) {
            json_error('Select a Payment Method for every entered amount.', 422);
        }
        if (isset($seen[$methodId])) {
            json_error('Only one allocation row is allowed for each Payment Method.', 422);
        }
        $seen[$methodId] = true;

        $method = cp_payment_method($pdo, $branchId, $methodId);

        $accountId = (int)($raw['account_id'] ?? 0);
        if ($accountId < 1) {
            json_error(
                $method['method_name'] . ' Account is required.',
                422,
                ['account_id' => 'Select an Account.']
            );
        }
        cp_account($pdo, $branchId, $accountId, (int)$method['account_type']);

        $reference = cp_nullable($raw['payment_reference'] ?? null, 100);
        if ((int)$method['requires_reference'] === 1 && $reference === null) {
            json_error(
                $method['method_name'] . ' Reference No is required.',
                422,
                ['payment_reference' => 'Enter the transaction reference.']
            );
        }

        $chequeNo = null;
        $chequeDate = null;
        if ((int)$method['requires_cheque_details'] === 1) {
            $chequeNo = cp_nullable($raw['cheque_no'] ?? null, 100);
            if ($chequeNo === null) {
                json_error(
                    'Cheque Number is required when Cheque Amount is entered.',
                    422,
                    ['cheque_no' => 'Enter Cheque Number.']
                );
            }
            $chequeDate = cp_valid_date($raw['cheque_date'] ?? '', 'cheque_date', true);
        }

        $rows[] = [
            'payment_method_id' => $methodId,
            'account_id' => $accountId,
            'amount' => $amount,
            'payment_reference' => $reference,
            'cheque_no' => $chequeNo,
            'cheque_date' => $chequeDate,
        ];
        $total = round($total + $amount, 2);
    }

    return ['rows' => $rows, 'total' => $total];
}

function cp_credit_balance(PDO $pdo, int $branchId, int $customerId, int $excludePaymentId = 0): float
{
    $sql = 'SELECT COALESCE(SUM(amount_in-amount_out),0)
            FROM food_customer_credit_ledger
            WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1';
    $params = [':branch_id' => $branchId, ':customer_id' => $customerId];

    if ($excludePaymentId > 0) {
        $sql .= " AND NOT (reference_type='customer_payment' AND reference_id=:exclude_id)";
        $params[':exclude_id'] = $excludePaymentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function cp_invoice_rows(
    PDO $pdo,
    int $branchId,
    int $customerId,
    int $excludePaymentId = 0,
    ?int $onlySaleId = null
): array {
    $excludeWhere = '';
    $params = [':branch_id' => $branchId, ':customer_id' => $customerId];

    if ($excludePaymentId > 0) {
        $excludeWhere = ' AND a.customer_payment_id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludePaymentId;
    }

    $only = '';
    if ($onlySaleId !== null) {
        $only = ' AND s.id=:sale_id';
        $params[':sale_id'] = $onlySaleId;
    }

    $sql = "SELECT s.id,s.sales_no,s.invoice_date,s.grand_total,
                   COALESCE((SELECT SUM(sr.grand_total)
                             FROM food_sale_returns sr
                             WHERE sr.branch_id=s.branch_id
                               AND sr.sale_id=s.id
                               AND sr.posting_status=1
                               AND sr.status=1),0) AS return_total,
                   COALESCE((SELECT SUM(a.allocated_amount+a.credit_amount+a.discount_amount)
                             FROM food_customer_payment_allocations a
                             INNER JOIN food_customer_payments p
                                     ON p.id=a.customer_payment_id
                                    AND p.branch_id=a.branch_id
                             WHERE a.branch_id=s.branch_id
                               AND a.sale_id=s.id
                               AND a.allocation_type=1
                               AND a.status=1
                               AND p.posting_status=1
                               AND p.status=1
                               AND p.reversed_at IS NULL{$excludeWhere}),0) AS settled_total
            FROM food_sales s
            WHERE s.branch_id=:branch_id
              AND s.customer_id=:customer_id
              AND s.document_type=4
              AND s.posting_status=1
              AND s.status=1
              AND s.reversed_at IS NULL{$only}
            ORDER BY s.invoice_date ASC,s.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $net = max(0.0, round((float)$r['grand_total'] - (float)$r['return_total'], 2));
        $settled = round((float)$r['settled_total'], 2);
        $pending = max(0.0, round($net - $settled, 2));
        if ($pending <= 0.001) {
            continue;
        }

        $rows[] = [
            'sale_id' => (int)$r['id'],
            'sale_ref' => encryptReference('sale', (int)$r['id']),
            'sales_no' => (string)$r['sales_no'],
            'invoice_date' => (string)$r['invoice_date'],
            'net_total' => $net,
            'settled_total' => $settled,
            'pending' => $pending,
        ];
    }

    return $rows;
}

function cp_opening_pending(PDO $pdo, int $branchId, int $customerId, int $excludePaymentId = 0): float
{
    $customer = cp_customer($pdo, $branchId, $customerId, false);
    $sql = 'SELECT COALESCE(SUM(a.allocated_amount+a.credit_amount+a.discount_amount),0)
            FROM food_customer_payment_allocations a
            INNER JOIN food_customer_payments p
                    ON p.id=a.customer_payment_id
                   AND p.branch_id=a.branch_id
            WHERE a.branch_id=:branch_id
              AND p.customer_id=:customer_id
              AND a.allocation_type=2
              AND a.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL';
    $params = [':branch_id' => $branchId, ':customer_id' => $customerId];

    if ($excludePaymentId > 0) {
        $sql .= ' AND a.customer_payment_id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludePaymentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $settled = (float)$stmt->fetchColumn();
    return max(0.0, round((float)$customer['opening_balance'] - $settled, 2));
}

function cp_customer_context(PDO $pdo, int $branchId, int $customerId, int $excludePaymentId = 0): array
{
    $customer = cp_customer($pdo, $branchId, $customerId, false);
    $invoices = cp_invoice_rows($pdo, $branchId, $customerId, $excludePaymentId);
    $opening = cp_opening_pending($pdo, $branchId, $customerId, $excludePaymentId);

    $invoiceTotal = 0.0;
    foreach ($invoices as $r) {
        $invoiceTotal += (float)$r['pending'];
    }

    return [
        'customer' => [
            'id' => $customerId,
            'ref' => encryptReference('customer', $customerId),
            'customer_code' => (string)$customer['customer_code'],
            'customer_name' => (string)$customer['customer_name'],
            'mobile' => (string)($customer['mobile'] ?? ''),
        ],
        'invoices' => $invoices,
        'invoice_outstanding' => round($invoiceTotal, 2),
        'opening_outstanding' => $opening,
        'overall_outstanding' => round($invoiceTotal + $opening, 2),
        'available_credit' => max(0.0, cp_credit_balance($pdo, $branchId, $customerId, $excludePaymentId)),
    ];
}

function cp_target_rows(
    PDO $pdo,
    int $branchId,
    int $customerId,
    int $paymentType,
    int $excludePaymentId = 0,
    ?int $invoiceId = null
): array {
    if ($paymentType === 1) {
        $opening = cp_opening_pending($pdo, $branchId, $customerId, $excludePaymentId);
        return $opening > 0.001 ? [[
            'allocation_type' => 2,
            'sale_id' => null,
            'sale_ref' => null,
            'label' => 'Opening Balance',
            'date' => '',
            'pending' => $opening,
        ]] : [];
    }

    if ($paymentType === 3) {
        if (!$invoiceId) {
            json_error('Particular Invoice is required.', 422, ['invoice_ref' => 'Select an Invoice.']);
        }
        $rows = cp_invoice_rows($pdo, $branchId, $customerId, $excludePaymentId, $invoiceId);
        if (!$rows) {
            json_error(
                'Selected Invoice has no pending amount.',
                422,
                ['invoice_ref' => 'Select a pending Final Invoice.']
            );
        }
        $r = $rows[0];
        return [[
            'allocation_type' => 1,
            'sale_id' => $r['sale_id'],
            'sale_ref' => $r['sale_ref'],
            'label' => $r['sales_no'],
            'date' => $r['invoice_date'],
            'pending' => $r['pending'],
        ]];
    }

    $targets = [];
    foreach (cp_invoice_rows($pdo, $branchId, $customerId, $excludePaymentId) as $r) {
        $targets[] = [
            'allocation_type' => 1,
            'sale_id' => $r['sale_id'],
            'sale_ref' => $r['sale_ref'],
            'label' => $r['sales_no'],
            'date' => $r['invoice_date'],
            'pending' => $r['pending'],
        ];
    }

    $opening = cp_opening_pending($pdo, $branchId, $customerId, $excludePaymentId);
    if ($opening > 0.001) {
        $targets[] = [
            'allocation_type' => 2,
            'sale_id' => null,
            'sale_ref' => null,
            'label' => 'Opening Balance',
            'date' => '',
            'pending' => $opening,
        ];
    }

    return $targets;
}

function cp_build_settlement(
    PDO $pdo,
    int $branchId,
    int $customerId,
    int $paymentType,
    array $data,
    float $actualPayment,
    int $excludePaymentId = 0
): array {
    $invoiceId = null;
    if ($paymentType === 3) {
        $invoiceId = cp_ref_to_id($data['invoice_ref'] ?? '', 'sale', 'Invoice');
    }

    $targets = cp_target_rows(
        $pdo,
        $branchId,
        $customerId,
        $paymentType,
        $excludePaymentId,
        $invoiceId
    );
    if (!$targets) {
        if ($paymentType === 1) {
            json_error('This Customer has no pending Opening Balance.', 422);
        }
        if ($paymentType === 2) {
            json_error('This Customer has no pending Final Invoice or Opening Balance.', 422);
        }
        json_error('There is no pending amount for the selected Payment Type.', 422);
    }

    $targetTotal = 0.0;
    foreach ($targets as $t) {
        $targetTotal += (float)$t['pending'];
    }
    $targetTotal = round($targetTotal, 2);
    $payment = round(max(0.0, $actualPayment), 2);

    $credit = cp_money($data['credit_applied'] ?? '', 'Credit Applied', 'credit_applied');
    $availableCredit = max(0.0, cp_credit_balance($pdo, $branchId, $customerId, $excludePaymentId));
    if ($credit > $availableCredit + 0.001) {
        json_error(
            'Credit Applied exceeds available Customer Credit.',
            422,
            ['credit_applied' => 'Maximum available credit is ' . number_format($availableCredit, 2, '.', '') . '.']
        );
    }

    $discountType = null;
    $discountValue = cp_money($data['discount_value'] ?? '', 'Discount Value', 'discount_value');
    $discountAmount = 0.0;

    if ($discountValue > 0) {
        $discountType = (int)($data['discount_type'] ?? 0);
        if (!in_array($discountType, [1, 2], true)) {
            json_error(
                'Select Discount Type.',
                422,
                ['discount_type' => 'Select Percentage or Fixed.']
            );
        }
        if ($discountType === 1) {
            if ($discountValue > 100) {
                json_error(
                    'Discount Percentage cannot exceed 100.',
                    422,
                    ['discount_value' => 'Maximum percentage is 100.']
                );
            }
            $discountAmount = round($targetTotal * $discountValue / 100, 2);
        } else {
            $discountAmount = $discountValue;
        }
    }

    if ($payment > $targetTotal + 0.001 && ($credit > 0.001 || $discountAmount > 0.001)) {
        json_error(
            'Do not apply Customer Credit or Settlement Discount when Actual Payment already exceeds the selected outstanding.',
            422
        );
    }

    $remainingAfterPayment = max(0.0, round($targetTotal - min($payment, $targetTotal), 2));
    if ($credit > $remainingAfterPayment + 0.001) {
        json_error(
            'Credit Applied exceeds the balance remaining after Payment.',
            422,
            ['credit_applied' => 'Maximum usable credit is ' . number_format($remainingAfterPayment, 2, '.', '') . '.']
        );
    }

    $remainingAfterCredit = max(0.0, round($remainingAfterPayment - $credit, 2));
    if ($discountAmount > $remainingAfterCredit + 0.001) {
        json_error(
            'Settlement Discount exceeds the remaining outstanding.',
            422,
            ['discount_value' => 'Maximum Discount Amount is ' . number_format($remainingAfterCredit, 2, '.', '') . '.']
        );
    }

    $remainingPayment = $payment;
    $remainingCredit = $credit;
    $remainingDiscount = $discountAmount;
    $allocations = [];

    foreach ($targets as $target) {
        $pending = round((float)$target['pending'], 2);

        $paymentPart = round(min($pending, $remainingPayment), 2);
        $pending = round($pending - $paymentPart, 2);
        $remainingPayment = round($remainingPayment - $paymentPart, 2);

        $creditPart = round(min($pending, $remainingCredit), 2);
        $pending = round($pending - $creditPart, 2);
        $remainingCredit = round($remainingCredit - $creditPart, 2);

        $discountPart = round(min($pending, $remainingDiscount), 2);
        $pending = round($pending - $discountPart, 2);
        $remainingDiscount = round($remainingDiscount - $discountPart, 2);

        if ($paymentPart > 0.001 || $creditPart > 0.001 || $discountPart > 0.001) {
            $allocations[] = $target + [
                'payment_amount' => $paymentPart,
                'credit_amount' => $creditPart,
                'discount_amount' => $discountPart,
                'settled' => round($paymentPart + $creditPart + $discountPart, 2),
                'pending_after' => max(0.0, $pending),
            ];
        }
    }

    if ($remainingCredit > 0.001 || $remainingDiscount > 0.001) {
        json_error('Unable to allocate the full Credit/Discount against the selected outstanding.', 422);
    }

    $excessCredit = max(0.0, round($remainingPayment, 2));

    return [
        'targets_total' => $targetTotal,
        'actual_payment' => $payment,
        'payment_amount' => $payment,
        'credit_applied' => $credit,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_amount' => $discountAmount,
        'settled_amount' => round(min($payment, $targetTotal) + $credit + $discountAmount, 2),
        'excess_credit' => $excessCredit,
        'remaining_outstanding' => max(
            0.0,
            round($targetTotal - (min($payment, $targetTotal) + $credit + $discountAmount), 2)
        ),
        'available_credit_before' => $availableCredit,
        'available_credit_after' => round($availableCredit - $credit + $excessCredit, 2),
        'allocations' => $allocations,
        'invoice_id' => $invoiceId,
    ];
}

function cp_payment_no(PDO $pdo, int $branchId): string
{
    $lock = $pdo->prepare('SELECT id FROM branches WHERE id=:id FOR UPDATE');
    $lock->execute([':id' => $branchId]);

    $stmt = $pdo->prepare(
        "SELECT payment_no
         FROM food_customer_payments
         WHERE branch_id=:branch_id
           AND payment_no REGEXP '^CPY[0-9]+$'
         ORDER BY CAST(SUBSTRING(payment_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');

    $number = 1;
    if ($last !== '' && preg_match('/^CPY([0-9]+)$/i', $last, $match)) {
        $number = (int)$match[1] + 1;
    }

    return 'CPY' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

function cp_refresh_sale(PDO $pdo, int $branchId, int $saleId): void
{
    $stmt = $pdo->prepare(
        'SELECT grand_total
         FROM food_sales
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $saleId, ':branch_id' => $branchId]);
    $grand = $stmt->fetchColumn();
    if ($grand === false) {
        return;
    }

    $ret = $pdo->prepare(
        'SELECT COALESCE(SUM(grand_total),0)
         FROM food_sale_returns
         WHERE branch_id=:branch_id
           AND sale_id=:sale_id
           AND posting_status=1
           AND status=1'
    );
    $ret->execute([':branch_id' => $branchId, ':sale_id' => $saleId]);
    $net = max(0.0, round((float)$grand - (float)$ret->fetchColumn(), 2));

    $alloc = $pdo->prepare(
        'SELECT
            COALESCE(SUM(a.allocated_amount+a.credit_amount),0) AS paid_value,
            COALESCE(SUM(a.allocated_amount+a.credit_amount+a.discount_amount),0) AS settled_value
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
                 ON p.id=a.customer_payment_id
                AND p.branch_id=a.branch_id
         WHERE a.branch_id=:branch_id
           AND a.sale_id=:sale_id
           AND a.allocation_type=1
           AND a.status=1
           AND p.posting_status=1
           AND p.status=1
           AND p.reversed_at IS NULL'
    );
    $alloc->execute([':branch_id' => $branchId, ':sale_id' => $saleId]);
    $values = $alloc->fetch() ?: ['paid_value' => 0, 'settled_value' => 0];

    $paid = min($net, round((float)$values['paid_value'], 2));
    $balance = max(0.0, round($net - (float)$values['settled_value'], 2));
    $status = $balance <= 0.001 ? 3 : ($paid > 0.001 ? 2 : 1);

    $update = $pdo->prepare(
        'UPDATE food_sales
         SET paid_amount=:paid,balance_amount=:balance,payment_status=:status,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $update->execute([
        ':paid' => $paid,
        ':balance' => $balance,
        ':status' => $status,
        ':id' => $saleId,
        ':branch_id' => $branchId,
    ]);
}

function cp_payment_record(PDO $pdo, int $branchId, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*,c.customer_code,c.customer_name
         FROM food_customer_payments p
         INNER JOIN food_customers c
                 ON c.id=p.customer_id
                AND c.branch_id=p.branch_id
         WHERE p.id=:id
           AND p.branch_id=:branch_id
           AND p.payment_no IS NOT NULL
           AND TRIM(p.payment_no)<>\'\'
           AND p.source_sale_id IS NULL
           AND p.status=1
           AND p.reversed_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $record = $stmt->fetch();

    if (!$record) {
        json_error('Customer Payment was not found.', 404);
    }

    $record['id'] = (int)$record['id'];
    $record['customer_id'] = (int)$record['customer_id'];
    $record['payment_type'] = (int)$record['payment_type'];
    foreach (['amount', 'discount_value', 'discount_amount', 'credit_applied'] as $key) {
        $record[$key] = (float)$record[$key];
    }

    $record['ref'] = encryptReference('customer_payment', (int)$record['id']);
    $record['customer_ref'] = encryptReference('customer', (int)$record['customer_id']);
    $record['payment_type_ref'] = cp_type_ref((int)$record['payment_type']);

    $details = $pdo->prepare(
        'SELECT d.id,d.payment_method_id,d.account_id,d.amount,d.payment_reference,d.cheque_no,d.cheque_date,
                pm.method_code,pm.method_name,pm.account_type,pm.requires_reference,pm.requires_cheque_details,
                a.account_code,a.account_name
         FROM food_customer_payment_details d
         INNER JOIN food_payment_methods pm
                 ON pm.id=d.payment_method_id
                AND pm.branch_id=d.branch_id
         INNER JOIN accounts a
                 ON a.id=d.account_id
                AND a.branch_id=d.branch_id
         WHERE d.customer_payment_id=:payment_id
           AND d.branch_id=:branch_id
           AND d.status=1
         ORDER BY pm.sort_order,d.id'
    );
    $details->execute([':payment_id' => $id, ':branch_id' => $branchId]);

    $paymentRows = [];
    foreach ($details->fetchAll() as $row) {
        $paymentRows[] = [
            'id' => (int)$row['id'],
            'payment_method_id' => (int)$row['payment_method_id'],
            'method_code' => (string)$row['method_code'],
            'method_name' => (string)$row['method_name'],
            'account_id' => (int)$row['account_id'],
            'account_name' => (string)$row['account_name'],
            'amount' => (float)$row['amount'],
            'payment_reference' => (string)($row['payment_reference'] ?? ''),
            'cheque_no' => (string)($row['cheque_no'] ?? ''),
            'cheque_date' => (string)($row['cheque_date'] ?? ''),
        ];
    }
    $record['payments'] = $paymentRows;

    $alloc = $pdo->prepare(
        'SELECT allocation_type,sale_id,allocated_amount,credit_amount,discount_amount
         FROM food_customer_payment_allocations
         WHERE customer_payment_id=:payment_id
           AND branch_id=:branch_id
           AND status=1
         ORDER BY id'
    );
    $alloc->execute([':payment_id' => $id, ':branch_id' => $branchId]);

    $allocations = [];
    $invoiceRef = '';
    $invoiceId = null;
    foreach ($alloc->fetchAll() as $row) {
        $row['allocation_type'] = (int)$row['allocation_type'];
        $row['sale_id'] = $row['sale_id'] === null ? null : (int)$row['sale_id'];
        foreach (['allocated_amount', 'credit_amount', 'discount_amount'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        if ((int)$record['payment_type'] === 3 && $row['sale_id']) {
            $invoiceId = (int)$row['sale_id'];
            $invoiceRef = encryptReference('sale', $invoiceId);
        }
        $allocations[] = $row;
    }

    $record['allocations'] = $allocations;
    $record['invoice_id'] = $invoiceId;
    $record['invoice_ref'] = $invoiceRef;
    return $record;
}

function cp_insert_payment_details(
    PDO $pdo,
    int $branchId,
    int $paymentId,
    int $userId,
    array $rows
): void {
    if (!$rows) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO food_customer_payment_details
         (customer_payment_id,branch_id,payment_method_id,account_id,amount,payment_reference,cheque_no,cheque_date,status,created_by,created_at,updated_at)
         VALUES
         (:customer_payment_id,:branch_id,:payment_method_id,:account_id,:amount,:payment_reference,:cheque_no,:cheque_date,1,:created_by,NOW(),NOW())'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            ':customer_payment_id' => $paymentId,
            ':branch_id' => $branchId,
            ':payment_method_id' => $row['payment_method_id'],
            ':account_id' => $row['account_id'],
            ':amount' => $row['amount'],
            ':payment_reference' => $row['payment_reference'],
            ':cheque_no' => $row['cheque_no'],
            ':cheque_date' => $row['cheque_date'],
            ':created_by' => $userId,
        ]);
    }
}


function cp_payment_effect_sale_ids(PDO $pdo, int $branchId, int $paymentId): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT sale_id
         FROM food_customer_payment_allocations
         WHERE customer_payment_id=:payment_id
           AND branch_id=:branch_id
           AND sale_id IS NOT NULL'
    );
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':branch_id' => $branchId,
    ]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $saleId) {
        $ids[(int)$saleId] = true;
    }
    return $ids;
}

function cp_is_pos_payment_row(array $payment): bool
{
    if ((int)($payment['source_sale_id'] ?? 0) > 0) {
        return true;
    }

    $paymentNo = strtoupper(trim((string)($payment['payment_no'] ?? '')));
    if ($paymentNo !== '' && strpos($paymentNo, 'POS-') === 0) {
        return true;
    }

    $notes = strtoupper(trim((string)($payment['notes'] ?? '')));
    return strpos($notes, 'POS PAYMENT FOR SALE ID ') === 0
        || strpos($notes, 'POS RECEIPT FOR ') === 0;
}

/*
 * Internal undo only.
 *
 * There is intentionally no public/manual "Reverse Payment" action.
 * Edit calls this before rebuilding the payment from the new form values.
 * Delete calls this before soft-deleting the payment header.
 */
function cp_undo_payment_effects(
    PDO $pdo,
    int $branchId,
    int $paymentId,
    bool $removeChildren
): array {
    $saleIds = cp_payment_effect_sale_ids($pdo, $branchId, $paymentId);

    if ($removeChildren) {
        $pdo->prepare(
            'DELETE FROM food_customer_payment_allocations
             WHERE customer_payment_id=:payment_id AND branch_id=:branch_id'
        )->execute([
            ':payment_id' => $paymentId,
            ':branch_id' => $branchId,
        ]);

        $pdo->prepare(
            'DELETE FROM food_customer_payment_details
             WHERE customer_payment_id=:payment_id AND branch_id=:branch_id'
        )->execute([
            ':payment_id' => $paymentId,
            ':branch_id' => $branchId,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE food_customer_payment_allocations
             SET status=0,updated_at=NOW()
             WHERE customer_payment_id=:payment_id
               AND branch_id=:branch_id
               AND status=1'
        )->execute([
            ':payment_id' => $paymentId,
            ':branch_id' => $branchId,
        ]);

        $pdo->prepare(
            'UPDATE food_customer_payment_details
             SET status=0,updated_at=NOW()
             WHERE customer_payment_id=:payment_id
               AND branch_id=:branch_id
               AND status=1'
        )->execute([
            ':payment_id' => $paymentId,
            ':branch_id' => $branchId,
        ]);
    }

    /*
     * Undo this payment's Customer Credit effect by making only its own
     * ledger rows inactive. Other Customer Payments / POS credits are untouched.
     */
    $pdo->prepare(
        "UPDATE food_customer_credit_ledger
         SET status=0,updated_at=NOW()
         WHERE branch_id=:branch_id
           AND reference_type='customer_payment'
           AND reference_id=:payment_id
           AND status=1"
    )->execute([
        ':branch_id' => $branchId,
        ':payment_id' => $paymentId,
    ]);

    return $saleIds;
}

$method = request_method();
cp_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('customer-payment.php', ACTION_VIEW);
    $ctx = cp_tenant_context($access['user']);

    $payload = [
        'allowed_actions' => $access['actions'],
        'customers' => cp_customers($ctx['branch_id']),
        'accounts' => cp_accounts($ctx['branch_id']),
        'payment_methods' => cp_payment_methods($ctx['branch_id']),
        'payment_types' => [
            ['type' => 1, 'value' => cp_type_ref(1), 'label' => 'Opening Balance'],
            ['type' => 2, 'value' => cp_type_ref(2), 'label' => 'Overall Outstanding'],
            ['type' => 3, 'value' => cp_type_ref(3), 'label' => 'Particular Invoice'],
        ],
        'today' => date('Y-m-d'),
    ];

    if (!empty($_GET['customer_ref'])) {
        $customerId = cp_ref_to_id($_GET['customer_ref'], 'customer', 'Customer');
        $payload['customer_context'] = cp_customer_context(
            db(),
            $ctx['branch_id'],
            $customerId
        );
    }

    json_success('Customer Payment options loaded.', $payload);
}

if ($method === 'GET' && isset($_GET['customer_context'])) {
    $access = require_permission('customer-payment.php', ACTION_VIEW);
    $ctx = cp_tenant_context($access['user']);
    $customerId = cp_ref_to_id($_GET['customer_ref'] ?? '', 'customer', 'Customer');

    $excludePaymentId = 0;
    if (!empty($_GET['payment_ref'])) {
        $excludePaymentId = cp_ref_to_id(
            $_GET['payment_ref'],
            'customer_payment',
            'Customer Payment'
        );
    }

    json_success(
        'Customer outstanding loaded.',
        cp_customer_context(db(), $ctx['branch_id'], $customerId, $excludePaymentId)
    );
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('customer-payment.php', ACTION_VIEW);
    $ctx = cp_tenant_context($access['user']);
    $id = cp_ref_to_id($_GET['ref'], 'customer_payment', 'Customer Payment');
    $record = cp_payment_record(db(), $ctx['branch_id'], $id);

    json_success('Customer Payment loaded.', [
        'payment' => $record,
        'allowed_actions' => $access['actions'],
        'accounts' => cp_accounts($ctx['branch_id']),
        'payment_methods' => cp_payment_methods($ctx['branch_id']),
        'payment_types' => [
            ['type' => 1, 'value' => cp_type_ref(1), 'label' => 'Opening Balance'],
            ['type' => 2, 'value' => cp_type_ref(2), 'label' => 'Overall Outstanding'],
            ['type' => 3, 'value' => cp_type_ref(3), 'label' => 'Particular Invoice'],
        ],
        'customer_context' => cp_customer_context(
            db(),
            $ctx['branch_id'],
            (int)$record['customer_id'],
            $id
        ),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = (string)($data['action'] ?? 'save');

    if ($action === 'preview') {
        $access = require_permission('customer-payment.php', ACTION_VIEW);
        $ctx = cp_tenant_context($access['user']);
        $pdo = db();

        $customerId = cp_ref_to_id($data['customer_ref'] ?? '', 'customer', 'Customer');
        cp_customer($pdo, $ctx['branch_id'], $customerId, true);
        $paymentType = cp_type_from_ref($data['payment_type_ref'] ?? '');

        $excludePaymentId = 0;
        if (!empty($data['ref'])) {
            $excludePaymentId = cp_ref_to_id(
                $data['ref'],
                'customer_payment',
                'Customer Payment'
            );
        }

        $parsedPayments = cp_parse_payment_details($pdo, $ctx['branch_id'], $data);
        $calc = cp_build_settlement(
            $pdo,
            $ctx['branch_id'],
            $customerId,
            $paymentType,
            $data,
            $parsedPayments['total'],
            $excludePaymentId
        );

        json_success('Settlement preview calculated.', $calc);
    }

    if ($action === 'delete') {
        /*
         * Customer Payment List is the common register for both manual Customer
         * Payments and Sales POS receipts. Load the payment first, then apply
         * the owning page permission:
         *   - Manual Customer Payment -> Customer Payment Delete
         *   - Sales POS payment       -> Sales Update
         */
        $viewAccess = require_permission('customer-payment-list.php', ACTION_VIEW);
        $ctx = cp_tenant_context($viewAccess['user']);
        $branchId = (int)$ctx['branch_id'];
        $paymentId = cp_ref_to_id(
            $data['ref'] ?? '',
            'customer_payment',
            'Customer Payment'
        );

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM food_customer_payments
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND status=1
                   AND reversed_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);
            $payment = $stmt->fetch();

            if (!$payment) {
                json_error('Payment was not found or is already deleted.', 404);
            }

            $isPos = cp_is_pos_payment_row($payment);
            if ($isPos) {
                require_permission('sales.php', ACTION_UPDATE);
            } else {
                $deleteAction = defined('ACTION_DELETE') ? ACTION_DELETE : 4;
                require_permission('customer-payment.php', $deleteAction);
            }

            $customerId = (int)($payment['customer_id'] ?? 0);
            if ($customerId > 0) {
                $creditWithoutPayment = cp_credit_balance(
                    $pdo,
                    $branchId,
                    $customerId,
                    $paymentId
                );
                if ($creditWithoutPayment < -0.001) {
                    json_error(
                        'This Payment cannot be deleted because Customer Credit created by it has already been used.',
                        409
                    );
                }
            }

            $saleIds = cp_undo_payment_effects(
                $pdo,
                $branchId,
                $paymentId,
                false
            );

            /* POS headers own a Final Invoice even when an old build created
               the receipt without an active allocation row. Refresh it too. */
            $sourceSaleId = (int)($payment['source_sale_id'] ?? 0);
            if ($sourceSaleId > 0) {
                $saleIds[$sourceSaleId] = true;
            }

            $pdo->prepare(
                'UPDATE food_customer_payments
                 SET posting_status=0,status=0,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);

            foreach (array_keys($saleIds) as $saleId) {
                cp_refresh_sale($pdo, $branchId, (int)$saleId);
            }

            $pdo->commit();
            json_success($isPos
                ? 'Sales POS Payment deleted and Final Invoice recalculated successfully.'
                : 'Customer Payment deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Customer Payment action.', 404);
    }

    $isEdit = !empty($data['ref']);
    $access = require_permission('customer-payment.php', $isEdit ? ACTION_UPDATE : 29);
    $ctx = cp_tenant_context($access['user']);
    $branchId = $ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $paymentId = 0;
        $paymentNo = '';
        $oldSaleIds = [];

        $oldCustomerId = 0;
        $creditWithoutOldPayment = 0.0;

        if ($isEdit) {
            $paymentId = cp_ref_to_id(
                $data['ref'],
                'customer_payment',
                'Customer Payment'
            );

            $stmt = $pdo->prepare(
                'SELECT *
                 FROM food_customer_payments
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND source_sale_id IS NULL
                   AND payment_no IS NOT NULL
                   AND TRIM(payment_no)<>\'\'
                   AND status=1
                   AND reversed_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);
            $old = $stmt->fetch();

            if (!$old) {
                json_error('Customer Payment was not found.', 404);
            }

            $oldCustomerId = (int)$old['customer_id'];
            $paymentNo = (string)$old['payment_no'];

            /*
             * Build/validate the edited settlement against a virtual base state
             * where this payment does not exist. Do NOT remove the old rows yet.
             * This keeps Edit rollback-safe and also lets Preview and Save use the
             * exact same outstanding/credit calculation.
             */
            $creditWithoutOldPayment = cp_credit_balance(
                $pdo,
                $branchId,
                $oldCustomerId,
                $paymentId
            );
        }

        $customerId = cp_ref_to_id($data['customer_ref'] ?? '', 'customer', 'Customer');
        cp_customer($pdo, $branchId, $customerId, true);

        if (
            $isEdit &&
            $oldCustomerId > 0 &&
            $oldCustomerId !== $customerId &&
            $creditWithoutOldPayment < -0.001
        ) {
            json_error(
                'Customer cannot be changed because credit created by the original Payment has already been used.',
                409
            );
        }

        $paymentType = cp_type_from_ref($data['payment_type_ref'] ?? '');
        $paymentDate = cp_valid_date($data['payment_date'] ?? '');

        $parsedPayments = cp_parse_payment_details($pdo, $branchId, $data);
        $actualPayment = (float)$parsedPayments['total'];
        $calc = cp_build_settlement(
            $pdo,
            $branchId,
            $customerId,
            $paymentType,
            $data,
            $actualPayment,
            $isEdit ? $paymentId : 0
        );

        if (
            $actualPayment <= 0.001 &&
            $calc['credit_applied'] <= 0.001 &&
            $calc['discount_amount'] <= 0.001
        ) {
            json_error(
                'Enter at least one Payment Amount, Customer Credit or Settlement Discount.',
                422
            );
        }

        if ($paymentNo === '') {
            $paymentNo = cp_payment_no($pdo, $branchId);
        }
        $notes = cp_nullable($data['notes'] ?? null, 255);

        /*
         * Only after the edited values have passed every settlement validation do
         * we remove the old effect. From this point the same transaction rebuilds
         * the header children, allocations and credit ledger from scratch.
         */
        if ($isEdit) {
            $oldSaleIds = cp_undo_payment_effects(
                $pdo,
                $branchId,
                $paymentId,
                true
            );
        }

        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE food_customer_payments
                 SET customer_id=:customer_id,
                     payment_no=:payment_no,
                     payment_date=:payment_date,
                     payment_type=:payment_type,
                     amount=:amount,
                     discount_type=:discount_type,
                     discount_value=:discount_value,
                     discount_amount=:discount_amount,
                     credit_applied=:credit_applied,
                     notes=:notes,
                     posting_status=1,
                     status=1,
                     updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':customer_id' => $customerId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':payment_type' => $paymentType,
                ':amount' => $actualPayment,
                ':discount_type' => $calc['discount_type'],
                ':discount_value' => $calc['discount_value'],
                ':discount_amount' => $calc['discount_amount'],
                ':credit_applied' => $calc['credit_applied'],
                ':notes' => $notes,
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO food_customer_payments
                 (branch_id,customer_id,payment_no,payment_date,payment_type,amount,
                  discount_type,discount_value,discount_amount,credit_applied,notes,
                  posting_status,status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:customer_id,:payment_no,:payment_date,:payment_type,:amount,
                  :discount_type,:discount_value,:discount_amount,:credit_applied,:notes,
                  1,1,:created_by,NOW(),NOW())'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':customer_id' => $customerId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':payment_type' => $paymentType,
                ':amount' => $actualPayment,
                ':discount_type' => $calc['discount_type'],
                ':discount_value' => $calc['discount_value'],
                ':discount_amount' => $calc['discount_amount'],
                ':credit_applied' => $calc['credit_applied'],
                ':notes' => $notes,
                ':created_by' => $userId,
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        cp_insert_payment_details(
            $pdo,
            $branchId,
            $paymentId,
            $userId,
            $parsedPayments['rows']
        );

        $allocationStmt = $pdo->prepare(
            'INSERT INTO food_customer_payment_allocations
             (customer_payment_id,sale_id,branch_id,allocation_type,allocated_amount,credit_amount,discount_amount,status,created_by,created_at,updated_at)
             VALUES
             (:payment_id,:sale_id,:branch_id,:allocation_type,:allocated_amount,:credit_amount,:discount_amount,1,:created_by,NOW(),NOW())'
        );

        $newSaleIds = [];
        foreach ($calc['allocations'] as $allocation) {
            $allocationStmt->execute([
                ':payment_id' => $paymentId,
                ':sale_id' => $allocation['sale_id'],
                ':branch_id' => $branchId,
                ':allocation_type' => $allocation['allocation_type'],
                ':allocated_amount' => $allocation['payment_amount'],
                ':credit_amount' => $allocation['credit_amount'],
                ':discount_amount' => $allocation['discount_amount'],
                ':created_by' => $userId,
            ]);
            if ($allocation['sale_id']) {
                $newSaleIds[(int)$allocation['sale_id']] = true;
            }
        }

        $ledger = $pdo->prepare(
            "INSERT INTO food_customer_credit_ledger
             (branch_id,customer_id,transaction_date,transaction_type,reference_type,reference_id,
              amount_in,amount_out,remarks,status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:customer_id,:transaction_date,:transaction_type,'customer_payment',:reference_id,
              :amount_in,:amount_out,:remarks,1,:created_by,NOW(),NOW())"
        );

        if ($calc['credit_applied'] > 0.001) {
            $ledger->execute([
                ':branch_id' => $branchId,
                ':customer_id' => $customerId,
                ':transaction_date' => $paymentDate,
                ':transaction_type' => 2,
                ':reference_id' => $paymentId,
                ':amount_in' => 0,
                ':amount_out' => $calc['credit_applied'],
                ':remarks' => 'Customer Credit applied in ' . $paymentNo,
                ':created_by' => $userId,
            ]);
        }

        if ($calc['excess_credit'] > 0.001) {
            $ledger->execute([
                ':branch_id' => $branchId,
                ':customer_id' => $customerId,
                ':transaction_date' => $paymentDate,
                ':transaction_type' => 1,
                ':reference_id' => $paymentId,
                ':amount_in' => $calc['excess_credit'],
                ':amount_out' => 0,
                ':remarks' => 'Excess payment credit from ' . $paymentNo,
                ':created_by' => $userId,
            ]);
        }

        if ($isEdit && $oldCustomerId === $customerId) {
            $finalCredit = cp_credit_balance($pdo, $branchId, $customerId);
            if ($finalCredit < -0.001) {
                json_error(
                    'Updated Customer Payment would leave Customer Credit negative because credit from the original Payment has already been used.',
                    409
                );
            }
        }

        foreach (array_unique(array_merge(array_keys($oldSaleIds), array_keys($newSaleIds))) as $saleId) {
            cp_refresh_sale($pdo, $branchId, (int)$saleId);
        }

        $pdo->commit();
        json_success(
            $isEdit ? 'Customer Payment updated successfully.' : 'Customer Payment posted successfully.',
            [
                'ref' => encryptReference('customer_payment', $paymentId),
                'payment_no' => $paymentNo,
                'actual_payment' => $actualPayment,
                'available_credit' => $calc['available_credit_after'],
            ]
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('customer-payment-list.php', ACTION_VIEW);
    $ctx = cp_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));

    $search = isset($_GET['search']['value'])
        ? trim((string)$_GET['search']['value'])
        : '';
    $sourceFilter = strtolower(trim((string)($_GET['source'] ?? '')));
    $statusFilter = strtolower(trim((string)($_GET['status'] ?? 'posted')));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $isPosExpr = "(
        p.source_sale_id IS NOT NULL
        OR UPPER(COALESCE(p.payment_no,'')) LIKE 'POS-%'
        OR UPPER(COALESCE(p.notes,'')) LIKE 'POS PAYMENT FOR SALE ID %'
        OR UPPER(COALESCE(p.notes,'')) LIKE 'POS RECEIPT FOR %'
    )";

    /* Old Sales builds stored a POS payment without source_sale_id/payment_no.
       If its notes identify it as POS, recover the linked Final Invoice from the
       payment allocation so it appears in the common register too. */
    $linkedSaleExpr = "COALESCE(
        p.source_sale_id,
        CASE WHEN {$isPosExpr} THEN (
            SELECT MIN(apx.sale_id)
            FROM food_customer_payment_allocations apx
            WHERE apx.customer_payment_id=p.id
              AND apx.branch_id=p.branch_id
              AND apx.sale_id IS NOT NULL
        ) ELSE NULL END
    )";

    $listableExpr = "(
        (p.payment_no IS NOT NULL AND TRIM(p.payment_no)<>'')
        OR {$isPosExpr}
    )";

    $base = ' FROM food_customer_payments p
              LEFT JOIN food_customers c
                     ON c.id=p.customer_id
                    AND c.branch_id=p.branch_id
              LEFT JOIN food_sales s
                     ON s.id=(' . $linkedSaleExpr . ')
                    AND s.branch_id=p.branch_id';

    $where = [
        'p.branch_id=:branch_id',
        $listableExpr,
    ];
    $params = [':branch_id' => $branchId];

    if ($sourceFilter === 'pos') {
        $where[] = $isPosExpr;
    } elseif ($sourceFilter === 'customer') {
        $where[] = 'NOT ' . $isPosExpr;
    }

    switch ($statusFilter) {
        case 'draft':
            $where[] = 'p.status=1';
            $where[] = 'p.posting_status=0';
            $where[] = 'p.reversed_at IS NULL';
            break;
        case 'inactive':
            $where[] = 'p.status=0';
            break;
        case 'reversed':
            $where[] = 'p.reversed_at IS NOT NULL';
            break;
        case 'all':
            break;
        case 'posted':
        default:
            $where[] = 'p.status=1';
            $where[] = 'p.posting_status=1';
            $where[] = 'p.reversed_at IS NULL';
            break;
    }

    if ($dateFrom !== '') {
        $where[] = 'p.payment_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'p.payment_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    if ($search !== '') {
        $where[] = "(
            p.payment_no LIKE :search_payment_no
            OR s.sales_no LIKE :search_invoice
            OR c.customer_code LIKE :search_customer_code
            OR c.customer_name LIKE :search_customer_name
            OR s.customer_name_snapshot LIKE :search_snapshot
            OR EXISTS (
                SELECT 1
                FROM food_customer_payment_details d
                INNER JOIN food_payment_methods pm
                        ON pm.id=d.payment_method_id
                       AND pm.branch_id=d.branch_id
                INNER JOIN accounts a
                        ON a.id=d.account_id
                       AND a.branch_id=d.branch_id
                WHERE d.customer_payment_id=p.id
                  AND d.branch_id=p.branch_id
                  AND d.status=1
                  AND (
                      pm.method_name LIKE :search_method
                      OR a.account_name LIKE :search_account
                      OR d.payment_reference LIKE :search_reference
                      OR d.cheque_no LIKE :search_cheque
                  )
            )
        )";
        $needle = '%' . $search . '%';
        $params[':search_payment_no'] = $needle;
        $params[':search_invoice'] = $needle;
        $params[':search_customer_code'] = $needle;
        $params[':search_customer_name'] = $needle;
        $params[':search_snapshot'] = $needle;
        $params[':search_method'] = $needle;
        $params[':search_account'] = $needle;
        $params[':search_reference'] = $needle;
        $params[':search_cheque'] = $needle;
    }

    $total = db()->prepare(
        'SELECT COUNT(*)' . $base .
        ' WHERE p.branch_id=:branch_id AND ' . $listableExpr
    );
    $total->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$total->fetchColumn();

    $filtered = db()->prepare(
        'SELECT COUNT(*)' . $base . ' WHERE ' . implode(' AND ', $where)
    );
    $filtered->execute($params);
    $recordsFiltered = (int)$filtered->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS payment_count,
            COALESCE(SUM(p.amount), 0) AS actual_payment,
            COALESCE(SUM(p.amount + p.credit_applied + p.discount_amount), 0) AS settlement_total
         ' . $base . '
         WHERE ' . implode(' AND ', $where)
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderCols = [
        0 => 'p.payment_no',
        1 => 'p.payment_date',
        2 => 'COALESCE(c.customer_name,s.customer_name_snapshot)',
        3 => 'p.source_sale_id',
        4 => 'p.payment_type',
        5 => 'p.amount',
        6 => 'p.credit_applied',
        7 => 'p.discount_amount',
        8 => 'p.id',
        9 => 'p.posting_status',
        10 => 'p.id',
        11 => 'p.id',
    ];

    $orderIndex = 1;
    $orderDirection = 'DESC';
    if (isset($_GET['order'][0])) {
        $requestedIndex = (int)($_GET['order'][0]['column'] ?? 1);
        if (isset($orderCols[$requestedIndex])) $orderIndex = $requestedIndex;
        $orderDirection = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc'
            ? 'ASC'
            : 'DESC';
    }

    $openingBalanceExpr = "GREATEST(0, COALESCE(c.opening_balance,0) - COALESCE((
        SELECT SUM(aob.allocated_amount+aob.credit_amount+aob.discount_amount)
        FROM food_customer_payment_allocations aob
        INNER JOIN food_customer_payments pob
                ON pob.id=aob.customer_payment_id
               AND pob.branch_id=aob.branch_id
        WHERE aob.branch_id=p.branch_id
          AND pob.customer_id=p.customer_id
          AND aob.allocation_type=2
          AND aob.status=1
          AND pob.posting_status=1
          AND pob.status=1
          AND pob.reversed_at IS NULL
    ),0))";

    $invoiceBalanceExpr = "COALESCE((
        SELECT SUM(GREATEST(0,sb.balance_amount))
        FROM food_sales sb
        WHERE sb.branch_id=p.branch_id
          AND sb.customer_id=p.customer_id
          AND sb.document_type=4
          AND sb.posting_status=1
          AND sb.status=1
          AND sb.reversed_at IS NULL
    ),0)";

    $particularBalanceExpr = "COALESCE((
        SELECT SUM(GREATEST(0,sp.balance_amount))
        FROM food_customer_payment_allocations ap
        INNER JOIN food_sales sp
                ON sp.id=ap.sale_id
               AND sp.branch_id=ap.branch_id
        WHERE ap.customer_payment_id=p.id
          AND ap.branch_id=p.branch_id
          AND ap.allocation_type=1
          AND ap.status=1
          AND sp.document_type=4
          AND sp.posting_status=1
          AND sp.status=1
          AND sp.reversed_at IS NULL
    ),0)";

    $detailSummaryExpr = "COALESCE(NULLIF((
        SELECT GROUP_CONCAT(
            CONCAT(pm.method_name,' / ',a.account_name)
            ORDER BY pm.sort_order,d.id
            SEPARATOR ' + '
        )
        FROM food_customer_payment_details d
        INNER JOIN food_payment_methods pm
                ON pm.id=d.payment_method_id
               AND pm.branch_id=d.branch_id
        INNER JOIN accounts a
                ON a.id=d.account_id
               AND a.branch_id=d.branch_id
        WHERE d.customer_payment_id=p.id
          AND d.branch_id=p.branch_id
          AND d.status=1
    ),''),
    CASE
        WHEN p.account_id IS NOT NULL AND COALESCE(p.payment_mode,'')<>'' THEN CONCAT(
            p.payment_mode,' / ',COALESCE((
                SELECT la.account_name
                FROM accounts la
                WHERE la.id=p.account_id AND la.branch_id=p.branch_id
                LIMIT 1
            ),'-')
        )
        ELSE ''
    END)";

    $sql = 'SELECT p.id,p.payment_no,p.payment_date,p.payment_type,p.amount,
                   p.credit_applied,p.discount_amount,p.posting_status,p.status,
                   p.reversed_at,p.source_sale_id,p.notes,
                   c.customer_code,
                   COALESCE(c.customer_name,s.customer_name_snapshot,\'Walk-in Customer\') AS customer_name,
                   s.id AS linked_sale_id,s.sales_no AS linked_sales_no,
                   CASE
                       WHEN ' . $isPosExpr . ' THEN COALESCE(s.balance_amount,0)
                       WHEN p.payment_type=1 THEN ' . $openingBalanceExpr . '
                       WHEN p.payment_type=3 THEN ' . $particularBalanceExpr . '
                       ELSE (' . $invoiceBalanceExpr . ' + ' . $openingBalanceExpr . ')
                   END AS balance_amount,
                   ' . $detailSummaryExpr . ' AS payment_summary,
                   CASE WHEN ' . $isPosExpr . ' THEN 1 ELSE 0 END AS is_pos' .
            $base .
            ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . $orderCols[$orderIndex] . ' ' . $orderDirection . ',p.id DESC' .
            ' LIMIT ' . $start . ',' . $length;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        $isPos = (int)$row['is_pos'] === 1;
        $linkedSaleId = (int)($row['linked_sale_id'] ?? 0);
        $linkedSalesNo = trim((string)($row['linked_sales_no'] ?? ''));
        $paymentNo = trim((string)($row['payment_no'] ?? ''));
        if ($paymentNo === '' && $isPos) {
            $paymentNo = $linkedSalesNo !== ''
                ? 'POS-' . $linkedSalesNo
                : 'POS-PAY-' . $id;
        }

        if (!empty($row['reversed_at'])) {
            $statusLabel = 'Reversed';
        } elseif ((int)$row['status'] !== 1) {
            $statusLabel = 'Inactive';
        } elseif ((int)$row['posting_status'] !== 1) {
            $statusLabel = 'Draft';
        } else {
            $statusLabel = 'Posted';
        }

        $ref = encryptReference('customer_payment', $id);
        $saleUrl = '';
        if ($isPos && $linkedSaleId > 0) {
            $saleRef = encryptReference('sale', $linkedSaleId);
            $saleUrl = 'sales.php?ref=' . rawurlencode($saleRef);
        }

        $paymentForLabel = $isPos
            ? ($linkedSalesNo !== '' ? $linkedSalesNo : 'Final Invoice')
            : cp_type_label((int)$row['payment_type']);

        $rows[] = [
            'ref' => $ref,
            'payment_no' => $paymentNo,
            'payment_date' => (string)$row['payment_date'],
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'customer_name' => (string)($row['customer_name'] ?? 'Walk-in Customer'),
            'source' => $isPos ? 'pos' : 'customer',
            'source_label' => $isPos ? 'Sales POS' : 'Customer Payment',
            'payment_type' => (int)$row['payment_type'],
            'payment_type_label' => cp_type_label((int)$row['payment_type']),
            'payment_for_label' => $paymentForLabel,
            'amount' => (float)$row['amount'],
            'credit_applied' => (float)$row['credit_applied'],
            'discount_amount' => (float)$row['discount_amount'],
            'balance_amount' => round((float)($row['balance_amount'] ?? 0), 2),
            'payment_summary' => (string)($row['payment_summary'] ?? ''),
            'status_label' => $statusLabel,
            'can_delete' => ((int)$row['status'] === 1 && empty($row['reversed_at'])),
            'view_url' => $isPos ? '' : 'customer-payment.php?ref=' . rawurlencode($ref) . '&view=1',
            'edit_url' => $isPos ? '' : 'customer-payment.php?ref=' . rawurlencode($ref),
            'sale_url' => $saleUrl,
        ];
    }

    $salesMenu = menu_by_path('sales.php');
    $salesActions = $salesMenu
        ? effective_actions_for_menu($access['user'], $salesMenu)
        : [];

    json_success('Customer Payment DataTable loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'payment_count' => (int)($summary['payment_count'] ?? 0),
            'actual_payment' => (float)($summary['actual_payment'] ?? 0),
            'settlement_total' => (float)($summary['settlement_total'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'form_actions' => effective_actions_for_menu(
            $access['user'],
            menu_by_path('customer-payment.php')
        ),
        'sales_actions' => $salesActions,
    ]);
}

json_error('Unsupported Customer Payment request.', 404);
