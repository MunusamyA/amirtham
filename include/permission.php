<?php
declare(strict_types=1);

/*
 * Global numeric permission IDs.
 *
 * Database storage stays compact and compatible with the existing starter kit:
 *   menus.available_action_ids = "1,2,3,7,20"
 *   role_permissions.action_ids = "1,3,7,20"
 *
 * Every read immediately converts CSV values to integer arrays through
 * normalize_csv_ids(). Permission logic never compares string action codes.
 *
 * IMPORTANT: Never reuse an existing ID for another purpose. New actions are
 * added in permission_actions and receive the next numeric ID (55, 56, ...).
 */
const ACTION_VIEW = 1;
const ACTION_CREATE = 2;
const ACTION_UPDATE = 3;
const ACTION_DELETE = 4;
const ACTION_COPY_TABLE = 5;
const ACTION_EXPORT_CSV = 6;
const ACTION_EXPORT_EXCEL = 7;
const ACTION_EXPORT_PDF = 8;
const ACTION_PRINT = 9;
const ACTION_SAVE_DRAFT = 10;
const ACTION_POST = 11;
const ACTION_FINALIZE = 12;
const ACTION_REVERSE = 13;
const ACTION_CANCEL = 14;
const ACTION_DOWNLOAD_PDF = 15;
const ACTION_DOWNLOAD_ATTACHMENT = 16;
const ACTION_DOWNLOAD_DOCUMENT = 17;
const ACTION_DOWNLOAD_GENERATED_FILE = 18;
const ACTION_EMAIL = 19;
const ACTION_WHATSAPP = 20;
const ACTION_SMS = 21;
const ACTION_VERIFY = 22;
const ACTION_APPROVE = 23;
const ACTION_REJECT = 24;
const ACTION_ASSIGN = 25;
const ACTION_CHANGE_STATUS = 26;
const ACTION_ACTIVATE = 27;
const ACTION_DEACTIVATE = 28;
const ACTION_RECEIVE_PAYMENT = 29;
const ACTION_MAKE_PAYMENT = 30;
const ACTION_ALLOCATE_PAYMENT = 31;
const ACTION_RETURN = 32;
const ACTION_REFUND = 33;
const ACTION_WAIVE = 34;
const ACTION_APPLY_DISCOUNT = 35;
const ACTION_APPLY_SCHOLARSHIP = 36;
const ACTION_UPLOAD_IMAGE = 37;
const ACTION_UPLOAD_DOCUMENT = 38;
const ACTION_UPLOAD_VIDEO = 39;
const ACTION_REMOVE_IMAGE = 40;
const ACTION_REMOVE_DOCUMENT = 41;
const ACTION_REMOVE_VIDEO = 42;
const ACTION_DUPLICATE_RECORD = 43;
const ACTION_CLONE_CONFIGURATION = 44;
const ACTION_VIEW_HISTORY = 45;
const ACTION_VIEW_AUDIT_LOG = 46;
const ACTION_MANAGE_THEME = 47;
const ACTION_MANAGE_SMTP = 48;
const ACTION_MANAGE_TAX_SETTINGS = 49;
const ACTION_MANAGE_NUMBERING = 50;
const ACTION_MANAGE_APP_SETTINGS = 51;
const ACTION_MANAGE_ROLES = 52;
const ACTION_MANAGE_PERMISSIONS = 53;
const ACTION_MANAGE_MENUS = 54;

function action_group_names(): array
{
    return [
        1 => 'Basic',
        2 => 'Table / Export',
        3 => 'Transaction',
        4 => 'Download',
        5 => 'Communication',
        6 => 'Approval',
        7 => 'Assignment / Status',
        8 => 'Finance',
        9 => 'Upload / Remove Files',
        10 => 'Utility / History',
        11 => 'Administration',
    ];
}

function default_permission_actions(): array
{
    return [
        1 => ['View', 'View list, detail, page, dashboard or report.', 1],
        2 => ['Create', 'Create a new record.', 1],
        3 => ['Update', 'Edit an existing editable record.', 1],
        4 => ['Delete', 'Delete or soft-delete an eligible record.', 1],
        5 => ['Copy Table Data', 'Copy table or DataTables data to the clipboard.', 2],
        6 => ['Export CSV', 'Export table or report data as CSV.', 2],
        7 => ['Export Excel', 'Export table or report data as Excel.', 2],
        8 => ['Export PDF', 'Export table or report data as PDF.', 2],
        9 => ['Print', 'Print an invoice, receipt, label, table or report.', 2],
        10 => ['Save Draft', 'Save a transaction as draft without final business effect.', 3],
        11 => ['Post', 'Post a draft so it becomes operational, stock or accounting effective.', 3],
        12 => ['Finalize / Lock', 'Finalize and lock a transaction from normal editing.', 3],
        13 => ['Reverse', 'Reverse the effect of an already posted or finalized transaction.', 3],
        14 => ['Cancel', 'Cancel a record or workflow without deleting its history.', 3],
        15 => ['Download PDF', 'Download a generated PDF file.', 4],
        16 => ['Download Attachment', 'Download a user-uploaded attachment.', 4],
        17 => ['Download Document', 'Download a stored business document.', 4],
        18 => ['Download Generated File', 'Download another system-generated file.', 4],
        19 => ['Email', 'Send information or a document by email.', 5],
        20 => ['WhatsApp', 'Send information or a document through WhatsApp.', 5],
        21 => ['SMS', 'Send an SMS message or notification.', 5],
        22 => ['Verify', 'Verify a record, document or payment before approval.', 6],
        23 => ['Approve', 'Approve a pending workflow or request.', 6],
        24 => ['Reject', 'Reject a pending workflow or request.', 6],
        25 => ['Assign', 'Assign an employee, faculty, practitioner, task or responsibility.', 7],
        26 => ['Change Status', 'Change an operational or workflow status.', 7],
        27 => ['Activate', 'Change an inactive record to active.', 7],
        28 => ['Deactivate', 'Change an active record to inactive.', 7],
        29 => ['Receive Payment', 'Record money received from a customer, student or patient.', 8],
        30 => ['Make Payment', 'Record money paid to a supplier or vendor.', 8],
        31 => ['Allocate Payment', 'Allocate a payment against invoices or installments.', 8],
        32 => ['Return', 'Record a product, sales or purchase return.', 8],
        33 => ['Refund', 'Return money to a customer, student or patient.', 8],
        34 => ['Waive', 'Waive an eligible fee or amount.', 8],
        35 => ['Apply Discount', 'Apply a discount to an eligible transaction.', 8],
        36 => ['Apply Scholarship', 'Apply a scholarship adjustment to a student fee plan.', 8],
        37 => ['Upload Image', 'Upload an image file.', 9],
        38 => ['Upload Document', 'Upload a PDF or document file.', 9],
        39 => ['Upload Video', 'Upload a video file.', 9],
        40 => ['Remove Image', 'Remove an uploaded image.', 9],
        41 => ['Remove Document', 'Remove an uploaded document.', 9],
        42 => ['Remove Video', 'Remove an uploaded video.', 9],
        43 => ['Duplicate Record', 'Create a new record from an existing record.', 10],
        44 => ['Clone Configuration', 'Copy a configuration or setup.', 10],
        45 => ['View History', 'View record change and history information.', 10],
        46 => ['View Audit Log', 'View security and audit trail information.', 10],
        47 => ['Manage Theme', 'Change dynamic theme configuration.', 11],
        48 => ['Manage SMTP', 'Change SMTP and email configuration.', 11],
        49 => ['Manage Tax Settings', 'Change GST or tax configuration.', 11],
        50 => ['Manage Numbering', 'Change invoice, receipt or document numbering configuration.', 11],
        51 => ['Manage App Settings', 'Change general application and security settings.', 11],
        52 => ['Manage Roles', 'Manage system, plan or company roles.', 11],
        53 => ['Manage Permissions', 'Assign or change role permissions.', 11],
        54 => ['Manage Menus', 'Manage sidebar and menu configuration.', 11],
    ];
}

/** Create the built-in action master rows when a fresh system is initialized. */
function ensure_default_permission_actions(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO permission_actions
         (id, action_name, purpose, group_id, sort_order, status, created_by, updated_by, created_at, updated_at)
         VALUES (:id, :action_name, :purpose, :group_id, :sort_order, 1, NULL, NULL, NOW(), NOW())'
    );

    foreach (default_permission_actions() as $id => $definition) {
        $stmt->execute([
            ':id' => $id,
            ':action_name' => $definition[0],
            ':purpose' => $definition[1],
            ':group_id' => $definition[2],
            ':sort_order' => $id,
        ]);
    }
}

function ensure_permission_action_master(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $count = (int) db()->query(
        'SELECT COUNT(*) FROM permission_actions WHERE id BETWEEN 1 AND ' . ACTION_MANAGE_MENUS
    )->fetchColumn();
    if ($count < ACTION_MANAGE_MENUS) {
        ensure_default_permission_actions(db());
    }
    $checked = true;
}

function permission_actions_list(bool $activeOnly = true): array
{
    ensure_permission_action_master();
    static $cache = [];
    $cacheKey = $activeOnly ? 'active' : 'all';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT id, action_name, purpose, group_id, sort_order, status, created_by, updated_by,
                   created_at, updated_at
            FROM permission_actions';
    if ($activeOnly) {
        $sql .= ' WHERE status = 1';
    }
    $sql .= ' ORDER BY group_id ASC, sort_order ASC, id ASC';

    $rows = db()->query($sql)->fetchAll();
    $groups = action_group_names();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['group_id'] = (int) $row['group_id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['status'] = (int) $row['status'];
        $row['group_name'] = $groups[$row['group_id']] ?? ('Group ' . $row['group_id']);
        $row['is_core'] = (int) $row['id'] <= ACTION_MANAGE_MENUS;
    }
    unset($row);
    $cache[$cacheKey] = $rows;
    return $rows;
}

function active_action_ids(): array
{
    return array_map(
        static function (array $row): int { return (int) $row['id']; },
        permission_actions_list(true)
    );
}

function action_names(bool $activeOnly = true): array
{
    $labels = [];
    foreach (permission_actions_list($activeOnly) as $row) {
        $labels[(int) $row['id']] = (string) $row['action_name'];
    }
    return $labels;
}

function menu_by_path(string $menuPath): array
{
    $stmt = db()->prepare('SELECT * FROM menus WHERE menu_path = :path AND status = 1 LIMIT 1');
    $stmt->execute([':path' => $menuPath]);
    $menu = $stmt->fetch();
    if (!$menu) {
        json_error('Menu configuration was not found.', 403);
    }
    return $menu;
}

function role_menu_actions(int $roleId, int $menuId): array
{
    $stmt = db()->prepare(
        'SELECT action_ids FROM role_permissions
         WHERE role_id = :role_id AND menu_id = :menu_id AND status = 1 LIMIT 1'
    );
    $stmt->execute([':role_id' => $roleId, ':menu_id' => $menuId]);
    $value = $stmt->fetchColumn();
    return $value === false ? [] : normalize_csv_ids((string) $value);
}

/**
 * Tenant-created employee roles may receive business-feature menus only.
 * Administration remains controlled by the Branch Admin/plan role.
 */
function tenant_role_menu_is_grantable(array $menu): bool
{
    static $menuMap = null;
    if ($menuMap === null) {
        $menuMap = [];
        $rows = db()->query('SELECT id, parent_id, menu_path FROM menus WHERE status = 1')->fetchAll();
        foreach ($rows as $row) {
            $menuMap[(int) $row['id']] = [
                'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
                'menu_path' => (string) $row['menu_path'],
            ];
        }
    }

    $currentId = isset($menu['id']) ? (int) $menu['id'] : 0;
    $guard = 0;
    while ($currentId > 0 && isset($menuMap[$currentId]) && $guard < 25) {
        if ($menuMap[$currentId]['menu_path'] === 'administration') {
            return false;
        }
        $parentId = $menuMap[$currentId]['parent_id'];
        if ($parentId === null) {
            break;
        }
        $currentId = (int) $parentId;
        $guard++;
    }
    return true;
}

function effective_actions_for_menu(array $user, array $menu): array
{
    $active = active_action_ids();
    $available = array_values(array_intersect(
        normalize_csv_ids($menu['available_action_ids']),
        $active
    ));
    $roleActions = role_menu_actions((int) $user['role_id'], (int) $menu['id']);
    $effective = array_values(array_intersect($available, $roleActions));

    if ((int) $user['role_type'] === 3) {
        if (empty($user['branch_plan_role_id'])) {
            return [];
        }
        $planActions = role_menu_actions((int) $user['branch_plan_role_id'], (int) $menu['id']);
        $effective = array_values(array_intersect($effective, $planActions));
    }

    sort($effective, SORT_NUMERIC);
    return array_map('intval', $effective);
}

function require_permission(string $menuPath, int $actionId): array
{
    $user = require_user();
    // Normal authorization is read-only. System definitions are initialized
    // once by platform-register.php, not rewritten on every API request.
    $menu = menu_by_path($menuPath);
    $actions = effective_actions_for_menu($user, $menu);
    if (!in_array((int) $actionId, $actions, true)) {
        json_error('You do not have permission for this action.', 403);
    }

    return ['user' => $user, 'menu' => $menu, 'actions' => $actions];
}

function sidebar_for_user(array $user): array
{
    $menus = db()->query('SELECT * FROM menus WHERE status = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    $nodes = [];
    $visibleIds = [];
    $labels = action_names(true);

    foreach ($menus as $menu) {
        $actions = effective_actions_for_menu($user, $menu);
        $actionData = [];
        foreach ($actions as $actionId) {
            $actionData[] = [
                'id' => (int) $actionId,
                'name' => $labels[(int) $actionId] ?? ('Action ' . (int) $actionId),
            ];
        }

        $menuId = (int) $menu['id'];
        $nodes[$menuId] = [
            'id' => $menuId,
            'parent_id' => $menu['parent_id'] === null ? null : (int) $menu['parent_id'],
            'menu_name' => $menu['menu_name'],
            'menu_path' => $menu['menu_path'],
            'icon' => $menu['icon'],
            'actions' => $actionData,
            'children' => [],
        ];
        if (in_array(ACTION_VIEW, $actions, true)) {
            $visibleIds[$menuId] = true;
        }
    }

    $included = $visibleIds;
    foreach (array_keys($visibleIds) as $visibleId) {
        $currentId = $visibleId;
        $guard = 0;
        while (isset($nodes[$currentId]) && $nodes[$currentId]['parent_id'] !== null && $guard < 25) {
            $parentId = (int) $nodes[$currentId]['parent_id'];
            if (!isset($nodes[$parentId])) {
                break;
            }
            $included[$parentId] = true;
            $currentId = $parentId;
            $guard++;
        }
    }

    return build_sidebar_children(null, $nodes, $included);
}

function build_sidebar_children($parentId, array $nodes, array $included, int $depth = 0): array
{
    if ($depth > 25) {
        return [];
    }

    $result = [];
    foreach ($nodes as $id => $node) {
        if (!isset($included[$id])) {
            continue;
        }
        $nodeParent = $node['parent_id'];
        $isRoot = $parentId === null && ($nodeParent === null || !isset($included[(int) $nodeParent]));
        $isChild = $parentId !== null && $nodeParent !== null && (int) $nodeParent === (int) $parentId;
        if (!$isRoot && !$isChild) {
            continue;
        }
        $node['children'] = build_sidebar_children($id, $nodes, $included, $depth + 1);
        $result[] = $node;
    }
    return $result;
}

function validate_branch_role_permissions(int $branchId, array $permissions): array
{
    $stmt = db()->prepare('SELECT role_id FROM branches WHERE id = :id AND status = 1 LIMIT 1');
    $stmt->execute([':id' => $branchId]);
    $branch = $stmt->fetch();
    if (!$branch) {
        json_error('Active branch was not found.', 422);
    }

    $active = active_action_ids();
    $clean = [];
    foreach ($permissions as $permission) {
        if (!is_array($permission) || !isset($permission['menu_id'])) {
            json_error('Invalid permission format.', 422);
        }
        $menuId = positive_id($permission['menu_id'], 'menu_id');
        $requested = normalize_csv_ids($permission['action_ids'] ?? []);

        $menuStmt = db()->prepare(
            'SELECT id, parent_id, menu_path, available_action_ids
             FROM menus WHERE id = :id AND status = 1'
        );
        $menuStmt->execute([':id' => $menuId]);
        $menu = $menuStmt->fetch();
        if (!$menu) {
            json_error('Invalid menu selected.', 422);
        }
        if (!tenant_role_menu_is_grantable($menu)) {
            json_error('Administration menus cannot be assigned to tenant-created roles.', 422);
        }

        $available = array_values(array_intersect(
            normalize_csv_ids($menu['available_action_ids']),
            $active
        ));
        $plan = role_menu_actions((int) $branch['role_id'], $menuId);
        $allowed = array_values(array_intersect($available, $plan));
        if (array_diff($requested, $allowed) !== []) {
            json_error('A selected action is not available in the current branch plan.', 422);
        }
        if ($requested !== []) {
            $clean[] = ['menu_id' => $menuId, 'action_ids' => csv_ids($requested)];
        }
    }
    return $clean;
}

/**
 * Create required platform configuration for a fresh empty installation.
 * These are system definitions, not demo/business data.
 */
function ensure_system_configuration(): void
{
    $pdo = db();
    ensure_default_permission_actions($pdo);

    /*
     * Detect the one-time v4 -> v5 system upgrade. After v5 exists, do not
     * overwrite menu action choices or plan permissions configured by Admin.
     */
    $existingMenuCount = (int) $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();
    $actionMasterExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM menus WHERE menu_path = 'actions.php'"
    )->fetchColumn() > 0;
    $upgradeFromV4 = $existingMenuCount > 0 && !$actionMasterExists;

    $plans = ['Basic', 'Medium', 'Premium'];
    foreach ($plans as $planName) {
        $stmt = $pdo->prepare(
            'SELECT id FROM roles WHERE company_id IS NULL AND role_type = 1 AND role_name = :name LIMIT 1'
        );
        $stmt->execute([':name' => $planName]);
        if (!(int) $stmt->fetchColumn()) {
            $insert = $pdo->prepare(
                'INSERT INTO roles (company_id, role_name, role_type, status, created_by, created_at, updated_at)
                 VALUES (NULL, :name, 1, 1, NULL, NOW(), NOW())'
            );
            $insert->execute([':name' => $planName]);
        }
    }

    // Built-in platform roles are system definitions. The Platform Owner is
    // linked to the first real user during platform-register.php.
    foreach (['Platform Admin', 'Platform Support User'] as $platformRoleName) {
        $stmt = $pdo->prepare(
            'SELECT id FROM roles WHERE company_id IS NULL AND role_type = 2 AND role_name = :name LIMIT 1'
        );
        $stmt->execute([':name' => $platformRoleName]);
        if (!(int) $stmt->fetchColumn()) {
            $insert = $pdo->prepare(
                'INSERT INTO roles (company_id, role_name, role_type, status, created_by, created_at, updated_at)
                 VALUES (NULL, :name, 2, 1, NULL, NOW(), NOW())'
            );
            $insert->execute([':name' => $platformRoleName]);
        }
    }

    $definitions = [
        ['parent' => null, 'name' => 'Dashboard', 'path' => 'dashboard.php', 'icon' => 'layout-dashboard', 'actions' => '1', 'sort' => 1, 'platform_only' => false],
        ['parent' => null, 'name' => 'Administration', 'path' => 'administration', 'icon' => 'settings', 'actions' => '1', 'sort' => 10, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Businesses', 'path' => 'business-list.php', 'icon' => 'building-2', 'actions' => '1,2,3', 'sort' => 11, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Branches', 'path' => 'branch-list.php', 'icon' => 'map-pin', 'actions' => '1,2,3', 'sort' => 12, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Roles', 'path' => 'role-list.php', 'icon' => 'shield', 'actions' => '1,2,3', 'sort' => 13, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Action Master', 'path' => 'actions.php', 'icon' => 'list-checks', 'actions' => '1,2,3,27,28', 'sort' => 14, 'platform_only' => true],
        ['parent' => 'administration', 'name' => 'Sidebar Menus', 'path' => 'sidebar-list.php', 'icon' => 'panel-left', 'actions' => '1,2,3', 'sort' => 15, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Permissions', 'path' => 'permission-form.php', 'icon' => 'key-round', 'actions' => '1,53', 'sort' => 16, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Security Settings', 'path' => 'settings.php', 'icon' => 'lock-keyhole', 'actions' => '1,51', 'sort' => 17, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Theme Settings', 'path' => 'theme-settings.php', 'icon' => 'palette', 'actions' => '1,47', 'sort' => 18, 'platform_only' => false],
        ['parent' => 'administration', 'name' => 'Mail Configuration', 'path' => 'mail-settings.php', 'icon' => 'mail', 'actions' => '1,48', 'sort' => 19, 'platform_only' => false],
        ['parent' => null, 'name' => 'Employees', 'path' => 'employees', 'icon' => 'users', 'actions' => '1', 'sort' => 20, 'platform_only' => false],
        ['parent' => 'employees', 'name' => 'Employee Form', 'path' => 'employee-form.php', 'icon' => 'user-plus', 'actions' => '1,2,3', 'sort' => 21, 'platform_only' => false],
        ['parent' => 'employees', 'name' => 'Employee List', 'path' => 'employee-list.php', 'icon' => 'list', 'actions' => '1,5,6,7,8,9', 'sort' => 22, 'platform_only' => false],
        ['parent' => null, 'name' => 'Food Supplementary', 'path' => 'food-supplementary', 'icon' => 'package', 'actions' => '1', 'sort' => 30, 'platform_only' => false],
        ['parent' => 'food-supplementary', 'name' => 'HSN Master', 'path' => 'hsn-master.php', 'icon' => 'receipt-text', 'actions' => '1,2,3,5,6,7,8,9,27,28', 'sort' => 31, 'platform_only' => false],
    ];

    $pathToId = [];
    foreach ($definitions as $definition) {
        $parentId = null;
        if ($definition['parent'] !== null) {
            if (isset($pathToId[$definition['parent']])) {
                $parentId = (int) $pathToId[$definition['parent']];
            } else {
                $stmt = $pdo->prepare('SELECT id FROM menus WHERE menu_path = :path LIMIT 1');
                $stmt->execute([':path' => $definition['parent']]);
                $parentId = (int) $stmt->fetchColumn();
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM menus WHERE menu_path = :path LIMIT 1');
        $stmt->execute([':path' => $definition['path']]);
        $menuId = (int) $stmt->fetchColumn();
        if ($menuId > 0) {
            if ($upgradeFromV4) {
                /* One-time semantic remap for the built-in system menus. */
                $update = $pdo->prepare(
                    'UPDATE menus SET parent_id = :parent_id, menu_name = :name, icon = :icon,
                     available_action_ids = :actions, sort_order = :sort_order, status = 1, updated_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    ':parent_id' => $parentId ?: null,
                    ':name' => $definition['name'],
                    ':icon' => $definition['icon'],
                    ':actions' => csv_ids($definition['actions']),
                    ':sort_order' => $definition['sort'],
                    ':id' => $menuId,
                ]);
            }
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO menus (parent_id, menu_name, menu_path, icon, available_action_ids, sort_order, status, created_at, updated_at)
                 VALUES (:parent_id, :name, :path, :icon, :actions, :sort_order, 1, NOW(), NOW())'
            );
            $insert->execute([
                ':parent_id' => $parentId ?: null,
                ':name' => $definition['name'],
                ':path' => $definition['path'],
                ':icon' => $definition['icon'],
                ':actions' => csv_ids($definition['actions']),
                ':sort_order' => $definition['sort'],
            ]);
            $menuId = (int) $pdo->lastInsertId();
        }
        $pathToId[$definition['path']] = $menuId;
    }

    $planPermissions = [
        'Basic' => [
            'dashboard.php' => '1',
            'business-list.php' => '1,3',
            'branch-list.php' => '1,3',
            'role-list.php' => '1,2,3',
            'permission-form.php' => '1,53',
            'settings.php' => '1,51',
            'theme-settings.php' => '1,47',
            'mail-settings.php' => '1,48',
            'employee-form.php' => '1,2,3',
            'employee-list.php' => '1,5,6,7,8,9',
        ],
        'Medium' => 'all',
        'Premium' => 'all',
    ];

    foreach ($plans as $planName) {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE company_id IS NULL AND role_type = 1 AND role_name = :name LIMIT 1');
        $stmt->execute([':name' => $planName]);
        $roleId = (int) $stmt->fetchColumn();
        if (!$roleId) {
            continue;
        }

        foreach ($definitions as $definition) {
            if (!empty($definition['platform_only'])) {
                continue;
            }
            $menuId = (int) ($pathToId[$definition['path']] ?? 0);
            if (!$menuId) {
                continue;
            }

            if ($planPermissions[$planName] === 'all') {
                $actions = csv_ids($definition['actions']);
            } else {
                if (!isset($planPermissions[$planName][$definition['path']])) {
                    continue;
                }
                $actions = csv_ids($planPermissions[$planName][$definition['path']]);
            }

            $permissionSql =
                'INSERT INTO role_permissions (role_id, menu_id, action_ids, status, created_at, updated_at)
                 VALUES (:role_id, :menu_id, :actions, 1, NOW(), NOW())';
            if ($upgradeFromV4) {
                $permissionSql .=
                    ' ON DUPLICATE KEY UPDATE action_ids = VALUES(action_ids), status = 1, updated_at = NOW()';
            } else {
                $permissionSql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $permissionSql);
            }
            $permissionStmt = $pdo->prepare($permissionSql);
            $permissionStmt->execute([
                ':role_id' => $roleId,
                ':menu_id' => $menuId,
                ':actions' => $actions,
            ]);
        }
    }
}

function ensure_platform_owner_permissions(): void
{
    $pdo = db();
    $roles = $pdo->query(
        "SELECT id FROM roles WHERE company_id IS NULL AND role_type = 2 AND role_name = 'Platform Owner' AND status = 1"
    )->fetchAll();
    if (!$roles) {
        return;
    }

    $active = active_action_ids();
    $menus = $pdo->query('SELECT id, available_action_ids FROM menus WHERE status = 1')->fetchAll();
    $stmt = $pdo->prepare(
        'INSERT INTO role_permissions (role_id, menu_id, action_ids, status, created_at, updated_at)
         VALUES (:role_id, :menu_id, :actions, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE action_ids = VALUES(action_ids), status = 1, updated_at = NOW()'
    );

    foreach ($roles as $role) {
        foreach ($menus as $menu) {
            $actions = array_values(array_intersect(
                normalize_csv_ids($menu['available_action_ids']),
                $active
            ));
            $stmt->execute([
                ':role_id' => (int) $role['id'],
                ':menu_id' => (int) $menu['id'],
                ':actions' => csv_ids($actions),
            ]);
        }
    }
}
