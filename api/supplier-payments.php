<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/supplier-finance.php';

/*
 * AMIRTHAM - Supplier Payment / Settlement API
 * payment_type:    1=Opening Balance, 2=Overall Outstanding (FIFO), 3=Particular Purchase
 * allocation_type: 1=Purchase, 2=Opening Balance
 * Edit/Delete internally undo the old child/allocation effects and recalculate purchases.
 */

function sp_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Supplier Payment is available only for tenant users.', 403);
    }
    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);
    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function sp_require_schema(): void
{
    $tables = [
        'food_suppliers','food_purchases','food_purchase_payments','food_purchase_returns',
        'accounts','food_supplier_payments','food_supplier_payment_details',
        'food_supplier_payment_allocations'
    ];
    foreach ($tables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            json_error('Supplier Payment schema is incomplete. Missing table: ' . $table . '. Run sql/supplier-payment-ledger.sql first.', 500);
        }
    }
    $columns = [
        'food_suppliers'=>['opening_balance','opening_balance_date'],
        'food_supplier_payments'=>['payment_no','payment_type','payment_date','amount','posting_status','reversed_at','reversed_by','status'],
        'food_supplier_payment_details'=>['supplier_payment_id','payment_mode','account_id','amount','reference_no','cheque_no','cheque_date','status'],
        'food_supplier_payment_allocations'=>['supplier_payment_id','allocation_type','purchase_id','allocated_amount','status'],
    ];
    foreach ($columns as $table=>$list) {
        foreach ($list as $column) {
            $stmt = db()->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . db()->quote($column));
            if (!$stmt || !$stmt->fetchColumn()) {
                json_error('Supplier Payment schema update is required. Missing column: ' . $table . '.' . $column . '.', 500);
            }
        }
    }
}

function sp_ref_to_id($value, string $purpose, string $label): int
{
    if (!is_string($value) || trim($value) === '') json_error($label . ' reference is required.', 422);
    try { $id = (int)decryptReference(trim($value), $purpose); }
    catch (Throwable $e) { json_error('Invalid ' . $label . ' reference.', 422); }
    if ($id < 1) json_error('Invalid ' . $label . ' reference.', 422);
    return $id;
}

function sp_type_ref(int $type): string
{
    return encryptReference('supplier_payment_type', $type);
}

function sp_type_from_ref($value): int
{
    $type = sp_ref_to_id($value, 'supplier_payment_type', 'Payment Type');
    if (!in_array($type, [1,2,3], true)) json_error('Invalid Payment Type.', 422);
    return $type;
}

function sp_type_label(int $type): string
{
    return [1=>'Opening Balance',2=>'Overall Outstanding',3=>'Particular Purchase'][$type] ?? 'Unknown';
}

function sp_valid_date($value, string $field='payment_date', bool $required=true): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        if (!$required) return null;
        json_error(($field === 'cheque_date' ? 'Cheque Date' : 'Payment Date') . ' is required.', 422, [$field=>'This date is required.']);
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $d->format('Y-m-d') !== $value) {
        json_error('Enter a valid date.', 422, [$field=>'Enter a valid date.']);
    }
    return $value;
}

function sp_nullable($value, int $max=255): ?string
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : mb_substr($value, 0, $max);
}

function sp_money($value, string $label='Amount'): float
{
    if ($value === '' || $value === null) return 0.0;
    if (!is_numeric($value)) json_error($label . ' must be a valid amount.', 422);
    $v = round((float)$value, 2);
    if ($v < 0) json_error($label . ' cannot be negative.', 422);
    return $v;
}

function sp_supplier(PDO $pdo, int $branchId, int $supplierId, bool $active=true): array
{
    $sql = 'SELECT id,supplier_code,supplier_name,mobile,opening_balance,opening_balance_date,status,created_at
            FROM food_suppliers WHERE id=:id AND branch_id=:branch_id';
    if ($active) $sql .= ' AND status=1';
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id'=>$supplierId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Selected Supplier is inactive or unavailable.', 422, ['supplier_ref'=>'Select an active Supplier.']);
    $row['id'] = (int)$row['id'];
    $row['opening_balance'] = (float)$row['opening_balance'];
    $row['status'] = (int)$row['status'];
    return $row;
}

function sp_suppliers(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,supplier_code,supplier_name,mobile
         FROM food_suppliers
         WHERE branch_id=:branch_id AND status=1
         ORDER BY supplier_name,supplier_code'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[]=[
            'id'=>(int)$r['id'],
            'ref'=>encryptReference('supplier',(int)$r['id']),
            'supplier_code'=>(string)$r['supplier_code'],
            'supplier_name'=>(string)$r['supplier_name'],
            'mobile'=>(string)($r['mobile'] ?? ''),
        ];
    }
    return $rows;
}

function sp_accounts(int $branchId): array
{
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,is_default_cash
         FROM accounts
         WHERE branch_id=:branch_id AND status=1
         ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[]=[
            'id'=>(int)$r['id'],
            'account_code'=>(string)$r['account_code'],
            'account_name'=>(string)$r['account_name'],
            'account_type'=>(int)$r['account_type'],
            'is_default_cash'=>(int)($r['is_default_cash'] ?? 0),
        ];
    }
    return $rows;
}

function sp_account(PDO $pdo, int $branchId, int $accountId, int $requiredType): void
{
    $stmt=$pdo->prepare('SELECT id,account_type,status FROM accounts WHERE id=:id AND branch_id=:branch_id LIMIT 1');
    $stmt->execute([':id'=>$accountId, ':branch_id'=>$branchId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['status']!==1) json_error('Selected Account is inactive or unavailable.',422);
    if ((int)$row['account_type']!==$requiredType) {
        json_error($requiredType===1?'Cash payment requires a Cash Account.':'UPI, Bank and Cheque require a Bank Account.',422);
    }
}

function sp_parse_payment_details(PDO $pdo, int $branchId, array $data): array
{
    $raw=$data['payments']??[];
    if (!is_array($raw)) json_error('Payment details must be a list.',422);
    $rows=[];$seen=[];$total=0.0;
    foreach ($raw as $item) {
        if (!is_array($item)) continue;
        $mode=(int)($item['payment_mode']??0);
        if (!in_array($mode,[1,2,3,4],true)) continue;
        $amount=sp_money($item['amount']??0,'Payment Amount');
        if ($amount<=0.001) continue;
        if (isset($seen[$mode])) json_error('Only one row is allowed for each Payment Mode.',422);
        $seen[$mode]=true;
        $accountId=(int)($item['account_id']??0);
        if ($accountId<1) json_error('Select an Account for every entered Payment Amount.',422);
        sp_account($pdo,$branchId,$accountId,$mode===1?1:2);
        $reference=sp_nullable($item['reference_no']??null,150);
        $chequeNo=null;$chequeDate=null;
        if ($mode===4) {
            $chequeNo=sp_nullable($item['cheque_no']??null,100);
            if ($chequeNo===null) json_error('Cheque Number is required when Cheque Amount is entered.',422);
            $chequeDate=sp_valid_date($item['cheque_date']??'','cheque_date',true);
        }
        $rows[]=[
            'payment_mode'=>$mode,'account_id'=>$accountId,'amount'=>$amount,
            'reference_no'=>$reference,'cheque_no'=>$chequeNo,'cheque_date'=>$chequeDate,
        ];
        $total=round($total+$amount,2);
    }
    if ($total<=0.001) json_error('Enter at least one Payment Amount.',422,['payments'=>'Payment Amount is required.']);
    return ['rows'=>$rows,'total'=>$total];
}

function sp_target_rows(
    PDO $pdo,
    int $branchId,
    int $supplierId,
    int $paymentType,
    int $excludePaymentId=0,
    ?int $purchaseId=null
): array {
    $ctx=sf_supplier_context($pdo,$branchId,$supplierId,$excludePaymentId);
    if (!$ctx) json_error('Supplier was not found.',404);
    $targets=[];

    if ($paymentType===1) {
        if ((float)$ctx['opening_outstanding']<=0.001) json_error('This Supplier has no pending Opening Balance.',422);
        return [[
            'allocation_type'=>2,'purchase_id'=>null,'purchase_ref'=>null,'label'=>'Opening Balance',
            'date'=>(string)$ctx['supplier']['opening_balance_date'],'pending'=>(float)$ctx['opening_outstanding'],
        ]];
    }

    if ($paymentType===3) {
        if (!$purchaseId) json_error('Particular Purchase is required.',422,['purchase_ref'=>'Select a Purchase.']);
        foreach ($ctx['purchases'] as $p) {
            if ((int)$p['id']===$purchaseId) {
                return [[
                    'allocation_type'=>1,'purchase_id'=>(int)$p['id'],'purchase_ref'=>$p['ref'],
                    'label'=>(string)$p['purchase_no'],'date'=>(string)$p['purchase_date'],'pending'=>(float)$p['pending'],
                ]];
            }
        }
        json_error('Selected Purchase has no pending amount.',422,['purchase_ref'=>'Select a pending Purchase.']);
    }

    /* Overall FIFO: Opening Balance first, then oldest Purchase. */
    if ((float)$ctx['opening_outstanding']>0.001) {
        $targets[]=[
            'allocation_type'=>2,'purchase_id'=>null,'purchase_ref'=>null,'label'=>'Opening Balance',
            'date'=>(string)$ctx['supplier']['opening_balance_date'],'pending'=>(float)$ctx['opening_outstanding'],
        ];
    }
    foreach ($ctx['purchases'] as $p) {
        $targets[]=[
            'allocation_type'=>1,'purchase_id'=>(int)$p['id'],'purchase_ref'=>$p['ref'],
            'label'=>(string)$p['purchase_no'],'date'=>(string)$p['purchase_date'],'pending'=>(float)$p['pending'],
        ];
    }
    if (!$targets) json_error('This Supplier has no pending Opening Balance or Purchase.',422);
    return $targets;
}

function sp_build_settlement(
    PDO $pdo,
    int $branchId,
    int $supplierId,
    int $paymentType,
    array $data,
    float $actualPayment,
    int $excludePaymentId=0
): array {
    $purchaseId=null;
    if ($paymentType===3) {
        $purchaseId=sp_ref_to_id($data['purchase_ref']??'','purchase','Purchase');
    }
    $targets=sp_target_rows($pdo,$branchId,$supplierId,$paymentType,$excludePaymentId,$purchaseId);
    $targetTotal=0.0;
    foreach ($targets as $t) $targetTotal+=(float)$t['pending'];
    $targetTotal=round($targetTotal,2);
    if ($actualPayment>$targetTotal+0.001) {
        json_error('Payment Amount cannot exceed the selected outstanding.',422,['payments'=>'Maximum payable amount is '.number_format($targetTotal,2,'.','').'.']);
    }

    $remaining=$actualPayment;$allocations=[];
    foreach ($targets as $target) {
        if ($remaining<=0.001) break;
        $applied=round(min((float)$target['pending'],$remaining),2);
        if ($applied<=0.001) continue;
        $remaining=round($remaining-$applied,2);
        $allocations[]=$target+[
            'allocated_amount'=>$applied,
            'pending_after'=>max(0.0,round((float)$target['pending']-$applied,2)),
        ];
    }
    return [
        'targets_total'=>$targetTotal,
        'actual_payment'=>$actualPayment,
        'remaining_outstanding'=>max(0.0,round($targetTotal-$actualPayment,2)),
        'allocations'=>$allocations,
    ];
}

function sp_generate_no(PDO $pdo, int $branchId): string
{
    $stmt=$pdo->prepare(
        "SELECT payment_no FROM food_supplier_payments
         WHERE branch_id=:branch_id AND payment_no REGEXP '^SPY[0-9]+$'
         ORDER BY CAST(SUBSTRING(payment_no,4) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $last=(string)($stmt->fetchColumn()?:'');$next=1;
    if ($last!=='' && preg_match('/^SPY([0-9]+)$/i',$last,$m)) $next=((int)$m[1])+1;
    return 'SPY'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function sp_payment_effect_purchase_ids(PDO $pdo, int $branchId, int $paymentId): array
{
    $stmt=$pdo->prepare(
        'SELECT DISTINCT purchase_id
         FROM food_supplier_payment_allocations
         WHERE supplier_payment_id=:payment_id AND branch_id=:branch_id AND purchase_id IS NOT NULL'
    );
    $stmt->execute([':payment_id'=>$paymentId,':branch_id'=>$branchId]);
    $ids=[];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id]=true;
    return $ids;
}

function sp_undo_payment_effects(PDO $pdo, int $branchId, int $paymentId, bool $removeChildren): array
{
    $ids=sp_payment_effect_purchase_ids($pdo,$branchId,$paymentId);
    if ($removeChildren) {
        $pdo->prepare('DELETE FROM food_supplier_payment_allocations WHERE supplier_payment_id=:id AND branch_id=:branch_id')
            ->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
        $pdo->prepare('DELETE FROM food_supplier_payment_details WHERE supplier_payment_id=:id AND branch_id=:branch_id')
            ->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
    } else {
        $pdo->prepare('UPDATE food_supplier_payment_allocations SET status=0,updated_at=NOW() WHERE supplier_payment_id=:id AND branch_id=:branch_id AND status=1')
            ->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
        $pdo->prepare('UPDATE food_supplier_payment_details SET status=0,updated_at=NOW() WHERE supplier_payment_id=:id AND branch_id=:branch_id AND status=1')
            ->execute([':id'=>$paymentId,':branch_id'=>$branchId]);
    }
    return $ids;
}

function sp_insert_details(PDO $pdo,int $branchId,int $paymentId,int $userId,array $rows): void
{
    $stmt=$pdo->prepare(
        'INSERT INTO food_supplier_payment_details
         (supplier_payment_id,branch_id,payment_mode,account_id,amount,reference_no,cheque_no,cheque_date,status,created_by,created_at,updated_at)
         VALUES(:supplier_payment_id,:branch_id,:payment_mode,:account_id,:amount,:reference_no,:cheque_no,:cheque_date,1,:created_by,NOW(),NOW())'
    );
    foreach ($rows as $r) {
        $stmt->execute([
            ':supplier_payment_id'=>$paymentId,':branch_id'=>$branchId,':payment_mode'=>$r['payment_mode'],
            ':account_id'=>$r['account_id'],':amount'=>$r['amount'],':reference_no'=>$r['reference_no'],
            ':cheque_no'=>$r['cheque_no'],':cheque_date'=>$r['cheque_date'],':created_by'=>$userId,
        ]);
    }
}

function sp_insert_allocations(PDO $pdo,int $branchId,int $paymentId,int $userId,array $rows): array
{
    $stmt=$pdo->prepare(
        'INSERT INTO food_supplier_payment_allocations
         (supplier_payment_id,purchase_id,branch_id,allocation_type,allocated_amount,status,created_by,created_at,updated_at)
         VALUES(:supplier_payment_id,:purchase_id,:branch_id,:allocation_type,:allocated_amount,1,:created_by,NOW(),NOW())'
    );
    $purchaseIds=[];
    foreach ($rows as $r) {
        $stmt->execute([
            ':supplier_payment_id'=>$paymentId,':purchase_id'=>$r['purchase_id'],':branch_id'=>$branchId,
            ':allocation_type'=>$r['allocation_type'],':allocated_amount'=>$r['allocated_amount'],':created_by'=>$userId,
        ]);
        if (!empty($r['purchase_id'])) $purchaseIds[(int)$r['purchase_id']]=true;
    }
    return $purchaseIds;
}

function sp_payment_record(PDO $pdo,int $branchId,int $id): array
{
    $stmt=$pdo->prepare(
        'SELECT p.*,s.supplier_code,s.supplier_name,s.mobile
         FROM food_supplier_payments p
         INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.id=:id AND p.branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':branch_id'=>$branchId]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) json_error('Supplier Payment was not found.',404);
    $r['id']=(int)$r['id'];$r['supplier_id']=(int)$r['supplier_id'];$r['payment_type']=(int)$r['payment_type'];$r['amount']=(float)$r['amount'];
    $r['ref']=encryptReference('supplier_payment',(int)$r['id']);
    $r['supplier_ref']=encryptReference('supplier',(int)$r['supplier_id']);
    $r['payment_type_ref']=sp_type_ref((int)$r['payment_type']);

    $d=$pdo->prepare(
        'SELECT d.*,a.account_code,a.account_name,a.account_type
         FROM food_supplier_payment_details d
         INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id
         WHERE d.supplier_payment_id=:id AND d.branch_id=:branch_id AND d.status=1
         ORDER BY d.payment_mode,d.id'
    );
    $d->execute([':id'=>$id,':branch_id'=>$branchId]);
    $payments=[];
    foreach($d->fetchAll(PDO::FETCH_ASSOC) as $row){
        $payments[]=[
            'id'=>(int)$row['id'],'payment_mode'=>(int)$row['payment_mode'],'account_id'=>(int)$row['account_id'],
            'account_name'=>(string)$row['account_name'],'amount'=>(float)$row['amount'],
            'reference_no'=>(string)($row['reference_no']??''),'cheque_no'=>(string)($row['cheque_no']??''),'cheque_date'=>(string)($row['cheque_date']??''),
        ];
    }
    $r['payments']=$payments;

    $a=$pdo->prepare(
        'SELECT allocation_type,purchase_id,allocated_amount
         FROM food_supplier_payment_allocations
         WHERE supplier_payment_id=:id AND branch_id=:branch_id AND status=1 ORDER BY id'
    );
    $a->execute([':id'=>$id,':branch_id'=>$branchId]);
    $alloc=[];$purchaseRef='';$purchaseId=null;
    foreach($a->fetchAll(PDO::FETCH_ASSOC) as $row){
        $row['allocation_type']=(int)$row['allocation_type'];
        $row['purchase_id']=$row['purchase_id']===null?null:(int)$row['purchase_id'];
        $row['allocated_amount']=(float)$row['allocated_amount'];
        if ((int)$r['payment_type']===3 && $row['purchase_id']) {
            $purchaseId=(int)$row['purchase_id'];
            $purchaseRef=encryptReference('purchase',$purchaseId);
        }
        $alloc[]=$row;
    }
    $r['allocations']=$alloc;$r['purchase_id']=$purchaseId;$r['purchase_ref']=$purchaseRef;
    return $r;
}

function sp_mode_label(int $mode): string
{
    return [1=>'Cash',2=>'UPI',3=>'Bank Transfer',4=>'Cheque'][$mode]??'Payment';
}

$method=request_method();
sp_require_schema();

if ($method==='GET' && isset($_GET['options'])) {
    $access=require_permission('supplier-payment.php',ACTION_VIEW);
    $ctx=sp_tenant_context($access['user']);
    $payload=[
        'allowed_actions'=>$access['actions'],
        'suppliers'=>sp_suppliers($ctx['branch_id']),
        'accounts'=>sp_accounts($ctx['branch_id']),
        'payment_types'=>[
            ['type'=>1,'value'=>sp_type_ref(1),'label'=>'Opening Balance'],
            ['type'=>2,'value'=>sp_type_ref(2),'label'=>'Overall Outstanding'],
            ['type'=>3,'value'=>sp_type_ref(3),'label'=>'Particular Purchase'],
        ],
        'today'=>date('Y-m-d'),
    ];
    if (!empty($_GET['supplier_ref'])) {
        $supplierId=sp_ref_to_id($_GET['supplier_ref'],'supplier','Supplier');
        $payload['supplier_context']=sf_supplier_context(db(),$ctx['branch_id'],$supplierId);
    }
    json_success('Supplier Payment options loaded.',$payload);
}

if ($method==='GET' && isset($_GET['supplier_context'])) {
    $access=require_permission('supplier-payment.php',ACTION_VIEW);
    $ctx=sp_tenant_context($access['user']);
    $supplierId=sp_ref_to_id($_GET['supplier_ref']??'','supplier','Supplier');
    $exclude=0;
    if (!empty($_GET['payment_ref'])) $exclude=sp_ref_to_id($_GET['payment_ref'],'supplier_payment','Supplier Payment');
    json_success('Supplier outstanding loaded.',sf_supplier_context(db(),$ctx['branch_id'],$supplierId,$exclude));
}

if ($method==='GET' && isset($_GET['ref'])) {
    $access=require_permission('supplier-payment.php',ACTION_VIEW);
    $ctx=sp_tenant_context($access['user']);
    $id=sp_ref_to_id($_GET['ref'],'supplier_payment','Supplier Payment');
    $record=sp_payment_record(db(),$ctx['branch_id'],$id);
    json_success('Supplier Payment loaded.',[
        'payment'=>$record,'allowed_actions'=>$access['actions'],'accounts'=>sp_accounts($ctx['branch_id']),
        'payment_types'=>[
            ['type'=>1,'value'=>sp_type_ref(1),'label'=>'Opening Balance'],
            ['type'=>2,'value'=>sp_type_ref(2),'label'=>'Overall Outstanding'],
            ['type'=>3,'value'=>sp_type_ref(3),'label'=>'Particular Purchase'],
        ],
        'supplier_context'=>sf_supplier_context(db(),$ctx['branch_id'],(int)$record['supplier_id'],$id),
    ]);
}

if ($method==='GET' && isset($_GET['datatable'])) {
    $access=require_permission('supplier-payment-list.php',ACTION_VIEW);
    $ctx=sp_tenant_context($access['user']);$branchId=$ctx['branch_id'];
    $formActions=effective_actions_for_menu($access['user'],menu_by_path('supplier-payment.php'));
    $draw=max(0,(int)($_GET['draw']??0));$start=max(0,(int)($_GET['start']??0));$length=max(1,min(100000,(int)($_GET['length']??10)));
    $search=trim((string)($_GET['search']['value']??''));$dateFrom=trim((string)($_GET['date_from']??''));$dateTo=trim((string)($_GET['date_to']??''));
    $supplierId=0;if(!empty($_GET['supplier_ref']))$supplierId=sp_ref_to_id($_GET['supplier_ref'],'supplier','Supplier');
    $where=['p.branch_id=:branch_id','p.status=1'];$params=[':branch_id'=>$branchId];
    if($search!==''){$like='%'.$search.'%';$where[]='(p.payment_no LIKE :s_no OR s.supplier_code LIKE :s_code OR s.supplier_name LIKE :s_name OR p.notes LIKE :s_notes)';$params+=[':s_no'=>$like,':s_code'=>$like,':s_name'=>$like,':s_notes'=>$like];}
    if($supplierId>0){$where[]='p.supplier_id=:supplier_id';$params[':supplier_id']=$supplierId;}
    if($dateFrom!==''){$where[]='p.payment_date>=:date_from';$params[':date_from']=$dateFrom;}
    if($dateTo!==''){$where[]='p.payment_date<=:date_to';$params[':date_to']=$dateTo;}
    $from=' FROM food_supplier_payments p INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id';
    $total=db()->prepare('SELECT COUNT(*)'.$from.' WHERE p.branch_id=:branch_id AND p.status=1');$total->execute([':branch_id'=>$branchId]);$recordsTotal=(int)$total->fetchColumn();
    $count=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));$count->execute($params);$recordsFiltered=(int)$count->fetchColumn();
    $cols=['p.payment_no','p.payment_date','s.supplier_name','p.payment_type','p.amount','p.posting_status'];$oc=(int)($_GET['order'][0]['column']??1);$dir=strtolower((string)($_GET['order'][0]['dir']??'desc'))==='asc'?'ASC':'DESC';$ob=$cols[$oc]??'p.payment_date';
    $sql='SELECT p.id,p.payment_no,p.payment_date,p.payment_type,p.amount,p.posting_status,p.reversed_at,s.supplier_code,s.supplier_name'.$from.' WHERE '.implode(' AND ',$where).' ORDER BY '.$ob.' '.$dir.',p.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);foreach($params as $k=>$v)$stmt->bindValue($k,$v,in_array($k,[':branch_id',':supplier_id'],true)?PDO::PARAM_INT:PDO::PARAM_STR);$stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);$stmt->execute();
    $rows=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $r['payment_type']=(int)$r['payment_type'];$r['amount']=(float)$r['amount'];$r['posting_status']=(int)$r['posting_status'];$r['payment_type_label']=sp_type_label((int)$r['payment_type']);
        $r['ref']=encryptReference('supplier_payment',(int)$r['id']);$r['edit_url']='supplier-payment.php?ref='.rawurlencode($r['ref']);$r['view_url']=$r['edit_url'].'&view=1';unset($r['id']);$rows[]=$r;
    }
    json_success('Supplier Payments loaded.',['datatable'=>['draw'=>$draw,'recordsTotal'=>$recordsTotal,'recordsFiltered'=>$recordsFiltered,'data'=>$rows],'list_actions'=>$access['actions'],'form_actions'=>$formActions,'suppliers'=>sp_suppliers($branchId)]);
}

if ($method==='POST') {
    $data=request_data();$action=strtolower(trim((string)($data['action']??'save')));

    if ($action==='preview') {
        $access=require_permission('supplier-payment.php',ACTION_VIEW);$ctx=sp_tenant_context($access['user']);$pdo=db();
        $supplierId=sp_ref_to_id($data['supplier_ref']??'','supplier','Supplier');sp_supplier($pdo,$ctx['branch_id'],$supplierId,true);
        $paymentType=sp_type_from_ref($data['payment_type_ref']??'');
        $exclude=0;if(!empty($data['ref']))$exclude=sp_ref_to_id($data['ref'],'supplier_payment','Supplier Payment');
        $details=sp_parse_payment_details($pdo,$ctx['branch_id'],$data);
        $settlement=sp_build_settlement($pdo,$ctx['branch_id'],$supplierId,$paymentType,$data,$details['total'],$exclude);
        json_success('Supplier Payment preview calculated.',$settlement);
    }

    if ($action==='delete') {
        $access=require_permission('supplier-payment.php',4);$ctx=sp_tenant_context($access['user']);$branchId=$ctx['branch_id'];$userId=(int)$access['user']['id'];
        $id=sp_ref_to_id($data['ref']??'','supplier_payment','Supplier Payment');$pdo=db();$pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare('SELECT * FROM food_supplier_payments WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1 FOR UPDATE');$stmt->execute([':id'=>$id,':branch_id'=>$branchId]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$old)json_error('Supplier Payment was not found.',404);
            $ids=sp_undo_payment_effects($pdo,$branchId,$id,false);
            $pdo->prepare('UPDATE food_supplier_payments SET posting_status=0,status=0,reversed_at=NOW(),reversed_by=:user_id,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id')->execute([':user_id'=>$userId,':id'=>$id,':branch_id'=>$branchId]);
            sf_refresh_purchases($pdo,$branchId,array_keys($ids));
            $pdo->commit();
            audit_log($userId,4,['company_id'=>$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);
            json_success('Supplier Payment deleted and affected Purchase balances recalculated successfully.');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    if ($action!=='save') json_error('Unsupported Supplier Payment action.',404);

    $isEdit=!empty($data['ref']);
    $access=require_permission('supplier-payment.php',$isEdit?ACTION_UPDATE:ACTION_CREATE);
    $ctx=sp_tenant_context($access['user']);$branchId=$ctx['branch_id'];$userId=(int)$access['user']['id'];$pdo=db();$pdo->beginTransaction();
    try{
        $paymentId=0;$paymentNo='';$old=null;$oldPurchaseIds=[];
        if($isEdit){
            $paymentId=sp_ref_to_id($data['ref'],'supplier_payment','Supplier Payment');
            $stmt=$pdo->prepare('SELECT * FROM food_supplier_payments WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1 FOR UPDATE');$stmt->execute([':id'=>$paymentId,':branch_id'=>$branchId]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$old)json_error('Supplier Payment was not found.',404);
            $paymentNo=(string)$old['payment_no'];$oldPurchaseIds=sp_payment_effect_purchase_ids($pdo,$branchId,$paymentId);
        }
        $supplierId=sp_ref_to_id($data['supplier_ref']??'','supplier','Supplier');sp_supplier($pdo,$branchId,$supplierId,true);
        $paymentType=sp_type_from_ref($data['payment_type_ref']??'');$paymentDate=sp_valid_date($data['payment_date']??'','payment_date',true);$notes=sp_nullable($data['notes']??null,255);
        $details=sp_parse_payment_details($pdo,$branchId,$data);
        $settlement=sp_build_settlement($pdo,$branchId,$supplierId,$paymentType,$data,$details['total'],$isEdit?$paymentId:0);

        if($isEdit){sp_undo_payment_effects($pdo,$branchId,$paymentId,true);}else{$paymentNo=sp_generate_no($pdo,$branchId);}
        $legacyMode=count($details['rows'])===1?sp_mode_label((int)$details['rows'][0]['payment_mode']):'Split';
        if($isEdit){
            $stmt=$pdo->prepare('UPDATE food_supplier_payments SET supplier_id=:supplier_id,payment_no=:payment_no,payment_type=:payment_type,payment_date=:payment_date,amount=:amount,payment_mode=:payment_mode,payment_reference=NULL,notes=:notes,posting_status=1,reversed_at=NULL,reversed_by=NULL,status=1,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id');
            $stmt->execute([':supplier_id'=>$supplierId,':payment_no'=>$paymentNo,':payment_type'=>$paymentType,':payment_date'=>$paymentDate,':amount'=>$details['total'],':payment_mode'=>$legacyMode,':notes'=>$notes,':id'=>$paymentId,':branch_id'=>$branchId]);
        }else{
            $stmt=$pdo->prepare('INSERT INTO food_supplier_payments(branch_id,supplier_id,payment_no,payment_type,payment_date,amount,payment_mode,payment_reference,notes,posting_status,reversed_at,reversed_by,status,created_by,created_at,updated_at) VALUES(:branch_id,:supplier_id,:payment_no,:payment_type,:payment_date,:amount,:payment_mode,NULL,:notes,1,NULL,NULL,1,:created_by,NOW(),NOW())');
            $stmt->execute([':branch_id'=>$branchId,':supplier_id'=>$supplierId,':payment_no'=>$paymentNo,':payment_type'=>$paymentType,':payment_date'=>$paymentDate,':amount'=>$details['total'],':payment_mode'=>$legacyMode,':notes'=>$notes,':created_by'=>$userId]);$paymentId=(int)$pdo->lastInsertId();
        }
        sp_insert_details($pdo,$branchId,$paymentId,$userId,$details['rows']);
        $newPurchaseIds=sp_insert_allocations($pdo,$branchId,$paymentId,$userId,$settlement['allocations']);
        $allIds=$oldPurchaseIds+$newPurchaseIds;sf_refresh_purchases($pdo,$branchId,array_keys($allIds));
        $pdo->commit();
        audit_log($userId,$isEdit?ACTION_UPDATE:ACTION_CREATE,['company_id'=>$ctx['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$paymentId,'old_data'=>$old]);
        json_success($isEdit?'Supplier Payment updated successfully.':'Supplier Payment posted successfully.',['payment'=>sp_payment_record(db(),$branchId,$paymentId)],$isEdit?200:201);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

json_error('Method not allowed.',405);
