<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

function menu_sections(): array {
    return ['common'=>'Common / System','clinic'=>'Clinic Management','college'=>'College Management','food_supplementary'=>'Food Supplementary'];
}
function valid_menu_section($value): string {
    $value = strtolower(trim((string)$value));
    if (!array_key_exists($value, menu_sections())) json_error('Select a valid management section.',422);
    return $value;
}
function validate_menu_parent($parentId, int $menuId, string $section): ?int {
    if ($parentId === null || $parentId === '' || (int)$parentId === 0) return null;
    $parentId = positive_id($parentId, 'parent_id');
    if ($parentId === $menuId) json_error('A menu cannot be its own parent.',422);
    $stmt = db()->prepare('SELECT id,parent_id,menu_section FROM menus WHERE id=:id LIMIT 1');
    $seen = []; $currentId = $parentId;
    while ($currentId !== null) {
        if (isset($seen[$currentId]) || count($seen) >= 512) json_error('Invalid circular menu hierarchy.',422);
        $seen[$currentId]=true;
        if ($currentId === $menuId) json_error('Circular menu hierarchy is not allowed.',422);
        $stmt->execute([':id'=>$currentId]); $current=$stmt->fetch();
        if (!$current) json_error('Selected parent menu was not found.',422);
        if ($current['menu_section'] !== $section) json_error('Parent menu must belong to the selected management section.',422);
        $currentId = $current['parent_id']===null ? null : (int)$current['parent_id'];
    }
    return $parentId;
}
function validated_menu_actions($value): string {
    $actions=normalize_csv_ids($value); $validIds=active_action_ids();
    if ($actions===[] || array_diff($actions,$validIds)!==[]) json_error('Select valid active menu actions.',422);
    if (!in_array(ACTION_VIEW,$actions,true)) array_unshift($actions,ACTION_VIEW);
    return csv_ids($actions);
}
$method=request_method();
if ($method==='GET') {
    $access=require_permission('sidebar-list.php',ACTION_VIEW);
    require_platform_user();
    if (isset($_GET['id'])) {
        $id=positive_id($_GET['id']);
        $stmt=db()->prepare('SELECT m.*,p.menu_name AS parent_name FROM menus m LEFT JOIN menus p ON p.id=m.parent_id WHERE m.id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]); $menu=$stmt->fetch();
        if (!$menu) json_error('Menu was not found.',404);
        json_success('Menu loaded.',['menu'=>$menu,'sections'=>menu_sections(),'actions'=>permission_actions_list(true),'action_names'=>action_names(true)]);
    }
    $filter=isset($_GET['section']) && $_GET['section']!=='' ? valid_menu_section($_GET['section']) : null;
    if ($filter===null) {
        $stmt=db()->query('SELECT m.*,p.menu_name AS parent_name FROM menus m LEFT JOIN menus p ON p.id=m.parent_id ORDER BY m.menu_section,m.sort_order,m.id');
    } else {
        $stmt=db()->prepare('SELECT m.*,p.menu_name AS parent_name FROM menus m LEFT JOIN menus p ON p.id=m.parent_id WHERE m.menu_section=:section ORDER BY m.sort_order,m.id');
        $stmt->execute([':section'=>$filter]);
    }
    json_success('Menus loaded.',['menus'=>$stmt->fetchAll(),'sections'=>menu_sections(),'actions'=>permission_actions_list(true),'action_names'=>action_names(true),'allowed_actions'=>$access['actions']]);
}
if ($method==='POST') {
    $access=require_permission('sidebar-list.php',ACTION_CREATE); $user=require_platform_user(); $data=request_data();
    require_fields($data,['menu_name','menu_path','available_action_ids']);
    $section=valid_menu_section($data['menu_section']??'common');
    $parentId=validate_menu_parent($data['parent_id']??null,0,$section);
    $actions=validated_menu_actions($data['available_action_ids']);
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('INSERT INTO menus (parent_id,menu_section,menu_name,menu_path,icon,available_action_ids,sort_order,status,created_at,updated_at)
            VALUES (:parent_id,:section,:menu_name,:menu_path,:icon,:actions,:sort_order,:status,NOW(),NOW())');
        $stmt->execute([
            ':parent_id'=>$parentId,':section'=>$section,':menu_name'=>trim((string)$data['menu_name']),
            ':menu_path'=>trim((string)$data['menu_path']),':icon'=>isset($data['icon'])?trim((string)$data['icon']):null,
            ':actions'=>$actions,':sort_order'=>isset($data['sort_order'])?(int)$data['sort_order']:0,
            ':status'=>isset($data['status'])?normalize_status($data['status']):1
        ]);
        $menuId=(int)$pdo->lastInsertId();
        $stmt=$pdo->prepare('INSERT INTO role_permissions (role_id,menu_id,action_ids,status,created_at,updated_at)
            VALUES (:role_id,:menu_id,:actions,1,NOW(),NOW())
            ON DUPLICATE KEY UPDATE action_ids=VALUES(action_ids),status=1,updated_at=NOW()');
        $stmt->execute([':role_id'=>(int)$user['role_id'],':menu_id'=>$menuId,':actions'=>$actions]);
        $pdo->commit();
        audit_log((int)$user['id'],ACTION_CREATE,['menu_id'=>(int)$access['menu']['id'],'record_id'=>$menuId]);
        json_success('Sidebar menu created successfully.',['menu_id'=>$menuId],201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode()==='23000') json_error('Menu path already exists or another database constraint failed.',409);
        throw $exception;
    }
}
if ($method==='PUT') {
    $access=require_permission('sidebar-list.php',ACTION_UPDATE); $user=require_platform_user(); $data=request_data();
    require_fields($data,['id','menu_name','menu_path','available_action_ids']);
    $menuId=positive_id($data['id']);
    $stmt=db()->prepare('SELECT * FROM menus WHERE id=:id LIMIT 1'); $stmt->execute([':id'=>$menuId]); $old=$stmt->fetch();
    if (!$old) json_error('Menu was not found.',404);
    $section=valid_menu_section($data['menu_section']??$old['menu_section']);
    $parentId=validate_menu_parent($data['parent_id']??null,$menuId,$section);
    $actions=validated_menu_actions($data['available_action_ids']);
    // Prevent a change of section from leaving a mixed-section subtree.
    $children=db()->prepare('SELECT COUNT(*) FROM menus WHERE parent_id=:id AND menu_section<>:section');
    $children->execute([':id'=>$menuId,':section'=>$section]);
    if ((int)$children->fetchColumn()>0) json_error('Move the child menus to the new management section first.',422);
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('UPDATE menus SET parent_id=:parent_id,menu_section=:section,menu_name=:menu_name,menu_path=:menu_path,
            icon=:icon,available_action_ids=:actions,sort_order=:sort_order,status=:status,updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            ':parent_id'=>$parentId,':section'=>$section,':menu_name'=>trim((string)$data['menu_name']),
            ':menu_path'=>trim((string)$data['menu_path']),':icon'=>isset($data['icon'])?trim((string)$data['icon']):null,
            ':actions'=>$actions,':sort_order'=>isset($data['sort_order'])?(int)$data['sort_order']:0,
            ':status'=>isset($data['status'])?normalize_status($data['status']):1,':id'=>$menuId
        ]);
        $allowed=normalize_csv_ids($actions);
        $perm=$pdo->prepare('SELECT id,action_ids,status FROM role_permissions WHERE menu_id=:menu_id'); $perm->execute([':menu_id'=>$menuId]);
        $update=$pdo->prepare('UPDATE role_permissions SET action_ids=:actions,status=:status,updated_at=NOW() WHERE id=:id');
        foreach ($perm->fetchAll() as $row) {
            $clean=array_values(array_intersect(normalize_csv_ids($row['action_ids']),$allowed));
            $update->execute([':actions'=>implode(',',$clean),':status'=>$clean===[]?0:(int)$row['status'],':id'=>(int)$row['id']]);
        }
        if ((int)$user['role_type']===2 && (string)$user['role_name']==='Platform Owner') {
            $owner=$pdo->prepare('INSERT INTO role_permissions (role_id,menu_id,action_ids,status,created_at,updated_at)
                VALUES (:role_id,:menu_id,:actions,1,NOW(),NOW())
                ON DUPLICATE KEY UPDATE action_ids=VALUES(action_ids),status=1,updated_at=NOW()');
            $owner->execute([':role_id'=>(int)$user['role_id'],':menu_id'=>$menuId,':actions'=>$actions]);
        }
        $pdo->commit();
        audit_log((int)$user['id'],ACTION_UPDATE,['menu_id'=>(int)$access['menu']['id'],'record_id'=>$menuId,'old_data'=>$old]);
        json_success('Sidebar menu updated successfully.');
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}
json_error('Method not allowed.',405);
