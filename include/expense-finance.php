<?php

declare(strict_types=1);

/**
 * AMIRTHAM - Shared Expense accounting helpers.
 *
 * Payment status is always condition based and never user selected:
 *   1 = Unpaid
 *   2 = Partially Paid
 *   3 = Paid
 *
 * Source of truth for Paid Amount:
 *   active + posted + non-reversed food_expense_payments
 */

function ef_expense_payment_total(
    PDO $pdo,
    int $branchId,
    int $expenseId,
    int $excludeExpensePaymentId = 0
): float {
    $sql = 'SELECT COALESCE(SUM(p.amount),0)
            FROM food_expense_payments p
            WHERE p.branch_id=:branch_id
              AND p.expense_id=:expense_id
              AND p.posting_status=1
              AND p.status=1
              AND p.reversed_at IS NULL';
    $params = [
        ':branch_id' => $branchId,
        ':expense_id' => $expenseId,
    ];

    if ($excludeExpensePaymentId > 0) {
        $sql .= ' AND p.id<>:exclude_payment_id';
        $params[':exclude_payment_id'] = $excludeExpensePaymentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return round((float)$stmt->fetchColumn(), 2);
}

function ef_payment_status(float $paidAmount, float $balanceAmount): int
{
    if ($paidAmount <= 0.001) {
        return 1;
    }
    if ($balanceAmount <= 0.01) {
        return 3;
    }
    return 2;
}

function ef_payment_status_label(int $status): string
{
    return [
        1 => 'Unpaid',
        2 => 'Partially Paid',
        3 => 'Paid',
    ][$status] ?? 'Unpaid';
}

function ef_expense_snapshot(
    PDO $pdo,
    int $branchId,
    int $expenseId,
    int $excludeExpensePaymentId = 0
): array {
    $stmt = $pdo->prepare(
        'SELECT e.id,e.branch_id,e.expense_category_id,e.expense_no,e.expense_date,
                e.payee_name,e.invoice_no,e.invoice_date,e.payee_gstin,e.payee_state_code,
                e.description,e.tax_mode,e.gst_rate,e.taxable_amount,e.cgst_amount,
                e.sgst_amount,e.igst_amount,e.total_amount,e.paid_amount,e.balance_amount,
                e.payment_status,e.posting_status,e.status,e.reversed_at,e.created_at,e.updated_at,
                ec.category_code,ec.category_name
         FROM expenses e
         INNER JOIN expense_categories ec ON ec.id=e.expense_category_id
         WHERE e.id=:id AND e.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $expenseId,
        ':branch_id' => $branchId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [];
    }

    $total = round((float)$row['total_amount'], 2);
    $paid = ef_expense_payment_total(
        $pdo,
        $branchId,
        $expenseId,
        $excludeExpensePaymentId
    );
    $balance = max(0.0, round($total - $paid, 2));
    $paymentStatus = ef_payment_status($paid, $balance);

    foreach ([
        'id','branch_id','expense_category_id','tax_mode','payment_status',
        'posting_status','status'
    ] as $key) {
        $row[$key] = (int)$row[$key];
    }
    foreach ([
        'gst_rate','taxable_amount','cgst_amount','sgst_amount','igst_amount',
        'total_amount','paid_amount','balance_amount'
    ] as $key) {
        $row[$key] = (float)$row[$key];
    }

    $row['paid_amount'] = $paid;
    $row['balance_amount'] = $balance;
    $row['payment_status'] = $paymentStatus;
    $row['payment_status_label'] = ef_payment_status_label($paymentStatus);

    return $row;
}

function ef_refresh_expense(PDO $pdo, int $branchId, int $expenseId): void
{
    $row = ef_expense_snapshot($pdo, $branchId, $expenseId);
    if (!$row) {
        return;
    }

    if ((int)$row['posting_status'] !== 1 ||
        (int)$row['status'] !== 1 ||
        !empty($row['reversed_at'])) {
        $paid = 0.0;
        $balance = 0.0;
        $paymentStatus = 1;
    } else {
        $paid = (float)$row['paid_amount'];
        $balance = (float)$row['balance_amount'];
        $paymentStatus = (int)$row['payment_status'];
    }

    $stmt = $pdo->prepare(
        'UPDATE expenses
         SET paid_amount=:paid_amount,
             balance_amount=:balance_amount,
             payment_status=:payment_status,
             updated_at=NOW()
         WHERE id=:id AND branch_id=:branch_id'
    );
    $stmt->execute([
        ':paid_amount' => $paid,
        ':balance_amount' => $balance,
        ':payment_status' => $paymentStatus,
        ':id' => $expenseId,
        ':branch_id' => $branchId,
    ]);
}

function ef_refresh_expenses(PDO $pdo, int $branchId, array $expenseIds): void
{
    $seen = [];
    foreach ($expenseIds as $expenseId) {
        $expenseId = (int)$expenseId;
        if ($expenseId < 1 || isset($seen[$expenseId])) {
            continue;
        }
        $seen[$expenseId] = true;
        ef_refresh_expense($pdo, $branchId, $expenseId);
    }
}

function ef_expense_context(
    PDO $pdo,
    int $branchId,
    int $expenseId,
    int $excludeExpensePaymentId = 0
): array {
    $row = ef_expense_snapshot(
        $pdo,
        $branchId,
        $expenseId,
        $excludeExpensePaymentId
    );

    if (!$row ||
        (int)$row['posting_status'] !== 1 ||
        (int)$row['status'] !== 1 ||
        !empty($row['reversed_at'])) {
        return [];
    }

    return [
        'id' => (int)$row['id'],
        'ref' => encryptReference('expense', (int)$row['id']),
        'expense_no' => (string)$row['expense_no'],
        'expense_date' => (string)$row['expense_date'],
        'category_name' => (string)$row['category_name'],
        'payee_name' => (string)$row['payee_name'],
        'total_amount' => (float)$row['total_amount'],
        'paid_amount' => (float)$row['paid_amount'],
        'balance_amount' => (float)$row['balance_amount'],
        'payment_status' => (int)$row['payment_status'],
        'payment_status_label' => (string)$row['payment_status_label'],
    ];
}
