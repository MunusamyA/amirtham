<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function hsn_record(int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate,
                status, created_by, updated_by, created_at, updated_at
         FROM hsn_master
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('HSN record was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    foreach (['gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
        $row[$key] = (float)$row[$key];
    }

    return $row;
}

function clean_hsn_code($value): string
{
    $code = preg_replace('/\s+/', '', trim((string)$value));
    if ($code === '' || preg_match('/^\d{2,8}$/', $code) !== 1) {
        json_error('Enter a valid HSN code.', 422, [
            'hsn_code' => 'HSN code must contain 2 to 8 digits.',
        ]);
    }
    return $code;
}

function clean_hsn_description($value): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;

    if (strlen($text) > 255) {
        json_error('HSN description is too long.', 422, [
            'description' => 'Description must be within 255 characters.',
        ]);
    }

    return $text;
}

function clean_tax_rate($value, string $field, string $label): float
{
    if ($value === '' || $value === null) return 0.0;

    if (!is_numeric($value)) {
        json_error('Enter a valid ' . $label . '.', 422, [
            $field => $label . ' must be numeric.',
        ]);
    }

    $rate = round((float)$value, 2);
    if ($rate < 0 || $rate > 100) {
        json_error('Enter a valid ' . $label . '.', 422, [
            $field => $label . ' must be between 0 and 100.',
        ]);
    }

    return $rate;
}

function ensure_unique_hsn(string $code, int $excludeId = 0): void
{
    $sql = 'SELECT id FROM hsn_master WHERE hsn_code = :code';
    $params = [':code' => $code];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error('HSN code already exists.', 409, [
            'hsn_code' => 'Use a unique HSN code.',
        ]);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('hsn.php', ACTION_VIEW);

    if (isset($_GET['id'])) {
        json_success('HSN record loaded.', [
            'hsn' => hsn_record(positive_id($_GET['id'])),
            'allowed_actions' => $access['actions'],
        ]);
    }

    if (isset($_GET['datatable']) && (int)$_GET['datatable'] === 1) {
        $draw = max(0, (int)($_GET['draw'] ?? 0));
        $start = max(0, (int)($_GET['start'] ?? 0));

        $lengthRaw = (int)($_GET['length'] ?? 25);
        $length = $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

        $search = trim((string)($_GET['search']['value'] ?? ''));

        $statusFilter =
            isset($_GET['status']) && $_GET['status'] !== ''
                ? normalize_status($_GET['status'])
                : null;

        $gstRateFilter = null;
        if (isset($_GET['gst_rate']) && $_GET['gst_rate'] !== '') {
            if (!is_numeric($_GET['gst_rate'])) {
                json_error('Invalid GST rate filter.', 422);
            }

            $gstRateFilter = round((float)$_GET['gst_rate'], 2);

            if ($gstRateFilter < 0 || $gstRateFilter > 100) {
                json_error('Invalid GST rate filter.', 422);
            }
        }

        $where = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(hsn_code LIKE :search_code
                  OR COALESCE(description, \'\') LIKE :search_description)';

            $params[':search_code'] = $like;
            $params[':search_description'] = $like;
        }

        if ($gstRateFilter !== null) {
            $where[] = 'gst_rate = :gst_rate';
            $params[':gst_rate'] = number_format($gstRateFilter, 2, '.', '');
        }

        if ($statusFilter !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        }

        $whereSql = $where
            ? ' WHERE ' . implode(' AND ', $where)
            : '';

        $bindParams = static function (PDOStatement $stmt, array $values): void {
            foreach ($values as $key => $value) {
                $stmt->bindValue(
                    $key,
                    $value,
                    $key === ':status'
                        ? PDO::PARAM_INT
                        : PDO::PARAM_STR
                );
            }
        };

        $total =
            (int)db()->query(
                'SELECT COUNT(*) FROM hsn_master'
            )->fetchColumn();

        $count = db()->prepare(
            'SELECT COUNT(*) FROM hsn_master' . $whereSql
        );
        $bindParams($count, $params);
        $count->execute();
        $filtered = (int)$count->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_hsn,
                COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END),0) AS active_hsn,
                COALESCE(SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END),0) AS inactive_hsn,
                COUNT(DISTINCT gst_rate) AS gst_slabs
             FROM hsn_master' . $whereSql
        );
        $bindParams($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            'hsn_code',
            'description',
            'gst_rate',
            'cgst_rate',
            'sgst_rate',
            'igst_rate',
            'cess_rate',
            'status'
        ];

        $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderBy =
            $columns[$orderColumn] ??
            'hsn_code';

        $sql =
            'SELECT
                id,
                hsn_code,
                description,
                gst_rate,
                cgst_rate,
                sgst_rate,
                igst_rate,
                cess_rate,
                status
             FROM hsn_master' .
            $whereSql .
            ' ORDER BY ' . $orderBy . ' ' . $orderDir . ', id ASC
              LIMIT :start, :length';

        $stmt = db()->prepare($sql);
        $bindParams($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'hsn_code' => (string)$row['hsn_code'],
                'description' => $row['description'],
                'gst_rate' => (float)$row['gst_rate'],
                'cgst_rate' => (float)$row['cgst_rate'],
                'sgst_rate' => (float)$row['sgst_rate'],
                'igst_rate' => (float)$row['igst_rate'],
                'cess_rate' => (float)$row['cess_rate'],
                'status' => (int)$row['status'],
            ];
        }

        $gstRateRows = db()->query(
            'SELECT DISTINCT gst_rate
             FROM hsn_master
             ORDER BY gst_rate ASC'
        )->fetchAll(PDO::FETCH_COLUMN);

        json_success('HSN records loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ],
            'summary' => [
                'total_hsn' => (int)($summary['total_hsn'] ?? 0),
                'active_hsn' => (int)($summary['active_hsn'] ?? 0),
                'inactive_hsn' => (int)($summary['inactive_hsn'] ?? 0),
                'gst_slabs' => (int)($summary['gst_slabs'] ?? 0),
            ],
            'filter_gst_rates' => array_map(
                static fn($value): float => (float)$value,
                $gstRateRows
            ),
            'allowed_actions' => $access['actions'],
        ]);
    }

    $onlyActive = isset($_GET['active']) && (int)$_GET['active'] === 1;
    $sql = 'SELECT id, hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate, status
            FROM hsn_master';
    if ($onlyActive) $sql .= ' WHERE status = 1';
    $sql .= ' ORDER BY hsn_code ASC';

    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['status'] = (int)$row['status'];
        foreach (['gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $key) {
            $row[$key] = (float)$row[$key];
        }
    }
    unset($row);

    json_success('HSN records loaded.', [
        'hsn_codes' => $rows,
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    $data = request_data();
    $isUpdate = $method === 'PUT';
    $access = require_permission('hsn.php', $isUpdate ? ACTION_UPDATE : ACTION_CREATE);
    $user = require_user();

    $id = $isUpdate ? positive_id($data['id'] ?? 0) : 0;
    $old = $isUpdate ? hsn_record($id) : null;

    $code = clean_hsn_code($data['hsn_code'] ?? '');
    ensure_unique_hsn($code, $id);

    $description = clean_hsn_description($data['description'] ?? '');
    $gst = clean_tax_rate($data['gst_rate'] ?? 0, 'gst_rate', 'GST rate');
    $cgst = clean_tax_rate($data['cgst_rate'] ?? 0, 'cgst_rate', 'CGST rate');
    $sgst = clean_tax_rate($data['sgst_rate'] ?? 0, 'sgst_rate', 'SGST rate');
    $igst = clean_tax_rate($data['igst_rate'] ?? 0, 'igst_rate', 'IGST rate');
    $cess = clean_tax_rate($data['cess_rate'] ?? 0, 'cess_rate', 'Cess rate');

    if ($isUpdate) {
        $stmt = db()->prepare(
            'UPDATE hsn_master
             SET hsn_code = :code,
                 description = :description,
                 gst_rate = :gst,
                 cgst_rate = :cgst,
                 sgst_rate = :sgst,
                 igst_rate = :igst,
                 cess_rate = :cess,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            ':code' => $code,
            ':description' => $description,
            ':gst' => $gst,
            ':cgst' => $cgst,
            ':sgst' => $sgst,
            ':igst' => $igst,
            ':cess' => $cess,
            ':updated_by' => (int)$user['id'],
            ':id' => $id,
        ]);

        audit_log((int)$user['id'], ACTION_UPDATE, [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]);

        json_success('HSN record updated successfully.', [
            'hsn' => hsn_record($id),
        ]);
    }

    $stmt = db()->prepare(
        'INSERT INTO hsn_master
         (hsn_code, description, gst_rate, cgst_rate, sgst_rate, igst_rate, cess_rate,
          status, created_by, updated_by, created_at, updated_at)
         VALUES
         (:code, :description, :gst, :cgst, :sgst, :igst, :cess,
          1, :created_by, :updated_by, NOW(), NOW())'
    );

    $stmt->execute([
        ':code' => $code,
        ':description' => $description,
        ':gst' => $gst,
        ':cgst' => $cgst,
        ':sgst' => $sgst,
        ':igst' => $igst,
        ':cess' => $cess,
        ':created_by' => (int)$user['id'],
        ':updated_by' => (int)$user['id'],
    ]);

    $newId = (int)db()->lastInsertId();

    audit_log((int)$user['id'], ACTION_CREATE, [
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $newId,
    ]);

    json_success('HSN record created successfully.', [
        'hsn_id' => $newId,
        'hsn' => hsn_record($newId),
    ], 201);
}

if ($method === 'PATCH') {
    $data = request_data();
    require_fields($data, ['id','status']);

    $id = positive_id($data['id']);
    $status = normalize_status($data['status']);
    $access = require_permission('hsn.php', $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE);
    $user = require_user();
    $old = hsn_record($id);

    db()->prepare(
        'UPDATE hsn_master
         SET status = :status,
             updated_by = :updated_by,
             updated_at = NOW()
         WHERE id = :id'
    )->execute([
        ':status' => $status,
        ':updated_by' => (int)$user['id'],
        ':id' => $id,
    ]);

    audit_log((int)$user['id'], $status === 1 ? ACTION_ACTIVATE : ACTION_DEACTIVATE, [
        'menu_id' => (int)$access['menu']['id'],
        'record_id' => $id,
        'old_data' => $old,
    ]);

    json_success($status === 1 ? 'HSN record activated.' : 'HSN record deactivated.');
}

json_error('Method not allowed.', 405);
