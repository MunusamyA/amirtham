<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/college-fee-payment-service.php';

/**
 * AMIRTHAM - Common College Fee Payment Mutation API
 *
 * POST action=save
 *   receipt_type=admission|payment
 *
 * POST action=delete
 *   receipt_ref=<encrypted receipt reference>
 *
 * This is the common mutation endpoint for every Fee Payment screen.
 */

function cfp_api_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error(
            'College Fee Payment is available only for tenant users.',
            403
        );
    }

    $branchId =
        (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error(
            'No active branch is assigned to your account.',
            403
        );
    }

    $stmt = db()->prepare(
        'SELECT b.id
         FROM branches b
         INNER JOIN companies c
                 ON c.id=b.company_id
         WHERE b.id=:branch_id
           AND b.status=1
           AND c.status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    if (!$stmt->fetchColumn()) {
        json_error(
            'Your assigned tenant branch is invalid or inactive.',
            403
        );
    }

    return [
        'branch_id' => $branchId
    ];
}

function cfp_api_decode_ref(
    $value,
    string $type,
    string $label
): int {
    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        json_error(
            $label . ' reference is required.',
            422
        );
    }

    try {
        $id =
            decryptReference(
                trim($value),
                $type
            );
    } catch (Throwable $e) {
        json_error(
            'Invalid ' . $label . ' reference.',
            422
        );
    }

    if ((int)$id < 1) {
        json_error(
            'Invalid ' . $label . ' reference.',
            422
        );
    }

    return (int)$id;
}

function cfp_api_valid_date($value): ?string
{
    $value =
        trim((string)$value);

    if ($value === '') {
        return null;
    }

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

    return (
        $date &&
        $date->format('Y-m-d') === $value
    )
        ? $value
        : null;
}

function cfp_api_decimal($value): float|false
{
    $text =
        trim((string)$value);

    if ($text === '') {
        $text = '0';
    }

    if (
        !preg_match(
            '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/',
            $text
        )
    ) {
        return false;
    }

    $number =
        round((float)$text, 2);

    return $number < 0
        ? false
        : $number;
}

function cfp_api_validate_account(
    int $branchId,
    int $accountId,
    int $mode
): bool {
    $stmt = db()->prepare(
        'SELECT account_type
         FROM accounts
         WHERE id=:id
           AND branch_id=:branch_id
           AND status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $accountId,
        ':branch_id' => $branchId
    ]);

    $type =
        $stmt->fetchColumn();

    if ($type === false) {
        return false;
    }

    return $mode === 1
        ? (int)$type === 1
        : (int)$type === 2;
}

function cfp_api_validate_rows(
    array $input,
    int $branchId,
    bool $allowZero
): array {
    $errors = [];

    $raw =
        is_array(
            $input['payment_rows'] ?? null
        )
            ? $input['payment_rows']
            : [];

    $rows = [];
    $total = 0.0;
    $seen = [];

    foreach ($raw as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $mode =
            (int)($row['payment_mode'] ?? 0);

        if (
            !in_array(
                $mode,
                [1,2,3,4],
                true
            )
        ) {
            continue;
        }

        if (isset($seen[$mode])) {
            $errors['payment_rows'] =
                'Each Payment Mode can appear only once.';
            continue;
        }

        $seen[$mode] = true;

        $amount =
            cfp_api_decimal(
                $row['amount'] ?? '0'
            );

        if ($amount === false) {
            $errors[
                'payment_rows[' .
                $index .
                '][amount]'
            ] =
                'Enter a valid Payment Amount.';
            continue;
        }

        if ($amount <= 0) {
            continue;
        }

        $accountText =
            trim(
                (string)(
                    $row['account_id'] ??
                    ''
                )
            );

        $accountId =
            preg_match(
                '/^[1-9][0-9]*$/',
                $accountText
            )
                ? (int)$accountText
                : 0;

        if (
            $accountId < 1 ||
            !cfp_api_validate_account(
                $branchId,
                $accountId,
                $mode
            )
        ) {
            $errors[
                'payment_rows[' .
                $index .
                '][account_id]'
            ] =
                'Select a valid Account for this Payment Mode.';
        }

        $reference =
            trim(
                (string)(
                    $row['reference_no'] ??
                    ''
                )
            );

        $chequeNo =
            trim(
                (string)(
                    $row['cheque_no'] ??
                    ''
                )
            );

        $chequeDateText =
            trim(
                (string)(
                    $row['cheque_date'] ??
                    ''
                )
            );

        $chequeDate =
            $chequeDateText === ''
                ? null
                : cfp_api_valid_date(
                    $chequeDateText
                );

        if (
            $mode === 2 &&
            $reference === ''
        ) {
            $errors[
                'payment_rows[' .
                $index .
                '][reference_no]'
            ] =
                'UPI Reference No is required.';
        }

        if (
            $mode === 3 &&
            $reference === ''
        ) {
            $errors[
                'payment_rows[' .
                $index .
                '][reference_no]'
            ] =
                'Bank Reference No is required.';
        }

        if ($mode === 4) {
            if ($chequeNo === '') {
                $errors[
                    'payment_rows[' .
                    $index .
                    '][cheque_no]'
                ] =
                    'Cheque No is required.';
            }

            if ($chequeDate === null) {
                $errors[
                    'payment_rows[' .
                    $index .
                    '][cheque_date]'
                ] =
                    'Cheque Date is required.';
            }
        }

        if (mb_strlen($reference) > 150) {
            $errors[
                'payment_rows[' .
                $index .
                '][reference_no]'
            ] =
                'Reference No cannot exceed 150 characters.';
        }

        if (mb_strlen($chequeNo) > 100) {
            $errors[
                'payment_rows[' .
                $index .
                '][cheque_no]'
            ] =
                'Cheque No cannot exceed 100 characters.';
        }

        $rows[] = [
            'payment_mode' => $mode,
            'account_id' => $accountId,
            'amount' =>
                round((float)$amount, 2),
            'reference_no' =>
                $reference === ''
                    ? null
                    : $reference,
            'cheque_no' =>
                $chequeNo === ''
                    ? null
                    : $chequeNo,
            'cheque_date' =>
                $chequeDate,
        ];

        $total += (float)$amount;
    }

    $total =
        round($total, 2);

    if (
        !$allowZero &&
        $total <= 0.009
    ) {
        $errors['payment_rows'] =
            'Enter a Payment Amount.';
    }

    return [
        'errors' => $errors,
        'rows' => $rows,
        'total' => $total,
    ];
}

$method = request_method();

if ($method !== 'POST') {
    json_error(
        'Unsupported request.',
        405
    );
}

$input = request_data();

$action =
    strtolower(
        trim(
            (string)(
                $input['action'] ??
                'save'
            )
        )
    );

if ($action === 'delete') {
    $receiptId =
        cfp_api_decode_ref(
            $input['receipt_ref'] ??
            $input['payment_ref'] ??
            '',
            'college_fee_receipt',
            'Receipt'
        );

    $lookup = db()->prepare(
        'SELECT receipt_type
         FROM college_fee_receipts
         WHERE id=:id
         LIMIT 1'
    );

    $lookup->execute([
        ':id' => $receiptId
    ]);

    $receiptType =
        (string)$lookup->fetchColumn();

    if ($receiptType === '') {
        json_error(
            'Fee Payment receipt was not found.',
            404
        );
    }

    if ($receiptType === 'admission') {
        $access =
            require_permission(
                'admission-form.php',
                ACTION_UPDATE
            );
    } elseif ($receiptType === 'payment') {
        $access =
            require_permission(
                'student-payment.php',
                4
            );
    } else {
        json_error(
            'This receipt type cannot be deleted here.',
            409
        );
    }

    $ctx =
        cfp_api_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result =
            cfp_delete_receipt(
                $pdo,
                $branchId,
                $receiptId,
                $userId
            );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof DomainException) {
            json_error(
                $e->getMessage(),
                409
            );
        }

        throw $e;
    }

    json_success(
        $receiptType === 'admission'
            ? 'Admission Payment deleted and all EMI/Student Payment balances recalculated.'
            : 'Student Payment deleted and all EMI/Student Payment balances recalculated.',
        [
            'receipt_type' =>
                $result['receipt_type'],
        ]
    );
}

if ($action !== 'save') {
    json_error(
        'Unsupported action.',
        405
    );
}

$receiptType =
    strtolower(
        trim(
            (string)(
                $input['receipt_type'] ??
                'payment'
            )
        )
    );

if (
    !in_array(
        $receiptType,
        ['admission','payment'],
        true
    )
) {
    json_error(
        'Select a valid Receipt Type.',
        422
    );
}

$admissionId =
    cfp_api_decode_ref(
        $input['admission_ref'] ?? '',
        'college_admission',
        'Admission'
    );

$receiptRef =
    trim(
        (string)(
            $input['receipt_ref'] ??
            $input['payment_ref'] ??
            ''
        )
    );

$receiptId = null;

if ($receiptRef !== '') {
    $receiptId =
        cfp_api_decode_ref(
            $receiptRef,
            'college_fee_receipt',
            'Receipt'
        );
}

if ($receiptType === 'admission') {
    $access =
        require_permission(
            'admission-form.php',
            ACTION_UPDATE
        );
} else {
    $access =
        require_permission(
            'student-payment.php',
            $receiptId === null
                ? ACTION_CREATE
                : ACTION_UPDATE
        );
}

$ctx =
    cfp_api_context(
        $access['user']
    );

$branchId =
    (int)$ctx['branch_id'];

$userId =
    (int)$access['user']['id'];

$admissionStmt = db()->prepare(
    'SELECT id,admission_date
     FROM college_admissions
     WHERE id=:id
       AND branch_id=:branch_id
     LIMIT 1'
);

$admissionStmt->execute([
    ':id' => $admissionId,
    ':branch_id' => $branchId
]);

$admission =
    $admissionStmt->fetch(PDO::FETCH_ASSOC);

if (!$admission) {
    json_error(
        'Admission was not found.',
        404
    );
}

$receiptDate =
    cfp_api_valid_date(
        $input['receipt_date'] ??
        $admission['admission_date']
    );

if ($receiptDate === null) {
    json_error(
        'Enter a valid Payment Date.',
        422,
        [
            'receipt_date' =>
                'Enter a valid Payment Date.'
        ]
    );
}

if (
    $receiptDate <
    (string)$admission['admission_date']
) {
    json_error(
        'Payment Date cannot be before Admission Date.',
        422,
        [
            'receipt_date' =>
                'Payment Date cannot be before Admission Date.'
        ]
    );
}

$rowValidation =
    cfp_api_validate_rows(
        $input,
        $branchId,
        $receiptType === 'admission'
    );

if (
    $rowValidation['errors'] !== []
) {
    json_error(
        'Fee Payment validation failed.',
        422,
        $rowValidation['errors']
    );
}

$pdo = db();
$pdo->beginTransaction();

try {
    $plan =
        cfp_plan_by_admission_for_update(
            $pdo,
            $branchId,
            $admissionId
        );

    if ($receiptType === 'admission') {
        if ($receiptId !== null) {
            $existing =
                cfp_receipt_for_update(
                    $pdo,
                    $branchId,
                    $receiptId
                );

            if (
                (string)$existing['receipt_type'] !== 'admission' ||
                (int)$existing['admission_id'] !== $admissionId ||
                (int)$existing['student_fee_plan_id'] !==
                    (int)$plan['id']
            ) {
                throw new DomainException(
                    'Admission Payment receipt does not belong to this Admission.'
                );
            }
        }

        $result =
            cfp_save_admission_receipt(
                $pdo,
                $branchId,
                $admissionId,
                (int)$plan['id'],
                $receiptDate,
                $rowValidation['rows'],
                $userId,
                'Payment collected during Student Admission'
            );
    } else {
        $paymentAgainst =
            strtolower(
                trim(
                    (string)(
                        $input['payment_against'] ??
                        'overall'
                    )
                )
            );

        $targetInstallmentId = null;

        if ($paymentAgainst === 'emi') {
            $targetInstallmentId =
                cfp_api_decode_ref(
                    $input[
                        'target_installment_ref'
                    ] ?? '',
                    'college_fee_installment',
                    'EMI'
                );
        }

        $notes =
            trim(
                (string)(
                    $input['notes'] ??
                    ''
                )
            );

        if (mb_strlen($notes) > 255) {
            throw new DomainException(
                'Notes cannot exceed 255 characters.'
            );
        }

        $result =
            cfp_save_student_payment(
                $pdo,
                $branchId,
                $admissionId,
                (int)$plan['id'],
                $receiptDate,
                $paymentAgainst,
                $targetInstallmentId,
                $rowValidation['rows'],
                $notes,
                $userId,
                $receiptId
            );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException) {
        json_error(
            $e->getMessage(),
            409
        );
    }

    if (
        $e instanceof PDOException &&
        $e->getCode() === '23000'
    ) {
        json_error(
            'Fee Payment could not be saved because a duplicate or linked value already exists.',
            409
        );
    }

    throw $e;
}

json_success(
    $receiptType === 'admission'
        ? 'Admission Payment updated and all EMI/Student Payment balances recalculated.'
        : (
            $receiptId === null
                ? 'Student Payment saved and balances recalculated.'
                : 'Student Payment updated and all EMI/Student Payment balances recalculated.'
        ),
    [
        'receipt_ref' =>
            $result['receipt_id']
                ? encryptReference(
                    'college_fee_receipt',
                    (int)$result['receipt_id']
                )
                : null,
        'amount' =>
            (float)$result['amount'],
    ]
);
