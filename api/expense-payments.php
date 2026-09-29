<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/expense-finance.php';

function ep_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Expense Payment is available only for tenant users.', 403);
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

function ep_require_schema(): void
{
    foreach (['expenses','expense_categories','accounts','food_expense_payments','food_expense_payment_details'] as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error(
                'Expense Payment schema is incomplete. Missing table: ' . $table .
                '. Run sql/expense-payment-daily-ledger.sql first.',
                500
            );
        }
    }
}

function ep_ref_to_id($value, string $type, string $label): int
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

function ep_nullable($value, int $max = 255): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : mb_substr($value, 0, $max);
}

function ep_valid_date($value, string $field, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if (!$required) {
            return null;
        }
        json_error('Payment Date is required.', 422, [$field => 'Date is required.']);
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date ||
        ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) ||
        $date->format('Y-m-d') !== $value) {
        json_error('Enter a valid Payment Date.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}

function ep_money($value, string $label = 'Payment Amount'): float
{
    if ($value === '' || $value === null) {
        return 0.0;
    }
    if (!is_numeric($value)) {
        json_error($label . ' must be numeric.', 422);
    }
    $amount = round((float)$value, 2);
    if ($amount < 0) {
        json_error($label . ' cannot be negative.', 422);
    }
    return $amount;
}

function ep_accounts(int $branchId): array
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

function ep_account(PDO $pdo, int $branchId, int $accountId, int $requiredType): void
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
        json_error('Selected Account is inactive or unavailable.', 422);
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

function ep_parse_details(PDO $pdo, int $branchId, array $data): array
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

        $amount = ep_money($item['amount'] ?? 0);
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
        ep_account($pdo, $branchId, $accountId, $mode === 1 ? 1 : 2);

        $reference = ep_nullable($item['reference_no'] ?? null, 150);
        $chequeNo = null;
        $chequeDate = null;
        if ($mode === 4) {
            $chequeNo = ep_nullable($item['cheque_no'] ?? null, 100);
            if ($chequeNo === null) {
                json_error('Cheque Number is required when Cheque Amount is entered.', 422);
            }
            $chequeDate = ep_valid_date($item['cheque_date'] ?? '', 'cheque_date', true);
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

    if ($total <= 0.001) {
        json_error('Enter at least one Payment Amount.', 422, ['payments' => 'Payment Amount is required.']);
    }

    return ['rows' => $rows, 'total' => $total];
}

function ep_generate_no(PDO $pdo, int $branchId): string
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

function ep_expenses(int $branchId, int $excludePaymentId = 0): array
{
    $stmt = db()->prepare(
        'SELECT id
         FROM expenses
         WHERE branch_id=:branch_id
           AND posting_status=1
           AND status=1
           AND reversed_at IS NULL
         ORDER BY expense_date DESC,id DESC'
    );
    $stmt->execute([':branch_id' => $branchId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $expenseId) {
        $ctx = ef_expense_context(db(), $branchId, (int)$expenseId, $excludePaymentId);
        if (!$ctx || (float)$ctx['balance_amount'] <= 0.001) {
            continue;
        }
        $rows[] = $ctx;
    }
    return $rows;
}

function ep_payment_record(PDO $pdo, int $branchId, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*,e.expense_no,e.expense_date,e.payee_name,ec.category_name
         FROM food_expense_payments p
         INNER JOIN expenses e ON e.id=p.expense_id AND e.branch_id=p.branch_id
         INNER JOIN expense_categories ec ON ec.id=e.expense_category_id
         WHERE p.id=:id AND p.branch_id=:branch_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_error('Expense Payment was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['expense_id'] = (int)$row['expense_id'];
    $row['amount'] = (float)$row['amount'];
    $row['posting_status'] = (int)$row['posting_status'];
    $row['status'] = (int)$row['status'];
    $row['ref'] = encryptReference('expense_payment', (int)$row['id']);
    $row['expense_ref'] = encryptReference('expense', (int)$row['expense_id']);

    $detailStmt = $pdo->prepare(
        'SELECT d.*,a.account_code,a.account_name,a.account_type
         FROM food_expense_payment_details d
         INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
         WHERE d.expense_payment_id=:id
           AND d.branch_id=:branch_id
           AND d.status=1
         ORDER BY d.payment_mode,d.id'
    );
    $detailStmt->execute([':id' => $id, ':branch_id' => $branchId]);

    $details = [];
    foreach ($detailStmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
        $details[] = [
            'id' => (int)$detail['id'],
            'payment_mode' => (int)$detail['payment_mode'],
            'account_id' => (int)$detail['account_id'],
            'account_name' => (string)$detail['account_name'],
            'amount' => (float)$detail['amount'],
            'reference_no' => (string)($detail['reference_no'] ?? ''),
            'cheque_no' => (string)($detail['cheque_no'] ?? ''),
            'cheque_date' => (string)($detail['cheque_date'] ?? ''),
        ];
    }
    $row['payments'] = $details;

    return $row;
}

function ep_insert_details(PDO $pdo, int $branchId, int $paymentId, int $userId, array $rows): void
{
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

function ep_undo_details(PDO $pdo, int $branchId, int $paymentId, bool $remove): void
{
    if ($remove) {
        $pdo->prepare(
            'DELETE FROM food_expense_payment_details
             WHERE expense_payment_id=:id AND branch_id=:branch_id'
        )->execute([':id' => $paymentId, ':branch_id' => $branchId]);
        return;
    }

    $pdo->prepare(
        'UPDATE food_expense_payment_details
         SET status=0,updated_at=NOW()
         WHERE expense_payment_id=:id
           AND branch_id=:branch_id
           AND status=1'
    )->execute([':id' => $paymentId, ':branch_id' => $branchId]);
}

function ep_mode_label(int $mode): string
{
    return [1 => 'Cash', 2 => 'UPI', 3 => 'Bank Transfer', 4 => 'Cheque'][$mode] ?? 'Payment';
}

$method = request_method();
ep_require_schema();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('expense-payment.php', ACTION_VIEW);
    $ctx = ep_tenant_context($access['user']);

    json_success('Expense Payment options loaded.', [
        'allowed_actions' => $access['actions'],
        'expenses' => ep_expenses((int)$ctx['branch_id']),
        'accounts' => ep_accounts((int)$ctx['branch_id']),
        'today' => date('Y-m-d'),
    ]);
}

if ($method === 'GET' && isset($_GET['expense_context'])) {
    $access = require_permission('expense-payment.php', ACTION_VIEW);
    $ctx = ep_tenant_context($access['user']);
    $expenseId = ep_ref_to_id($_GET['expense_ref'] ?? '', 'expense', 'Expense');
    $excludePaymentId = 0;
    if (!empty($_GET['payment_ref'])) {
        $excludePaymentId = ep_ref_to_id($_GET['payment_ref'], 'expense_payment', 'Expense Payment');
    }

    $expense = ef_expense_context(
        db(),
        (int)$ctx['branch_id'],
        $expenseId,
        $excludePaymentId
    );
    if (!$expense) {
        json_error('Selected Expense is unavailable or has no payable balance.', 422);
    }
    json_success('Expense outstanding loaded.', $expense);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('expense-payment.php', ACTION_VIEW);
    $ctx = ep_tenant_context($access['user']);
    $id = ep_ref_to_id($_GET['ref'], 'expense_payment', 'Expense Payment');
    $record = ep_payment_record(db(), (int)$ctx['branch_id'], $id);

    json_success('Expense Payment loaded.', [
        'payment' => $record,
        'allowed_actions' => $access['actions'],
        'accounts' => ep_accounts((int)$ctx['branch_id']),
        'expenses' => ep_expenses((int)$ctx['branch_id'], $id),
        'expense_context' => ef_expense_context(
            db(),
            (int)$ctx['branch_id'],
            (int)$record['expense_id'],
            $id
        ),
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('expense-payment-list.php', ACTION_VIEW);
    $ctx = ep_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $formActions = effective_actions_for_menu($access['user'], menu_by_path('expense-payment.php'));

    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = max(1, min(100000, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $where = ['p.branch_id=:branch_id', 'p.status=1'];
    $params = [':branch_id' => $branchId];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(p.payment_no LIKE :s_payment OR e.expense_no LIKE :s_expense OR ec.category_name LIKE :s_category OR e.payee_name LIKE :s_payee OR p.notes LIKE :s_notes)';
        $params += [
            ':s_payment' => $like,
            ':s_expense' => $like,
            ':s_category' => $like,
            ':s_payee' => $like,
            ':s_notes' => $like,
        ];
    }
    if ($dateFrom !== '') {
        $where[] = 'p.payment_date>=:date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'p.payment_date<=:date_to';
        $params[':date_to'] = $dateTo;
    }

    $from = ' FROM food_expense_payments p
              INNER JOIN expenses e ON e.id=p.expense_id AND e.branch_id=p.branch_id
              INNER JOIN expense_categories ec ON ec.id=e.expense_category_id';

    $totalStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE p.branch_id=:branch_id AND p.status=1');
    $totalStmt->execute([':branch_id' => $branchId]);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
    $countStmt->execute($params);
    $recordsFiltered = (int)$countStmt->fetchColumn();

    $columns = ['p.payment_no','p.payment_date','e.expense_no','ec.category_name','e.payee_name','p.amount'];
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 1);
    $direction = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = $columns[$orderColumn] ?? 'p.payment_date';

    $sql = 'SELECT p.id,p.payment_no,p.payment_date,p.amount,p.posting_status,p.expense_id,
                   e.expense_no,e.payee_name,ec.category_name' .
           $from . ' WHERE ' . implode(' AND ', $where) .
           ' ORDER BY ' . $orderBy . ' ' . $direction . ',p.id DESC LIMIT :start,:length';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':branch_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['amount'] = (float)$row['amount'];
        $row['posting_status'] = (int)$row['posting_status'];
        $row['ref'] = encryptReference('expense_payment', (int)$row['id']);
        $row['expense_ref'] = encryptReference('expense', (int)$row['expense_id']);
        $row['edit_url'] = 'expense-payment.php?ref=' . rawurlencode($row['ref'])
            . '&expense=' . rawurlencode($row['expense_ref']) . '&lock=1';
        $row['view_url'] = $row['edit_url'] . '&view=1';
        unset($row['id'], $row['expense_id']);
        $rows[] = $row;
    }

    json_success('Expense Payments loaded.', [
        'datatable' => [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ],
        'list_actions' => $access['actions'],
        'form_actions' => $formActions,
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $action = strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action === 'preview') {
        $access = require_permission('expense-payment.php', ACTION_VIEW);
        $ctx = ep_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $pdo = db();

        $expenseId = ep_ref_to_id($data['expense_ref'] ?? '', 'expense', 'Expense');
        $excludePaymentId = 0;
        if (!empty($data['ref'])) {
            $excludePaymentId = ep_ref_to_id($data['ref'], 'expense_payment', 'Expense Payment');
        }
        $expense = ef_expense_context($pdo, $branchId, $expenseId, $excludePaymentId);
        if (!$expense) {
            json_error('Selected Expense is unavailable or has no payable balance.', 422);
        }
        $details = ep_parse_details($pdo, $branchId, $data);
        if ($details['total'] > (float)$expense['balance_amount'] + 0.001) {
            json_error(
                'Payment Amount cannot exceed Expense Balance.',
                422,
                ['payments' => 'Maximum payable amount is ' . number_format((float)$expense['balance_amount'], 2, '.', '') . '.']
            );
        }

        json_success('Expense Payment preview calculated.', [
            'expense' => $expense,
            'actual_payment' => $details['total'],
            'balance_after' => max(0.0, round((float)$expense['balance_amount'] - $details['total'], 2)),
        ]);
    }

    if ($action === 'delete') {
        $access = require_permission('expense-payment.php', 4);
        $ctx = ep_tenant_context($access['user']);
        $branchId = (int)$ctx['branch_id'];
        $userId = (int)$access['user']['id'];
        $id = ep_ref_to_id($data['ref'] ?? '', 'expense_payment', 'Expense Payment');
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM food_expense_payments
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $id, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                json_error('Expense Payment was not found.', 404);
            }

            $expenseId = (int)$old['expense_id'];
            ep_undo_details($pdo, $branchId, $id, false);
            $pdo->prepare(
                'UPDATE food_expense_payments
                 SET posting_status=0,status=0,reversed_at=NOW(),reversed_by=:user_id,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            )->execute([
                ':user_id' => $userId,
                ':id' => $id,
                ':branch_id' => $branchId,
            ]);

            ef_refresh_expense($pdo, $branchId, $expenseId);
            $pdo->commit();

            audit_log($userId, 4, [
                'company_id' => $ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
            ]);
            json_success('Expense Payment deleted and Expense balance recalculated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action !== 'save') {
        json_error('Unsupported Expense Payment action.', 404);
    }

    $isEdit = !empty($data['ref']);
    $access = require_permission('expense-payment.php', $isEdit ? ACTION_UPDATE : ACTION_CREATE);
    $ctx = ep_tenant_context($access['user']);
    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $paymentId = 0;
        $paymentNo = '';
        $old = null;
        $oldExpenseId = 0;

        if ($isEdit) {
            $paymentId = ep_ref_to_id($data['ref'], 'expense_payment', 'Expense Payment');
            $stmt = $pdo->prepare(
                'SELECT * FROM food_expense_payments
                 WHERE id=:id AND branch_id=:branch_id AND status=1
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $paymentId, ':branch_id' => $branchId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                json_error('Expense Payment was not found.', 404);
            }
            $paymentNo = (string)$old['payment_no'];
            $oldExpenseId = (int)$old['expense_id'];
        }

        $expenseId = ep_ref_to_id($data['expense_ref'] ?? '', 'expense', 'Expense');
        $expense = ef_expense_context(
            $pdo,
            $branchId,
            $expenseId,
            $isEdit ? $paymentId : 0
        );
        if (!$expense) {
            json_error('Selected Expense is unavailable or has no payable balance.', 422);
        }

        $paymentDate = ep_valid_date($data['payment_date'] ?? '', 'payment_date', true);
        $notes = ep_nullable($data['notes'] ?? null, 255);
        $details = ep_parse_details($pdo, $branchId, $data);

        if ($details['total'] > (float)$expense['balance_amount'] + 0.001) {
            json_error(
                'Payment Amount cannot exceed Expense Balance.',
                422,
                ['payments' => 'Maximum payable amount is ' . number_format((float)$expense['balance_amount'], 2, '.', '') . '.']
            );
        }

        if ($isEdit) {
            ep_undo_details($pdo, $branchId, $paymentId, true);
            $stmt = $pdo->prepare(
                'UPDATE food_expense_payments
                 SET expense_id=:expense_id,
                     payment_no=:payment_no,
                     payment_date=:payment_date,
                     amount=:amount,
                     notes=:notes,
                     posting_status=1,reversed_at=NULL,reversed_by=NULL,status=1,updated_at=NOW()
                 WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':expense_id' => $expenseId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':amount' => $details['total'],
                ':notes' => $notes,
                ':id' => $paymentId,
                ':branch_id' => $branchId,
            ]);
        } else {
            $paymentNo = ep_generate_no($pdo, $branchId);
            $stmt = $pdo->prepare(
                'INSERT INTO food_expense_payments(
                    branch_id,expense_id,payment_no,payment_date,amount,notes,
                    posting_status,reversed_at,reversed_by,status,created_by,created_at,updated_at
                 ) VALUES(
                    :branch_id,:expense_id,:payment_no,:payment_date,:amount,:notes,
                    1,NULL,NULL,1,:created_by,NOW(),NOW()
                 )'
            );
            $stmt->execute([
                ':branch_id' => $branchId,
                ':expense_id' => $expenseId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':amount' => $details['total'],
                ':notes' => $notes,
                ':created_by' => $userId,
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        ep_insert_details($pdo, $branchId, $paymentId, $userId, $details['rows']);

        if ($oldExpenseId > 0 && $oldExpenseId !== $expenseId) {
            ef_refresh_expense($pdo, $branchId, $oldExpenseId);
        }
        ef_refresh_expense($pdo, $branchId, $expenseId);

        $pdo->commit();

        audit_log($userId, $isEdit ? ACTION_UPDATE : ACTION_CREATE, [
            'company_id' => $ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $paymentId,
            'old_data' => $old,
        ]);

        json_success(
            $isEdit ? 'Expense Payment updated successfully.' : 'Expense Payment posted successfully.',
            ['payment' => ep_payment_record(db(), $branchId, $paymentId)],
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
