<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function medicine_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Medicine Master is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error('No active branch is assigned to your account.', 403);
    }

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1
         LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('Your assigned tenant branch is invalid or inactive.',403);
    }

    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function medicine_require_schema(): void
{
    static $checked=false;
    if ($checked) return;

    try {
        $stmt=db()->query('SHOW COLUMNS FROM `clinic_medicines`');
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        json_error('Medicine Master database table is missing.',500,[
            'schema'=>'Run clinic-ayurveda-billing-upgrade.php once.'
        ]);
    }

    $required=[
        'id','branch_id','medicine_code','medicine_name','medicine_type',
        'unit','pack_size','selling_price','status','created_by','created_at','updated_at'
    ];
    $found=array_map('strtolower',array_column($rows,'Field'));
    $missing=array_values(array_diff($required,$found));
    if ($missing!==[]) {
        json_error('Medicine Master database structure is incomplete.',500,[
            'schema'=>'Missing columns: '.implode(', ',$missing).'. Run clinic-ayurveda-billing-upgrade.php once.'
        ]);
    }
    $checked=true;
}

function medicine_ref_to_id($value): int
{
    if (!is_string($value) || trim($value)==='') {
        json_error('Medicine reference is required.',422,['ref'=>'Medicine reference is required.']);
    }
    try {
        return decryptReference(trim($value),'clinic_medicine');
    } catch (Throwable $e) {
        json_error('Invalid Medicine reference.',422,['ref'=>'Invalid Medicine reference.']);
    }
}

function medicine_generate_code(PDO $pdo,int $branchId): string
{
    $stmt=$pdo->prepare(
        "SELECT medicine_code FROM clinic_medicines
         WHERE branch_id=:branch_id AND medicine_code REGEXP '^MED[0-9]+$'
         ORDER BY CAST(SUBSTRING(medicine_code,4) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $last=(string)($stmt->fetchColumn() ?: '');
    $next=1;
    if ($last!=='' && preg_match('/^MED([0-9]+)$/i',$last,$m)) {
        $next=((int)$m[1])+1;
    }
    return 'MED'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function medicine_decimal($value,string $fieldName): string
{
    $raw=trim((string)$value);
    if ($raw==='') return '0.00';
    if (!is_numeric($raw) || (float)$raw<0) {
        json_error('Medicine validation failed.',422,[$fieldName=>'Enter a valid non-negative price.']);
    }
    return number_format((float)$raw,2,'.','');
}

function medicine_record(array $ctx,int $id): array
{
    $stmt=db()->prepare(
        'SELECT m.*,u.name AS created_by_name
         FROM clinic_medicines m
         LEFT JOIN users u ON u.id=m.created_by
         WHERE m.id=:id AND m.branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>(int)$ctx['branch_id']]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Medicine was not found.',404);
    $row['id']=(int)$row['id'];
    $row['status']=(int)$row['status'];
    $row['selling_price']=number_format((float)$row['selling_price'],2,'.','');
    $row['ref']=encryptReference('clinic_medicine',(int)$row['id']);
    return $row;
}

function medicine_options(array $ctx,int $includeId=0): array
{
    $sql='SELECT id,medicine_code,medicine_name,medicine_type,unit,pack_size,selling_price,status
          FROM clinic_medicines WHERE branch_id=:branch_id AND (status=1';
    $params=[':branch_id'=>(int)$ctx['branch_id']];
    if ($includeId>0) {
        $sql.=' OR id=:include_id';
        $params[':include_id']=$includeId;
    }
    $sql.=') ORDER BY medicine_name,id';
    $stmt=db()->prepare($sql);
    $stmt->execute($params);
    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[]=[
            'ref'=>encryptReference('clinic_medicine',(int)$row['id']),
            'medicine_code'=>(string)$row['medicine_code'],
            'medicine_name'=>(string)$row['medicine_name'],
            'medicine_type'=>(string)$row['medicine_type'],
            'unit'=>$row['unit'],
            'pack_size'=>$row['pack_size'],
            'selling_price'=>number_format((float)$row['selling_price'],2,'.',''),
            'status'=>(int)$row['status'],
            'label'=>(string)$row['medicine_code'].' - '.(string)$row['medicine_name'],
        ];
    }
    return $rows;
}

$method=request_method();
medicine_require_schema();

if ($method==='GET' && isset($_GET['options'])) {
    $access=require_permission('medicine-list.php',ACTION_VIEW);
    $ctx=medicine_context($access['user']);
    json_success('Medicine options loaded.',[
        'allowed_actions'=>$access['actions'],
        'next_medicine_code'=>medicine_generate_code(db(),(int)$ctx['branch_id']),
        'medicines'=>medicine_options($ctx),
        'branch'=>$ctx,
    ]);
}

if ($method==='GET' && isset($_GET['ref'])) {
    $access=require_permission('medicine-list.php',ACTION_VIEW);
    $ctx=medicine_context($access['user']);
    $id=medicine_ref_to_id($_GET['ref'] ?? '');
    json_success('Medicine loaded.',[
        'record'=>medicine_record($ctx,$id),
        'allowed_actions'=>$access['actions'],
        'branch'=>$ctx,
    ]);
}

if ($method==='GET' && isset($_GET['datatable'])) {
    $access=require_permission('medicine-list.php',ACTION_VIEW);
    $ctx=medicine_context($access['user']);
    $branchId=(int)$ctx['branch_id'];
    $draw=max(0,(int)($_GET['draw'] ?? 0));
    $start=max(0,(int)($_GET['start'] ?? 0));
    $lengthRaw=(int)($_GET['length'] ?? 10);
    $length=$lengthRaw<0 ? 100000 : max(1,min(100000,$lengthRaw));
    $search=trim((string)($_GET['search']['value'] ?? ''));
    $where=['m.branch_id=:branch_id'];
    $params=[':branch_id'=>$branchId];
    if ($search!=='') {
        $where[]='(m.medicine_code LIKE :s1 OR m.medicine_name LIKE :s2 OR m.medicine_type LIKE :s3)';
        $like='%'.$search.'%';
        $params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
    }

    $medicineTypeFilter=trim((string)($_GET['medicine_type'] ?? ''));
    if($medicineTypeFilter!==''){
        $where[]='m.medicine_type=:medicine_type';
        $params[':medicine_type']=$medicineTypeFilter;
    }

    $statusFilter=trim((string)($_GET['status'] ?? ''));
    if($statusFilter!=='' && in_array($statusFilter,['0','1'],true)){
        $where[]='m.status=:status';
        $params[':status']=(int)$statusFilter;
    }

    $total=db()->prepare('SELECT COUNT(*) FROM clinic_medicines WHERE branch_id=:branch_id');
    $total->execute([':branch_id'=>$branchId]);
    $recordsTotal=(int)$total->fetchColumn();
    $count=db()->prepare('SELECT COUNT(*) FROM clinic_medicines m WHERE '.implode(' AND ',$where));
    $count->execute($params);
    $recordsFiltered=(int)$count->fetchColumn();

    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN m.status=1 THEN 1 ELSE 0 END),0) AS active_count,
            COALESCE(SUM(CASE WHEN m.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
            COUNT(DISTINCT NULLIF(TRIM(m.medicine_type),\'\')) AS type_count
         FROM clinic_medicines m
         WHERE '.implode(' AND ',$where)
    );

    foreach($params as $key=>$value){
        $summaryStmt->bindValue(
            $key,
            $value,
            in_array($key,[':branch_id',':status'],true)
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];

    $orderCols=['m.medicine_code','m.medicine_name','m.medicine_type','m.selling_price','m.status','m.created_at'];
    $orderIndex=(int)($_GET['order'][0]['column'] ?? 1);
    $orderDir=strtolower((string)($_GET['order'][0]['dir'] ?? 'asc'))==='desc' ? 'DESC':'ASC';
    $orderBy=$orderCols[$orderIndex] ?? 'm.medicine_name';
    $stmt=db()->prepare(
        'SELECT m.id,m.medicine_code,m.medicine_name,m.medicine_type,m.unit,m.pack_size,m.selling_price,m.status,m.created_at
         FROM clinic_medicines m WHERE '.implode(' AND ',$where).
        ' ORDER BY '.$orderBy.' '.$orderDir.',m.id DESC LIMIT :start,:length'
    );
    foreach ($params as $key=>$value) {
        $stmt->bindValue(
            $key,
            $value,
            in_array($key,[':branch_id',':status'],true)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);
    $stmt->bindValue(':length',$length,PDO::PARAM_INT);
    $stmt->execute();
    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ref=encryptReference('clinic_medicine',(int)$row['id']);
        $rows[]=[
            'ref'=>$ref,
            'medicine_code'=>(string)$row['medicine_code'],
            'medicine_name'=>(string)$row['medicine_name'],
            'medicine_type'=>(string)$row['medicine_type'],
            'unit'=>$row['unit'],
            'pack_size'=>$row['pack_size'],
            'selling_price'=>number_format((float)$row['selling_price'],2,'.',''),
            'status'=>(int)$row['status'],
            'status_label'=>(int)$row['status']===1?'Active':'Inactive',
            'created_at'=>$row['created_at'],
            'view_url'=>'medicine-form.php?ref='.rawurlencode($ref).'&view=1',
            'edit_url'=>'medicine-form.php?ref='.rawurlencode($ref),
        ];
    }
    $typeStmt=db()->prepare(
        "SELECT DISTINCT medicine_type
         FROM clinic_medicines
         WHERE branch_id=:branch_id
           AND TRIM(medicine_type)<>''
         ORDER BY medicine_type"
    );
    $typeStmt->execute([':branch_id'=>$branchId]);
    $medicineTypes=array_values(
        array_filter(
            array_map(
                static fn($value)=>(string)$value,
                $typeStmt->fetchAll(PDO::FETCH_COLUMN)
            ),
            static fn($value)=>trim($value)!==''
        )
    );

    json_success('Medicine list loaded.',[
        'datatable'=>[
            'draw'=>$draw,'recordsTotal'=>$recordsTotal,
            'recordsFiltered'=>$recordsFiltered,'data'=>$rows,
        ],
        'summary'=>[
            'total_count'=>(int)($summary['total_count']??0),
            'active_count'=>(int)($summary['active_count']??0),
            'inactive_count'=>(int)($summary['inactive_count']??0),
            'type_count'=>(int)($summary['type_count']??0),
        ],
        'filter_options'=>[
            'medicine_types'=>$medicineTypes,
        ],
        'list_actions'=>$access['actions'],
        'form_actions'=>$access['actions'],
    ]);
}

if ($method==='POST') {
    $data=request_data();
    $action=strtolower(trim((string)($data['action'] ?? 'save')));

    if ($action==='delete') {
        $access=require_permission('medicine-list.php',4);
        $ctx=medicine_context($access['user']);
        $id=medicine_ref_to_id($data['ref'] ?? '');
        $old=medicine_record($ctx,$id);
        db()->prepare('UPDATE clinic_medicines SET status=0,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')
            ->execute([':id'=>$id,':branch_id'=>(int)$ctx['branch_id']]);
        audit_log((int)$access['user']['id'],4,[
            'company_id'=>(int)$ctx['company_id'],'branch_id'=>(int)$ctx['branch_id'],
            'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old,
        ]);
        json_success('Medicine deactivated successfully.');
    }

    if ($action!=='save') json_error('Unsupported Medicine action.',404);

    $isUpdate=isset($data['ref']) && is_string($data['ref']) && trim($data['ref'])!=='';
    $access=require_permission('medicine-list.php',$isUpdate?ACTION_UPDATE:ACTION_CREATE);
    $ctx=medicine_context($access['user']);
    $branchId=(int)$ctx['branch_id'];
    $userId=(int)$access['user']['id'];
    $id=$isUpdate?medicine_ref_to_id($data['ref'] ?? ''):0;

    $name=trim((string)($data['medicine_name'] ?? ''));
    $type=trim((string)($data['medicine_type'] ?? ''));
    $unit=trim((string)($data['unit'] ?? ''));
    $packSize=trim((string)($data['pack_size'] ?? ''));
    $status=(int)($data['status'] ?? 1);
    $price=medicine_decimal($data['selling_price'] ?? '0','selling_price');
    $errors=[];
    if ($name==='') $errors['medicine_name']='Medicine Name is required.';
    elseif (mb_strlen($name)>255) $errors['medicine_name']='Medicine Name cannot exceed 255 characters.';
    if ($type==='') $errors['medicine_type']='Medicine Type is required.';
    elseif (mb_strlen($type)>100) $errors['medicine_type']='Medicine Type cannot exceed 100 characters.';
    if (mb_strlen($unit)>100) $errors['unit']='Unit cannot exceed 100 characters.';
    if (mb_strlen($packSize)>100) $errors['pack_size']='Pack Size cannot exceed 100 characters.';
    if (!in_array($status,[0,1],true)) $errors['status']='Select a valid Status.';

    $dupSql='SELECT id FROM clinic_medicines WHERE branch_id=:branch_id AND medicine_name=:name';
    $dupParams=[':branch_id'=>$branchId,':name'=>$name];
    if ($id>0) { $dupSql.=' AND id<>:id';$dupParams[':id']=$id; }
    $dupSql.=' LIMIT 1';
    $dup=db()->prepare($dupSql);$dup->execute($dupParams);
    if ($dup->fetchColumn()) $errors['medicine_name']='Medicine Name already exists.';
    if ($errors!==[]) json_error('Medicine validation failed.',422,$errors);

    if ($isUpdate) {
        $old=medicine_record($ctx,$id);
        db()->prepare(
            'UPDATE clinic_medicines SET medicine_name=:name,medicine_type=:type,unit=:unit,pack_size=:pack_size,
             selling_price=:price,status=:status,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id'
        )->execute([
            ':name'=>$name,':type'=>$type,':unit'=>$unit!==''?$unit:null,':pack_size'=>$packSize!==''?$packSize:null,
            ':price'=>$price,':status'=>$status,':id'=>$id,':branch_id'=>$branchId,
        ]);
        audit_log($userId,3,[
            'company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],
            'record_id'=>$id,'old_data'=>$old,'new_data'=>[
                'medicine_name'=>$name,'medicine_type'=>$type,'unit'=>$unit,'pack_size'=>$packSize,
                'selling_price'=>$price,'status'=>$status,
            ],
        ]);
        json_success('Medicine updated successfully.',[
            'ref'=>encryptReference('clinic_medicine',$id),'record'=>medicine_record($ctx,$id),
        ]);
    }

    $code=medicine_generate_code(db(),$branchId);
    $stmt=db()->prepare(
        'INSERT INTO clinic_medicines
        (branch_id,medicine_code,medicine_name,medicine_type,unit,pack_size,selling_price,status,created_by,created_at,updated_at)
        VALUES(:branch_id,:code,:name,:type,:unit,:pack_size,:price,:status,:created_by,NOW(),NOW())'
    );
    $stmt->execute([
        ':branch_id'=>$branchId,':code'=>$code,':name'=>$name,':type'=>$type,
        ':unit'=>$unit!==''?$unit:null,':pack_size'=>$packSize!==''?$packSize:null,
        ':price'=>$price,':status'=>$status,':created_by'=>$userId,
    ]);
    $id=(int)db()->lastInsertId();
    audit_log($userId,2,[
        'company_id'=>(int)$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],
        'record_id'=>$id,'new_data'=>[
            'medicine_code'=>$code,'medicine_name'=>$name,'medicine_type'=>$type,
            'unit'=>$unit,'pack_size'=>$packSize,'selling_price'=>$price,'status'=>$status,
        ],
    ]);
    json_success('Medicine saved successfully.',[
        'ref'=>encryptReference('clinic_medicine',$id),'record'=>medicine_record($ctx,$id),
    ]);
}

json_error('Method not allowed.',405);
