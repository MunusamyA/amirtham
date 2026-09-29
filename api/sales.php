<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/*
 * AMIRTHAM - Sales POS API
 *
 * Document types:
 *   1 = Quotation
 *   2 = Proforma Bill
 *   3 = Sales Bill
 *   4 = Final Invoice
 *
 * Tax mode:
 *   0 = Non-GST
 *   1 = Normal GST
 *
 * Only Final Invoice posts stock and customer payment allocations.
 */

const SALES_ACTION_QUOTATION = 55;
const SALES_ACTION_PROFORMA = 56;
const SALES_ACTION_SALES_BILL = 57;
const SALES_ACTION_FINAL_INVOICE = 58;
const SALES_ACTION_VIEW_PROFIT = 59;

function sales_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Sales POS is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, b.state_code,
                c.company_name
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
        'state_code' => $row['state_code'] === null ? null : (string)$row['state_code'],
    ];
}

function sales_require_schema(): void
{
    $requiredTables = [
        'branches', 'companies', 'users',
        'food_customers', 'food_products', 'food_seller_types', 'food_product_sale_prices',
        'food_units', 'hsn_master',
        'food_purchases', 'food_purchase_items',
        'food_sales', 'food_sale_items',
        'accounts', 'food_customer_payments',
        'food_payment_methods', 'food_customer_payment_details',
        'food_customer_payment_allocations', 'food_customer_credit_ledger', 'food_stock_movements',
    ];

    // One metadata read instead of a separate SHOW TABLES request per table.
    $tablePlaceholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $tableStmt = db()->prepare(
        'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $tablePlaceholders . ')'
    );
    $tableStmt->execute($requiredTables);
    $foundTables = array_fill_keys(array_map('strtolower', $tableStmt->fetchAll(PDO::FETCH_COLUMN)), true);
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!isset($foundTables[strtolower($table)])) $missingTables[] = $table;
    }

    if ($missingTables) {
        json_error(
            'Sales POS schema is incomplete. Missing table(s): ' . implode(', ', $missingTables) .
            '. Run sql/sales-pos-schema-update.sql first.',
            500
        );
    }

    $requiredColumns = [
        'food_sales' => [
            'sales_no', 'document_type', 'due_date',
            'customer_reference', 'branch_state_code', 'customer_state_code',
            'item_discount_total', 'overall_discount_type', 'overall_discount_value',
            'overall_discount_amount', 'cess_total', 'before_round_total',
            'round_off_enabled', 'paid_amount', 'balance_amount', 'payment_status',
        ],
        'food_seller_types' => [
            'seller_type_name', 'status',
        ],
        'food_product_sale_prices' => [
            'seller_type_id', 'seller_type_name', 'sale_price', 'status',
        ],
        'food_sale_items' => [
            'source_purchase_id', 'selected_unit_id', 'primary_unit_id',
            'secondary_unit_id', 'conversion_rate', 'primary_quantity', 'secondary_quantity', 'base_quantity',
            'seller_type_id', 'unit_price', 'seller_type_name', 'discount_type', 'discount_value',
            'overall_discount_share', 'tax_type', 'hsn_id',
            'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate',
            'cess_rate', 'cess_amount',
        ],
        'food_customer_payments' => [
            'payment_no', 'source_sale_id', 'payment_date', 'payment_type',
            'amount', 'discount_type', 'discount_value', 'discount_amount',
            'credit_applied', 'notes', 'posting_status', 'status',
        ],
        'food_payment_methods' => [
            'method_code', 'account_type', 'requires_reference',
            'requires_cheque_details', 'sort_order', 'status',
        ],
        'food_customer_payment_details' => [
            'customer_payment_id', 'branch_id', 'payment_method_id', 'account_id',
            'amount', 'payment_reference', 'cheque_no', 'cheque_date', 'status',
        ],
        'food_customer_payment_allocations' => [
            'allocation_type', 'allocated_amount', 'credit_amount', 'discount_amount', 'status',
        ],
        'food_customer_credit_ledger' => [
            'customer_id', 'transaction_date', 'transaction_type', 'reference_type',
            'reference_id', 'amount_in', 'amount_out', 'remarks', 'status',
        ],
        'food_stock_movements' => [
            'source_purchase_id', 'unit_id', 'conversion_rate',
            'quantity_in', 'quantity_out', 'remarks',
        ],
    ];

    // One batched metadata read replaces dozens of SHOW COLUMNS statements.
    $columnTables = array_keys($requiredColumns);
    $columnPlaceholders = implode(',', array_fill(0, count($columnTables), '?'));
    $columnStmt = db()->prepare(
        'SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $columnPlaceholders . ')'
    );
    $columnStmt->execute($columnTables);
    $foundColumns = [];
    foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $columnRow) {
        $foundColumns[strtolower((string)$columnRow['TABLE_NAME'])][strtolower((string)$columnRow['COLUMN_NAME'])] = true;
    }
    $missingColumns = [];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (!isset($foundColumns[strtolower($table)][strtolower($column)])) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }

    if ($missingColumns) {
        json_error(
            'Sales POS schema update is required. Missing column(s): ' . implode(', ', $missingColumns) .
            '. Run sql/sales-pos-schema-update.sql first.',
            500
        );
    }

    sales_require_account_foreign_keys();
}

function sales_has_action(array $actions, int $actionId): bool
{
    foreach ($actions as $action) {
        if ((int)$action === $actionId) {
            return true;
        }
    }
    return false;
}

function sales_require_action(array $access, int $actionId, string $message): void
{
    if (!sales_has_action((array)($access['actions'] ?? []), $actionId)) {
        json_error($message, 403);
    }
}

function sales_document_action_id(int $documentType): int
{
    switch ($documentType) {
        case 1: return SALES_ACTION_QUOTATION;
        case 2: return SALES_ACTION_PROFORMA;
        case 3: return SALES_ACTION_SALES_BILL;
        case 4: return SALES_ACTION_FINAL_INVOICE;
    }
    return 0;
}

function sales_document_label(int $documentType): string
{
    switch ($documentType) {
        case 1: return 'Quotation';
        case 2: return 'Proforma Bill';
        case 3: return 'Sales Bill';
        case 4: return 'Final Invoice';
    }
    return 'Sales Document';
}

function sales_prefix(int $documentType, int $taxMode): string
{
    switch ($documentType) {
        case 1: return 'QTN';
        case 2: return 'PFI';
        case 3: return 'SAL';
        case 4: return $taxMode === 0 ? 'NOG' : 'INV';
    }
    return 'SAL';
}

function sales_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Sales reference is required.', 422, ['ref' => 'Sales reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'sale');
    } catch (Throwable $exception) {
        json_error('Invalid Sales reference.', 422, ['ref' => 'Invalid Sales reference.']);
    }

    return 0;
}

function sales_target_ref(int $documentType): string
{
    if (!in_array($documentType, [1,2,3,4], true)) {
        throw new InvalidArgumentException('Invalid Sales target type.');
    }
    return encryptReference('sales_target', $documentType);
}

function sales_target_refs(): array
{
    return [
        '1' => sales_target_ref(1),
        '2' => sales_target_ref(2),
        '3' => sales_target_ref(3),
        '4' => sales_target_ref(4),
    ];
}

function sales_target_type_from_ref($value, bool $required = true): int
{
    if (!is_string($value) || trim($value) === '') {
        if ($required) {
            json_error('Sales target reference is required.', 422, [
                'target_ref' => 'Sales target reference is required.',
            ]);
        }
        return 0;
    }

    try {
        $documentType = (int)decryptReference(trim($value), 'sales_target');
    } catch (Throwable $exception) {
        json_error('Invalid Sales target reference.', 422, [
            'target_ref' => 'Invalid Sales target reference.',
        ]);
        return 0;
    }

    if (!in_array($documentType, [1,2,3,4], true)) {
        json_error('Invalid Sales target reference.', 422, [
            'target_ref' => 'Invalid Sales target reference.',
        ]);
    }

    return $documentType;
}

function sales_effective_conversion(float $saved, float $master, bool $hasSecondary): float
{
    if (!$hasSecondary) return 1.0;
    if ($saved <= 0 && $master > 0) return $master;
    if ($master > 1.0000001 && $saved > 0 && $saved <= 1.0000001) return $master;
    return $saved > 0 ? $saved : ($master > 0 ? $master : 1.0);
}

function sales_batch_conversion_meta(array $row): array
{
    $primaryUnitId = (int)($row['primary_unit_id'] ?? 0);
    $secondaryUnitId = !empty($row['secondary_unit_id']) ? (int)$row['secondary_unit_id'] : null;
    $savedConversion = (float)($row['conversion_rate'] ?? 0);

    if ($secondaryUnitId === null) {
        return [
            'conversion_rate' => 1.0,
            'saved_conversion_rate' => $savedConversion,
            'legacy_repaired' => false,
        ];
    }

    $masterPrimary = (int)($row['master_primary_unit_id'] ?? 0);
    $masterSecondary = !empty($row['master_secondary_unit_id']) ? (int)$row['master_secondary_unit_id'] : null;
    $masterConversion = (float)($row['master_conversion_rate'] ?? 0);
    $sameUnits = $primaryUnitId > 0
        && $masterPrimary === $primaryUnitId
        && $masterSecondary !== null
        && $masterSecondary === $secondaryUnitId;

    $effective = $savedConversion;
    $legacyRepaired = false;

    /* Legacy Purchase rows created before mixed-unit support sometimes saved
       conversion_rate=1 even though the same Product Master units are KG->GRM
       (or another >1 conversion). Repair only when the unit IDs still match. */
    if ($sameUnits && $masterConversion > 1.0000001 && $savedConversion <= 1.0000001) {
        $effective = $masterConversion;
        $legacyRepaired = true;
    } elseif ($effective <= 0 && $sameUnits && $masterConversion > 0) {
        $effective = $masterConversion;
        $legacyRepaired = true;
    }

    return [
        'conversion_rate' => $effective,
        'saved_conversion_rate' => $savedConversion,
        'legacy_repaired' => $legacyRepaired,
    ];
}

function sales_nullable($value)
{
    if ($value === null) return null;
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function sales_valid_date($value, string $field, bool $required)
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) {
            json_error('Date is required.', 422, [$field => 'Date is required.']);
        }
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field => 'Enter a valid date.']);
    }

    return $value;
}

function sales_generate_no_locked(PDO $pdo, int $branchId, int $documentType, int $taxMode): string
{
    /* Serialize number generation per branch without adding a number-sequence table. */
    $lock = $pdo->prepare('SELECT id FROM branches WHERE id=:branch_id FOR UPDATE');
    $lock->execute([':branch_id' => $branchId]);
    if (!$lock->fetchColumn()) {
        json_error('Branch was not found.', 404);
    }

    $prefix = sales_prefix($documentType, $taxMode);
    $regex = '^' . preg_quote($prefix, '/') . '[0-9]+$';
    $startAt = strlen($prefix) + 1;

    $stmt = $pdo->prepare(
        'SELECT sales_no
         FROM food_sales
         WHERE branch_id=:branch_id
           AND sales_no REGEXP :pattern
         ORDER BY CAST(SUBSTRING(sales_no,' . (int)$startAt . ') AS UNSIGNED) DESC
         LIMIT 1'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':pattern' => $regex,
    ]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function sales_customer(int $branchId, int $customerId, bool $requireActive = true): array
{
    $sql = 'SELECT id,customer_code,customer_name,mobile,email,gstin,state_code,credit_limit,opening_balance,status
            FROM food_customers
            WHERE id=:id AND branch_id=:branch_id';
    if ($requireActive) $sql .= ' AND status=1';
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $customerId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Selected Customer is inactive or unavailable.', 422, [
            'customer_id' => 'Select an active Customer.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    $row['credit_limit'] = (float)$row['credit_limit'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    return $row;
}

/** No state field is required in POS. Use existing master data and GSTIN first. */
function sales_state_code($value): string
{
    $value = trim((string)($value ?? ''));
    if ($value === '' || !preg_match('/^[0-9]{1,2}$/', $value)) return '';
    $number = (int)$value;
    return $number >= 1 && $number <= 99 ? str_pad((string)$number,2,'0',STR_PAD_LEFT) : '';
}

function sales_customer_state(array $customer, string $branchState): string
{
    $gstin = strtoupper(trim((string)($customer['gstin'] ?? '')));
    if (preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', $gstin)) {
        $fromGstin = sales_state_code(substr($gstin,0,2));
        if ($fromGstin !== '') return $fromGstin;
    }
    $saved = sales_state_code($customer['state_code'] ?? '');
    if ($saved !== '') return $saved;
    // For unregistered walk-in customers with no recorded state, use branch
    // as an application fallback. No extra State Code input is presented.
    return $branchState;
}

function sales_product(int $branchId, int $productId, bool $requireActive = true): array
{
    $sql = 'SELECT p.id,p.product_code,p.product_name,p.primary_unit_id,p.secondary_unit_id,p.secondary_conversion,
                   p.hsn_id,p.sale_price,p.sale_tax_type,p.purchase_price,p.status,
                   pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                   su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol,
                   h.hsn_code,h.gst_rate,h.cgst_rate,h.sgst_rate,h.igst_rate,h.cess_rate
            FROM food_products p
            INNER JOIN food_units pu ON pu.id=p.primary_unit_id AND pu.branch_id=p.branch_id
            LEFT JOIN food_units su ON su.id=p.secondary_unit_id AND su.branch_id=p.branch_id
            LEFT JOIN hsn_master h ON h.id=p.hsn_id
            WHERE p.id=:id AND p.branch_id=:branch_id';
    if ($requireActive) $sql .= ' AND p.status=1';
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $productId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Selected Product is inactive or unavailable.', 422, [
            'items' => 'Select active Products only.',
        ]);
    }

    foreach (['id','primary_unit_id','sale_tax_type','status'] as $key) {
        $row[$key] = (int)$row[$key];
    }
    $row['secondary_unit_id'] = $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'];
    $row['hsn_id'] = $row['hsn_id'] === null ? null : (int)$row['hsn_id'];
    foreach (['secondary_conversion','sale_price','purchase_price','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
        $row[$key] = $row[$key] === null ? 0.0 : (float)$row[$key];
    }

    return $row;
}

function sales_seller_pricing(
    int $branchId,
    int $productId,
    int $sellerTypeId = 0,
    ?string $sellerTypeName = null,
    bool $requireActive = true
): ?array {
    $sellerTypeName = trim((string)($sellerTypeName ?? ''));

    if ($sellerTypeId < 1 && $sellerTypeName === '') {
        return null;
    }

    $sql =
        'SELECT sp.seller_type_id,
                st.seller_type_name,
                sp.sale_price,
                sp.status AS price_status,
                st.status AS seller_type_status
         FROM food_product_sale_prices sp
         INNER JOIN food_seller_types st
                 ON st.id=sp.seller_type_id
                AND st.branch_id=sp.branch_id
         WHERE sp.branch_id=:branch_id
           AND sp.product_id=:product_id';

    $params = [
        ':branch_id' => $branchId,
        ':product_id' => $productId,
    ];

    if ($sellerTypeId > 0) {
        $sql .= ' AND sp.seller_type_id=:seller_type_id';
        $params[':seller_type_id'] = $sellerTypeId;
    } else {
        $sql .= ' AND LOWER(TRIM(st.seller_type_name))=LOWER(:seller_type_name)';
        $params[':seller_type_name'] = $sellerTypeName;
    }

    if ($requireActive) {
        $sql .= ' AND sp.status=1 AND st.status=1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row) {
        json_error(
            'Selected Seller Type is unavailable for this Product.',
            422,
            ['items' => 'Select a valid Seller Type configured in Product Master.']
        );
    }

    return [
        'seller_type_id' => (int)$row['seller_type_id'],
        'seller_type_name' => (string)$row['seller_type_name'],
        'sale_price' => round((float)$row['sale_price'], 2),
        'price_status' => (int)$row['price_status'],
        'seller_type_status' => (int)$row['seller_type_status'],
    ];
}

/**
 * Single canonical account master across Sales POS: accounts.
 * The FK migration must be applied before processing existing payment records.
 */
function sales_payment_account_table(): string
{
    return 'accounts';
}

function sales_require_account_foreign_keys(): void
{
    foreach (['food_customer_payments','food_customer_payment_details'] as $child) {
        $stmt = db()->prepare(
            "SELECT REFERENCED_TABLE_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=:child
               AND COLUMN_NAME='account_id'
               AND REFERENCED_COLUMN_NAME='id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );
        $stmt->execute([':child' => $child]);
        $parent = (string)($stmt->fetchColumn() ?: '');
        if ($parent !== 'accounts') {
            json_error(
                'Sales payment account migration is required. Run sql/02-migrate-sales-account-fks.sql (after backup) so ' .
                $child . '.account_id references accounts.id.',
                500,
                ['schema' => 'Sales POS uses only accounts; the payment FK must reference accounts.']
            );
        }
    }
}

/** Some installations have optional account-display/default columns. */
function sales_payment_account_select_columns(): string
{
    static $select = null;
    if ($select !== null) return $select;
    $table = sales_payment_account_table();
    $stmt = db()->query('SHOW COLUMNS FROM `' . $table . '`');
    $columns = array_map('strtolower', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field'));
    foreach (['id','branch_id','account_name','account_type','status'] as $required) {
        if (!in_array($required, $columns, true)) {
            json_error('The POS payment account master is missing required column: ' . $required, 500);
        }
    }
    $fields = ['id','branch_id','account_name','account_type','status'];
    foreach (['account_code','bank_name','account_number','upi_id','is_default_cash','is_default_bank'] as $optional) {
        $fields[] = in_array($optional,$columns,true)
            ? '`' . $optional . '`'
            : ($optional === 'is_default_cash' || $optional === 'is_default_bank'
                ? '0 AS `' . $optional . '`'
                : "'' AS `" . $optional . '`');
    }
    $select = implode(',', $fields);
    return $select;
}

function sales_payment_account_default(int $branchId, int $accountType): array
{
    $stmt = db()->prepare(
        'SELECT ' . sales_payment_account_select_columns() . '
         FROM `' . sales_payment_account_table() . '`
         WHERE branch_id=:branch_id AND account_type=:account_type AND status=1
         ORDER BY ' . ($accountType === 1 ? 'is_default_cash DESC,' : 'is_default_bank DESC,') . ' id ASC LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId, ':account_type'=>$accountType]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) json_error(
        $accountType === 1
            ? 'Configure an active Cash Account in the payment account master before receiving Cash.'
            : 'Configure an active Bank Account in the payment account master before receiving UPI / Bank / Cheque.',
        422, ['payments'=>'No matching active payment Account was found.']
    );
    return $account;
}

function sales_account(int $branchId, int $accountId): array
{
    $stmt = db()->prepare(
        'SELECT ' . sales_payment_account_select_columns() . '
         FROM `' . sales_payment_account_table() . '`
         WHERE id=:id AND branch_id=:branch_id AND status=1
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Selected payment Account is inactive or unavailable.', 422, [
            'payments' => 'Select an active Account.',
        ]);
    }
    $row['id'] = (int)$row['id'];
    $row['account_type'] = (int)$row['account_type'];
    return $row;
}


function sales_payment_methods(PDO $pdo, int $branchId): array
{
    $stmt = $pdo->prepare(
        "SELECT id,method_code,method_name,account_type,requires_reference,requires_cheque_details,status
         FROM food_payment_methods
         WHERE branch_id=:branch_id
           AND method_code IN ('CASH','UPI','BANK','CHEQUE')
         ORDER BY sort_order,id"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rowsByCode = [];
    foreach ($stmt->fetchAll() as $row) {
        $code = strtoupper(trim((string)$row['method_code']));
        if ($code === '') continue;
        $row['id'] = (int)$row['id'];
        $row['account_type'] = (int)$row['account_type'];
        $row['requires_reference'] = (int)$row['requires_reference'];
        $row['requires_cheque_details'] = (int)$row['requires_cheque_details'];
        $row['status'] = (int)$row['status'];
        $rowsByCode[$code] = $row;
    }

    $modeCodes = [1 => 'CASH', 2 => 'UPI', 3 => 'BANK', 4 => 'CHEQUE'];
    $result = [];
    foreach ($modeCodes as $mode => $code) {
        if (isset($rowsByCode[$code]) && (int)$rowsByCode[$code]['status'] === 1) {
            $result[$mode] = $rowsByCode[$code];
        }
    }
    return $result;
}

function sales_pos_payment_no(string $salesNo): string
{
    return substr('POS-' . strtoupper(trim($salesNo)), 0, 30);
}

function sales_purchase_source(int $branchId, int $sourcePurchaseId, int $productId): array
{
    /* A Purchase header is the stock batch source in AMIRTHAM.  Always take the
       unit/conversion snapshot from that Purchase Item first.  Product Master
       may be changed later and must not reinterpret an existing batch. */
    $stmt = db()->prepare(
        'SELECT p.id,p.purchase_no,p.batch_number,p.purchase_date,
                pi.id AS purchase_item_id,pi.batch_id,pi.expiry_date,
                pi.selected_unit_id AS purchase_unit_id,
                pi.primary_unit_id,pi.secondary_unit_id,pi.conversion_rate,
                pi.primary_quantity,pi.secondary_quantity,
                pi.unit_price AS purchase_unit_price,
                pi.base_quantity AS purchased_base_quantity,
                pi.taxable_amount AS purchase_taxable_amount,
                pm.primary_unit_id AS master_primary_unit_id,
                pm.secondary_unit_id AS master_secondary_unit_id,
                pm.secondary_conversion AS master_conversion_rate,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol
         FROM food_purchases p
         INNER JOIN food_purchase_items pi
            ON pi.purchase_id=p.id
           AND pi.branch_id=p.branch_id
           AND pi.product_id=:product_id
           AND pi.status=1
         INNER JOIN food_products pm
            ON pm.id=pi.product_id AND pm.branch_id=pi.branch_id
         LEFT JOIN food_units pu
            ON pu.id=pi.primary_unit_id AND pu.branch_id=pi.branch_id
         LEFT JOIN food_units su
            ON su.id=pi.secondary_unit_id AND su.branch_id=pi.branch_id
         WHERE p.id=:purchase_id
           AND p.branch_id=:branch_id
           AND p.posting_status=1
           AND p.status=1
         LIMIT 1'
    );
    $stmt->execute([
        ':purchase_id' => $sourcePurchaseId,
        ':branch_id' => $branchId,
        ':product_id' => $productId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Selected Batch is unavailable for this Product.', 422, [
            'items' => 'Select a valid posted Purchase Batch.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['purchase_item_id'] = (int)$row['purchase_item_id'];
    $row['batch_id'] = $row['batch_id'] === null ? null : (int)$row['batch_id'];
    $row['purchase_unit_id'] = $row['purchase_unit_id'] === null ? null : (int)$row['purchase_unit_id'];
    $row['primary_unit_id'] = $row['primary_unit_id'] === null ? 0 : (int)$row['primary_unit_id'];
    $row['secondary_unit_id'] = $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'];
    $row['master_primary_unit_id'] = $row['master_primary_unit_id'] === null ? 0 : (int)$row['master_primary_unit_id'];
    $row['master_secondary_unit_id'] = $row['master_secondary_unit_id'] === null ? null : (int)$row['master_secondary_unit_id'];
    $row['master_conversion_rate'] = (float)($row['master_conversion_rate'] ?? 0);
    $row['primary_quantity'] = (float)($row['primary_quantity'] ?? 0);
    $row['secondary_quantity'] = (float)($row['secondary_quantity'] ?? 0);
    $row['conversion_rate'] = (float)($row['conversion_rate'] ?? 0);
    $row['purchase_unit_price'] = (float)($row['purchase_unit_price'] ?? 0);
    $row['purchased_base_quantity'] = (float)$row['purchased_base_quantity'];
    $row['purchase_taxable_amount'] = (float)$row['purchase_taxable_amount'];

    $conversionMeta = sales_batch_conversion_meta($row);
    $row['saved_conversion_rate'] = (float)$conversionMeta['saved_conversion_rate'];
    $row['conversion_rate'] = (float)$conversionMeta['conversion_rate'];
    $row['legacy_conversion_repaired'] = (bool)$conversionMeta['legacy_repaired'];
    if ($row['legacy_conversion_repaired']) {
        $row['purchased_base_quantity'] = round(
            ($row['primary_quantity'] * $row['conversion_rate']) + $row['secondary_quantity'],
            3
        );
    }

    if ($row['primary_unit_id'] < 1) {
        json_error('Selected Purchase Batch has no saved Primary Unit snapshot.', 409, [
            'items' => 'Recreate/fix the Purchase Batch before using it in Sales.'
        ]);
    }
    if ($row['secondary_unit_id'] !== null && $row['conversion_rate'] <= 0) {
        json_error('Selected Purchase Batch has an invalid saved unit conversion.', 409, [
            'items' => 'Recreate/fix the Purchase Batch before using it in Sales.'
        ]);
    }
    return $row;
}

function sales_available_base_stock(int $branchId, int $productId, int $sourcePurchaseId, ?array $source = null): float
{
    $source = $source ?? sales_purchase_source($branchId, $sourcePurchaseId, $productId);
    $repairLegacy = !empty($source['legacy_conversion_repaired']);
    $effectiveConversion = (float)($source['conversion_rate'] ?? 1);
    $primaryUnitId = (int)($source['primary_unit_id'] ?? 0);

    $stmt = db()->prepare(
        'SELECT quantity_in,quantity_out,unit_id,conversion_rate
         FROM food_stock_movements
         WHERE branch_id=:branch_id
           AND product_id=:product_id
           AND source_purchase_id=:source_purchase_id
           AND status=1'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
        ':source_purchase_id' => $sourcePurchaseId,
    ]);

    $total = 0.0;
    foreach ($stmt->fetchAll() as $movement) {
        $delta = (float)$movement['quantity_in'] - (float)$movement['quantity_out'];
        $movementConversion = (float)($movement['conversion_rate'] ?? 0);
        $movementUnitId = $movement['unit_id'] === null ? 0 : (int)$movement['unit_id'];

        /* Old movements for a legacy conversion=1 batch were stored in Primary
           quantity. Normalize only those rows; new repaired movements already
           carry the real base-unit conversion and must not be multiplied again. */
        if (
            $repairLegacy
            && $effectiveConversion > 1.0000001
            && $movementConversion <= 1.0000001
            && ($movementUnitId === 0 || $movementUnitId === $primaryUnitId)
        ) {
            $delta *= $effectiveConversion;
        }
        $total += $delta;
    }

    return round($total, 3);
}

function sales_old_item_restore_map(array $oldSale): array
{
    $map = [];
    foreach ((array)($oldSale['items'] ?? []) as $item) {
        $key = ((int)$item['product_id']) . ':' . ((int)$item['source_purchase_id']);
        $map[$key] = ($map[$key] ?? 0.0) + (float)$item['base_quantity'];
    }
    return $map;
}

/** Customer refresh without refetching the entire product and Seller Type catalog. */
function sales_customer_options(int $branchId, bool $includeInactive = false): array
{
    $customerSql = 'SELECT c.id,c.customer_code,c.customer_name,c.mobile,c.gstin,c.state_code,c.status,
                           COALESCE((
                               SELECT SUM(l.amount_in-l.amount_out)
                               FROM food_customer_credit_ledger l
                               WHERE l.branch_id=c.branch_id
                                 AND l.customer_id=c.id
                                 AND l.status=1
                           ),0) AS available_credit
                    FROM food_customers c
                    WHERE c.branch_id=:branch_id';
    if (!$includeInactive) $customerSql .= ' AND c.status=1';
    $customerSql .= ' ORDER BY c.customer_name';
    $customerStmt = db()->prepare($customerSql);
    $customerStmt->execute([':branch_id' => $branchId]);

    $customers = [];
    foreach ($customerStmt->fetchAll() as $row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
        $row['available_credit'] = round((float)($row['available_credit'] ?? 0), 2);
        $customers[] = $row;
    }

    return $customers;
}

function sales_options(int $branchId, bool $includeInactive = false): array
{
    $productSql = 'SELECT p.id,p.product_code,p.product_name,p.primary_unit_id,p.secondary_unit_id,p.secondary_conversion,
                          p.hsn_id,p.sale_price,p.sale_tax_type,p.status,
                          pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                          su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol,
                          h.hsn_code,h.gst_rate,h.cgst_rate,h.sgst_rate,h.igst_rate,h.cess_rate
                   FROM food_products p
                   INNER JOIN food_units pu ON pu.id=p.primary_unit_id AND pu.branch_id=p.branch_id
                   LEFT JOIN food_units su ON su.id=p.secondary_unit_id AND su.branch_id=p.branch_id
                   LEFT JOIN hsn_master h ON h.id=p.hsn_id
                   WHERE p.branch_id=:branch_id';
    if (!$includeInactive) $productSql .= ' AND p.status=1';
    $productSql .= ' ORDER BY p.product_name';
    $productStmt = db()->prepare($productSql);
    $productStmt->execute([':branch_id' => $branchId]);

    $priceStmt = db()->prepare(
        'SELECT sp.id,sp.product_id,sp.seller_type_id,st.seller_type_name,
                sp.markup_type,sp.markup_value,sp.sale_price,
                sp.status,st.status AS seller_type_status
         FROM food_product_sale_prices sp
         INNER JOIN food_seller_types st
                 ON st.id=sp.seller_type_id
                AND st.branch_id=sp.branch_id
         WHERE sp.branch_id=:branch_id' .
         ($includeInactive ? '' : ' AND sp.status=1 AND st.status=1') . '
         ORDER BY sp.product_id,st.sort_order,st.seller_type_name,sp.id'
    );
    $priceStmt->execute([':branch_id' => $branchId]);
    $pricesByProduct = [];
    foreach ($priceStmt->fetchAll() as $row) {
        $row['id'] = (int)$row['id'];
        $row['product_id'] = (int)$row['product_id'];
        $row['seller_type_id'] = (int)$row['seller_type_id'];
        $row['seller_type_status'] = (int)$row['seller_type_status'];
        $row['markup_type'] = (int)$row['markup_type'];
        $row['markup_value'] = (float)$row['markup_value'];
        $row['sale_price'] = (float)$row['sale_price'];
        $row['status'] = (int)$row['status'];
        if (!isset($pricesByProduct[$row['product_id']])) {
            $pricesByProduct[$row['product_id']] = [];
        }
        $pricesByProduct[$row['product_id']][] = $row;
    }

    $accountSql = 'SELECT ' . sales_payment_account_select_columns() . '
                   FROM `' . sales_payment_account_table() . '` WHERE branch_id=:branch_id';
    if (!$includeInactive) $accountSql .= ' AND status=1';
    $accountSql .= ' ORDER BY account_type, is_default_cash DESC, is_default_bank DESC, account_name, id';
    $accountStmt = db()->prepare($accountSql);
    $accountStmt->execute([':branch_id' => $branchId]);

    $customers = sales_customer_options($branchId, $includeInactive);

    $products = [];
    foreach ($productStmt->fetchAll() as $row) {
        foreach (['id','primary_unit_id','sale_tax_type','status'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $row['secondary_unit_id'] = $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'];
        $row['hsn_id'] = $row['hsn_id'] === null ? null : (int)$row['hsn_id'];
        foreach (['secondary_conversion','sale_price','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
            $row[$key] = $row[$key] === null ? 0.0 : (float)$row[$key];
        }
        $row['sale_prices'] = $pricesByProduct[$row['id']] ?? [];
        $products[] = $row;
    }

    $accounts = [];
    foreach ($accountStmt->fetchAll() as $row) {
        $row['id'] = (int)$row['id'];
        $row['account_type'] = (int)$row['account_type'];
        $row['status'] = (int)$row['status'];
        $accounts[] = $row;
    }

    return [
        'customers' => $customers,
        'products' => $products,
        'accounts' => $accounts,
    ];
}

function sales_record(array $context, int $id): array
{
    $stmt = db()->prepare(
        'SELECT s.*,
                c.customer_code,c.customer_name AS current_customer_name,c.mobile AS customer_mobile
         FROM food_sales s
         LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
         WHERE s.id=:id AND s.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$context['branch_id'],
    ]);
    $sale = $stmt->fetch();
    if (!$sale) {
        json_error('Sales document was not found.', 404);
    }

    foreach (['id','branch_id','customer_id','document_type','tax_mode','overall_discount_type','round_off_enabled','payment_status','posting_status','status'] as $key) {
        $sale[$key] = $sale[$key] === null ? null : (int)$sale[$key];
    }
    foreach (['subtotal','item_discount_total','discount_total','overall_discount_value','overall_discount_amount','taxable_total','cgst_total','sgst_total','igst_total','cess_total','before_round_total','round_off','grand_total','paid_amount','balance_amount'] as $key) {
        $sale[$key] = (float)$sale[$key];
    }
    $sale['ref'] = encryptReference('sale', (int)$sale['id']);

    $itemStmt = db()->prepare(
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
    $itemStmt->execute([
        ':sale_id' => $id,
        ':branch_id' => (int)$context['branch_id'],
    ]);

    $items = [];
    foreach ($itemStmt->fetchAll() as $row) {
        foreach (['id','sale_id','branch_id','product_id','source_purchase_id','batch_id','selected_unit_id','primary_unit_id','secondary_unit_id','seller_type_id','discount_type','tax_type','hsn_id','status'] as $key) {
            $row[$key] = $row[$key] === null ? null : (int)$row[$key];
        }
        foreach (['conversion_rate','primary_quantity','secondary_quantity','quantity','base_quantity','unit_price','discount_value','discount_amount','overall_discount_share','taxable_amount','tax_rate','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate','cgst_amount','sgst_amount','igst_amount','cess_amount','line_total'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        /*
         * Sale Item is the immutable Sales snapshot.  It was copied from the
         * selected Purchase Batch when the Sale was created.  For legacy rows
         * only, fall back to the original Purchase Item snapshot.  Never use
         * today's Product Master to reinterpret an old Sale.
         */
        $salePrimary = (int)($row['primary_unit_id'] ?? 0);
        $saleSecondary = (int)($row['secondary_unit_id'] ?? 0);
        $batchPrimary = (int)($row['batch_primary_unit_id'] ?? 0);
        $batchSecondary = (int)($row['batch_secondary_unit_id'] ?? 0);

        $primaryUnitId = $salePrimary > 0 ? $salePrimary : $batchPrimary;
        $secondaryUnitId = $salePrimary > 0
            ? ($saleSecondary > 0 ? $saleSecondary : null)
            : ($batchSecondary > 0 ? $batchSecondary : null);
        $hasSecondary = $secondaryUnitId !== null;

        if ($primaryUnitId < 1) {
            json_error('Sales Item unit snapshot is incomplete. Recreate the affected Sales document.', 409);
        }

        if ($hasSecondary) {
            $effectiveConversion = (float)($row['conversion_rate'] ?? 0);
            $masterPrimary = (int)($row['master_primary_unit_id'] ?? 0);
            $masterSecondary = !empty($row['master_secondary_unit_id']) ? (int)$row['master_secondary_unit_id'] : null;
            $masterConversion = (float)($row['master_conversion_rate'] ?? 0);
            $sameMasterUnits = $masterPrimary === $primaryUnitId
                && $masterSecondary !== null
                && $masterSecondary === $secondaryUnitId;

            if ($sameMasterUnits && $masterConversion > 1.0000001 && $effectiveConversion <= 1.0000001) {
                $effectiveConversion = $masterConversion;
            } elseif ($effectiveConversion <= 0 && $batchPrimary > 0) {
                $batchRow = [
                    'primary_unit_id' => $batchPrimary,
                    'secondary_unit_id' => $batchSecondary > 0 ? $batchSecondary : null,
                    'conversion_rate' => (float)($row['batch_conversion_rate'] ?? 0),
                    'master_primary_unit_id' => $masterPrimary,
                    'master_secondary_unit_id' => $masterSecondary,
                    'master_conversion_rate' => $masterConversion,
                ];
                $effectiveConversion = (float)sales_batch_conversion_meta($batchRow)['conversion_rate'];
            }
            if ($effectiveConversion <= 0) {
                json_error('Sales Item conversion snapshot is invalid. Recreate the affected Sales document.', 409);
            }
        } else {
            $effectiveConversion = 1.0;
            $row['secondary_quantity'] = 0.0;
        }

        $row['primary_unit_id'] = $primaryUnitId;
        $row['secondary_unit_id'] = $secondaryUnitId;
        $row['conversion_rate'] = $effectiveConversion;
        $row['batch_id'] = !empty($row['batch_item_batch_id']) ? (int)$row['batch_item_batch_id'] : $row['batch_id'];
        $row['primary_unit_name'] = (string)(($row['sale_primary_unit_name'] ?? '') ?: ($row['batch_primary_unit_name'] ?? ''));
        $row['primary_unit_symbol'] = (string)(($row['sale_primary_unit_symbol'] ?? '') ?: ($row['batch_primary_unit_symbol'] ?? ''));
        $row['secondary_unit_name'] = $hasSecondary ? (string)(($row['sale_secondary_unit_name'] ?? '') ?: ($row['batch_secondary_unit_name'] ?? '')) : '';
        $row['secondary_unit_symbol'] = $hasSecondary ? (string)(($row['sale_secondary_unit_symbol'] ?? '') ?: ($row['batch_secondary_unit_symbol'] ?? '')) : '';

        if ($row['primary_quantity'] <= 0 && $row['secondary_quantity'] <= 0 && $row['quantity'] > 0) {
            if ($hasSecondary && (int)$row['selected_unit_id'] === (int)$secondaryUnitId) {
                $row['secondary_quantity'] = $row['quantity'];
            } else {
                $row['primary_quantity'] = $row['quantity'];
            }
        }
        if ($row['primary_quantity'] > 0 || $row['secondary_quantity'] > 0) {
            $row['base_quantity'] = round(($row['primary_quantity'] * $effectiveConversion) + $row['secondary_quantity'], 3);
            $row['quantity'] = round($row['primary_quantity'] + ($hasSecondary ? $row['secondary_quantity'] / $effectiveConversion : 0), 3);
        }

        $items[] = $row;
    }
    $sale['items'] = $items;

    /* Sales POS now owns one Customer Payment header per Final Invoice.
       Cash / UPI / Bank / Cheque are stored as detail rows under that header. */
    $paymentStmt = db()->prepare(
        "SELECT d.id,cp.payment_date,d.account_id,d.amount,
                LOWER(pm.method_code) AS payment_mode,
                d.payment_reference,d.cheque_no,d.cheque_date,
                a.account_name,a.account_type
         FROM food_customer_payments cp
         INNER JOIN food_customer_payment_details d
            ON d.customer_payment_id=cp.id
           AND d.branch_id=cp.branch_id
           AND d.status=1
         INNER JOIN food_payment_methods pm
            ON pm.id=d.payment_method_id
           AND pm.branch_id=d.branch_id
           AND pm.status=1
         LEFT JOIN `" . sales_payment_account_table() . "` a
            ON a.id=d.account_id
           AND a.branch_id=d.branch_id
         WHERE cp.source_sale_id=:sale_id
           AND cp.branch_id=:branch_id
           AND cp.status=1
           AND cp.posting_status=1
         ORDER BY pm.sort_order,pm.id,d.id"
    );
    $paymentStmt->execute([
        ':sale_id' => $id,
        ':branch_id' => (int)$context['branch_id'],
    ]);
    $payments = [];
    foreach ($paymentStmt->fetchAll() as $row) {
        $row['id'] = (int)$row['id'];
        $row['account_id'] = $row['account_id'] === null ? null : (int)$row['account_id'];
        $row['account_type'] = $row['account_type'] === null ? null : (int)$row['account_type'];
        $row['amount'] = (float)$row['amount'];
        $payments[] = $row;
    }

    /* Compatibility: older POS builds stored one payment header per mode.
       Load those rows until that invoice is edited/saved once; save will
       consolidate them into one header + detail rows automatically. */
    if (!$payments) {
        $legacyStmt = db()->prepare(
            'SELECT cp.id,cp.account_id,cp.payment_date,cp.amount,cp.payment_mode,
                    cp.payment_reference,
                    NULL AS cheque_no,NULL AS cheque_date,
                    a.account_name,a.account_type
             FROM food_customer_payments cp
             LEFT JOIN `' . sales_payment_account_table() . '` a
                ON a.id=cp.account_id AND a.branch_id=cp.branch_id
             WHERE cp.source_sale_id=:sale_id
               AND cp.branch_id=:branch_id
               AND cp.status=1
               AND cp.posting_status=1
               AND cp.amount>0
               AND cp.account_id IS NOT NULL
               AND cp.payment_mode IS NOT NULL
             ORDER BY cp.id'
        );
        $legacyStmt->execute([
            ':sale_id' => $id,
            ':branch_id' => (int)$context['branch_id'],
        ]);
        foreach ($legacyStmt->fetchAll() as $row) {
            $row['id'] = (int)$row['id'];
            $row['account_id'] = $row['account_id'] === null ? null : (int)$row['account_id'];
            $row['account_type'] = $row['account_type'] === null ? null : (int)$row['account_type'];
            $row['amount'] = (float)$row['amount'];
            $payments[] = $row;
        }
    }
    $sale['payments'] = $payments;

    /* Load POS-owned Customer Credit usage separately from physical payment
       detail rows. Credit can settle a Final Invoice even when there is no
       Cash / UPI / Bank / Cheque row. */
    $posHeaderStmt = db()->prepare(
        'SELECT id,credit_applied
         FROM food_customer_payments
         WHERE branch_id=:branch_id
           AND source_sale_id=:sale_id
           AND posting_status=1
           AND status=1
         ORDER BY id
         LIMIT 1'
    );
    $posHeaderStmt->execute([
        ':branch_id' => (int)$context['branch_id'],
        ':sale_id' => $id,
    ]);
    $posHeader = $posHeaderStmt->fetch() ?: [];
    $posHeaderId = (int)($posHeader['id'] ?? 0);
    $sale['credit_applied'] = round((float)($posHeader['credit_applied'] ?? 0), 2);
    $sale['available_customer_credit'] = $sale['customer_id']
        ? max(0.0, sales_credit_balance_excluding_payment(
            db(),
            (int)$context['branch_id'],
            (int)$sale['customer_id'],
            $posHeaderId
        ))
        : 0.0;

    /* External settlement includes actual receipt + Customer Credit + Discount.
       POS-owned settlement for this invoice is excluded from this figure. */
    $externalStmt = db()->prepare(
        'SELECT COALESCE(SUM(
                    COALESCE(a.allocated_amount,0)
                  + COALESCE(a.credit_amount,0)
                  + COALESCE(a.discount_amount,0)
                ),0)
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
            ON p.id=a.customer_payment_id
           AND p.branch_id=a.branch_id
           AND p.status=1
           AND p.posting_status=1
         WHERE a.sale_id=:sale_id
           AND a.branch_id=:branch_id
           AND a.status=1
           AND (p.source_sale_id IS NULL OR p.source_sale_id<>:sale_id_compare)'
    );
    $externalStmt->execute([
        ':sale_id' => $id,
        ':branch_id' => (int)$context['branch_id'],
        ':sale_id_compare' => $id,
    ]);
    $sale['external_allocated_amount'] = round((float)$externalStmt->fetchColumn(), 2);

    return $sale;
}

function sales_existing_item_ids(array $sale): array
{
    $ids = [];
    foreach ((array)($sale['items'] ?? []) as $item) {
        $ids[(int)$item['product_id']] = true;
    }
    return $ids;
}

function sales_calculate(array $context, array $data, array $oldSale = [], bool $isConversion = false): array
{
    $branchId = (int)$context['branch_id'];

    $documentType = (int)($data['document_type'] ?? 0);
    if (!in_array($documentType, [1,2,3,4], true)) {
        json_error('Select a valid document type.', 422, ['document_type' => 'Select a valid document type.']);
    }

    $taxMode = (int)($data['tax_mode'] ?? 1);
    if (!in_array($taxMode, [0,1], true)) $taxMode = 1;

    $customerId = (int)($data['customer_id'] ?? 0);
    if ($customerId < 1) {
        json_error('Customer is required.', 422, ['customer_id' => 'Customer is required.']);
    }

    $sameOldCustomer = !empty($oldSale) && (int)($oldSale['customer_id'] ?? 0) === $customerId;
    $customer = sales_customer($branchId, $customerId, !$sameOldCustomer || $isConversion);

    $salesDate = sales_valid_date($data['sales_date'] ?? ($data['invoice_date'] ?? ''), 'sales_date', true);
    $dueDate = sales_valid_date($data['due_date'] ?? '', 'due_date', false);
    if ($dueDate !== null && $dueDate < $salesDate) {
        json_error('Due Date cannot be before Sales Date.', 422, ['due_date' => 'Due Date cannot be before Sales Date.']);
    }

    $branchState = sales_state_code($context['state_code'] ?? '');
    $customerState = sales_customer_state($customer, $branchState);
    // When branch state is not configured, do not invent a state-code snapshot.
    // Local-state tax treatment is the fallback, matching the POS preview.
    $supplyType = $taxMode === 1
        ? (($branchState !== '' && $customerState !== '' && $branchState !== $customerState)
            ? 'inter_state' : 'intra_state')
        : null;

    $overallType = (int)($data['overall_discount_type'] ?? 1);
    if (!in_array($overallType, [1,2], true)) $overallType = 1;
    $overallValue = $data['overall_discount_value'] ?? 0;
    if (!is_numeric($overallValue) || (float)$overallValue < 0) {
        json_error('Enter a valid Overall Discount.', 422, ['overall_discount_value' => 'Enter a valid Overall Discount.']);
    }
    $overallValue = round((float)$overallValue, 2);
    if ($overallType === 1 && $overallValue > 100) {
        json_error('Percentage Discount cannot exceed 100%.', 422, ['overall_discount_value' => 'Percentage Discount cannot exceed 100%.']);
    }

    $roundOffEnabled = (int)($data['round_off_enabled'] ?? 0) === 1 ? 1 : 0;
    $notes = sales_nullable($data['notes'] ?? null);
    $customerReference = sales_nullable($data['customer_reference'] ?? null);

    $rawItems = $data['items'] ?? [];
    if (is_string($rawItems)) {
        $decoded = json_decode($rawItems, true);
        $rawItems = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawItems) || count($rawItems) < 1) {
        json_error('Add at least one Product.', 422, ['items' => 'Add at least one Product.']);
    }

    $oldItemsById = [];
    foreach ((array)($oldSale['items'] ?? []) as $oldItem) {
        $oldItemsById[(int)$oldItem['id']] = $oldItem;
    }
    $oldProductIds = sales_existing_item_ids($oldSale);
    $oldRestore = ((int)($oldSale['document_type'] ?? 0) === 4) ? sales_old_item_restore_map($oldSale) : [];

    $seenProducts = [];
    $items = [];
    $subtotal = 0.0;
    $itemDiscountTotal = 0.0;
    $preOverallTaxableTotal = 0.0;

    foreach (array_values($rawItems) as $index => $raw) {
        if (!is_array($raw)) continue;
        $rowNo = $index + 1;
        $productId = (int)($raw['product_id'] ?? 0);
        if ($productId < 1) {
            json_error('Product is required in row ' . $rowNo . '.', 422, ['items' => 'Product is required in row ' . $rowNo . '.']);
        }
        if (isset($seenProducts[$productId])) {
            json_error('Product is already added in row ' . $rowNo . '. Please edit the existing row.', 422, [
                'items' => 'Product is already added in row ' . $rowNo . '. Please edit the existing row.',
            ]);
        }
        $seenProducts[$productId] = true;

        $itemId = (int)($raw['item_id'] ?? 0);
        $oldItem = ($itemId > 0 && isset($oldItemsById[$itemId])) ? $oldItemsById[$itemId] : null;
        $sameSavedItem = $oldItem && (int)$oldItem['product_id'] === $productId && !$isConversion;
        $requireProductActive = !$sameSavedItem;
        if (!$requireProductActive && !isset($oldProductIds[$productId])) $requireProductActive = true;
        $product = sales_product($branchId, $productId, $requireProductActive);

        $sourcePurchaseId = (int)($raw['source_purchase_id'] ?? 0);
        if ($sourcePurchaseId < 1) {
            json_error('Batch is required in row ' . $rowNo . '.', 422, ['items' => 'Batch is required in row ' . $rowNo . '.']);
        }
        $purchase = sales_purchase_source($branchId, $sourcePurchaseId, $productId);

        /* Batch-wise mixed units.  The selected Purchase Item is the source of
           truth for Primary/Secondary units and their conversion snapshot. */
        $primaryUnitId = (int)($purchase['primary_unit_id'] ?? 0);
        if ($primaryUnitId < 1) {
            json_error('Purchase Batch unit snapshot is incomplete in row ' . $rowNo . '.', 422, [
                'items' => 'The selected Purchase Batch has no saved Primary Unit. Recreate/fix that Purchase Batch first.',
            ]);
        }
        $secondaryUnitId = !empty($purchase['secondary_unit_id'])
            ? (int)$purchase['secondary_unit_id']
            : null;
        $hasSecondary = $secondaryUnitId !== null;
        $conversion = $hasSecondary ? (float)($purchase['conversion_rate'] ?? 0) : 1.0;
        if ($hasSecondary && $conversion <= 0) {
            json_error('Purchase Batch conversion is invalid in row ' . $rowNo . '.', 422, [
                'items' => 'The selected Purchase Batch has no valid saved Primary → Secondary conversion. Product Master is intentionally not used for old batches.',
            ]);
        }

        $primaryQtyRaw = $raw['primary_quantity'] ?? null;
        $secondaryQtyRaw = $raw['secondary_quantity'] ?? null;
        $primaryRateRaw = $raw['unit_price'] ?? 0;

        /* Backward-compatible support for old one-unit Sales payloads. */
        if ($primaryQtyRaw === null && $secondaryQtyRaw === null) {
            $legacyQty = $raw['quantity'] ?? 0;
            $legacyUnit = (int)($raw['selected_unit_id'] ?? $primaryUnitId);
            if (!is_numeric($legacyQty)) $legacyQty = 0;
            if ($hasSecondary && $legacyUnit === (int)$secondaryUnitId) {
                $primaryQtyRaw = 0;
                $secondaryQtyRaw = $legacyQty;
                $legacyRate = $raw['unit_price'] ?? 0;
                $primaryRateRaw = is_numeric($legacyRate) ? ((float)$legacyRate * $conversion) : 0;
            } else {
                $primaryQtyRaw = $legacyQty;
                $secondaryQtyRaw = 0;
            }
        }

        if (!is_numeric($primaryQtyRaw) || (float)$primaryQtyRaw < 0) {
            json_error('Primary Quantity is invalid in row ' . $rowNo . '.', 422, ['items' => 'Primary Quantity is invalid in row ' . $rowNo . '.']);
        }
        if (!is_numeric($secondaryQtyRaw) || (float)$secondaryQtyRaw < 0) {
            json_error('Secondary Quantity is invalid in row ' . $rowNo . '.', 422, ['items' => 'Secondary Quantity is invalid in row ' . $rowNo . '.']);
        }
        if (!is_numeric($primaryRateRaw) || (float)$primaryRateRaw < 0) {
            json_error('Primary Rate is invalid in row ' . $rowNo . '.', 422, ['items' => 'Primary Rate is invalid in row ' . $rowNo . '.']);
        }

        $primaryQty = round((float)$primaryQtyRaw, 3);
        $secondaryQty = $hasSecondary ? round((float)$secondaryQtyRaw, 3) : 0.0;
        if ($primaryQty <= 0 && $secondaryQty <= 0) {
            json_error('Enter Primary Qty or Secondary Qty in row ' . $rowNo . '.', 422, ['items' => 'Enter Primary Qty or Secondary Qty in row ' . $rowNo . '.']);
        }

        $primaryRate = round((float)$primaryRateRaw, 2);
        $secondaryRate = $hasSecondary ? round($primaryRate / $conversion, 6) : 0.0;
        $baseQty = round(($primaryQty * $conversion) + $secondaryQty, 3);
        $qty = round($primaryQty + ($hasSecondary ? $secondaryQty / $conversion : 0), 3); // legacy equivalent Primary Qty
        $rate = $primaryRate;
        $selectedUnitId = $primaryUnitId;

        $available = sales_available_base_stock($branchId, $productId, $sourcePurchaseId, $purchase);
        $restoreKey = $productId . ':' . $sourcePurchaseId;
        if (isset($oldRestore[$restoreKey])) {
            $available = round($available + (float)$oldRestore[$restoreKey], 3);
        }
        if ($baseQty > $available + 0.0005) {
            json_error('Insufficient Batch Stock in row ' . $rowNo . '. Available base stock: ' . number_format($available, 3, '.', '') . '.', 422, [
                'items' => 'Insufficient Batch Stock in row ' . $rowNo . '. Available base stock: ' . number_format($available, 3, '.', '') . '.',
            ]);
        }

        $sellerTypeId = (int)($raw['seller_type_id'] ?? 0);
        $sellerTypeName = sales_nullable($raw['seller_type_name'] ?? null);

        /*
         * Preserve an unchanged historical Seller Type snapshot even if that
         * Seller Type was later deactivated or removed from the Product price
         * configuration. Any NEW/CHANGED Seller Type must still be an active
         * Product Seller price.
         */
        $oldSellerTypeId = $oldItem ? (int)($oldItem['seller_type_id'] ?? 0) : 0;
        $oldSellerTypeName = $oldItem
            ? sales_nullable($oldItem['seller_type_name'] ?? null)
            : null;

        $sameSellerSnapshot = false;
        if ($sameSavedItem) {
            if ($sellerTypeId > 0 && $oldSellerTypeId > 0) {
                $sameSellerSnapshot = $sellerTypeId === $oldSellerTypeId;
            } elseif ($sellerTypeId < 1 && $oldSellerTypeId < 1) {
                $sameSellerSnapshot =
                    strtolower((string)($sellerTypeName ?? '')) ===
                    strtolower((string)($oldSellerTypeName ?? ''));
            }
        }

        if ($sameSellerSnapshot) {
            $sellerTypeId = $oldSellerTypeId;
            $sellerTypeName = $oldSellerTypeName;
        } elseif ($sellerTypeId > 0 || $sellerTypeName !== null) {
            $sellerPricing = sales_seller_pricing(
                $branchId,
                $productId,
                $sellerTypeId,
                $sellerTypeName,
                true
            );
            $sellerTypeId = (int)$sellerPricing['seller_type_id'];
            $sellerTypeName = (string)$sellerPricing['seller_type_name'];
        } else {
            $sellerTypeId = 0;
            $sellerTypeName = null;
        }

        $discountType = (int)($raw['discount_type'] ?? 1);
        if (!in_array($discountType, [1,2], true)) $discountType = 1;
        $discountValue = $raw['discount_value'] ?? 0;
        if (!is_numeric($discountValue) || (float)$discountValue < 0) {
            json_error('Discount is invalid in row ' . $rowNo . '.', 422, ['items' => 'Discount is invalid in row ' . $rowNo . '.']);
        }
        $discountValue = round((float)$discountValue, 2);
        if ($discountType === 1 && $discountValue > 100) {
            json_error('Percentage Discount cannot exceed 100% in row ' . $rowNo . '.', 422, ['items' => 'Percentage Discount cannot exceed 100% in row ' . $rowNo . '.']);
        }

        $taxType = (int)($raw['tax_type'] ?? $product['sale_tax_type']);
        if (!in_array($taxType, [1,2], true)) $taxType = (int)$product['sale_tax_type'];

        $gst = 0.0;
        $cess = 0.0;
        if ($taxMode === 1) {
            $sameTaxModeSnapshot = $sameSavedItem && (int)($oldSale['tax_mode'] ?? 1) === $taxMode;
            if ($sameTaxModeSnapshot) {
                $gst = (float)($oldItem['gst_rate'] ?? $oldItem['tax_rate'] ?? 0);
                $cess = (float)($oldItem['cess_rate'] ?? 0);
            } else {
                $gst = (float)($product['gst_rate'] ?? 0);
                $cess = (float)($product['cess_rate'] ?? 0);
            }
        }

        $cgst = 0.0;
        $sgst = 0.0;
        $igst = 0.0;
        if ($taxMode === 1) {
            if ($supplyType === 'intra_state') {
                /* Use HSN split when it totals to GST, otherwise split evenly. */
                $currentCgst = (float)($product['cgst_rate'] ?? 0);
                $currentSgst = (float)($product['sgst_rate'] ?? 0);
                if ((!isset($sameTaxModeSnapshot) || !$sameTaxModeSnapshot) && abs(($currentCgst + $currentSgst) - $gst) < 0.01) {
                    $cgst = $currentCgst;
                    $sgst = $currentSgst;
                } else {
                    $cgst = round($gst / 2, 3);
                    $sgst = round($gst - $cgst, 3);
                }
            } else {
                $currentIgst = (float)($product['igst_rate'] ?? 0);
                $igst = ((!isset($sameTaxModeSnapshot) || !$sameTaxModeSnapshot) && abs($currentIgst - $gst) < 0.01) ? $currentIgst : $gst;
            }
        }

        $gross = round(($primaryQty * $primaryRate) + ($secondaryQty * $secondaryRate), 2);
        $itemDiscount = $discountType === 1
            ? round($gross * $discountValue / 100, 2)
            : $discountValue;
        if ($itemDiscount > $gross + 0.001) {
            json_error('Fixed Discount cannot exceed line Gross in row ' . $rowNo . '.', 422, [
                'items' => 'Fixed Discount cannot exceed line Gross in row ' . $rowNo . '.',
            ]);
        }

        $netGross = max(0, round($gross - $itemDiscount, 2));
        $combinedRate = $gst + $cess;
        /* Keep the discount basis in the customer's entered price basis.
           Inclusive prices stay inclusive until all discounts are allocated;
           only then is GST backed out. */
        $preTaxable = $netGross;

        $items[] = [
            'source_item_id' => $itemId,
            'product_id' => $productId,
            'product' => $product,
            'source_purchase_id' => $sourcePurchaseId,
            'batch_id' => $purchase['batch_id'] ?? null,
            'purchase' => $purchase,
            'selected_unit_id' => $selectedUnitId,
            'primary_unit_id' => $primaryUnitId,
            'secondary_unit_id' => $secondaryUnitId,
            'conversion_rate' => $conversion,
            'primary_quantity' => $primaryQty,
            'secondary_quantity' => $secondaryQty,
            'quantity' => $qty,
            'base_quantity' => $baseQty,
            'available_base_quantity' => $available,
            'seller_type_id' => $sellerTypeId > 0 ? $sellerTypeId : null,
            'seller_type_name' => $sellerTypeName,
            'unit_price' => $rate,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $itemDiscount,
            'tax_type' => $taxType,
            'hsn_id' => $product['hsn_id'],
            'gst_rate' => $gst,
            'cgst_rate' => $cgst,
            'sgst_rate' => $sgst,
            'igst_rate' => $igst,
            'cess_rate' => $cess,
            'pre_overall_taxable' => $preTaxable,
        ];

        $subtotal += $gross;
        $itemDiscountTotal += $itemDiscount;
        $preOverallTaxableTotal += $preTaxable;
    }

    $subtotal = round($subtotal, 2);
    $itemDiscountTotal = round($itemDiscountTotal, 2);
    $preOverallTaxableTotal = round($preOverallTaxableTotal, 2);

    $overallAmount = $overallType === 1
        ? round($preOverallTaxableTotal * $overallValue / 100, 2)
        : $overallValue;
    if ($overallAmount > $preOverallTaxableTotal + 0.001) {
        json_error('Overall fixed Discount cannot exceed the Taxable Amount.', 422, [
            'overall_discount_value' => 'Overall fixed Discount cannot exceed the Taxable Amount.',
        ]);
    }

    $allocatedOverall = 0.0;
    $taxableTotal = 0.0;
    $cgstTotal = 0.0;
    $sgstTotal = 0.0;
    $igstTotal = 0.0;
    $cessTotal = 0.0;
    $beforeRound = 0.0;
    $lastIndex = count($items) - 1;

    foreach ($items as $index => &$item) {
        if ($overallAmount <= 0 || $preOverallTaxableTotal <= 0) {
            $share = 0.0;
        } elseif ($index === $lastIndex) {
            $share = round($overallAmount - $allocatedOverall, 2);
        } else {
            $share = round($overallAmount * ($item['pre_overall_taxable'] / $preOverallTaxableTotal), 2);
            $allocatedOverall += $share;
        }

        $netLineAmount = max(0, round($item['pre_overall_taxable'] - $share, 2));
        $combinedRate = (float)$item['gst_rate'] + (float)$item['cess_rate'];
        $taxable = ((int)$item['tax_type'] === 1 && $combinedRate > 0)
            ? round($netLineAmount / (1 + $combinedRate / 100), 2)
            : $netLineAmount;
        $cgstAmount = round($taxable * $item['cgst_rate'] / 100, 2);
        $sgstAmount = round($taxable * $item['sgst_rate'] / 100, 2);
        $igstAmount = round($taxable * $item['igst_rate'] / 100, 2);
        $cessAmount = round($taxable * $item['cess_rate'] / 100, 2);
        if ((int)$item['tax_type'] === 1) {
            $inclusiveDiff = round($netLineAmount - ($taxable + $cgstAmount + $sgstAmount + $igstAmount + $cessAmount), 2);
            if (abs($inclusiveDiff) >= 0.009) {
                if ((float)$item['cess_rate'] > 0) $cessAmount = round($cessAmount + $inclusiveDiff, 2);
                elseif ((float)$item['igst_rate'] > 0) $igstAmount = round($igstAmount + $inclusiveDiff, 2);
                elseif ((float)$item['sgst_rate'] > 0) $sgstAmount = round($sgstAmount + $inclusiveDiff, 2);
                elseif ((float)$item['cgst_rate'] > 0) $cgstAmount = round($cgstAmount + $inclusiveDiff, 2);
            }
            $lineTotal = $netLineAmount;
        } else {
            $lineTotal = round($taxable + $cgstAmount + $sgstAmount + $igstAmount + $cessAmount, 2);
        }

        $item['overall_discount_share'] = $share;
        $item['taxable_amount'] = $taxable;
        $item['tax_rate'] = $item['gst_rate'];
        $item['cgst_amount'] = $cgstAmount;
        $item['sgst_amount'] = $sgstAmount;
        $item['igst_amount'] = $igstAmount;
        $item['cess_amount'] = $cessAmount;
        $item['line_total'] = $lineTotal;

        $taxableTotal += $taxable;
        $cgstTotal += $cgstAmount;
        $sgstTotal += $sgstAmount;
        $igstTotal += $igstAmount;
        $cessTotal += $cessAmount;
        $beforeRound += $lineTotal;
    }
    unset($item);

    $taxableTotal = round($taxableTotal, 2);
    $cgstTotal = round($cgstTotal, 2);
    $sgstTotal = round($sgstTotal, 2);
    $igstTotal = round($igstTotal, 2);
    $cessTotal = round($cessTotal, 2);
    $beforeRound = round($beforeRound, 2);
    $roundOff = $roundOffEnabled === 1 ? round(round($beforeRound) - $beforeRound, 2) : 0.0;
    $grandTotal = round($beforeRound + $roundOff, 2);

    $payments = [];
    $creditApplied = 0.0;
    if ($documentType === 4) {
        $rawCreditApplied = $data['credit_applied'] ?? 0;
        if ($rawCreditApplied === '' || $rawCreditApplied === null) {
            $rawCreditApplied = 0;
        }
        if (!is_numeric($rawCreditApplied) || (float)$rawCreditApplied < 0) {
            json_error('Customer Credit amount is invalid.', 422, [
                'credit_applied' => 'Enter a valid Customer Credit amount.',
            ]);
        }
        $creditApplied = round((float)$rawCreditApplied, 2);
    }

    $paymentInput = $data['payments'] ?? [];
    if (is_string($paymentInput)) {
        $decodedPayments = json_decode($paymentInput, true);
        $paymentInput = is_array($decodedPayments) ? $decodedPayments : [];
    }

    if ($documentType === 4 && is_array($paymentInput)) {
        $paymentMethods = sales_payment_methods(db(), $branchId);
        $paymentTotal = 0.0;

        foreach (array_values($paymentInput) as $rawPayment) {
            if (!is_array($rawPayment)) continue;

            $mode = (int)($rawPayment['payment_mode'] ?? 0);
            $amount = $rawPayment['amount'] ?? 0;
            if (!is_numeric($amount)) {
                json_error('Invalid payment Amount.', 422, ['payments' => 'Invalid payment Amount.']);
            }

            $amount = round((float)$amount, 2);
            if ($amount <= 0) continue;

            if (!isset($paymentMethods[$mode])) {
                json_error('Selected Payment Method is inactive or unavailable.', 422, [
                    'payments' => 'Use an active Cash / UPI / Bank / Cheque method.',
                ]);
            }

            $method = $paymentMethods[$mode];
            $methodCode = strtoupper((string)$method['method_code']);
            $modeName = strtolower($methodCode);

            $accountId = (int)($rawPayment['account_id'] ?? 0);
            $account = $accountId > 0
                ? sales_account($branchId, $accountId)
                : sales_payment_account_default($branchId, (int)$method['account_type']);
            $accountId = (int)$account['id'];
            if ((int)$account['account_type'] !== (int)$method['account_type']) {
                json_error(
                    (int)$method['account_type'] === 1
                        ? 'Cash payment requires a Cash Account.'
                        : 'UPI / Bank / Cheque payment requires a Bank Account.',
                    422,
                    [
                        'payments' => (int)$method['account_type'] === 1
                            ? 'Cash payment requires a Cash Account.'
                            : 'UPI / Bank / Cheque payment requires a Bank Account.',
                    ]
                );
            }

            // Reference No is optional for all POS payment modes.
            $reference = sales_nullable($rawPayment['reference_no'] ?? null);

            $chequeNo = null;
            $chequeDate = null;
            if ((int)$method['requires_cheque_details'] === 1) {
                $chequeNo = sales_nullable($rawPayment['cheque_no'] ?? ($rawPayment['reference_no'] ?? null));
                $chequeDate = sales_valid_date(
                    $rawPayment['cheque_date'] ?? ($rawPayment['payment_date'] ?? ''),
                    'payments',
                    true
                );
                // Cheque No is optional; Cheque Date is still validated.

                /* Cheque number has its own detail column; do not duplicate it
                   into payment_reference. */
                $reference = null;
            }

            $paymentTotal += $amount;
            $payments[] = [
                'payment_mode' => $mode,
                'payment_mode_name' => $modeName,
                'payment_method_id' => (int)$method['id'],
                'payment_method_code' => $methodCode,
                'account_id' => $accountId,
                'amount' => $amount,
                /* POS receipt date is the Final Invoice date. Only Cheque has a
                   method-specific date in the detail table. */
                'payment_date' => $salesDate,
                'reference_no' => $reference,
                'cheque_no' => $chequeNo,
                'cheque_date' => $chequeDate,
            ];
        }

        $paymentTotal = round($paymentTotal, 2);
        $editingFinalInvoice = !empty($oldSale)
            && (int)($oldSale['document_type'] ?? 0) === 4
            && $documentType === 4;

        if (!$editingFinalInvoice && $paymentTotal > $grandTotal + 0.001) {
            json_error('Received amount cannot exceed the Invoice Total.', 422, [
                'payments' => 'Received amount cannot exceed the Invoice Total.',
            ]);
        }
        if (!$editingFinalInvoice && ($paymentTotal + $creditApplied) > $grandTotal + 0.001) {
            json_error('Payment plus Customer Credit cannot exceed the Invoice Total.', 422, [
                'credit_applied' => 'Reduce Customer Credit or payment amount.',
            ]);
        }
    }

    return [
        'document_type' => $documentType,
        'tax_mode' => $taxMode,
        'customer_id' => $customerId,
        'customer' => $customer,
        'sales_date' => $salesDate,
        'due_date' => $dueDate,
        'customer_reference' => $customerReference,
        'branch_state_code' => $branchState === '' ? null : $branchState,
        'customer_state_code' => $customerState === '' ? null : $customerState,
        'supply_type' => $supplyType,
        'subtotal' => $subtotal,
        'item_discount_total' => $itemDiscountTotal,
        'overall_discount_type' => $overallType,
        'overall_discount_value' => $overallValue,
        'overall_discount_amount' => $overallAmount,
        'discount_total' => round($itemDiscountTotal + $overallAmount, 2),
        'taxable_total' => $taxableTotal,
        'cgst_total' => $cgstTotal,
        'sgst_total' => $sgstTotal,
        'igst_total' => $igstTotal,
        'cess_total' => $cessTotal,
        'before_round_total' => $beforeRound,
        'round_off' => $roundOff,
        'round_off_enabled' => $roundOffEnabled,
        'grand_total' => $grandTotal,
        'notes' => $notes,
        'items' => $items,
        'payments' => $payments,
        'credit_applied' => $creditApplied,
    ];
}

function sales_has_posted_return(int $branchId, int $saleId): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM food_sale_returns
         WHERE branch_id=:branch_id AND sale_id=:sale_id
           AND posting_status=1 AND status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId, ':sale_id' => $saleId]);
    return (bool)$stmt->fetchColumn();
}

function sales_clear_old_stock_effect(PDO $pdo, int $branchId, array $oldSale): void
{
    if ((int)($oldSale['document_type'] ?? 0) !== 4) return;

    if (sales_has_posted_return($branchId, (int)$oldSale['id'])) {
        json_error('Final Invoice cannot be edited because a posted Sale Return exists for it.', 409);
    }

    /*
     * Keep only the current stock effect for an editable Final Invoice.
     * Older builds may have both Sale Out and edit-reversal rows. Removing
     * both restores the batch stock to its pre-invoice state inside the same
     * transaction; the edited Final Invoice is then posted once again.
     */
    $stmt = $pdo->prepare(
        "DELETE FROM food_stock_movements
         WHERE branch_id=:branch_id
           AND reference_id=:sale_id
           AND reference_type IN ('sale_invoice','sale_edit_reversal')"
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':sale_id' => (int)$oldSale['id'],
    ]);
}

function sales_credit_balance_excluding_payment(
    PDO $pdo,
    int $branchId,
    int $customerId,
    int $paymentId
): float {
    $sql = "SELECT COALESCE(SUM(amount_in-amount_out),0)
            FROM food_customer_credit_ledger
            WHERE branch_id=:branch_id
              AND customer_id=:customer_id
              AND status=1";
    $params = [
        ':branch_id' => $branchId,
        ':customer_id' => $customerId,
    ];
    if ($paymentId > 0) {
        $sql .= " AND NOT (
                    reference_type='customer_payment'
                    AND reference_id=:payment_id
                  )";
        $params[':payment_id'] = $paymentId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function sales_cap_external_settlements_to_credit(
    PDO $pdo,
    int $branchId,
    int $saleId,
    string $salesNo,
    string $transactionDate,
    int $userId,
    float $invoiceTotal
): float {
    /*
     * Customer Payments remain owned by the Customer Payment module. When an
     * edited Final Invoice becomes smaller, only the allocation against this
     * invoice is reduced. Released actual payment / previously-used Customer
     * Credit becomes Customer Credit again. Released discount is simply removed.
     *
     * Allocation priority is the same as Customer Payment:
     *   1) Actual Payment  2) Customer Credit  3) Settlement Discount
     */
    $stmt = $pdo->prepare(
        'SELECT a.id AS allocation_id,
                a.customer_payment_id,
                a.allocated_amount,
                a.credit_amount,
                a.discount_amount,
                p.customer_id,
                p.payment_no
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
            ON p.id=a.customer_payment_id
           AND p.branch_id=a.branch_id
           AND p.status=1
           AND p.posting_status=1
         WHERE a.branch_id=:branch_id
           AND a.sale_id=:sale_id
           AND a.status=1
           AND (p.source_sale_id IS NULL OR p.source_sale_id<>:sale_id_compare)
         ORDER BY a.id
         FOR UPDATE'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':sale_id' => $saleId,
        ':sale_id_compare' => $saleId,
    ]);

    $updateAllocation = $pdo->prepare(
        'UPDATE food_customer_payment_allocations
         SET allocated_amount=:allocated_amount,
             credit_amount=:credit_amount,
             discount_amount=:discount_amount,
             status=:status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );

    $insertCredit = $pdo->prepare(
        "INSERT INTO food_customer_credit_ledger
         (branch_id,customer_id,transaction_date,transaction_type,
          reference_type,reference_id,amount_in,amount_out,remarks,
          status,created_by,created_at,updated_at)
         VALUES
         (:branch_id,:customer_id,:transaction_date,1,
          'customer_payment',:reference_id,:amount_in,0,:remarks,
          1,:created_by,NOW(),NOW())"
    );

    $remaining = max(0.0, round($invoiceTotal, 2));
    $keptTotal = 0.0;

    foreach ($stmt->fetchAll() as $row) {
        $oldPayment = max(0.0, round((float)$row['allocated_amount'], 2));
        $oldCredit = max(0.0, round((float)$row['credit_amount'], 2));
        $oldDiscount = max(0.0, round((float)$row['discount_amount'], 2));

        $newPayment = round(min($oldPayment, $remaining), 2);
        $remaining = round(max(0.0, $remaining - $newPayment), 2);

        $newCredit = round(min($oldCredit, $remaining), 2);
        $remaining = round(max(0.0, $remaining - $newCredit), 2);

        $newDiscount = round(min($oldDiscount, $remaining), 2);
        $remaining = round(max(0.0, $remaining - $newDiscount), 2);

        $releasedPayment = round(max(0.0, $oldPayment - $newPayment), 2);
        $releasedCredit = round(max(0.0, $oldCredit - $newCredit), 2);
        $creditBack = round($releasedPayment + $releasedCredit, 2);

        $newSettled = round($newPayment + $newCredit + $newDiscount, 2);
        $updateAllocation->execute([
            ':allocated_amount' => $newPayment,
            ':credit_amount' => $newCredit,
            ':discount_amount' => $newDiscount,
            ':status' => $newSettled > 0.001 ? 1 : 0,
            ':id' => (int)$row['allocation_id'],
            ':branch_id' => $branchId,
        ]);

        if ($creditBack > 0.001) {
            $paymentNo = trim((string)($row['payment_no'] ?? ''));
            $label = $paymentNo !== '' ? $paymentNo : ('Payment #' . (int)$row['customer_payment_id']);
            $insertCredit->execute([
                ':branch_id' => $branchId,
                ':customer_id' => (int)$row['customer_id'],
                ':transaction_date' => $transactionDate,
                ':reference_id' => (int)$row['customer_payment_id'],
                ':amount_in' => $creditBack,
                ':remarks' => 'Invoice reduction credit from ' . $salesNo . ' / ' . $label,
                ':created_by' => $userId,
            ]);
        }

        $keptTotal += $newSettled;
    }

    return round($keptTotal, 2);
}

function sales_sync_pos_receipt(
    PDO $pdo,
    int $branchId,
    int $saleId,
    string $salesNo,
    int $customerId,
    string $paymentDate,
    int $userId,
    array $payments,
    float $requestedCredit,
    float $availableCredit,
    float $maximumAllocation
): float {
    $paymentTotal = 0.0;
    foreach ($payments as $payment) {
        $paymentTotal += round((float)($payment['amount'] ?? 0), 2);
    }
    $paymentTotal = round($paymentTotal, 2);
    $requestedCredit = max(0.0, round($requestedCredit, 2));
    $availableCredit = max(0.0, round($availableCredit, 2));
    $maximumAllocation = max(0.0, round($maximumAllocation, 2));

    if ($requestedCredit > $availableCredit + 0.001) {
        json_error(
            'Customer Credit exceeds the available balance.',
            422,
            ['credit_applied' => 'Maximum available Customer Credit is ' . number_format($availableCredit, 2, '.', '') . '.']
        );
    }

    $allocatedAmount = round(min($paymentTotal, $maximumAllocation), 2);
    $remainingAfterPayment = max(0.0, round($maximumAllocation - $allocatedAmount, 2));

    if ($paymentTotal > $maximumAllocation + 0.001 && $requestedCredit > 0.001) {
        json_error(
            'Do not apply Customer Credit when the entered payment already exceeds the remaining Invoice balance.',
            422,
            ['credit_applied' => 'Set Customer Credit to 0.00 or reduce the payment amount.']
        );
    }
    if ($requestedCredit > $remainingAfterPayment + 0.001) {
        json_error(
            'Customer Credit exceeds the Invoice balance remaining after payment.',
            422,
            ['credit_applied' => 'Maximum usable Customer Credit is ' . number_format($remainingAfterPayment, 2, '.', '') . '.']
        );
    }

    $creditAllocated = round($requestedCredit, 2);
    $excessCredit = round(max(0.0, $paymentTotal - $allocatedAmount), 2);

    /* Lock every historical POS header for this sale. Older builds may have
       one header per mode; after this save they are consolidated into one. */
    $existingStmt = $pdo->prepare(
        'SELECT id,payment_no,status,customer_id
         FROM food_customer_payments
         WHERE branch_id=:branch_id
           AND source_sale_id=:sale_id
         ORDER BY status DESC,id
         FOR UPDATE'
    );
    $existingStmt->execute([
        ':branch_id' => $branchId,
        ':sale_id' => $saleId,
    ]);
    $existingRows = $existingStmt->fetchAll();

    $headerId = 0;
    $existingPaymentNo = '';
    if ($existingRows) {
        $headerId = (int)$existingRows[0]['id'];
        $existingPaymentNo = trim((string)($existingRows[0]['payment_no'] ?? ''));

        /* Editing a POS receipt replaces its old credit effect. If that credit
           has already been consumed by another transaction, silently removing
           it would make Customer Credit negative, so block only that case. */
        $oldCustomerId = (int)($existingRows[0]['customer_id'] ?? $customerId);
        $baseCredit = sales_credit_balance_excluding_payment(
            $pdo,
            $branchId,
            $oldCustomerId,
            $headerId
        );
        if ($baseCredit < -0.001) {
            json_error(
                'This Final Invoice generated Customer Credit that has already been used. Reverse the dependent credit usage first.',
                409
            );
        }

        $pdo->prepare(
            "UPDATE food_customer_credit_ledger
             SET status=0,updated_at=NOW()
             WHERE branch_id=:branch_id
               AND reference_type='customer_payment'
               AND reference_id=:payment_id
               AND status=1"
        )->execute([
            ':branch_id' => $branchId,
            ':payment_id' => $headerId,
        ]);
    }

    /* Reverse the old POS-owned settlement first. It is rebuilt from the
       current payment rows below. External Customer Payments are untouched. */
    $pdo->prepare(
        'UPDATE food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
            ON p.id=a.customer_payment_id
           AND p.branch_id=a.branch_id
         SET a.allocated_amount=0,
             a.credit_amount=0,
             a.discount_amount=0,
             a.status=0,
             a.updated_at=NOW()
         WHERE a.branch_id=:branch_id
           AND a.sale_id=:sale_id
           AND p.source_sale_id=:sale_id_compare'
    )->execute([
        ':branch_id' => $branchId,
        ':sale_id' => $saleId,
        ':sale_id_compare' => $saleId,
    ]);

    $pdo->prepare(
        'UPDATE food_customer_payment_details d
         INNER JOIN food_customer_payments p
            ON p.id=d.customer_payment_id
           AND p.branch_id=d.branch_id
         SET d.status=0,d.updated_at=NOW()
         WHERE d.branch_id=:branch_id
           AND p.source_sale_id=:sale_id'
    )->execute([
        ':branch_id' => $branchId,
        ':sale_id' => $saleId,
    ]);

    if ($paymentTotal <= 0.001 && $creditAllocated <= 0.001) {
        $pdo->prepare(
            'UPDATE food_customer_payments
             SET amount=0,credit_applied=0,status=0,updated_at=NOW()
             WHERE branch_id=:branch_id
               AND source_sale_id=:sale_id'
        )->execute([
            ':branch_id' => $branchId,
            ':sale_id' => $saleId,
        ]);
        return 0.0;
    }

    $paymentNo = $existingPaymentNo !== ''
        ? $existingPaymentNo
        : sales_pos_payment_no($salesNo);

    if ($headerId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE food_customer_payments SET
                customer_id=:customer_id,
                payment_no=:payment_no,
                payment_date=:payment_date,
                payment_type=3,
                amount=:amount,
                discount_type=NULL,
                discount_value=0,
                discount_amount=0,
                credit_applied=:credit_applied,
                notes=:notes,
                posting_status=1,
                status=1,
                updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id
               AND source_sale_id=:source_sale_id'
        );
        $stmt->execute([
            ':customer_id' => $customerId,
            ':payment_no' => $paymentNo,
            ':payment_date' => $paymentDate,
            ':amount' => $paymentTotal,
            ':credit_applied' => $creditAllocated,
            ':notes' => 'POS receipt for ' . $salesNo,
            ':id' => $headerId,
            ':branch_id' => $branchId,
            ':source_sale_id' => $saleId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO food_customer_payments
             (branch_id,customer_id,payment_no,source_sale_id,payment_date,payment_type,
              amount,discount_type,discount_value,discount_amount,credit_applied,
              notes,posting_status,status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:customer_id,:payment_no,:source_sale_id,:payment_date,3,
              :amount,NULL,0,0,:credit_applied,:notes,1,1,:created_by,NOW(),NOW())'
        );
        $stmt->execute([
            ':branch_id' => $branchId,
            ':customer_id' => $customerId,
            ':payment_no' => $paymentNo,
            ':source_sale_id' => $saleId,
            ':payment_date' => $paymentDate,
            ':amount' => $paymentTotal,
            ':credit_applied' => $creditAllocated,
            ':notes' => 'POS receipt for ' . $salesNo,
            ':created_by' => $userId,
        ]);
        $headerId = (int)$pdo->lastInsertId();
    }

    /* Deactivate old extra per-mode headers from previous builds. */
    if (count($existingRows) > 1) {
        $extraIds = [];
        foreach (array_slice($existingRows, 1) as $row) {
            $extraIds[] = (int)$row['id'];
        }
        if ($extraIds) {
            $placeholders = implode(',', array_fill(0, count($extraIds), '?'));
            $stmt = $pdo->prepare(
                'UPDATE food_customer_payments
                 SET status=0,updated_at=NOW()
                 WHERE branch_id=?
                   AND source_sale_id=?
                   AND id IN (' . $placeholders . ')'
            );
            $stmt->execute(array_merge([$branchId, $saleId], $extraIds));
        }
    }

    $detailStmt = $pdo->prepare(
        'INSERT INTO food_customer_payment_details
         (customer_payment_id,branch_id,payment_method_id,account_id,amount,
          payment_reference,cheque_no,cheque_date,status,created_by,created_at,updated_at)
         VALUES
         (:customer_payment_id,:branch_id,:payment_method_id,:account_id,:amount,
          :payment_reference,:cheque_no,:cheque_date,1,:created_by,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
            account_id=VALUES(account_id),
            amount=VALUES(amount),
            payment_reference=VALUES(payment_reference),
            cheque_no=VALUES(cheque_no),
            cheque_date=VALUES(cheque_date),
            status=1,
            updated_at=NOW()'
    );

    foreach ($payments as $payment) {
        $amount = round((float)($payment['amount'] ?? 0), 2);
        if ($amount <= 0) continue;

        $detailStmt->execute([
            ':customer_payment_id' => $headerId,
            ':branch_id' => $branchId,
            ':payment_method_id' => (int)$payment['payment_method_id'],
            ':account_id' => (int)$payment['account_id'],
            ':amount' => $amount,
            ':payment_reference' => $payment['reference_no'],
            ':cheque_no' => $payment['cheque_no'],
            ':cheque_date' => $payment['cheque_date'],
            ':created_by' => $userId,
        ]);
    }

    /* Actual receipt is allocated first, then selected Customer Credit.
       Any actual receipt above the remaining invoice becomes new Customer Credit. */
    if (($allocatedAmount + $creditAllocated) > 0.001) {
        $allocationStmt = $pdo->prepare(
            'INSERT INTO food_customer_payment_allocations
             (customer_payment_id,sale_id,branch_id,allocation_type,
              allocated_amount,credit_amount,discount_amount,status,
              created_by,created_at,updated_at)
             VALUES
             (:customer_payment_id,:sale_id,:branch_id,1,
              :allocated_amount,:credit_amount,0,1,:created_by,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                allocation_type=1,
                allocated_amount=VALUES(allocated_amount),
                credit_amount=VALUES(credit_amount),
                discount_amount=0,
                status=1,
                updated_at=NOW()'
        );
        $allocationStmt->execute([
            ':customer_payment_id' => $headerId,
            ':sale_id' => $saleId,
            ':branch_id' => $branchId,
            ':allocated_amount' => $allocatedAmount,
            ':credit_amount' => $creditAllocated,
            ':created_by' => $userId,
        ]);
    }

    if ($creditAllocated > 0.001) {
        $creditUseLedger = $pdo->prepare(
            "INSERT INTO food_customer_credit_ledger
             (branch_id,customer_id,transaction_date,transaction_type,
              reference_type,reference_id,amount_in,amount_out,remarks,
              status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:customer_id,:transaction_date,2,
              'customer_payment',:reference_id,0,:amount_out,:remarks,
              1,:created_by,NOW(),NOW())"
        );
        $creditUseLedger->execute([
            ':branch_id' => $branchId,
            ':customer_id' => $customerId,
            ':transaction_date' => $paymentDate,
            ':reference_id' => $headerId,
            ':amount_out' => $creditAllocated,
            ':remarks' => 'Customer Credit applied to ' . $salesNo,
            ':created_by' => $userId,
        ]);
    }

    if ($excessCredit > 0.001) {
        $ledger = $pdo->prepare(
            "INSERT INTO food_customer_credit_ledger
             (branch_id,customer_id,transaction_date,transaction_type,
              reference_type,reference_id,amount_in,amount_out,remarks,
              status,created_by,created_at,updated_at)
             VALUES
             (:branch_id,:customer_id,:transaction_date,1,
              'customer_payment',:reference_id,:amount_in,0,:remarks,
              1,:created_by,NOW(),NOW())"
        );
        $ledger->execute([
            ':branch_id' => $branchId,
            ':customer_id' => $customerId,
            ':transaction_date' => $paymentDate,
            ':reference_id' => $headerId,
            ':amount_in' => $excessCredit,
            ':remarks' => 'Excess POS payment credit from ' . $paymentNo,
            ':created_by' => $userId,
        ]);
    }

    return round($allocatedAmount + $creditAllocated, 2);
}

function sales_payment_state(PDO $pdo, int $branchId, int $saleId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(
                COALESCE(a.allocated_amount,0)
              + COALESCE(a.credit_amount,0)
            ),0) AS paid_amount,
            COALESCE(SUM(
                COALESCE(a.allocated_amount,0)
              + COALESCE(a.credit_amount,0)
              + COALESCE(a.discount_amount,0)
            ),0) AS settled_amount
         FROM food_customer_payment_allocations a
         INNER JOIN food_customer_payments p
            ON p.id=a.customer_payment_id
           AND p.branch_id=a.branch_id
           AND p.status=1
           AND p.posting_status=1
         WHERE a.sale_id=:sale_id
           AND a.branch_id=:branch_id
           AND a.status=1'
    );
    $stmt->execute([
        ':sale_id' => $saleId,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch() ?: [];

    return [
        'paid_amount' => round((float)($row['paid_amount'] ?? 0), 2),
        'settled_amount' => round((float)($row['settled_amount'] ?? 0), 2),
    ];
}


function sales_insert_items_and_stock(
    PDO $pdo,
    int $branchId,
    int $saleId,
    string $salesNo,
    string $salesDate,
    int $userId,
    int $documentType,
    array $items
): void {
    $itemStmt = $pdo->prepare(
        'INSERT INTO food_sale_items
         (sale_id,branch_id,product_id,source_purchase_id,batch_id,
          selected_unit_id,primary_unit_id,secondary_unit_id,conversion_rate,
          primary_quantity,secondary_quantity,quantity,base_quantity,seller_type_id,seller_type_name,unit_price,
          discount_type,discount_value,discount_amount,overall_discount_share,
          tax_type,hsn_id,taxable_amount,tax_rate,gst_rate,cgst_rate,sgst_rate,igst_rate,cess_rate,
          cgst_amount,sgst_amount,igst_amount,cess_amount,line_total,
          status,created_by,created_at,updated_at)
         VALUES
         (:sale_id,:branch_id,:product_id,:source_purchase_id,:batch_id,
          :selected_unit_id,:primary_unit_id,:secondary_unit_id,:conversion_rate,
          :primary_quantity,:secondary_quantity,:quantity,:base_quantity,:seller_type_id,:seller_type_name,:unit_price,
          :discount_type,:discount_value,:discount_amount,:overall_discount_share,
          :tax_type,:hsn_id,:taxable_amount,:tax_rate,:gst_rate,:cgst_rate,:sgst_rate,:igst_rate,:cess_rate,
          :cgst_amount,:sgst_amount,:igst_amount,:cess_amount,:line_total,
          1,:created_by,NOW(),NOW())'
    );

    $stockStmt = null;
    if ($documentType === 4) {
        $stockStmt = $pdo->prepare(
            'INSERT INTO food_stock_movements
             (branch_id,product_id,source_purchase_id,batch_id,movement_date,movement_type,
              reference_type,reference_id,reference_no,quantity_in,quantity_out,
              unit_id,conversion_rate,remarks,status,created_by,created_at)
             VALUES
             (:branch_id,:product_id,:source_purchase_id,:batch_id,:movement_date,2,
              \'sale_invoice\',:reference_id,:reference_no,0,:quantity_out,
              :unit_id,:conversion_rate,:remarks,1,:created_by,NOW())'
        );
    }

    foreach ($items as $item) {
        $itemStmt->execute([
            ':sale_id' => $saleId,
            ':branch_id' => $branchId,
            ':product_id' => (int)$item['product_id'],
            ':source_purchase_id' => (int)$item['source_purchase_id'],
            ':batch_id' => $item['batch_id'] === null ? null : (int)$item['batch_id'],
            ':selected_unit_id' => (int)$item['selected_unit_id'],
            ':primary_unit_id' => (int)$item['primary_unit_id'],
            ':secondary_unit_id' => $item['secondary_unit_id'] === null ? null : (int)$item['secondary_unit_id'],
            ':conversion_rate' => (float)$item['conversion_rate'],
            ':primary_quantity' => (float)$item['primary_quantity'],
            ':secondary_quantity' => (float)$item['secondary_quantity'],
            ':quantity' => (float)$item['quantity'],
            ':base_quantity' => (float)$item['base_quantity'],
            ':seller_type_id' => $item['seller_type_id'] === null ? null : (int)$item['seller_type_id'],
            ':seller_type_name' => $item['seller_type_name'],
            ':unit_price' => (float)$item['unit_price'],
            ':discount_type' => (int)$item['discount_type'],
            ':discount_value' => (float)$item['discount_value'],
            ':discount_amount' => (float)$item['discount_amount'],
            ':overall_discount_share' => (float)$item['overall_discount_share'],
            ':tax_type' => (int)$item['tax_type'],
            ':hsn_id' => $item['hsn_id'] === null ? null : (int)$item['hsn_id'],
            ':taxable_amount' => (float)$item['taxable_amount'],
            ':tax_rate' => (float)$item['gst_rate'],
            ':gst_rate' => (float)$item['gst_rate'],
            ':cgst_rate' => (float)$item['cgst_rate'],
            ':sgst_rate' => (float)$item['sgst_rate'],
            ':igst_rate' => (float)$item['igst_rate'],
            ':cess_rate' => (float)$item['cess_rate'],
            ':cgst_amount' => (float)$item['cgst_amount'],
            ':sgst_amount' => (float)$item['sgst_amount'],
            ':igst_amount' => (float)$item['igst_amount'],
            ':cess_amount' => (float)$item['cess_amount'],
            ':line_total' => (float)$item['line_total'],
            ':created_by' => $userId,
        ]);

        if ($stockStmt) {
            $stockStmt->execute([
                ':branch_id' => $branchId,
                ':product_id' => (int)$item['product_id'],
                ':source_purchase_id' => (int)$item['source_purchase_id'],
                ':batch_id' => $item['batch_id'] === null ? null : (int)$item['batch_id'],
                ':movement_date' => $salesDate,
                ':reference_id' => $saleId,
                ':reference_no' => $salesNo,
                ':quantity_out' => (float)$item['base_quantity'],
                ':unit_id' => $item['secondary_unit_id'] === null ? (int)$item['primary_unit_id'] : (int)$item['secondary_unit_id'],
                ':conversion_rate' => (float)$item['conversion_rate'],
                ':remarks' => 'Final Invoice ' . $salesNo,
                ':created_by' => $userId,
            ]);
        }
    }
}

function sales_save(array $access, array $context, array $data, bool $isUpdate): array
{
    $branchId = (int)$context['branch_id'];
    $userId = (int)$access['user']['id'];

    /* Browser never sends a trusted numeric document type. */
    $documentType = sales_target_type_from_ref($data['target_ref'] ?? '', true);
    $data['document_type'] = $documentType;

    $targetAction = sales_document_action_id($documentType);
    if ($targetAction < 1) {
        json_error('Invalid Sales document target.', 422);
    }

    sales_require_action($access, $targetAction, 'You do not have permission for the selected Sales document target.');

    $oldSale = [];
    $saleId = 0;

    if ($isUpdate) {
        sales_require_action($access, ACTION_UPDATE, 'You do not have permission to update Sales documents.');
        $saleId = sales_ref_to_id($data['ref'] ?? '');
        $oldSale = sales_record($context, $saleId);

        /* Final Invoice is already the final business document. It may be edited,
           but it cannot be changed back to a pre-sale document. */
        if ((int)$oldSale['document_type'] === 4 && $documentType !== 4) {
            json_error('Final Invoice cannot be changed to another document type.', 409);
        }

        if ((int)$oldSale['document_type'] === 4
            && (int)($oldSale['customer_id'] ?? 0) !== (int)($data['customer_id'] ?? 0)
            && (float)($oldSale['paid_amount'] ?? 0) > 0.001) {
            json_error(
                'Customer cannot be changed because this Final Invoice already has Customer payments allocated. Reallocate those receipts first.',
                409
            );
        }
    } else {
        sales_require_action($access, ACTION_CREATE, 'You do not have permission to create Sales documents.');
    }

    /* Same Sale row is recalculated from the current browser rows. For Final
       Invoice edit, sales_calculate() adds the original invoice quantity back
       only for stock validation; database stock is untouched until submit. */
    $calc = sales_calculate($context, $data, $isUpdate ? $oldSale : [], false);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($isUpdate) {
            $lock = $pdo->prepare(
                'SELECT id FROM food_sales
                 WHERE id=:id AND branch_id=:branch_id
                 FOR UPDATE'
            );
            $lock->execute([':id' => $saleId, ':branch_id' => $branchId]);
            if (!$lock->fetchColumn()) {
                json_error('Sales document was not found.', 404);
            }
        }

        $oldDocumentType = $isUpdate ? (int)$oldSale['document_type'] : 0;
        $oldTaxMode = $isUpdate ? (int)$oldSale['tax_mode'] : 1;
        $numberMustChange = !$isUpdate
            || $oldDocumentType !== $documentType
            || sales_prefix($oldDocumentType, $oldTaxMode) !== sales_prefix($documentType, (int)$calc['tax_mode']);

        $salesNo = $numberMustChange
            ? sales_generate_no_locked($pdo, $branchId, $documentType, (int)$calc['tax_mode'])
            : (string)$oldSale['sales_no'];

        if ($isUpdate && $oldDocumentType === 4) {
            /* Remove the old active invoice stock effect only now, inside the
               transaction. Direct browser edits never touch stock. */
            sales_clear_old_stock_effect($pdo, $branchId, $oldSale);
        }

        $postingStatus = $documentType === 4 ? 1 : 0;

        if ($isUpdate) {
            $stmt = $pdo->prepare(
                'UPDATE food_sales SET
                    sales_no=:sales_no,
                    document_type=:document_type,
                    customer_id=:customer_id,
                    customer_reference=:customer_reference,
                    invoice_date=:invoice_date,
                    due_date=:due_date,
                    customer_name_snapshot=:customer_name_snapshot,
                    customer_gstin_snapshot=:customer_gstin_snapshot,
                    branch_state_code=:branch_state_code,
                    customer_state_code=:customer_state_code,
                    tax_mode=:tax_mode,
                    supply_type=:supply_type,
                    subtotal=:subtotal,
                    item_discount_total=:item_discount_total,
                    discount_total=:discount_total,
                    overall_discount_type=:overall_discount_type,
                    overall_discount_value=:overall_discount_value,
                    overall_discount_amount=:overall_discount_amount,
                    taxable_total=:taxable_total,
                    cgst_total=:cgst_total,
                    sgst_total=:sgst_total,
                    igst_total=:igst_total,
                    cess_total=:cess_total,
                    before_round_total=:before_round_total,
                    round_off=:round_off,
                    round_off_enabled=:round_off_enabled,
                    grand_total=:grand_total,
                    notes=:notes,
                    posting_status=:posting_status,
                    status=1,
                    updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO food_sales
                 (branch_id,sales_no,document_type,customer_id,customer_reference,
                  invoice_date,due_date,customer_name_snapshot,customer_gstin_snapshot,
                  branch_state_code,customer_state_code,tax_mode,supply_type,
                  subtotal,item_discount_total,discount_total,
                  overall_discount_type,overall_discount_value,overall_discount_amount,
                  taxable_total,cgst_total,sgst_total,igst_total,cess_total,
                  before_round_total,round_off,round_off_enabled,grand_total,
                  paid_amount,balance_amount,payment_status,notes,posting_status,
                  status,created_by,created_at,updated_at)
                 VALUES
                 (:branch_id,:sales_no,:document_type,:customer_id,:customer_reference,
                  :invoice_date,:due_date,:customer_name_snapshot,:customer_gstin_snapshot,
                  :branch_state_code,:customer_state_code,:tax_mode,:supply_type,
                  :subtotal,:item_discount_total,:discount_total,
                  :overall_discount_type,:overall_discount_value,:overall_discount_amount,
                  :taxable_total,:cgst_total,:sgst_total,:igst_total,:cess_total,
                  :before_round_total,:round_off,:round_off_enabled,:grand_total,
                  0,:balance_amount,1,:notes,:posting_status,
                  1,:created_by,NOW(),NOW())'
            );
        }

        $params = [
            ':branch_id' => $branchId,
            ':sales_no' => $salesNo,
            ':document_type' => $documentType,
            ':customer_id' => (int)$calc['customer_id'],
            ':customer_reference' => $calc['customer_reference'],
            ':invoice_date' => $calc['sales_date'],
            ':due_date' => $calc['due_date'],
            ':customer_name_snapshot' => (string)$calc['customer']['customer_name'],
            ':customer_gstin_snapshot' => sales_nullable($calc['customer']['gstin'] ?? null),
            ':branch_state_code' => $calc['branch_state_code'],
            ':customer_state_code' => $calc['customer_state_code'],
            ':tax_mode' => (int)$calc['tax_mode'],
            ':supply_type' => $calc['supply_type'],
            ':subtotal' => (float)$calc['subtotal'],
            ':item_discount_total' => (float)$calc['item_discount_total'],
            ':discount_total' => (float)$calc['discount_total'],
            ':overall_discount_type' => (int)$calc['overall_discount_type'],
            ':overall_discount_value' => (float)$calc['overall_discount_value'],
            ':overall_discount_amount' => (float)$calc['overall_discount_amount'],
            ':taxable_total' => (float)$calc['taxable_total'],
            ':cgst_total' => (float)$calc['cgst_total'],
            ':sgst_total' => (float)$calc['sgst_total'],
            ':igst_total' => (float)$calc['igst_total'],
            ':cess_total' => (float)$calc['cess_total'],
            ':before_round_total' => (float)$calc['before_round_total'],
            ':round_off' => (float)$calc['round_off'],
            ':round_off_enabled' => (int)$calc['round_off_enabled'],
            ':grand_total' => (float)$calc['grand_total'],
            ':notes' => $calc['notes'],
            ':posting_status' => $postingStatus,
        ];

        if ($isUpdate) {
            $params[':id'] = $saleId;
        } else {
            $params[':balance_amount'] = (float)$calc['grand_total'];
            $params[':created_by'] = $userId;
        }

        $stmt->execute($params);

        if (!$isUpdate) {
            $saleId = (int)$pdo->lastInsertId();
        } else {
            /* Preserve history safely without deleting rows that another module
               may reference. Only current status=1 rows represent the document. */
            $pdo->prepare(
                'UPDATE food_sale_items
                 SET status=0,updated_at=NOW()
                 WHERE sale_id=:sale_id AND branch_id=:branch_id AND status=1'
            )->execute([':sale_id' => $saleId, ':branch_id' => $branchId]);
        }

        /* Recheck inside the same transaction. If this was a Final Invoice edit,
           its previous stock effect has already been removed. */
        if ($documentType === 4) {
            foreach ($calc['items'] as $item) {
                $availableNow = sales_available_base_stock(
                    $branchId,
                    (int)$item['product_id'],
                    (int)$item['source_purchase_id']
                );
                if ((float)$item['base_quantity'] > $availableNow + 0.0005) {
                    json_error(
                        'Stock changed while saving. Available Batch stock is now ' . number_format($availableNow, 3, '.', '') . '.',
                        409
                    );
                }
            }
        }

        sales_insert_items_and_stock(
            $pdo,
            $branchId,
            $saleId,
            $salesNo,
            (string)$calc['sales_date'],
            $userId,
            $documentType,
            $calc['items']
        );

        $paidAmount = 0.0;
        $balanceAmount = (float)$calc['grand_total'];
        $paymentStatus = 1;

        if ($documentType === 4) {
            /* Capture Customer Credit available before this invoice edit releases
               any new credit. When editing, exclude this invoice's existing POS
               payment effect so previously-used credit is available for rebuild. */
            $posHeaderLookup = $pdo->prepare(
                'SELECT id
                 FROM food_customer_payments
                 WHERE branch_id=:branch_id
                   AND source_sale_id=:sale_id
                 ORDER BY status DESC,id
                 LIMIT 1'
            );
            $posHeaderLookup->execute([
                ':branch_id' => $branchId,
                ':sale_id' => $saleId,
            ]);
            $existingPosPaymentId = (int)($posHeaderLookup->fetchColumn() ?: 0);
            $availableCreditForPos = max(
                0.0,
                sales_credit_balance_excluding_payment(
                    $pdo,
                    $branchId,
                    (int)$calc['customer_id'],
                    $existingPosPaymentId
                )
            );

            /* If an edited invoice becomes smaller than existing Customer Payment
               settlements, keep only what fits this invoice and move the released
               payment / credit back to Customer Credit automatically. */
            $externalSettled = $isUpdate
                ? sales_cap_external_settlements_to_credit(
                    $pdo,
                    $branchId,
                    $saleId,
                    $salesNo,
                    (string)$calc['sales_date'],
                    $userId,
                    (float)$calc['grand_total']
                )
                : 0.0;

            $remainingForPos = max(
                0.0,
                round((float)$calc['grand_total'] - $externalSettled, 2)
            );

            sales_sync_pos_receipt(
                $pdo,
                $branchId,
                $saleId,
                $salesNo,
                (int)$calc['customer_id'],
                (string)$calc['sales_date'],
                $userId,
                $calc['payments'],
                (float)$calc['credit_applied'],
                $availableCreditForPos,
                $remainingForPos
            );

            $paymentState = sales_payment_state($pdo, $branchId, $saleId);
            $paidAmount = min(
                (float)$calc['grand_total'],
                (float)$paymentState['paid_amount']
            );
            $settledAmount = min(
                (float)$calc['grand_total'],
                (float)$paymentState['settled_amount']
            );
            $balanceAmount = max(
                0.0,
                round((float)$calc['grand_total'] - $settledAmount, 2)
            );
            $paymentStatus = $balanceAmount <= 0.001
                ? 3
                : ($paidAmount > 0 ? 2 : 1);
        }

        $pdo->prepare(
            'UPDATE food_sales
             SET paid_amount=:paid_amount,
                 balance_amount=:balance_amount,
                 payment_status=:payment_status,
                 updated_at=NOW()
             WHERE id=:id AND branch_id=:branch_id'
        )->execute([
            ':paid_amount' => $paidAmount,
            ':balance_amount' => $balanceAmount,
            ':payment_status' => $paymentStatus,
            ':id' => $saleId,
            ':branch_id' => $branchId,
        ]);

        audit_log(
            $userId,
            $isUpdate ? ACTION_UPDATE : ACTION_CREATE,
            [
                'company_id' => (int)$context['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $saleId,
                'old_data' => $isUpdate ? $oldSale : null,
            ]
        );

        $pdo->commit();
        return sales_record($context, $saleId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sales_batch_options(array $access, array $context): void
{
    $branchId = (int)$context['branch_id'];
    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId < 1) {
        json_error('Product is required.', 422);
    }
    $masterProduct = sales_product($branchId, $productId, false);

    $restore = [];
    $existingSources = [];
    $ref = trim((string)($_GET['ref'] ?? ''));
    if ($ref !== '') {
        $sale = sales_record($context, sales_ref_to_id($ref));
        foreach ((array)($sale['items'] ?? []) as $savedItem) {
            if ((int)$savedItem['product_id'] === $productId) {
                $existingSources[(int)$savedItem['source_purchase_id']] = true;
            }
        }
        if ((int)$sale['document_type'] === 4) {
            $restore = sales_old_item_restore_map($sale);
        }
    }

    $stmt = db()->prepare(
        'SELECT p.id,p.purchase_no,p.batch_number,p.purchase_date,pi.expiry_date,
                pi.id AS purchase_item_id,pi.batch_id,
                pi.primary_unit_id,pi.secondary_unit_id,pi.conversion_rate,
                pi.primary_quantity,pi.secondary_quantity,
                pi.base_quantity AS purchased_base_quantity,
                pi.taxable_amount AS purchase_taxable_amount,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol
         FROM food_purchases p
         INNER JOIN food_purchase_items pi
            ON pi.purchase_id=p.id
           AND pi.branch_id=p.branch_id
           AND pi.product_id=:product_id
           AND pi.status=1
         LEFT JOIN food_units pu ON pu.id=pi.primary_unit_id AND pu.branch_id=pi.branch_id
         LEFT JOIN food_units su ON su.id=pi.secondary_unit_id AND su.branch_id=pi.branch_id
         WHERE p.branch_id=:branch_id
           AND p.posting_status=1
           AND p.status=1
         ORDER BY p.purchase_date,p.id'
    );
    $stmt->execute([':product_id' => $productId, ':branch_id' => $branchId]);

    $canViewProfit = sales_has_action((array)$access['actions'], SALES_ACTION_VIEW_PROFIT);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $purchaseId = (int)$row['id'];
        $snapshotPrimary = (int)($row['primary_unit_id'] ?? 0);
        $snapshotSecondary = (int)($row['secondary_unit_id'] ?? 0);
        $snapshotConversion = (float)($row['conversion_rate'] ?? 0);
        if ($snapshotPrimary < 1) continue;

        $batchMetaRow = $row;
        $batchMetaRow['master_primary_unit_id'] = (int)($masterProduct['primary_unit_id'] ?? 0);
        $batchMetaRow['master_secondary_unit_id'] = !empty($masterProduct['secondary_unit_id']) ? (int)$masterProduct['secondary_unit_id'] : null;
        $batchMetaRow['master_conversion_rate'] = (float)($masterProduct['secondary_conversion'] ?? 0);
        $conversionMeta = sales_batch_conversion_meta($batchMetaRow);
        $effectiveConversion = (float)$conversionMeta['conversion_rate'];
        if ($snapshotSecondary > 0 && $effectiveConversion <= 0) continue;

        $sourceMeta = [
            'primary_unit_id' => $snapshotPrimary,
            'secondary_unit_id' => $snapshotSecondary > 0 ? $snapshotSecondary : null,
            'conversion_rate' => $effectiveConversion,
            'legacy_conversion_repaired' => (bool)$conversionMeta['legacy_repaired'],
        ];
        $available = sales_available_base_stock($branchId, $productId, $purchaseId, $sourceMeta);
        $key = $productId . ':' . $purchaseId;
        if (isset($restore[$key])) {
            $available = round($available + (float)$restore[$key], 3);
        }
        if ($available <= 0.0005 && !isset($existingSources[$purchaseId])) continue;

        $result = [
            'id' => $purchaseId,
            'purchase_item_id' => (int)$row['purchase_item_id'],
            'batch_id' => $row['batch_id'] === null ? null : (int)$row['batch_id'],
            'purchase_no' => (string)$row['purchase_no'],
            'batch_number' => (string)$row['batch_number'],
            'purchase_date' => (string)$row['purchase_date'],
            'expiry_date' => $row['expiry_date'],
            'primary_unit_id' => $row['primary_unit_id'] === null ? 0 : (int)$row['primary_unit_id'],
            'secondary_unit_id' => $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'],
            'conversion_rate' => $effectiveConversion,
            'saved_conversion_rate' => $snapshotConversion,
            'legacy_conversion_repaired' => (bool)$conversionMeta['legacy_repaired'],
            'primary_unit_name' => (string)($row['primary_unit_name'] ?? ''),
            'primary_unit_symbol' => (string)($row['primary_unit_symbol'] ?? ''),
            'secondary_unit_name' => (string)($row['secondary_unit_name'] ?? ''),
            'secondary_unit_symbol' => (string)($row['secondary_unit_symbol'] ?? ''),
            'available_base_qty' => $available,
        ];

        if ($canViewProfit) {
            $baseQty = (bool)$conversionMeta['legacy_repaired']
                ? round(((float)$row['primary_quantity'] * $effectiveConversion) + (float)$row['secondary_quantity'], 3)
                : (float)$row['purchased_base_quantity'];
            $result['base_cost'] = $baseQty > 0
                ? round((float)$row['purchase_taxable_amount'] / $baseQty, 6)
                : 0.0;
        }
        $rows[] = $result;
    }

    json_success('Batch options loaded.', [
        'batches' => $rows,
        'can_view_profit' => $canViewProfit,
    ]);
}

function sales_datatable(array $access, array $context): void
{
    $branchId = (int)$context['branch_id'];
    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));

    $search = '';
    if (isset($_GET['search']) && is_array($_GET['search'])) {
        $search = trim((string)($_GET['search']['value'] ?? ''));
    }

    $documentType = isset($_GET['document_type']) && $_GET['document_type'] !== ''
        ? (int)$_GET['document_type']
        : 0;
    $taxMode = isset($_GET['tax_mode']) && $_GET['tax_mode'] !== ''
        ? (int)$_GET['tax_mode']
        : -1;
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $where = ['s.branch_id=:branch_id', 's.status=1'];
    $params = [':branch_id' => $branchId];

    if (in_array($documentType, [1,2,3,4], true)) {
        $where[] = 's.document_type=:document_type';
        $params[':document_type'] = $documentType;
    }
    if (in_array($taxMode, [0,1], true)) {
        $where[] = 's.tax_mode=:tax_mode';
        $params[':tax_mode'] = $taxMode;
    }
    if ($dateFrom !== '') {
        sales_valid_date($dateFrom, 'date_from', true);
        $where[] = 's.invoice_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        sales_valid_date($dateTo, 'date_to', true);
        $where[] = 's.invoice_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }
    if ($search !== '') {
        $where[] = '(s.sales_no LIKE :search_sales_no
                     OR s.customer_name_snapshot LIKE :search_snapshot
                     OR c.customer_name LIKE :search_customer_name
                     OR c.customer_code LIKE :search_customer_code
                     OR c.mobile LIKE :search_mobile)';

        $term = '%' . $search . '%';
        $params[':search_sales_no'] = $term;
        $params[':search_snapshot'] = $term;
        $params[':search_customer_name'] = $term;
        $params[':search_customer_code'] = $term;
        $params[':search_mobile'] = $term;
    }

    $from = ' FROM food_sales s LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id';

    $totalStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE s.branch_id=:branch_id AND s.status=1');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, in_array($key, [':branch_id',':document_type',':tax_mode'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS document_count,
            COALESCE(SUM(s.grand_total),0) AS grand_total,
            COALESCE(SUM(CASE WHEN s.document_type=4 THEN s.paid_amount ELSE 0 END),0) AS paid_amount,
            COALESCE(SUM(CASE WHEN s.document_type=4 THEN s.balance_amount ELSE 0 END),0) AS balance_amount
         ' . $from . '
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':document_type', ':tax_mode'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        's.sales_no', 's.invoice_date', 's.document_type', 'c.customer_name',
        's.tax_mode', 's.grand_total', 's.paid_amount', 's.balance_amount', 's.payment_status', 's.id'
    ];
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDirection = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = $columns[$orderColumn] ?? 's.invoice_date';

    $sql = 'SELECT s.id,s.sales_no,s.document_type,s.invoice_date,s.due_date,s.tax_mode,
                   s.customer_name_snapshot,s.grand_total,s.paid_amount,s.balance_amount,
                   s.payment_status,s.posting_status,c.customer_code,c.customer_name
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . ' ' . $orderDirection . ',s.id DESC
            LIMIT :start,:length';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, in_array($key, [':branch_id',':document_type',':tax_mode'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['document_type'] = (int)$row['document_type'];
        $row['tax_mode'] = (int)$row['tax_mode'];
        $row['payment_status'] = (int)$row['payment_status'];
        $row['posting_status'] = (int)$row['posting_status'];
        $row['grand_total'] = (float)$row['grand_total'];
        $row['paid_amount'] = (float)$row['paid_amount'];
        $row['balance_amount'] = (float)$row['balance_amount'];
        $row['document_type_label'] = sales_document_label((int)$row['document_type']);
        $row['tax_mode_label'] = (int)$row['tax_mode'] === 0 ? 'Non-GST' : 'GST';
        $row['payment_status_label'] = (int)$row['payment_status'] === 3
            ? 'Paid'
            : ((int)$row['payment_status'] === 2 ? 'Partially Paid' : 'Unpaid');
        $row['ref'] = encryptReference('sale', (int)$row['id']);
        $row['target_refs'] = sales_target_refs();
        $row['open_url'] = 'sales.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    $formMenu = menu_by_path('sales.php');
    $formActions = $formMenu ? effective_actions_for_menu($access['user'], $formMenu) : [];

    json_success('Sales documents loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'document_count' => (int)($summary['document_count'] ?? 0),
            'grand_total' => (float)($summary['grand_total'] ?? 0),
            'paid_amount' => (float)($summary['paid_amount'] ?? 0),
            'balance_amount' => (float)($summary['balance_amount'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'form_actions' => $formActions,
    ]);
}

$method = request_method();
sales_require_schema();

if ($method === 'GET' && isset($_GET['customers'])) {
    $access = require_permission('sales.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    json_success('Customer options loaded.', [
        'customers' => sales_customer_options((int)$context['branch_id']),
    ]);
}

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('sales.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    $customerMenu = menu_by_path('customer-form.php');
    $customerFormActions = $customerMenu ? effective_actions_for_menu($access['user'], $customerMenu) : [];

    json_success('Sales POS options loaded.', [
        'allowed_actions' => $access['actions'],
        'customer_form_actions' => $customerFormActions,
        'current_user_id' => (int)$access['user']['id'],
        'branch' => $context,
        'target_refs' => sales_target_refs(),
        'options' => sales_options((int)$context['branch_id']),
    ]);
}

if ($method === 'GET' && isset($_GET['batches'])) {
    $access = require_permission('sales.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    sales_batch_options($access, $context);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('sales.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    $sale = sales_record($context, sales_ref_to_id($_GET['ref']));

    $targetRef = trim((string)($_GET['target'] ?? ''));
    $targetDocumentType = 0;
    if ($targetRef !== '') {
        $targetDocumentType = sales_target_type_from_ref($targetRef, true);
        sales_require_action(
            $access,
            sales_document_action_id($targetDocumentType),
            'You do not have permission for the selected Sales document target.'
        );
        sales_require_action($access, ACTION_UPDATE, 'You do not have permission to update Sales documents.');

        if ((int)$sale['document_type'] === 4 && $targetDocumentType !== 4) {
            json_error('Final Invoice cannot be changed to another document type.', 409);
        }
    }

    $customerMenu = menu_by_path('customer-form.php');
    $customerFormActions = $customerMenu ? effective_actions_for_menu($access['user'], $customerMenu) : [];

    json_success('Sales document loaded.', [
        'sale' => $sale,
        'target_ref' => $targetRef !== '' ? $targetRef : null,
        'target_document_type' => $targetDocumentType,
        'target_refs' => sales_target_refs(),
        'allowed_actions' => $access['actions'],
        'customer_form_actions' => $customerFormActions,
        'current_user_id' => (int)$access['user']['id'],
        'branch' => $context,
        'options' => sales_options((int)$context['branch_id'], true),
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('sales-list.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    sales_datatable($access, $context);
}

if ($method === 'POST' || $method === 'PUT') {
    $access = require_permission('sales.php', ACTION_VIEW);
    $context = sales_tenant_context($access['user']);
    $data = request_data();
    $isUpdate = $method === 'PUT';
    $sale = sales_save($access, $context, $data, $isUpdate);

    $message = $isUpdate
        ? sales_document_label((int)$sale['document_type']) . ' updated successfully.'
        : sales_document_label((int)$sale['document_type']) . ' created successfully.';

    json_success($message, ['sale' => $sale], $isUpdate ? 200 : 201);
}

json_error('Method not allowed.', 405);
