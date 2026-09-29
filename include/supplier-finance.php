<?php
declare(strict_types=1);

/**
 * Shared Supplier accounting helpers.
 * Source of truth:
 *   Purchase liability         = posted food_purchases.grand_total
 *   Purchase Return reduction = posted, active, non-reversed food_purchase_returns
 *   Purchase-time payment      = active food_purchase_payments
 *   Later supplier settlement = active allocation rows of posted supplier payments
 */

function sf_purchase_return_total(PDO $pdo, int $branchId, int $purchaseId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(grand_total),0)
         FROM food_purchase_returns
         WHERE branch_id=:branch_id
           AND purchase_id=:purchase_id
           AND posting_status=1
           AND status=1
           AND reversed_at IS NULL'
    );
    $stmt->execute([':branch_id'=>$branchId, ':purchase_id'=>$purchaseId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function sf_purchase_direct_payment_total(PDO $pdo, int $branchId, int $purchaseId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0)
         FROM food_purchase_payments
         WHERE branch_id=:branch_id
           AND purchase_id=:purchase_id
           AND status=1'
    );
    $stmt->execute([':branch_id'=>$branchId, ':purchase_id'=>$purchaseId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function sf_supplier_payment_allocation_total(
    PDO $pdo,
    int $branchId,
    int $purchaseId,
    int $excludeSupplierPaymentId = 0
): float {
    $sql = 'SELECT COALESCE(SUM(a.allocated_amount),0)
            FROM food_supplier_payment_allocations a
            INNER JOIN food_supplier_payments p
                    ON p.id=a.supplier_payment_id
                   AND p.branch_id=a.branch_id
            WHERE a.branch_id=:branch_id
              AND a.purchase_id=:purchase_id
              AND a.allocation_type=1
              AND a.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL';
    $params = [':branch_id'=>$branchId, ':purchase_id'=>$purchaseId];
    if ($excludeSupplierPaymentId > 0) {
        $sql .= ' AND a.supplier_payment_id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludeSupplierPaymentId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function sf_purchase_snapshot(
    PDO $pdo,
    int $branchId,
    int $purchaseId,
    int $excludeSupplierPaymentId = 0
): array {
    $stmt = $pdo->prepare(
        'SELECT id,supplier_id,purchase_no,purchase_date,grand_total,posting_status,status
         FROM food_purchases
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id'=>$purchaseId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [];
    }

    $grand = round((float)$row['grand_total'], 2);
    $returns = sf_purchase_return_total($pdo, $branchId, $purchaseId);
    $net = max(0.0, round($grand - $returns, 2));
    $direct = sf_purchase_direct_payment_total($pdo, $branchId, $purchaseId);
    $later = sf_supplier_payment_allocation_total($pdo, $branchId, $purchaseId, $excludeSupplierPaymentId);
    $paid = round($direct + $later, 2);
    $balance = max(0.0, round($net - $paid, 2));
    $status = $paid <= 0.001 ? 1 : ($balance <= 0.01 ? 3 : 2);

    return [
        'id'=>(int)$row['id'],
        'supplier_id'=>(int)$row['supplier_id'],
        'purchase_no'=>(string)$row['purchase_no'],
        'purchase_date'=>(string)$row['purchase_date'],
        'grand_total'=>$grand,
        'return_total'=>$returns,
        'net_total'=>$net,
        'direct_paid'=>$direct,
        'supplier_paid'=>$later,
        'paid_total'=>$paid,
        'pending'=>$balance,
        'payment_status'=>$status,
        'posting_status'=>(int)$row['posting_status'],
        'status'=>(int)$row['status'],
    ];
}

function sf_refresh_purchase(PDO $pdo, int $branchId, int $purchaseId): void
{
    $row = sf_purchase_snapshot($pdo, $branchId, $purchaseId);
    if (!$row || (int)$row['posting_status'] !== 1 || (int)$row['status'] !== 1) {
        return;
    }
    $stmt = $pdo->prepare(
        'UPDATE food_purchases
         SET paid_amount=:paid_amount,
             balance_amount=:balance_amount,
             payment_status=:payment_status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $stmt->execute([
        ':paid_amount'=>$row['paid_total'],
        ':balance_amount'=>$row['pending'],
        ':payment_status'=>$row['payment_status'],
        ':id'=>$purchaseId,
        ':branch_id'=>$branchId,
    ]);
}

function sf_refresh_purchases(PDO $pdo, int $branchId, array $purchaseIds): void
{
    $seen = [];
    foreach ($purchaseIds as $id) {
        $id = (int)$id;
        if ($id < 1 || isset($seen[$id])) continue;
        $seen[$id] = true;
        sf_refresh_purchase($pdo, $branchId, $id);
    }
}

function sf_supplier_opening_allocated(
    PDO $pdo,
    int $branchId,
    int $supplierId,
    int $excludeSupplierPaymentId = 0
): float {
    $sql = 'SELECT COALESCE(SUM(a.allocated_amount),0)
            FROM food_supplier_payment_allocations a
            INNER JOIN food_supplier_payments p
                    ON p.id=a.supplier_payment_id
                   AND p.branch_id=a.branch_id
            WHERE a.branch_id=:branch_id
              AND p.supplier_id=:supplier_id
              AND a.allocation_type=2
              AND a.status=1
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL';
    $params = [':branch_id'=>$branchId, ':supplier_id'=>$supplierId];
    if ($excludeSupplierPaymentId > 0) {
        $sql .= ' AND a.supplier_payment_id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludeSupplierPaymentId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function sf_supplier_context(
    PDO $pdo,
    int $branchId,
    int $supplierId,
    int $excludeSupplierPaymentId = 0
): array {
    $stmt = $pdo->prepare(
        'SELECT id,supplier_code,supplier_name,mobile,opening_balance,opening_balance_date,status,created_at
         FROM food_suppliers
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id'=>$supplierId, ':branch_id'=>$branchId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        return [];
    }

    $opening = round((float)$supplier['opening_balance'], 2);
    $openingAllocated = sf_supplier_opening_allocated($pdo, $branchId, $supplierId, $excludeSupplierPaymentId);
    $openingPending = max(0.0, round($opening - $openingAllocated, 2));

    $purchaseStmt = $pdo->prepare(
        'SELECT id
         FROM food_purchases
         WHERE branch_id=:branch_id
           AND supplier_id=:supplier_id
           AND posting_status=1
           AND status=1
         ORDER BY purchase_date ASC,id ASC'
    );
    $purchaseStmt->execute([':branch_id'=>$branchId, ':supplier_id'=>$supplierId]);

    $purchases = [];
    $purchaseOutstanding = 0.0;
    foreach ($purchaseStmt->fetchAll(PDO::FETCH_COLUMN) as $purchaseId) {
        $snap = sf_purchase_snapshot($pdo, $branchId, (int)$purchaseId, $excludeSupplierPaymentId);
        if (!$snap || $snap['pending'] <= 0.001) continue;
        $snap['ref'] = encryptReference('purchase', (int)$snap['id']);
        $purchases[] = $snap;
        $purchaseOutstanding += (float)$snap['pending'];
    }

    return [
        'supplier'=>[
            'id'=>(int)$supplier['id'],
            'ref'=>encryptReference('supplier', (int)$supplier['id']),
            'supplier_code'=>(string)$supplier['supplier_code'],
            'supplier_name'=>(string)$supplier['supplier_name'],
            'mobile'=>(string)($supplier['mobile'] ?? ''),
            'opening_balance'=>$opening,
            'opening_balance_date'=>(string)($supplier['opening_balance_date'] ?: substr((string)$supplier['created_at'], 0, 10)),
            'status'=>(int)$supplier['status'],
        ],
        'purchases'=>$purchases,
        'opening_outstanding'=>$openingPending,
        'purchase_outstanding'=>round($purchaseOutstanding, 2),
        'overall_outstanding'=>round($openingPending + $purchaseOutstanding, 2),
    ];
}
