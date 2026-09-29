<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

const CUSTOMER_ACTION_ACTIVATE = 27;
const CUSTOMER_ACTION_DEACTIVATE = 28;

function customer_tenant_context(array $user): array
{
    if ((int) ($user['role_type'] ?? 0) === 2) {
        json_error('Customer management is available only for tenant users.', 403);
    }

    $branchId = isset($user['branch_id']) ? (int) $user['branch_id'] : 0;
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
        'branch_id' => (int) $context['branch_id'],
        'company_id' => (int) $context['company_id'],
        'branch_name' => (string) $context['branch_name'],
        'company_name' => (string) $context['company_name'],
    ];
}

function customer_nullable($value): ?string
{
    if ($value === null) return null;
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function customer_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT customer_code
         FROM food_customers
         WHERE branch_id = :branch_id
           AND customer_code REGEXP '^CUS[0-9]+$'
         ORDER BY CAST(SUBSTRING(customer_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string) ($stmt->fetchColumn() ?: '');
    $nextNumber = 1;

    if ($lastCode !== '' && preg_match('/^CUS([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int) $matches[1]) + 1;
    }

    return 'CUS' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

function normalize_customer_data(array $data): array
{
    foreach (['email', 'mobile', 'pan'] as $field) {
        if (array_key_exists($field, $data)) {
            $data[$field] = normalize_input_value($field, $data[$field]);
        }
    }

    if (array_key_exists('gstin', $data)) {
        $data['gstin'] = strtoupper(preg_replace('/\s+/', '', trim((string) $data['gstin'])));
    }

    if (array_key_exists('pan', $data)) {
        $data['pan'] = strtoupper(preg_replace('/\s+/', '', trim((string) $data['pan'])));
    }

    if (array_key_exists('state_code', $data)) {
        $stateCode = preg_replace('/\D+/', '', trim((string) $data['state_code']));
        if ($stateCode !== '') {
            $stateNumber = (int) $stateCode;
            $data['state_code'] = $stateNumber >= 1 && $stateNumber <= 99
                ? str_pad((string) $stateNumber, 2, '0', STR_PAD_LEFT)
                : $stateCode;
        } else {
            $data['state_code'] = '';
        }
    }

    if (array_key_exists('customer_code', $data)) {
        $data['customer_code'] = trim((string) $data['customer_code']);
    }

    if (array_key_exists('customer_name', $data)) {
        $data['customer_name'] = trim((string) $data['customer_name']);
    }

    if (array_key_exists('address', $data)) {
        $data['address'] = trim((string) $data['address']);
    }

    return $data;
}

function validate_customer_data(array $data): void
{
    $errors = [];

    if (!empty($data['email']) && !valid_input_value('email', $data['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!empty($data['mobile']) && !valid_input_value('mobile', $data['mobile'])) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }

    if (!empty($data['pan']) && !valid_input_value('pan', $data['pan'])) {
        $errors['pan'] = 'PAN format must be ABCDE1234F.';
    }

    $gstin = isset($data['gstin']) ? trim((string) $data['gstin']) : '';
    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
        $errors['gstin'] = 'Enter a valid 15-character GSTIN.';
    }

    $stateCode = isset($data['state_code']) ? trim((string) $data['state_code']) : '';
    if ($stateCode !== '' && !preg_match('/^[0-9]{2}$/', $stateCode)) {
        $errors['state_code'] = 'State code must contain exactly 2 digits.';
    }

    $creditLimit = isset($data['credit_limit']) && $data['credit_limit'] !== ''
        ? $data['credit_limit']
        : 0;

    if (!is_numeric($creditLimit) || (float) $creditLimit < 0) {
        $errors['credit_limit'] = 'Credit limit must be zero or greater.';
    }

    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? $data['opening_balance']
        : 0;

    if (!is_numeric($openingBalance) || (float) $openingBalance < 0) {
        $errors['opening_balance'] = 'Opening balance must be zero or greater.';
    }

    $openingBalanceDate = trim((string)($data['opening_balance_date'] ?? ''));
    if (is_numeric($openingBalance) && (float)$openingBalance > 0 && $openingBalanceDate === '') {
        $errors['opening_balance_date'] = 'Opening balance date is required when Opening Balance is greater than zero.';
    }
    if ($openingBalanceDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $openingBalanceDate);
        if (!$date || $date->format('Y-m-d') !== $openingBalanceDate) {
            $errors['opening_balance_date'] = 'Enter a valid Opening Balance date.';
        }
    }

    if ($errors !== []) {
        json_error('Customer validation failed.', 422, $errors);
    }
}

function customer_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted customer reference is required.', 422, [
            'ref' => 'Customer reference is required.',
        ]);
        return 0;
    }

    try {
        return decryptReference(trim($value), 'customer');
    } catch (Throwable $exception) {
        json_error($exception->getMessage(), 422, [
            'ref' => 'Invalid customer reference.',
        ]);
        return 0;
    }
}

function customer_record_for_tenant(array $context, int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, customer_code, customer_name,
                mobile, email, gstin, pan, address, state_code,
                credit_limit, opening_balance, opening_balance_date, status,
                created_by, created_at, updated_at
         FROM food_customers
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $customerId,
        ':branch_id' => (int) $context['branch_id'],
    ]);

    $customer = $stmt->fetch();

    if (!$customer) {
        json_error('Customer was not found in your branch.', 404);
    }

    $customer['ref'] = encryptReference('customer', (int) $customer['id']);
    unset($customer['id']);

    return $customer;
}

function customer_result_item(array $row): array
{
    $reference = encryptReference('customer', (int) $row['id']);
    unset($row['id']);

    $row['ref'] = $reference;
    $row['edit_url'] = 'customer-form.php?ref=' . rawurlencode($reference);
    $row['ledger_url'] = 'customer-ledger.php?ref=' . rawurlencode($reference);

    return $row;
}

function customer_assert_unique(
    int $branchId,
    string $customerCode,
    ?string $gstin = null,
    int $excludeId = 0
): void {
    $sql =
        'SELECT id
         FROM food_customers
         WHERE branch_id = :branch_id
           AND customer_code = :customer_code';

    $params = [
        ':branch_id' => $branchId,
        ':customer_code' => $customerCode,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetch()) {
        json_error('Customer code already exists in your branch.', 409, [
            'customer_code' => 'This customer code is already in use.',
        ]);
    }

    if ($gstin !== null && $gstin !== '') {
        $sql =
            'SELECT id
             FROM food_customers
             WHERE branch_id = :branch_id
               AND gstin = :gstin';

        $params = [
            ':branch_id' => $branchId,
            ':gstin' => $gstin,
        ];

        if ($excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            json_error('GSTIN already exists for another customer in your branch.', 409, [
                'gstin' => 'This GSTIN is already in use.',
            ]);
        }
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('customer-form.php', ACTION_VIEW);
    $context = customer_tenant_context($access['user']);

    json_success('Customer form access loaded.', [
        'allowed_actions' => $access['actions'],
        'next_customer_code' => customer_generate_code((int) $context['branch_id']),
        'branch' => [
            'branch_id' => $context['branch_id'],
            'branch_name' => $context['branch_name'],
            'company_name' => $context['company_name'],
        ],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('customer-form.php', ACTION_VIEW);
    $context = customer_tenant_context($access['user']);

    $customer = customer_record_for_tenant(
        $context,
        customer_id_from_reference($_GET['ref'])
    );

    json_success('Customer loaded.', [
        'customer' => $customer,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = customer_tenant_context($user);
    $branchId = (int)$context['branch_id'];

    $draw =
        isset($_GET['draw'])
            ? max(0, (int)$_GET['draw'])
            : 0;

    $start =
        isset($_GET['start'])
            ? max(0, (int)$_GET['start'])
            : 0;

    $lengthRaw =
        isset($_GET['length'])
            ? (int)$_GET['length']
            : 10;

    $length =
        $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

    $searchValue = '';

    if (
        isset($_GET['search']) &&
        is_array($_GET['search']) &&
        isset($_GET['search']['value'])
    ) {
        $searchValue =
            trim(
                (string)$_GET['search']['value']
            );
    }

    $gstFilter =
        strtolower(
            trim(
                (string)($_GET['gst_filter'] ?? '')
            )
        );

    if (
        $gstFilter !== '' &&
        !in_array($gstFilter, ['with', 'without'], true)
    ) {
        json_error('Invalid GSTIN filter.', 422);
    }

    $statusFilter =
        isset($_GET['status']) && $_GET['status'] !== ''
            ? normalize_status($_GET['status'])
            : null;

    $baseFrom =
        ' FROM food_customers c';

    $baseWhere = [
        'c.branch_id = :branch_id'
    ];

    $where = $baseWhere;

    $params = [
        ':branch_id' => $branchId
    ];

    if ($searchValue !== '') {
        $like = '%' . $searchValue . '%';

        $where[] =
            '(c.customer_code LIKE :search_code
              OR c.customer_name LIKE :search_name
              OR COALESCE(c.mobile, \'\') LIKE :search_mobile
              OR COALESCE(c.email, \'\') LIKE :search_email
              OR COALESCE(c.gstin, \'\') LIKE :search_gstin
              OR COALESCE(c.pan, \'\') LIKE :search_pan
              OR COALESCE(c.state_code, \'\') LIKE :search_state)';

        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_mobile'] = $like;
        $params[':search_email'] = $like;
        $params[':search_gstin'] = $like;
        $params[':search_pan'] = $like;
        $params[':search_state'] = $like;
    }

    if ($gstFilter === 'with') {
        $where[] =
            'c.gstin IS NOT NULL
             AND TRIM(c.gstin) <> \'\'';
    } elseif ($gstFilter === 'without') {
        $where[] =
            '(c.gstin IS NULL
              OR TRIM(c.gstin) = \'\')';
    }

    if ($statusFilter !== null) {
        $where[] = 'c.status = :status';
        $params[':status'] = $statusFilter;
    }

    $bindParams = static function (
        PDOStatement $stmt,
        array $values
    ): void {
        foreach ($values as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                in_array($key, [':branch_id', ':status'], true)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
            );
        }
    };

    $totalStmt =
        db()->prepare(
            'SELECT COUNT(*)' .
            $baseFrom .
            ' WHERE ' .
            implode(
                ' AND ',
                $baseWhere
            )
        );

    $totalStmt->execute([
        ':branch_id' => $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $filteredStmt =
        db()->prepare(
            'SELECT COUNT(*)' .
            $baseFrom .
            ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
        );

    $bindParams(
        $filteredStmt,
        $params
    );

    $filteredStmt->execute();

    $recordsFiltered =
        (int)$filteredStmt->fetchColumn();

    $summaryStmt =
        db()->prepare(
            'SELECT
                COUNT(*) AS total_customers,
                COALESCE(SUM(CASE WHEN c.status=1 THEN 1 ELSE 0 END),0) AS active_customers,
                COALESCE(SUM(CASE WHEN c.status=0 THEN 1 ELSE 0 END),0) AS inactive_customers,
                COALESCE(SUM(c.opening_balance),0) AS opening_balance' .
            $baseFrom .
            ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
        );

    $bindParams(
        $summaryStmt,
        $params
    );

    $summaryStmt->execute();

    $summary =
        $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        0 => 'c.customer_code',
        1 => 'c.customer_name',
        2 => 'c.mobile',
        3 => 'c.gstin',
        4 => 'c.state_code',
        5 => 'c.credit_limit',
        6 => 'c.opening_balance',
        7 => 'c.status',
        8 => 'c.id',
    ];

    $orderIndex = 8;
    $orderDir = 'DESC';

    if (
        isset($_GET['order'][0]) &&
        is_array($_GET['order'][0])
    ) {
        $requestedIndex =
            isset($_GET['order'][0]['column'])
                ? (int)$_GET['order'][0]['column']
                : 8;

        if (
            isset(
                $orderColumns[
                    $requestedIndex
                ]
            )
        ) {
            $orderIndex =
                $requestedIndex;
        }

        $requestedDir =
            strtolower(
                (string)(
                    $_GET['order'][0]['dir'] ??
                    'desc'
                )
            );

        $orderDir =
            $requestedDir === 'asc'
                ? 'ASC'
                : 'DESC';
    }

    $sql =
        'SELECT
            c.id,
            c.customer_code,
            c.customer_name,
            c.mobile,
            c.email,
            c.gstin,
            c.pan,
            c.state_code,
            c.credit_limit,
            c.opening_balance,
            c.opening_balance_date,
            c.status,
            c.created_at' .
        $baseFrom .
        ' WHERE ' .
        implode(
            ' AND ',
            $where
        ) .
        ' ORDER BY ' .
        $orderColumns[$orderIndex] .
        ' ' .
        $orderDir .
        ' LIMIT :start,:length';

    $stmt =
        db()->prepare(
            $sql
        );

    $bindParams(
        $stmt,
        $params
    );

    $stmt->bindValue(
        ':start',
        $start,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':length',
        $length,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows =
        array_map(
            'customer_result_item',
            $stmt->fetchAll()
        );

    $formActions =
        effective_actions_for_menu(
            $user,
            menu_by_path(
                'customer-form.php'
            )
        );

    $ledgerMenu =
        menu_by_path(
            'customer-ledger.php'
        );

    $ledgerActions =
        $ledgerMenu
            ? effective_actions_for_menu(
                $user,
                $ledgerMenu
            )
            : [];

    json_success(
        'Customer DataTable loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],

            'summary' => [
                'total_customers' =>
                    (int)($summary['total_customers'] ?? 0),

                'active_customers' =>
                    (int)($summary['active_customers'] ?? 0),

                'inactive_customers' =>
                    (int)($summary['inactive_customers'] ?? 0),

                'opening_balance' =>
                    (float)($summary['opening_balance'] ?? 0),
            ],

            'list_actions' =>
                $access['actions'],

            'form_actions' =>
                $formActions,

            'ledger_actions' =>
                $ledgerActions,
        ]
    );
}

if ($method === 'GET') {
    $access = require_permission('customer-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = customer_tenant_context($user);

    $stmt = db()->prepare(
        'SELECT id, customer_code, customer_name,
                mobile, email, gstin, pan, address, state_code,
                credit_limit, opening_balance, opening_balance_date, status, created_at, updated_at
         FROM food_customers
         WHERE branch_id = :branch_id
         ORDER BY id DESC'
    );
    $stmt->execute([':branch_id' => (int) $context['branch_id']]);

    $customers = array_map('customer_result_item', $stmt->fetchAll());

    json_success('Customers loaded.', [
        'customers' => $customers,
        'allowed_actions' => $access['actions'],
        'form_actions' => effective_actions_for_menu(
            $user,
            menu_by_path('customer-form.php')
        ),
        'ledger_actions' => (($ledgerMenu = menu_by_path('customer-ledger.php')) ? effective_actions_for_menu($user, $ledgerMenu) : []),
    ]);
}

if ($method === 'POST') {
    $rawData = request_data();

    if (isset($rawData['action'])) {
        $actionName = strtolower(trim((string) $rawData['action']));

        if ($actionName === 'activate' || $actionName === 'deactivate') {
            $actionId = $actionName === 'activate'
                ? CUSTOMER_ACTION_ACTIVATE
                : CUSTOMER_ACTION_DEACTIVATE;

            $access = require_permission('customer-list.php', $actionId);
            $user = $access['user'];
            $context = customer_tenant_context($user);

            require_fields($rawData, [
                'ref' => 'Customer reference is required.',
            ]);

            $customerId = customer_id_from_reference($rawData['ref']);
            $old = customer_record_for_tenant($context, $customerId);
            $newStatus = $actionName === 'activate' ? 1 : 0;

            if ((int) $old['status'] !== $newStatus) {
                $stmt = db()->prepare(
                    'UPDATE food_customers
                     SET status = :status, updated_at = NOW()
                     WHERE id = :id
                       AND branch_id = :branch_id'
                );
                $stmt->execute([
                    ':status' => $newStatus,
                    ':id' => $customerId,
                    ':branch_id' => (int) $context['branch_id'],
                ]);

                audit_log((int) $user['id'], $actionId, [
                    'company_id' => (int) $context['company_id'],
                    'branch_id' => (int) $context['branch_id'],
                    'menu_id' => (int) $access['menu']['id'],
                    'record_id' => $customerId,
                ]);
            }

            json_success(
                $newStatus === 1
                    ? 'Customer activated successfully.'
                    : 'Customer deactivated successfully.',
                [
                    'ref' => encryptReference('customer', $customerId),
                    'status' => $newStatus,
                ]
            );
        }

        json_error('Invalid customer action.', 422);
    }

    $access = require_permission('customer-form.php', ACTION_CREATE);
    $user = $access['user'];
    $context = customer_tenant_context($user);
    $data = normalize_customer_data($rawData);

    require_fields($data, [
        'customer_name' => 'Customer name is required.',
    ]);

    validate_customer_data($data);

    $branchId = (int) $context['branch_id'];
    $gstin = customer_nullable($data['gstin'] ?? null);
    $customerCode = customer_generate_code($branchId);

    customer_assert_unique(
        $branchId,
        $customerCode,
        $gstin
    );

    $status = isset($data['status']) ? normalize_status($data['status']) : 1;
    $creditLimit = isset($data['credit_limit']) && $data['credit_limit'] !== ''
        ? (float) $data['credit_limit']
        : 0.0;
    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? (float) $data['opening_balance']
        : 0.0;
    $openingBalanceDate = customer_nullable($data['opening_balance_date'] ?? null);

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_customers
             (branch_id, customer_code, customer_name,
              mobile, email, gstin, pan, address, state_code,
              credit_limit, opening_balance, opening_balance_date, status,
              created_by, created_at, updated_at)
             VALUES
             (:branch_id, :customer_code, :customer_name,
              :mobile, :email, :gstin, :pan, :address, :state_code,
              :credit_limit, :opening_balance, :opening_balance_date, :status,
              :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':customer_code' => $customerCode,
            ':customer_name' => trim((string) $data['customer_name']),
            ':mobile' => customer_nullable($data['mobile'] ?? null),
            ':email' => customer_nullable($data['email'] ?? null),
            ':gstin' => $gstin,
            ':pan' => customer_nullable($data['pan'] ?? null),
            ':address' => customer_nullable($data['address'] ?? null),
            ':state_code' => customer_nullable($data['state_code'] ?? null),
            ':credit_limit' => $creditLimit,
            ':opening_balance' => $openingBalance,
            ':opening_balance_date' => $openingBalanceDate,
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);

        $customerId = (int) db()->lastInsertId();

        audit_log((int) $user['id'], ACTION_CREATE, [
            'company_id' => (int) $context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $customerId,
        ]);

        json_success('Customer created successfully.', [
            'ref' => encryptReference('customer', $customerId),
            'customer_code' => $customerCode,
        ], 201);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Customer code already exists in your branch.', 409, [
                'customer_code' => 'This customer code is already in use.',
            ]);
        }

        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('customer-form.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = customer_tenant_context($user);
    $data = normalize_customer_data(request_data());

    require_fields($data, [
        'ref' => 'Customer reference is required.',
        'customer_name' => 'Customer name is required.',
    ]);

    validate_customer_data($data);

    $customerId = customer_id_from_reference($data['ref']);
    $old = customer_record_for_tenant($context, $customerId);
    $branchId = (int) $context['branch_id'];
    $gstin = customer_nullable($data['gstin'] ?? null);

    customer_assert_unique(
        $branchId,
        (string) $old['customer_code'],
        $gstin,
        $customerId
    );

    $status = isset($data['status'])
        ? normalize_status($data['status'])
        : (int) $old['status'];

    $creditLimit = isset($data['credit_limit']) && $data['credit_limit'] !== ''
        ? (float) $data['credit_limit']
        : 0.0;
    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? (float) $data['opening_balance']
        : 0.0;
    $openingBalanceDate = customer_nullable($data['opening_balance_date'] ?? null);

    try {
        $stmt = db()->prepare(
            'UPDATE food_customers
             SET customer_name = :customer_name,
                 mobile = :mobile,
                 email = :email,
                 gstin = :gstin,
                 pan = :pan,
                 address = :address,
                 state_code = :state_code,
                 credit_limit = :credit_limit,
                 opening_balance = :opening_balance,
                 opening_balance_date = :opening_balance_date,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );

        $stmt->execute([
            ':customer_name' => trim((string) $data['customer_name']),
            ':mobile' => customer_nullable($data['mobile'] ?? null),
            ':email' => customer_nullable($data['email'] ?? null),
            ':gstin' => $gstin,
            ':pan' => customer_nullable($data['pan'] ?? null),
            ':address' => customer_nullable($data['address'] ?? null),
            ':state_code' => customer_nullable($data['state_code'] ?? null),
            ':credit_limit' => $creditLimit,
            ':opening_balance' => $openingBalance,
            ':opening_balance_date' => $openingBalanceDate,
            ':status' => $status,
            ':id' => $customerId,
            ':branch_id' => $branchId,
        ]);

        audit_log((int) $user['id'], ACTION_UPDATE, [
            'company_id' => (int) $context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $customerId,
        ]);

        json_success('Customer updated successfully.', [
            'ref' => encryptReference('customer', $customerId),
        ]);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Customer code already exists in your branch.', 409, [
                'customer_code' => 'This customer code is already in use.',
            ]);
        }

        throw $exception;
    }
}

json_error('Method not allowed. Customer deletion is not enabled.', 405);
