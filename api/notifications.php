<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Method not allowed.', 405);
}

$user = require_user();
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$limit = max(1, min(30, $limit));

$sql =
    'SELECT a.id, a.action_id, a.record_id, a.created_at,
            actor.name AS actor_name, m.menu_name, m.icon
     FROM audit_logs a
     INNER JOIN users actor ON actor.id = a.user_id
     LEFT JOIN menus m ON m.id = a.menu_id';
$params = [];
if ((int) $user['role_type'] !== 2) {
    $sql .= ' WHERE a.branch_id = :branch_id';
    $params[':branch_id'] = (int) $user['branch_id'];
}
$sql .= ' ORDER BY a.id DESC LIMIT ' . $limit;
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$labels = action_names(false);

$notifications = [];
foreach ($rows as $row) {
    $actionId = (int) $row['action_id'];
    $action = isset($labels[$actionId]) ? $labels[$actionId] : 'Updated';
    $menu = trim((string) ($row['menu_name'] ?? 'System'));
    $actor = trim((string) ($row['actor_name'] ?? 'User'));
    $record = $row['record_id'] === null ? '' : ' #' . (int) $row['record_id'];
    $notifications[] = [
        'id' => (int) $row['id'],
        'message' => $actor . ' · ' . $action . ' ' . $menu . $record,
        'icon' => $row['icon'] ?: 'activity',
        'created_at' => $row['created_at'],
    ];
}

json_success('Notifications loaded.', ['notifications' => $notifications]);
