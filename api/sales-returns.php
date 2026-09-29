<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (!defined('ACTION_VIEW')) define('ACTION_VIEW', 1);
if (!defined('ACTION_UPDATE')) define('ACTION_UPDATE', 3);
if (!defined('ACTION_DELETE')) define('ACTION_DELETE', 4);
if (!defined('ACTION_RETURN')) define('ACTION_RETURN', 32);

function sr_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Sales Return is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,b.state_code,c.company_name
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
        'state_code' => $row['state_code'] === null ? null : (string)$row['state_code'],
    ];
}

function sr_require_schema(): void
{
    $tables = [
        'food_sales','food_sale_items','food_sale_returns','food_sale_return_items',
        'food_stock_movements','food_products','food_purchases','food_units','accounts','food_customers',
        'food_customer_payments','food_customer_payment_allocations',
        'food_customer_credit_ledger','food_payment_methods',
        'food_sales_return_refunds','food_sales_return_allocation_releases',
    ];
    foreach ($tables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error(
                'Sales Return schema is incomplete. Run sql/sales-return-complete-update.sql first. Missing table: ' . $table,
                500
            );
        }
    }

    $required = [
        'food_sale_returns' => [
            'return_no','tax_mode','supply_type','subtotal','item_discount_total','overall_discount_total',
            'discount_total','cess_total','before_round_total','round_off','notes','settlement_type',
            'outstanding_adjusted','released_payment_amount','released_credit_amount',
            'released_discount_amount','credit_generated','refund_amount'
        ],
        'food_sale_items' => [
            'primary_unit_id','secondary_unit_id','conversion_rate','primary_quantity','secondary_quantity',
            'base_quantity','unit_price'
        ],
        'food_sale_return_items' => [
            'source_purchase_id','selected_unit_id','primary_unit_id','secondary_unit_id','conversion_rate',
            'primary_quantity','secondary_quantity','base_quantity','unit_price','discount_amount',
            'overall_discount_share','tax_type','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate','cess_amount',
            'return_reason','add_to_stock'
        ],
        'food_customer_payment_allocations' => ['allocated_amount','credit_amount','discount_amount'],
    ];

    $missing = [];
    foreach ($required as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = db()->query(
                'SHOW COLUMNS FROM `' . str_replace('`','``',$table) . '` LIKE ' . db()->quote($column)
            );
            if (!$stmt || !$stmt->fetchColumn()) $missing[] = $table . '.' . $column;
        }
    }
    if ($missing) {
        json_error(
            'Sales Return schema update is required. Missing column(s): ' . implode(', ', $missing) .
            '. Run sql/sales-return-complete-update.sql first.',
            500
        );
    }
}

function sr_unit_snapshot(array $row): array
{
    /*
     * Sales Return normally follows the immutable Sale/Purchase snapshot.
     * Legacy rows created before mixed-unit support could incorrectly save
     * conversion_rate=1 for the same Primary/Secondary unit pair.  When the
     * current Product Master still has the exact same unit IDs and a >1
     * conversion, use that conversion only to repair this legacy defect.
     */
    $salePrimary = (int)($row['primary_unit_id'] ?? 0);
    $saleSecondary = (int)($row['secondary_unit_id'] ?? 0);
    $batchPrimary = (int)($row['batch_primary_unit_id'] ?? 0);
    $batchSecondary = (int)($row['batch_secondary_unit_id'] ?? 0);

    $primary = $salePrimary > 0 ? $salePrimary : $batchPrimary;
    if ($primary < 1) {
        json_error('Original Sale Item unit snapshot is incomplete. Recreate/fix that Sale first.', 409);
    }

    $secondary = $salePrimary > 0 ? $saleSecondary : $batchSecondary;
    if ($secondary < 1) return [$primary, null, 1.0];

    $conversion = (float)($row['conversion_rate'] ?? 0);
    if ($conversion <= 0 && $salePrimary < 1) {
        $conversion = (float)($row['batch_conversion_rate'] ?? 0);
    }

    $masterPrimary = (int)($row['master_primary_unit_id'] ?? 0);
    $masterSecondary = !empty($row['master_secondary_unit_id']) ? (int)$row['master_secondary_unit_id'] : null;
    $masterConversion = (float)($row['master_conversion_rate'] ?? 0);
    $sameMasterUnits = $masterPrimary === $primary
        && $masterSecondary !== null
        && $masterSecondary === $secondary;

    if ($sameMasterUnits && $masterConversion > 1.0000001 && $conversion <= 1.0000001) {
        $conversion = $masterConversion;
    }

    if ($conversion <= 0) {
        json_error('Original Sale Item conversion snapshot is invalid. Recreate/fix that Sale first.', 409);
    }

    return [$primary, $secondary, $conversion];
}

function sr_effective_conversion(array $row): float
{
    [,,$conversion] = sr_unit_snapshot($row);
    return $conversion > 0 ? $conversion : 1.0;
}

function sr_snapshot_quantities(array $row): array
{
    $primary = round((float)($row['primary_quantity'] ?? 0), 3);
    $secondary = round((float)($row['secondary_quantity'] ?? 0), 3);
    if ($primary <= 0 && $secondary <= 0 && (float)($row['quantity'] ?? 0) > 0) {
        $legacy = round((float)$row['quantity'], 3);
        $selected = (int)($row['selected_unit_id'] ?? 0);
        $secondaryId = (int)($row['batch_secondary_unit_id'] ?? ($row['secondary_unit_id'] ?? 0));
        if ($secondaryId > 0 && $selected === $secondaryId) $secondary = $legacy;
        else $primary = $legacy;
    }
    return [$primary, $secondary];
}

function sr_effective_base_quantity(array $row, float $conversion): float
{
    [$primary, $secondary] = sr_snapshot_quantities($row);
    if ($primary > 0 || $secondary > 0) {
        return round(($primary * $conversion) + $secondary, 3);
    }
    return round((float)($row['base_quantity'] ?? 0), 3);
}

function sr_nullable($value, int $max = 0): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if ($max > 0 && mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
    return $value;
}

function sr_valid_date($value, string $field): string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') json_error(ucwords(str_replace('_',' ',$field)) . ' is required.', 422, [$field => 'Required.']);
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        json_error('Enter a valid ' . str_replace('_',' ',$field) . '.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}

function sr_money($value, string $label, string $field): float
{
    if ($value === '' || $value === null) return 0.0;
    if (!is_numeric($value)) json_error($label . ' is invalid.', 422, [$field => 'Enter a valid amount.']);
    $n = round((float)$value, 2);
    if ($n < 0) json_error($label . ' cannot be negative.', 422, [$field => 'Amount cannot be negative.']);
    return $n;
}

function sr_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') json_error($label . ' reference is required.', 422);
    try {
        $id = (int)decryptReference(trim($value), $purpose);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }
    if ($id < 1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function sr_payment_methods(PDO $pdo, int $branchId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,method_code,method_name,account_type,requires_reference,requires_cheque_details,status
         FROM food_payment_methods
         WHERE branch_id=:branch_id AND status=1
           AND UPPER(method_code) IN (\'CASH\',\'UPI\',\'BANK\',\'CHEQUE\')
         ORDER BY FIELD(UPPER(method_code),\'CASH\',\'UPI\',\'BANK\',\'CHEQUE\'),id'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $code = strtoupper(trim((string)$row['method_code']));
        $rows[$code] = [
            'id' => (int)$row['id'],
            'code' => $code,
            'name' => (string)$row['method_name'],
            'account_type' => (int)$row['account_type'],
            'requires_reference' => (int)$row['requires_reference'],
            'requires_cheque_details' => (int)$row['requires_cheque_details'],
        ];
    }
    foreach (['CASH','UPI','BANK','CHEQUE'] as $required) {
        if (!isset($rows[$required])) {
            json_error('Payment Method master is incomplete. Missing ' . $required . '.', 500);
        }
    }
    return $rows;
}

function sr_accounts(PDO $pdo, int $branchId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,account_code,account_name,account_type,bank_name,account_number,upi_id,is_default_cash
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,is_default_cash DESC,account_name,id'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => (int)$row['account_type'],
            'bank_name' => (string)($row['bank_name'] ?? ''),
            'account_number' => (string)($row['account_number'] ?? ''),
            'upi_id' => (string)($row['upi_id'] ?? ''),
            'is_default_cash' => (int)($row['is_default_cash'] ?? 0),
        ];
    }
    return $rows;
}

function sr_credit_balance(PDO $pdo, int $branchId, ?int $customerId): float
{
    if (!$customerId) return 0.0;
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_in-amount_out),0)
         FROM food_customer_credit_ledger
         WHERE branch_id=:branch_id AND customer_id=:customer_id AND status=1'
    );
    $stmt->execute([':branch_id' => $branchId, ':customer_id' => $customerId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function sr_return_ledger_effect(PDO $pdo, int $branchId, int $returnId): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_in-amount_out),0)
         FROM food_customer_credit_ledger
         WHERE branch_id=:branch_id
           AND reference_type='sales_return'
           AND reference_id=:return_id
           AND status=1"
    );
    $stmt->execute([':branch_id' => $branchId, ':return_id' => $returnId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function sr_assert_credit_reversible(PDO $pdo, int $branchId, int $returnId, ?int $customerId): void
{
    if (!$customerId) return;
    $effect = sr_return_ledger_effect($pdo, $branchId, $returnId);
    if ($effect <= 0.001) return;
    $current = sr_credit_balance($pdo, $branchId, $customerId);
    if (round($current - $effect, 2) < -0.001) {
        json_error(
            'Customer Credit generated by this Sales Return has already been used. Edit or delete the dependent transaction first.',
            409
        );
    }
}

function sr_reverse_credit_effect(PDO $pdo, int $branchId, int $returnId, ?int $customerId, int $userId, string $date): void
{
    if (!$customerId) return;
    $effect = sr_return_ledger_effect($pdo, $branchId, $returnId);
    if (abs($effect) <= 0.001) return;

    $amountIn = $effect < 0 ? abs($effect) : 0.0;
    $amountOut = $effect > 0 ? $effect : 0.0;
    $stmt = $pdo->prepare(
        "INSERT INTO food_customer_credit_ledger
         (branch_id,customer_id,transaction_date,transaction_type,reference_type,reference_id,
          amount_in,amount_out,remarks,status,created_by,created_at,updated_at)
         VALUES
         (:branch_id,:customer_id,:transaction_date,4,'sales_return',:reference_id,
          :amount_in,:amount_out,:remarks,1,:created_by,NOW(),NOW())"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':customer_id' => $customerId,
        ':transaction_date' => $date,
        ':reference_id' => $returnId,
        ':amount_in' => $amountIn,
        ':amount_out' => $amountOut,
        ':remarks' => 'Sales Return internal reversal',
        ':created_by' => $userId,
    ]);
}

function sr_generate_no(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT return_no
         FROM food_sale_returns
         WHERE branch_id=:branch_id AND return_no REGEXP '^SRT[0-9]+$'
         ORDER BY CAST(SUBSTRING(return_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^SRT([0-9]+)$/i', $last, $m)) $next = ((int)$m[1]) + 1;
    return 'SRT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function sr_invoice_locked(PDO $pdo, int $branchId, int $saleId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*,c.customer_code,c.customer_name AS current_customer_name,c.mobile AS customer_mobile
         FROM food_sales s
         LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
         WHERE s.id=:id AND s.branch_id=:branch_id
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([':id' => $saleId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Final Invoice was not found.', 404);
    if ((int)$row['document_type'] !== 4 || (int)$row['posting_status'] !== 1 || (int)$row['status'] !== 1 || !empty($row['reversed_at'])) {
        json_error('Sales Return is allowed only for an active posted Final Invoice.', 422);
    }
    return $row;
}

function sr_returned_base_map(PDO $pdo, int $branchId, int $saleId, int $excludeReturnId = 0): array
{
    $sql =
        'SELECT sri.sale_item_id,sri.primary_quantity,sri.secondary_quantity,sri.quantity,sri.base_quantity,
                sri.conversion_rate,sri.selected_unit_id,
                si.primary_unit_id,si.secondary_unit_id
         FROM food_sale_return_items sri
         INNER JOIN food_sale_returns sr
            ON sr.id=sri.sale_return_id AND sr.branch_id=sri.branch_id
         INNER JOIN food_sale_items si
            ON si.id=sri.sale_item_id AND si.branch_id=sri.branch_id
         WHERE sr.branch_id=:branch_id AND sr.sale_id=:sale_id
           AND sr.posting_status=1 AND sr.status=1
           AND sri.status=1';
    $params = [':branch_id' => $branchId, ':sale_id' => $saleId];
    if ($excludeReturnId > 0) {
        $sql .= ' AND sr.id<>:exclude_return_id';
        $params[':exclude_return_id'] = $excludeReturnId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $base = round((float)($row['base_quantity'] ?? 0), 3);
        if ($base <= 0) {
            $conversion = sr_effective_conversion($row);
            $base = sr_effective_base_quantity($row, $conversion);
        }
        $itemId = (int)$row['sale_item_id'];
        $map[$itemId] = round(($map[$itemId] ?? 0) + $base, 3);
    }
    return $map;
}

function sr_previous_return_total(PDO $pdo, int $branchId, int $saleId, int $excludeReturnId = 0): float
{
    $sql =
        'SELECT COALESCE(SUM(grand_total),0)
         FROM food_sale_returns
         WHERE branch_id=:branch_id AND sale_id=:sale_id
           AND posting_status=1 AND status=1';
    $params = [':branch_id' => $branchId, ':sale_id' => $saleId];
    if ($excludeReturnId > 0) {
        $sql .= ' AND id<>:exclude_return_id';
        $params[':exclude_return_id'] = $excludeReturnId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function sr_previous_round_off(PDO $pdo, int $branchId, int $saleId, int $excludeReturnId = 0): float
{
    $sql =
        'SELECT COALESCE(SUM(round_off),0)
         FROM food_sale_returns
         WHERE branch_id=:branch_id AND sale_id=:sale_id
           AND posting_status=1 AND status=1';
    $params = [':branch_id' => $branchId, ':sale_id' => $saleId];
    if ($excludeReturnId > 0) {
        $sql .= ' AND id<>:exclude_return_id';
        $params[':exclude_return_id'] = $excludeReturnId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function sr_release_totals(PDO $pdo, int $branchId, int $returnId): array
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(released_payment_amount),0) AS payment_amount,
                COALESCE(SUM(released_credit_amount),0) AS credit_amount,
                COALESCE(SUM(released_discount_amount),0) AS discount_amount
         FROM food_sales_return_allocation_releases
         WHERE branch_id=:branch_id AND sale_return_id=:return_id AND status=1'
    );
    $stmt->execute([':branch_id' => $branchId, ':return_id' => $returnId]);
    $row = $stmt->fetch() ?: [];
    return [
        'payment' => round((float)($row['payment_amount'] ?? 0), 2),
        'credit' => round((float)($row['credit_amount'] ?? 0), 2),
        'discount' => round((float)($row['discount_amount'] ?? 0), 2),
    ];
}

function sr_allocation_totals(PDO $pdo, int $branchId, int $saleId, int $restoreReturnId = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(a.allocated_amount),0) AS payment_amount,
                COALESCE(SUM(a.credit_amount),0) AS credit_amount,
                COALESCE(SUM(a.discount_amount),0) AS discount_amount
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
           ON p.id=a.customer_payment_id AND p.branch_id=a.branch_id
          AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
         WHERE a.branch_id=:branch_id AND a.sale_id=:sale_id AND a.status=1'
    );
    $stmt->execute([':branch_id' => $branchId, ':sale_id' => $saleId]);
    $row = $stmt->fetch() ?: [];
    $totals = [
        'payment' => round((float)($row['payment_amount'] ?? 0), 2),
        'credit' => round((float)($row['credit_amount'] ?? 0), 2),
        'discount' => round((float)($row['discount_amount'] ?? 0), 2),
    ];
    if ($restoreReturnId > 0) {
        $release = sr_release_totals($pdo, $branchId, $restoreReturnId);
        $totals['payment'] = round($totals['payment'] + $release['payment'], 2);
        $totals['credit'] = round($totals['credit'] + $release['credit'], 2);
        $totals['discount'] = round($totals['discount'] + $release['discount'], 2);
    }
    $totals['paid'] = round($totals['payment'] + $totals['credit'], 2);
    $totals['settled'] = round($totals['paid'] + $totals['discount'], 2);
    return $totals;
}

function sr_invoice_options(PDO $pdo, int $branchId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id,s.sales_no,s.invoice_date,s.customer_id,s.customer_name_snapshot,s.tax_mode,s.grand_total,
                c.customer_code,c.customer_name,c.mobile
         FROM food_sales s
         LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
         WHERE s.branch_id=:branch_id
           AND s.document_type=4 AND s.posting_status=1 AND s.status=1 AND s.reversed_at IS NULL
         ORDER BY s.invoice_date DESC,s.id DESC
         LIMIT 1000'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $saleId = (int)$row['id'];
        $returned = sr_returned_base_map($pdo, $branchId, $saleId);
        $itemStmt = $pdo->prepare(
            'SELECT id,base_quantity FROM food_sale_items
             WHERE sale_id=:sale_id AND branch_id=:branch_id AND status=1'
        );
        $itemStmt->execute([':sale_id' => $saleId, ':branch_id' => $branchId]);
        $returnable = false;
        foreach ($itemStmt->fetchAll() as $item) {
            if ((float)$item['base_quantity'] - (float)($returned[(int)$item['id']] ?? 0) > 0.0004) {
                $returnable = true;
                break;
            }
        }
        $name = trim((string)($row['customer_name'] ?? ''));
        if ($name === '') $name = trim((string)($row['customer_name_snapshot'] ?? ''));
        if ($name === '') $name = 'Walk-in Customer';
        $rows[] = [
            'id' => $saleId,
            'ref' => encryptReference('sale', $saleId),
            'sales_no' => (string)$row['sales_no'],
            'invoice_date' => (string)$row['invoice_date'],
            'customer_id' => $row['customer_id'] === null ? 0 : (int)$row['customer_id'],
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'customer_name' => $name,
            'mobile' => (string)($row['mobile'] ?? ''),
            'tax_mode' => (int)$row['tax_mode'],
            'grand_total' => (float)$row['grand_total'],
            'returnable' => $returnable,
        ];
    }
    return $rows;
}

function sr_invoice_record(PDO $pdo, int $branchId, int $saleId, int $excludeReturnId = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*,c.customer_code,c.customer_name AS current_customer_name,c.mobile AS customer_mobile
         FROM food_sales s
         LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
         WHERE s.id=:id AND s.branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id' => $saleId, ':branch_id' => $branchId]);
    $sale = $stmt->fetch();
    if (!$sale) json_error('Final Invoice was not found.', 404);
    if ((int)$sale['document_type'] !== 4 || (int)$sale['posting_status'] !== 1 || (int)$sale['status'] !== 1 || !empty($sale['reversed_at'])) {
        json_error('Sales Return is allowed only for an active posted Final Invoice.', 422);
    }

    $returnedMap = sr_returned_base_map($pdo, $branchId, $saleId, $excludeReturnId);
    $itemStmt = $pdo->prepare(
        'SELECT si.*,p.product_code,p.product_name,
                p.primary_unit_id AS master_primary_unit_id,
                p.secondary_unit_id AS master_secondary_unit_id,
                p.secondary_conversion AS master_conversion_rate,
                pi.primary_unit_id AS batch_primary_unit_id,
                pi.secondary_unit_id AS batch_secondary_unit_id,
                pi.conversion_rate AS batch_conversion_rate,
                pi.batch_id AS batch_item_batch_id,
                bpu.unit_name AS batch_primary_unit_name,bpu.unit_symbol AS batch_primary_unit_symbol,
                bsu.unit_name AS batch_secondary_unit_name,bsu.unit_symbol AS batch_secondary_unit_symbol,
                spu.unit_name AS sale_primary_unit_name,spu.unit_symbol AS sale_primary_unit_symbol,
                ssu.unit_name AS sale_secondary_unit_name,ssu.unit_symbol AS sale_secondary_unit_symbol,
                fp.purchase_no,fp.batch_number
         FROM food_sale_items si
         INNER JOIN food_products p ON p.id=si.product_id AND p.branch_id=si.branch_id
         LEFT JOIN food_purchases fp ON fp.id=si.source_purchase_id AND fp.branch_id=si.branch_id
         LEFT JOIN food_purchase_items pi
            ON pi.purchase_id=si.source_purchase_id
           AND pi.branch_id=si.branch_id
           AND pi.product_id=si.product_id
           AND pi.status=1
         LEFT JOIN food_units bpu ON bpu.id=pi.primary_unit_id AND bpu.branch_id=pi.branch_id
         LEFT JOIN food_units bsu ON bsu.id=pi.secondary_unit_id AND bsu.branch_id=pi.branch_id
         LEFT JOIN food_units spu ON spu.id=si.primary_unit_id AND spu.branch_id=si.branch_id
         LEFT JOIN food_units ssu ON ssu.id=si.secondary_unit_id AND ssu.branch_id=si.branch_id
         WHERE si.sale_id=:sale_id AND si.branch_id=:branch_id AND si.status=1
         ORDER BY si.id'
    );
    $itemStmt->execute([':sale_id' => $saleId, ':branch_id' => $branchId]);
    $items = [];
    foreach ($itemStmt->fetchAll() as $row) {
        $itemId = (int)$row['id'];
        [$primaryUnitId, $secondaryUnitId, $conversion] = sr_unit_snapshot($row);
        [$primaryQty, $secondaryQty] = sr_snapshot_quantities($row);
        if ($secondaryUnitId === null) $secondaryQty = 0.0;
        $soldBase = sr_effective_base_quantity($row, $conversion);
        $returnedBase = round((float)($returnedMap[$itemId] ?? 0), 3);
        $returnableBase = max(0.0, round($soldBase - $returnedBase, 3));
        $items[] = [
            'sale_item_id' => $itemId,
            'sale_item_ref' => encryptReference('sale_item', $itemId),
            'product_id' => (int)$row['product_id'],
            'product_code' => (string)$row['product_code'],
            'product_name' => (string)$row['product_name'],
            'purchase_no' => (string)($row['purchase_no'] ?? ''),
            'batch_number' => (string)($row['batch_number'] ?? ''),
            'primary_unit_id' => $primaryUnitId,
            'secondary_unit_id' => $secondaryUnitId,
            'primary_unit_name' => (string)(($row['sale_primary_unit_name'] ?? '') ?: ($row['batch_primary_unit_name'] ?? '')),
            'primary_unit_symbol' => (string)(($row['sale_primary_unit_symbol'] ?? '') ?: ($row['batch_primary_unit_symbol'] ?? '')),
            'secondary_unit_name' => $secondaryUnitId !== null
                ? (string)(($row['sale_secondary_unit_name'] ?? '') ?: ($row['batch_secondary_unit_name'] ?? ''))
                : '',
            'secondary_unit_symbol' => $secondaryUnitId !== null
                ? (string)(($row['sale_secondary_unit_symbol'] ?? '') ?: ($row['batch_secondary_unit_symbol'] ?? ''))
                : '',
            'primary_quantity' => $primaryQty,
            'secondary_quantity' => $secondaryQty,
            'quantity' => (float)$row['quantity'],
            'base_quantity' => $soldBase,
            'returned_base_quantity' => $returnedBase,
            'returnable_base_quantity' => $returnableBase,
            'returned_primary_quantity' => $secondaryUnitId !== null ? floor(($returnedBase + 0.0000001) / $conversion) : $returnedBase,
            'returned_secondary_quantity' => $secondaryUnitId !== null ? round($returnedBase - (floor(($returnedBase + 0.0000001) / $conversion) * $conversion), 3) : 0.0,
            'returnable_primary_quantity' => $secondaryUnitId !== null ? floor(($returnableBase + 0.0000001) / $conversion) : $returnableBase,
            'returnable_secondary_quantity' => $secondaryUnitId !== null ? round($returnableBase - (floor(($returnableBase + 0.0000001) / $conversion) * $conversion), 3) : 0.0,
            'conversion_rate' => $conversion,
            'unit_price' => (float)$row['unit_price'],
            'discount_amount' => (float)$row['discount_amount'],
            'overall_discount_share' => (float)$row['overall_discount_share'],
            'tax_type' => (int)$row['tax_type'],
            'taxable_amount' => (float)$row['taxable_amount'],
            'tax_rate' => (float)$row['tax_rate'],
            'gst_rate' => (float)$row['gst_rate'],
            'cgst_rate' => (float)$row['cgst_rate'],
            'sgst_rate' => (float)$row['sgst_rate'],
            'igst_rate' => (float)$row['igst_rate'],
            'cess_rate' => (float)$row['cess_rate'],
            'cgst_amount' => (float)$row['cgst_amount'],
            'sgst_amount' => (float)$row['sgst_amount'],
            'igst_amount' => (float)$row['igst_amount'],
            'cess_amount' => (float)$row['cess_amount'],
            'line_total' => (float)$row['line_total'],
            'source_purchase_missing' => (int)($row['source_purchase_id'] ?? 0) < 1,
        ];
    }

    $previousReturnTotal = sr_previous_return_total($pdo, $branchId, $saleId, $excludeReturnId);
    $netBefore = max(0.0, round((float)$sale['grand_total'] - $previousReturnTotal, 2));
    $allocation = sr_allocation_totals($pdo, $branchId, $saleId, $excludeReturnId);
    $settledBefore = min($netBefore, $allocation['settled']);
    $balanceBefore = max(0.0, round($netBefore - $settledBefore, 2));

    $customerId = $sale['customer_id'] === null ? null : (int)$sale['customer_id'];
    $credit = sr_credit_balance($pdo, $branchId, $customerId);
    if ($excludeReturnId > 0) {
        $effect = sr_return_ledger_effect($pdo, $branchId, $excludeReturnId);
        $credit = max(0.0, round($credit - $effect, 2));
    }

    $customerName = trim((string)($sale['current_customer_name'] ?? ''));
    if ($customerName === '') $customerName = trim((string)($sale['customer_name_snapshot'] ?? ''));
    if ($customerName === '') $customerName = 'Walk-in Customer';

    return [
        'id' => $saleId,
        'ref' => encryptReference('sale', $saleId),
        'sales_no' => (string)$sale['sales_no'],
        'invoice_date' => (string)$sale['invoice_date'],
        'customer_id' => $customerId ?? 0,
        'customer_code' => (string)($sale['customer_code'] ?? ''),
        'customer_name' => $customerName,
        'customer_mobile' => (string)($sale['customer_mobile'] ?? ''),
        'tax_mode' => (int)$sale['tax_mode'],
        'tax_mode_label' => (int)$sale['tax_mode'] === 0 ? 'Non-GST' : 'GST',
        'supply_type' => (string)($sale['supply_type'] ?? ''),
        'original_total' => (float)$sale['grand_total'],
        'previous_return_total' => $previousReturnTotal,
        'current_net_total' => $netBefore,
        'paid_amount' => $allocation['paid'],
        'settled_amount' => $allocation['settled'],
        'balance_amount' => $balanceBefore,
        'settlement_payment' => $allocation['payment'],
        'settlement_credit' => $allocation['credit'],
        'settlement_discount' => $allocation['discount'],
        'customer_credit_balance' => $credit,
        'before_round_total' => (float)$sale['before_round_total'],
        'round_off' => (float)$sale['round_off'],
        'previous_return_round_off' => sr_previous_round_off($pdo, $branchId, $saleId, $excludeReturnId),
        'items' => $items,
    ];
}

function sr_allocate_round_off(array $sale, float $previousRoundOff, float $returnBeforeRound, bool $completesInvoice): float
{
    $invoiceRound = round((float)$sale['round_off'], 2);
    $remaining = round($invoiceRound - $previousRoundOff, 2);
    if (abs($remaining) < 0.005) return 0.0;
    if ($completesInvoice) return $remaining;
    $invoiceBefore = (float)$sale['before_round_total'];
    if (abs($invoiceBefore) < 0.005 || abs($invoiceRound) < 0.005) return 0.0;
    $share = round($invoiceRound * ($returnBeforeRound / $invoiceBefore), 2);
    if ($remaining > 0) return max(0.0, min($share, $remaining));
    return min(0.0, max($share, $remaining));
}

function sr_build_items(PDO $pdo, int $branchId, array $sale, array $rawItems, int $excludeReturnId = 0): array
{
    $saleId = (int)$sale['id'];
    $itemStmt = $pdo->prepare(
        'SELECT si.*,p.product_code,p.product_name,
                p.primary_unit_id AS master_primary_unit_id,
                p.secondary_unit_id AS master_secondary_unit_id,
                p.secondary_conversion AS master_conversion_rate,
                pi.primary_unit_id AS batch_primary_unit_id,
                pi.secondary_unit_id AS batch_secondary_unit_id,
                pi.conversion_rate AS batch_conversion_rate,
                pi.batch_id AS batch_item_batch_id,
                fp.batch_number,fp.purchase_no
         FROM food_sale_items si
         INNER JOIN food_products p ON p.id=si.product_id AND p.branch_id=si.branch_id
         LEFT JOIN food_purchases fp ON fp.id=si.source_purchase_id AND fp.branch_id=si.branch_id
         LEFT JOIN food_purchase_items pi
            ON pi.purchase_id=si.source_purchase_id
           AND pi.branch_id=si.branch_id
           AND pi.product_id=si.product_id
           AND pi.status=1
         WHERE si.sale_id=:sale_id AND si.branch_id=:branch_id AND si.status=1
         ORDER BY si.id FOR UPDATE'
    );
    $itemStmt->execute([':sale_id' => $saleId, ':branch_id' => $branchId]);
    $saleItems = [];
    foreach ($itemStmt->fetchAll() as $row) $saleItems[(int)$row['id']] = $row;
    if (!$saleItems) json_error('This Final Invoice has no active items.', 422);

    $returnedMap = sr_returned_base_map($pdo, $branchId, $saleId, $excludeReturnId);
    $previousRoundOff = sr_previous_round_off($pdo, $branchId, $saleId, $excludeReturnId);
    $used = [];
    $returnBaseByItem = [];
    $items = [];

    $totals = [
        'subtotal'=>0.0,'item_discount_total'=>0.0,'overall_discount_total'=>0.0,
        'taxable_total'=>0.0,'cgst_total'=>0.0,'sgst_total'=>0.0,'igst_total'=>0.0,
        'cess_total'=>0.0,'before_round_total'=>0.0
    ];

    foreach (array_values($rawItems) as $raw) {
        if (!is_array($raw)) continue;
        $primaryQtyRaw = $raw['return_primary_quantity'] ?? ($raw['primary_quantity'] ?? null);
        $secondaryQtyRaw = $raw['return_secondary_quantity'] ?? ($raw['secondary_quantity'] ?? null);
        if ($primaryQtyRaw === null && $secondaryQtyRaw === null) {
            /* Backward-compatible old one-unit return payload. */
            $primaryQtyRaw = $raw['return_quantity'] ?? '';
            $secondaryQtyRaw = 0;
        }
        if (($primaryQtyRaw === '' || $primaryQtyRaw === null) && ($secondaryQtyRaw === '' || $secondaryQtyRaw === null)) continue;
        if (!is_numeric($primaryQtyRaw ?: 0) || !is_numeric($secondaryQtyRaw ?: 0)) {
            json_error('Return quantities must be valid numbers.', 422, ['items'=>'Invalid Return Qty.']);
        }
        $primaryQty = round((float)($primaryQtyRaw ?: 0), 3);
        $secondaryQty = round((float)($secondaryQtyRaw ?: 0), 3);
        if ($primaryQty < 0 || $secondaryQty < 0) json_error('Return quantities cannot be negative.',422,['items'=>'Invalid Return Qty.']);
        if ($primaryQty <= 0 && $secondaryQty <= 0) continue;

        $returnReason = sr_nullable($raw['return_reason'] ?? null, 100);
        if ($returnReason === null) {
            json_error('Reason for Return is required for every returned product.', 422, [
                'items' => 'Select Reason for Return.'
            ]);
        }
        $addToStockRaw = $raw['add_to_stock'] ?? null;
        if (!in_array((string)$addToStockRaw, ['0','1'], true)) {
            json_error('Select Add to Stock Yes/No for every returned product.', 422, [
                'items' => 'Select Add to Stock.'
            ]);
        }
        $addToStock = (int)$addToStockRaw;

        $saleItemId = sr_ref_to_id($raw['sale_item_ref'] ?? '', 'sale_item', 'Sale Item');
        if (isset($used[$saleItemId])) json_error('The same Invoice item cannot be returned twice.', 422);
        $used[$saleItemId] = true;
        if (!isset($saleItems[$saleItemId])) json_error('One selected item does not belong to this Final Invoice.', 422);

        $source = $saleItems[$saleItemId];
        $sourcePurchaseId = (int)($source['source_purchase_id'] ?? 0);
        if ($sourcePurchaseId < 1) {
            json_error((string)$source['product_name'] . ' has no original Purchase/Batch source.', 422);
        }

        [$primaryUnitId, $secondaryUnitId, $conversion] = sr_unit_snapshot($source);
        $hasSecondary = $secondaryUnitId !== null;
        if (!$hasSecondary) $secondaryQty = 0.0;
        $soldBase = sr_effective_base_quantity($source, $conversion);
        $returnedBefore = round((float)($returnedMap[$saleItemId] ?? 0), 3);
        $availableBase = max(0.0, round($soldBase - $returnedBefore, 3));
        $returnBase = round(($primaryQty * $conversion) + $secondaryQty, 3);
        if ($returnBase > $availableBase + 0.0004) {
            json_error(
                (string)$source['product_name'] . ' Return Qty exceeds the available return quantity.',
                422,
                ['items'=>'Return Qty exceeds available return quantity.']
            );
        }

        $qty = round($primaryQty + ($hasSecondary ? $secondaryQty / $conversion : 0), 3); // legacy equivalent Primary Qty
        $ratio = min(1.0, $returnBase / max(0.000001, $soldBase));
        $primaryRateSnapshot = (float)($source['unit_price'] ?? 0);
        $secondaryRateSnapshot = ($hasSecondary && $conversion > 0)
            ? round($primaryRateSnapshot / $conversion, 6)
            : 0.0;
        $originalGross = round(
            ((float)($source['primary_quantity'] ?? 0) * $primaryRateSnapshot) +
            ((float)($source['secondary_quantity'] ?? 0) * $secondaryRateSnapshot),
            2
        );
        if ($originalGross <= 0 && (float)$source['quantity'] > 0) {
            $originalGross = round((float)$source['quantity'] * (float)$source['unit_price'], 2);
        }
        $gross = round($originalGross * $ratio, 2);
        $itemDiscount = round((float)$source['discount_amount'] * $ratio, 2);
        $overallShare = round((float)$source['overall_discount_share'] * $ratio, 2);
        $taxable = round((float)$source['taxable_amount'] * $ratio, 2);
        $cgst = round((float)$source['cgst_amount'] * $ratio, 2);
        $sgst = round((float)$source['sgst_amount'] * $ratio, 2);
        $igst = round((float)$source['igst_amount'] * $ratio, 2);
        $cess = round((float)$source['cess_amount'] * $ratio, 2);
        $lineTotal = round((float)$source['line_total'] * $ratio, 2);

        $returnBaseByItem[$saleItemId] = $returnBase;
        $items[] = [
            'sale_item_id'=>$saleItemId,
            'product_id'=>(int)$source['product_id'],
            'source_purchase_id'=>$sourcePurchaseId,
            'batch_id'=>!empty($source['batch_item_batch_id']) ? (int)$source['batch_item_batch_id'] : ($source['batch_id'] === null ? null : (int)$source['batch_id']),
            'selected_unit_id'=>$primaryUnitId,
            'primary_unit_id'=>$primaryUnitId,
            'secondary_unit_id'=>$secondaryUnitId,
            'conversion_rate'=>$conversion,
            'primary_quantity'=>$primaryQty,
            'secondary_quantity'=>$secondaryQty,
            'quantity'=>$qty,
            'base_quantity'=>$returnBase,
            'unit_price'=>$primaryRateSnapshot,
            'discount_amount'=>$itemDiscount,
            'overall_discount_share'=>$overallShare,
            'tax_type'=>(int)$source['tax_type'],
            'taxable_amount'=>$taxable,
            'tax_rate'=>(float)$source['tax_rate'],
            'gst_rate'=>(float)$source['gst_rate'],
            'cgst_rate'=>(float)$source['cgst_rate'],
            'sgst_rate'=>(float)$source['sgst_rate'],
            'igst_rate'=>(float)$source['igst_rate'],
            'cess_rate'=>(float)$source['cess_rate'],
            'cgst_amount'=>$cgst,'sgst_amount'=>$sgst,'igst_amount'=>$igst,'cess_amount'=>$cess,
            'line_total'=>$lineTotal,
            'return_reason'=>$returnReason,
            'add_to_stock'=>$addToStock,
        ];

        $totals['subtotal'] += $gross;
        $totals['item_discount_total'] += $itemDiscount;
        $totals['overall_discount_total'] += $overallShare;
        $totals['taxable_total'] += $taxable;
        $totals['cgst_total'] += $cgst;
        $totals['sgst_total'] += $sgst;
        $totals['igst_total'] += $igst;
        $totals['cess_total'] += $cess;
        $totals['before_round_total'] += $lineTotal;
    }
    if (!$items) json_error('Enter Return Qty for at least one item.', 422, ['items'=>'Enter Return Qty.']);

    $completesInvoice = true;
    foreach ($saleItems as $id => $source) {
        $soldBase = sr_effective_base_quantity($source, sr_effective_conversion($source));
        $before = round((float)($returnedMap[$id] ?? 0), 3);
        $now = round((float)($returnBaseByItem[$id] ?? 0), 3);
        if (round($soldBase - $before - $now, 3) > 0.0004) {
            $completesInvoice = false;
            break;
        }
    }

    foreach ($totals as $key => $value) $totals[$key] = round($value, 2);
    $roundOff = sr_allocate_round_off($sale, $previousRoundOff, $totals['before_round_total'], $completesInvoice);
    $totals['round_off'] = $roundOff;
    $totals['grand_total'] = round($totals['before_round_total'] + $roundOff, 2);
    $totals['discount_total'] = round($totals['item_discount_total'] + $totals['overall_discount_total'], 2);

    return ['items'=>$items,'totals'=>$totals];
}

function sr_restore_release_rows(PDO $pdo, int $branchId, int $returnId, int $userId): void
{
    $stmt = $pdo->prepare(
        'SELECT r.*
         FROM food_sales_return_allocation_releases r
         WHERE r.branch_id=:branch_id AND r.sale_return_id=:return_id AND r.status=1
         ORDER BY r.id FOR UPDATE'
    );
    $stmt->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);
    foreach ($stmt->fetchAll() as $row) {
        $allocationId = (int)$row['customer_payment_allocation_id'];
        $find = $pdo->prepare(
            'SELECT id FROM food_customer_payment_allocations
             WHERE id=:id AND branch_id=:branch_id LIMIT 1 FOR UPDATE'
        );
        $find->execute([':id'=>$allocationId, ':branch_id'=>$branchId]);
        $found = $find->fetchColumn();

        if (!$found) {
            $findPair = $pdo->prepare(
                'SELECT id FROM food_customer_payment_allocations
                 WHERE customer_payment_id=:payment_id AND sale_id=:sale_id AND branch_id=:branch_id
                 LIMIT 1 FOR UPDATE'
            );
            $findPair->execute([
                ':payment_id'=>(int)$row['customer_payment_id'],
                ':sale_id'=>(int)$row['sale_id'],
                ':branch_id'=>$branchId,
            ]);
            $found = $findPair->fetchColumn();
        }

        if ($found) {
            $pdo->prepare(
                'UPDATE food_customer_payment_allocations
                 SET allocated_amount=allocated_amount+:payment_amount,
                     credit_amount=credit_amount+:credit_amount,
                     discount_amount=discount_amount+:discount_amount,
                     status=1,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':payment_amount'=>(float)$row['released_payment_amount'],
                ':credit_amount'=>(float)$row['released_credit_amount'],
                ':discount_amount'=>(float)$row['released_discount_amount'],
                ':id'=>(int)$found,
                ':branch_id'=>$branchId,
            ]);
        } else {
            $payment = $pdo->prepare(
                'SELECT id FROM food_customer_payments
                 WHERE id=:id AND branch_id=:branch_id
                   AND posting_status=1 AND status=1 AND reversed_at IS NULL
                 LIMIT 1 FOR UPDATE'
            );
            $payment->execute([':id'=>(int)$row['customer_payment_id'], ':branch_id'=>$branchId]);
            if (!$payment->fetchColumn()) {
                json_error(
                    'A Customer Payment linked to this Sales Return has changed or been reversed. The Sales Return cannot be edited/deleted safely.',
                    409
                );
            }

            $pdo->prepare(
                'INSERT INTO food_customer_payment_allocations
                 (customer_payment_id,sale_id,branch_id,allocation_type,allocated_amount,credit_amount,discount_amount,
                  status,created_by,created_at,updated_at)
                 VALUES
                 (:payment_id,:sale_id,:branch_id,1,:payment_amount,:credit_amount,:discount_amount,1,:created_by,NOW(),NOW())'
            )->execute([
                ':payment_id'=>(int)$row['customer_payment_id'],
                ':sale_id'=>(int)$row['sale_id'],
                ':branch_id'=>$branchId,
                ':payment_amount'=>(float)$row['released_payment_amount'],
                ':credit_amount'=>(float)$row['released_credit_amount'],
                ':discount_amount'=>(float)$row['released_discount_amount'],
                ':created_by'=>$userId,
            ]);
        }
    }

    $pdo->prepare(
        'UPDATE food_sales_return_allocation_releases
         SET status=0,updated_at=NOW()
         WHERE branch_id=:branch_id AND sale_return_id=:return_id AND status=1'
    )->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);
}

function sr_refresh_sale_from_allocations(PDO $pdo, int $branchId, int $saleId): array
{
    $sale = $pdo->prepare(
        'SELECT grand_total FROM food_sales
         WHERE id=:sale_id AND branch_id=:branch_id LIMIT 1 FOR UPDATE'
    );
    $sale->execute([':sale_id'=>$saleId, ':branch_id'=>$branchId]);
    $original = (float)$sale->fetchColumn();

    $returns = $pdo->prepare(
        'SELECT COALESCE(SUM(grand_total),0) FROM food_sale_returns
         WHERE branch_id=:branch_id AND sale_id=:sale_id AND posting_status=1 AND status=1'
    );
    $returns->execute([':branch_id'=>$branchId, ':sale_id'=>$saleId]);
    $returned = round((float)$returns->fetchColumn(), 2);
    $net = max(0.0, round($original - $returned, 2));

    $totals = sr_allocation_totals($pdo, $branchId, $saleId, 0);
    $paid = min($net, round($totals['payment'] + $totals['credit'], 2));
    $settled = min($net, round($totals['settled'], 2));
    $balance = max(0.0, round($net - $settled, 2));
    $status = $balance <= 0.001 ? 3 : ($paid > 0.001 ? 2 : 1);

    $pdo->prepare(
        'UPDATE food_sales
         SET paid_amount=:paid,balance_amount=:balance,payment_status=:status,updated_at=NOW()
         WHERE id=:sale_id AND branch_id=:branch_id'
    )->execute([
        ':paid'=>$paid, ':balance'=>$balance, ':status'=>$status,
        ':sale_id'=>$saleId, ':branch_id'=>$branchId,
    ]);

    return ['net'=>$net,'paid'=>$paid,'settled'=>$settled,'balance'=>$balance,'payment_status'=>$status];
}

function sr_cap_allocations(
    PDO $pdo,
    int $branchId,
    int $saleId,
    int $returnId,
    int $userId,
    float $targetNet
): array {
    $stmt = $pdo->prepare(
        'SELECT a.id,a.customer_payment_id,a.allocated_amount,a.credit_amount,a.discount_amount
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
           ON p.id=a.customer_payment_id AND p.branch_id=a.branch_id
          AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
         WHERE a.branch_id=:branch_id AND a.sale_id=:sale_id AND a.status=1
         ORDER BY p.payment_date,p.id,a.id
         FOR UPDATE'
    );
    $stmt->execute([':branch_id'=>$branchId, ':sale_id'=>$saleId]);
    $rows = $stmt->fetchAll();

    $totalPayment = $totalCredit = $totalDiscount = 0.0;
    foreach ($rows as $row) {
        $totalPayment += max(0.0,(float)$row['allocated_amount']);
        $totalCredit += max(0.0,(float)$row['credit_amount']);
        $totalDiscount += max(0.0,(float)$row['discount_amount']);
    }
    $totalPayment = round($totalPayment,2);
    $totalCredit = round($totalCredit,2);
    $totalDiscount = round($totalDiscount,2);

    $remaining = max(0.0, round($targetNet,2));
    $keepPayment = min($totalPayment,$remaining);
    $remaining = round($remaining-$keepPayment,2);
    $keepCredit = min($totalCredit,max(0.0,$remaining));
    $remaining = round($remaining-$keepCredit,2);
    $keepDiscount = min($totalDiscount,max(0.0,$remaining));

    $remainKeepPayment = $keepPayment;
    $remainKeepCredit = $keepCredit;
    $remainKeepDiscount = $keepDiscount;

    $released = ['payment'=>0.0,'credit'=>0.0,'discount'=>0.0];

    $update = $pdo->prepare(
        'UPDATE food_customer_payment_allocations
         SET allocated_amount=:payment_amount,credit_amount=:credit_amount,discount_amount=:discount_amount,
             status=:status,updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $releaseInsert = $pdo->prepare(
        'INSERT INTO food_sales_return_allocation_releases
         (branch_id,sale_return_id,customer_payment_allocation_id,customer_payment_id,sale_id,
          released_payment_amount,released_credit_amount,released_discount_amount,status,created_by,created_at,updated_at)
         VALUES
         (:branch_id,:return_id,:allocation_id,:payment_id,:sale_id,
          :payment_amount,:credit_amount,:discount_amount,1,:created_by,NOW(),NOW())'
    );

    foreach ($rows as $row) {
        $oldP = max(0.0, round((float)$row['allocated_amount'],2));
        $oldC = max(0.0, round((float)$row['credit_amount'],2));
        $oldD = max(0.0, round((float)$row['discount_amount'],2));

        $newP = min($oldP,max(0.0,$remainKeepPayment));
        $remainKeepPayment = round($remainKeepPayment-$newP,2);

        $newC = min($oldC,max(0.0,$remainKeepCredit));
        $remainKeepCredit = round($remainKeepCredit-$newC,2);

        $newD = min($oldD,max(0.0,$remainKeepDiscount));
        $remainKeepDiscount = round($remainKeepDiscount-$newD,2);

        $relP = round($oldP-$newP,2);
        $relC = round($oldC-$newC,2);
        $relD = round($oldD-$newD,2);
        $sumNew = round($newP+$newC+$newD,2);

        $update->execute([
            ':payment_amount'=>$newP, ':credit_amount'=>$newC, ':discount_amount'=>$newD,
            ':status'=>$sumNew > 0.001 ? 1 : 0,
            ':id'=>(int)$row['id'], ':branch_id'=>$branchId,
        ]);

        if ($relP > 0.001 || $relC > 0.001 || $relD > 0.001) {
            $releaseInsert->execute([
                ':branch_id'=>$branchId,
                ':return_id'=>$returnId,
                ':allocation_id'=>(int)$row['id'],
                ':payment_id'=>(int)$row['customer_payment_id'],
                ':sale_id'=>$saleId,
                ':payment_amount'=>$relP,
                ':credit_amount'=>$relC,
                ':discount_amount'=>$relD,
                ':created_by'=>$userId,
            ]);
            $released['payment'] += $relP;
            $released['credit'] += $relC;
            $released['discount'] += $relD;
        }
    }

    foreach ($released as $k=>$v) $released[$k]=round($v,2);
    sr_refresh_sale_from_allocations($pdo,$branchId,$saleId);
    return $released;
}

function sr_parse_refunds(
    PDO $pdo,
    int $branchId,
    array $data,
    string $returnDate
): array {
    $methods = sr_payment_methods($pdo,$branchId);
    $accounts = [];
    foreach (sr_accounts($pdo,$branchId) as $a) $accounts[(int)$a['id']] = $a;

    $raw = $data['refunds'] ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw,true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) $raw = [];

    $rows = [];
    $total = 0.0;
    $usedCodes = [];
    foreach ($raw as $row) {
        if (!is_array($row)) continue;
        $code = strtoupper(trim((string)($row['method_code'] ?? '')));
        if ($code === '' || !isset($methods[$code])) continue;
        $amount = sr_money($row['amount'] ?? 0, $methods[$code]['name'] . ' Refund Amount', 'refunds');
        if ($amount <= 0.001) continue;
        if (isset($usedCodes[$code])) json_error('Duplicate Refund Method: ' . $methods[$code]['name'] . '.',422);
        $usedCodes[$code] = true;

        $accountId = (int)($row['account_id'] ?? 0);
        if ($accountId < 1 || !isset($accounts[$accountId])) {
            json_error($methods[$code]['name'] . ' Refund Account is required.',422);
        }
        if ((int)$accounts[$accountId]['account_type'] !== (int)$methods[$code]['account_type']) {
            json_error($methods[$code]['name'] . ' uses an invalid Account type.',422);
        }

        $reference = sr_nullable($row['payment_reference'] ?? null,100);
        if ((int)$methods[$code]['requires_reference'] === 1 && $reference === null) {
            json_error($methods[$code]['name'] . ' Reference is required.',422);
        }

        $chequeNo = sr_nullable($row['cheque_no'] ?? null,100);
        $chequeDate = null;
        if ((int)$methods[$code]['requires_cheque_details'] === 1) {
            if ($chequeNo === null) json_error('Cheque No is required.',422);
            $chequeDate = sr_valid_date($row['cheque_date'] ?? '', 'cheque_date');
        }

        $refundDate = sr_valid_date($row['refund_date'] ?? $returnDate, 'refund_date');
        if ($refundDate < $returnDate) json_error('Refund Date cannot be before Return Date.',422);

        $rows[] = [
            'payment_method_id'=>(int)$methods[$code]['id'],
            'method_code'=>$code,
            'method_name'=>$methods[$code]['name'],
            'account_id'=>$accountId,
            'amount'=>$amount,
            'payment_reference'=>$reference,
            'cheque_no'=>$chequeNo,
            'cheque_date'=>$chequeDate,
            'refund_date'=>$refundDate,
        ];
        $total += $amount;
    }
    return ['rows'=>$rows,'total'=>round($total,2)];
}

function sr_insert_refunds(
    PDO $pdo,
    int $branchId,
    int $returnId,
    int $saleId,
    ?int $customerId,
    int $userId,
    array $rows
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO food_sales_return_refunds
         (branch_id,sale_return_id,sale_id,customer_id,payment_method_id,account_id,refund_date,amount,
          payment_reference,cheque_no,cheque_date,status,reversed_at,reversed_by,created_by,created_at,updated_at)
         VALUES
         (:branch_id,:return_id,:sale_id,:customer_id,:method_id,:account_id,:refund_date,:amount,
          :reference,:cheque_no,:cheque_date,1,NULL,NULL,:created_by,NOW(),NOW())'
    );
    foreach ($rows as $row) {
        $stmt->execute([
            ':branch_id'=>$branchId, ':return_id'=>$returnId, ':sale_id'=>$saleId, ':customer_id'=>$customerId,
            ':method_id'=>(int)$row['payment_method_id'], ':account_id'=>(int)$row['account_id'],
            ':refund_date'=>(string)$row['refund_date'], ':amount'=>(float)$row['amount'],
            ':reference'=>$row['payment_reference'], ':cheque_no'=>$row['cheque_no'], ':cheque_date'=>$row['cheque_date'],
            ':created_by'=>$userId,
        ]);
    }
}

function sr_insert_credit(PDO $pdo, int $branchId, int $returnId, ?int $customerId, int $userId, string $date, float $amount, string $returnNo): void
{
    if (!$customerId || $amount <= 0.001) return;
    $stmt = $pdo->prepare(
        "INSERT INTO food_customer_credit_ledger
         (branch_id,customer_id,transaction_date,transaction_type,reference_type,reference_id,
          amount_in,amount_out,remarks,status,created_by,created_at,updated_at)
         VALUES
         (:branch_id,:customer_id,:transaction_date,1,'sales_return',:reference_id,
          :amount_in,0,:remarks,1,:created_by,NOW(),NOW())"
    );
    $stmt->execute([
        ':branch_id'=>$branchId, ':customer_id'=>$customerId, ':transaction_date'=>$date,
        ':reference_id'=>$returnId, ':amount_in'=>$amount,
        ':remarks'=>'Customer Credit generated from Sales Return ' . $returnNo,
        ':created_by'=>$userId,
    ]);
}

function sr_deactivate_old_effects(PDO $pdo, int $branchId, int $returnId, ?int $customerId, int $userId, string $date): void
{
    sr_assert_credit_reversible($pdo,$branchId,$returnId,$customerId);
    sr_restore_release_rows($pdo,$branchId,$returnId,$userId);
    sr_reverse_credit_effect($pdo,$branchId,$returnId,$customerId,$userId,$date);

    $pdo->prepare(
        "UPDATE food_stock_movements SET status=0
         WHERE branch_id=:branch_id AND reference_type='sale_return' AND reference_id=:return_id AND status=1"
    )->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);

    $pdo->prepare(
        'UPDATE food_sale_return_items SET status=0,updated_at=NOW()
         WHERE branch_id=:branch_id AND sale_return_id=:return_id AND status=1'
    )->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);

    $pdo->prepare(
        'UPDATE food_sales_return_refunds
         SET status=0,reversed_at=NOW(),reversed_by=:user_id,updated_at=NOW()
         WHERE branch_id=:branch_id AND sale_return_id=:return_id AND status=1 AND reversed_at IS NULL'
    )->execute([':user_id'=>$userId, ':branch_id'=>$branchId, ':return_id'=>$returnId]);
}

function sr_insert_items_and_stock(
    PDO $pdo,
    int $branchId,
    int $returnId,
    int $saleId,
    string $returnNo,
    string $returnDate,
    string $salesNo,
    int $userId,
    array $items
): void {
    $itemInsert = $pdo->prepare(
        'INSERT INTO food_sale_return_items
         (sale_return_id,branch_id,sale_item_id,product_id,source_purchase_id,batch_id,
          selected_unit_id,primary_unit_id,secondary_unit_id,conversion_rate,
          primary_quantity,secondary_quantity,quantity,base_quantity,unit_price,discount_amount,overall_discount_share,
          tax_type,taxable_amount,tax_rate,gst_rate,cgst_rate,sgst_rate,igst_rate,cess_rate,
          cgst_amount,sgst_amount,igst_amount,cess_amount,line_total,return_reason,add_to_stock,status,created_by,created_at,updated_at)
         VALUES
         (:return_id,:branch_id,:sale_item_id,:product_id,:source_purchase_id,:batch_id,
          :unit_id,:primary_unit_id,:secondary_unit_id,:conversion_rate,
          :primary_quantity,:secondary_quantity,:quantity,:base_quantity,:unit_price,:discount_amount,:overall_discount_share,
          :tax_type,:taxable_amount,:tax_rate,:gst_rate,:cgst_rate,:sgst_rate,:igst_rate,:cess_rate,
          :cgst_amount,:sgst_amount,:igst_amount,:cess_amount,:line_total,:return_reason,:add_to_stock,1,:created_by,NOW(),NOW())'
    );
    $stockInsert = $pdo->prepare(
        "INSERT INTO food_stock_movements
         (branch_id,product_id,source_purchase_id,batch_id,movement_date,movement_type,reference_type,reference_id,
          reference_no,quantity_in,quantity_out,unit_id,conversion_rate,remarks,status,created_by,created_at)
         VALUES
         (:branch_id,:product_id,:source_purchase_id,:batch_id,:movement_date,4,'sale_return',:reference_id,
          :reference_no,:quantity_in,0,:unit_id,:conversion_rate,:remarks,1,:created_by,NOW())"
    );

    foreach ($items as $item) {
        $itemInsert->execute([
            ':return_id'=>$returnId, ':branch_id'=>$branchId, ':sale_item_id'=>$item['sale_item_id'],
            ':product_id'=>$item['product_id'], ':source_purchase_id'=>$item['source_purchase_id'],
            ':batch_id'=>$item['batch_id'], ':unit_id'=>$item['selected_unit_id'],
            ':primary_unit_id'=>$item['primary_unit_id'], ':secondary_unit_id'=>$item['secondary_unit_id'],
            ':conversion_rate'=>$item['conversion_rate'], ':primary_quantity'=>$item['primary_quantity'],
            ':secondary_quantity'=>$item['secondary_quantity'], ':quantity'=>$item['quantity'],
            ':base_quantity'=>$item['base_quantity'], ':unit_price'=>$item['unit_price'],
            ':discount_amount'=>$item['discount_amount'], ':overall_discount_share'=>$item['overall_discount_share'],
            ':tax_type'=>$item['tax_type'], ':taxable_amount'=>$item['taxable_amount'], ':tax_rate'=>$item['tax_rate'],
            ':gst_rate'=>$item['gst_rate'], ':cgst_rate'=>$item['cgst_rate'], ':sgst_rate'=>$item['sgst_rate'],
            ':igst_rate'=>$item['igst_rate'], ':cess_rate'=>$item['cess_rate'],
            ':cgst_amount'=>$item['cgst_amount'], ':sgst_amount'=>$item['sgst_amount'],
            ':igst_amount'=>$item['igst_amount'], ':cess_amount'=>$item['cess_amount'],
            ':line_total'=>$item['line_total'],
            ':return_reason'=>$item['return_reason'], ':add_to_stock'=>$item['add_to_stock'],
            ':created_by'=>$userId,
        ]);

        if ((int)$item['add_to_stock'] !== 1) {
            continue;
        }

        $stockInsert->execute([
            ':branch_id'=>$branchId, ':product_id'=>$item['product_id'],
            ':source_purchase_id'=>$item['source_purchase_id'], ':batch_id'=>$item['batch_id'], ':movement_date'=>$returnDate,
            ':reference_id'=>$returnId, ':reference_no'=>$returnNo,
            ':quantity_in'=>$item['base_quantity'], ':unit_id'=>$item['secondary_unit_id'] === null ? $item['primary_unit_id'] : $item['secondary_unit_id'],
            ':conversion_rate'=>$item['conversion_rate'],
            ':remarks'=>'Sales Return ' . $returnNo . ' against Final Invoice ' . $salesNo . ' | ' . $item['return_reason'],
            ':created_by'=>$userId,
        ]);
    }
}

function sr_return_record(PDO $pdo, int $branchId, int $returnId): array
{
    $stmt = $pdo->prepare(
        'SELECT sr.*,s.sales_no,s.invoice_date,s.customer_name_snapshot,
                c.customer_code,c.customer_name AS current_customer_name,c.mobile AS customer_mobile
         FROM food_sale_returns sr
         INNER JOIN food_sales s ON s.id=sr.sale_id AND s.branch_id=sr.branch_id
         LEFT JOIN food_customers c ON c.id=sr.customer_id AND c.branch_id=sr.branch_id
         WHERE sr.id=:id AND sr.branch_id=:branch_id AND sr.status=1
         LIMIT 1'
    );
    $stmt->execute([':id'=>$returnId, ':branch_id'=>$branchId]);
    $ret = $stmt->fetch();
    if (!$ret) json_error('Sales Return was not found.',404);

    $itemsStmt = $pdo->prepare(
        'SELECT sri.*,p.product_code,p.product_name,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol,
                fp.purchase_no,fp.batch_number
         FROM food_sale_return_items sri
         INNER JOIN food_products p ON p.id=sri.product_id AND p.branch_id=sri.branch_id
         LEFT JOIN food_units pu ON pu.id=sri.primary_unit_id AND pu.branch_id=sri.branch_id
         LEFT JOIN food_units su ON su.id=sri.secondary_unit_id AND su.branch_id=sri.branch_id
         LEFT JOIN food_purchases fp ON fp.id=sri.source_purchase_id AND fp.branch_id=sri.branch_id
         WHERE sri.branch_id=:branch_id AND sri.sale_return_id=:return_id AND sri.status=1
         ORDER BY sri.id'
    );
    $itemsStmt->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);
    $items=[];
    foreach ($itemsStmt->fetchAll() as $row) {
        $items[]=[
            'sale_item_id'=>(int)$row['sale_item_id'],
            'sale_item_ref'=>encryptReference('sale_item',(int)$row['sale_item_id']),
            'product_code'=>(string)$row['product_code'],'product_name'=>(string)$row['product_name'],
            'purchase_no'=>(string)($row['purchase_no']??''),'batch_number'=>(string)($row['batch_number']??''),
            'primary_unit_id'=>(int)($row['primary_unit_id']??0),
            'secondary_unit_id'=>$row['secondary_unit_id']===null?null:(int)$row['secondary_unit_id'],
            'primary_unit_name'=>(string)($row['primary_unit_name']??''),'primary_unit_symbol'=>(string)($row['primary_unit_symbol']??''),
            'secondary_unit_name'=>(string)($row['secondary_unit_name']??''),'secondary_unit_symbol'=>(string)($row['secondary_unit_symbol']??''),
            'primary_quantity'=>(float)($row['primary_quantity']??0),'secondary_quantity'=>(float)($row['secondary_quantity']??0),
            'quantity'=>(float)$row['quantity'],'base_quantity'=>(float)$row['base_quantity'],
            'conversion_rate'=>(float)$row['conversion_rate'],
            'unit_price'=>(float)$row['unit_price'],
            'discount_amount'=>(float)$row['discount_amount'],'overall_discount_share'=>(float)$row['overall_discount_share'],
            'taxable_amount'=>(float)$row['taxable_amount'],'gst_rate'=>(float)$row['gst_rate'],
            'cgst_amount'=>(float)$row['cgst_amount'],'sgst_amount'=>(float)$row['sgst_amount'],
            'igst_amount'=>(float)$row['igst_amount'],'cess_amount'=>(float)$row['cess_amount'],
            'line_total'=>(float)$row['line_total'],
            'return_reason'=>(string)($row['return_reason']??''),
            'add_to_stock'=>(int)($row['add_to_stock']??1),
        ];
    }

    $refundStmt=$pdo->prepare(
        'SELECT r.*,pm.method_code,pm.method_name,a.account_code,a.account_name,a.account_type
         FROM food_sales_return_refunds r
         INNER JOIN food_payment_methods pm ON pm.id=r.payment_method_id AND pm.branch_id=r.branch_id
         INNER JOIN accounts a ON a.id=r.account_id AND a.branch_id=r.branch_id
         WHERE r.branch_id=:branch_id AND r.sale_return_id=:return_id
           AND r.status=1 AND r.reversed_at IS NULL
         ORDER BY FIELD(UPPER(pm.method_code),\'CASH\',\'UPI\',\'BANK\',\'CHEQUE\'),r.id'
    );
    $refundStmt->execute([':branch_id'=>$branchId, ':return_id'=>$returnId]);
    $refunds=[];
    foreach($refundStmt->fetchAll() as $r){
        $refunds[]=[
            'method_code'=>strtoupper((string)$r['method_code']),
            'method_name'=>(string)$r['method_name'],
            'payment_method_id'=>(int)$r['payment_method_id'],
            'account_id'=>(int)$r['account_id'],
            'account_code'=>(string)$r['account_code'],
            'account_name'=>(string)$r['account_name'],
            'refund_date'=>(string)$r['refund_date'],
            'amount'=>(float)$r['amount'],
            'payment_reference'=>(string)($r['payment_reference']??''),
            'cheque_no'=>(string)($r['cheque_no']??''),
            'cheque_date'=>(string)($r['cheque_date']??''),
        ];
    }

    $name=trim((string)($ret['current_customer_name']??''));
    if($name==='')$name=trim((string)($ret['customer_name_snapshot']??''));
    if($name==='')$name='Walk-in Customer';

    return [
        'id'=>(int)$ret['id'],
        'ref'=>encryptReference('sale_return',(int)$ret['id']),
        'sale_id'=>(int)$ret['sale_id'],
        'sale_ref'=>encryptReference('sale',(int)$ret['sale_id']),
        'return_no'=>(string)$ret['return_no'],'return_date'=>(string)$ret['return_date'],
        'sales_no'=>(string)$ret['sales_no'],'invoice_date'=>(string)$ret['invoice_date'],
        'customer_id'=>$ret['customer_id']===null?0:(int)$ret['customer_id'],
        'customer_code'=>(string)($ret['customer_code']??''),'customer_name'=>$name,
        'customer_mobile'=>(string)($ret['customer_mobile']??''),
        'tax_mode'=>(int)$ret['tax_mode'],'tax_mode_label'=>(int)$ret['tax_mode']===0?'Non-GST':'GST',
        'reason'=>(string)($ret['reason']??''),'notes'=>(string)($ret['notes']??''),
        'subtotal'=>(float)$ret['subtotal'],'item_discount_total'=>(float)$ret['item_discount_total'],
        'overall_discount_total'=>(float)$ret['overall_discount_total'],'discount_total'=>(float)$ret['discount_total'],
        'taxable_total'=>(float)$ret['taxable_total'],'cgst_total'=>(float)$ret['cgst_total'],
        'sgst_total'=>(float)$ret['sgst_total'],'igst_total'=>(float)$ret['igst_total'],
        'cess_total'=>(float)$ret['cess_total'],'before_round_total'=>(float)$ret['before_round_total'],
        'round_off'=>(float)$ret['round_off'],'grand_total'=>(float)$ret['grand_total'],
        'settlement_type'=>(int)$ret['settlement_type'],
        'outstanding_adjusted'=>(float)$ret['outstanding_adjusted'],
        'released_payment_amount'=>(float)$ret['released_payment_amount'],
        'released_credit_amount'=>(float)$ret['released_credit_amount'],
        'released_discount_amount'=>(float)$ret['released_discount_amount'],
        'credit_generated'=>(float)$ret['credit_generated'],
        'refund_amount'=>(float)$ret['refund_amount'],
        'items'=>$items,'refunds'=>$refunds,
    ];
}

function sr_save(array $access,array $context,array $data,bool $isUpdate): array
{
    $pdo=db();
    $branchId=(int)$context['branch_id'];
    $userId=(int)($access['user']['id']??0);
    if($userId<1)json_error('Unable to identify current user.',403);

    $returnId=0;
    $old=null;
    $saleId=0;
    $returnNo='';
    $returnDate=sr_valid_date($data['return_date']??'','return_date');
    $reason=null;
    $notes=sr_nullable($data['notes']??null,2000);
    $settlementType=(int)($data['settlement_type']??0);
    if(!in_array($settlementType,[1,2,3],true)){
        json_error('Select Return Settlement.',422,['settlement_type'=>'Select Customer Credit, Refund or Split.']);
    }

    $rawItems=$data['items']??[];
    if(is_string($rawItems)){
        $decoded=json_decode($rawItems,true);
        $rawItems=is_array($decoded)?$decoded:[];
    }
    if(!is_array($rawItems)||!$rawItems)json_error('Enter Return Qty for at least one item.',422);

    $refundParsed=sr_parse_refunds($pdo,$branchId,$data,$returnDate);
    $lockName='amirtham:sales-return-no:'.$branchId;
    $lockHeld=false;

    try{
        $pdo->beginTransaction();

        if($isUpdate){
            $returnId=sr_ref_to_id($data['ref']??'','sale_return','Sales Return');
            $stmt=$pdo->prepare(
                'SELECT * FROM food_sale_returns
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id'=>$returnId,':branch_id'=>$branchId]);
            $old=$stmt->fetch();
            if(!$old)json_error('Sales Return was not found.',404);
            if(!empty($old['reversed_at']))json_error('Deleted Sales Return cannot be edited.',409);
            $saleId=(int)$old['sale_id'];
            $returnNo=(string)$old['return_no'];

            sr_assert_credit_reversible($pdo,$branchId,$returnId,$old['customer_id']===null?null:(int)$old['customer_id']);
            sr_deactivate_old_effects(
                $pdo,$branchId,$returnId,$old['customer_id']===null?null:(int)$old['customer_id'],
                $userId,$returnDate
            );
            $pdo->prepare(
                'UPDATE food_sale_returns SET posting_status=0,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([':id'=>$returnId,':branch_id'=>$branchId]);
        }else{
            $saleId=sr_ref_to_id($data['sale_ref']??'','sale','Final Invoice');
            $lock=$pdo->prepare('SELECT GET_LOCK(:lock_name,5)');
            $lock->execute([':lock_name'=>$lockName]);
            $lockHeld=(int)$lock->fetchColumn()===1;
            if(!$lockHeld)json_error('Unable to allocate the Sales Return No. Please try again.',409);
            $returnNo=sr_generate_no($pdo,$branchId);
        }

        $sale=sr_invoice_locked($pdo,$branchId,$saleId);
        if($returnDate<(string)$sale['invoice_date']){
            json_error('Return Date cannot be before the Final Invoice Date.',422);
        }

        $built=sr_build_items($pdo,$branchId,$sale,$rawItems,0);
        $items=$built['items'];
        $t=$built['totals'];

        $uniqueReasons=[];
        foreach($items as $itemRow){
            $itemReason=trim((string)($itemRow['return_reason']??''));
            if($itemReason!=='' && !in_array($itemReason,$uniqueReasons,true))$uniqueReasons[]=$itemReason;
        }
        $reason=sr_nullable(implode(', ',$uniqueReasons),255);

        $previousReturns=sr_previous_return_total($pdo,$branchId,$saleId,0);
        $netBefore=max(0.0,round((float)$sale['grand_total']-$previousReturns,2));
        $allocationBefore=sr_allocation_totals($pdo,$branchId,$saleId,0);
        $settledBefore=min($netBefore,$allocationBefore['settled']);
        $balanceBefore=max(0.0,round($netBefore-$settledBefore,2));
        $newNet=max(0.0,round($netBefore-$t['grand_total'],2));
        $outstandingAdjusted=min($t['grand_total'],$balanceBefore);

        if($isUpdate){
            $header=$pdo->prepare(
                'UPDATE food_sale_returns SET
                 customer_id=:customer_id,return_date=:return_date,tax_mode=:tax_mode,supply_type=:supply_type,reason=:reason,
                 subtotal=:subtotal,item_discount_total=:item_discount_total,overall_discount_total=:overall_discount_total,
                 discount_total=:discount_total,taxable_total=:taxable_total,cgst_total=:cgst_total,sgst_total=:sgst_total,
                 igst_total=:igst_total,cess_total=:cess_total,before_round_total=:before_round_total,round_off=:round_off,
                 grand_total=:grand_total,notes=:notes,settlement_type=:settlement_type,outstanding_adjusted=:outstanding_adjusted,
                 released_payment_amount=0,released_credit_amount=0,released_discount_amount=0,credit_generated=0,refund_amount=0,
                 posting_status=1,status=1,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $header->execute([
                ':customer_id'=>$sale['customer_id']===null?null:(int)$sale['customer_id'],
                ':return_date'=>$returnDate,':tax_mode'=>(int)$sale['tax_mode'],
                ':supply_type'=>sr_nullable($sale['supply_type']??null,20),':reason'=>$reason,
                ':subtotal'=>$t['subtotal'],':item_discount_total'=>$t['item_discount_total'],
                ':overall_discount_total'=>$t['overall_discount_total'],':discount_total'=>$t['discount_total'],
                ':taxable_total'=>$t['taxable_total'],':cgst_total'=>$t['cgst_total'],':sgst_total'=>$t['sgst_total'],
                ':igst_total'=>$t['igst_total'],':cess_total'=>$t['cess_total'],
                ':before_round_total'=>$t['before_round_total'],':round_off'=>$t['round_off'],
                ':grand_total'=>$t['grand_total'],':notes'=>$notes,':settlement_type'=>$settlementType,
                ':outstanding_adjusted'=>$outstandingAdjusted,':id'=>$returnId,':branch_id'=>$branchId,
            ]);
        }else{
            $header=$pdo->prepare(
                'INSERT INTO food_sale_returns
                 (branch_id,customer_id,sale_id,return_no,return_date,tax_mode,supply_type,reason,
                  subtotal,item_discount_total,overall_discount_total,discount_total,taxable_total,cgst_total,sgst_total,
                  igst_total,cess_total,before_round_total,round_off,grand_total,notes,settlement_type,outstanding_adjusted,
                  credit_released,released_payment_amount,released_credit_amount,released_discount_amount,credit_generated,
                  refund_amount,posting_status,status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:customer_id,:sale_id,:return_no,:return_date,:tax_mode,:supply_type,:reason,
                  :subtotal,:item_discount_total,:overall_discount_total,:discount_total,:taxable_total,:cgst_total,:sgst_total,
                  :igst_total,:cess_total,:before_round_total,:round_off,:grand_total,:notes,:settlement_type,:outstanding_adjusted,
                  0,0,0,0,0,0,1,1,:created_by,NOW(),NOW())'
            );
            $header->execute([
                ':branch_id'=>$branchId,':customer_id'=>$sale['customer_id']===null?null:(int)$sale['customer_id'],
                ':sale_id'=>$saleId,':return_no'=>$returnNo,':return_date'=>$returnDate,
                ':tax_mode'=>(int)$sale['tax_mode'],':supply_type'=>sr_nullable($sale['supply_type']??null,20),':reason'=>$reason,
                ':subtotal'=>$t['subtotal'],':item_discount_total'=>$t['item_discount_total'],
                ':overall_discount_total'=>$t['overall_discount_total'],':discount_total'=>$t['discount_total'],
                ':taxable_total'=>$t['taxable_total'],':cgst_total'=>$t['cgst_total'],':sgst_total'=>$t['sgst_total'],
                ':igst_total'=>$t['igst_total'],':cess_total'=>$t['cess_total'],
                ':before_round_total'=>$t['before_round_total'],':round_off'=>$t['round_off'],
                ':grand_total'=>$t['grand_total'],':notes'=>$notes,':settlement_type'=>$settlementType,
                ':outstanding_adjusted'=>$outstandingAdjusted,':created_by'=>$userId,
            ]);
            $returnId=(int)$pdo->lastInsertId();
        }

        sr_insert_items_and_stock(
            $pdo,$branchId,$returnId,$saleId,$returnNo,$returnDate,(string)$sale['sales_no'],$userId,$items
        );

        $released=sr_cap_allocations($pdo,$branchId,$saleId,$returnId,$userId,$newNet);
        $maxRefund=round($released['payment'],2);
        $refundTotal=round((float)$refundParsed['total'],2);

        if($settlementType===1 && $refundTotal>0.001){
            json_error('Customer Credit settlement cannot contain Refund Amount.',422);
        }
        if($settlementType===2){
            if($maxRefund<=0.001)json_error('No actual Customer Payment is refundable for this Return.',422);
            if(abs($refundTotal-$maxRefund)>0.01){
                json_error('Refund settlement must refund the full refundable amount of ' . number_format($maxRefund,2,'.','') . '.',422);
            }
        }
        if($settlementType===3){
            if($maxRefund<=0.001)json_error('No actual Customer Payment is available for Split settlement.',422);
            if($refundTotal<=0.001 || $refundTotal >= $maxRefund-0.001){
                json_error('Split settlement requires Refund Amount greater than zero and less than ' . number_format($maxRefund,2,'.','') . '.',422);
            }
        }
        if($refundTotal>$maxRefund+0.001){
            json_error('Refund Amount cannot exceed refundable actual payment of ' . number_format($maxRefund,2,'.','') . '.',422);
        }

        $creditGenerated=round(($released['payment']-$refundTotal)+$released['credit'],2);
        $customerId=$sale['customer_id']===null?null:(int)$sale['customer_id'];
        if(!$customerId && $creditGenerated>0.001){
            json_error('Walk-in Customer cannot keep Customer Credit. Refund the full refundable amount instead.',422);
        }

        sr_insert_refunds($pdo,$branchId,$returnId,$saleId,$customerId,$userId,$refundParsed['rows']);
        sr_insert_credit($pdo,$branchId,$returnId,$customerId,$userId,$returnDate,$creditGenerated,$returnNo);

        $pdo->prepare(
            'UPDATE food_sale_returns SET
             credit_released=:credit_released,released_payment_amount=:payment_amount,
             released_credit_amount=:credit_amount,released_discount_amount=:discount_amount,
             credit_generated=:credit_generated,refund_amount=:refund_amount,updated_at=NOW()
             WHERE id=:id AND branch_id=:branch_id'
        )->execute([
            ':credit_released'=>round($released['payment']+$released['credit'],2),
            ':payment_amount'=>$released['payment'],':credit_amount'=>$released['credit'],
            ':discount_amount'=>$released['discount'],':credit_generated'=>$creditGenerated,
            ':refund_amount'=>$refundTotal,':id'=>$returnId,':branch_id'=>$branchId,
        ]);

        $pdo->commit();

        if($lockHeld){
            $rel=$pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $rel->execute([':lock_name'=>$lockName]);
            $lockHeld=false;
        }

        if(function_exists('audit_log')){
            audit_log($userId,$isUpdate?ACTION_UPDATE:ACTION_RETURN,[
                'company_id'=>(int)$context['company_id'],'branch_id'=>$branchId,
                'menu_id'=>(int)($access['menu']['id']??0),'record_id'=>$returnId,
                'old_data'=>$old ?: null,
            ]);
        }
        return sr_return_record($pdo,$branchId,$returnId);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if($lockHeld){
            try{$rel=$pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');$rel->execute([':lock_name'=>$lockName]);}catch(Throwable $ignore){}
        }
        throw $e;
    }
}

function sr_delete(array $access,array $context,array $data): void
{
    $pdo=db();
    $branchId=(int)$context['branch_id'];
    $userId=(int)($access['user']['id']??0);
    $returnId=sr_ref_to_id($data['ref']??'','sale_return','Sales Return');

    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare(
            'SELECT * FROM food_sale_returns
             WHERE id=:id AND branch_id=:branch_id AND status=1
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':id'=>$returnId,':branch_id'=>$branchId]);
        $ret=$stmt->fetch();
        if(!$ret)json_error('Sales Return was not found.',404);

        $customerId=$ret['customer_id']===null?null:(int)$ret['customer_id'];
        sr_assert_credit_reversible($pdo,$branchId,$returnId,$customerId);
        sr_deactivate_old_effects($pdo,$branchId,$returnId,$customerId,$userId,date('Y-m-d'));

        $pdo->prepare(
            'UPDATE food_sale_returns
             SET status=0,posting_status=0,reversed_at=NOW(),reversed_by=:user_id,updated_at=NOW()
             WHERE id=:id AND branch_id=:branch_id'
        )->execute([':user_id'=>$userId,':id'=>$returnId,':branch_id'=>$branchId]);

        sr_refresh_sale_from_allocations($pdo,$branchId,(int)$ret['sale_id']);
        $pdo->commit();

        if(function_exists('audit_log')){
            audit_log($userId,ACTION_DELETE,[
                'company_id'=>(int)$context['company_id'],'branch_id'=>$branchId,
                'menu_id'=>(int)($access['menu']['id']??0),'record_id'=>$returnId,'old_data'=>$ret,
            ]);
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

sr_require_schema();
$method=request_method();

if($method==='GET' && isset($_GET['options'])){
    $access=require_permission('sales-return.php',ACTION_VIEW);
    $ctx=sr_tenant_context($access['user']);
    $pdo=db();
    $branchId=(int)$ctx['branch_id'];
    $invoices=sr_invoice_options($pdo,$branchId);
    $customers=[];
    $seen=[];
    foreach($invoices as $row){
        $cid=(int)$row['customer_id'];
        if(isset($seen[$cid]))continue;
        $seen[$cid]=true;
        $customers[]=[
            'id'=>$cid,
            'customer_code'=>(string)$row['customer_code'],
            'customer_name'=>(string)$row['customer_name'],
            'mobile'=>(string)$row['mobile'],
        ];
    }
    json_success('Sales Return options loaded.',[
        'allowed_actions'=>$access['actions'],
        'next_return_no'=>sr_generate_no($pdo,$branchId),
        'customers'=>$customers,'invoices'=>$invoices,
        'payment_methods'=>array_values(sr_payment_methods($pdo,$branchId)),
        'accounts'=>sr_accounts($pdo,$branchId),
    ]);
}

if($method==='GET' && isset($_GET['sale_ref'])){
    $access=require_permission('sales-return.php',ACTION_VIEW);
    $ctx=sr_tenant_context($access['user']);
    $saleId=sr_ref_to_id($_GET['sale_ref'],'sale','Final Invoice');
    $exclude=0;
    if(!empty($_GET['exclude_return_ref'])){
        $exclude=sr_ref_to_id($_GET['exclude_return_ref'],'sale_return','Sales Return');
    }
    json_success('Final Invoice loaded.',[
        'allowed_actions'=>$access['actions'],
        'invoice'=>sr_invoice_record(db(),(int)$ctx['branch_id'],$saleId,$exclude),
    ]);
}

if($method==='GET' && isset($_GET['ref'])){
    $access=require_permission('sales-return.php',ACTION_VIEW);
    $ctx=sr_tenant_context($access['user']);
    $id=sr_ref_to_id($_GET['ref'],'sale_return','Sales Return');
    $record=sr_return_record(db(),(int)$ctx['branch_id'],$id);
    $record['editable_invoice']=sr_invoice_record(db(),(int)$ctx['branch_id'],(int)$record['sale_id'],$id);
    json_success('Sales Return loaded.',[
        'allowed_actions'=>$access['actions'],'sales_return'=>$record,
    ]);
}

if($method==='GET' && isset($_GET['datatable'])){
    $access=require_permission('sales-return-list.php',ACTION_VIEW);
    $ctx=sr_tenant_context($access['user']);
    $branchId=(int)$ctx['branch_id'];
    $draw=max(0,(int)($_GET['draw']??0));
    $start=max(0,(int)($_GET['start']??0));
    $lengthRaw=(int)($_GET['length']??10);
    $length=$lengthRaw<0
        ?100000
        :max(1,min(100000,$lengthRaw));
    $search=trim((string)($_GET['search']['value']??''));
    $dateFrom=trim((string)($_GET['date_from']??''));
    $dateTo=trim((string)($_GET['date_to']??''));
    $settlementType=trim((string)($_GET['settlement_type']??''));

    $where=['sr.branch_id=:branch_id','sr.status=1','sr.posting_status=1'];
    $params=[':branch_id'=>$branchId];
    if($search!==''){
        $where[]='(sr.return_no LIKE :search_return_no
                   OR s.sales_no LIKE :search_sales_no
                   OR c.customer_code LIKE :search_customer_code
                   OR c.customer_name LIKE :search_customer_name
                   OR s.customer_name_snapshot LIKE :search_snapshot)';

        $term='%'.$search.'%';
        $params[':search_return_no']=$term;
        $params[':search_sales_no']=$term;
        $params[':search_customer_code']=$term;
        $params[':search_customer_name']=$term;
        $params[':search_snapshot']=$term;
    }
    if($dateFrom!==''){sr_valid_date($dateFrom,'date_from');$where[]='sr.return_date>=:date_from';$params[':date_from']=$dateFrom;}
    if($dateTo!==''){sr_valid_date($dateTo,'date_to');$where[]='sr.return_date<=:date_to';$params[':date_to']=$dateTo;}
    if($settlementType!=='' && in_array((int)$settlementType,[1,2,3],true)){
        $where[]='sr.settlement_type=:settlement_type';
        $params[':settlement_type']=(int)$settlementType;
    }

    $from=' FROM food_sale_returns sr
            INNER JOIN food_sales s ON s.id=sr.sale_id AND s.branch_id=sr.branch_id
            LEFT JOIN food_customers c ON c.id=sr.customer_id AND c.branch_id=sr.branch_id';

    $total=db()->prepare('SELECT COUNT(*)'.$from.' WHERE sr.branch_id=:branch_id AND sr.status=1 AND sr.posting_status=1');
    $total->execute([':branch_id'=>$branchId]);
    $recordsTotal=(int)$total->fetchColumn();

    $count=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));
    foreach($params as $k=>$v)$count->bindValue($k,$v,in_array($k,[':branch_id',':settlement_type'],true)?PDO::PARAM_INT:PDO::PARAM_STR);
    $count->execute();
    $recordsFiltered=(int)$count->fetchColumn();

    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS return_count,
            COALESCE(SUM(sr.grand_total),0) AS return_total,
            COALESCE(SUM(sr.refund_amount),0) AS refund_total,
            COALESCE(SUM(sr.credit_generated),0) AS credit_total
         '.$from.'
         WHERE '.implode(' AND ',$where)
    );
    foreach($params as $k=>$v){
        $summaryStmt->bindValue(
            $k,
            $v,
            in_array($k,[':branch_id',':settlement_type'],true)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];

    $columns=['sr.return_no','sr.return_date','s.sales_no','c.customer_name','item_count','stock_item_count','sr.grand_total',
              'sr.outstanding_adjusted','sr.credit_generated','sr.refund_amount','sr.settlement_type','sr.id'];
    $orderColumn=(int)($_GET['order'][0]['column']??1);
    $direction=strtolower((string)($_GET['order'][0]['dir']??'desc'))==='asc'?'ASC':'DESC';
    $orderBy=$columns[$orderColumn]??'sr.return_date';

    $sql='SELECT sr.id,sr.return_no,sr.return_date,sr.settlement_type,sr.grand_total,
                 sr.outstanding_adjusted,sr.credit_generated,sr.refund_amount,
                 s.sales_no,s.customer_name_snapshot,c.customer_code,c.customer_name,
                 (SELECT COUNT(*)
                    FROM food_sale_return_items sri
                   WHERE sri.sale_return_id=sr.id
                     AND sri.branch_id=sr.branch_id
                     AND sri.status=1) AS item_count,
                 (SELECT COUNT(*)
                    FROM food_sale_return_items sri
                   WHERE sri.sale_return_id=sr.id
                     AND sri.branch_id=sr.branch_id
                     AND sri.status=1
                     AND sri.add_to_stock=1) AS stock_item_count,
                 (SELECT COALESCE(SUM(CASE WHEN sri.add_to_stock=1 THEN sri.base_quantity ELSE 0 END),0)
                    FROM food_sale_return_items sri
                   WHERE sri.sale_return_id=sr.id
                     AND sri.branch_id=sr.branch_id
                     AND sri.status=1) AS stock_base_quantity
          '.$from.'
          WHERE '.implode(' AND ',$where).'
          ORDER BY '.$orderBy.' '.$direction.',sr.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);
    foreach($params as $k=>$v)$stmt->bindValue($k,$v,in_array($k,[':branch_id',':settlement_type'],true)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);
    $stmt->execute();

    $rows=[];
    foreach($stmt->fetchAll() as $row){
        $name=trim((string)($row['customer_name']??''));
        if($name==='')$name=trim((string)($row['customer_name_snapshot']??''));
        if($name==='')$name='Walk-in Customer';
        $ref=encryptReference('sale_return',(int)$row['id']);
        $type=(int)$row['settlement_type'];
        $rows[]=[
            'return_no'=>(string)$row['return_no'],'return_date'=>(string)$row['return_date'],
            'sales_no'=>(string)$row['sales_no'],'customer_code'=>(string)($row['customer_code']??''),
            'customer_name'=>$name,
            'item_count'=>(int)($row['item_count']??0),
            'stock_item_count'=>(int)($row['stock_item_count']??0),
            'stock_base_quantity'=>(float)($row['stock_base_quantity']??0),
            'grand_total'=>(float)$row['grand_total'],
            'outstanding_adjusted'=>(float)$row['outstanding_adjusted'],
            'credit_generated'=>(float)$row['credit_generated'],'refund_amount'=>(float)$row['refund_amount'],
            'settlement_type'=>$type,
            'settlement_label'=>$type===2?'Refund':($type===3?'Credit + Refund':'Customer Credit'),
            'ref'=>$ref,
            'view_url'=>'sales-return.php?ref='.rawurlencode($ref).'&mode=view',
            'edit_url'=>'sales-return.php?ref='.rawurlencode($ref).'&mode=edit',
        ];
    }

    $formMenu=menu_by_path('sales-return.php');
    $formActions=$formMenu?effective_actions_for_menu($access['user'],$formMenu):[];

    json_success('Sales Returns loaded.',[
        'datatable'=>['draw'=>$draw,'recordsTotal'=>$recordsTotal,'recordsFiltered'=>$recordsFiltered,'data'=>$rows],
        'summary'=>[
            'return_count'=>(int)($summary['return_count']??0),
            'return_total'=>(float)($summary['return_total']??0),
            'refund_total'=>(float)($summary['refund_total']??0),
            'credit_total'=>(float)($summary['credit_total']??0),
        ],
        'allowed_actions'=>$access['actions'],'form_actions'=>$formActions,
    ]);
}

if($method==='POST'){
    $access=require_permission('sales-return.php',ACTION_RETURN);
    $ctx=sr_tenant_context($access['user']);
    $row=sr_save($access,$ctx,request_data(),false);
    json_success('Sales Return posted successfully.',['sales_return'=>$row],201);
}

if($method==='PUT'){
    $access=require_permission('sales-return.php',ACTION_UPDATE);
    $ctx=sr_tenant_context($access['user']);
    $row=sr_save($access,$ctx,request_data(),true);
    json_success('Sales Return updated successfully.',['sales_return'=>$row]);
}

if($method==='DELETE'){
    $access=require_permission('sales-return.php',ACTION_DELETE);
    $ctx=sr_tenant_context($access['user']);
    sr_delete($access,$ctx,request_data());
    json_success('Sales Return deleted successfully.');
}

json_error('Method not allowed.',405);
