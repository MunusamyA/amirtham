<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function branch_record_for_user(array $user, int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT b.*, c.company_name, r.role_name AS plan_name,
                (SELECT u.name FROM users u INNER JOIN roles ur ON ur.id = u.role_id
                 WHERE u.branch_id = b.id AND ur.role_type = 1
                 ORDER BY u.id ASC LIMIT 1) AS admin_name,
                (SELECT u.username FROM users u INNER JOIN roles ur ON ur.id = u.role_id
                 WHERE u.branch_id = b.id AND ur.role_type = 1
                 ORDER BY u.id ASC LIMIT 1) AS admin_username
         FROM branches b
         INNER JOIN companies c ON c.id = b.company_id
         INNER JOIN roles r ON r.id = b.role_id
         WHERE b.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $branchId]);
    $branch = $stmt->fetch();
    if (!$branch) {
        json_error('Branch was not found.', 404);
    }
    if ((int) $user['role_type'] !== 2 && (int) $branch['id'] !== (int) $user['branch_id']) {
        json_error('You cannot access another branch.', 403);
    }
    return $branch;
}

function active_plan(int $roleId): array
{
    $stmt = db()->prepare(
        'SELECT id, role_name FROM roles
         WHERE id = :id AND role_type = 1 AND company_id IS NULL AND status = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $roleId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        json_error('Selected plan is invalid or inactive.', 422);
    }
    return $plan;
}

function validate_branch_admin_contact(array $data): void
{
    $errors = [];
    if (!empty($data['admin_email']) &&
        !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = 'Enter a valid email address.';
    }
    if (!empty($data['admin_mobile']) &&
        !preg_match('/^[6-9][0-9]{9}$/', (string) $data['admin_mobile'])) {
        $errors['admin_mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if ($errors !== []) {
        json_error('Branch Admin validation failed.', 422, $errors);
    }
}

$method = request_method();

if ($method === 'GET') {
    $access = require_permission('branch-list.php', ACTION_VIEW);
    $user = $access['user'];
    if (isset($_GET['id'])) {
        $branch = branch_record_for_user($user, positive_id($_GET['id']));
        json_success('Branch loaded.', [
            'branch' => $branch,
            'allowed_actions' => $access['actions'],
            'current_user' => [
                'role_type' => (int) $user['role_type'],
                'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
            ],
        ]);
    }


    if (isset($_GET['datatable'])) {
        $draw = max(0, (int)($_GET['draw'] ?? 0));
        $start = max(0, (int)($_GET['start'] ?? 0));

        $lengthRaw = (int)($_GET['length'] ?? 10);
        $length = $lengthRaw < 0
            ? 100000
            : max(1, min(100000, $lengthRaw));

        $search = trim((string)($_GET['search']['value'] ?? ''));
        $companyRaw = trim((string)($_GET['company_id'] ?? ''));
        $planRaw = trim((string)($_GET['plan_role_id'] ?? ''));
        $statusRaw = trim((string)($_GET['status'] ?? ''));

        $baseFrom =
            ' FROM branches b
              INNER JOIN companies c ON c.id=b.company_id
              INNER JOIN roles r ON r.id=b.role_id ';

        $baseWhere = [];
        $baseParams = [];

        if ((int)$user['role_type'] !== 2) {
            $baseWhere[] = 'b.id=:access_branch_id';
            $baseParams[':access_branch_id'] = (int)$user['branch_id'];
        }

        $where = $baseWhere;
        $params = $baseParams;

        if ($search !== '') {
            $like = '%' . $search . '%';

            $where[] =
                '(c.company_name LIKE :search_company
                  OR b.branch_name LIKE :search_branch
                  OR b.branch_code LIKE :search_code
                  OR r.role_name LIKE :search_plan
                  OR EXISTS (
                      SELECT 1
                      FROM users au
                      INNER JOIN roles ar ON ar.id=au.role_id
                      WHERE au.branch_id=b.id
                        AND ar.role_type=1
                        AND (
                            au.name LIKE :search_admin
                            OR au.username LIKE :search_username
                        )
                  ))';

            $params[':search_company'] = $like;
            $params[':search_branch'] = $like;
            $params[':search_code'] = $like;
            $params[':search_plan'] = $like;
            $params[':search_admin'] = $like;
            $params[':search_username'] = $like;
        }

        if ($companyRaw !== '') {
            $companyId = positive_id($companyRaw, 'company_id');
            $where[] = 'b.company_id=:filter_company_id';
            $params[':filter_company_id'] = $companyId;
        }

        if ($planRaw !== '') {
            $planRoleId = positive_id($planRaw, 'plan_role_id');
            $where[] = 'b.role_id=:filter_plan_role_id';
            $params[':filter_plan_role_id'] = $planRoleId;
        }

        if ($statusRaw !== '') {
            $status = normalize_status($statusRaw);
            $where[] = 'b.status=:filter_status';
            $params[':filter_status'] = $status;
        }

        $baseWhereSql =
            $baseWhere === []
                ? ''
                : ' WHERE ' . implode(' AND ', $baseWhere);

        $whereSql =
            $where === []
                ? ''
                : ' WHERE ' . implode(' AND ', $where);

        $bind = static function (PDOStatement $stmt, array $values): void {
            foreach ($values as $key => $value) {
                $stmt->bindValue(
                    $key,
                    $value,
                    is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
                );
            }
        };

        $totalStmt = db()->prepare(
            'SELECT COUNT(*)' .
            $baseFrom .
            $baseWhereSql
        );
        $bind($totalStmt, $baseParams);
        $totalStmt->execute();
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredStmt = db()->prepare(
            'SELECT COUNT(*)' .
            $baseFrom .
            $whereSql
        );
        $bind($filteredStmt, $params);
        $filteredStmt->execute();
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        $summaryStmt = db()->prepare(
            'SELECT
                COUNT(*) AS total_branches,
                COALESCE(SUM(CASE WHEN b.status=1 THEN 1 ELSE 0 END),0) AS active_branches,
                COALESCE(SUM(CASE WHEN b.status=0 THEN 1 ELSE 0 END),0) AS inactive_branches,
                COUNT(DISTINCT b.company_id) AS business_count' .
            $baseFrom .
            $whereSql
        );
        $bind($summaryStmt, $params);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $orderColumns = [
            0 => 'c.company_name',
            1 => 'b.branch_name',
            2 => 'b.branch_code',
            3 => 'r.role_name',
            4 => 'b.id',
            5 => 'b.status',
            6 => 'b.id',
        ];

        $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
        $orderDir =
            strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc'
                ? 'DESC'
                : 'ASC';

        $orderColumn =
            $orderColumns[$orderIndex] ??
            'b.branch_name';

        $sql =
            'SELECT
                b.id,
                b.company_id,
                b.branch_name,
                b.branch_code,
                b.role_id,
                b.status,
                c.company_name,
                r.role_name AS plan_name,
                (
                    SELECT u.name
                    FROM users u
                    INNER JOIN roles ur ON ur.id=u.role_id
                    WHERE u.branch_id=b.id
                      AND ur.role_type=1
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS admin_name' .
            $baseFrom .
            $whereSql .
            ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ',b.id DESC
              LIMIT :start,:length';

        $stmt = db()->prepare($sql);
        $bind($stmt, $params);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (int)$row['id'];
            $row['company_id'] = (int)$row['company_id'];
            $row['role_id'] = (int)$row['role_id'];
            $row['status'] = (int)$row['status'];
            $rows[] = $row;
        }

        if ((int)$user['role_type'] === 2) {
            $businesses = db()->query(
                'SELECT id,company_name
                 FROM companies
                 ORDER BY company_name,id'
            )->fetchAll(PDO::FETCH_ASSOC);

            $plans = db()->query(
                'SELECT id,role_name
                 FROM roles
                 WHERE role_type=1
                   AND company_id IS NULL
                 ORDER BY role_name,id'
            )->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $businesses = [];
            $plans = [];
        }

        json_success('Branches loaded.', [
            'datatable' => [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ],
            'summary' => [
                'total_branches' => (int)($summary['total_branches'] ?? 0),
                'active_branches' => (int)($summary['active_branches'] ?? 0),
                'inactive_branches' => (int)($summary['inactive_branches'] ?? 0),
                'business_count' => (int)($summary['business_count'] ?? 0),
            ],
            'filter_businesses' => $businesses,
            'filter_plans' => $plans,
            'allowed_actions' => $access['actions'],
            'current_user' => [
                'role_type' => (int)$user['role_type'],
                'branch_id' =>
                    $user['branch_id'] === null
                        ? null
                        : (int)$user['branch_id'],
            ],
        ]);
    }

    if ((int) $user['role_type'] === 2) {
        $stmt = db()->query(
            'SELECT b.*, c.company_name, r.role_name AS plan_name,
                    (SELECT u.name FROM users u INNER JOIN roles ur ON ur.id = u.role_id
                     WHERE u.branch_id = b.id AND ur.role_type = 1
                     ORDER BY u.id ASC LIMIT 1) AS admin_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             INNER JOIN roles r ON r.id = b.role_id
             ORDER BY b.id DESC'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT b.*, c.company_name, r.role_name AS plan_name,
                    (SELECT u.name FROM users u INNER JOIN roles ur ON ur.id = u.role_id
                     WHERE u.branch_id = b.id AND ur.role_type = 1
                     ORDER BY u.id ASC LIMIT 1) AS admin_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             INNER JOIN roles r ON r.id = b.role_id
             WHERE b.id = :branch_id'
        );
        $stmt->execute([':branch_id' => (int) $user['branch_id']]);
    }
    json_success('Branches loaded.', [
        'branches' => $stmt->fetchAll(),
        'allowed_actions' => $access['actions'],
        'current_user' => [
            'role_type' => (int) $user['role_type'],
            'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
        ],
    ]);
}

if ($method === 'POST') {
    $access = require_permission('branch-list.php', ACTION_CREATE);
    $user = require_platform_user();
    $data = request_data();
    require_fields($data, [
        'company_id', 'branch_name', 'branch_code', 'plan_role_id',
        'admin_name', 'username', 'password'
    ]);
    validate_branch_admin_contact($data);
    require_strong_password($data['password']);
    $companyId = positive_id($data['company_id'], 'company_id');
    $branchStmt = db()->prepare('SELECT id FROM companies WHERE id = :id AND status = 1 LIMIT 1');
    $branchStmt->execute([':id' => $companyId]);
    if (!$branchStmt->fetch()) {
        json_error('Selected business is invalid or inactive.', 422);
    }
    $planRoleId = positive_id($data['plan_role_id'], 'plan_role_id');
    active_plan($planRoleId);
    $status = isset($data['status']) ? normalize_status($data['status']) : 1;

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO branches
             (company_id, branch_name, branch_code, role_id, status, created_by, created_at, updated_at)
             VALUES (:company_id, :name, :code, :role_id, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => trim((string) $data['branch_name']),
            ':code' => trim((string) $data['branch_code']),
            ':role_id' => $planRoleId,
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);
        $branchId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO users
             (company_id, branch_id, role_id, name, username, email, mobile,
              password_hash, status, created_by, created_at, updated_at)
             VALUES (:company_id, :branch_id, :role_id, :name, :username, :email,
                     :mobile, :password_hash, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':branch_id' => $branchId,
            ':role_id' => $planRoleId,
            ':name' => trim((string) $data['admin_name']),
            ':username' => trim((string) $data['username']),
            ':email' => isset($data['admin_email']) ? trim((string) $data['admin_email']) : null,
            ':mobile' => isset($data['admin_mobile']) ? trim((string) $data['admin_mobile']) : null,
            ':password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
            ':status' => $status,
            ':created_by' => (int) $user['id'],
        ]);
        $pdo->commit();
        audit_log((int) $user['id'], ACTION_CREATE, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'menu_id' => (int) $access['menu']['id'],
            'record_id' => $branchId,
        ]);
        json_success('Branch and Branch Admin created successfully.', ['branch_id' => $branchId], 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            json_error('Branch code or username already exists.', 409);
        }
        throw $exception;
    }
}

if ($method === 'PUT') {
    $access = require_permission('branch-list.php', ACTION_UPDATE);
    $user = $access['user'];
    $data = request_data();
    require_fields($data, ['id', 'branch_name', 'branch_code']);
    $branchId = positive_id($data['id']);
    $branch = branch_record_for_user($user, $branchId);
    $companyId = (int) $branch['company_id'];
    $planRoleId = (int) $branch['role_id'];
    $status = (int) $branch['status'];

    if ((int) $user['role_type'] === 2) {
        $companyId = positive_id(isset($data['company_id']) ? $data['company_id'] : $companyId, 'company_id');
        $planRoleId = positive_id(isset($data['plan_role_id']) ? $data['plan_role_id'] : $planRoleId, 'plan_role_id');
        active_plan($planRoleId);
        $status = isset($data['status']) ? normalize_status($data['status']) : $status;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE branches SET company_id = :company_id, branch_name = :name,
             branch_code = :code, role_id = :role_id, status = :status, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => trim((string) $data['branch_name']),
            ':code' => trim((string) $data['branch_code']),
            ':role_id' => $planRoleId,
            ':status' => $status,
            ':id' => $branchId,
        ]);
        if ($planRoleId !== (int) $branch['role_id']) {
            $stmt = $pdo->prepare(
                'UPDATE users u INNER JOIN roles r ON r.id = u.role_id AND r.role_type = 1
                 SET u.role_id = :role_id, u.updated_at = NOW() WHERE u.branch_id = :branch_id'
            );
            $stmt->execute([':role_id' => $planRoleId, ':branch_id' => $branchId]);
        }
        $pdo->commit();
        json_success('Branch updated successfully.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($method === 'PATCH') {
    $access = require_permission('branch-list.php', ACTION_UPDATE);
    $user = require_platform_user();
    $data = request_data();
    require_fields($data, ['id', 'status']);
    $branchId = positive_id($data['id']);
    branch_record_for_user($user, $branchId);
    db()->prepare('UPDATE branches SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute([':status' => normalize_status($data['status']), ':id' => $branchId]);
    json_success('Branch status updated successfully.');
}

json_error('Method not allowed.', 405);
