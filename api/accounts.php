<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function account_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Account management is available only for tenant users.', 403);
    }

    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);

    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function account_generate_code(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT account_code FROM accounts
         WHERE branch_id=:branch_id AND account_code REGEXP '^ACC[0-9]+$'
         ORDER BY CAST(SUBSTRING(account_code,4) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([':branch_id' => $branchId]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $n = 1;
    if ($last !== '' && preg_match('/^ACC([0-9]+)$/i', $last, $m)) $n = ((int)$m[1]) + 1;
    return 'ACC' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function account_ref_id($value): int
{
    if (!is_string($value) || trim($value) === '') json_error('Account reference is required.', 422);
    try { return decryptReference(trim($value), 'account'); }
    catch (Throwable $e) { json_error('Invalid account reference.', 422); }
    return 0;
}

function account_type_label(int $v): string
{
    return [1 => 'Cash', 2 => 'Bank'][$v] ?? 'Unknown';
}

function account_bank_type_label(?int $v): string
{
    return [1 => 'Current Account', 2 => 'Savings Account', 3 => 'Other'][$v ?? 0] ?? '-';
}

function account_validate(array $in): array
{
    $errors = [];
    $name = trim((string)($in['account_name'] ?? ''));
    $type = (int)($in['account_type'] ?? 0);
    $bankType = ($in['bank_account_type'] ?? '') === '' ? null : (int)$in['bank_account_type'];
    $opening = trim((string)($in['opening_balance'] ?? '0'));
    $date = trim((string)($in['opening_balance_date'] ?? ''));
    $status = (int)($in['status'] ?? 1);
    $defaultCash = (int)($in['is_default_cash'] ?? 0);

    if ($name === '') $errors['account_name'] = 'Account Name is required.';
    if (!in_array($type, [1,2], true)) $errors['account_type'] = 'Select a valid Account Type.';
    if (!is_numeric($opening) || (float)$opening < 0) $errors['opening_balance'] = 'Opening Balance must be zero or greater.';
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors['opening_balance_date'] = 'Enter a valid Opening Balance Date.';
    if (!in_array($status, [0,1], true)) $errors['status'] = 'Select a valid Status.';

    $bankName = trim((string)($in['bank_name'] ?? ''));
    $accountNumber = trim((string)($in['account_number'] ?? ''));
    $ifsc = strtoupper(preg_replace('/\s+/', '', trim((string)($in['ifsc_code'] ?? ''))));
    $upi = trim((string)($in['upi_id'] ?? ''));

    if ($type === 2) {
        if (!in_array((int)$bankType, [1,2,3], true)) $errors['bank_account_type'] = 'Bank Account Type is required.';
        if ($bankName === '') $errors['bank_name'] = 'Bank Name is required.';
        if ($accountNumber === '') $errors['account_number'] = 'Account Number is required.';
        if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) $errors['ifsc_code'] = 'Enter a valid IFSC code.';
        $defaultCash = 0;
    } else {
        $bankType = null;
        $bankName = '';
        $accountNumber = '';
        $ifsc = '';
        $upi = '';
        $defaultCash = $defaultCash === 1 ? 1 : 0;
    }

    if ($errors) json_error('Account validation failed.', 422, $errors);

    return [
        'account_name' => $name,
        'account_type' => $type,
        'bank_account_type' => $bankType,
        'bank_name' => $bankName !== '' ? $bankName : null,
        'account_number' => $accountNumber !== '' ? $accountNumber : null,
        'ifsc_code' => $ifsc !== '' ? $ifsc : null,
        'upi_id' => $upi !== '' ? $upi : null,
        'opening_balance' => round((float)$opening, 2),
        'opening_balance_date' => $date !== '' ? $date : null,
        'is_default_cash' => $defaultCash,
        'status' => $status,
    ];
}

function account_fetch(int $branchId, int $id): array
{
    $stmt = db()->prepare(
        'SELECT id,branch_id,account_code,account_name,account_type,bank_account_type,bank_name,
                account_number,ifsc_code,upi_id,opening_balance,opening_balance_date,is_default_cash,status,
                created_by,created_at,updated_at
         FROM accounts WHERE id=:id AND branch_id=:branch_id LIMIT 1'
    );
    $stmt->execute([':id'=>$id, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Account was not found in your branch.', 404);
    $row['ref'] = encryptReference('account', (int)$row['id']);
    unset($row['id']);
    return $row;
}

function account_assert_unique(int $branchId, string $name, int $exclude=0): void
{
    $sql = 'SELECT id FROM accounts WHERE branch_id=:branch_id AND LOWER(account_name)=LOWER(:name)';
    $p = [':branch_id'=>$branchId, ':name'=>$name];
    if ($exclude > 0) { $sql .= ' AND id<>:id'; $p[':id']=$exclude; }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql); $stmt->execute($p);
    if ($stmt->fetch()) json_error('Account Name already exists in your branch.', 409, ['account_name'=>'This Account Name is already in use.']);
}

$method = request_method();

if ($method === 'GET' && isset($_GET['options'])) {
    $access = require_permission('account-form.php', ACTION_VIEW);
    $ctx = account_tenant_context($access['user']);
    json_success('Account form options loaded.', [
        'allowed_actions'=>$access['actions'],
        'next_account_code'=>account_generate_code($ctx['branch_id']),
        'branch'=>$ctx,
    ]);
}

if ($method === 'GET' && isset($_GET['active_options'])) {
    $access = require_permission('purchase-form.php', ACTION_VIEW);
    $ctx = account_tenant_context($access['user']);
    $stmt = db()->prepare(
        'SELECT id,account_code,account_name,account_type,bank_account_type,bank_name,account_number,upi_id
         FROM accounts WHERE branch_id=:branch_id AND status=1 ORDER BY account_type,account_name'
    );
    $stmt->execute([':branch_id'=>$ctx['branch_id']]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']=(int)$r['id']; $r['account_type']=(int)$r['account_type'];
        $r['bank_account_type']=$r['bank_account_type']===null?null:(int)$r['bank_account_type'];
    }
    unset($r);
    json_success('Active accounts loaded.', ['accounts'=>$rows]);
}

if ($method === 'GET' && isset($_GET['ref'])) {
    $access = require_permission('account-form.php', ACTION_VIEW);
    $ctx = account_tenant_context($access['user']);
    json_success('Account loaded.', [
        'account'=>account_fetch($ctx['branch_id'], account_ref_id($_GET['ref'])),
        'allowed_actions'=>$access['actions'],
    ]);
}

if ($method === 'GET' && isset($_GET['datatable'])) {
    $access = require_permission('account-list.php', ACTION_VIEW);
    $ctx = account_tenant_context($access['user']);
    $branchId = $ctx['branch_id'];

    $draw=max(0,(int)($_GET['draw']??0));
    $start=max(0,(int)($_GET['start']??0));
    $lengthRaw=(int)($_GET['length']??10); $length=$lengthRaw<0?100000:max(1,min(100000,$lengthRaw));
    $search=''; if(isset($_GET['search']['value'])) $search=trim((string)$_GET['search']['value']);
    $type=isset($_GET['account_type']) && $_GET['account_type']!=='' ? (int)$_GET['account_type'] : null;
    $status=isset($_GET['status']) && $_GET['status']!=='' ? (int)$_GET['status'] : null;

    $where=['a.branch_id=:branch_id']; $params=[':branch_id'=>$branchId];
    if($search!=='') {
        $where[]='(a.account_code LIKE :s1 OR a.account_name LIKE :s2 OR a.bank_name LIKE :s3 OR a.account_number LIKE :s4 OR a.ifsc_code LIKE :s5 OR a.upi_id LIKE :s6)';
        $sv='%'.$search.'%'; foreach([':s1',':s2',':s3',':s4',':s5',':s6'] as $k)$params[$k]=$sv;
    }
    if($type!==null && in_array($type,[1,2],true)){ $where[]='a.account_type=:atype'; $params[':atype']=$type; }
    if($status!==null && in_array($status,[0,1],true)){ $where[]='a.status=:status'; $params[':status']=$status; }

    $stmt=db()->prepare('SELECT COUNT(*) FROM accounts WHERE branch_id=:branch_id');
    $stmt->execute([':branch_id'=>$branchId]); $total=(int)$stmt->fetchColumn();
    $stmt=db()->prepare('SELECT COUNT(*) FROM accounts a WHERE '.implode(' AND ',$where));
    $stmt->execute($params); $filtered=(int)$stmt->fetchColumn();

    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS total_accounts,
            COALESCE(SUM(CASE WHEN a.account_type=1 THEN 1 ELSE 0 END),0) AS cash_accounts,
            COALESCE(SUM(CASE WHEN a.account_type=2 THEN 1 ELSE 0 END),0) AS bank_accounts,
            COALESCE(SUM(a.opening_balance),0) AS opening_balance
         FROM accounts a
         WHERE '.implode(' AND ',$where)
    );
    $summaryStmt->execute($params);
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderMap=[0=>'a.account_code',1=>'a.account_name',2=>'a.account_type',3=>'a.bank_name',4=>'a.account_number',5=>'a.opening_balance',6=>'a.status'];
    $oc=(int)($_GET['order'][0]['column']??0); $od=strtolower((string)($_GET['order'][0]['dir']??'asc'))==='desc'?'DESC':'ASC';
    $order=$orderMap[$oc]??'a.account_code';

    $sql='SELECT a.id,a.account_code,a.account_name,a.account_type,a.bank_account_type,a.bank_name,a.account_number,
                 a.opening_balance,a.opening_balance_date,a.is_default_cash,a.status
          FROM accounts a WHERE '.implode(' AND ',$where)." ORDER BY {$order} {$od},a.id DESC LIMIT :start,:length";
    $stmt=db()->prepare($sql);
    foreach($params as $k=>$v)$stmt->bindValue($k,$v);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT); $stmt->bindValue(':length',$length,PDO::PARAM_INT); $stmt->execute();
    $rows=$stmt->fetchAll();
    foreach($rows as &$r){
        $r['account_type']=(int)$r['account_type']; $r['bank_account_type']=$r['bank_account_type']===null?null:(int)$r['bank_account_type'];
        $r['status']=(int)$r['status']; $r['is_default_cash']=(int)$r['is_default_cash'];
        $r['type_label']=account_type_label($r['account_type']); $r['bank_type_label']=account_bank_type_label($r['bank_account_type']);
        $ref=encryptReference('account',(int)$r['id']); $r['ref']=$ref; $r['edit_url']='account-form.php?ref='.rawurlencode($ref); unset($r['id']);
    }
    unset($r);

    json_success('Accounts loaded.', [
        'datatable'=>['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows],
        'summary'=>[
            'total_accounts'=>(int)($summary['total_accounts']??0),
            'cash_accounts'=>(int)($summary['cash_accounts']??0),
            'bank_accounts'=>(int)($summary['bank_accounts']??0),
            'opening_balance'=>(float)($summary['opening_balance']??0),
        ],
        'list_actions'=>$access['actions'],
        'form_actions'=>require_permission('account-form.php', ACTION_VIEW)['actions'],
    ]);
}

if ($method === 'PATCH') {
    $access = require_permission('account-list.php', ACTION_VIEW);
    $ctx = account_tenant_context($access['user']);
    $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $id=account_ref_id($body['ref']??''); $action=(string)($body['action']??'');
    $actionId=$action==='activate'?27:($action==='deactivate'?28:0);
    if(!$actionId || !in_array($actionId,array_map('intval',$access['actions']),true)) json_error('You do not have permission to perform this action.',403);
    $newStatus=$action==='activate'?1:0;
    $stmt=db()->prepare('UPDATE accounts SET status=:status WHERE id=:id AND branch_id=:branch_id');
    $stmt->execute([':status'=>$newStatus,':id'=>$id,':branch_id'=>$ctx['branch_id']]);
    audit_log((int)$access['user']['id'],$actionId,['menu_path'=>'account-list.php','record_id'=>$id,'branch_id'=>$ctx['branch_id'],'new_data'=>['status'=>$newStatus]]);
    json_success($newStatus?'Account activated.':'Account deactivated.');
}

if ($method === 'POST') {
    $isUpdate = strtoupper((string)($_POST['_method'] ?? '')) === 'PUT';
    $action = $isUpdate ? ACTION_UPDATE : ACTION_CREATE;
    $access = require_permission('account-form.php', $action);
    $ctx = account_tenant_context($access['user']);
    $data=account_validate($_POST);
    $id=0;
    if($isUpdate){ $id=account_ref_id($_POST['ref']??''); account_fetch($ctx['branch_id'],$id); }
    account_assert_unique($ctx['branch_id'],$data['account_name'],$id);

    $pdo=db(); $pdo->beginTransaction();
    try {
        if($data['account_type']===1 && $data['is_default_cash']===1){
            $stmt=$pdo->prepare('UPDATE accounts SET is_default_cash=0 WHERE branch_id=:branch_id AND account_type=1'.($id>0?' AND id<>:id':''));
            $p=[':branch_id'=>$ctx['branch_id']]; if($id>0)$p[':id']=$id; $stmt->execute($p);
        }

        if($isUpdate){
            $stmt=$pdo->prepare(
                'UPDATE accounts SET account_name=:name,account_type=:type,bank_account_type=:bank_type,bank_name=:bank_name,
                 account_number=:account_number,ifsc_code=:ifsc,upi_id=:upi,opening_balance=:opening,opening_balance_date=:opening_date,
                 is_default_cash=:default_cash,status=:status WHERE id=:id AND branch_id=:branch_id'
            );
            $stmt->execute([
                ':name'=>$data['account_name'],':type'=>$data['account_type'],':bank_type'=>$data['bank_account_type'],':bank_name'=>$data['bank_name'],
                ':account_number'=>$data['account_number'],':ifsc'=>$data['ifsc_code'],':upi'=>$data['upi_id'],':opening'=>$data['opening_balance'],
                ':opening_date'=>$data['opening_balance_date'],':default_cash'=>$data['is_default_cash'],':status'=>$data['status'],':id'=>$id,':branch_id'=>$ctx['branch_id']
            ]);
        } else {
            $code=account_generate_code($ctx['branch_id']);
            $stmt=$pdo->prepare(
                'INSERT INTO accounts(branch_id,account_code,account_name,account_type,bank_account_type,bank_name,account_number,ifsc_code,upi_id,
                 opening_balance,opening_balance_date,is_default_cash,status,created_by)
                 VALUES(:branch_id,:code,:name,:type,:bank_type,:bank_name,:account_number,:ifsc,:upi,:opening,:opening_date,:default_cash,:status,:created_by)'
            );
            $stmt->execute([
                ':branch_id'=>$ctx['branch_id'],':code'=>$code,':name'=>$data['account_name'],':type'=>$data['account_type'],':bank_type'=>$data['bank_account_type'],
                ':bank_name'=>$data['bank_name'],':account_number'=>$data['account_number'],':ifsc'=>$data['ifsc_code'],':upi'=>$data['upi_id'],
                ':opening'=>$data['opening_balance'],':opening_date'=>$data['opening_balance_date'],':default_cash'=>$data['is_default_cash'],':status'=>$data['status'],
                ':created_by'=>(int)$access['user']['id']
            ]);
            $id=(int)$pdo->lastInsertId();
        }

        audit_log((int)$access['user']['id'],$action,['menu_path'=>'account-form.php','record_id'=>$id,'branch_id'=>$ctx['branch_id'],'new_data'=>$data]);
        $pdo->commit();
        json_success($isUpdate?'Account updated successfully.':'Account created successfully.', ['ref'=>encryptReference('account',$id)]);
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); json_error($e->getMessage(),500); }
}

json_error('Unsupported request.', 405);
