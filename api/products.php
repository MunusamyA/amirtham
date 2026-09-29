<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Product integer codes
|--------------------------------------------------------------------------
| product_type       : 1=Raw Material, 2=Finished Product, 3=Consumable
| purchase_tax_type  : 1=Inclusive, 2=Exclusive
| sale_tax_type      : 1=Inclusive, 2=Exclusive
| seller_type_id     : Seller Type Master ID for product pricing
| track_batch        : 0=No, 1=Yes
| track_expiry       : 0=No, 1=Yes
| status             : 0=Inactive, 1=Active
|--------------------------------------------------------------------------
*/

function product_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Product management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         WHERE b.id = :branch_id
           AND b.status = 1
           AND c.status = 1
         LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $context = $stmt->fetch();

    if (!$context) {
        json_error('Your assigned tenant branch is invalid or inactive.', 403);
    }

    return [
        'branch_id' => (int)$context['branch_id'],
        'company_id' => (int)$context['company_id'],
        'branch_name' => (string)$context['branch_name'],
        'company_name' => (string)$context['company_name'],
    ];
}

function product_require_schema(): void
{
    static $checked = false;
    if ($checked) return;

    foreach (['food_units', 'food_seller_types', 'food_product_sale_prices'] as $tableName) {
        $tableStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $tableStmt->execute([':table_name' => $tableName]);

        if ((int)$tableStmt->fetchColumn() !== 1) {
            json_error(
                'Product database update is required.',
                500,
                ['schema' => 'Run amirtham-product-master-upgrade.sql. Missing table: ' . $tableName]
            );
        }
    }

    $required = [
        'subcategory_id',
        'primary_unit_id',
        'secondary_unit_id',
        'secondary_conversion',
        'hsn_id',
        'enter_mrp',
        'gst_type',
        'final_mrp',
        'purchase_price',
        'purchase_tax_type',
        'sale_price',
        'sale_tax_type',
    ];

    $placeholders = implode(',', array_fill(0, count($required), '?'));

    $stmt = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge(['food_products'], $required));

    $found = array_map('strtolower', array_column($stmt->fetchAll(), 'COLUMN_NAME'));
    $missing = array_values(array_diff($required, $found));

    if ($missing !== []) {
        json_error(
            'Product database update is required.',
            500,
            ['schema' => 'Run amirtham-product-master-upgrade.sql. Missing columns: ' . implode(', ', $missing)]
        );
    }

    $pricingColumn = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME IN (\'seller_type_id\', \'seller_type_name\')'
    );
    $pricingColumn->execute([
        ':table_name' => 'food_product_sale_prices',
    ]);

    $pricingFound = array_map('strtolower', array_column($pricingColumn->fetchAll(), 'COLUMN_NAME'));
    foreach (['seller_type_id', 'seller_type_name'] as $requiredPricingColumn) {
        if (!in_array($requiredPricingColumn, $pricingFound, true)) {
            json_error(
                'Product database update is required.',
                500,
                ['schema' => 'Run amirtham-product-master-upgrade.sql. Missing column: food_product_sale_prices.' . $requiredPricingColumn]
            );
        }
    }

    $checked = true;
}

function product_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT product_code
         FROM food_products
         WHERE branch_id = :branch_id
           AND product_code REGEXP '^PRD[0-9]+$'
         ORDER BY CAST(SUBSTRING(product_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string)($stmt->fetchColumn() ?: '');
    $nextNumber = 1;

    if ($lastCode !== '' && preg_match('/^PRD([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return 'PRD' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function product_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted Product reference is required.', 422, [
            'ref' => 'Product reference is required.',
        ]);
    }

    try {
        return decryptReference(trim($value), 'product');
    } catch (Throwable $exception) {
        json_error('Invalid Product reference.', 422, [
            'ref' => 'Invalid Product reference.',
        ]);
    }

    return 0;
}

function product_clean_decimal(
    $value,
    string $field,
    string $label,
    int $places = 2,
    bool $nullable = false
): ?float {
    if ($value === '' || $value === null) {
        return $nullable ? null : 0.0;
    }

    if (!is_numeric($value)) {
        json_error('Product validation failed.', 422, [
            $field => $label . ' must be numeric.',
        ]);
    }

    $number = round((float)$value, $places);

    if ($number < 0) {
        json_error('Product validation failed.', 422, [
            $field => $label . ' must be zero or greater.',
        ]);
    }

    return $number;
}

function product_clean_enum(
    $value,
    string $field,
    string $label,
    array $allowed
): int {
    if (!is_numeric($value)) {
        json_error('Product validation failed.', 422, [
            $field => 'Select a valid ' . $label . '.',
        ]);
    }

    $number = (int)$value;

    if (!in_array($number, $allowed, true)) {
        json_error('Product validation failed.', 422, [
            $field => 'Select a valid ' . $label . '.',
        ]);
    }

    return $number;
}

function product_type_label(int $value): string
{
    return [
        1 => 'Raw Material',
        2 => 'Finished Product',
        3 => 'Consumable',
    ][$value] ?? 'Unknown';
}

function product_tax_type_label(int $value): string
{
    return [
        1 => 'Inclusive',
        2 => 'Exclusive',
    ][$value] ?? 'Unknown';
}

function product_category_record(
    int $branchId,
    int $categoryId,
    bool $requireActive = true
): array {
    $sql = 'SELECT id, category_code, category_name, status
            FROM food_product_categories
            WHERE id = :id
              AND branch_id = :branch_id
              AND parent_id IS NULL';

    if ($requireActive) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $categoryId,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product validation failed.', 422, [
            'category_id' => $requireActive
                ? 'Select an active Category.'
                : 'Selected Category was not found.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];

    return $row;
}

function product_subcategory_record(
    int $branchId,
    int $subcategoryId,
    int $categoryId,
    bool $requireActive = true
): array {
    $sql = 'SELECT s.id, s.parent_id, s.category_code, s.category_name, s.status,
                   p.category_name AS parent_category_name, p.status AS parent_status
            FROM food_product_categories s
            INNER JOIN food_product_categories p ON p.id = s.parent_id
            WHERE s.id = :id
              AND s.branch_id = :branch_id
              AND s.parent_id = :category_id
              AND p.branch_id = s.branch_id
              AND p.parent_id IS NULL';

    if ($requireActive) {
        $sql .= ' AND s.status = 1 AND p.status = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $subcategoryId,
        ':branch_id' => $branchId,
        ':category_id' => $categoryId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product validation failed.', 422, [
            'subcategory_id' => $requireActive
                ? 'Select an active Subcategory under the selected Category.'
                : 'Selected Subcategory was not found under this Category.',
        ]);
    }

    foreach (['id', 'parent_id', 'status', 'parent_status'] as $key) {
        $row[$key] = (int)$row[$key];
    }

    return $row;
}

function product_hsn_record(int $hsnId, bool $requireActive = true): array
{
    $sql = 'SELECT id, hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
            FROM hsn_master
            WHERE id = :id';

    if ($requireActive) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $hsnId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product validation failed.', 422, [
            'hsn_id' => $requireActive
                ? 'Select an active HSN.'
                : 'Selected HSN was not found.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];

    foreach (['gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate'] as $key) {
        $row[$key] = (float)$row[$key];
    }

    return $row;
}

function product_unit_record(
    int $branchId,
    int $unitId,
    string $field,
    bool $requireActive = true
): array {
    $sql = 'SELECT id, unit_code, unit_name, unit_symbol, status
            FROM food_units
            WHERE id = :id
              AND branch_id = :branch_id';

    if ($requireActive) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $unitId,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product validation failed.', 422, [
            $field => $requireActive
                ? 'Select an active Unit.'
                : 'Selected Unit was not found.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];

    return $row;
}

function product_category_options(int $branchId, int $includeId = 0): array
{
    $sql = 'SELECT id, category_code, category_name, status
            FROM food_product_categories
            WHERE branch_id = :branch_id
              AND parent_id IS NULL
              AND (status = 1';

    $params = [':branch_id' => $branchId];

    if ($includeId > 0) {
        $sql .= ' OR id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY category_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    return $rows;
}

function product_subcategory_options(
    int $branchId,
    int $categoryId,
    int $includeId = 0
): array {
    if ($categoryId < 1) return [];

    $sql = 'SELECT s.id, s.parent_id, s.category_code, s.category_name, s.status
            FROM food_product_categories s
            INNER JOIN food_product_categories p ON p.id = s.parent_id
            WHERE s.branch_id = :branch_id
              AND s.parent_id = :category_id
              AND p.branch_id = s.branch_id
              AND p.parent_id IS NULL
              AND ((s.status = 1 AND p.status = 1)';

    $params = [
        ':branch_id' => $branchId,
        ':category_id' => $categoryId,
    ];

    if ($includeId > 0) {
        $sql .= ' OR s.id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY s.category_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['parent_id'] = (int)$row['parent_id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    return $rows;
}

function product_hsn_options(int $includeId = 0): array
{
    $sql = 'SELECT id, hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
            FROM hsn_master
            WHERE (status = 1';

    $params = [];

    if ($includeId > 0) {
        $sql .= ' OR id = :include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ') ORDER BY hsn_code ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];

        foreach (['gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate'] as $key) {
            $row[$key] = (float)$row[$key];
        }
    }
    unset($row);

    return $rows;
}

function product_unit_options(
    int $branchId,
    int $includePrimaryId = 0,
    int $includeSecondaryId = 0
): array {
    $includeIds = array_values(array_unique(array_filter([
        $includePrimaryId,
        $includeSecondaryId,
    ], static fn($id) => (int)$id > 0)));

    $sql = 'SELECT id, unit_code, unit_name, unit_symbol, status
            FROM food_units
            WHERE branch_id = :branch_id
              AND (status = 1';

    $params = [':branch_id' => $branchId];

    if ($includeIds !== []) {
        $holders = [];

        foreach ($includeIds as $index => $id) {
            $key = ':unit_' . $index;
            $holders[] = $key;
            $params[$key] = (int)$id;
        }

        $sql .= ' OR id IN (' . implode(',', $holders) . ')';
    }

    $sql .= ') ORDER BY unit_name ASC, unit_symbol ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    return $rows;
}



function product_seller_type_record(
    int $branchId,
    int $sellerTypeId,
    bool $requireActive = true
): array {
    $sql = 'SELECT id, seller_type_name, sort_order, status
            FROM food_seller_types
            WHERE id = :id
              AND branch_id = :branch_id';

    if ($requireActive) {
        $sql .= ' AND status = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $sellerTypeId,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product validation failed.', 422, [
            'sale_prices_json' => $requireActive
                ? 'Select an active Seller Type.'
                : 'Selected Seller Type was not found.',
        ]);
    }

    $row['id'] = (int)$row['id'];
    $row['sort_order'] = (int)$row['sort_order'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function product_seller_type_options(int $branchId, array $includeIds = [], bool $includeAll = false): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), static function ($id) {
        return $id > 0;
    })));

    $sql = 'SELECT id, seller_type_name, sort_order, status
            FROM food_seller_types
            WHERE branch_id = :branch_id';
    $params = [':branch_id' => $branchId];

    if (!$includeAll) {
        $sql .= ' AND (status = 1';
        if ($includeIds !== []) {
            $holders = [];
            foreach ($includeIds as $index => $id) {
                $key = ':seller_type_' . $index;
                $holders[] = $key;
                $params[$key] = $id;
            }
            $sql .= ' OR id IN (' . implode(',', $holders) . ')';
        }
        $sql .= ')';
    }

    $sql .= ' ORDER BY sort_order ASC, seller_type_name ASC, id ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['status'] = (int)$row['status'];
    }
    unset($row);

    return $rows;
}

function product_secondary_unit_rate(float $primaryUnitRate, ?float $secondaryConversion): ?float
{
    if ($secondaryConversion === null || $secondaryConversion <= 0) {
        return null;
    }

    // Keep more precision internally. Final line amount should be rounded only after quantity multiplication.
    return round($primaryUnitRate / $secondaryConversion, 6);
}

function product_sale_prices(
    int $branchId,
    int $productId,
    float $purchasePrice,
    ?float $secondaryConversion = null
): array {
    $stmt = db()->prepare(
        'SELECT sp.id, sp.seller_type_id,
                st.seller_type_name, st.status AS seller_type_status,
                sp.markup_type, sp.markup_value, sp.sale_price, sp.status
         FROM food_product_sale_prices sp
         INNER JOIN food_seller_types st
           ON st.id = sp.seller_type_id
          AND st.branch_id = sp.branch_id
         WHERE sp.branch_id = :branch_id
           AND sp.product_id = :product_id
           AND sp.status = 1
         ORDER BY st.sort_order ASC, st.seller_type_name ASC, sp.id ASC'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $salePrice = round((float)$row['sale_price'], 2);
        $profit = round($salePrice - $purchasePrice, 2);
        $profitMargin = $salePrice > 0
            ? round(($profit / $salePrice) * 100, 2)
            : 0.0;

        $rows[] = [
            'id' => (int)$row['id'],
            'seller_type_id' => (int)$row['seller_type_id'],
            'seller_type_name' => (string)$row['seller_type_name'],
            'seller_type_status' => (int)$row['seller_type_status'],
            'markup_type' => (int)$row['markup_type'],
            'markup_value' => (float)$row['markup_value'],
            'sale_price' => $salePrice,
            'secondary_sale_price' => product_secondary_unit_rate($salePrice, $secondaryConversion),
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'status' => (int)$row['status'],
        ];
    }

    return $rows;
}

function product_sale_price_map(int $branchId, array $productIds): array
{
    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static function ($id) {
        return $id > 0;
    })));

    if ($productIds === []) {
        return [];
    }

    $holders = [];
    $params = [':branch_id' => $branchId];

    foreach ($productIds as $index => $id) {
        $key = ':product_' . $index;
        $holders[] = $key;
        $params[$key] = $id;
    }

    $stmt = db()->prepare(
        'SELECT sp.product_id, sp.seller_type_id, st.seller_type_name,
                sp.markup_type, sp.markup_value, sp.sale_price
         FROM food_product_sale_prices sp
         INNER JOIN food_seller_types st
           ON st.id = sp.seller_type_id
          AND st.branch_id = sp.branch_id
         WHERE sp.branch_id = :branch_id
           AND sp.status = 1
           AND sp.product_id IN (' . implode(',', $holders) . ')
         ORDER BY sp.product_id ASC, st.sort_order ASC, st.seller_type_name ASC'
    );
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $productId = (int)$row['product_id'];
        if (!isset($map[$productId])) {
            $map[$productId] = [];
        }

        $map[$productId][] = [
            'seller_type_id' => (int)$row['seller_type_id'],
            'seller_type_name' => (string)$row['seller_type_name'],
            'markup_type' => (int)$row['markup_type'],
            'markup_value' => (float)$row['markup_value'],
            'sale_price' => (float)$row['sale_price'],
        ];
    }

    return $map;
}

function product_parse_sale_prices(
    array $data,
    int $branchId,
    float $purchasePrice,
    float $finalMrp,
    int $existingProductId = 0
): array {
    $raw = $data['sale_prices_json'] ?? ($data['sale_prices'] ?? []);

    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            $raw = [];
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                json_error('Product validation failed.', 422, [
                    'sale_prices_json' => 'Sale Base Pricing data is invalid.',
                ]);
            }
            $raw = $decoded;
        }
    }

    if (!is_array($raw)) {
        json_error('Product validation failed.', 422, [
            'sale_prices_json' => 'Sale Base Pricing data is invalid.',
        ]);
    }

    $rows = [];
    $seen = [];

    foreach (array_values($raw) as $index => $item) {
        if (!is_array($item)) {
            json_error('Product validation failed.', 422, [
                'sale_prices_json' => 'Sale Base Pricing row ' . ($index + 1) . ' is invalid.',
            ]);
        }

        $sellerTypeId = positive_id($item['seller_type_id'] ?? 0);
        if (isset($seen[$sellerTypeId])) {
            json_error('Product validation failed.', 422, [
                'sale_prices_json' => 'The same Seller Type cannot be added twice.',
            ]);
        }
        $seen[$sellerTypeId] = true;

        $sellerType = product_seller_type_record($branchId, $sellerTypeId, false);
        if ((int)$sellerType['status'] !== 1) {
            // An inactive type may be retained only if this product already used it.
            // New products and new associations must always use active Seller Types.
            if ($existingProductId < 1) {
                json_error('Product validation failed.', 422, [
                    'sale_prices_json' => 'Only active Seller Types can be added to a Product.',
                ]);
            }
            $oldLink = db()->prepare(
                'SELECT id FROM food_product_sale_prices
                 WHERE branch_id=:branch_id AND product_id=:product_id
                   AND seller_type_id=:seller_type_id AND status=1 LIMIT 1'
            );
            $oldLink->execute([
                ':branch_id' => $branchId,
                ':product_id' => $existingProductId,
                ':seller_type_id' => $sellerTypeId,
            ]);
            if (!$oldLink->fetchColumn()) {
                json_error('Product validation failed.', 422, [
                    'sale_prices_json' => 'Inactive Seller Type cannot be newly linked to a Product.',
                ]);
            }
        }

        $markupType = product_clean_enum(
            $item['markup_type'] ?? 1,
            'sale_prices_json',
            'Markup Type',
            [1, 2]
        );

        $markupValue = product_clean_decimal(
            $item['markup_value'] ?? 0,
            'sale_prices_json',
            'Markup Value',
            2
        );

        $markupAmount = $markupType === 2
            ? $markupValue
            : round($purchasePrice * $markupValue / 100, 2);

        $salePrice = round($purchasePrice + $markupAmount, 2);

        if ($finalMrp > 0 && $salePrice > $finalMrp + 0.001) {
            json_error('Product validation failed.', 422, [
                'sale_prices_json' => 'Row ' . ($index + 1) . ': Sale Price cannot exceed Final MRP.',
            ]);
        }

        $rows[] = [
            'seller_type_id' => $sellerTypeId,
            'seller_type_name' => (string)$sellerType['seller_type_name'],
            'markup_type' => $markupType,
            'markup_value' => $markupValue,
            'sale_price' => $salePrice,
        ];
    }

    return $rows;
}

function product_legacy_sale_price(array $salePrices): float
{
    foreach ($salePrices as $row) {
        if (strcasecmp((string)($row['seller_type_name'] ?? ''), 'Retail') === 0) {
            return round((float)$row['sale_price'], 2);
        }
    }

    return $salePrices !== []
        ? round((float)$salePrices[0]['sale_price'], 2)
        : 0.0;
}

function product_sync_sale_prices(
    int $branchId,
    int $productId,
    int $userId,
    array $salePrices
): void {
    $delete = db()->prepare(
        'DELETE FROM food_product_sale_prices
         WHERE branch_id = :branch_id
           AND product_id = :product_id'
    );
    $delete->execute([
        ':branch_id' => $branchId,
        ':product_id' => $productId,
    ]);

    if ($salePrices === []) {
        return;
    }

    $insert = db()->prepare(
        'INSERT INTO food_product_sale_prices
         (
            branch_id,
            product_id,
            seller_type_id,
            seller_type_name,
            markup_type,
            markup_value,
            sale_price,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :branch_id,
            :product_id,
            :seller_type_id,
            :seller_type_name,
            :markup_type,
            :markup_value,
            :sale_price,
            1,
            :created_by,
            NOW(),
            NOW()
         )'
    );

    foreach ($salePrices as $row) {
        $insert->execute([
            ':branch_id' => $branchId,
            ':product_id' => $productId,
            ':seller_type_id' => (int)$row['seller_type_id'],
            ':seller_type_name' => (string)$row['seller_type_name'],
            ':markup_type' => (int)$row['markup_type'],
            ':markup_value' => (float)$row['markup_value'],
            ':sale_price' => (float)$row['sale_price'],
            ':created_by' => $userId,
        ]);
    }
}

function product_final_mrp(
    float $enterMrp,
    int $gstType,
    ?array $hsn
): float {
    if ($gstType === 1) {
        return round($enterMrp, 2);
    }

    $gstRate = $hsn ? (float)($hsn['gst_rate'] ?? 0) : 0.0;
    $cessRate = $hsn ? (float)($hsn['cess_rate'] ?? 0) : 0.0;
    return round($enterMrp + ($enterMrp * ($gstRate + $cessRate) / 100), 2);
}

function product_form_options(
    int $branchId,
    int $categoryId = 0,
    int $subcategoryId = 0,
    int $hsnId = 0,
    int $primaryUnitId = 0,
    int $secondaryUnitId = 0,
    array $sellerTypeIncludeIds = []
): array {
    return [
        'categories' => product_category_options($branchId, $categoryId),
        'subcategories' => product_subcategory_options($branchId, $categoryId, $subcategoryId),
        'hsn_codes' => product_hsn_options($hsnId),
        'units' => product_unit_options($branchId, $primaryUnitId, $secondaryUnitId),
        'seller_types' => product_seller_type_options($branchId, $sellerTypeIncludeIds),
    ];
}

function product_record_for_tenant(array $context, int $id): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.branch_id, p.category_id, p.subcategory_id,
                p.product_code, p.product_name, p.product_type,
                p.primary_unit_id, p.secondary_unit_id, p.secondary_conversion,
                p.hsn_id,
                p.enter_mrp, p.gst_type, p.final_mrp,
                p.purchase_price, p.purchase_tax_type,
                p.sale_price, p.sale_tax_type,
                p.reorder_level, p.track_batch, p.track_expiry,
                p.status, p.created_by, p.created_at, p.updated_at,

                c.category_code,
                c.category_name,

                sc.category_code AS subcategory_code,
                sc.category_name AS subcategory_name,

                pu.unit_code AS primary_unit_code,
                pu.unit_name AS primary_unit_name,
                pu.unit_symbol AS primary_unit_symbol,

                su.unit_code AS secondary_unit_code,
                su.unit_name AS secondary_unit_name,
                su.unit_symbol AS secondary_unit_symbol,

                h.hsn_code,
                h.description AS hsn_description,
                h.gst_rate,
                h.cgst_rate,
                h.sgst_rate,
                h.igst_rate,
                h.cess_rate
         FROM food_products p
         INNER JOIN food_product_categories c
           ON c.id = p.category_id
          AND c.branch_id = p.branch_id
          AND c.parent_id IS NULL
         LEFT JOIN food_product_categories sc
           ON sc.id = p.subcategory_id
          AND sc.branch_id = p.branch_id
          AND sc.parent_id = p.category_id
         INNER JOIN food_units pu
           ON pu.id = p.primary_unit_id
          AND pu.branch_id = p.branch_id
         LEFT JOIN food_units su
           ON su.id = p.secondary_unit_id
          AND su.branch_id = p.branch_id
         LEFT JOIN hsn_master h
           ON h.id = p.hsn_id
         WHERE p.id = :id
           AND p.branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$context['branch_id'],
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        json_error('Product was not found.', 404);
    }

    foreach ([
        'id',
        'branch_id',
        'category_id',
        'product_type',
        'primary_unit_id',
        'gst_type',
        'purchase_tax_type',
        'sale_tax_type',
        'track_batch',
        'track_expiry',
        'status',
        'created_by',
    ] as $key) {
        $row[$key] = (int)$row[$key];
    }

    $row['subcategory_id'] = $row['subcategory_id'] === null ? null : (int)$row['subcategory_id'];
    $row['secondary_unit_id'] = $row['secondary_unit_id'] === null ? null : (int)$row['secondary_unit_id'];
    $row['hsn_id'] = $row['hsn_id'] === null ? null : (int)$row['hsn_id'];

    foreach ([
        'secondary_conversion',
        'enter_mrp',
        'final_mrp',
        'purchase_price',
        'sale_price',
        'reorder_level',
        'gst_rate',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'cess_rate',
    ] as $key) {
        $row[$key] = $row[$key] === null ? null : (float)$row[$key];
    }

    $row['product_type_label'] = product_type_label((int)$row['product_type']);
    $row['gst_type_label'] = product_tax_type_label((int)$row['gst_type']);
    $row['purchase_tax_type_label'] = product_tax_type_label((int)$row['purchase_tax_type']);
    $row['sale_tax_type_label'] = product_tax_type_label((int)$row['sale_tax_type']);
    $row['sale_prices'] = product_sale_prices(
        (int)$context['branch_id'],
        (int)$row['id'],
        (float)$row['purchase_price'],
        $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
    );
    $row['secondary_purchase_price'] = product_secondary_unit_rate(
        (float)$row['purchase_price'],
        $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
    );
    $row['secondary_sale_price'] = product_secondary_unit_rate(
        (float)$row['sale_price'],
        $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
    );
    $row['ref'] = encryptReference('product', (int)$row['id']);

    return $row;
}

function product_assert_unique_code(
    int $branchId,
    string $code,
    int $excludeId = 0
): void {
    $sql = 'SELECT id
            FROM food_products
            WHERE branch_id = :branch_id
              AND product_code = :product_code';

    $params = [
        ':branch_id' => $branchId,
        ':product_code' => $code,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('Product Code already exists.', 409, [
            'product_code' => 'This Product Code is already in use.',
        ]);
    }
}

$method = request_method();
product_require_schema();

/*
|--------------------------------------------------------------------------
| Form options
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('product-form.php', ACTION_VIEW);
    $context = product_tenant_context($access['user']);

    json_success('Product form options loaded.', [
        'allowed_actions' => $access['actions'],
        'next_product_code' => product_generate_code((int)$context['branch_id']),
        'options' => product_form_options((int)$context['branch_id']),
    ]);
}

/*
|--------------------------------------------------------------------------
| Subcategory dependency
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && isset($_GET['subcategories'])) {
    $access = require_permission('product-form.php', ACTION_VIEW);
    $context = product_tenant_context($access['user']);
    $branchId = (int)$context['branch_id'];

    $categoryId = isset($_GET['category_id']) ? max(0, (int)$_GET['category_id']) : 0;
    $includeId = isset($_GET['include_subcategory_id'])
        ? max(0, (int)$_GET['include_subcategory_id'])
        : 0;

    if ($categoryId < 1) {
        json_success('Subcategory options loaded.', [
            'subcategories' => [],
        ]);
    }

    if ($includeId > 0) {
        product_category_record($branchId, $categoryId, false);
        product_subcategory_record($branchId, $includeId, $categoryId, false);
    } else {
        product_category_record($branchId, $categoryId, true);
    }

    json_success('Subcategory options loaded.', [
        'subcategories' => product_subcategory_options($branchId, $categoryId, $includeId),
    ]);
}

/*
|--------------------------------------------------------------------------
| Single Product
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('product-form.php', ACTION_VIEW);
    $context = product_tenant_context($access['user']);

    $product = product_record_for_tenant(
        $context,
        product_id_from_reference($_GET['ref'])
    );

    json_success('Product loaded.', [
        'product' => $product,
        'allowed_actions' => $access['actions'],
        'options' => product_form_options(
            (int)$context['branch_id'],
            (int)$product['category_id'],
            (int)($product['subcategory_id'] ?? 0),
            (int)($product['hsn_id'] ?? 0),
            (int)$product['primary_unit_id'],
            (int)($product['secondary_unit_id'] ?? 0),
            array_column($product['sale_prices'] ?? [], 'seller_type_id')
        ),
    ]);
}

/*
|--------------------------------------------------------------------------
| Product list filter options
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && isset($_GET['list_options'])) {
    $access = require_permission('product-list.php', ACTION_VIEW);
    $context = product_tenant_context($access['user']);

    json_success('Product list options loaded.', [
        'categories' => product_category_options((int)$context['branch_id']),
        'allowed_actions' => $access['actions'],
    ]);
}

/*
|--------------------------------------------------------------------------
| DataTable
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && isset($_GET['datatable'])) {
    $listAccess = require_permission('product-list.php', ACTION_VIEW);
    $context = product_tenant_context($listAccess['user']);
    $branchId = (int)$context['branch_id'];

    $formActions = effective_actions_for_menu(
        $listAccess['user'],
        menu_by_path('product-form.php')
    );

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));

    $statusFilter = isset($_GET['status']) && $_GET['status'] !== ''
        ? normalize_status($_GET['status'])
        : null;

    $typeFilter = isset($_GET['product_type']) && $_GET['product_type'] !== ''
        ? (int)$_GET['product_type']
        : 0;

    if (!in_array($typeFilter, [0, 1, 2, 3], true)) {
        $typeFilter = 0;
    }

    $categoryFilter = isset($_GET['category_id']) && $_GET['category_id'] !== ''
        ? max(0, (int)$_GET['category_id'])
        : 0;

    $from = ' FROM food_products p
              INNER JOIN food_product_categories c
                ON c.id = p.category_id
               AND c.branch_id = p.branch_id
               AND c.parent_id IS NULL
              LEFT JOIN food_product_categories sc
                ON sc.id = p.subcategory_id
               AND sc.branch_id = p.branch_id
               AND sc.parent_id = p.category_id
              INNER JOIN food_units pu
                ON pu.id = p.primary_unit_id
               AND pu.branch_id = p.branch_id
              LEFT JOIN food_units su
                ON su.id = p.secondary_unit_id
               AND su.branch_id = p.branch_id
              LEFT JOIN hsn_master h
                ON h.id = p.hsn_id';

    $where = ['p.branch_id = :branch_id'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $where[] = '(p.product_code LIKE :search_product_code
                     OR p.product_name LIKE :search_product_name
                     OR c.category_name LIKE :search_category
                     OR sc.category_name LIKE :search_subcategory
                     OR pu.unit_name LIKE :search_primary_unit_name
                     OR pu.unit_symbol LIKE :search_primary_unit_symbol
                     OR su.unit_name LIKE :search_secondary_unit_name
                     OR su.unit_symbol LIKE :search_secondary_unit_symbol
                     OR h.hsn_code LIKE :search_hsn_code)';

        $searchValue = '%' . $search . '%';
        $params[':search_product_code'] = $searchValue;
        $params[':search_product_name'] = $searchValue;
        $params[':search_category'] = $searchValue;
        $params[':search_subcategory'] = $searchValue;
        $params[':search_primary_unit_name'] = $searchValue;
        $params[':search_primary_unit_symbol'] = $searchValue;
        $params[':search_secondary_unit_name'] = $searchValue;
        $params[':search_secondary_unit_symbol'] = $searchValue;
        $params[':search_hsn_code'] = $searchValue;
    }

    if ($statusFilter !== null) {
        $where[] = 'p.status = :status';
        $params[':status'] = $statusFilter;
    }

    if ($typeFilter > 0) {
        $where[] = 'p.product_type = :product_type';
        $params[':product_type'] = $typeFilter;
    }

    if ($categoryFilter > 0) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryFilter;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM food_products
         WHERE branch_id = :branch_id'
    );
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)' . $from . '
         WHERE ' . implode(' AND ', $where)
    );

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status', ':product_type', ':category_id'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $countStmt->execute();
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END), 0) AS active_products,
            COALESCE(SUM(CASE WHEN p.status = 0 THEN 1 ELSE 0 END), 0) AS inactive_products
         ' . $from . '
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status', ':product_type', ':category_id'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        0 => 'p.product_code',
        1 => 'p.product_name',
        2 => 'c.category_name',
        3 => 'sc.category_name',
        4 => 'p.product_type',
        5 => 'pu.unit_name',
        6 => 'p.final_mrp',
        7 => 'p.purchase_price',
        8 => 'p.sale_price',
        9 => 'p.status',
    ];

    $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDirection = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
        ? 'DESC'
        : 'ASC';

    $orderBy = $orderColumns[$orderColumn] ?? 'p.product_code';

    $sql = 'SELECT
                p.id,
                p.product_code,
                p.product_name,
                p.product_type,
                p.secondary_conversion,
                p.enter_mrp,
                p.gst_type,
                p.final_mrp,
                p.purchase_price,
                p.purchase_tax_type,
                p.sale_price,
                p.sale_tax_type,
                p.reorder_level,
                p.track_batch,
                p.track_expiry,
                p.status,

                c.category_name,
                sc.category_name AS subcategory_name,

                pu.unit_name AS primary_unit_name,
                pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,
                su.unit_symbol AS secondary_unit_symbol,

                h.hsn_code,
                h.gst_rate,
                h.cess_rate
            ' . $from . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . ' ' . $orderDirection . ', p.id ASC
            LIMIT :start, :length';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status', ':product_type', ':category_id'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rawRows = $stmt->fetchAll();
    $productIds = array_map(function ($row) {
        return (int)$row['id'];
    }, $rawRows);
    $salePriceMap = product_sale_price_map($branchId, $productIds);

    $rows = [];

    foreach ($rawRows as $row) {
        $id = (int)$row['id'];
        $productType = (int)$row['product_type'];
        $gstType = (int)$row['gst_type'];
        $purchaseTaxType = (int)$row['purchase_tax_type'];
        $saleTaxType = (int)$row['sale_tax_type'];
        $purchasePrice = (float)$row['purchase_price'];

        $salePrices = [];
        foreach ($salePriceMap[$id] ?? [] as $priceRow) {
            $salePrice = round((float)$priceRow['sale_price'], 2);
            $profit = round($salePrice - $purchasePrice, 2);
            $profitMargin = $salePrice > 0
                ? round(($profit / $salePrice) * 100, 2)
                : 0.0;

            $priceRow['secondary_sale_price'] = product_secondary_unit_rate(
                $salePrice,
                $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
            );
            $priceRow['profit'] = $profit;
            $priceRow['profit_margin'] = $profitMargin;
            $salePrices[] = $priceRow;
        }

        $rows[] = [
            'id' => $id,
            'ref' => encryptReference('product', $id),
            'edit_url' => 'product-form.php?ref=' . rawurlencode(encryptReference('product', $id)),
            'product_code' => (string)$row['product_code'],
            'product_name' => (string)$row['product_name'],
            'category_name' => (string)$row['category_name'],
            'subcategory_name' => $row['subcategory_name'],
            'product_type' => $productType,
            'product_type_label' => product_type_label($productType),
            'primary_unit_name' => (string)$row['primary_unit_name'],
            'primary_unit_symbol' => (string)$row['primary_unit_symbol'],
            'secondary_unit_name' => $row['secondary_unit_name'],
            'secondary_unit_symbol' => $row['secondary_unit_symbol'],
            'secondary_conversion' => $row['secondary_conversion'] === null
                ? null
                : (float)$row['secondary_conversion'],
            'hsn_code' => $row['hsn_code'],
            'gst_rate' => $row['gst_rate'] === null ? 0.0 : (float)$row['gst_rate'],
            'cess_rate' => $row['cess_rate'] === null ? 0.0 : (float)$row['cess_rate'],
            'enter_mrp' => (float)$row['enter_mrp'],
            'gst_type' => $gstType,
            'gst_type_label' => product_tax_type_label($gstType),
            'final_mrp' => (float)$row['final_mrp'],
            'purchase_price' => $purchasePrice,
            'purchase_tax_type' => $purchaseTaxType,
            'purchase_tax_type_label' => product_tax_type_label($purchaseTaxType),
            'sale_price' => (float)$row['sale_price'],
            'sale_tax_type' => $saleTaxType,
            'sale_tax_type_label' => product_tax_type_label($saleTaxType),
            'secondary_purchase_price' => product_secondary_unit_rate(
                $purchasePrice,
                $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
            ),
            'secondary_sale_price' => product_secondary_unit_rate(
                (float)$row['sale_price'],
                $row['secondary_conversion'] === null ? null : (float)$row['secondary_conversion']
            ),
            'sale_prices' => $salePrices,
            'reorder_level' => (float)$row['reorder_level'],
            'track_batch' => (int)$row['track_batch'],
            'track_expiry' => (int)$row['track_expiry'],
            'status' => (int)$row['status'],
        ];
    }

    json_success('Product records loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'total_products' => (int)($summary['total_products'] ?? 0),
            'active_products' => (int)($summary['active_products'] ?? 0),
            'inactive_products' => (int)($summary['inactive_products'] ?? 0),
        ],
        'list_actions' => $listAccess['actions'],
        'form_actions' => $formActions,
    ]);
}

/*
|--------------------------------------------------------------------------
| Activate / Deactivate
|--------------------------------------------------------------------------
*/
if ($method === 'PATCH') {
    $data = request_data();

    require_fields($data, [
        'ref' => 'Product reference is required.',
        'action' => 'Product status action is required.',
    ]);

    $action = strtolower(trim((string)$data['action']));

    if (!in_array($action, ['activate', 'deactivate'], true)) {
        json_error('Invalid Product status action.', 422);
    }

    $newStatus = $action === 'activate' ? 1 : 0;
    $permission = $newStatus === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE;

    $access = require_permission('product-list.php', $permission);
    $user = $access['user'];
    $context = product_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    $productId = product_id_from_reference($data['ref']);
    $old = product_record_for_tenant($context, $productId);

    db()->prepare(
        'UPDATE food_products
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND branch_id = :branch_id'
    )->execute([
        ':status' => $newStatus,
        ':id' => $productId,
        ':branch_id' => $branchId,
    ]);

    audit_log((int)$user['id'], $permission, [
        'company_id' => (int)$context['company_id'],
        'branch_id' => $branchId,
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $productId,
        'old_data' => $old,
    ]);

    json_success($newStatus === 1
        ? 'Product activated successfully.'
        : 'Product deactivated successfully.'
    );
}

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/
if ($method === 'POST') {
    $access = require_permission('product-form.php', ACTION_CREATE);
    $user = $access['user'];
    $context = product_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, [
        'product_name' => 'Product Name is required.',
        'product_type' => 'Product Type is required.',
        'category_id' => 'Category is required.',
        'primary_unit_id' => 'Primary Unit is required.',
        'enter_mrp' => 'Enter MRP is required.',
        'gst_type' => 'GST Type is required.',
        'purchase_price' => 'Purchase Price / Stock Price is required.',
    ]);

    $productName = preg_replace('/\s+/', ' ', trim((string)$data['product_name']));

    if ($productName === '' || strlen($productName) > 180) {
        json_error('Product validation failed.', 422, [
            'product_name' => 'Product Name must be within 180 characters.',
        ]);
    }

    $productType = product_clean_enum(
        $data['product_type'],
        'product_type',
        'Product Type',
        [1, 2, 3]
    );

    $categoryId = positive_id($data['category_id']);
    product_category_record($branchId, $categoryId, true);

    $subcategoryId = isset($data['subcategory_id']) && (int)$data['subcategory_id'] > 0
        ? positive_id($data['subcategory_id'])
        : 0;

    if ($subcategoryId > 0) {
        product_subcategory_record($branchId, $subcategoryId, $categoryId, true);
    }

    $primaryUnitId = positive_id($data['primary_unit_id']);
    product_unit_record($branchId, $primaryUnitId, 'primary_unit_id', true);

    $secondaryUnitId = isset($data['secondary_unit_id']) && (int)$data['secondary_unit_id'] > 0
        ? positive_id($data['secondary_unit_id'])
        : 0;

    $secondaryConversion = null;

    if ($secondaryUnitId > 0) {
        product_unit_record($branchId, $secondaryUnitId, 'secondary_unit_id', true);

        if ($secondaryUnitId === $primaryUnitId) {
            json_error('Product validation failed.', 422, [
                'secondary_unit_id' => 'Secondary Unit must be different from Primary Unit.',
            ]);
        }

        $secondaryConversion = product_clean_decimal(
            $data['secondary_conversion'] ?? '',
            'secondary_conversion',
            'Conversion Qty',
            3,
            true
        );

        if ($secondaryConversion === null || $secondaryConversion <= 0) {
            json_error('Product validation failed.', 422, [
                'secondary_conversion' => 'Conversion Qty must be greater than zero.',
            ]);
        }
    }

    $hsnId = isset($data['hsn_id']) && (int)$data['hsn_id'] > 0
        ? positive_id($data['hsn_id'])
        : 0;

    $hsn = $hsnId > 0
        ? product_hsn_record($hsnId, true)
        : null;

    $enterMrp = product_clean_decimal(
        $data['enter_mrp'],
        'enter_mrp',
        'Enter MRP',
        2
    );

    if ($enterMrp <= 0) {
        json_error('Product validation failed.', 422, [
            'enter_mrp' => 'Enter MRP must be greater than zero.',
        ]);
    }

    $gstType = product_clean_enum(
        $data['gst_type'],
        'gst_type',
        'GST Type',
        [1, 2]
    );

    $finalMrp = product_final_mrp($enterMrp, $gstType, $hsn);

    $purchasePrice = product_clean_decimal(
        $data['purchase_price'],
        'purchase_price',
        'Purchase Price / Stock Price',
        2
    );

    if ($purchasePrice <= 0) {
        json_error('Product validation failed.', 422, [
            'purchase_price' => 'Purchase Price / Stock Price must be greater than zero.',
        ]);
    }

    if ($finalMrp > 0 && $purchasePrice > $finalMrp + 0.001) {
        json_error('Product validation failed.', 422, [
            'purchase_price' => 'Purchase Price / Stock Price cannot exceed Final MRP.',
        ]);
    }

    $salePrices = product_parse_sale_prices(
        $data,
        $branchId,
        $purchasePrice,
        $finalMrp
    );

    /*
     * Backward compatibility:
     * food_products.sale_price is kept as one fallback price.
     * Seller Type named Retail is preferred; otherwise the first seller price is used.
     */
    $salePrice = product_legacy_sale_price($salePrices);
    $purchaseTaxType = $gstType;
    $saleTaxType = $gstType;

    $reorderLevel = product_clean_decimal(
        $data['reorder_level'] ?? 0,
        'reorder_level',
        'Reorder Level',
        3
    );

    $trackBatch = isset($data['track_batch'])
        ? normalize_status($data['track_batch'])
        : 1;

    $trackExpiry = $trackBatch === 1 && isset($data['track_expiry'])
        ? normalize_status($data['track_expiry'])
        : 0;

    $status = isset($data['status'])
        ? normalize_status($data['status'])
        : 1;

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $productCode = product_generate_code($branchId);
        product_assert_unique_code($branchId, $productCode);

        $stmt = $pdo->prepare(
            'INSERT INTO food_products
             (
                branch_id,
                category_id,
                subcategory_id,
                product_code,
                product_name,
                product_type,
                primary_unit_id,
                secondary_unit_id,
                secondary_conversion,
                hsn_id,
                enter_mrp,
                gst_type,
                final_mrp,
                purchase_price,
                purchase_tax_type,
                sale_price,
                sale_tax_type,
                reorder_level,
                track_batch,
                track_expiry,
                status,
                created_by,
                created_at,
                updated_at
             )
             VALUES
             (
                :branch_id,
                :category_id,
                :subcategory_id,
                :product_code,
                :product_name,
                :product_type,
                :primary_unit_id,
                :secondary_unit_id,
                :secondary_conversion,
                :hsn_id,
                :enter_mrp,
                :gst_type,
                :final_mrp,
                :purchase_price,
                :purchase_tax_type,
                :sale_price,
                :sale_tax_type,
                :reorder_level,
                :track_batch,
                :track_expiry,
                :status,
                :created_by,
                NOW(),
                NOW()
             )'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':category_id' => $categoryId,
            ':subcategory_id' => $subcategoryId > 0 ? $subcategoryId : null,
            ':product_code' => $productCode,
            ':product_name' => $productName,
            ':product_type' => $productType,
            ':primary_unit_id' => $primaryUnitId,
            ':secondary_unit_id' => $secondaryUnitId > 0 ? $secondaryUnitId : null,
            ':secondary_conversion' => $secondaryConversion,
            ':hsn_id' => $hsnId > 0 ? $hsnId : null,
            ':enter_mrp' => $enterMrp,
            ':gst_type' => $gstType,
            ':final_mrp' => $finalMrp,
            ':purchase_price' => $purchasePrice,
            ':purchase_tax_type' => $purchaseTaxType,
            ':sale_price' => $salePrice,
            ':sale_tax_type' => $saleTaxType,
            ':reorder_level' => $reorderLevel,
            ':track_batch' => $trackBatch,
            ':track_expiry' => $trackExpiry,
            ':status' => $status,
            ':created_by' => (int)$user['id'],
        ]);

        $productId = (int)$pdo->lastInsertId();

        product_sync_sale_prices(
            $branchId,
            $productId,
            (int)$user['id'],
            $salePrices
        );

        $pdo->commit();

        audit_log((int)$user['id'], ACTION_CREATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $productId,
        ]);

        json_success('Product created successfully.', [
            'ref' => encryptReference('product', $productId),
            'product_code' => $productCode,
        ], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Product create conflicts with an existing record.', 409);
        }

        throw $exception;
    }
}

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/
if ($method === 'PUT') {
    $access = require_permission('product-form.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = product_tenant_context($user);
    $branchId = (int)$context['branch_id'];
    $data = request_data();

    require_fields($data, [
        'ref' => 'Product reference is required.',
        'product_name' => 'Product Name is required.',
        'product_type' => 'Product Type is required.',
        'category_id' => 'Category is required.',
        'primary_unit_id' => 'Primary Unit is required.',
        'enter_mrp' => 'Enter MRP is required.',
        'gst_type' => 'GST Type is required.',
        'purchase_price' => 'Purchase Price / Stock Price is required.',
    ]);

    $productId = product_id_from_reference($data['ref']);
    $old = product_record_for_tenant($context, $productId);

    $productName = preg_replace('/\s+/', ' ', trim((string)$data['product_name']));

    if ($productName === '' || strlen($productName) > 180) {
        json_error('Product validation failed.', 422, [
            'product_name' => 'Product Name must be within 180 characters.',
        ]);
    }

    $productType = product_clean_enum(
        $data['product_type'],
        'product_type',
        'Product Type',
        [1, 2, 3]
    );

    $categoryId = positive_id($data['category_id']);
    $categoryChanged = $categoryId !== (int)$old['category_id'];
    product_category_record($branchId, $categoryId, $categoryChanged);

    $subcategoryId = isset($data['subcategory_id']) && (int)$data['subcategory_id'] > 0
        ? positive_id($data['subcategory_id'])
        : 0;

    if ($subcategoryId > 0) {
        $subcategoryChanged = $subcategoryId !== (int)($old['subcategory_id'] ?? 0) || $categoryChanged;
        product_subcategory_record($branchId, $subcategoryId, $categoryId, $subcategoryChanged);
    }

    $primaryUnitId = positive_id($data['primary_unit_id']);
    $primaryChanged = $primaryUnitId !== (int)$old['primary_unit_id'];
    product_unit_record($branchId, $primaryUnitId, 'primary_unit_id', $primaryChanged);

    $secondaryUnitId = isset($data['secondary_unit_id']) && (int)$data['secondary_unit_id'] > 0
        ? positive_id($data['secondary_unit_id'])
        : 0;

    $secondaryConversion = null;

    if ($secondaryUnitId > 0) {
        $secondaryChanged = $secondaryUnitId !== (int)($old['secondary_unit_id'] ?? 0);
        product_unit_record($branchId, $secondaryUnitId, 'secondary_unit_id', $secondaryChanged);

        if ($secondaryUnitId === $primaryUnitId) {
            json_error('Product validation failed.', 422, [
                'secondary_unit_id' => 'Secondary Unit must be different from Primary Unit.',
            ]);
        }

        $secondaryConversion = product_clean_decimal(
            $data['secondary_conversion'] ?? '',
            'secondary_conversion',
            'Conversion Qty',
            3,
            true
        );

        if ($secondaryConversion === null || $secondaryConversion <= 0) {
            json_error('Product validation failed.', 422, [
                'secondary_conversion' => 'Conversion Qty must be greater than zero.',
            ]);
        }
    }

    $hsnId = isset($data['hsn_id']) && (int)$data['hsn_id'] > 0
        ? positive_id($data['hsn_id'])
        : 0;

    $hsn = null;
    if ($hsnId > 0) {
        $hsnChanged = $hsnId !== (int)($old['hsn_id'] ?? 0);
        $hsn = product_hsn_record($hsnId, $hsnChanged);
    }

    $enterMrp = product_clean_decimal(
        $data['enter_mrp'],
        'enter_mrp',
        'Enter MRP',
        2
    );

    if ($enterMrp <= 0) {
        json_error('Product validation failed.', 422, [
            'enter_mrp' => 'Enter MRP must be greater than zero.',
        ]);
    }

    $gstType = product_clean_enum(
        $data['gst_type'],
        'gst_type',
        'GST Type',
        [1, 2]
    );

    $finalMrp = product_final_mrp($enterMrp, $gstType, $hsn);

    $purchasePrice = product_clean_decimal(
        $data['purchase_price'],
        'purchase_price',
        'Purchase Price / Stock Price',
        2
    );

    if ($purchasePrice <= 0) {
        json_error('Product validation failed.', 422, [
            'purchase_price' => 'Purchase Price / Stock Price must be greater than zero.',
        ]);
    }

    if ($finalMrp > 0 && $purchasePrice > $finalMrp + 0.001) {
        json_error('Product validation failed.', 422, [
            'purchase_price' => 'Purchase Price / Stock Price cannot exceed Final MRP.',
        ]);
    }

    $salePrices = product_parse_sale_prices(
        $data,
        $branchId,
        $purchasePrice,
        $finalMrp,
        $productId
    );

    $salePrice = product_legacy_sale_price($salePrices);
    $purchaseTaxType = $gstType;
    $saleTaxType = $gstType;

    $reorderLevel = product_clean_decimal(
        $data['reorder_level'] ?? 0,
        'reorder_level',
        'Reorder Level',
        3
    );

    $trackBatch = isset($data['track_batch'])
        ? normalize_status($data['track_batch'])
        : (int)$old['track_batch'];

    $trackExpiry = $trackBatch === 1 && isset($data['track_expiry'])
        ? normalize_status($data['track_expiry'])
        : 0;

    $status = isset($data['status'])
        ? normalize_status($data['status'])
        : (int)$old['status'];

    product_assert_unique_code(
        $branchId,
        (string)$old['product_code'],
        $productId
    );

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE food_products
             SET category_id = :category_id,
                 subcategory_id = :subcategory_id,
                 product_name = :product_name,
                 product_type = :product_type,
                 primary_unit_id = :primary_unit_id,
                 secondary_unit_id = :secondary_unit_id,
                 secondary_conversion = :secondary_conversion,
                 hsn_id = :hsn_id,
                 enter_mrp = :enter_mrp,
                 gst_type = :gst_type,
                 final_mrp = :final_mrp,
                 purchase_price = :purchase_price,
                 purchase_tax_type = :purchase_tax_type,
                 sale_price = :sale_price,
                 sale_tax_type = :sale_tax_type,
                 reorder_level = :reorder_level,
                 track_batch = :track_batch,
                 track_expiry = :track_expiry,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );

        $stmt->execute([
            ':category_id' => $categoryId,
            ':subcategory_id' => $subcategoryId > 0 ? $subcategoryId : null,
            ':product_name' => $productName,
            ':product_type' => $productType,
            ':primary_unit_id' => $primaryUnitId,
            ':secondary_unit_id' => $secondaryUnitId > 0 ? $secondaryUnitId : null,
            ':secondary_conversion' => $secondaryConversion,
            ':hsn_id' => $hsnId > 0 ? $hsnId : null,
            ':enter_mrp' => $enterMrp,
            ':gst_type' => $gstType,
            ':final_mrp' => $finalMrp,
            ':purchase_price' => $purchasePrice,
            ':purchase_tax_type' => $purchaseTaxType,
            ':sale_price' => $salePrice,
            ':sale_tax_type' => $saleTaxType,
            ':reorder_level' => $reorderLevel,
            ':track_batch' => $trackBatch,
            ':track_expiry' => $trackExpiry,
            ':status' => $status,
            ':id' => $productId,
            ':branch_id' => $branchId,
        ]);

        product_sync_sale_prices(
            $branchId,
            $productId,
            (int)$user['id'],
            $salePrices
        );

        $pdo->commit();

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'company_id' => (int)$context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $productId,
            'old_data' => $old,
        ]);

        json_success('Product updated successfully.', [
            'ref' => encryptReference('product', $productId),
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Product update conflicts with an existing record.', 409);
        }

        throw $exception;
    }
}

json_error('Method not allowed. Product deletion is not enabled.', 405);
