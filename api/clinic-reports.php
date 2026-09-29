<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

// One API for all twelve clinic reports. Scope is always resolved to its own menu
// before permission checking; never trust a client-supplied menu path/branch ID.
const CR_MODULE = 'clinic';
function cr_scopes(): array {
    return [
      'overview'=>'report-overview.php', 'patients'=>'report-patients.php',
      'appointments'=>'report-appointments.php', 'consultations'=>'report-consultations.php',
      'services'=>'report-services.php', 'laboratory'=>'report-laboratory.php',
      'collections'=>'report-collections.php', 'payments'=>'report-payments.php',
      'outstanding'=>'report-outstanding.php', 'doctors'=>'report-doctors.php',
      'financial'=>'report-financial.php', 'activity'=>'report-activity.php'
    ];
}
function cr_date(string $field,string $default): string {
    $raw=trim((string)($_GET[$field]??'')); if($raw==='')return $default;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$raw);
    $e=DateTimeImmutable::getLastErrors();
    if(!$d || ($e && ($e['error_count']||$e['warning_count'])) || $d->format('Y-m-d')!==$raw) json_error('Invalid '.$field.'.',422);
    return $raw;
}
function cr_context(array $user): array {
    if((int)($user['role_type']??0)===2)json_error('Clinic reports require a tenant account.',403);
    $branchId=(int)($user['branch_id']??0);
    if($branchId<1)json_error('An active branch is required.',403);
    $q=db()->prepare('SELECT b.id branch_id,b.company_id,b.branch_name,c.company_name FROM branches b JOIN companies c ON c.id=b.company_id AND c.status=1 WHERE b.id=:bid AND b.status=1 LIMIT 1');
    $q->execute([':bid'=>$branchId]);$x=$q->fetch(PDO::FETCH_ASSOC);
    if(!$x || (isset($user['company_id']) && (int)$user['company_id']>0 && (int)$user['company_id']!==(int)$x['company_id']))json_error('Invalid branch or company.',403);
    return $x;
}
function cr_rows(string $sql,array $args=[]):array { $q=db()->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function cr_aggregate(array $rows,array $keys):array {
    $o=['records'=>count($rows)];foreach($keys as $k)$o[$k]=0;
    foreach($rows as $r)foreach($keys as $k)$o[$k]+=(float)($r[$k]??0);
    foreach($keys as $k)$o[$k]=round($o[$k],2);
    return $o;
}
function cr_filtered(string $dateColumn,int $branch,string $from,string $to):array {
    return ['branch_id'=>$branch, 'date_from'=>$from,'date_to'=>$to];
}
function cr_opt_id(string $key):int { return max(0,(int)($_GET[$key]??0)); }
function cr_options(array $ctx,array $access,string $from,string $to):array {
    $branch=(int)$ctx['branch_id'];
    $patients=cr_rows('SELECT id,patient_code,patient_name,status FROM clinic_patients WHERE branch_id=:branch ORDER BY patient_name',[':branch'=>$branch]);
    $practitioners=cr_rows("SELECT DISTINCT TRIM(z.doctor_name) AS id,TRIM(z.doctor_name) AS label FROM (
       SELECT c.consultant_name doctor_name FROM clinic_consultations c WHERE c.branch_id=:b1 AND c.status=1
       UNION ALL SELECT COALESCE(NULLIF(a.consultant_name,''),u.name) FROM clinic_appointments a LEFT JOIN users u ON u.id=a.practitioner_user_id WHERE a.branch_id=:b2 AND a.status=1
     ) z WHERE z.doctor_name IS NOT NULL AND TRIM(z.doctor_name)<>'' ORDER BY label",[':b1'=>$branch,':b2'=>$branch]);
    return ['allowed_actions'=>$access['actions'],'branch'=>$ctx,'date_from'=>$from,'date_to'=>$to,
      'patients'=>$patients,'doctors'=>$practitioners,
      'payment_modes'=>cr_rows('SELECT DISTINCT payment_mode AS id,payment_mode AS label FROM clinic_bill_payments WHERE branch_id=:b AND status=1 ORDER BY payment_mode',[':b'=>$branch]),
      'appointment_states'=>cr_rows('SELECT DISTINCT appointment_status AS id, appointment_status AS label FROM clinic_appointments WHERE branch_id=:b AND status=1 ORDER BY appointment_status',[':b'=>$branch]),
      'consultation_states'=>cr_rows('SELECT DISTINCT consultation_status AS id, consultation_status AS label FROM clinic_consultations WHERE branch_id=:b AND status=1 ORDER BY consultation_status',[':b'=>$branch]),
      'payment_statuses'=>[['id'=>'Unpaid','label'=>'Unpaid'],['id'=>'Partially Paid','label'=>'Partially Paid'],['id'=>'Paid','label'=>'Paid']]];
}
function cr_patients(int $b,string $f,string $t):array {
    $rows=cr_rows("SELECT p.id,p.patient_code,p.patient_name,p.mobile,p.gender,p.date_of_birth,p.blood_group,p.created_at,
      COALESCE(v.visits,0) visits,COALESCE(a.appointments,0) appointments,COALESCE(bl.billed,0) billed,
      p.status,IF(p.status=1,'Active','Inactive') status_label
      FROM clinic_patients p
      LEFT JOIN (SELECT patient_id,COUNT(*) visits FROM clinic_consultations WHERE branch_id=:bv AND status=1 AND COALESCE(NULLIF(visit_date,'0000-00-00'),DATE(created_at)) BETWEEN :vf AND :vt GROUP BY patient_id) v ON v.patient_id=p.id
      LEFT JOIN (SELECT patient_id,COUNT(*) appointments FROM clinic_appointments WHERE branch_id=:ba AND status=1 AND appointment_date BETWEEN :af AND :at GROUP BY patient_id) a ON a.patient_id=p.id
      LEFT JOIN (SELECT patient_id,SUM(grand_total) billed FROM clinic_bills WHERE branch_id=:bb AND status=1 AND bill_date BETWEEN :bf AND :bt GROUP BY patient_id) bl ON bl.patient_id=p.id
      WHERE p.branch_id=:b AND p.created_at < DATE_ADD(:t, INTERVAL 1 DAY) ORDER BY p.created_at DESC,p.id DESC",
      [':bv'=>$b,':vf'=>$f,':vt'=>$t,':ba'=>$b,':af'=>$f,':at'=>$t,':bb'=>$b,':bf'=>$f,':bt'=>$t,':b'=>$b,':t'=>$t]);
    $out=cr_aggregate($rows,['visits','appointments','billed']);$out['patients']=count($rows);$out['active']=count(array_filter($rows,static fn($r)=>(int)$r['status']===1));
    $out['new_patients']=count(array_filter($rows,static fn($r)=>substr((string)$r['created_at'],0,10)>=$f));
    return ['rows'=>$rows,'summary'=>$out,'note'=>'Patient total includes patients registered up to the selected end date; visits, appointments and billed amount use the selected date range.'];
}
function cr_appointments(int $b,string $f,string $t):array {
    $id=cr_opt_id('patient_id');$status=trim((string)($_GET['status']??''));$args=[':b'=>$b,':f'=>$f,':t'=>$t];$where='';
    if($id){$where.=' AND a.patient_id=:patient';$args[':patient']=$id;}
    if($status!==''){$where.=' AND a.appointment_status=:state';$args[':state']=$status;}
    $rows=cr_rows("SELECT a.appointment_no,a.appointment_date,a.appointment_time,p.patient_code,p.patient_name,
      a.visit_type,COALESCE(NULLIF(a.consultant_name,''),u.name,'Unassigned') doctor,a.duration_minutes,
      a.appointment_state,a.appointment_status,a.created_at
      FROM clinic_appointments a JOIN clinic_patients p ON p.id=a.patient_id AND p.branch_id=a.branch_id
      LEFT JOIN users u ON u.id=a.practitioner_user_id
      WHERE a.branch_id=:b AND a.status=1 AND a.appointment_date BETWEEN :f AND :t $where ORDER BY a.appointment_date DESC,a.appointment_time DESC",$args);
    $s=['records'=>count($rows),'completed'=>0,'cancelled'=>0,'no_show'=>0,'scheduled'=>0];
    foreach($rows as $r){$state=strtolower(trim((string)$r['appointment_state']));$status=strtolower(trim((string)$r['appointment_status']));
      if($state==='completed'||$status==='completed')$s['completed']++;
      elseif($state==='cancelled'||$status==='cancelled')$s['cancelled']++;
      elseif($state==='no_show'||in_array($status,['no show','no_show'],true))$s['no_show']++;
      else $s['scheduled']++;
    }
    return ['rows'=>$rows,'summary'=>$s];
}
function cr_consultations(int $b,string $f,string $t):array {
    $id=cr_opt_id('patient_id');$status=trim((string)($_GET['status']??''));$args=[':b'=>$b,':f'=>$f,':t'=>$t];$where='';
    if($id){$where.=' AND c.patient_id=:patient';$args[':patient']=$id;}
    if($status!==''){$where.=' AND c.consultation_status=:state';$args[':state']=$status;}
    $rows=cr_rows("SELECT c.consultation_no,COALESCE(NULLIF(c.visit_date,'0000-00-00'),DATE(c.created_at)) report_date,
      p.patient_code,p.patient_name,COALESCE(NULLIF(c.consultant_name,''),NULLIF(a.consultant_name,''),u.name,'Unassigned') doctor,
      c.consultation_status,c.consultation_fee,c.chief_complaint,COALESCE(NULLIF(c.diagnosis_summary,''),d.diagnosis_name,'') diagnosis,
      c.follow_up_date,c.created_at
      FROM clinic_consultations c LEFT JOIN clinic_patients p ON p.id=c.patient_id AND p.branch_id=c.branch_id
      LEFT JOIN clinic_appointments a ON a.id=c.appointment_id AND a.branch_id=c.branch_id
      LEFT JOIN users u ON u.id=a.practitioner_user_id
      LEFT JOIN clinic_diagnoses d ON d.id=c.diagnosis_id AND d.branch_id=c.branch_id
      WHERE c.branch_id=:b AND c.status=1 AND COALESCE(NULLIF(c.visit_date,'0000-00-00'),DATE(c.created_at)) BETWEEN :f AND :t $where
      ORDER BY report_date DESC,c.id DESC",$args);
    $s=cr_aggregate($rows,['consultation_fee']);$s['completed']=0;$s['followups']=0;
    foreach($rows as $r){if(strtolower((string)$r['consultation_status'])==='completed')$s['completed']++;if(!empty($r['follow_up_date']))$s['followups']++;}
    return ['rows'=>$rows,'summary'=>$s,'note'=>'Consultation fee is the clinical record fee, not an additional receipt. Financial totals come from bills and payments.'];
}
function cr_line_items(int $b,string $f,string $t,bool $lab):array {
    $rule=$lab?"UPPER(i.item_type) LIKE '%LAB%'":"UPPER(i.item_type) NOT IN ('MEDICINE','DRUG','PHARMACY') AND UPPER(i.item_type) NOT LIKE '%LAB%'";
    $rows=cr_rows("SELECT b.bill_date,b.bill_no,p.patient_code,p.patient_name,i.item_type,i.description,
       i.quantity,i.unit_price,i.amount,COALESCE(l.lab_test_code,'') lab_test_code,
       CASE WHEN i.reference_id IS NOT NULL AND l.id IS NOT NULL THEN l.lab_test_name ELSE i.description END test_name
       FROM clinic_bill_items i JOIN clinic_bills b ON b.id=i.bill_id AND b.branch_id=i.branch_id AND b.status=1
       JOIN clinic_patients p ON p.id=b.patient_id AND p.branch_id=b.branch_id
       LEFT JOIN clinic_lab_tests l ON l.id=i.reference_id AND l.branch_id=i.branch_id AND UPPER(i.item_type) LIKE '%LAB%'
       WHERE i.branch_id=:b AND i.status=1 AND b.bill_date BETWEEN :f AND :t AND $rule
       ORDER BY b.bill_date DESC,b.id DESC,i.id",[':b'=>$b,':f'=>$f,':t'=>$t]);
    $s=cr_aggregate($rows,['quantity','amount']);$s['bills']=count(array_unique(array_column($rows,'bill_no')));
    return ['rows'=>$rows,'summary'=>$s,'note'=>$lab?'Only billed lab items are listed. The uploaded schema contains lab-test masters but no lab results/orders table.':'Amounts are billed line values before any bill-level discount. Consultations are included when entered as bill items.'];
}
function cr_collections(int $b,string $f,string $t):array {
    $mode=trim((string)($_GET['payment_mode']??''));$args=[':b'=>$b,':f'=>$f,':t'=>$t];$where='';
    if($mode!==''){$where=' AND pay.payment_mode=:mode';$args[':mode']=$mode;}
    $rows=cr_rows("SELECT COALESCE(pay.payment_date,DATE(pay.created_at)) receipt_date,b.bill_no,p.patient_code,p.patient_name,
      pay.payment_mode,COALESCE(pay.account_name,a.account_name,'') account_name,pay.reference_no,pay.amount,pay.created_at
      FROM clinic_bill_payments pay JOIN clinic_bills b ON b.id=pay.bill_id AND b.branch_id=pay.branch_id AND b.status=1
      JOIN clinic_patients p ON p.id=b.patient_id AND p.branch_id=b.branch_id
      LEFT JOIN accounts a ON a.id=pay.account_id AND a.branch_id=pay.branch_id
      WHERE pay.branch_id=:b AND pay.status=1 AND COALESCE(pay.payment_date,DATE(pay.created_at)) BETWEEN :f AND :t $where
      ORDER BY receipt_date DESC,pay.id DESC",$args);
    $s=cr_aggregate($rows,['amount']);$s['cash']=0;$s['upi']=0;$s['other']=0;
    foreach($rows as $r){$mode=strtoupper(trim((string)$r['payment_mode']));$k=$mode==='CASH'?'cash':($mode==='UPI'?'upi':'other');$s[$k]+=(float)$r['amount'];}
    return ['rows'=>$rows,'summary'=>$s,'note'=>'Collections use actual payment rows and receipt dates, never repeat the bill header paid total.'];
}
function cr_payments(int $b,string $f,string $t):array {
    $rows=cr_rows("SELECT pay.payment_mode,COALESCE(pay.account_name,a.account_name,'Unspecified') account_name,
      COUNT(*) receipts,SUM(pay.amount) amount,MIN(COALESCE(pay.payment_date,DATE(pay.created_at))) first_date,
      MAX(COALESCE(pay.payment_date,DATE(pay.created_at))) last_date
      FROM clinic_bill_payments pay JOIN clinic_bills b ON b.id=pay.bill_id AND b.branch_id=pay.branch_id AND b.status=1
      LEFT JOIN accounts a ON a.id=pay.account_id AND a.branch_id=pay.branch_id
      WHERE pay.branch_id=:b AND pay.status=1 AND COALESCE(pay.payment_date,DATE(pay.created_at)) BETWEEN :f AND :t
      GROUP BY pay.payment_mode,COALESCE(pay.account_name,a.account_name,'Unspecified') ORDER BY amount DESC",
      [':b'=>$b,':f'=>$f,':t'=>$t]);
    $s=cr_aggregate($rows,['receipts','amount']);$s['modes']=count(array_unique(array_column($rows,'payment_mode')));
    return ['rows'=>$rows,'summary'=>$s];
}
function cr_outstanding(int $b,string $f,string $t):array {
    $id=cr_opt_id('patient_id');$args=[':b'=>$b,':f'=>$f,':t'=>$t];$where='';
    if($id){$where=' AND b.patient_id=:patient';$args[':patient']=$id;}
    $rows=cr_rows("SELECT b.bill_date,b.bill_no,p.patient_code,p.patient_name,p.mobile,b.grand_total,b.paid_amount,b.balance_amount,
       b.payment_status,GREATEST(DATEDIFF(CURDATE(),b.bill_date),0) days_since_bill
       FROM clinic_bills b JOIN clinic_patients p ON p.id=b.patient_id AND p.branch_id=b.branch_id
       WHERE b.branch_id=:b AND b.status=1 AND b.bill_date BETWEEN :f AND :t AND b.balance_amount>0.005 $where
       ORDER BY b.bill_date,p.patient_name",$args);
    $s=cr_aggregate($rows,['grand_total','paid_amount','balance_amount']);$s['over_30_days']=0;
    foreach($rows as $r)if((int)$r['days_since_bill']>30)$s['over_30_days']+=(float)$r['balance_amount'];
    return ['rows'=>$rows,'summary'=>$s,'note'=>'Current outstanding for bills issued in the selected date range; not a historical as-of balance. Age is measured from bill date (no due-date field in supplied clinic bills).'];
}
function cr_doctors(int $b,string $f,string $t):array {
    $doctor=trim((string)($_GET['doctor']??''));$sql="SELECT COALESCE(NULLIF(TRIM(c.consultant_name),''),NULLIF(TRIM(a.consultant_name),''),u.name,'Unassigned') doctor,
       COUNT(*) consultations,SUM(CASE WHEN LOWER(c.consultation_status)='completed' THEN 1 ELSE 0 END) completed,
       SUM(c.consultation_fee) recorded_fee,COUNT(DISTINCT c.patient_id) patients,
       MAX(COALESCE(NULLIF(c.visit_date,'0000-00-00'),DATE(c.created_at))) last_visit
       FROM clinic_consultations c LEFT JOIN clinic_appointments a ON a.id=c.appointment_id AND a.branch_id=c.branch_id
       LEFT JOIN users u ON u.id=a.practitioner_user_id
       WHERE c.branch_id=:b AND c.status=1 AND COALESCE(NULLIF(c.visit_date,'0000-00-00'),DATE(c.created_at)) BETWEEN :f AND :t
       GROUP BY doctor";
    $rows=cr_rows($sql.' ORDER BY doctor',[':b'=>$b,':f'=>$f,':t'=>$t]);
    if($doctor!=='')$rows=array_values(array_filter($rows,static fn($r)=>(string)$r['doctor']===$doctor));
    $s=cr_aggregate($rows,['consultations','completed','recorded_fee']);$s['doctors']=count($rows);
    return ['rows'=>$rows,'summary'=>$s,'note'=>'Practitioner is attributed from the consultation name, then linked appointment, then appointment user. Unassigned stays unassigned; no guessed doctor ownership.'];
}
function cr_financial(int $b,string $f,string $t):array {
    // Daily billed and cash receipts are distinct, and expenses are recorded paid amounts
    // against their expense date (no expense-payment event tables exist in supplied dump).
    $sql="SELECT x.report_date, SUM(x.bill_count) bills,ROUND(SUM(x.billed),2) billed,
       ROUND(SUM(x.discounts),2) discounts,ROUND(SUM(x.receipts),2) receipts,
       ROUND(SUM(x.expense_paid),2) expense_paid_recorded,
       ROUND(SUM(x.receipts)-SUM(x.expense_paid),2) receipts_less_expense_paid_recorded
       FROM (
        SELECT bill_date report_date,COUNT(*) bill_count,SUM(grand_total) billed,SUM(discount_amount) discounts,0 receipts,0 expense_paid
        FROM clinic_bills WHERE branch_id=:b1 AND status=1 AND bill_date BETWEEN :f1 AND :t1 GROUP BY bill_date
        UNION ALL
        SELECT COALESCE(p.payment_date,DATE(p.created_at)),0,0,0,SUM(p.amount),0
        FROM clinic_bill_payments p JOIN clinic_bills b ON b.id=p.bill_id AND b.branch_id=p.branch_id AND b.status=1
        WHERE p.branch_id=:b2 AND p.status=1 AND COALESCE(p.payment_date,DATE(p.created_at)) BETWEEN :f2 AND :t2
        GROUP BY COALESCE(p.payment_date,DATE(p.created_at))
        UNION ALL
        SELECT expense_date,0,0,0,0,SUM(paid_amount) FROM expenses
        WHERE branch_id=:b3 AND module_code=:module AND posting_status=1 AND status=1 AND reversed_at IS NULL AND expense_date BETWEEN :f3 AND :t3
        GROUP BY expense_date
       ) x GROUP BY x.report_date ORDER BY x.report_date DESC";
    $a=[':b1'=>$b,':b2'=>$b,':b3'=>$b,':f1'=>$f,':f2'=>$f,':f3'=>$f,':t1'=>$t,':t2'=>$t,':t3'=>$t,':module'=>CR_MODULE];
    $rows=cr_rows($sql,$a);return ['rows'=>$rows,'summary'=>cr_aggregate($rows,['bills','billed','discounts','receipts','expense_paid_recorded','receipts_less_expense_paid_recorded']),
    'note'=>'Billed = active bill grand totals. Receipts = dated payment rows. Paid expenses are expense-header amounts grouped by expense date because the uploaded schema has no expense-payment transaction table; the final difference is not an audited cash-flow/P&L figure.'];
}
function cr_activity(int $b,int $company,string $f,string $t):array {
    $rows=cr_rows("SELECT a.created_at,u.name user_name,m.menu_name,COALESCE(pa.action_name,CONCAT('Action ',a.action_id)) action_name,
       a.record_id,a.ip_address
       FROM audit_logs a JOIN menus m ON m.id=a.menu_id JOIN users u ON u.id=a.user_id
       LEFT JOIN permission_actions pa ON pa.id=a.action_id
       WHERE a.branch_id=:b AND a.company_id=:company AND a.created_at>=:f AND a.created_at<DATE_ADD(:t,INTERVAL 1 DAY)
         AND (m.menu_path='clinic' OR m.parent_id=(SELECT id FROM menus WHERE menu_path='clinic' LIMIT 1)
           OR m.parent_id=(SELECT id FROM menus WHERE menu_path='clinic-reports' LIMIT 1))
       ORDER BY a.created_at DESC,a.id DESC",[':b'=>$b,':company'=>$company,':f'=>$f,':t'=>$t]);
    $s=['records'=>count($rows),'users'=>count(array_unique(array_column($rows,'user_name'))),'creates'=>0,'updates'=>0];
    foreach($rows as $r){$action=strtolower((string)$r['action_name']);if($action==='create')$s['creates']++;if($action==='update')$s['updates']++;}
    return ['rows'=>$rows,'summary'=>$s,'note'=>'Only branch/company scoped clinic menu audit entries are displayed. Clinical notes and old/new data are intentionally excluded.'];
}
function cr_overview(int $b,string $f,string $t):array {
    $p=cr_patients($b,$f,$t)['summary'];
    $a=cr_appointments($b,$f,$t)['summary'];
    $c=cr_consultations($b,$f,$t)['summary'];
    $fi=cr_financial($b,$f,$t);
    $os=cr_outstanding($b,$f,$t)['summary'];
    $rows=[];
    foreach($fi['rows'] as $r)$rows[]=['report_date'=>$r['report_date'],'bills'=>$r['bills'],'billed'=>$r['billed'],'receipts'=>$r['receipts'],'expense_paid_recorded'=>$r['expense_paid_recorded']];
    return ['rows'=>$rows,'summary'=>['patients'=>$p['patients'],'new_patients'=>$p['new_patients'],'appointments'=>$a['records'],'consultations'=>$c['records'],'billed'=>$fi['summary']['billed'],'receipts'=>$fi['summary']['receipts'],'outstanding'=>$os['balance_amount']],
    'note'=>'Patient total is registered-to-date. Outstanding is the current balance of bills dated within the range; other values are selected-period activity.'];
}
if(request_method()!=='GET')json_error('Method not allowed.',405);
$scope=trim((string)($_GET['scope']??''));$map=cr_scopes();if(!isset($map[$scope]))json_error('Unsupported clinic report.',422);
$access=require_permission($map[$scope],ACTION_VIEW);$ctx=cr_context($access['user']);$b=(int)$ctx['branch_id'];
$from=cr_date('date_from',date('Y-m-01'));$to=cr_date('date_to',date('Y-m-d'));
if($to<$from)json_error('To Date cannot be earlier than From Date.',422);
if(isset($_GET['options']))json_success('Options loaded.',cr_options($ctx,$access,$from,$to));
switch($scope){
 case 'overview':$data=cr_overview($b,$from,$to);break;
 case 'patients':$data=cr_patients($b,$from,$to);break;
 case 'appointments':$data=cr_appointments($b,$from,$to);break;
 case 'consultations':$data=cr_consultations($b,$from,$to);break;
 case 'services':$data=cr_line_items($b,$from,$to,false);break;
 case 'laboratory':$data=cr_line_items($b,$from,$to,true);break;
 case 'collections':$data=cr_collections($b,$from,$to);break;
 case 'payments':$data=cr_payments($b,$from,$to);break;
 case 'outstanding':$data=cr_outstanding($b,$from,$to);break;
 case 'doctors':$data=cr_doctors($b,$from,$to);break;
 case 'financial':$data=cr_financial($b,$from,$to);break;
 case 'activity':$data=cr_activity($b,(int)$ctx['company_id'],$from,$to);break;
 default:json_error('Invalid report.',422);
}
$data['date_from']=$from;$data['date_to']=$to;$data['branch']=$ctx;
$data['allowed_actions']=$access['actions'];
json_success('Clinic report loaded.',$data);
