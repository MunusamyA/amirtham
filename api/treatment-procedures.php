<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

/**
 * AMIRTHAM - Clinic Treatment / Procedure Master API
 *
 * Table:
 * clinic_treatment_procedures
 *
 * Actions:
 * GET  ?options=1
 * GET  ?ref=<encrypted>
 * GET  ?datatable=1
 * POST action=save
 * POST action=delete (soft deactivate)
 */

function treatment_procedure_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Treatment / Procedure Master is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT
            b.id AS branch_id,
            b.company_id,
            b.branch_name,
            c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id
           AND b.status=1
           AND c.status=1
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

function treatment_procedure_require_schema(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $stmt = db()->query('SHOW COLUMNS FROM `clinic_treatment_procedures`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        json_error(
            'Treatment / Procedure Master database table is missing.',
            500,
            [
                'schema' =>
                    'Run clinic-ayurveda-billing-upgrade.php once after the Treatment / Procedure setup.'
            ]
        );
    }

    $required = [
        'id',
        'branch_id',
        'procedure_code',
        'procedure_name',
        'price',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    $found = array_map('strtolower', array_column($rows, 'Field'));
    $missing = array_values(array_diff($required, $found));

    if ($missing !== []) {
        json_error(
            'Treatment / Procedure Master database structure is incomplete.',
            500,
            [
                'schema' =>
                    'Missing columns: ' .
                    implode(', ', $missing) .
                    '. Run clinic-ayurveda-billing-upgrade.php once.'
            ]
        );
    }

    $checked = true;
}

function treatment_procedure_ref_to_id($value): int
{
    if (!is_string($value) || trim($value) === '') {
        json_error(
            'Treatment / Procedure reference is required.',
            422,
            ['ref' => 'Treatment / Procedure reference is required.']
        );
    }

    try {
        return decryptReference(
            trim($value),
            'clinic_treatment_procedure'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Treatment / Procedure reference.',
            422,
            ['ref' => 'Invalid Treatment / Procedure reference.']
        );
    }
}

function treatment_procedure_generate_code(
    PDO $pdo,
    int $branchId
): string {
    $stmt = $pdo->prepare(
        "SELECT procedure_code
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id
           AND procedure_code REGEXP '^TRT[0-9]+$'
         ORDER BY
            CAST(SUBSTRING(procedure_code,4) AS UNSIGNED) DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':branch_id' => $branchId,
    ]);

    $last = (string)($stmt->fetchColumn() ?: '');
    $next = 1;

    if (
        $last !== '' &&
        preg_match('/^TRT([0-9]+)$/i', $last, $m)
    ) {
        $next = ((int)$m[1]) + 1;
    }

    return 'TRT' .
        str_pad(
            (string)$next,
            4,
            '0',
            STR_PAD_LEFT
        );
}

function treatment_procedure_record(
    array $ctx,
    int $id
): array {
    $stmt = db()->prepare(
        'SELECT
            t.id,
            t.procedure_code,
            t.procedure_name,
            t.price,
            t.status,
            t.created_by,
            t.created_at,
            t.updated_at,
            u.name AS created_by_name
         FROM clinic_treatment_procedures t
         LEFT JOIN users u
                ON u.id=t.created_by
         WHERE t.id=:id
           AND t.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $id,
        ':branch_id' => (int)$ctx['branch_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Treatment / Procedure was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    $row['price'] = number_format((float)$row['price'], 2, '.', '');

    $row['ref'] = encryptReference(
        'clinic_treatment_procedure',
        (int)$row['id']
    );

    return $row;
}

function treatment_procedure_options(
    array $ctx,
    int $includeId = 0
): array {
    $sql =
        'SELECT
            id,
            procedure_code,
            procedure_name,
            price,
            status
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id
           AND (status=1';

    $params = [
        ':branch_id' => (int)$ctx['branch_id'],
    ];

    if ($includeId > 0) {
        $sql .= ' OR id=:include_id';
        $params[':include_id'] = $includeId;
    }

    $sql .= ')
         ORDER BY procedure_name,id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'ref' => encryptReference(
                'clinic_treatment_procedure',
                (int)$row['id']
            ),
            'procedure_code' => (string)$row['procedure_code'],
            'procedure_name' => (string)$row['procedure_name'],
            'price' => number_format((float)$row['price'], 2, '.', ''),
            'status' => (int)$row['status'],
            'label' =>
                (string)$row['procedure_code'] .
                ' - ' .
                (string)$row['procedure_name'],
        ];
    }

    return $rows;
}

$method = request_method();

treatment_procedure_require_schema();

/* Options */
if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission(
        'treatment-procedure-list.php',
        ACTION_VIEW
    );

    $ctx = treatment_procedure_context(
        $access['user']
    );

    json_success(
        'Treatment / Procedure options loaded.',
        [
            'allowed_actions' => $access['actions'],
            'next_procedure_code' =>
                treatment_procedure_generate_code(
                    db(),
                    (int)$ctx['branch_id']
                ),
            'procedures' =>
                treatment_procedure_options($ctx),
            'branch' => $ctx,
        ]
    );
}

/* Single record */
if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission(
        'treatment-procedure-list.php',
        ACTION_VIEW
    );

    $ctx = treatment_procedure_context(
        $access['user']
    );

    $id = treatment_procedure_ref_to_id(
        $_GET['ref'] ?? ''
    );

    json_success(
        'Treatment / Procedure loaded.',
        [
            'record' =>
                treatment_procedure_record(
                    $ctx,
                    $id
                ),
            'allowed_actions' => $access['actions'],
            'branch' => $ctx,
        ]
    );
}

/* DataTable */
if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission(
        'treatment-procedure-list.php',
        ACTION_VIEW
    );

    $ctx = treatment_procedure_context(
        $access['user']
    );

    $branchId = (int)$ctx['branch_id'];
    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $start = max(0, (int)($_GET['start'] ?? 0));
    $lengthRaw = (int)($_GET['length'] ?? 10);
    $length =
        $lengthRaw < 0
            ? 100000
            : max(
                1,
                min(100000, $lengthRaw)
            );

    $search = trim(
        (string)(
            $_GET['search']['value'] ??
            ''
        )
    );

    $where = [
        't.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' => $branchId,
    ];

    if ($search !== '') {
        $where[] =
            '(t.procedure_code LIKE :s1
              OR t.procedure_name LIKE :s2)';

        $like = '%' . $search . '%';

        $params[':s1'] = $like;
        $params[':s2'] = $like;
    }

    $statusFilter = trim((string)($_GET['status'] ?? ''));
    if ($statusFilter !== '' && in_array($statusFilter, ['0','1'], true)) {
        $where[] = 't.status=:status';
        $params[':status'] = (int)$statusFilter;
    }

    $totalStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id'
    );

    $totalStmt->execute([
        ':branch_id' => $branchId,
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM clinic_treatment_procedures t
         WHERE ' .
         implode(' AND ', $where)
    );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $summaryStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN t.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN t.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(AVG(t.price),0) AS average_price
         FROM clinic_treatment_procedures t
         WHERE ' . implode(' AND ', $where)
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderColumns = [
        't.procedure_code',
        't.procedure_name',
        't.price',
        't.status',
        't.created_at',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column'] ??
            1
        );

    $orderDir =
        strtolower(
            (string)(
                $_GET['order'][0]['dir'] ??
                'asc'
            )
        ) === 'desc'
            ? 'DESC'
            : 'ASC';

    $orderBy =
        $orderColumns[$orderIndex] ??
        't.procedure_name';

    $stmt = db()->prepare(
        'SELECT
            t.id,
            t.procedure_code,
            t.procedure_name,
            t.price,
            t.status,
            t.created_at
         FROM clinic_treatment_procedures t
         WHERE ' .
         implode(' AND ', $where) .
         ' ORDER BY ' .
         $orderBy .
         ' ' .
         $orderDir .
         ',t.id DESC
         LIMIT :start,:length'
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array($key, [':branch_id', ':status'], true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

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

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref = encryptReference(
            'clinic_treatment_procedure',
            (int)$row['id']
        );

        $rows[] = [
            'ref' => $ref,
            'procedure_code' => (string)$row['procedure_code'],
            'procedure_name' => (string)$row['procedure_name'],
            'price' => number_format((float)$row['price'], 2, '.', ''),
            'status' => (int)$row['status'],
            'status_label' =>
                (int)$row['status'] === 1
                    ? 'Active'
                    : 'Inactive',
            'created_at' => $row['created_at'],
            'view_url' =>
                'treatment-procedure-form.php?ref=' .
                rawurlencode($ref) .
                '&view=1',
            'edit_url' =>
                'treatment-procedure-form.php?ref=' .
                rawurlencode($ref),
        ];
    }

    json_success(
        'Treatment / Procedure list loaded.',
        [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_count' => (int)($summary['total_count'] ?? 0),
                'active_count' => (int)($summary['active_count'] ?? 0),
                'inactive_count' => (int)($summary['inactive_count'] ?? 0),
                'average_price' => (float)($summary['average_price'] ?? 0),
            ],
            'list_actions' => $access['actions'],
            'form_actions' => $access['actions'],
        ]
    );
}

/* Save / deactivate */
if ($method === 'POST') {
    $data = request_data();

    $action = strtolower(
        trim(
            (string)(
                $data['action'] ??
                'save'
            )
        )
    );

    if ($action === 'delete') {
        $access = require_permission(
            'treatment-procedure-list.php',
            4
        );

        $ctx = treatment_procedure_context(
            $access['user']
        );

        $id = treatment_procedure_ref_to_id(
            $data['ref'] ?? ''
        );

        $old = treatment_procedure_record(
            $ctx,
            $id
        );

        $usage = 0;

        foreach (
            [
                'clinic_treatment_protocol_days',
                'clinic_patient_treatment_days',
            ] as $table
        ) {
            try {
                $stmt = db()->prepare(
                    'SELECT COUNT(*)
                     FROM `' . $table . '`
                     WHERE branch_id=:branch_id
                       AND treatment_procedure_id=:procedure_id'
                );

                $stmt->execute([
                    ':branch_id' =>
                        (int)$ctx['branch_id'],

                    ':procedure_id' =>
                        $id,
                ]);

                $usage +=
                    (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                /* Older schema may not have the column yet. */
            }
        }

        if ($usage > 0) {
            json_error(
                'This Treatment / Procedure is already used in a Protocol or Patient Treatment Plan and cannot be deactivated.',
                409
            );
        }

        db()->prepare(
            'UPDATE clinic_treatment_procedures
             SET status=0,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':id' => $id,
            ':branch_id' => (int)$ctx['branch_id'],
        ]);

        audit_log(
            (int)$access['user']['id'],
            4,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => (int)$ctx['branch_id'],
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
            ]
        );

        json_success(
            'Treatment / Procedure deactivated successfully.'
        );
    }

    if ($action !== 'save') {
        json_error(
            'Unsupported Treatment / Procedure action.',
            404
        );
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access = require_permission(
        'treatment-procedure-list.php',
        $isUpdate
            ? ACTION_UPDATE
            : ACTION_CREATE
    );

    $ctx = treatment_procedure_context(
        $access['user']
    );

    $branchId = (int)$ctx['branch_id'];
    $userId = (int)$access['user']['id'];

    $procedureName = trim(
        (string)(
            $data['procedure_name'] ??
            ''
        )
    );

    $status = (int)(
        $data['status'] ??
        1
    );

    $priceRaw = trim((string)($data['price'] ?? '0'));
    $price = '0.00';

    $errors = [];

    if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
        $errors['price'] = 'Enter a valid non-negative Price.';
    } else {
        $price = number_format((float)$priceRaw, 2, '.', '');
    }

    if ($procedureName === '') {
        $errors['procedure_name'] =
            'Treatment / Procedure Name is required.';
    } elseif (mb_strlen($procedureName) > 255) {
        $errors['procedure_name'] =
            'Treatment / Procedure Name cannot exceed 255 characters.';
    }

    if (!in_array($status, [0,1], true)) {
        $errors['status'] =
            'Select a valid Status.';
    }

    $id =
        $isUpdate
            ? treatment_procedure_ref_to_id(
                $data['ref'] ?? ''
            )
            : 0;

    $dupSql =
        'SELECT id
         FROM clinic_treatment_procedures
         WHERE branch_id=:branch_id
           AND procedure_name=:procedure_name';

    $dupParams = [
        ':branch_id' => $branchId,
        ':procedure_name' => $procedureName,
    ];

    if ($id > 0) {
        $dupSql .= ' AND id<>:id';
        $dupParams[':id'] = $id;
    }

    $dupSql .= ' LIMIT 1';

    $dupStmt = db()->prepare($dupSql);
    $dupStmt->execute($dupParams);

    if ($dupStmt->fetchColumn()) {
        $errors['procedure_name'] =
            'Treatment / Procedure Name already exists.';
    }

    if ($errors !== []) {
        json_error(
            'Treatment / Procedure validation failed.',
            422,
            $errors
        );
    }

    if ($isUpdate) {
        $old = treatment_procedure_record(
            $ctx,
            $id
        );

        db()->prepare(
            'UPDATE clinic_treatment_procedures
             SET procedure_name=:procedure_name,
                 price=:price,
                 status=:status,
                 updated_at=NOW()
             WHERE id=:id
               AND branch_id=:branch_id'
        )->execute([
            ':procedure_name' => $procedureName,
            ':price' => $price,
            ':status' => $status,
            ':id' => $id,
            ':branch_id' => $branchId,
        ]);

        audit_log(
            $userId,
            3,
            [
                'company_id' => (int)$ctx['company_id'],
                'branch_id' => $branchId,
                'menu_id' => (int)$access['menu']['id'],
                'record_id' => $id,
                'old_data' => $old,
                'new_data' => [
                    'procedure_name' => $procedureName,
                    'price' => $price,
                    'status' => $status,
                ],
            ]
        );

        json_success(
            'Treatment / Procedure updated successfully.',
            [
                'ref' => encryptReference(
                    'clinic_treatment_procedure',
                    $id
                ),
                'record' =>
                    treatment_procedure_record(
                        $ctx,
                        $id
                    ),
            ]
        );
    }

    $procedureCode =
        treatment_procedure_generate_code(
            db(),
            $branchId
        );

    $stmt = db()->prepare(
        'INSERT INTO clinic_treatment_procedures
        (
            branch_id,
            procedure_code,
            procedure_name,
            price,
            status,
            created_by,
            created_at,
            updated_at
        )
        VALUES
        (
            :branch_id,
            :procedure_code,
            :procedure_name,
            :price,
            :status,
            :created_by,
            NOW(),
            NOW()
        )'
    );

    $stmt->execute([
        ':branch_id' => $branchId,
        ':procedure_code' => $procedureCode,
        ':procedure_name' => $procedureName,
        ':price' => $price,
        ':status' => $status,
        ':created_by' => $userId,
    ]);

    $id = (int)db()->lastInsertId();

    audit_log(
        $userId,
        2,
        [
            'company_id' => (int)$ctx['company_id'],
            'branch_id' => $branchId,
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'new_data' => [
                'procedure_code' => $procedureCode,
                'procedure_name' => $procedureName,
                'price' => $price,
                'status' => $status,
            ],
        ]
    );

    json_success(
        'Treatment / Procedure saved successfully.',
        [
            'ref' => encryptReference(
                'clinic_treatment_procedure',
                $id
            ),
            'record' =>
                treatment_procedure_record(
                    $ctx,
                    $id
                ),
        ]
    );
}

json_error('Method not allowed.', 405);
