<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function lab_test_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0)===2) json_error('Lab Test Master is available only for tenant users.',403);
    $branchId=(int)($user['branch_id'] ?? 0);
    if ($branchId<1) json_error('No active branch is assigned to your account.',403);
    $stmt=db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.',403);
    return [
        'branch_id'=>(int)$row['branch_id'],'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],'company_name'=>(string)$row['company_name'],
    ];
}

function lab_test_require_schema(): void
{
    static $checked=false;if($checked)return;
    try{$stmt=db()->query('SHOW COLUMNS FROM `clinic_lab_tests`');$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){
        json_error('Lab Test Master database table is missing.',500,['schema'=>'Run clinic-ayurveda-billing-upgrade.php once.']);
    }
    $required=['id','branch_id','lab_test_code','lab_test_name','price','status','created_by','created_at','updated_at'];
    $found=array_map('strtolower',array_column($rows,'Field'));
    $missing=array_values(array_diff($required,$found));
    if($missing!==[])json_error('Lab Test Master database structure is incomplete.',500,['schema'=>'Missing columns: '.implode(', ',$missing).'. Run clinic-ayurveda-billing-upgrade.php once.']);
    $checked=true;
}

function lab_test_ref_to_id($value): int
{
    if(!is_string($value)||trim($value)==='')json_error('Lab Test reference is required.',422,['ref'=>'Lab Test reference is required.']);
    try{return decryptReference(trim($value),'clinic_lab_test');}catch(Throwable $e){json_error('Invalid Lab Test reference.',422,['ref'=>'Invalid Lab Test reference.']);}
}

function lab_test_generate_code(PDO $pdo,int $branchId): string
{
    $stmt=$pdo->prepare("SELECT lab_test_code FROM clinic_lab_tests WHERE branch_id=:branch_id AND lab_test_code REGEXP '^LAB[0-9]+$' ORDER BY CAST(SUBSTRING(lab_test_code,4) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute([':branch_id'=>$branchId]);$last=(string)($stmt->fetchColumn() ?: '');$next=1;
    if($last!==''&&preg_match('/^LAB([0-9]+)$/i',$last,$m))$next=((int)$m[1])+1;
    return 'LAB'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function lab_price($value): string
{
    $raw=trim((string)$value);if($raw==='')return '0.00';
    if(!is_numeric($raw)||(float)$raw<0)json_error('Lab Test validation failed.',422,['price'=>'Enter a valid non-negative price.']);
    return number_format((float)$raw,2,'.','');
}

function lab_test_record(array $ctx,int $id): array
{
    $stmt=db()->prepare('SELECT l.*,u.name AS created_by_name FROM clinic_lab_tests l LEFT JOIN users u ON u.id=l.created_by WHERE l.id=:id AND l.branch_id=:branch_id LIMIT 1');
    $stmt->execute([':id'=>$id,':branch_id'=>(int)$ctx['branch_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)json_error('Lab Test was not found.',404);
    $row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$row['price']=number_format((float)$row['price'],2,'.','');
    $row['ref']=encryptReference('clinic_lab_test',(int)$row['id']);return $row;
}

function lab_test_options(array $ctx,int $includeId=0): array
{
    $sql='SELECT id,lab_test_code,lab_test_name,price,status FROM clinic_lab_tests WHERE branch_id=:branch_id AND (status=1';
    $params=[':branch_id'=>(int)$ctx['branch_id']];
    if($includeId>0){$sql.=' OR id=:include_id';$params[':include_id']=$includeId;}
    $sql.=') ORDER BY lab_test_name,id';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$rows[]=[
        'ref'=>encryptReference('clinic_lab_test',(int)$row['id']),
        'lab_test_code'=>(string)$row['lab_test_code'],'lab_test_name'=>(string)$row['lab_test_name'],
        'price'=>number_format((float)$row['price'],2,'.',''),'status'=>(int)$row['status'],
        'label'=>(string)$row['lab_test_code'].' - '.(string)$row['lab_test_name'],
    ];}
    return $rows;
}

$method=request_method();lab_test_require_schema();

if($method==='GET'&&isset($_GET['options'])){
    $access=require_permission('lab-test-list.php',ACTION_VIEW);$ctx=lab_test_context($access['user']);
    json_success('Lab Test options loaded.',[
        'allowed_actions'=>$access['actions'],'next_lab_test_code'=>lab_test_generate_code(db(),(int)$ctx['branch_id']),
        'lab_tests'=>lab_test_options($ctx),'branch'=>$ctx,
    ]);
}

if($method==='GET'&&isset($_GET['ref'])){
    $access=require_permission('lab-test-list.php',ACTION_VIEW);$ctx=lab_test_context($access['user']);$id=lab_test_ref_to_id($_GET['ref'] ?? '');
    json_success('Lab Test loaded.',['record'=>lab_test_record($ctx,$id),'allowed_actions'=>$access['actions'],'branch'=>$ctx]);
}

if($method==='GET'&&isset($_GET['datatable'])){
    $access=require_permission('lab-test-list.php',ACTION_VIEW);$ctx=lab_test_context($access['user']);$branchId=(int)$ctx['branch_id'];
    $draw=max(0,(int)($_GET['draw'] ?? 0));$start=max(0,(int)($_GET['start'] ?? 0));$lengthRaw=(int)($_GET['length'] ?? 10);$length=$lengthRaw<0?100000:max(1,min(100000,$lengthRaw));
    $search=trim((string)($_GET['search']['value'] ?? ''));$where=['l.branch_id=:branch_id'];$params=[':branch_id'=>$branchId];
    if($search!==''){$where[]='(l.lab_test_code LIKE :s1 OR l.lab_test_name LIKE :s2)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;}
    $statusFilter=trim((string)($_GET['status'] ?? ''));
    if($statusFilter!==''&&in_array($statusFilter,['0','1'],true)){$where[]='l.status=:status';$params[':status']=(int)$statusFilter;}
    $total=db()->prepare('SELECT COUNT(*) FROM clinic_lab_tests WHERE branch_id=:branch_id');$total->execute([':branch_id'=>$branchId]);$recordsTotal=(int)$total->fetchColumn();
    $count=db()->prepare('SELECT COUNT(*) FROM clinic_lab_tests l WHERE '.implode(' AND ',$where));$count->execute($params);$recordsFiltered=(int)$count->fetchColumn();

    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN l.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN l.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COALESCE(AVG(l.price),0) AS average_price
         FROM clinic_lab_tests l
         WHERE '.implode(' AND ',$where)
    );
    foreach($params as $key=>$value){
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key,[':branch_id',':status'],true)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }
    $summaryStmt->execute();
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $orderCols=['l.lab_test_code','l.lab_test_name','l.price','l.status','l.created_at'];$orderIndex=(int)($_GET['order'][0]['column'] ?? 1);$orderDir=strtolower((string)($_GET['order'][0]['dir'] ?? 'asc'))==='desc'?'DESC':'ASC';$orderBy=$orderCols[$orderIndex] ?? 'l.lab_test_name';
    $stmt=db()->prepare('SELECT l.id,l.lab_test_code,l.lab_test_name,l.price,l.status,l.created_at FROM clinic_lab_tests l WHERE '.implode(' AND ',$where).' ORDER BY '.$orderBy.' '.$orderDir.',l.id DESC LIMIT :start,:length');
    foreach($params as $key=>$value)$stmt->bindValue($key,$value,in_array($key,[':branch_id',':status'],true)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);$stmt->execute();$rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$ref=encryptReference('clinic_lab_test',(int)$row['id']);$rows[]=[
        'ref'=>$ref,'lab_test_code'=>(string)$row['lab_test_code'],'lab_test_name'=>(string)$row['lab_test_name'],'price'=>number_format((float)$row['price'],2,'.',''),
        'status'=>(int)$row['status'],'status_label'=>(int)$row['status']===1?'Active':'Inactive','created_at'=>$row['created_at'],
        'view_url'=>'lab-test-form.php?ref='.rawurlencode($ref).'&view=1','edit_url'=>'lab-test-form.php?ref='.rawurlencode($ref),
    ];}
    json_success('Lab Test list loaded.',[
        'datatable'=>[
            'draw'=>$draw,
            'recordsTotal'=>$recordsTotal,
            'recordsFiltered'=>$recordsFiltered,
            'data'=>$rows
        ],
        'summary'=>[
            'total_count'=>(int)($summary['total_count']??0),
            'active_count'=>(int)($summary['active_count']??0),
            'inactive_count'=>(int)($summary['inactive_count']??0),
            'average_price'=>(float)($summary['average_price']??0),
        ],
        'list_actions'=>$access['actions'],
        'form_actions'=>$access['actions']
    ]);
}

if($method==='POST'){
    $data=request_data();$action=strtolower(trim((string)($data['action'] ?? 'save')));
    if($action==='delete'){
        $access=require_permission('lab-test-list.php',4);$ctx=lab_test_context($access['user']);$id=lab_test_ref_to_id($data['ref'] ?? '');$old=lab_test_record($ctx,$id);
        db()->prepare('UPDATE clinic_lab_tests SET status=0,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':id'=>$id,':branch_id'=>(int)$ctx['branch_id']]);
        audit_log((int)$access['user']['id'],4,['company_id'=>(int)$ctx['company_id'],'branch_id'=>(int)$ctx['branch_id'],'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);
        json_success('Lab Test deactivated successfully.');
    }
    if($action!=='save')json_error('Unsupported Lab Test action.',404);
    $isUpdate=isset($data['ref'])&&is_string($data['ref'])&&trim($data['ref'])!=='';$access=require_permission('lab-test-list.php',$isUpdate?ACTION_UPDATE:ACTION_CREATE);$ctx=lab_test_context($access['user']);$branchId=(int)$ctx['branch_id'];$userId=(int)$access['user']['id'];$id=$isUpdate?lab_test_ref_to_id($data['ref'] ?? ''):0;
    $name=trim((string)($data['lab_test_name'] ?? ''));$price=lab_price($data['price'] ?? '0');$status=(int)($data['status'] ?? 1);$errors=[];
    if($name==='')$errors['lab_test_name']='Lab Test Name is required.';elseif(mb_strlen($name)>255)$errors['lab_test_name']='Lab Test Name cannot exceed 255 characters.';
    if(!in_array($status,[0,1],true))$errors['status']='Select a valid Status.';
    $dupSql='SELECT id FROM clinic_lab_tests WHERE branch_id=:branch_id AND lab_test_name=:name';$dupParams=[':branch_id'=>$branchId,':name'=>$name];if($id>0){$dupSql.=' AND id<>:id';$dupParams[':id']=$id;}$dupSql.=' LIMIT 1';$dup=db()->prepare($dupSql);$dup->execute($dupParams);if($dup->fetchColumn())$errors['lab_test_name']='Lab Test Name already exists.';
    if($errors!==[])json_error('Lab Test validation failed.',422,$errors);
    if($isUpdate){$old=lab_test_record($ctx,$id);db()->prepare('UPDATE clinic_lab_tests SET lab_test_name=:name,price=:price,status=:status,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':name'=>$name,':price'=>$price,':status'=>$status,':id'=>$id,':branch_id'=>$branchId]);audit_log($userId,3,['company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old,'new_data'=>['lab_test_name'=>$name,'price'=>$price,'status'=>$status]]);json_success('Lab Test updated successfully.',['ref'=>encryptReference('clinic_lab_test',$id),'record'=>lab_test_record($ctx,$id)]);}
    $code=lab_test_generate_code(db(),$branchId);$stmt=db()->prepare('INSERT INTO clinic_lab_tests(branch_id,lab_test_code,lab_test_name,price,status,created_by,created_at,updated_at) VALUES(:branch_id,:code,:name,:price,:status,:created_by,NOW(),NOW())');$stmt->execute([':branch_id'=>$branchId,':code'=>$code,':name'=>$name,':price'=>$price,':status'=>$status,':created_by'=>$userId]);$id=(int)db()->lastInsertId();audit_log($userId,2,['company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'new_data'=>['lab_test_code'=>$code,'lab_test_name'=>$name,'price'=>$price,'status'=>$status]]);json_success('Lab Test saved successfully.',['ref'=>encryptReference('clinic_lab_test',$id),'record'=>lab_test_record($ctx,$id)]);
}

json_error('Method not allowed.',405);
