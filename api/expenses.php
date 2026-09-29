<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/expense-finance.php';

const EXPENSE_MODULE_CODE = 'food_supplementary';

function ex_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Expense is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,b.state_code,
                c.company_name
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
        'state_code' => (string)($row['state_code'] ?? ''),
        'company_name' => (string)$row['company_name'],
    ];
}

function ex_require_schema(): void
{
    foreach (['expenses','expense_categories','accounts','food_expense_payments','food_expense_payment_details'] as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error(
                'Expense schema is incomplete. Missing table: ' . $table .
                '. Run sql/expense-payment-daily-ledger.sql first.',
                500
            );
        }
    }

    foreach ([
        'expense_no','invoice_no','invoice_date','payee_gstin','payee_state_code',
        'gst_rate','paid_amount','balance_amount','payment_status'
    ] as $column) {
        $stmt = db()->query('SHOW COLUMNS FROM expenses LIKE ' . db()->quote($column));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error(
                'Expense schema is incomplete. Missing expenses.' . $column .
                '. Run sql/expense-payment-daily-ledger.sql first.',
                500
            );
        }
    }
}

function ex_ref_to_id($value, string $type = 'expense', string $label = 'Expense'): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error($label . ' reference is required.', 422);
    }

    try {
        return decryptReference(trim($value), $type);
    } catch (Throwable $e) {
        json_error('Invalid ' . $label . ' reference.', 422);
    }
    return 0;
}

function ex_nullable($value, int $max = 255): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : mb_substr($value, 0, $max);
}

function ex_valid_date($value, string $field, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if (!$required) {
            return null;
        }
        json_error('Enter a valid date.', 422, [$field => 'Date is required.']);
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date ||
        ($errors && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) ||
        $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}

function ex_money($value, string $label, string $field): float
{
    if ($value === '' || $value === null) {
        return 0.0;
    }
    if (!is_numeric($value)) {
        json_error($label . ' must be a valid amount.', 422, [$field => 'Enter a valid amount.']);
    }
    $amount = round((float)$value, 2);
    if ($amount < 0) {
        json_error($label . ' cannot be negative.', 422, [$field => 'Enter zero or greater.']);
    }
    return $amount;
}

function ex_rate($value): float
{
    if ($value === '' || $value === null) {
        return 0.0;
    }
    if (!is_numeric($value)) {
        json_error('GST Rate must be numeric.', 422, ['gst_rate' => 'Enter a valid GST Rate.']);
    }
    $rate = round((float)$value, 3);
    if ($rate < 0 || $rate > 100) {
        json_error('GST Rate must be between 0 and 100.', 422, ['gst_rate' => 'Enter 0 to 100.']);
    }
    return $rate;
}

function ex_categories(int $companyId, bool $includeInactive = false): array
{
    $sql = 'SELECT id,category_code,category_name,module_code,status
            FROM expense_categories
            WHERE company_id=:company_id
              AND (module_code IS NULL OR module_code=:module_code)';
    if (!$includeInactive) {
        $sql .= ' AND status=1';
    }
    $sql .= ' ORDER BY category_name,category_code';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':company_id' => $companyId,
        ':module_code' => EXPENSE_MODULE_CODE,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'category_code' => (string)$row['category_code'],
            'category_name' => (string)$row['category_name'],
            'status' => (int)$row['status'],
        ];
    }
    return $rows;
}

function ex_category(int $companyId, int $categoryId): array
{
    $stmt = db()->prepare(
        'SELECT id,category_code,category_name,status
         FROM expense_categories
         WHERE id=:id
           AND company_id=:company_id
           AND (module_code IS NULL OR module_code=:module_code)
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $categoryId,
        ':company_id' => $companyId,
        ':module_code' => EXPENSE_MODULE_CODE,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['status'] !== 1) {
        json_error('Selected Expense Category is inactive or unavailable.', 422, [
            'expense_category_id' => 'Select an active Expense Category.',
        ]);
    }
    return $row;
}

function ex_generate_no(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT expense_no
         FROM expenses
         WHERE branch_id=:branch_id
           AND expense_no REGEXP '^EXP[0-9]+$'
         ORDER BY CAST(SUBSTRING(expense_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^EXP([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }
    return 'EXP' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}


function ex_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,is_default_cash,status
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'account_code' => (string)$row['account_code'],
            'account_name' => (string)$row['account_name'],
            'account_type' => (int)$row['account_type'],
            'is_default_cash' => (int)($row['is_default_cash'] ?? 0),
        ];
    }
    return $rows;
}

function ex_payment_account(PDO $pdo, int $branchId, int $accountId, int $requiredType): void
{
    $stmt = $pdo->prepare(
        'SELECT id,account_type,status
         FROM accounts
         WHERE id=:id AND branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['status'] !== 1) {
        json_error('Selected payment Account is inactive or unavailable.', 422);
    }
    if ((int)$row['account_type'] !== $requiredType) {
        json_error(
            $requiredType === 1
                ? 'Cash payment requires a Cash Account.'
                : 'UPI, Bank and Cheque require a Bank Account.',
            422
        );
    }
}

function ex_optional_payment_details(PDO $pdo, int $branchId, array $data): array
{
    $raw = $data['payments'] ?? [];
    if (!is_array($raw)) {
        json_error('Payment details must be a list.', 422);
    }

    $rows = [];
    $seen = [];
    $total = 0.0;

    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $mode = (int)($item['payment_mode'] ?? 0);
        if (!in_array($mode, [1,2,3,4], true)) {
            continue;
        }

        $amount = ex_money($item['amount'] ?? 0, 'Payment Amount', 'payments');
        if ($amount <= 0.001) {
            continue;
        }
        if (isset($seen[$mode])) {
            json_error('Only one row is allowed for each Payment Mode.', 422);
        }
        $seen[$mode] = true;

        $accountId = (int)($item['account_id'] ?? 0);
        if ($accountId < 1) {
            json_error('Select an Account for every entered Payment Amount.', 422);
        }
        ex_payment_account($pdo, $branchId, $accountId, $mode === 1 ? 1 : 2);

        $reference = ex_nullable($item['reference_no'] ?? null, 150);
        $chequeNo = null;
        $chequeDate = null;
        if ($mode === 4) {
            $chequeNo = ex_nullable($item['cheque_no'] ?? null, 100);
            if ($chequeNo === null) {
                json_error('Cheque Number is required when Cheque Amount is entered.', 422);
            }
            $chequeDate = ex_valid_date($item['cheque_date'] ?? '', 'cheque_date', true);
        }

        $rows[] = [
            'payment_mode' => $mode,
            'account_id' => $accountId,
            'amount' => $amount,
            'reference_no' => $reference,
            'cheque_no' => $chequeNo,
            'cheque_date' => $chequeDate,
        ];
        $total = round($total + $amount, 2);
    }

    return ['rows' => $rows, 'total' => $total];
}

function ex_generate_payment_no(PDO $pdo, int $branchId): string
{
    $stmt = $pdo->prepare(
        "SELECT payment_no
         FROM food_expense_payments
         WHERE branch_id=:branch_id
           AND payment_no REGEXP '^EPY[0-9]+$'
         ORDER BY CAST(SUBSTRING(payment_no,4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^EPY([0-9]+)$/i', $last, $match)) {
        $next = ((int)$match[1]) + 1;
    }
    return 'EPY' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function ex_insert_payment_details(PDO $pdo, int $branchId, int $paymentId, int $userId, array $rows): void
{
    if (!$rows) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO food_expense_payment_details
         (expense_payment_id,branch_id,payment_mode,account_id,amount,reference_no,
          cheque_no,cheque_date,status,created_by,created_at,updated_at)
         VALUES
         (:expense_payment_id,:branch_id,:payment_mode,:account_id,:amount,:reference_no,
          :cheque_no,:cheque_date,1,:created_by,NOW(),NOW())'
    );
    foreach ($rows as $row) {
        $stmt->execute([
            ':expense_payment_id' => $paymentId,
            ':branch_id' => $branchId,
            ':payment_mode' => $row['payment_mode'],
            ':account_id' => $row['account_id'],
            ':amount' => $row['amount'],
            ':reference_no' => $row['reference_no'],
            ':cheque_no' => $row['cheque_no'],
            ':cheque_date' => $row['cheque_date'],
            ':created_by' => $userId,
        ]);
    }
}

function ex_calculate(array $ctx, array $data): array
{
    $categoryId = (int)($data['expense_category_id'] ?? 0);
    if ($categoryId < 1) {
        json_error('Expense Category is required.', 422, [
            'expense_category_id' => 'Select Expense Category.',
        ]);
    }
    $category = ex_category((int)$ctx['company_id'], $categoryId);

    $expenseDate = ex_valid_date($data['expense_date'] ?? '', 'expense_date', true);
    $payee = trim((string)($data['payee_name'] ?? ''));
    if ($payee === '') {
        json_error('Payee Name is required.', 422, ['payee_name' => 'Enter Payee Name.']);
    }
    $payee = mb_substr($payee, 0, 150);

    $invoiceNo = ex_nullable($data['invoice_no'] ?? null, 100);
    $invoiceDate = ex_valid_date($data['invoice_date'] ?? '', 'invoice_date', false);
    $description = ex_nullable($data['description'] ?? null, 255);

    $taxMode = (int)($data['tax_mode'] ?? 1) === 0 ? 0 : 1;
    $taxableAmount = ex_money($data['taxable_amount'] ?? 0, 'Expense Amount', 'taxable_amount');
    if ($taxableAmount <= 0.001) {
        json_error('Expense Amount must be greater than zero.', 422, [
            'taxable_amount' => 'Enter an amount greater than zero.',
        ]);
    }

    $gstRate = $taxMode === 1 ? ex_rate($data['gst_rate'] ?? 0) : 0.0;
    $gstin = strtoupper(trim((string)($data['payee_gstin'] ?? '')));
    if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        json_error('Enter a valid 15-character GSTIN.', 422, ['payee_gstin' => 'Invalid GSTIN.']);
    }
    $gstin = $gstin === '' ? null : $gstin;

    $payeeState = trim((string)($data['payee_state_code'] ?? ''));
    if ($payeeState === '' && $gstin !== null && preg_match('/^[0-9]{2}/', $gstin, $match)) {
        $payeeState = $match[0];
    }
    if ($payeeState !== '' && !preg_match('/^[0-9]{2}$/', $payeeState)) {
        json_error('Payee State Code must contain exactly 2 digits.', 422, [
            'payee_state_code' => 'Enter a 2-digit State Code.',
        ]);
    }

    $cgst = 0.0;
    $sgst = 0.0;
    $igst = 0.0;
    $supplyType = null;

    if ($taxMode === 1 && $gstRate > 0.0001) {
        $branchState = trim((string)($ctx['state_code'] ?? ''));
        if (!preg_match('/^[0-9]{2}$/', $branchState)) {
            json_error('Branch State Code is required for GST Expense calculation.', 422);
        }
        if (!preg_match('/^[0-9]{2}$/', $payeeState)) {
            json_error('Payee State Code is required in GST mode.', 422, [
                'payee_state_code' => 'Enter Payee State Code or Payee GSTIN.',
            ]);
        }

        if ($branchState === $payeeState) {
            $supplyType = 'intra_state';
            $halfRate = $gstRate / 2;
            $cgst = round($taxableAmount * $halfRate / 100, 2);
            $sgst = round($taxableAmount * ($gstRate - $halfRate) / 100, 2);
        } else {
            $supplyType = 'inter_state';
            $igst = round($taxableAmount * $gstRate / 100, 2);
        }
    }

    $total = round($taxableAmount + $cgst + $sgst + $igst, 2);

    return [
        'expense_category_id' => $categoryId,
        'category' => $category,
        'expense_date' => $expenseDate,
        'payee_name' => $payee,
        'invoice_no' => $invoiceNo,
        'invoice_date' => $invoiceDate,
        'payee_gstin' => $gstin,
        'payee_state_code' => $payeeState === '' ? null : $payeeState,
        'description' => $description,
        'tax_mode' => $taxMode,
        'gst_rate' => $gstRate,
        'supply_type' => $supplyType,
        'taxable_amount' => $taxableAmount,
        'cgst_amount' => $cgst,
        'sgst_amount' => $sgst,
        'igst_amount' => $igst,
        'total_amount' => $total,
    ];
}

function ex_record(array $ctx, int $id): array
{
    $row = ef_expense_snapshot(db(), (int)$ctx['branch_id'], $id);
    if (!$row) {
        json_error('Expense was not found.', 404);
    }

    $row['ref'] = encryptReference('expense', (int)$row['id']);
    return $row;
}

function ex_payment_count(PDO $pdo, int $branchId, int $expenseId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM food_expense_payments
         WHERE branch_id=:branch_id
           AND expense_id=:expense_id
           AND posting_status=1
           AND status=1
           AND reversed_at IS NULL'
    );
    $stmt->execute([
        ':branch_id' => $branchId,
        ':expense_id' => $expenseId,
    ]);
    return (int)$stmt->fetchColumn();
}

$method = request_method();
ex_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('expense-form.php', ACTION_VIEW);
    $ctx = ex_tenant_context($access['user']);

    json_success('Expense options loaded.', [
        'allowed_actions' => $access['actions'],
        'categories' => ex_categories((int)$ctx['company_id']),
        'accounts' => ex_accounts((int)$ctx['branch_id']),
        'next_expense_no' => ex_generate_no(db(), (int)$ctx['branch_id']),
        'today' => date('Y-m-d'),
        'branch' => $ctx,
        'payment_actions' => effective_actions_for_menu($access['user'], menu_by_path('expense-payment.php')),
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('expense-form.php', ACTION_VIEW);
    $ctx = ex_tenant_context($access['user']);
    $id = ex_ref_to_id($_GET['ref'] ?? '');

    json_success('Expense loaded.', [
        'expense' => ex_record($ctx, $id),
        'allowed_actions' => $access['actions'],
        'categories' => ex_categories((int)$ctx['company_id'], true),
        'accounts' => ex_accounts((int)$ctx['branch_id']),
        'branch' => $ctx,
        'payment_actions' => effective_actions_for_menu($access['user'], menu_by_path('expense-payment.php')),
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('expense-list.php', ACTION_VIEW);
    $ctx = ex_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $formActions = effective_actions_for_menu($access['user'], menu_by_path('expense-form.php'));
    $paymentActions = effective_actions_for_menu($access['user'], menu_by_path('expense-payment.php'));

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length = $lengthRaw < 0
        ? 100000
        : max(1, min(100000, $lengthRaw));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $categoryId = max(0, (int)($_GET['category_id'] ?? 0));
    $postingStatus = isset($_GET['posting_status']) && $_GET['posting_status'] !== '' ? (int)$_GET['posting_status'] : -1;
    $paymentStatus = isset($_GET['payment_status']) && $_GET['payment_status'] !== '' ? (int)$_GET['payment_status'] : 0;
    $taxMode = isset($_GET['tax_mode']) && $_GET['tax_mode'] !== '' ? (int)$_GET['tax_mode'] : -1;

    $where = ['e.branch_id=:branch_id', 'e.status=1'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(e.expense_no LIKE :s_no OR ec.category_name LIKE :s_category OR e.payee_name LIKE :s_payee OR e.invoice_no LIKE :s_invoice OR e.description LIKE :s_desc)';
        $params += [
            ':s_no' => $like,
            ':s_category' => $like,
            ':s_payee' => $like,
            ':s_invoice' => $like,
            ':s_desc' => $like,
        ];
    }
    if ($dateFrom !== '') {
        $where[] = 'e.expense_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'e.expense_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }
    if ($categoryId > 0) {
        $where[] = 'e.expense_category_id=:category_id';
        $params[':category_id'] = $categoryId;
    }
    if (in_array($postingStatus, [0,1], true)) {
        $where[] = 'e.posting_status=:posting_status';
        $params[':posting_status'] = $postingStatus;
    }
    if (in_array($paymentStatus, [1,2,3], true)) {
        $where[] = 'e.payment_status=:payment_status';
        $params[':payment_status'] = $paymentStatus;
    }
    if (in_array($taxMode, [0,1], true)) {
        $where[] = 'e.tax_mode=:tax_mode';
        $params[':tax_mode'] = $taxMode;
    }

    $from = ' FROM expenses e INNER JOIN expense_categories ec ON ec.id=e.expense_category_id';

    $totalStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE e.branch_id=:branch_id AND e.status=1');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS expense_count,
            COALESCE(SUM(e.total_amount), 0) AS total_amount,
            COALESCE(SUM(e.balance_amount), 0) AS balance_amount
         ' . $from . '
         WHERE ' . implode(' AND ', $where)
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $columns = [
        'e.expense_no','e.expense_date','ec.category_name','e.payee_name',
        'e.total_amount','e.paid_amount','e.balance_amount','e.payment_status',
        'e.tax_mode','e.posting_status'
    ];
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 1);
    $direction = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = $columns[$orderColumn] ?? 'e.expense_date';

    $sql = 'SELECT e.id,e.expense_no,e.expense_date,e.payee_name,e.invoice_no,
                   e.total_amount,e.paid_amount,e.balance_amount,e.payment_status,
                   e.tax_mode,e.posting_status,ec.category_code,ec.category_name' .
           $from . ' WHERE ' . implode(' AND ', $where) .
           ' ORDER BY ' . $orderBy . ' ' . $direction . ',e.id DESC LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $isInt = in_array($key, [':branch_id',':category_id',':posting_status',':payment_status',':tax_mode'], true);
        $stmt->bindValue($key, $value, $isInt ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach (['total_amount','paid_amount','balance_amount'] as $key) {
            $row[$key] = (float)$row[$key];
        }
        foreach (['payment_status','tax_mode','posting_status'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $row['payment_status_label'] = ef_payment_status_label((int)$row['payment_status']);
        $row['tax_mode_label'] = (int)$row['tax_mode'] === 1 ? 'GST' : 'NON GST';
        $row['posting_status_label'] = (int)$row['posting_status'] === 1 ? 'Posted' : 'Draft';
        $row['ref'] = encryptReference('expense', (int)$row['id']);
        $row['edit_url'] = 'expense-form.php?ref=' . rawurlencode($row['ref']);
        $row['view_url'] = $row['edit_url'] . '&view=1';
        $row['payment_url'] = 'expense-payment.php?expense=' . rawurlencode($row['ref']) . '&lock=1';
        unset($row['id']);
        $rows[] = $row;
    }

    json_success('Expenses loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'summary' => [
            'expense_count' => (int)($summary['expense_count'] ?? 0),
            'total_amount' => (float)($summary['total_amount'] ?? 0),
            'balance_amount' => (float)($summary['balance_amount'] ?? 0),
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
        'payment_actions' => $paymentActions,
        'categories' => ex_categories((int)$ctx['company_id']),
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'delete') {
        $access = require_permission('expense-form.php', 4);
        $ctx = ex_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = ex_ref_to_id($data['ref'] ?? '');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM expenses
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                json_error('Expense was not found.', 404);
            }

            if (ex_payment_count($pdo, $branchId, $id) > 0) {
                json_error(
                    'This Expense has posted payments. Delete/reverse its Expense Payments first.',
                    409
                );
            }

            $pdo->prepare(
                'UPDATE expenses
                 SET posting_status=0,status=0,reversed_at=NOW(),reversed_by=:user_id,
                     paid_amount=0,balance_amount=0,payment_status=1,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':user_id' => $userId,
                ':id' => $id,
                ':branch_id' => $branchId,
            ]);

            $pdo->commit();
            audit_log($userId, 4, [
                'company_id' => $ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
            ]);
            json_success('Expense deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Expense action.', 404);
    }

    $isEdit = !empty($data['ref']);
    $access = require_permission('expense-form.php', $isEdit ? ACTION_UPDATE : ACTION_CREATE);
    $ctx = ex_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $posting = (int)($data['posting_status'] ?? 0) === 1 ? 1 : 0;
    $calc = ex_calculate($ctx, $data);
    $pdo = db();

    $initialPayment = ['rows' => [], 'total' => 0.0];
    $paymentDate = null;
    if ($posting === 1) {
        $initialPayment = ex_optional_payment_details($pdo, $branchId, $data);
        if ((float)$initialPayment['total'] > (float)$calc['total_amount'] + 0.001) {
            json_error('Payment Amount cannot exceed Total Expense.', 422, [
                'payments' => 'Maximum payment is ' . number_format((float)$calc['total_amount'], 2, '.', '') . '.',
            ]);
        }
        if ((float)$initialPayment['total'] > 0.001) {
            $paymentActions = effective_actions_for_menu($access['user'], menu_by_path('expense-payment.php'));
            if (!in_array(ACTION_CREATE, array_map('intval', $paymentActions), true)) {
                json_error('You do not have permission to create Expense Payments.', 403);
            }
            $paymentDate = ex_valid_date($data['payment_date'] ?? $calc['expense_date'], 'payment_date', true);
        }
    }

    $pdo->beginTransaction();

    try {
        $id = 0;
        $old = null;
        $expenseNo = '';

        if ($isEdit) {
            $id = ex_ref_to_id($data['ref']);
            $stmt = $pdo->prepare(
                'SELECT * FROM expenses
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                json_error('Expense was not found.', 404);
            }
            if ((int)$old['posting_status'] === 1) {
                json_error('Posted Expense is read-only. Create or edit Expense Payments instead.', 409);
            }
            $expenseNo = (string)$old['expense_no'];
        } else {
            $expenseNo = ex_generate_no($pdo, $branchId);
        }

        $params = [
            ':branch_id' => $branchId,
            ':module_code' => EXPENSE_MODULE_CODE,
            ':expense_category_id' => $calc['expense_category_id'],
            ':expense_no' => $expenseNo,
            ':expense_date' => $calc['expense_date'],
            ':payee_name' => $calc['payee_name'],
            ':invoice_no' => $calc['invoice_no'],
            ':invoice_date' => $calc['invoice_date'],
            ':payee_gstin' => $calc['payee_gstin'],
            ':payee_state_code' => $calc['payee_state_code'],
            ':description' => $calc['description'],
            ':tax_mode' => $calc['tax_mode'],
            ':gst_rate' => $calc['gst_rate'],
            ':taxable_amount' => $calc['taxable_amount'],
            ':cgst_amount' => $calc['cgst_amount'],
            ':sgst_amount' => $calc['sgst_amount'],
            ':igst_amount' => $calc['igst_amount'],
            ':total_amount' => $calc['total_amount'],
            ':posting_status' => $posting,
        ];

        if ($isEdit) {
            $sql = 'UPDATE expenses
                    SET module_code=:module_code,
                        expense_category_id=:expense_category_id,
                        expense_no=:expense_no,
                        expense_date=:expense_date,
                        payee_name=:payee_name,
                        invoice_no=:invoice_no,
                        invoice_date=:invoice_date,
                        payee_gstin=:payee_gstin,
                        payee_state_code=:payee_state_code,
                        description=:description,
                        tax_mode=:tax_mode,
                        gst_rate=:gst_rate,
                        taxable_amount=:taxable_amount,
                        cgst_amount=:cgst_amount,
                        sgst_amount=:sgst_amount,
                        igst_amount=:igst_amount,
                        total_amount=:total_amount,
                        paid_amount=0,
                        balance_amount=:initial_balance,
                        payment_status=1,
                        payment_mode=NULL,
                        payment_reference=NULL,
                        posting_status=:posting_status,
                        reversed_at=NULL,reversed_by=NULL,status=1,updated_at=NOW()
                    WHERE id=:id AND branch_id=:branch_id';
            $params[':initial_balance'] = $posting ? $calc['total_amount'] : 0.0;
            $params[':id'] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = 'INSERT INTO expenses(
                        branch_id,module_code,expense_category_id,expense_no,expense_date,
                        payee_name,invoice_no,invoice_date,payee_gstin,payee_state_code,
                        description,tax_mode,gst_rate,taxable_amount,cgst_amount,sgst_amount,
                        igst_amount,total_amount,paid_amount,balance_amount,payment_status,
                        payment_mode,payment_reference,attachment_meta,posting_status,
                        reversed_at,reversed_by,status,created_by,created_at,updated_at
                    ) VALUES(
                        :branch_id,:module_code,:expense_category_id,:expense_no,:expense_date,
                        :payee_name,:invoice_no,:invoice_date,:payee_gstin,:payee_state_code,
                        :description,:tax_mode,:gst_rate,:taxable_amount,:cgst_amount,:sgst_amount,
                        :igst_amount,:total_amount,0,:initial_balance,1,
                        NULL,NULL,NULL,:posting_status,
                        NULL,NULL,1,:created_by,NOW(),NOW()
                    )';
            $params[':initial_balance'] = $posting ? $calc['total_amount'] : 0.0;
            $params[':created_by'] = $userId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $id = (int)$pdo->lastInsertId();
        }

        $initialPaymentId = 0;
        if ($posting && (float)$initialPayment['total'] > 0.001) {
            $paymentNo = ex_generate_payment_no($pdo, $branchId);
            $paymentStmt = $pdo->prepare(
                'INSERT INTO food_expense_payments(
                    branch_id,expense_id,payment_no,payment_date,amount,notes,
                    posting_status,reversed_at,reversed_by,status,created_by,created_at,updated_at
                 ) VALUES(
                    :branch_id,:expense_id,:payment_no,:payment_date,:amount,:notes,
                    1,NULL,NULL,1,:created_by,NOW(),NOW()
                 )'
            );
            $paymentStmt->execute([
                ':branch_id' => $branchId,
                ':expense_id' => $id,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':amount' => $initialPayment['total'],
                ':notes' => 'Payment entered while posting Expense ' . $expenseNo,
                ':created_by' => $userId,
            ]);
            $initialPaymentId = (int)$pdo->lastInsertId();
            ex_insert_payment_details($pdo, $branchId, $initialPaymentId, $userId, $initialPayment['rows']);
        }

        if ($posting) {
            ef_refresh_expense($pdo, $branchId, $id);
        }

        $pdo->commit();

        audit_log($userId, $isEdit ? ACTION_UPDATE : ACTION_CREATE, [
            'company_id' => $ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]);

        json_success(
            $posting
                ? 'Expense posted successfully.'
                : ($isEdit ? 'Expense draft updated successfully.' : 'Expense draft saved successfully.'),
            [
                'expense' => ex_record($ctx, $id),
                'initial_payment_ref' => $initialPaymentId > 0
                    ? encryptReference('expense_payment', $initialPaymentId)
                    : null,
            ],
            $isEdit ? 200 : 201
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

json_error('Method not allowed.', 405);
