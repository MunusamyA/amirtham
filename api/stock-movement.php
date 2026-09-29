<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function sm_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Stock Movement is available only for tenant users.', 403);
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

function sm_require_schema(): void
{
    foreach (['food_stock_movements','food_products','food_purchases','food_units'] as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error('Stock Movement schema is incomplete. Missing table: ' . $table . '.', 500);
        }
    }
}

function sm_date($value, string $fallback): string
{
    $value = trim((string)$value);
    if ($value === '') return $fallback;
    $d = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $d->format('Y-m-d') !== $value) {
        json_error('Enter a valid Stock Movement date.', 422);
    }
    return $value;
}

function sm_products(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT p.id,p.product_code,p.product_name,p.status,
                pu.unit_symbol AS primary_unit_symbol,
                su.unit_symbol AS secondary_unit_symbol
         FROM food_products p
         LEFT JOIN food_units pu ON pu.id=p.primary_unit_id AND pu.branch_id=p.branch_id
         LEFT JOIN food_units su ON su.id=p.secondary_unit_id AND su.branch_id=p.branch_id
         WHERE p.branch_id=:branch_id
         ORDER BY p.product_name,p.product_code'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'product_code' => (string)$r['product_code'],
            'product_name' => (string)$r['product_name'],
            'status' => (int)$r['status'],
            'unit_symbol' => (string)($r['secondary_unit_symbol'] ?: $r['primary_unit_symbol'] ?: ''),
        ];
    }
    return $rows;
}

function sm_batches(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.id AS source_purchase_id,p.purchase_no,p.batch_number,p.purchase_date,
                pi.product_id,pr.product_code,pr.product_name
         FROM food_purchases p
         INNER JOIN food_purchase_items pi ON pi.purchase_id=p.id AND pi.branch_id=p.branch_id AND pi.status=1
         INNER JOIN food_products pr ON pr.id=pi.product_id AND pr.branch_id=pi.branch_id
         WHERE p.branch_id=:branch_id
           AND p.posting_status=1
           AND p.status=1
           AND p.reversed_at IS NULL
         ORDER BY p.purchase_date DESC,p.id DESC,pr.product_name'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'source_purchase_id' => (int)$r['source_purchase_id'],
            'product_id' => (int)$r['product_id'],
            'purchase_no' => (string)($r['purchase_no'] ?? ''),
            'batch_number' => (string)($r['batch_number'] ?? ''),
            'purchase_date' => (string)$r['purchase_date'],
            'product_code' => (string)$r['product_code'],
            'product_name' => (string)$r['product_name'],
        ];
    }
    return $rows;
}

function sm_type_label(int $type): string
{
    return [
        1 => 'Purchase In',
        2 => 'Sale Out',
        3 => 'Purchase Return Out',
        4 => 'Sale Return In',
        5 => 'Adjustment',
    ][$type] ?? 'Movement';
}

function sm_view_url(string $referenceType, int $referenceId): string
{
    $t = strtolower(trim($referenceType));
    try {
        if ($referenceId < 1) return '';
        if ($t === 'purchase' || str_contains($t, 'purchase_in')) {
            return 'purchase-form.php?ref=' . rawurlencode(encryptReference('purchase', $referenceId));
        }
        if ($t === 'sale' || $t === 'sales' || str_contains($t, 'sale_out')) {
            return 'sales.php?ref=' . rawurlencode(encryptReference('sale', $referenceId));
        }
        if (str_contains($t, 'purchase_return')) {
            return 'purchase-return-form.php?ref=' . rawurlencode(encryptReference('purchase_return', $referenceId));
        }
        if (str_contains($t, 'sale_return') || str_contains($t, 'sales_return')) {
            return 'sales-return.php?ref=' . rawurlencode(encryptReference('sale_return', $referenceId));
        }
    } catch (Throwable $e) {
        return '';
    }
    return '';
}

function sm_rows(int $branchId, string $toDate, int $productId, int $sourcePurchaseId): array
{
    $sql = 'SELECT sm.id,sm.product_id,sm.source_purchase_id,sm.batch_id,
                   sm.movement_date,sm.movement_type,sm.reference_type,sm.reference_id,
                   sm.reference_no,sm.quantity_in,sm.quantity_out,sm.conversion_rate,
                   sm.remarks,sm.created_at,
                   pr.product_code,pr.product_name,
                   p.purchase_no,p.batch_number,
                   u.unit_name,u.unit_symbol
            FROM food_stock_movements sm
            INNER JOIN food_products pr ON pr.id=sm.product_id AND pr.branch_id=sm.branch_id
            LEFT JOIN food_purchases p ON p.id=sm.source_purchase_id AND p.branch_id=sm.branch_id
            LEFT JOIN food_units u ON u.id=sm.unit_id AND u.branch_id=sm.branch_id
            WHERE sm.branch_id=:branch_id
              AND sm.status=1
              AND sm.movement_date<=:date_to';
    $params = [':branch_id' => $branchId, ':date_to' => $toDate];
    if ($productId > 0) {
        $sql .= ' AND sm.product_id=:product_id';
        $params[':product_id'] = $productId;
    }
    if ($sourcePurchaseId > 0) {
        $sql .= ' AND sm.source_purchase_id=:source_purchase_id';
        $params[':source_purchase_id'] = $sourcePurchaseId;
    }
    $sql .= ' ORDER BY sm.movement_date ASC,sm.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$method = request_method();
if ($method !== 'GET') json_error('Method not allowed.', 405);
sm_require_schema();

$access = require_permission('stock-movement.php', ACTION_VIEW);
$ctx = sm_tenant_context($access['user']);
$branchId = (int)$ctx['branch_id'];

if (isset($_GET['options'])) {
    json_success('Stock Movement options loaded.', [
        'allowed_actions' => $access['actions'],
        'products' => sm_products($branchId),
        'batches' => sm_batches($branchId),
        'movement_types' => [
            ['value' => '', 'label' => 'All Movements'],
            ['value' => 1, 'label' => 'Purchase In'],
            ['value' => 2, 'label' => 'Sale Out'],
            ['value' => 3, 'label' => 'Purchase Return Out'],
            ['value' => 4, 'label' => 'Sale Return In'],
            ['value' => 5, 'label' => 'Adjustment'],
        ],
        'date_from' => date('Y-m-01'),
        'date_to' => date('Y-m-d'),
        'branch' => $ctx,
    ]);
}

$fromDate = sm_date($_GET['date_from'] ?? '', date('Y-m-01'));
$toDate = sm_date($_GET['date_to'] ?? '', date('Y-m-d'));
if ($toDate < $fromDate) json_error('To Date cannot be earlier than From Date.', 422);
$productId = max(0, (int)($_GET['product_id'] ?? 0));
$sourcePurchaseId = max(0, (int)($_GET['source_purchase_id'] ?? 0));
$movementType = max(0, (int)($_GET['movement_type'] ?? 0));
if ($movementType > 5) $movementType = 0;
$search = trim((string)($_GET['search']['value'] ?? $_GET['search'] ?? ''));
$start = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 500) $length = 25;
$draw = max(0, (int)($_GET['draw'] ?? 0));

$all = sm_rows($branchId, $toDate, $productId, $sourcePurchaseId);
$balances = [];
$periodAll = [];
$units = [];
$openingSingle = 0.0;
$periodInSingle = 0.0;
$periodOutSingle = 0.0;
$typeCounts = [1=>0,2=>0,3=>0,4=>0,5=>0];

foreach ($all as $r) {
    $key = (int)$r['product_id'] . '|' . (int)($r['source_purchase_id'] ?? 0) . '|' . (int)($r['batch_id'] ?? 0);
    if (!array_key_exists($key, $balances)) $balances[$key] = 0.0;
    $in = (float)$r['quantity_in'];
    $out = (float)$r['quantity_out'];
    $date = (string)$r['movement_date'];
    if ($date < $fromDate) {
        $balances[$key] += $in - $out;
        if ($productId > 0) $openingSingle += $in - $out;
        continue;
    }
    $balances[$key] += $in - $out;
    if ($productId > 0) {
        $periodInSingle += $in;
        $periodOutSingle += $out;
    }
    $type = (int)$r['movement_type'];
    if (isset($typeCounts[$type])) $typeCounts[$type]++;
    $unit = trim((string)($r['unit_symbol'] ?: $r['unit_name'] ?: ''));
    if ($unit !== '') $units[$unit] = true;
    $productText = trim((string)$r['product_code']) !== '' ? $r['product_code'] . ' - ' . $r['product_name'] : $r['product_name'];
    $batchText = trim((string)($r['batch_number'] ?? ''));
    if ($batchText === '') $batchText = trim((string)($r['purchase_no'] ?? ''));
    if ($batchText === '') $batchText = 'Direct / No Batch';
    $row = [
        'id' => (int)$r['id'],
        'movement_date' => $date,
        'created_at' => (string)$r['created_at'],
        'product_id' => (int)$r['product_id'],
        'product' => $productText,
        'product_code' => (string)$r['product_code'],
        'product_name' => (string)$r['product_name'],
        'source_purchase_id' => (int)($r['source_purchase_id'] ?? 0),
        'batch' => $batchText,
        'purchase_no' => (string)($r['purchase_no'] ?? ''),
        'movement_type' => $type,
        'movement_type_label' => sm_type_label($type),
        'reference' => (string)($r['reference_no'] ?: strtoupper((string)$r['reference_type']) . '#' . (int)$r['reference_id']),
        'reference_type' => (string)$r['reference_type'],
        'quantity_in' => round($in, 3),
        'quantity_out' => round($out, 3),
        'balance' => round($balances[$key], 3),
        'unit' => $unit,
        'remarks' => (string)($r['remarks'] ?? ''),
        'view_url' => sm_view_url((string)$r['reference_type'], (int)$r['reference_id']),
    ];
    $periodAll[] = $row;
}

$recordsTotal = count($periodAll);
$filtered = array_values(array_filter($periodAll, static function(array $r) use ($movementType, $search): bool {
    if ($movementType > 0 && (int)$r['movement_type'] !== $movementType) return false;
    if ($search !== '') {
        $hay = strtolower(implode(' ', [
            $r['movement_date'],$r['product'],$r['batch'],$r['movement_type_label'],
            $r['reference'],$r['reference_type'],$r['remarks'],$r['unit']
        ]));
        if (strpos($hay, strtolower($search)) === false) return false;
    }
    return true;
}));
$recordsFiltered = count($filtered);

$columns = ['movement_date','product','batch','movement_type_label','reference','quantity_in','quantity_out','balance','unit','remarks'];
$orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$orderKey = $columns[$orderIndex] ?? 'movement_date';
usort($filtered, static function(array $a, array $b) use ($orderKey, $orderDir): int {
    $av = $a[$orderKey] ?? '';
    $bv = $b[$orderKey] ?? '';
    if (is_numeric($av) && is_numeric($bv)) $cmp = (float)$av <=> (float)$bv;
    else $cmp = strnatcasecmp((string)$av, (string)$bv);
    if ($cmp === 0) $cmp = ((int)$a['id']) <=> ((int)$b['id']);
    return $orderDir === 'asc' ? $cmp : -$cmp;
});
$page = array_slice($filtered, $start, $length);

$unitLabel = count($units) === 1 ? (string)array_key_first($units) : 'Base Qty';
$closingSingle = $openingSingle + $periodInSingle - $periodOutSingle;

json_success('Stock Movement loaded.', [
    'allowed_actions' => $access['actions'],
    'datatable' => [
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $page,
    ],
    'summary' => [
        'single_product' => $productId > 0,
        'unit' => $unitLabel,
        'opening_qty' => round($openingSingle, 3),
        'quantity_in' => round($periodInSingle, 3),
        'quantity_out' => round($periodOutSingle, 3),
        'closing_qty' => round($closingSingle, 3),
        'movement_count' => $recordsTotal,
        'purchase_in_count' => $typeCounts[1],
        'sale_out_count' => $typeCounts[2],
        'purchase_return_count' => $typeCounts[3],
        'sale_return_count' => $typeCounts[4],
        'adjustment_count' => $typeCounts[5],
    ],
]);
