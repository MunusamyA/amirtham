<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

$user = require_user();

/* This response is a view of the permission tree, not a permission grant. */
header('Cache-Control: private, no-store, max-age=0');

/* These keys MUST match the preceding menu migration's menus.menu_section. */
$managementNames = [
    'clinic' => 'Clinic Management',
    'college' => 'College Management',
    'food_supplementary' => 'Food Supplementary',
];
$managementPaths = [
    'clinic' => 'management-clinic',
    'college' => 'management-college',
    'food_supplementary' => 'management-food-supplementary',
];

$pdo = db();
/* The module selector itself is permission-gated: an active root and VIEW grant are required. */
$rootStmt = $pdo->prepare(
    'SELECT m.menu_section, m.menu_path
       FROM menus m
       INNER JOIN role_permissions rp ON rp.menu_id = m.id
      WHERE rp.role_id = :role_id AND rp.status = 1 AND m.status = 1
        AND FIND_IN_SET(:view_id, rp.action_ids) > 0
        AND m.menu_path IN (:clinic, :college, :food)'
);
$rootStmt->execute([
    ':role_id' => (int) $user['role_id'],
    ':view_id' => (string) ACTION_VIEW,
    ':clinic' => $managementPaths['clinic'],
    ':college' => $managementPaths['college'],
    ':food' => $managementPaths['food_supplementary'],
]);
$grantedPaths = [];
foreach ($rootStmt->fetchAll(PDO::FETCH_ASSOC) as $root) {
    $grantedPaths[(string)$root['menu_path']] = true;
}

$sections = [];
foreach ($managementNames as $key => $label) {
    if (isset($grantedPaths[$managementPaths[$key]])) {
        $sections[] = ['key' => $key, 'label' => $label];
    }
}

/* A stale/tampered cookie never unlocks an unpermitted module. */
$requested = strtolower(trim((string)($_COOKIE['amirtham_management_section_v1'] ?? '')));
$allowedKeys = array_column($sections, 'key');
$selected = in_array($requested, $allowedKeys, true)
    ? $requested
    : ($allowedKeys[0] ?? 'common');

/* Reuse the original, authoritative permission-aware menu builder. */
$rawMenus = sidebar_for_user($user);

/* Associate menu IDs and paths with sections, even if the builder omits menu_section. */
$sectionById = [];
$sectionByPath = [];
$sortOrderById = [];
$sortOrderByPath = [];
foreach ($pdo->query('SELECT id, menu_path, menu_section, sort_order FROM menus ORDER BY sort_order ASC')->fetchAll(PDO::FETCH_ASSOC) as $menuRow) {
    $sectionById[(int)$menuRow['id']] = (string)$menuRow['menu_section'];
    $sectionByPath[(string)$menuRow['menu_path']] = (string)$menuRow['menu_section'];
    $sortOrderById[(int)$menuRow['id']] = (int)$menuRow['sort_order'];
    $sortOrderByPath[(string)$menuRow['menu_path']] = (int)$menuRow['sort_order'];
}

/*
|--------------------------------------------------------------------------
| Sidebar-only exclusions
|--------------------------------------------------------------------------
| Do NOT set these rows inactive or delete their permissions: form pages are
| still required for Add/Edit operations, and the three selector rows remain
| required for permission-checking the topbar buttons.
|--------------------------------------------------------------------------
*/
$sidebarHiddenPaths = array_fill_keys([
    // The management sections are selected by buttons in the TOPBAR only.
    'management-clinic', 'management-college', 'management-food-supplementary',
    // Legacy/dormant header rows from the original navigation.
    'clinic', 'community-college', 'food-supplementary',
    'employees', 'management-common-forms',
    // Add/Edit/detail pages should be reached from their list pages, not sidebar links.
    'employee-form.php', 'account-form.php',
    'course-form.php', 'course-subject-form.php', 'course-fee-form.php',
    'batch-form.php', 'admission-form.php', 'attendance-form.php',
    'supplier-form.php', 'customer-form.php', 'seller-type-form.php',
    'product-form.php', 'purchase-form.php', 'purchase-return-form.php',
    'sales-return.php', 'customer-payment.php', 'supplier-payment.php',
    'expense-form.php', 'expense-payment.php',
], true);

/** Recognize nodes regardless of how the existing sidebar builder names its fields. */
function am_is_menu_node(array $row): bool
{
    return array_key_exists('menu_path', $row)
        || (array_key_exists('id', $row) && (array_key_exists('menu_name', $row) || array_key_exists('name', $row)))
        || (array_key_exists('path', $row) && (array_key_exists('menu_name', $row) || array_key_exists('name', $row)))
        || (array_key_exists('href', $row) && (array_key_exists('menu_name', $row) || array_key_exists('name', $row)))
        || (array_key_exists('menu_id', $row) && array_key_exists('name', $row));
}

/** Normalize paths without modifying any real navigation URL. */
function am_sidebar_lookup_path(array $node): string
{
    $path = (string)($node['menu_path'] ?? $node['path'] ?? $node['href'] ?? $node['url'] ?? '');
    $pathname = parse_url($path, PHP_URL_PATH);
    return strtolower(basename(trim((string)($pathname === false ? $path : $pathname), '/')));
}

/** Preserve all original node data and permissions; only omit sidebar-only entries. */
function am_filter_menu_tree($value, string $selected, array $sectionById, array $sectionByPath, array $hiddenPaths)
{
    if (!is_array($value)) return $value;

    if (am_is_menu_node($value)) {
        $id = (int)($value['id'] ?? $value['menu_id'] ?? 0);
        $path = am_sidebar_lookup_path($value);
        if (isset($hiddenPaths[$path])) return null;
        $section = $sectionById[$id] ?? $sectionByPath[$path] ?? (string)($value['menu_section'] ?? 'common');
        if ($section !== 'common' && $section !== $selected) return null;
        foreach (['children', 'submenus', 'items', 'menus'] as $childKey) {
            if (isset($value[$childKey]) && is_array($value[$childKey])) {
                $value[$childKey] = am_filter_menu_tree($value[$childKey], $selected, $sectionById, $sectionByPath, $hiddenPaths);
            }
        }
        return $value;
    }

    $isList = array_is_list($value);
    $filtered = [];
    foreach ($value as $key => $row) {
        if (is_array($row)) {
            $row = am_filter_menu_tree($row, $selected, $sectionById, $sectionByPath, $hiddenPaths);
            if ($row === null) continue;
        }
        if ($isList) $filtered[] = $row;
        else $filtered[$key] = $row;
    }
    return $filtered;
}

$visibleMenus = am_filter_menu_tree($rawMenus, $selected, $sectionById, $sectionByPath, $sidebarHiddenPaths);

/*
 * One sorting rule for both Common and management menus:
 * the database menus.sort_order only. No hardcoded priorities, alphabetical
 * comparison or ID-based tie breaker. Keep existing permissions and menu data.
 * PHP 8 usort() is stable for equal sort_order values.
 */
function am_sort_sidebar_tree($value, array $byId, array $byPath)
{
    if (!is_array($value)) return $value;

    if (am_is_menu_node($value)) {
        foreach (['children', 'submenus', 'items', 'menus'] as $childKey) {
            if (isset($value[$childKey]) && is_array($value[$childKey])) {
                $value[$childKey] = am_sort_sidebar_tree($value[$childKey], $byId, $byPath);
            }
        }
        return $value;
    }

    $isList = array_is_list($value);
    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = am_sort_sidebar_tree($item, $byId, $byPath);
        }
    }
    if (!$isList || count($value) < 2) return $value;

    // Do not reorder unrelated arrays, metadata, or grouped API payloads.
    foreach ($value as $item) {
        if (!is_array($item) || !am_is_menu_node($item)) return $value;
    }

    foreach ($value as &$node) {
        $id = (int)($node['id'] ?? $node['menu_id'] ?? 0);
        $path = am_sidebar_lookup_path($node);
        $rank = $byId[$id] ?? $byPath[$path] ?? (int)($node['sort_order'] ?? PHP_INT_MAX);
        // Supply the true database value even if the original builder was stale.
        $node['sort_order'] = $rank;
        if (array_key_exists('order', $node)) $node['order'] = $rank;
    }
    unset($node);

    usort($value, static function (array $a, array $b): int {
        return (int)$a['sort_order'] <=> (int)$b['sort_order'];
    });
    return $value;
}

$visibleMenus = am_sort_sidebar_tree($visibleMenus, $sortOrderById, $sortOrderByPath);

/* Keep the existing company logo resolution unchanged. */
$branchId = $user['branch_id'] === null ? null : (int)$user['branch_id'];
$companyLogoPath = trim((string)app_setting('company_logo', '', $branchId));
$companyLogoUrl = '';
if ($companyLogoPath !== '') {
    if (preg_match('~^(?:https?:)?//~i', $companyLogoPath)
        || strpos($companyLogoPath, 'data:') === 0
        || strpos($companyLogoPath, 'blob:') === 0) {
        $companyLogoUrl = $companyLogoPath;
    } else {
        $uploadBaseUrl = rtrim((string)env_value('UPLOAD_BASE_URL', ''), '/');
        if ($uploadBaseUrl !== '' && strpos($companyLogoPath, 'uploads/') === 0) {
            $companyLogoUrl = $uploadBaseUrl . '/'
                . ltrim(substr($companyLogoPath, strlen('uploads/')), '/');
        } else {
            $companyLogoUrl = ltrim(str_replace('\\', '/', $companyLogoPath), '/');
        }
    }
}

json_success('Sidebar loaded.', [
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id'],
        'role_name' => $user['role_name'],
        'role_type' => (int)$user['role_type'],
        'company_id' => $user['company_id'] === null ? null : (int)$user['company_id'],
        'company_name' => $user['company_name'],
        'branch_id' => $user['branch_id'] === null ? null : (int)$user['branch_id'],
        'branch_name' => $user['branch_name'],
        'company_logo' => $companyLogoPath,
        'company_logo_url' => $companyLogoUrl,
    ],
    'menus' => $visibleMenus,
    'management' => [
        'selected' => $selected,
        'sections' => $sections,
    ],
]);
