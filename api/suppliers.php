<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

const SUPPLIER_ACTION_ACTIVATE = 27;
const SUPPLIER_ACTION_DEACTIVATE = 28;

function supplier_tenant_context(array $user): array
{
    if ((int) ($user['role_type'] ?? 0) === 2) {
        json_error('Supplier management is available only for tenant users.', 403);
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

function supplier_nullable($value): ?string
{
    if ($value === null) return null;
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function supplier_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT supplier_code
         FROM food_suppliers
         WHERE branch_id = :branch_id
           AND supplier_code REGEXP '^SUP[0-9]+$'
         ORDER BY CAST(SUBSTRING(supplier_code, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);

    $lastCode = (string) ($stmt->fetchColumn() ?: '');
    $nextNumber = 1;

    if ($lastCode !== '' && preg_match('/^SUP([0-9]+)$/i', $lastCode, $matches)) {
        $nextNumber = ((int) $matches[1]) + 1;
    }

    return 'SUP' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

function normalize_supplier_data(array $data): array
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

    if (array_key_exists('supplier_code', $data)) {
        $data['supplier_code'] = trim((string) $data['supplier_code']);
    }

    if (array_key_exists('supplier_name', $data)) {
        $data['supplier_name'] = trim((string) $data['supplier_name']);
    }

    if (array_key_exists('contact_person', $data)) {
        $data['contact_person'] = trim((string) $data['contact_person']);
    }

    if (array_key_exists('address', $data)) {
        $data['address'] = trim((string) $data['address']);
    }

    return $data;
}

function validate_supplier_data(array $data): void
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
    if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        $errors['gstin'] = 'Enter a valid 15-character GSTIN.';
    }

    $stateCode = isset($data['state_code']) ? trim((string) $data['state_code']) : '';
    if ($stateCode !== '' && !preg_match('/^[0-9]{2}$/', $stateCode)) {
        $errors['state_code'] = 'State code must contain exactly 2 digits.';
    }

    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? $data['opening_balance']
        : 0;

    if (!is_numeric($openingBalance) || (float) $openingBalance < 0) {
        $errors['opening_balance'] = 'Opening balance must be zero or greater.';
    }

    if ($errors !== []) {
        json_error('Supplier validation failed.', 422, $errors);
    }
}

function supplier_id_from_reference($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error('Encrypted supplier reference is required.', 422, [
            'ref' => 'Supplier reference is required.',
        ]);
        return 0;
    }

    try {
        return decryptReference(trim($value), 'supplier');
    } catch (Throwable $exception) {
        json_error($exception->getMessage(), 422, [
            'ref' => 'Invalid supplier reference.',
        ]);
        return 0;
    }
}

function supplier_record_for_tenant(array $context, int $supplierId): array
{
    $stmt = db()->prepare(
        'SELECT id, branch_id, supplier_code, supplier_name, contact_person,
                mobile, email, gstin, pan, address, state_code,
                opening_balance, opening_balance_date, status, created_by, created_at, updated_at
         FROM food_suppliers
         WHERE id = :id
           AND branch_id = :branch_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $supplierId,
        ':branch_id' => (int) $context['branch_id'],
    ]);

    $supplier = $stmt->fetch();

    if (!$supplier) {
        json_error('Supplier was not found in your branch.', 404);
    }

    $supplier['ref'] = encryptReference('supplier', (int) $supplier['id']);
    unset($supplier['id']);

    return $supplier;
}

function supplier_result_item(array $row): array
{
    $reference = encryptReference('supplier', (int) $row['id']);
    unset($row['id']);

    $row['ref'] = $reference;
    $row['edit_url'] = 'supplier-form.php?ref=' . rawurlencode($reference);

    return $row;
}

function supplier_assert_unique(
    int $branchId,
    string $supplierCode,
    ?string $gstin = null,
    int $excludeId = 0
): void {
    $sql =
        'SELECT id
         FROM food_suppliers
         WHERE branch_id = :branch_id
           AND supplier_code = :supplier_code';

    $params = [
        ':branch_id' => $branchId,
        ':supplier_code' => $supplierCode,
    ];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetch()) {
        json_error('Supplier code already exists in your branch.', 409, [
            'supplier_code' => 'This supplier code is already in use.',
        ]);
    }

    if ($gstin !== null && $gstin !== '') {
        $sql =
            'SELECT id
             FROM food_suppliers
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
            json_error('GSTIN already exists for another supplier in your branch.', 409, [
                'gstin' => 'This GSTIN is already in use.',
            ]);
        }
    }
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('supplier-form.php', ACTION_VIEW);
    $context = supplier_tenant_context($access['user']);

    json_success('Supplier form access loaded.', [
        'allowed_actions' => $access['actions'],
        'next_supplier_code' => supplier_generate_code((int) $context['branch_id']),
        'branch' => [
            'branch_id' => $context['branch_id'],
            'branch_name' => $context['branch_name'],
            'company_name' => $context['company_name'],
        ],
    ]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('supplier-form.php', ACTION_VIEW);
    $context = supplier_tenant_context($access['user']);

    $supplier = supplier_record_for_tenant(
        $context,
        supplier_id_from_reference($_GET['ref'])
    );

    json_success('Supplier loaded.', [
        'supplier' => $supplier,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = supplier_tenant_context($user);
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
        ' FROM food_suppliers s';

    $baseWhere = [
        's.branch_id = :branch_id'
    ];

    $where = $baseWhere;

    $params = [
        ':branch_id' => $branchId
    ];

    if ($searchValue !== '') {
        $like = '%' . $searchValue . '%';

        $where[] =
            '(s.supplier_code LIKE :search_code
              OR s.supplier_name LIKE :search_name
              OR COALESCE(s.contact_person, \'\') LIKE :search_contact_person
              OR COALESCE(s.mobile, \'\') LIKE :search_mobile
              OR COALESCE(s.email, \'\') LIKE :search_email
              OR COALESCE(s.gstin, \'\') LIKE :search_gstin
              OR COALESCE(s.pan, \'\') LIKE :search_pan
              OR COALESCE(s.state_code, \'\') LIKE :search_state)';

        $params[':search_code'] = $like;
        $params[':search_name'] = $like;
        $params[':search_contact_person'] = $like;
        $params[':search_mobile'] = $like;
        $params[':search_email'] = $like;
        $params[':search_gstin'] = $like;
        $params[':search_pan'] = $like;
        $params[':search_state'] = $like;
    }

    if ($gstFilter === 'with') {
        $where[] =
            's.gstin IS NOT NULL
             AND TRIM(s.gstin) <> \'\'';
    } elseif ($gstFilter === 'without') {
        $where[] =
            '(s.gstin IS NULL
              OR TRIM(s.gstin) = \'\')';
    }

    if ($statusFilter !== null) {
        $where[] = 's.status = :status';
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
                COUNT(*) AS total_suppliers,
                COALESCE(SUM(CASE WHEN s.status=1 THEN 1 ELSE 0 END),0) AS active_suppliers,
                COALESCE(SUM(CASE WHEN s.status=0 THEN 1 ELSE 0 END),0) AS inactive_suppliers,
                COALESCE(SUM(s.opening_balance),0) AS opening_balance' .
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
        0 => 's.supplier_code',
        1 => 's.supplier_name',
        2 => 's.contact_person',
        3 => 's.mobile',
        4 => 's.gstin',
        5 => 's.state_code',
        6 => 's.opening_balance',
        7 => 's.status',
        8 => 's.id',
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
            s.id,
            s.supplier_code,
            s.supplier_name,
            s.contact_person,
            s.mobile,
            s.email,
            s.gstin,
            s.pan,
            s.state_code,
            s.opening_balance,
            s.opening_balance_date,
            s.status,
            s.created_at' .
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
            'supplier_result_item',
            $stmt->fetchAll()
        );

    $formActions =
        effective_actions_for_menu(
            $user,
            menu_by_path(
                'supplier-form.php'
            )
        );

    json_success(
        'Supplier DataTable loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],

            'summary' => [
                'total_suppliers' =>
                    (int)($summary['total_suppliers'] ?? 0),

                'active_suppliers' =>
                    (int)($summary['active_suppliers'] ?? 0),

                'inactive_suppliers' =>
                    (int)($summary['inactive_suppliers'] ?? 0),

                'opening_balance' =>
                    (float)($summary['opening_balance'] ?? 0),
            ],

            'list_actions' =>
                $access['actions'],

            'form_actions' =>
                $formActions,
        ]
    );
}

if ($method === 'GET') {
    $access = require_permission('supplier-list.php', ACTION_VIEW);
    $user = $access['user'];
    $context = supplier_tenant_context($user);

    $stmt = db()->prepare(
        'SELECT id, supplier_code, supplier_name, contact_person,
                mobile, email, gstin, pan, address, state_code,
                opening_balance, opening_balance_date, status, created_at, updated_at
         FROM food_suppliers
         WHERE branch_id = :branch_id
         ORDER BY id DESC'
    );
    $stmt->execute([':branch_id' => (int) $context['branch_id']]);

    $suppliers = array_map('supplier_result_item', $stmt->fetchAll());

    json_success('Suppliers loaded.', [
        'suppliers' => $suppliers,
        'allowed_actions' => $access['actions'],
        'form_actions' => effective_actions_for_menu(
            $user,
            menu_by_path('supplier-form.php')
        ),
    ]);
}

if ($method === 'POST') {
    $rawData = request_data();

    if (isset($rawData['action'])) {
        $actionName = strtolower(trim((string) $rawData['action']));

        if ($actionName === 'activate' || $actionName === 'deactivate') {
            $actionId = $actionName === 'activate'
                ? SUPPLIER_ACTION_ACTIVATE
                : SUPPLIER_ACTION_DEACTIVATE;

            $access = require_permission('supplier-list.php', $actionId);
            $user = $access['user'];
            $context = supplier_tenant_context($user);

            require_fields($rawData, [
                'ref' => 'Supplier reference is required.',
            ]);

            $supplierId = supplier_id_from_reference($rawData['ref']);
            $old = supplier_record_for_tenant($context, $supplierId);
            $newStatus = $actionName === 'activate' ? 1 : 0;

            if ((int) $old['status'] !== $newStatus) {
                $stmt = db()->prepare(
                    'UPDATE food_suppliers
                     SET status = :status, updated_at = NOW()
                     WHERE id = :id
                       AND branch_id = :branch_id'
                );
                $stmt->execute([
                    ':status' => $newStatus,
                    ':id' => $supplierId,
                    ':branch_id' => (int) $context['branch_id'],
                ]);

                audit_log((int) $user['id'], $actionId, [
                    'company_id' => (int) $context['company_id'],
                    'branch_id' => (int) $context['branch_id'],
                    'menu_id' => (int) $access['menu']['id'],
                    'record_id' => $supplierId,
                ]);
            }

            json_success(
                $newStatus === 1
                    ? 'Supplier activated successfully.'
                    : 'Supplier deactivated successfully.',
                [
                    'ref' => encryptReference('supplier', $supplierId),
                    'status' => $newStatus,
                ]
            );
        }

        json_error('Invalid supplier action.', 422);
    }

    $access = require_permission('supplier-form.php', ACTION_CREATE);
    $user = $access['user'];
    $context = supplier_tenant_context($user);
    $data = normalize_supplier_data($rawData);

    require_fields($data, [
        'supplier_name' => 'Supplier name is required.',
        'mobile' => 'Mobile number is required.',
    ]);

    validate_supplier_data($data);

    $branchId = (int) $context['branch_id'];
    $gstin = supplier_nullable($data['gstin'] ?? null);
    $supplierCode = supplier_generate_code($branchId);

    supplier_assert_unique(
        $branchId,
        $supplierCode,
        $gstin
    );

    $status = isset($data['status']) ? normalize_status($data['status']) : 1;
    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? (float) $data['opening_balance']
        : 0.0;
    $openingBalanceDate = supplier_nullable($data['opening_balance_date'] ?? null);
    if ($openingBalance > 0 && $openingBalanceDate === null) {
        $openingBalanceDate = date('Y-m-d');
    }
    if ($openingBalanceDate !== null) {
        $d = DateTime::createFromFormat('Y-m-d', $openingBalanceDate);
        if (!$d || $d->format('Y-m-d') !== $openingBalanceDate) {
            json_error('Enter a valid Opening Balance Date.', 422, ['opening_balance_date' => 'Enter a valid date.']);
        }
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO food_suppliers
             (branch_id, supplier_code, supplier_name, contact_person,
              mobile, email, gstin, pan, address, state_code,
              opening_balance, opening_balance_date, status, created_by, created_at, updated_at)
             VALUES
             (:branch_id, :supplier_code, :supplier_name, :contact_person,
              :mobile, :email, :gstin, :pan, :address, :state_code,
              :opening_balance, :opening_balance_date, :status, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':branch_id' => $branchId,
            ':supplier_code' => $supplierCode,
            ':supplier_name' => trim((string) $data['supplier_name']),
            ':contact_person' => supplier_nullable($data['contact_person'] ?? null),
            ':mobile' => supplier_nullable($data['mobile'] ?? null),
            ':email' => supplier_nullable($data['email'] ?? null),
            ':gstin' => $gstin,
            ':pan' => supplier_nullable($data['pan'] ?? null),
            ':address' => supplier_nullable($data['address'] ?? null),
            ':state_code' => supplier_nullable($data['state_code'] ?? null),
            ':opening_balance' => $openingBalance,
            ':opening_balance_date' => $openingBalanceDate,
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);

        $supplierId = (int) db()->lastInsertId();

        audit_log((int) $user['id'], ACTION_CREATE, [
            'company_id' => (int) $context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $supplierId,
        ]);

        json_success('Supplier created successfully.', [
            'ref' => encryptReference('supplier', $supplierId),
            'supplier_code' => $supplierCode,
        ], 201);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Supplier code already exists in your branch.', 409, [
                'supplier_code' => 'This supplier code is already in use.',
            ]);
        }

        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('supplier-form.php', ACTION_UPDATE);
    $user = $access['user'];
    $context = supplier_tenant_context($user);
    $data = normalize_supplier_data(request_data());

    require_fields($data, [
        'ref' => 'Supplier reference is required.',
        'supplier_name' => 'Supplier name is required.',
        'mobile' => 'Mobile number is required.',
    ]);

    validate_supplier_data($data);

    $supplierId = supplier_id_from_reference($data['ref']);
    $old = supplier_record_for_tenant($context, $supplierId);
    $branchId = (int) $context['branch_id'];
    $gstin = supplier_nullable($data['gstin'] ?? null);

    supplier_assert_unique(
        $branchId,
        (string) $old['supplier_code'],
        $gstin,
        $supplierId
    );

    $status = isset($data['status'])
        ? normalize_status($data['status'])
        : (int) $old['status'];

    $openingBalance = isset($data['opening_balance']) && $data['opening_balance'] !== ''
        ? (float) $data['opening_balance']
        : 0.0;
    $openingBalanceDate = supplier_nullable($data['opening_balance_date'] ?? ($old['opening_balance_date'] ?? null));
    if ($openingBalance > 0 && $openingBalanceDate === null) {
        $openingBalanceDate = date('Y-m-d');
    }
    if ($openingBalanceDate !== null) {
        $d = DateTime::createFromFormat('Y-m-d', $openingBalanceDate);
        if (!$d || $d->format('Y-m-d') !== $openingBalanceDate) {
            json_error('Enter a valid Opening Balance Date.', 422, ['opening_balance_date' => 'Enter a valid date.']);
        }
    }

    try {
        $stmt = db()->prepare(
            'UPDATE food_suppliers
             SET supplier_name = :supplier_name,
                 contact_person = :contact_person,
                 mobile = :mobile,
                 email = :email,
                 gstin = :gstin,
                 pan = :pan,
                 address = :address,
                 state_code = :state_code,
                 opening_balance = :opening_balance,
                 opening_balance_date = :opening_balance_date,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND branch_id = :branch_id'
        );

        $stmt->execute([
            ':supplier_name' => trim((string) $data['supplier_name']),
            ':contact_person' => supplier_nullable($data['contact_person'] ?? null),
            ':mobile' => supplier_nullable($data['mobile'] ?? null),
            ':email' => supplier_nullable($data['email'] ?? null),
            ':gstin' => $gstin,
            ':pan' => supplier_nullable($data['pan'] ?? null),
            ':address' => supplier_nullable($data['address'] ?? null),
            ':state_code' => supplier_nullable($data['state_code'] ?? null),
            ':opening_balance' => $openingBalance,
            ':opening_balance_date' => $openingBalanceDate,
            ':status' => $status,
            ':id' => $supplierId,
            ':branch_id' => $branchId,
        ]);

        audit_log((int) $user['id'], ACTION_UPDATE, [
            'company_id' => (int) $context['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $supplierId,
        ]);

        json_success('Supplier updated successfully.', [
            'ref' => encryptReference('supplier', $supplierId),
        ]);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Supplier code already exists in your branch.', 409, [
                'supplier_code' => 'This supplier code is already in use.',
            ]);
        }

        throw $exception;
    }
}

json_error('Method not allowed. Supplier deletion is not enabled.', 405);
