<?php

declare(strict_types=1);

/**
 * AMIRTHAM Community College - Fee Receipt Data API
 *
 * This file contains all receipt database/data logic.
 * - Direct API request: returns JSON.
 * - Included by student-fee-receipt.php in library mode: exposes only the
 *   data functions so the root PDF file can render the FPDF output.
 */

require_once dirname(__DIR__) . '/include/bootstrap.php';

function cfr_clean_text(?string $value): string
{
    $value = trim((string)$value);
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function cfr_decode_ref(string $ref): int
{
    $ref = trim($ref);
    if ($ref === '') {
        throw new InvalidArgumentException('Receipt reference is required.');
    }

    try {
        $id = decryptReference($ref, 'college_fee_receipt');
    } catch (Throwable $e) {
        throw new InvalidArgumentException('Invalid receipt reference.');
    }

    if ((int)$id <= 0) {
        throw new InvalidArgumentException('Invalid receipt reference.');
    }

    return (int)$id;
}

function cfr_payment_mode_label(int $mode): string
{
    return match ($mode) {
        1 => 'Cash',
        2 => 'UPI',
        3 => 'Bank Transfer',
        4 => 'Cheque',
        default => 'Other',
    };
}

function cfr_settings(int $branchId): array
{
    $keys = [
        'business_name',
        'business_short_name',
        'legal_name',
        'company_logo',
        'digital_signature',
        'gst_number',
        'pan_number',
        'business_email',
        'business_mobile',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'pincode',
        'country',
        'currency_code',
        'currency_symbol',
        'date_format',
        'invoice_footer',
        'authorized_signatory',
    ];

    $defaults = [
        'business_name' => function_exists('app_name') ? (string)app_name() : 'AMIRTHAM COMMUNITY COLLEGE',
        'business_short_name' => 'Amirtham',
        'legal_name' => '',
        'company_logo' => '',
        'digital_signature' => '',
        'gst_number' => '',
        'pan_number' => '',
        'business_email' => '',
        'business_mobile' => '',
        'website' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
        'country' => 'India',
        'currency_code' => 'INR',
        'currency_symbol' => 'Rs.',
        'date_format' => 'd-m-Y',
        'invoice_footer' => '',
        'authorized_signatory' => '',
    ];

    $out = [];
    foreach ($keys as $key) {
        $out[$key] = function_exists('app_setting')
            ? (string)app_setting($key, $defaults[$key] ?? '', $branchId)
            : (string)($defaults[$key] ?? '');
    }

    if (trim($out['business_name']) === '') {
        $out['business_name'] = $defaults['business_name'];
    }

    return $out;
}

function cfr_require_print_permission(array $user, array $receipt): void
{
    if (!function_exists('menu_by_path') || !function_exists('effective_actions_for_menu')) {
        throw new RuntimeException('Permission service is unavailable.');
    }

    $menuPath = strtolower((string)($receipt['receipt_type'] ?? '')) === 'admission'
        ? 'admission-list.php'
        : 'student-payment-list.php';

    $menu = menu_by_path($menuPath);
    if (!$menu) {
        throw new RuntimeException('Receipt permission configuration is unavailable.');
    }

    $actions = effective_actions_for_menu($user, $menu);
    $printAction = defined('ACTION_PRINT') ? (int)ACTION_PRINT : 9;

    if (!in_array($printAction, array_map('intval', $actions), true)) {
        throw new DomainException('You do not have permission to print this receipt.');
    }
}

function cfr_load_receipt(int $receiptId, array $user): array
{
    $sql =
        'SELECT
            r.*,
            a.admission_no,
            a.admission_date,
            s.student_code,
            s.student_name,
            s.mobile,
            c.course_code,
            c.course_name,
            b.batch_code,
            b.batch_name,
            fp.net_payable,
            fp.payment_type,
            u.username AS created_by_username
         FROM college_fee_receipts r
         INNER JOIN college_admissions a
                 ON a.id = r.admission_id
                AND a.branch_id = r.branch_id
         INNER JOIN college_students s
                 ON s.id = a.student_id
                AND s.branch_id = a.branch_id
         INNER JOIN college_courses c
                 ON c.id = a.course_id
                AND c.branch_id = a.branch_id
         INNER JOIN college_batches b
                 ON b.id = a.batch_id
                AND b.branch_id = a.branch_id
         INNER JOIN college_student_fee_plans fp
                 ON fp.id = r.student_fee_plan_id
                AND fp.branch_id = r.branch_id
                AND fp.admission_id = r.admission_id
         LEFT JOIN users u
                ON u.id = r.created_by
         WHERE r.id = :receipt_id
           AND r.status = 1
           AND r.posting_status = 1
           AND r.reversed_at IS NULL
         LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':receipt_id' => $receiptId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new OutOfBoundsException('Posted receipt was not found.');
    }

    $branchId = (int)$row['branch_id'];
    if ((int)($user['role_type'] ?? 0) !== 2 && (int)($user['branch_id'] ?? 0) !== $branchId) {
        throw new DomainException('You do not have access to this receipt.');
    }

    $row['id'] = (int)$row['id'];
    $row['branch_id'] = $branchId;
    $row['admission_id'] = (int)$row['admission_id'];
    $row['student_fee_plan_id'] = (int)$row['student_fee_plan_id'];
    $row['target_installment_id'] = $row['target_installment_id'] === null ? null : (int)$row['target_installment_id'];
    $row['amount'] = round((float)$row['amount'], 2);
    $row['net_payable'] = round((float)$row['net_payable'], 2);

    return $row;
}

function cfr_prior_total(array $receipt): float
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(amount), 0)
         FROM college_fee_receipts
         WHERE branch_id = :branch_id
           AND admission_id = :admission_id
           AND student_fee_plan_id = :plan_id
           AND status = 1
           AND posting_status = 1
           AND reversed_at IS NULL
           AND (
                receipt_date < :receipt_date
                OR (receipt_date = :receipt_date_same AND id < :receipt_id)
           )"
    );
    $stmt->execute([
        ':branch_id' => $receipt['branch_id'],
        ':admission_id' => $receipt['admission_id'],
        ':plan_id' => $receipt['student_fee_plan_id'],
        ':receipt_date' => $receipt['receipt_date'],
        ':receipt_date_same' => $receipt['receipt_date'],
        ':receipt_id' => $receipt['id'],
    ]);

    return round((float)$stmt->fetchColumn(), 2);
}

function cfr_payment_details(array $receipt): array
{
    $stmt = db()->prepare(
        'SELECT
            d.payment_mode,
            d.amount,
            d.reference_no,
            d.cheque_no,
            d.cheque_date,
            a.account_code,
            a.account_name,
            a.account_type,
            a.bank_name,
            a.account_number,
            a.upi_id
         FROM college_fee_receipt_payment_details d
         INNER JOIN accounts a
                 ON a.id = d.account_id
                AND a.branch_id = d.branch_id
         WHERE d.branch_id = :branch_id
           AND d.fee_receipt_id = :receipt_id
           AND d.status = 1
         ORDER BY d.id'
    );
    $stmt->execute([
        ':branch_id' => $receipt['branch_id'],
        ':receipt_id' => $receipt['id'],
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mode = (int)$row['payment_mode'];
        $reference = cfr_clean_text($row['reference_no'] ?? '');

        if ($mode === 4) {
            $parts = [];
            $chequeNo = cfr_clean_text($row['cheque_no'] ?? '');
            if ($chequeNo !== '') {
                $parts[] = $chequeNo;
            }
            if (!empty($row['cheque_date'])) {
                $parts[] = (string)$row['cheque_date'];
            }
            if ($parts !== []) {
                $reference = implode(' / ', $parts);
            }
        }

        $rows[] = [
            'mode' => cfr_payment_mode_label($mode),
            'account' => cfr_clean_text($row['account_name'] ?? ''),
            'reference' => $reference !== '' ? $reference : '-',
            'amount' => round((float)$row['amount'], 2),
        ];
    }

    if ($rows === []) {
        $rows[] = [
            'mode' => cfr_clean_text($receipt['payment_mode'] ?? '') ?: 'Payment',
            'account' => '-',
            'reference' => cfr_clean_text($receipt['payment_reference'] ?? '') ?: '-',
            'amount' => round((float)$receipt['amount'], 2),
        ];
    }

    return $rows;
}


function cfr_fee_plan_items(array $receipt): array
{
    $stmt = db()->prepare(
        'SELECT
            fee_head_snapshot,
            amount,
            mandatory_snapshot,
            sort_order
         FROM college_student_fee_plan_items
         WHERE branch_id = :branch_id
           AND student_fee_plan_id = :plan_id
           AND status = 1
         ORDER BY sort_order, id'
    );
    $stmt->execute([
        ':branch_id' => $receipt['branch_id'],
        ':plan_id' => $receipt['student_fee_plan_id'],
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $feeHead = cfr_clean_text($row['fee_head_snapshot'] ?? '');
        if ($feeHead === '') {
            $feeHead = 'Fee';
        }

        $rows[] = [
            'fee_head' => $feeHead,
            'amount' => round((float)$row['amount'], 2),
            'mandatory' => (int)$row['mandatory_snapshot'],
            'sort_order' => (int)$row['sort_order'],
        ];
    }

    return $rows;
}

function cfr_allocations(array $receipt): array
{
    $stmt = db()->prepare(
        'SELECT
            i.id AS installment_id,
            i.installment_number,
            i.due_date,
            i.due_amount,
            i.waived_amount,
            a.allocated_amount
         FROM college_fee_receipt_allocations a
         INNER JOIN college_fee_installments i
                 ON i.id = a.fee_installment_id
                AND i.branch_id = a.branch_id
         WHERE a.branch_id = :branch_id
           AND a.fee_receipt_id = :receipt_id
           AND a.status = 1
           AND i.status = 1
         ORDER BY i.installment_number, i.id'
    );
    $stmt->execute([
        ':branch_id' => $receipt['branch_id'],
        ':receipt_id' => $receipt['id'],
    ]);

    return array_map(static function (array $row): array {
        return [
            'installment_id' => (int)$row['installment_id'],
            'installment_number' => (int)$row['installment_number'],
            'due_date' => (string)$row['due_date'],
            'due_amount' => round((float)$row['due_amount'], 2),
            'waived_amount' => round((float)$row['waived_amount'], 2),
            'allocated_amount' => round((float)$row['allocated_amount'], 2),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function cfr_emi_snapshot(array $receipt, array $allocations): ?array
{
    $installmentId = (int)($receipt['target_installment_id'] ?? 0);
    if ($installmentId < 1) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, installment_number, due_date, due_amount, waived_amount
         FROM college_fee_installments
         WHERE id = :id
           AND branch_id = :branch_id
           AND student_fee_plan_id = :plan_id
           AND status = 1
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $installmentId,
        ':branch_id' => $receipt['branch_id'],
        ':plan_id' => $receipt['student_fee_plan_id'],
    ]);
    $emi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emi) {
        return null;
    }

    $priorStmt = db()->prepare(
        'SELECT COALESCE(SUM(al.allocated_amount), 0)
         FROM college_fee_receipt_allocations al
         INNER JOIN college_fee_receipts r
                 ON r.id = al.fee_receipt_id
                AND r.branch_id = al.branch_id
         WHERE al.branch_id = :branch_id
           AND al.fee_installment_id = :installment_id
           AND al.status = 1
           AND r.status = 1
           AND r.posting_status = 1
           AND r.reversed_at IS NULL
           AND (
                r.receipt_date < :receipt_date
                OR (r.receipt_date = :receipt_date_same AND r.id < :receipt_id)
           )'
    );
    $priorStmt->execute([
        ':branch_id' => $receipt['branch_id'],
        ':installment_id' => $installmentId,
        ':receipt_date' => $receipt['receipt_date'],
        ':receipt_date_same' => $receipt['receipt_date'],
        ':receipt_id' => $receipt['id'],
    ]);
    $previousPaid = round((float)$priorStmt->fetchColumn(), 2);

    $paidNow = 0.0;
    foreach ($allocations as $allocation) {
        if ((int)$allocation['installment_id'] === $installmentId) {
            $paidNow += (float)$allocation['allocated_amount'];
        }
    }
    $paidNow = round($paidNow > 0 ? $paidNow : (float)$receipt['amount'], 2);

    $effectiveDue = max(0, round((float)$emi['due_amount'] - (float)$emi['waived_amount'], 2));

    return [
        'installment_number' => (int)$emi['installment_number'],
        'due_date' => (string)$emi['due_date'],
        'due_amount' => round((float)$emi['due_amount'], 2),
        'waived_amount' => round((float)$emi['waived_amount'], 2),
        'previous_paid' => $previousPaid,
        'paid_now' => $paidNow,
        'balance_after' => max(0, round($effectiveDue - $previousPaid - $paidNow, 2)),
    ];
}

function cfr_number_words(int $number): string
{
    if ($number === 0) {
        return 'Zero';
    }

    $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];
    $tens = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];

    $underHundred = static function (int $n) use ($ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }
        return $tens[intdiv($n, 10)] . (($n % 10) ? ' ' . $ones[$n % 10] : '');
    };

    $underThousand = static function (int $n) use (&$underHundred, $ones): string {
        $parts = [];
        if ($n >= 100) {
            $parts[] = $ones[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
        }
        if ($n > 0) {
            $parts[] = $underHundred($n);
        }
        return implode(' ', $parts);
    };

    $parts = [];

    if ($number >= 10000000) {
        $crore = intdiv($number, 10000000);
        $parts[] = cfr_number_words($crore) . ' Crore';
        $number %= 10000000;
    }
    if ($number >= 100000) {
        $lakh = intdiv($number, 100000);
        $parts[] = cfr_number_words($lakh) . ' Lakh';
        $number %= 100000;
    }
    if ($number >= 1000) {
        $thousand = intdiv($number, 1000);
        $parts[] = $underThousand($thousand) . ' Thousand';
        $number %= 1000;
    }
    if ($number > 0) {
        $parts[] = $underThousand($number);
    }

    return trim(implode(' ', $parts));
}

function cfr_amount_words(float $amount): string
{
    $amount = round($amount, 2);
    $rupees = (int)floor($amount);
    $paise = (int)round(($amount - $rupees) * 100);

    $text = 'Rupees ' . cfr_number_words($rupees);
    if ($paise > 0) {
        $text .= ' and ' . cfr_number_words($paise) . ' Paise';
    }

    return $text . ' Only';
}

function cfr_payload_from_ref(string $ref, array $user): array
{
    $receiptId = cfr_decode_ref($ref);
    $receipt = cfr_load_receipt($receiptId, $user);
    cfr_require_print_permission($user, $receipt);

    $settings = cfr_settings((int)$receipt['branch_id']);
    $payments = cfr_payment_details($receipt);
    $feeItems = cfr_fee_plan_items($receipt);
    $allocations = cfr_allocations($receipt);
    $priorPaid = cfr_prior_total($receipt);
    $totalFee = (float)$receipt['net_payable'];
    $currentAmount = (float)$receipt['amount'];
    $previousBalance = max(0, round($totalFee - $priorPaid, 2));
    $balanceAfter = max(0, round($previousBalance - $currentAmount, 2));
    $emi = cfr_emi_snapshot($receipt, $allocations);

    $receiptType = strtolower((string)$receipt['receipt_type']);
    $paymentAgainst = strtolower((string)$receipt['payment_against']);

    if ($receiptType === 'admission') {
        $kind = 'admission';
    } elseif ($paymentAgainst === 'emi') {
        $kind = 'emi';
    } else {
        $kind = 'overall';
    }

    $remarks = cfr_clean_text($receipt['notes'] ?? '');
    if ($remarks === '') {
        $remarks = match ($kind) {
            'admission' => 'Admission fee payment received.',
            'emi' => $emi ? 'EMI ' . $emi['installment_number'] . ' payment received.' : 'EMI payment received.',
            default => 'Overall outstanding payment received.',
        };
    }

    $modeTotal = 0.0;
    foreach ($payments as $payment) {
        $modeTotal += (float)$payment['amount'];
    }
    $modeTotal = round($modeTotal, 2);

    return [
        'receipt' => $receipt,
        'settings' => $settings,
        'payments' => $payments,
        'fee_items' => $feeItems,
        'allocations' => $allocations,
        'emi' => $emi,
        'kind' => $kind,
        'summary' => [
            'total_fee' => round($totalFee, 2),
            'previous_paid' => round($priorPaid, 2),
            'previous_balance' => round($previousBalance, 2),
            'current_amount' => round($currentAmount, 2),
            'balance_after' => round($balanceAfter, 2),
            'payment_mode_total' => $modeTotal,
            'amount_in_words' => cfr_amount_words($modeTotal > 0 ? $modeTotal : $currentAmount),
            'remarks' => $remarks,
        ],
    ];
}


if (!defined('STUDENT_FEE_RECEIPT_LIBRARY_MODE')) {
    $user = require_user();

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        json_error('Unsupported request.', 405);
    }

    try {
        $payload = cfr_payload_from_ref((string)($_GET['ref'] ?? ''), $user);
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    } catch (OutOfBoundsException $e) {
        json_error($e->getMessage(), 404);
    } catch (DomainException $e) {
        json_error($e->getMessage(), 403);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }

    json_success('Fee receipt loaded.', $payload);
}
