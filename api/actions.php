<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function action_record(int $id): array
{
    $stmt = db()->prepare(
        'SELECT id, action_name, purpose, group_id, sort_order, status, created_by, updated_by,
                created_at, updated_at
         FROM permission_actions WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Permission action was not found.', 404);
    }

    $row['id'] = (int)$row['id'];
    $row['group_id'] = (int)$row['group_id'];
    $row['sort_order'] = (int)$row['sort_order'];
    $row['status'] = (int)$row['status'];
    $row['group_name'] =
        action_group_names()[$row['group_id']] ??
        ('Group ' . $row['group_id']);
    $row['is_core'] = (int)$row['id'] <= ACTION_MANAGE_MENUS;

    return $row;
}

function validate_action_group($value): int
{
    $groupId = (int)$value;

    if (!isset(action_group_names()[$groupId])) {
        json_error(
            'Select a valid action group.',
            422,
            ['group_id' => 'Invalid action group.']
        );
    }

    return $groupId;
}

function clean_action_name($value): string
{
    $name = trim((string)$value);

    if ($name === '' || strlen($name) > 120) {
        json_error(
            'Enter a valid action name.',
            422,
            [
                'action_name' =>
                    'Action name is required and must be within 120 characters.'
            ]
        );
    }

    return $name;
}

function ensure_unique_action_name(string $name, int $excludeId = 0): void
{
    $sql =
        'SELECT id
         FROM permission_actions
         WHERE action_name=:name';

    $params = [':name' => $name];

    if ($excludeId > 0) {
        $sql .= ' AND id<>:id';
        $params[':id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error(
            'Action name already exists.',
            409,
            ['action_name' => 'Use a unique action name.']
        );
    }
}

function clean_action_purpose($value): ?string
{
    $purpose = trim((string)$value);

    if ($purpose === '') {
        return null;
    }

    if (strlen($purpose) > 255) {
        json_error(
            'Purpose is too long.',
            422,
            ['purpose' => 'Purpose must be within 255 characters.']
        );
    }

    return $purpose;
}

function action_bind_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('actions.php', ACTION_VIEW);
    require_platform_user();

    if (isset($_GET['id'])) {
        json_success('Permission action loaded.', [
            'action' => action_record(positive_id($_GET['id'])),
            'groups' => action_group_names(),
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
        $groupRaw = trim((string)($_GET['group_id'] ?? ''));
        $statusRaw = trim((string)($_GET['status'] ?? ''));

        $where = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(CAST(id AS CHAR) LIKE :search_id
                  OR action_name LIKE :search_name
                  OR COALESCE(purpose,\'\') LIKE :search_purpose)';

            $params[':search_id'] = $like;
            $params[':search_name'] = $like;
            $params[':search_purpose'] = $like;
        }

        if ($groupRaw !== '') {
            $groupId = validate_action_group($groupRaw);
            $where[] = 'group_id=:group_id';
            $params[':group_id'] = $groupId;
        }

        if ($statusRaw !== '') {
            $status = (int)$statusRaw;

            if (!in_array($status, [0,1], true)) {
                json_error('Invalid Permission Action status.', 422);
            }

            $where[] = 'status=:status';
            $params[':status'] = $status;
        }

        $whereSql =
            $where === []
                ? ''
                : ' WHERE ' . implode(' AND ', $where);

        $total =
            (int)db()->query(
                'SELECT COUNT(*) FROM permission_actions'
            )->fetchColumn();

        $countStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM permission_actions' .
            $whereSql
        );
        action_bind_params($countStmt, $params);
        $countStmt->execute();
        $filtered = (int)$countStmt->fetchColumn();

        $summaryParams = $params;
        $summaryParams[':core_limit'] = (int)ACTION_MANAGE_MENUS;

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_actions,
                COALESCE(SUM(CASE WHEN status=1 THEN 1 ELSE 0 END),0) AS active_actions,
                COALESCE(SUM(CASE WHEN status=0 THEN 1 ELSE 0 END),0) AS inactive_actions,
                COALESCE(SUM(CASE WHEN id<=:core_limit THEN 1 ELSE 0 END),0) AS core_actions
             FROM permission_actions' .
            $whereSql
        );
        action_bind_params($summaryStmt, $summaryParams);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $columns = [
            'id',
            'action_name',
            'group_id',
            'purpose',
            'status',
            'sort_order'
        ];

        $orderColumn =
            (int)($_GET['order'][0]['column'] ?? 0);

        $orderDir =
            strtolower(
                (string)($_GET['order'][0]['dir'] ?? 'asc')
            ) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderBy =
            $columns[$orderColumn] ??
            'id';

        $sql =
            'SELECT
                id,
                action_name,
                purpose,
                group_id,
                sort_order,
                status,
                created_at,
                updated_at
             FROM permission_actions' .
            $whereSql .
            ' ORDER BY ' .
            $orderBy . ' ' . $orderDir .
            ',id ASC
             LIMIT :start,:length';

        $stmt = db()->prepare($sql);
        action_bind_params($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $groups = action_group_names();
        $rows = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['id'];

            $rows[] = [
                'id' => $id,
                'action_name' => $row['action_name'],
                'purpose' => $row['purpose'],
                'group_id' => (int)$row['group_id'],
                'group_name' =>
                    $groups[(int)$row['group_id']] ??
                    ('Group ' . (int)$row['group_id']),
                'sort_order' => (int)$row['sort_order'],
                'status' => (int)$row['status'],
                'is_core' =>
                    $id <= ACTION_MANAGE_MENUS,
            ];
        }

        json_success('Permission actions loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ],
            'summary' => [
                'total_actions' => (int)($summary['total_actions'] ?? 0),
                'active_actions' => (int)($summary['active_actions'] ?? 0),
                'inactive_actions' => (int)($summary['inactive_actions'] ?? 0),
                'core_actions' => (int)($summary['core_actions'] ?? 0),
            ],
            'groups' => $groups,
            'allowed_actions' => $access['actions'],
        ]);
    }

    json_success('Permission actions loaded.', [
        'actions' => permission_actions_list(false),
        'groups' => action_group_names(),
        'allowed_actions' => $access['actions'],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('actions.php', ACTION_CREATE);
    $user = require_platform_user();
    $data = request_data();

    require_fields(
        $data,
        [
            'action_name' => 'Action name is required.',
            'group_id' => 'Action group is required.'
        ]
    );

    $name = clean_action_name($data['action_name']);
    $purpose = clean_action_purpose($data['purpose'] ?? '');

    ensure_unique_action_name($name);

    $groupId =
        validate_action_group(
            $data['group_id']
        );

    $sortOrder =
        isset($data['sort_order'])
            ? max(0, (int)$data['sort_order'])
            : 0;

    $stmt = db()->prepare(
        'INSERT INTO permission_actions
         (
            action_name,
            purpose,
            group_id,
            sort_order,
            status,
            created_by,
            updated_by,
            created_at,
            updated_at
         )
         VALUES
         (
            :name,
            :purpose,
            :group_id,
            :sort_order,
            1,
            :created_by,
            :updated_by,
            NOW(),
            NOW()
         )'
    );

    $stmt->execute([
        ':name' => $name,
        ':purpose' => $purpose,
        ':group_id' => $groupId,
        ':sort_order' => $sortOrder,
        ':created_by' => (int)$user['id'],
        ':updated_by' => (int)$user['id'],
    ]);

    $id = (int)db()->lastInsertId();

    audit_log(
        (int)$user['id'],
        ACTION_CREATE,
        [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
        ]
    );

    json_success(
        'Permission action created successfully.',
        ['action_id' => $id],
        201
    );
}

if ($method === 'PUT') {
    $access =
        require_permission(
            'actions.php',
            ACTION_UPDATE
        );

    $user =
        require_platform_user();

    $data =
        request_data();

    require_fields(
        $data,
        [
            'id',
            'action_name',
            'group_id'
        ]
    );

    $id =
        positive_id(
            $data['id']
        );

    $old =
        action_record(
            $id
        );

    $name =
        clean_action_name(
            $data['action_name']
        );

    $purpose =
        clean_action_purpose(
            $data['purpose'] ?? ''
        );

    ensure_unique_action_name(
        $name,
        $id
    );

    $groupId =
        validate_action_group(
            $data['group_id']
        );

    $sortOrder =
        isset($data['sort_order'])
            ? max(
                0,
                (int)$data['sort_order']
            )
            : (int)$old['sort_order'];

    db()->prepare(
        'UPDATE permission_actions
         SET action_name=:name,
             purpose=:purpose,
             group_id=:group_id,
             sort_order=:sort_order,
             updated_by=:updated_by,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':name' => $name,
        ':purpose' => $purpose,
        ':group_id' => $groupId,
        ':sort_order' => $sortOrder,
        ':updated_by' => (int)$user['id'],
        ':id' => $id,
    ]);

    audit_log(
        (int)$user['id'],
        ACTION_UPDATE,
        [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]
    );

    json_success(
        'Permission action updated successfully.'
    );
}

if ($method === 'PATCH') {
    $user =
        require_platform_user();

    $data =
        request_data();

    require_fields(
        $data,
        [
            'id',
            'status'
        ]
    );

    $id =
        positive_id(
            $data['id']
        );

    $status =
        normalize_status(
            $data['status']
        );

    $access =
        require_permission(
            'actions.php',
            $status === 1
                ? ACTION_ACTIVATE
                : ACTION_DEACTIVATE
        );

    $old =
        action_record(
            $id
        );

    if (
        $id <= ACTION_MANAGE_MENUS &&
        $status === 0
    ) {
        json_error(
            'Built-in actions 1 to 54 cannot be deactivated.',
            409
        );
    }

    db()->prepare(
        'UPDATE permission_actions
         SET status=:status,
             updated_by=:updated_by,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status' => $status,
        ':updated_by' => (int)$user['id'],
        ':id' => $id,
    ]);

    audit_log(
        (int)$user['id'],
        $status === 1
            ? ACTION_ACTIVATE
            : ACTION_DEACTIVATE,
        [
            'menu_id' => (int)$access['menu']['id'],
            'record_id' => $id,
            'old_data' => $old,
        ]
    );

    json_success(
        $status === 1
            ? 'Permission action activated.'
            : 'Permission action deactivated.'
    );
}

json_error('Method not allowed.', 405);
