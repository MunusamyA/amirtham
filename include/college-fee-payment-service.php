<?php
declare(strict_types=1);

/**
 * AMIRTHAM - Common College Fee Payment Service
 *
 * This file contains the ONE common mutation/recalculation implementation
 * used by:
 * - Admission Payment
 * - Student Payment page
 * - Student Payment list
 * - Future receipt/payment screens
 *
 * It does not output JSON. Business-rule failures throw DomainException.
 */

function cfp_add_months_clamped(string $dateText, int $months): string
{
    $date = new DateTimeImmutable($dateText);
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');

    $total = ($year * 12) + ($month - 1) + $months;
    $targetYear = intdiv($total, 12);
    $targetMonth = ($total % 12) + 1;

    $monthStart = new DateTimeImmutable(
        sprintf('%04d-%02d-01', $targetYear, $targetMonth)
    );

    $targetDay = min(
        $day,
        (int)$monthStart->format('t')
    );

    return sprintf(
        '%04d-%02d-%02d',
        $targetYear,
        $targetMonth,
        $targetDay
    );
}

function cfp_generate_installments(
    float $principal,
    int $count,
    ?string $firstDueDate
): array {
    $principal = round(max(0, $principal), 2);

    if (
        $principal <= 0.009 ||
        $count < 1 ||
        !$firstDueDate
    ) {
        return [];
    }

    $cents = (int)round($principal * 100);
    $base = intdiv($cents, $count);
    $allocated = 0;
    $rows = [];

    for ($i = 1; $i <= $count; $i++) {
        $amountCents =
            $i === $count
                ? $cents - $allocated
                : $base;

        $allocated += $amountCents;

        $rows[] = [
            'installment_number' => $i,
            'due_date' =>
                cfp_add_months_clamped(
                    $firstDueDate,
                    $i - 1
                ),
            'due_amount' =>
                round($amountCents / 100, 2),
        ];
    }

    return $rows;
}

function cfp_next_receipt_number(
    PDO $pdo,
    int $branchId
): int {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            MAX(
                CAST(
                    SUBSTRING(receipt_no,4)
                    AS UNSIGNED
                )
            ),
            0
         )
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND receipt_no REGEXP '^FRC[0-9]+$'"
    );

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    return ((int)$stmt->fetchColumn()) + 1;
}

function cfp_receipt_code(int $number): string
{
    return 'FRC' .
        str_pad(
            (string)$number,
            4,
            '0',
            STR_PAD_LEFT
        );
}

function cfp_mode_label(array $paymentRows): string
{
    $modes = array_values(
        array_unique(
            array_map(
                static fn(array $row): int =>
                    (int)$row['payment_mode'],
                $paymentRows
            )
        )
    );

    if (count($modes) !== 1) {
        return 'mixed';
    }

    return match ($modes[0]) {
        1 => 'cash',
        2 => 'upi',
        3 => 'bank',
        4 => 'cheque',
        default => 'mixed',
    };
}

function cfp_payment_total(array $paymentRows): float
{
    return round(
        array_sum(
            array_map(
                static fn(array $row): float =>
                    round((float)$row['amount'], 2),
                $paymentRows
            )
        ),
        2
    );
}

function cfp_plan_for_update(
    PDO $pdo,
    int $branchId,
    int $planId
): array {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM college_student_fee_plans
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':id' => $planId,
        ':branch_id' => $branchId
    ]);

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new DomainException(
            'Student Fee Plan is not available.'
        );
    }

    $plan['id'] = (int)$plan['id'];
    $plan['admission_id'] =
        (int)$plan['admission_id'];
    $plan['net_payable'] =
        round((float)$plan['net_payable'], 2);
    $plan['installment_count'] =
        (int)$plan['installment_count'];

    return $plan;
}

function cfp_plan_by_admission_for_update(
    PDO $pdo,
    int $branchId,
    int $admissionId
): array {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM college_student_fee_plans
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND status=1
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId
    ]);

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new DomainException(
            'Student Fee Plan is not available.'
        );
    }

    $plan['id'] = (int)$plan['id'];
    $plan['admission_id'] =
        (int)$plan['admission_id'];
    $plan['net_payable'] =
        round((float)$plan['net_payable'], 2);
    $plan['installment_count'] =
        (int)$plan['installment_count'];

    return $plan;
}

function cfp_receipt_for_update(
    PDO $pdo,
    int $branchId,
    int $receiptId
): array {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM college_fee_receipts
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        ':id' => $receiptId,
        ':branch_id' => $branchId
    ]);

    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        throw new DomainException(
            'Fee Payment receipt was not found.'
        );
    }

    $receipt['id'] = (int)$receipt['id'];
    $receipt['admission_id'] =
        (int)$receipt['admission_id'];
    $receipt['student_fee_plan_id'] =
        (int)$receipt['student_fee_plan_id'];
    $receipt['amount'] =
        round((float)$receipt['amount'], 2);

    return $receipt;
}

function cfp_admission_receipt_for_update(
    PDO $pdo,
    int $branchId,
    int $admissionId,
    int $planId
): ?array {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND admission_id=:admission_id
           AND student_fee_plan_id=:plan_id
           AND receipt_type='admission'
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL
         ORDER BY id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':admission_id' => $admissionId,
        ':plan_id' => $planId,
    ]);

    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        return null;
    }

    $receipt['id'] = (int)$receipt['id'];
    $receipt['amount'] =
        round((float)$receipt['amount'], 2);

    return $receipt;
}

function cfp_replace_payment_details(
    PDO $pdo,
    int $branchId,
    int $receiptId,
    array $paymentRows,
    int $userId
): void {
    $delete = $pdo->prepare(
        'DELETE FROM college_fee_receipt_payment_details
         WHERE branch_id=:branch_id
           AND fee_receipt_id=:receipt_id'
    );

    $delete->execute([
        ':branch_id' => $branchId,
        ':receipt_id' => $receiptId
    ]);

    if ($paymentRows === []) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO college_fee_receipt_payment_details
         (
            fee_receipt_id,
            branch_id,
            payment_mode,
            account_id,
            amount,
            reference_no,
            cheque_no,
            cheque_date,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :fee_receipt_id,
            :branch_id,
            :payment_mode,
            :account_id,
            :amount,
            :reference_no,
            :cheque_no,
            :cheque_date,
            1,
            :created_by,
            NOW(),
            NOW()
         )'
    );

    foreach ($paymentRows as $row) {
        $insert->execute([
            ':fee_receipt_id' => $receiptId,
            ':branch_id' => $branchId,
            ':payment_mode' =>
                (int)$row['payment_mode'],
            ':account_id' =>
                (int)$row['account_id'],
            ':amount' =>
                round((float)$row['amount'], 2),
            ':reference_no' =>
                $row['reference_no'] ?? null,
            ':cheque_no' =>
                $row['cheque_no'] ?? null,
            ':cheque_date' =>
                $row['cheque_date'] ?? null,
            ':created_by' => $userId,
        ]);
    }
}

function cfp_sync_installments(
    PDO $pdo,
    int $branchId,
    int $planId,
    float $principal,
    int $userId
): void {
    $plan = cfp_plan_for_update(
        $pdo,
        $branchId,
        $planId
    );

    $principal =
        round(max(0, $principal), 2);

    if ($principal <= 0.009) {
        $desired = [];

        $pdo->prepare(
            "UPDATE college_student_fee_plans
             SET payment_type='full',
                 installment_count=0,
                 first_due_date=NULL,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id"
        )->execute([
            ':id' => $planId,
            ':branch_id' => $branchId,
        ]);
    } else {
        $count =
            (int)$plan['installment_count'];

        $firstDueDate =
            $plan['first_due_date']
                ? (string)$plan['first_due_date']
                : null;

        if ($count < 1 || !$firstDueDate) {
            throw new DomainException(
                'Set EMI Count and First EMI Due Date in Admission before reducing or deleting the Admission Payment.'
            );
        }

        $desired = cfp_generate_installments(
            $principal,
            $count,
            $firstDueDate
        );

        $pdo->prepare(
            "UPDATE college_student_fee_plans
             SET payment_type='emi',
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id"
        )->execute([
            ':id' => $planId,
            ':branch_id' => $branchId,
        ]);
    }

    $existingStmt = $pdo->prepare(
        'SELECT *
         FROM college_fee_installments
         WHERE branch_id=:branch_id
           AND student_fee_plan_id=:plan_id
         ORDER BY installment_number,id
         FOR UPDATE'
    );

    $existingStmt->execute([
        ':branch_id' => $branchId,
        ':plan_id' => $planId
    ]);

    $existingByNumber = [];

    foreach (
        $existingStmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $existingByNumber[
            (int)$row['installment_number']
        ] = $row;
    }

    $keepIds = [];

    $update = $pdo->prepare(
        'UPDATE college_fee_installments
         SET due_date=:due_date,
             due_amount=:due_amount,
             status=1,
             updated_at=NOW()
         WHERE id=:id
           AND branch_id=:branch_id
           AND student_fee_plan_id=:plan_id'
    );

    $insert = $pdo->prepare(
        'INSERT INTO college_fee_installments
         (
            student_fee_plan_id,
            branch_id,
            installment_number,
            due_date,
            due_amount,
            waived_amount,
            remarks,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :student_fee_plan_id,
            :branch_id,
            :installment_number,
            :due_date,
            :due_amount,
            0,
            NULL,
            1,
            :created_by,
            NOW(),
            NOW()
         )'
    );

    foreach ($desired as $row) {
        $number =
            (int)$row['installment_number'];

        if (isset($existingByNumber[$number])) {
            $existing =
                $existingByNumber[$number];

            $id = (int)$existing['id'];

            $update->execute([
                ':due_date' => $row['due_date'],
                ':due_amount' => $row['due_amount'],
                ':id' => $id,
                ':branch_id' => $branchId,
                ':plan_id' => $planId,
            ]);

            $keepIds[] = $id;
        } else {
            $insert->execute([
                ':student_fee_plan_id' => $planId,
                ':branch_id' => $branchId,
                ':installment_number' => $number,
                ':due_date' => $row['due_date'],
                ':due_amount' => $row['due_amount'],
                ':created_by' => $userId,
            ]);

            $keepIds[] =
                (int)$pdo->lastInsertId();
        }
    }

    $extraIds = [];

    foreach ($existingByNumber as $existing) {
        $id = (int)$existing['id'];

        if (!in_array($id, $keepIds, true)) {
            $extraIds[] = $id;
        }
    }

    if ($extraIds !== []) {
        $marks =
            implode(
                ',',
                array_fill(
                    0,
                    count($extraIds),
                    '?'
                )
            );

        $targetCheck = $pdo->prepare(
            "SELECT
                r.receipt_no,
                i.installment_number
             FROM college_fee_receipts r
             INNER JOIN college_fee_installments i
                     ON i.id=r.target_installment_id
                    AND i.branch_id=r.branch_id
             WHERE r.branch_id=?
               AND r.student_fee_plan_id=?
               AND r.receipt_type='payment'
               AND r.status=1
               AND r.posting_status=1
               AND r.reversed_at IS NULL
               AND r.target_installment_id IN (" .
             $marks .
             ')
             LIMIT 1'
        );

        $targetCheck->execute(
            array_merge(
                [$branchId, $planId],
                $extraIds
            )
        );

        $targeted =
            $targetCheck->fetch(PDO::FETCH_ASSOC);

        if ($targeted) {
            throw new DomainException(
                'EMI Count cannot be reduced because ' .
                (string)$targeted['receipt_no'] .
                ' is linked to EMI ' .
                (int)$targeted['installment_number'] .
                '.'
            );
        }

        $deleteAlloc = $pdo->prepare(
            'DELETE FROM college_fee_receipt_allocations
             WHERE branch_id=?
               AND fee_installment_id IN (' .
             $marks .
             ')'
        );

        $deleteAlloc->execute(
            array_merge(
                [$branchId],
                $extraIds
            )
        );

        $deleteInstallments = $pdo->prepare(
            'DELETE FROM college_fee_installments
             WHERE branch_id=?
               AND student_fee_plan_id=?
               AND id IN (' .
             $marks .
             ')'
        );

        $deleteInstallments->execute(
            array_merge(
                [$branchId, $planId],
                $extraIds
            )
        );
    }
}

function cfp_replay_student_payment_allocations(
    PDO $pdo,
    int $branchId,
    int $planId
): void {
    $installmentStmt = $pdo->prepare(
        'SELECT
            id,
            installment_number,
            due_date,
            due_amount,
            waived_amount
         FROM college_fee_installments
         WHERE branch_id=:branch_id
           AND student_fee_plan_id=:plan_id
           AND status=1
         ORDER BY installment_number,due_date,id
         FOR UPDATE'
    );

    $installmentStmt->execute([
        ':branch_id' => $branchId,
        ':plan_id' => $planId
    ]);

    $installments =
        $installmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $outstanding = [];
    $installmentMap = [];

    foreach ($installments as $row) {
        $id = (int)$row['id'];

        $outstanding[$id] =
            round(
                max(
                    0,
                    (float)$row['due_amount'] -
                    (float)$row['waived_amount']
                ),
                2
            );

        $installmentMap[$id] = true;
    }

    $receiptStmt = $pdo->prepare(
        "SELECT
            id,
            receipt_no,
            receipt_date,
            amount,
            payment_against,
            target_installment_id,
            created_by
         FROM college_fee_receipts
         WHERE branch_id=:branch_id
           AND student_fee_plan_id=:plan_id
           AND receipt_type='payment'
           AND status=1
           AND posting_status=1
           AND reversed_at IS NULL
         ORDER BY receipt_date,id
         FOR UPDATE"
    );

    $receiptStmt->execute([
        ':branch_id' => $branchId,
        ':plan_id' => $planId
    ]);

    $receipts =
        $receiptStmt->fetchAll(PDO::FETCH_ASSOC);

    $receiptIds =
        array_map(
            'intval',
            array_column($receipts, 'id')
        );

    if ($receiptIds !== []) {
        $marks =
            implode(
                ',',
                array_fill(
                    0,
                    count($receiptIds),
                    '?'
                )
            );

        $delete = $pdo->prepare(
            'DELETE FROM college_fee_receipt_allocations
             WHERE branch_id=?
               AND fee_receipt_id IN (' .
             $marks .
             ')'
        );

        $delete->execute(
            array_merge(
                [$branchId],
                $receiptIds
            )
        );
    }

    $insert = $pdo->prepare(
        'INSERT INTO college_fee_receipt_allocations
         (
            fee_receipt_id,
            fee_installment_id,
            branch_id,
            allocated_amount,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :fee_receipt_id,
            :fee_installment_id,
            :branch_id,
            :allocated_amount,
            1,
            :created_by,
            NOW(),
            NOW()
         )'
    );

    foreach ($receipts as $receipt) {
        $receiptId = (int)$receipt['id'];
        $createdBy = (int)$receipt['created_by'];

        $remaining =
            round(
                (float)$receipt['amount'],
                2
            );

        if ($remaining <= 0.009) {
            continue;
        }

        $against =
            strtolower(
                (string)$receipt['payment_against']
            );

        $targetId =
            $receipt['target_installment_id'] === null
                ? 0
                : (int)$receipt['target_installment_id'];

        if ($against === 'emi') {
            if (
                $targetId < 1 ||
                !isset($installmentMap[$targetId])
            ) {
                throw new DomainException(
                    'Payment ' .
                    (string)$receipt['receipt_no'] .
                    ' has an invalid EMI target.'
                );
            }

            $available =
                round(
                    $outstanding[$targetId] ?? 0,
                    2
                );

            if (
                $remaining >
                $available + 0.009
            ) {
                throw new DomainException(
                    'Payment ' .
                    (string)$receipt['receipt_no'] .
                    ' exceeds the outstanding amount of its selected EMI after recalculation.'
                );
            }

            $insert->execute([
                ':fee_receipt_id' => $receiptId,
                ':fee_installment_id' => $targetId,
                ':branch_id' => $branchId,
                ':allocated_amount' => $remaining,
                ':created_by' => $createdBy,
            ]);

            $outstanding[$targetId] =
                round(
                    max(
                        0,
                        $available - $remaining
                    ),
                    2
                );

            $remaining = 0.0;
        } else {
            foreach ($installments as $installment) {
                if ($remaining <= 0.009) {
                    break;
                }

                $installmentId =
                    (int)$installment['id'];

                $available =
                    round(
                        $outstanding[
                            $installmentId
                        ] ?? 0,
                        2
                    );

                if ($available <= 0.009) {
                    continue;
                }

                $allocate =
                    round(
                        min(
                            $remaining,
                            $available
                        ),
                        2
                    );

                if ($allocate <= 0.009) {
                    continue;
                }

                $insert->execute([
                    ':fee_receipt_id' => $receiptId,
                    ':fee_installment_id' => $installmentId,
                    ':branch_id' => $branchId,
                    ':allocated_amount' => $allocate,
                    ':created_by' => $createdBy,
                ]);

                $outstanding[$installmentId] =
                    round(
                        max(
                            0,
                            $available - $allocate
                        ),
                        2
                    );

                $remaining =
                    round(
                        $remaining - $allocate,
                        2
                    );
            }
        }

        if ($remaining > 0.009) {
            throw new DomainException(
                'Payment ' .
                (string)$receipt['receipt_no'] .
                ' exceeds the Student outstanding balance after recalculation.'
            );
        }
    }
}

function cfp_save_admission_receipt(
    PDO $pdo,
    int $branchId,
    int $admissionId,
    int $planId,
    string $receiptDate,
    array $paymentRows,
    int $userId,
    ?string $notes = null
): array {
    $plan =
        cfp_plan_for_update(
            $pdo,
            $branchId,
            $planId
        );

    if ((int)$plan['admission_id'] !== $admissionId) {
        throw new DomainException(
            'Student Fee Plan does not belong to this Admission.'
        );
    }

    $total =
        cfp_payment_total(
            $paymentRows
        );

    if (
        $total >
        (float)$plan['net_payable'] + 0.009
    ) {
        throw new DomainException(
            'Admission Payment cannot exceed Total Fee.'
        );
    }

    $existing =
        cfp_admission_receipt_for_update(
            $pdo,
            $branchId,
            $admissionId,
            $planId
        );

    $receiptId =
        $existing
            ? (int)$existing['id']
            : null;

    if ($total <= 0.009) {
        if ($receiptId !== null) {
            $pdo->prepare(
                'DELETE FROM college_fee_receipt_allocations
                 WHERE branch_id=:branch_id
                   AND fee_receipt_id=:receipt_id'
            )->execute([
                ':branch_id' => $branchId,
                ':receipt_id' => $receiptId,
            ]);

            $pdo->prepare(
                'DELETE FROM college_fee_receipt_payment_details
                 WHERE branch_id=:branch_id
                   AND fee_receipt_id=:receipt_id'
            )->execute([
                ':branch_id' => $branchId,
                ':receipt_id' => $receiptId,
            ]);

            $pdo->prepare(
                "DELETE FROM college_fee_receipts
                 WHERE id=:id
                   AND branch_id=:branch_id
                   AND receipt_type='admission'"
            )->execute([
                ':id' => $receiptId,
                ':branch_id' => $branchId,
            ]);
        }

        $receiptId = null;
    } elseif ($receiptId === null) {
        $receiptNo =
            cfp_receipt_code(
                cfp_next_receipt_number(
                    $pdo,
                    $branchId
                )
            );

        $stmt = $pdo->prepare(
            "INSERT INTO college_fee_receipts
             (
                branch_id,
                receipt_no,
                admission_id,
                student_fee_plan_id,
                receipt_date,
                amount,
                receipt_type,
                payment_against,
                target_installment_id,
                payment_mode,
                payment_reference,
                notes,
                posting_status,
                reversed_at,
                reversed_by,
                status,
                created_by,
                created_at,
                updated_at
             )
             VALUES
             (
                :branch_id,
                :receipt_no,
                :admission_id,
                :student_fee_plan_id,
                :receipt_date,
                :amount,
                'admission',
                'admission',
                NULL,
                :payment_mode,
                NULL,
                :notes,
                1,
                NULL,
                NULL,
                1,
                :created_by,
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':receipt_no' => $receiptNo,
            ':admission_id' => $admissionId,
            ':student_fee_plan_id' => $planId,
            ':receipt_date' => $receiptDate,
            ':amount' => $total,
            ':payment_mode' =>
                cfp_mode_label($paymentRows),
            ':notes' =>
                $notes ?: 'Payment collected during Student Admission',
            ':created_by' => $userId,
        ]);

        $receiptId =
            (int)$pdo->lastInsertId();

        cfp_replace_payment_details(
            $pdo,
            $branchId,
            $receiptId,
            $paymentRows,
            $userId
        );
    } else {
        $stmt = $pdo->prepare(
            "UPDATE college_fee_receipts
             SET receipt_date=:receipt_date,
                 amount=:amount,
                 payment_mode=:payment_mode,
                 payment_reference=NULL,
                 notes=:notes,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id
               AND receipt_type='admission'"
        );

        $stmt->execute([
            ':receipt_date' => $receiptDate,
            ':amount' => $total,
            ':payment_mode' =>
                cfp_mode_label($paymentRows),
            ':notes' =>
                $notes ?: 'Payment collected during Student Admission',
            ':id' => $receiptId,
            ':branch_id' => $branchId,
        ]);

        cfp_replace_payment_details(
            $pdo,
            $branchId,
            $receiptId,
            $paymentRows,
            $userId
        );
    }

    $principal =
        round(
            max(
                0,
                (float)$plan['net_payable'] -
                $total
            ),
            2
        );

    cfp_sync_installments(
        $pdo,
        $branchId,
        $planId,
        $principal,
        $userId
    );

    cfp_replay_student_payment_allocations(
        $pdo,
        $branchId,
        $planId
    );

    return [
        'receipt_id' => $receiptId,
        'amount' => $total,
        'principal' => $principal,
    ];
}

function cfp_save_student_payment(
    PDO $pdo,
    int $branchId,
    int $admissionId,
    int $planId,
    string $receiptDate,
    string $paymentAgainst,
    ?int $targetInstallmentId,
    array $paymentRows,
    ?string $notes,
    int $userId,
    ?int $receiptId = null
): array {
    $plan =
        cfp_plan_for_update(
            $pdo,
            $branchId,
            $planId
        );

    if ((int)$plan['admission_id'] !== $admissionId) {
        throw new DomainException(
            'Student Fee Plan does not belong to this Admission.'
        );
    }

    $total =
        cfp_payment_total(
            $paymentRows
        );

    if ($total <= 0.009) {
        throw new DomainException(
            'Enter a Payment Amount.'
        );
    }

    $paymentAgainst =
        strtolower(
            trim($paymentAgainst)
        );

    if (
        !in_array(
            $paymentAgainst,
            ['overall','emi'],
            true
        )
    ) {
        throw new DomainException(
            'Select a valid Payment Against option.'
        );
    }

    if ($paymentAgainst === 'emi') {
        if (!$targetInstallmentId) {
            throw new DomainException(
                'Select a Particular EMI.'
            );
        }

        $target = $pdo->prepare(
            'SELECT id
             FROM college_fee_installments
             WHERE id=:id
               AND branch_id=:branch_id
               AND student_fee_plan_id=:plan_id
               AND status=1
             LIMIT 1
             FOR UPDATE'
        );

        $target->execute([
            ':id' => $targetInstallmentId,
            ':branch_id' => $branchId,
            ':plan_id' => $planId,
        ]);

        if (!$target->fetchColumn()) {
            throw new DomainException(
                'Selected EMI is invalid.'
            );
        }
    } else {
        $targetInstallmentId = null;
    }

    if ($receiptId !== null) {
        $existing =
            cfp_receipt_for_update(
                $pdo,
                $branchId,
                $receiptId
            );

        if (
            (string)$existing['receipt_type'] !== 'payment' ||
            (int)$existing['admission_id'] !== $admissionId ||
            (int)$existing['student_fee_plan_id'] !== $planId
        ) {
            throw new DomainException(
                'Student Payment does not belong to this Admission.'
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE college_fee_receipts
             SET receipt_date=:receipt_date,
                 amount=:amount,
                 payment_against=:payment_against,
                 target_installment_id=:target_installment_id,
                 payment_mode=:payment_mode,
                 payment_reference=NULL,
                 notes=:notes,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id
               AND receipt_type='payment'"
        );

        $stmt->execute([
            ':receipt_date' => $receiptDate,
            ':amount' => $total,
            ':payment_against' =>
                $paymentAgainst,
            ':target_installment_id' =>
                $targetInstallmentId,
            ':payment_mode' =>
                cfp_mode_label($paymentRows),
            ':notes' =>
                $notes === '' ? null : $notes,
            ':id' => $receiptId,
            ':branch_id' => $branchId,
        ]);
    } else {
        $receiptNo =
            cfp_receipt_code(
                cfp_next_receipt_number(
                    $pdo,
                    $branchId
                )
            );

        $stmt = $pdo->prepare(
            "INSERT INTO college_fee_receipts
             (
                branch_id,
                receipt_no,
                admission_id,
                student_fee_plan_id,
                receipt_date,
                amount,
                receipt_type,
                payment_against,
                target_installment_id,
                payment_mode,
                payment_reference,
                notes,
                posting_status,
                reversed_at,
                reversed_by,
                status,
                created_by,
                created_at,
                updated_at
             )
             VALUES
             (
                :branch_id,
                :receipt_no,
                :admission_id,
                :student_fee_plan_id,
                :receipt_date,
                :amount,
                'payment',
                :payment_against,
                :target_installment_id,
                :payment_mode,
                NULL,
                :notes,
                1,
                NULL,
                NULL,
                1,
                :created_by,
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':receipt_no' => $receiptNo,
            ':admission_id' => $admissionId,
            ':student_fee_plan_id' => $planId,
            ':receipt_date' => $receiptDate,
            ':amount' => $total,
            ':payment_against' =>
                $paymentAgainst,
            ':target_installment_id' =>
                $targetInstallmentId,
            ':payment_mode' =>
                cfp_mode_label($paymentRows),
            ':notes' =>
                $notes === '' ? null : $notes,
            ':created_by' => $userId,
        ]);

        $receiptId =
            (int)$pdo->lastInsertId();
    }

    cfp_replace_payment_details(
        $pdo,
        $branchId,
        $receiptId,
        $paymentRows,
        $userId
    );

    cfp_replay_student_payment_allocations(
        $pdo,
        $branchId,
        $planId
    );

    return [
        'receipt_id' => $receiptId,
        'amount' => $total,
    ];
}

function cfp_delete_receipt(
    PDO $pdo,
    int $branchId,
    int $receiptId,
    int $userId
): array {
    $receipt =
        cfp_receipt_for_update(
            $pdo,
            $branchId,
            $receiptId
        );

    $planId =
        (int)$receipt['student_fee_plan_id'];

    $admissionId =
        (int)$receipt['admission_id'];

    $type =
        (string)$receipt['receipt_type'];

    if (
        !in_array(
            $type,
            ['admission','payment'],
            true
        )
    ) {
        throw new DomainException(
            'This receipt type cannot be deleted from Fee Payment.'
        );
    }

    $plan =
        cfp_plan_for_update(
            $pdo,
            $branchId,
            $planId
        );

    if ($type === 'admission') {
        $principal =
            round(
                (float)$plan['net_payable'],
                2
            );

        if (
            $principal > 0.009 &&
            (
                (int)$plan['installment_count'] < 1 ||
                empty($plan['first_due_date'])
            )
        ) {
            throw new DomainException(
                'This Admission was fully paid during Admission. Set EMI Count and First EMI Due Date in Admission form before deleting the Admission Payment.'
            );
        }
    }

    $pdo->prepare(
        'DELETE FROM college_fee_receipt_allocations
         WHERE branch_id=:branch_id
           AND fee_receipt_id=:receipt_id'
    )->execute([
        ':branch_id' => $branchId,
        ':receipt_id' => $receiptId,
    ]);

    $pdo->prepare(
        'DELETE FROM college_fee_receipt_payment_details
         WHERE branch_id=:branch_id
           AND fee_receipt_id=:receipt_id'
    )->execute([
        ':branch_id' => $branchId,
        ':receipt_id' => $receiptId,
    ]);

    $pdo->prepare(
        'DELETE FROM college_fee_receipts
         WHERE id=:id
           AND branch_id=:branch_id'
    )->execute([
        ':id' => $receiptId,
        ':branch_id' => $branchId,
    ]);

    if ($type === 'admission') {
        cfp_sync_installments(
            $pdo,
            $branchId,
            $planId,
            (float)$plan['net_payable'],
            $userId
        );
    }

    cfp_replay_student_payment_allocations(
        $pdo,
        $branchId,
        $planId
    );

    return [
        'receipt_type' => $type,
        'admission_id' => $admissionId,
        'plan_id' => $planId,
    ];
}
