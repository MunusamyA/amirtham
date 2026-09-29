<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/supplier-finance.php';

function pr_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Purchase Return is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, b.state_code, c.company_name
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

function pr_require_schema(): void
{
    $requiredTables = [
        'branches',
        'companies',
        'users',
        'food_suppliers',
        'food_products',
        'food_units',
        'food_purchases',
        'food_purchase_items',
        'food_purchase_returns',
        'food_purchase_return_items',
        'food_stock_movements',
    ];

    $missingTables = [];
    foreach ($requiredTables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            $missingTables[] = $table;
        }
    }

    if ($missingTables) {
        json_error(
            'Purchase Return schema is incomplete. Missing table(s): ' . implode(', ', $missingTables) .
            '. Run sql/purchase-return-schema-update.sql first.',
            500
        );
    }

    $requiredColumns = [
        'food_purchases' => [
            'purchase_no', 'batch_number', 'posting_status', 'grand_total'
        ],
        'food_purchase_items' => [
            'selected_unit_id', 'primary_unit_id', 'secondary_unit_id', 'conversion_rate',
            'primary_quantity', 'secondary_quantity', 'base_quantity', 'unit_price',
            'discount_amount', 'overall_discount_share', 'purchase_tax_type',
            'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate',
            'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount',
            'taxable_amount', 'line_total'
        ],
        'food_purchase_returns' => [
            'return_no', 'subtotal', 'discount_total', 'taxable_total',
            'cgst_total', 'sgst_total', 'igst_total', 'cess_total',
            'before_round_total', 'round_off', 'grand_total', 'posting_status'
        ],
        'food_purchase_return_items' => [
            'selected_unit_id', 'primary_unit_id', 'secondary_unit_id', 'conversion_rate', 'batch_id',
            'primary_quantity', 'secondary_quantity', 'base_quantity', 'unit_price',
            'discount_amount', 'overall_discount_share', 'purchase_tax_type',
            'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate',
            'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount',
            'taxable_amount', 'line_total'
        ],
        'food_stock_movements' => [
            'branch_id', 'product_id', 'source_purchase_id', 'batch_id',
            'movement_date', 'movement_type', 'reference_type',
            'reference_id', 'reference_no', 'quantity_in', 'quantity_out',
            'unit_id', 'conversion_rate', 'remarks', 'status', 'created_by'
        ],
    ];

    $missingColumns = [];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = db()->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . db()->quote($column)
            );
            if (!$stmt || !$stmt->fetchColumn()) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }

    if ($missingColumns) {
        json_error(
            'Purchase Return schema is incomplete. Missing column(s): ' . implode(', ', $missingColumns) .
            '. Run sql/purchase-return-schema-update.sql first.',
            500
        );
    }
}

function pr_effective_conversion(array $row): float
{
    /* Purchase Return always follows the original Purchase Item snapshot. */
    $secondaryId = (int)($row['secondary_unit_id'] ?? 0);
    if ($secondaryId < 1) return 1.0;

    $saved = (float)($row['conversion_rate'] ?? 0);
    if ($saved <= 0) {
        json_error(
            'Original Purchase Item has an invalid saved Primary → Secondary conversion. Recreate/fix that Purchase Batch first.',
            409
        );
    }
    return $saved;
}

function pr_snapshot_quantities(array $row): array
{
    $primary = round((float)($row['primary_quantity'] ?? 0), 3);
    $secondary = round((float)($row['secondary_quantity'] ?? 0), 3);
    if ($primary <= 0 && $secondary <= 0 && (float)($row['quantity'] ?? 0) > 0) {
        $legacy = round((float)$row['quantity'], 3);
        $selected = (int)($row['selected_unit_id'] ?? 0);
        $secondaryId = (int)($row['secondary_unit_id'] ?? 0);
        if ($secondaryId > 0 && $selected === $secondaryId) $secondary = $legacy;
        else $primary = $legacy;
    }
    return [$primary, $secondary];
}

function pr_nullable($value): ?string
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

function pr_valid_date($value, string $field, bool $required = false): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        if ($required) {
            json_error('Purchase Return validation failed.', 422, [$field => 'This date is required.']);
        }
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        json_error('Purchase Return validation failed.', 422, [$field => 'Enter a valid date.']);
    }

    return $value;
}

function pr_return_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Purchase Return reference is required.', 422, ['ref' => 'Purchase Return reference is required.']);
    }

    try {
        return decryptReference(trim($value), 'purchase_return');
    } catch (Throwable $e) {
        json_error('Invalid Purchase Return reference.', 422, ['ref' => 'Invalid Purchase Return reference.']);
    }

    return 0;
}

function pr_purchase_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Original Purchase is required.', 422, ['purchase_ref' => 'Original Purchase is required.']);
    }

    try {
        return decryptReference(trim($value), 'purchase');
    } catch (Throwable $e) {
        json_error('Invalid Original Purchase reference.', 422, ['purchase_ref' => 'Invalid Original Purchase reference.']);
    }

    return 0;
}

function pr_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT return_no
         FROM food_purchase_returns
         WHERE branch_id=:branch_id
           AND return_no REGEXP '^PRT[0-9]+$'
         ORDER BY CAST(SUBSTRING(return_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^PRT([0-9]+)$/i', $last, $matches)) {
        $next = ((int)$matches[1]) + 1;
    }

    return 'PRT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function pr_purchase_options(int $branchId, bool $includeInactive = false): array
{
    $sql =
        'SELECT p.id,p.purchase_no,p.batch_number,p.purchase_date,p.supplier_invoice_number,
                p.grand_total,p.posting_status,p.status,
                s.supplier_code,s.supplier_name
         FROM food_purchases p
         INNER JOIN food_suppliers s
                 ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.branch_id=:branch_id
           AND p.posting_status=1';

    if (!$includeInactive) {
        $sql .= ' AND p.status=1';
    }

    $sql .= ' ORDER BY p.purchase_date DESC,p.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'ref' => encryptReference('purchase', (int)$row['id']),
            'purchase_no' => (string)$row['purchase_no'],
            'batch_number' => (string)($row['batch_number'] ?? ''),
            'purchase_date' => (string)$row['purchase_date'],
            'supplier_invoice_number' => (string)($row['supplier_invoice_number'] ?? ''),
            'grand_total' => (float)$row['grand_total'],
            'supplier_code' => (string)($row['supplier_code'] ?? ''),
            'supplier_name' => (string)$row['supplier_name'],
            'status' => (int)$row['status'],
        ];
    }

    return $rows;
}

function pr_source_stock_base(int $branchId, int $purchaseId, int $productId): float
{
    /* Stock movements already store canonical base quantity for the Purchase
       batch.  Summing them directly keeps old batches stable even if Product
       Master conversion is changed later. */
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(quantity_in-quantity_out),0)
         FROM food_stock_movements
         WHERE branch_id=:branch_id
           AND product_id=:product_id
           AND status=1
           AND (
                source_purchase_id=:source_purchase_id
                OR (
                    source_purchase_id IS NULL
                    AND reference_type=\'purchase\'
                    AND reference_id=:legacy_purchase_id
                )
           )'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
        ':source_purchase_id' => $purchaseId,
        ':legacy_purchase_id' => $purchaseId,
    ]);
    return round((float)$stmt->fetchColumn(), 3);
}

function pr_posted_return_base(int $branchId, int $purchaseItemId, int $excludeReturnId = 0): float
{
    $sql =
        'SELECT pri.primary_quantity,pri.secondary_quantity,pri.quantity,pri.base_quantity,
                pri.conversion_rate,pri.selected_unit_id,
                pi.primary_unit_id,pi.secondary_unit_id
         FROM food_purchase_return_items pri
         INNER JOIN food_purchase_returns pr
                 ON pr.id=pri.purchase_return_id AND pr.branch_id=pri.branch_id
         INNER JOIN food_purchase_items pi
                 ON pi.id=pri.purchase_item_id AND pi.branch_id=pri.branch_id
         WHERE pri.branch_id=:branch_id
           AND pri.purchase_item_id=:purchase_item_id
           AND pri.status=1
           AND pr.status=1
           AND pr.posting_status=1';

    $params = [':branch_id'=>$branchId, ':purchase_item_id'=>$purchaseItemId];
    if ($excludeReturnId > 0) {
        $sql .= ' AND pr.id<>:exclude_return_id';
        $params[':exclude_return_id'] = $excludeReturnId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $total = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        $conversion = pr_effective_conversion($row);
        [$primary, $secondary] = pr_snapshot_quantities($row);
        $base = ($primary > 0 || $secondary > 0)
            ? round(($primary * $conversion) + $secondary, 3)
            : round((float)($row['base_quantity'] ?? 0), 3);
        $total += $base;
    }
    return round($total, 3);
}

function pr_purchase_data(array $context, int $purchaseId, int $excludeReturnId = 0): array
{
    $branchId = (int)$context['branch_id'];

    $stmt = db()->prepare(
        'SELECT p.*,s.supplier_code,s.supplier_name
         FROM food_purchases p
         INNER JOIN food_suppliers s
                 ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.id=:id
           AND p.branch_id=:branch_id
           AND p.posting_status=1
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $purchaseId,
        ':branch_id' => $branchId,
    ]);

    $purchase = $stmt->fetch();
    if (!$purchase) {
        json_error('Original posted Purchase was not found.', 404);
    }

    $itemStmt = db()->prepare(
        'SELECT pi.*,
                p.product_code,p.product_name,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol
         FROM food_purchase_items pi
         INNER JOIN food_products p
                 ON p.id=pi.product_id AND p.branch_id=pi.branch_id
         LEFT JOIN food_units pu
                ON pu.id=pi.primary_unit_id AND pu.branch_id=pi.branch_id
         LEFT JOIN food_units su
                ON su.id=pi.secondary_unit_id AND su.branch_id=pi.branch_id
         WHERE pi.purchase_id=:purchase_id
           AND pi.branch_id=:branch_id
           AND pi.status=1
         ORDER BY pi.id'
    );
    $itemStmt->execute([
        ':purchase_id' => $purchaseId,
        ':branch_id' => $branchId,
    ]);

    $items = [];
    foreach ($itemStmt->fetchAll() as $row) {
        $purchaseItemId = (int)$row['id'];
        $productId = (int)$row['product_id'];
        $conversion = pr_effective_conversion($row);
        [$primaryQty, $secondaryQty] = pr_snapshot_quantities($row);
        $purchasedQty = round((float)$row['quantity'], 3); // legacy equivalent Primary Qty
        $purchasedBase = ($primaryQty > 0 || $secondaryQty > 0)
            ? round(($primaryQty * $conversion) + $secondaryQty, 3)
            : round((float)($row['base_quantity'] ?? 0), 3);

        $returnedBase = pr_posted_return_base($branchId, $purchaseItemId, $excludeReturnId);
        $sourceStockBase = pr_source_stock_base($branchId, $purchaseId, $productId);
        $availableBase = max(0.0, min(
            round($purchasedBase - $returnedBase, 3),
            round($sourceStockBase, 3)
        ));

        $hasSecondary = !empty($row['secondary_unit_id']);
        $availablePrimary = $hasSecondary ? floor(($availableBase + 0.0000001) / $conversion) : $availableBase;
        $availableSecondary = $hasSecondary ? round($availableBase - ($availablePrimary * $conversion), 3) : 0.0;
        $returnedPrimary = $hasSecondary ? floor(($returnedBase + 0.0000001) / $conversion) : $returnedBase;
        $returnedSecondary = $hasSecondary ? round($returnedBase - ($returnedPrimary * $conversion), 3) : 0.0;

        $row['id'] = $purchaseItemId;
        $row['product_id'] = $productId;
        $row['batch_id'] = $row['batch_id'] === null ? null : (int)$row['batch_id'];
        $row['selected_unit_id'] = (int)($row['selected_unit_id'] ?? 0);
        $row['primary_unit_id'] = (int)($row['primary_unit_id'] ?? 0);
        $row['secondary_unit_id'] = $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'];
        $row['conversion_rate'] = $conversion;
        $row['primary_quantity'] = $primaryQty;
        $row['secondary_quantity'] = $secondaryQty;
        $row['quantity'] = $purchasedQty;
        $row['base_quantity'] = $purchasedBase;
        $row['unit_price'] = (float)($row['unit_price'] ?? 0);
        $row['unit_price'] = (float)$row['unit_price'];
        $row['already_returned_base_quantity'] = $returnedBase;
        $row['already_returned_primary_quantity'] = $returnedPrimary;
        $row['already_returned_secondary_quantity'] = $returnedSecondary;
        $row['available_base_quantity'] = $availableBase;
        $row['available_primary_quantity'] = $availablePrimary;
        $row['available_secondary_quantity'] = $availableSecondary;
        $row['source_stock_base_quantity'] = $sourceStockBase;
        $row['discount_amount'] = (float)($row['discount_amount'] ?? 0);
        $row['overall_discount_share'] = (float)($row['overall_discount_share'] ?? 0);
        $row['taxable_amount'] = (float)($row['taxable_amount'] ?? 0);
        $row['purchase_tax_type'] = (int)($row['purchase_tax_type'] ?? 2);
        $row['gst_rate'] = (float)($row['gst_rate'] ?? $row['tax_rate'] ?? 0);
        $row['cgst_rate'] = (float)($row['cgst_rate'] ?? 0);
        $row['sgst_rate'] = (float)($row['sgst_rate'] ?? 0);
        $row['igst_rate'] = (float)($row['igst_rate'] ?? 0);
        $row['cess_rate'] = (float)($row['cess_rate'] ?? 0);
        $row['cgst_amount'] = (float)($row['cgst_amount'] ?? 0);
        $row['sgst_amount'] = (float)($row['sgst_amount'] ?? 0);
        $row['igst_amount'] = (float)($row['igst_amount'] ?? 0);
        $row['cess_amount'] = (float)($row['cess_amount'] ?? 0);
        $row['line_total'] = (float)($row['line_total'] ?? 0);
        /* Legacy display aliases kept for older clients. */
        $row['already_returned_quantity'] = round($returnedBase / $conversion, 3);
        $row['available_quantity'] = round($availableBase / $conversion, 3);
        $row['source_stock_quantity'] = round($sourceStockBase / $conversion, 3);
        $items[] = $row;
    }

    $purchase['id'] = (int)$purchase['id'];
    $purchase['supplier_id'] = (int)$purchase['supplier_id'];
    $purchase['grand_total'] = (float)$purchase['grand_total'];
    $purchase['ref'] = encryptReference('purchase', (int)$purchase['id']);
    $purchase['items'] = $items;

    return $purchase;
}

function pr_record(array $context, int $returnId): array
{
    $branchId = (int)$context['branch_id'];

    $stmt = db()->prepare(
        'SELECT pr.*,
                p.purchase_no,p.batch_number,p.purchase_date,p.supplier_invoice_number,
                s.supplier_code,s.supplier_name
         FROM food_purchase_returns pr
         INNER JOIN food_purchases p
                 ON p.id=pr.purchase_id AND p.branch_id=pr.branch_id
         INNER JOIN food_suppliers s
                 ON s.id=pr.supplier_id AND s.branch_id=pr.branch_id
         WHERE pr.id=:id
           AND pr.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $returnId,
        ':branch_id' => $branchId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        json_error('Purchase Return was not found.', 404);
    }

    foreach (['id','branch_id','supplier_id','purchase_id','posting_status','status'] as $key) {
        $row[$key] = (int)$row[$key];
    }
    foreach ([
        'subtotal','discount_total','taxable_total','cgst_total','sgst_total',
        'igst_total','cess_total','before_round_total','round_off','grand_total'
    ] as $key) {
        $row[$key] = (float)$row[$key];
    }

    $row['ref'] = encryptReference('purchase_return', (int)$row['id']);
    $row['purchase_ref'] = encryptReference('purchase', (int)$row['purchase_id']);

    $itemStmt = db()->prepare(
        'SELECT pri.*,
                p.product_code,p.product_name,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol
         FROM food_purchase_return_items pri
         INNER JOIN food_products p
                 ON p.id=pri.product_id AND p.branch_id=pri.branch_id
         LEFT JOIN food_units pu
                ON pu.id=pri.primary_unit_id AND pu.branch_id=pri.branch_id
         LEFT JOIN food_units su
                ON su.id=pri.secondary_unit_id AND su.branch_id=pri.branch_id
         WHERE pri.purchase_return_id=:return_id
           AND pri.branch_id=:branch_id
           AND pri.status=1
         ORDER BY pri.id'
    );
    $itemStmt->execute([
        ':return_id' => $returnId,
        ':branch_id' => $branchId,
    ]);

    $items = [];
    foreach ($itemStmt->fetchAll() as $item) {
        foreach (['id','purchase_return_id','purchase_item_id','product_id','selected_unit_id','primary_unit_id','secondary_unit_id','purchase_tax_type','status'] as $key) {
            if (array_key_exists($key, $item)) {
                $item[$key] = $item[$key] === null ? null : (int)$item[$key];
            }
        }
        foreach ([
            'conversion_rate','primary_quantity','secondary_quantity','quantity','base_quantity','unit_price','discount_amount',
            'overall_discount_share','taxable_amount','gst_rate','cgst_rate','sgst_rate',
            'igst_rate','cess_rate','cgst_amount','sgst_amount','igst_amount','cess_amount',
            'line_total'
        ] as $key) {
            if (array_key_exists($key, $item)) {
                $item[$key] = (float)$item[$key];
            }
        }
        $items[] = $item;
    }

    $row['items'] = $items;
    return $row;
}

function pr_calculate(array $context, array $data, int $editingReturnId = 0): array
{
    $branchId = (int)$context['branch_id'];
    $purchaseId = pr_purchase_ref_to_id($data['purchase_ref'] ?? '');
    $purchase = pr_purchase_data($context, $purchaseId, $editingReturnId);

    $returnDate = pr_valid_date($data['return_date'] ?? '', 'return_date', true);
    $reason = pr_nullable($data['reason'] ?? null);

    $rawItems = $data['items'] ?? [];
    if (!is_array($rawItems)) {
        $rawItems = [];
    }

    $purchaseItems = [];
    foreach ($purchase['items'] as $item) {
        $purchaseItems[(int)$item['id']] = $item;
    }

    $items = [];
    $subtotal = 0.0;
    $discountTotal = 0.0;
    $taxableTotal = 0.0;
    $cgstTotal = 0.0;
    $sgstTotal = 0.0;
    $igstTotal = 0.0;
    $cessTotal = 0.0;
    $beforeRound = 0.0;
    $requestedBaseByProduct = [];
    $sourceStockBaseByProduct = [];

    foreach ($rawItems as $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $purchaseItemId = (int)($raw['purchase_item_id'] ?? 0);
        if ($purchaseItemId < 1) continue;

        if (!isset($purchaseItems[$purchaseItemId])) {
            json_error('Purchase Return validation failed.', 422, [
                'items' => 'A selected Product does not belong to the Original Purchase.'
            ]);
        }

        $source = $purchaseItems[$purchaseItemId];
        $conversion = (float)$source['conversion_rate'];
        if ($conversion <= 0) $conversion = 1.0;
        $hasSecondary = !empty($source['secondary_unit_id']);

        $primaryQtyRaw = $raw['primary_quantity'] ?? null;
        $secondaryQtyRaw = $raw['secondary_quantity'] ?? null;
        if ($primaryQtyRaw === null && $secondaryQtyRaw === null) {
            /* Legacy one-unit return payload. */
            $primaryQtyRaw = $raw['quantity'] ?? 0;
            $secondaryQtyRaw = 0;
        }
        if (!is_numeric($primaryQtyRaw) || (float)$primaryQtyRaw < 0 ||
            !is_numeric($secondaryQtyRaw) || (float)$secondaryQtyRaw < 0) {
            json_error('Purchase Return validation failed.', 422, ['items'=>'Return quantities must be zero or greater.']);
        }

        $primaryQty = round((float)$primaryQtyRaw,3);
        $secondaryQty = $hasSecondary ? round((float)$secondaryQtyRaw,3) : 0.0;
        if ($primaryQty <= 0 && $secondaryQty <= 0) continue;

        $baseQty = round(($primaryQty * $conversion) + $secondaryQty, 3);
        $availableBase = round((float)$source['available_base_quantity'],3);
        if ($baseQty > $availableBase + 0.0005) {
            json_error('Purchase Return validation failed.', 422, [
                'items' => $source['product_name'] . ' return quantity exceeds available return stock.'
            ]);
        }
        $qty = round($primaryQty + ($hasSecondary ? $secondaryQty / $conversion : 0),3); // legacy equivalent Primary Qty
        $originalBase = (float)$source['base_quantity'];
        if ($originalBase <= 0) {
            $originalBase = round(((float)$source['primary_quantity'] * $conversion) + (float)$source['secondary_quantity'], 3);
        }
        if ($originalBase <= 0) {
            json_error('Original Purchase Item quantity is invalid.', 422);
        }

        $ratio = min(1.0, max(0.0, $baseQty / $originalBase));
        $primaryRateSnapshot = (float)($source['unit_price'] ?? 0);
        $secondaryRateSnapshot = ($hasSecondary && $conversion > 0)
            ? round($primaryRateSnapshot / $conversion, 6)
            : 0.0;
        $grossOriginal = round(
            ((float)($source['primary_quantity'] ?? 0) * $primaryRateSnapshot) +
            ((float)($source['secondary_quantity'] ?? 0) * $secondaryRateSnapshot),
            2
        );
        if ($grossOriginal <= 0 && (float)($source['quantity'] ?? 0) > 0) {
            $grossOriginal = round((float)$source['quantity'] * (float)($source['unit_price'] ?? 0), 2);
        }
        $gross = round($grossOriginal * $ratio, 2);
        $itemDiscount = round((float)$source['discount_amount'] * $ratio, 2);
        $overallShare = round((float)$source['overall_discount_share'] * $ratio, 2);
        $taxable = round((float)$source['taxable_amount'] * $ratio, 2);
        $cgstAmount = round((float)$source['cgst_amount'] * $ratio, 2);
        $sgstAmount = round((float)$source['sgst_amount'] * $ratio, 2);
        $igstAmount = round((float)$source['igst_amount'] * $ratio, 2);
        $cessAmount = round((float)$source['cess_amount'] * $ratio, 2);
        $lineTotal = round((float)$source['line_total'] * $ratio, 2);

        $productId = (int)$source['product_id'];
        $requestedBaseByProduct[$productId] = round(($requestedBaseByProduct[$productId] ?? 0) + $baseQty, 3);
        if (!isset($sourceStockBaseByProduct[$productId])) {
            $sourceStockBaseByProduct[$productId] = pr_source_stock_base($branchId, $purchaseId, $productId);
        }

        $items[] = [
            'purchase_item_id' => $purchaseItemId,
            'product_id' => (int)$source['product_id'],
            'batch_id' => $source['batch_id'] === null ? null : (int)$source['batch_id'],
            'selected_unit_id' => (int)$source['primary_unit_id'],
            'primary_unit_id' => (int)$source['primary_unit_id'],
            'secondary_unit_id' => $source['secondary_unit_id'] === null ? null : (int)$source['secondary_unit_id'],
            'conversion_rate' => $conversion,
            'primary_quantity' => $primaryQty,
            'secondary_quantity' => $secondaryQty,
            'quantity' => $qty,
            'base_quantity' => $baseQty,
            'unit_price' => $primaryRateSnapshot,
            'discount_amount' => $itemDiscount,
            'overall_discount_share' => $overallShare,
            'purchase_tax_type' => (int)$source['purchase_tax_type'],
            'taxable_amount' => $taxable,
            'gst_rate' => (float)$source['gst_rate'],
            'cgst_rate' => (float)$source['cgst_rate'],
            'sgst_rate' => (float)$source['sgst_rate'],
            'igst_rate' => (float)$source['igst_rate'],
            'cess_rate' => (float)$source['cess_rate'],
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'cess_amount' => $cessAmount,
            'line_total' => $lineTotal,
            'product_name' => (string)$source['product_name'],
        ];

        $subtotal += $gross;
        $discountTotal += ($itemDiscount + $overallShare);
        $taxableTotal += $taxable;
        $cgstTotal += $cgstAmount;
        $sgstTotal += $sgstAmount;
        $igstTotal += $igstAmount;
        $cessTotal += $cessAmount;
        $beforeRound += $lineTotal;
    }

    foreach ($requestedBaseByProduct as $productId => $requestedBase) {
        $sourceStockBase = (float)($sourceStockBaseByProduct[$productId] ?? 0);
        if ($requestedBase > $sourceStockBase + 0.0005) {
            json_error('Purchase Return validation failed.', 422, [
                'items' => 'Total Return Qty for one Product exceeds the stock currently available from this Purchase Batch.'
            ]);
        }
    }

    if (!$items) {
        json_error('Enter Return Qty for at least one Product.', 422, [
            'items' => 'At least one Product Return Qty must be greater than zero.'
        ]);
    }

    $subtotal = round($subtotal, 2);
    $discountTotal = round($discountTotal, 2);
    $taxableTotal = round($taxableTotal, 2);
    $cgstTotal = round($cgstTotal, 2);
    $sgstTotal = round($sgstTotal, 2);
    $igstTotal = round($igstTotal, 2);
    $cessTotal = round($cessTotal, 2);
    $beforeRound = round($beforeRound, 2);

    return [
        'purchase_id' => $purchaseId,
        'supplier_id' => (int)$purchase['supplier_id'],
        'return_date' => $returnDate,
        'reason' => $reason,
        'subtotal' => $subtotal,
        'discount_total' => $discountTotal,
        'taxable_total' => $taxableTotal,
        'cgst_total' => $cgstTotal,
        'sgst_total' => $sgstTotal,
        'igst_total' => $igstTotal,
        'cess_total' => $cessTotal,
        'before_round_total' => $beforeRound,
        'round_off' => 0.00,
        'grand_total' => $beforeRound,
        'items' => $items,
        'purchase' => $purchase,
    ];
}

function pr_save(array $access, array $context, array $data, bool $isUpdate): array
{
    $branchId = (int)$context['branch_id'];
    $userId = (int)$access['user']['id'];
    $returnId = 0;
    $old = null;

    if ($isUpdate) {
        $returnId = pr_return_ref_to_id($data['ref'] ?? '');
        $old = pr_record($context, $returnId);
        if ((int)$old['posting_status'] === 1) {
            json_error('Posted Purchase Returns are read-only and cannot be edited.', 409);
        }
    }

    $calc = pr_calculate($context, $data, $returnId);
    $mode = strtolower(trim((string)($data['save_mode'] ?? 'draft')));
    $posting = $mode === 'post' ? 1 : 0;
    $returnNo = $isUpdate ? (string)$old['return_no'] : pr_generate_no($branchId);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($isUpdate) {
            $stmt = $pdo->prepare(
                'UPDATE food_purchase_returns
                 SET supplier_id=:supplier_id,
                     purchase_id=:purchase_id,
                     return_date=:return_date,
                     reason=:reason,
                     subtotal=:subtotal,
                     discount_total=:discount_total,
                     taxable_total=:taxable_total,
                     cgst_total=:cgst_total,
                     sgst_total=:sgst_total,
                     igst_total=:igst_total,
                     cess_total=:cess_total,
                     before_round_total=:before_round_total,
                     round_off=:round_off,
                     grand_total=:grand_total,
                     posting_status=:posting_status,
                     status=1,
                     updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO food_purchase_returns(
                    branch_id,supplier_id,purchase_id,return_no,return_date,reason,
                    subtotal,discount_total,taxable_total,cgst_total,sgst_total,
                    igst_total,cess_total,before_round_total,round_off,grand_total,
                    posting_status,status,created_by,created_at,updated_at
                 ) VALUES(
                    :branch_id,:supplier_id,:purchase_id,:return_no,:return_date,:reason,
                    :subtotal,:discount_total,:taxable_total,:cgst_total,:sgst_total,
                    :igst_total,:cess_total,:before_round_total,:round_off,:grand_total,
                    :posting_status,1,:created_by,NOW(),NOW()
                 )'
            );
        }

        $params = [
            ':branch_id' => $branchId,
            ':supplier_id' => $calc['supplier_id'],
            ':purchase_id' => $calc['purchase_id'],
            ':return_date' => $calc['return_date'],
            ':reason' => $calc['reason'],
            ':subtotal' => $calc['subtotal'],
            ':discount_total' => $calc['discount_total'],
            ':taxable_total' => $calc['taxable_total'],
            ':cgst_total' => $calc['cgst_total'],
            ':sgst_total' => $calc['sgst_total'],
            ':igst_total' => $calc['igst_total'],
            ':cess_total' => $calc['cess_total'],
            ':before_round_total' => $calc['before_round_total'],
            ':round_off' => $calc['round_off'],
            ':grand_total' => $calc['grand_total'],
            ':posting_status' => $posting,
        ];

        if ($isUpdate) {
            $params[':id'] = $returnId;
        } else {
            $params[':return_no'] = $returnNo;
            $params[':created_by'] = $userId;
        }

        $stmt->execute($params);

        if (!$isUpdate) {
            $returnId = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'DELETE FROM food_purchase_return_items
                 WHERE purchase_return_id=:id AND branch_id=:branch_id'
            )->execute([
                ':id' => $returnId,
                ':branch_id' => $branchId,
            ]);
        }

        $itemStmt = $pdo->prepare(
            'INSERT INTO food_purchase_return_items(
                purchase_return_id,branch_id,purchase_item_id,product_id,
                selected_unit_id,primary_unit_id,secondary_unit_id,conversion_rate,batch_id,
                primary_quantity,secondary_quantity,quantity,base_quantity,unit_price,
                discount_amount,overall_discount_share,purchase_tax_type,
                taxable_amount,tax_rate,gst_rate,cgst_rate,sgst_rate,igst_rate,cess_rate,
                cgst_amount,sgst_amount,igst_amount,cess_amount,line_total,
                status,created_by,created_at,updated_at
             ) VALUES(
                :purchase_return_id,:branch_id,:purchase_item_id,:product_id,
                :selected_unit_id,:primary_unit_id,:secondary_unit_id,:conversion_rate,:batch_id,
                :primary_quantity,:secondary_quantity,:quantity,:base_quantity,:unit_price,
                :discount_amount,:overall_discount_share,:purchase_tax_type,
                :taxable_amount,:tax_rate,:gst_rate,:cgst_rate,:sgst_rate,:igst_rate,:cess_rate,
                :cgst_amount,:sgst_amount,:igst_amount,:cess_amount,:line_total,
                1,:created_by,NOW(),NOW()
             )'
        );

        $movementStmt = $posting
            ? $pdo->prepare(
                'INSERT INTO food_stock_movements(
                    branch_id,product_id,source_purchase_id,batch_id,movement_date,
                    movement_type,reference_type,reference_id,reference_no,
                    quantity_in,quantity_out,unit_id,conversion_rate,remarks,
                    status,created_by,created_at
                 ) VALUES(
                    :branch_id,:product_id,:source_purchase_id,:batch_id,:movement_date,
                    3,\'purchase_return\',:reference_id,:reference_no,
                    0,:quantity_out,:unit_id,:conversion_rate,:remarks,
                    1,:created_by,NOW()
                 )'
            )
            : null;

        foreach ($calc['items'] as $item) {
            $itemStmt->execute([
                ':purchase_return_id' => $returnId,
                ':branch_id' => $branchId,
                ':purchase_item_id' => $item['purchase_item_id'],
                ':product_id' => $item['product_id'],
                ':selected_unit_id' => $item['selected_unit_id'],
                ':primary_unit_id' => $item['primary_unit_id'],
                ':secondary_unit_id' => $item['secondary_unit_id'],
                ':batch_id' => $item['batch_id'],
                ':conversion_rate' => $item['conversion_rate'],
                ':primary_quantity' => $item['primary_quantity'],
                ':secondary_quantity' => $item['secondary_quantity'],
                ':quantity' => $item['quantity'],
                ':base_quantity' => $item['base_quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount_amount' => $item['discount_amount'],
                ':overall_discount_share' => $item['overall_discount_share'],
                ':purchase_tax_type' => $item['purchase_tax_type'],
                ':taxable_amount' => $item['taxable_amount'],
                ':tax_rate' => $item['gst_rate'],
                ':gst_rate' => $item['gst_rate'],
                ':cgst_rate' => $item['cgst_rate'],
                ':sgst_rate' => $item['sgst_rate'],
                ':igst_rate' => $item['igst_rate'],
                ':cess_rate' => $item['cess_rate'],
                ':cgst_amount' => $item['cgst_amount'],
                ':sgst_amount' => $item['sgst_amount'],
                ':igst_amount' => $item['igst_amount'],
                ':cess_amount' => $item['cess_amount'],
                ':line_total' => $item['line_total'],
                ':created_by' => $userId,
            ]);

            if ($movementStmt) {
                $movementStmt->execute([
                    ':branch_id' => $branchId,
                    ':product_id' => $item['product_id'],
                    ':source_purchase_id' => $calc['purchase_id'],
                    ':batch_id' => $item['batch_id'],
                    ':movement_date' => $calc['return_date'],
                    ':reference_id' => $returnId,
                    ':reference_no' => $returnNo,
                    ':quantity_out' => $item['base_quantity'],
                    ':unit_id' => $item['secondary_unit_id'] !== null ? (int)$item['secondary_unit_id'] : ($item['primary_unit_id'] > 0 ? (int)$item['primary_unit_id'] : null),
                    ':conversion_rate' => $item['conversion_rate'],
                    ':remarks' => 'Purchase Return ' . $returnNo,
                    ':created_by' => $userId,
                ]);
            }
        }

        if ($posting) { sf_refresh_purchase($pdo, $branchId, (int)$calc['purchase_id']); }
        $pdo->commit();

        audit_log(
            $userId,
            $isUpdate ? ACTION_UPDATE : ACTION_CREATE,
            [
                'company_id' => (int)$context['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $returnId,
                'old_data' => $old,
            ]
        );

        return pr_record($context, $returnId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$method = request_method();
pr_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('purchase-return-form.php', ACTION_VIEW);
    $context = pr_tenant_context($access['user']);

    json_success('Purchase Return form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_return_no' => pr_generate_no((int)$context['branch_id']),
        'purchases' => pr_purchase_options((int)$context['branch_id']),
        'branch' => $context,
    ]);
}

if ($method === 'GET' && isset($_GET['purchase_ref'])) {
    $access = require_permission('purchase-return-form.php', ACTION_VIEW);
    $context = pr_tenant_context($access['user']);
    $purchaseId = pr_purchase_ref_to_id($_GET['purchase_ref']);

    json_success('Original Purchase loaded.', [
        'purchase' => pr_purchase_data($context, $purchaseId),
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('purchase-return-form.php', ACTION_VIEW);
    $context = pr_tenant_context($access['user']);
    $returnId = pr_return_ref_to_id($_GET['ref']);
    $return = pr_record($context, $returnId);

    json_success('Purchase Return loaded.', [
        'allowed_actions' => $access['actions'],
        'purchase_return' => $return,
        'purchase' => pr_purchase_data($context, (int)$return['purchase_id'], $returnId),
        'purchases' => pr_purchase_options((int)$context['branch_id'], true),
        'branch' => $context,
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('purchase-return-list.php', ACTION_VIEW);
    $context = pr_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];
    $formActions = effective_actions_for_menu(
        $access['user'],
        menu_by_path('purchase-return-form.php')
    );

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $posting = isset($_GET['posting_status']) && $_GET['posting_status'] !== ''
        ? (int)$_GET['posting_status']
        : -1;
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $where = ['pr.branch_id=:branch_id', 'pr.status=1'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(pr.return_no LIKE :s_return
                  OR p.purchase_no LIKE :s_purchase
                  OR p.batch_number LIKE :s_batch
                  OR s.supplier_code LIKE :s_supplier_code
                  OR s.supplier_name LIKE :s_supplier_name)';
        $params[':s_return'] = $like;
        $params[':s_purchase'] = $like;
        $params[':s_batch'] = $like;
        $params[':s_supplier_code'] = $like;
        $params[':s_supplier_name'] = $like;
    }

    if (in_array($posting, [0,1], true)) {
        $where[] = 'pr.posting_status=:posting_status';
        $params[':posting_status'] = $posting;
    }

    if ($dateFrom !== '') {
        $where[] = 'pr.return_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'pr.return_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    $from =
        ' FROM food_purchase_returns pr
          INNER JOIN food_purchases p
                  ON p.id=pr.purchase_id AND p.branch_id=pr.branch_id
          INNER JOIN food_suppliers s
                  ON s.id=pr.supplier_id AND s.branch_id=pr.branch_id';

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)' . $from . ' WHERE pr.branch_id=:branch_id AND pr.status=1'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS return_count,
            COALESCE(SUM(pr.grand_total),0) AS return_total,
            COALESCE(SUM(CASE WHEN pr.posting_status=1 THEN 1 ELSE 0 END),0) AS posted_count,
            COALESCE(SUM(CASE WHEN pr.posting_status=0 THEN 1 ELSE 0 END),0) AS draft_count'
        . $from .
        ' WHERE ' . implode(' AND ', $where)
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':posting_status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        'pr.return_no',
        'pr.return_date',
        'p.purchase_no',
        'p.batch_number',
        's.supplier_name',
        'pr.grand_total',
        'pr.posting_status',
    ];
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 1);
    $orderDirection = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = $columns[$orderColumn] ?? 'pr.return_date';

    $sql =
        'SELECT pr.id,pr.return_no,pr.return_date,pr.grand_total,pr.posting_status,
                p.purchase_no,p.batch_number,
                s.supplier_code,s.supplier_name' .
        $from .
        ' WHERE ' . implode(' AND ', $where) .
        ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ',pr.id DESC
          LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':posting_status'], true) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['grand_total'] = (float)$row['grand_total'];
        $row['posting_status'] = (int)$row['posting_status'];
        $row['posting_status_label'] = $row['posting_status'] === 1 ? 'Posted' : 'Draft';
        $row['ref'] = encryptReference('purchase_return', (int)$row['id']);
        $row['open_url'] = 'purchase-return-form.php?ref=' . rawurlencode($row['ref']);
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Purchase Returns loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'return_count' => (int)($summary['return_count'] ?? 0),
            'return_total' => (float)($summary['return_total'] ?? 0),
            'posted_count' => (int)($summary['posted_count'] ?? 0),
            'draft_count' => (int)($summary['draft_count'] ?? 0),
        ],
        'allowed_actions' => $access['actions'],
        'form_actions' => $formActions,
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    $isUpdate = $method === 'PUT';
    $access = require_permission(
        'purchase-return-form.php',
        $isUpdate ? ACTION_UPDATE : ACTION_CREATE
    );
    $context = pr_tenant_context($access['user']);
    $data = request_data();
    $return = pr_save($access, $context, $data, $isUpdate);

    json_success(
        (int)$return['posting_status'] === 1
            ? 'Purchase Return posted successfully.'
            : ($isUpdate ? 'Purchase Return draft updated successfully.' : 'Purchase Return draft saved successfully.'),
        ['purchase_return' => $return],
        $isUpdate ? 200 : 201
    );
}

json_error('Method not allowed.', 405);
