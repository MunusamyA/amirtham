<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';

// Read-only unified dashboard. All data is scoped to the signed-in tenant branch.
// Never accept a branch, company, role or permission ID supplied by the browser.
if (request_method() !== 'GET') json_error('Method not allowed.', 405);
$access = require_permission('dashboard.php', ACTION_VIEW);
$user = $access['user'];
if ((int)($user['role_type'] ?? 0) === 2) json_error('This dashboard requires a tenant account.', 403);
$branchId = (int)($user['branch_id'] ?? 0);
$roleId = (int)($user['role_id'] ?? 0);
if ($branchId < 1 || $roleId < 1) json_error('An active branch and role are required.', 403);
$q = db()->prepare('SELECT b.id,b.company_id,b.branch_name,c.company_name FROM branches b JOIN companies c ON c.id=b.company_id AND c.status=1 WHERE b.id=:b AND b.status=1 LIMIT 1');
$q->execute([':b'=>$branchId]);
$branch=$q->fetch(PDO::FETCH_ASSOC);
if (!$branch || ((int)($user['company_id']??0)>0 && (int)$branch['company_id'] !== (int)$user['company_id'])) json_error('Invalid assigned branch.', 403);

function dd_rows(string $sql, array $params=[]): array { $s=db()->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
function dd_scalar(string $sql, array $params=[]): float { $s=db()->prepare($sql); $s->execute($params); return (float)($s->fetchColumn() ?: 0); }
function dd_date(string $key,string $fallback): string {
    $value=trim((string)($_GET[$key]??'')); if ($value==='') return $fallback;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value); $errors=DateTimeImmutable::getLastErrors();
    if (!$d || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $d->format('Y-m-d')!==$value) json_error('Invalid '.$key.'.',422);
    return $value;
}
function dd_scope(string $dateColumn, string $extra=''): string { return "branch_id=:branch AND $dateColumn BETWEEN :date_from AND :date_to $extra"; }
function dd_amount(array $rows):float { return round(array_sum(array_map(static fn($r)=>(float)($r['amount']??0),$rows)),2); }
function dd_sum_rows(string $sql,int $b,string $f,string $t):array { return dd_rows($sql,[':branch'=>$b,':date_from'=>$f,':date_to'=>$t]); }
function dd_day_add(array &$daily,array $rows,string $field):void {
    foreach($rows as $r){$d=(string)($r['d']??''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d))continue;
        if (!isset($daily[$d]))$daily[$d]=[]; $daily[$d][$field]=($daily[$d][$field]??0)+(float)($r['amount']??0);
    }
}
function dd_mode(string $mode):string {
    $s=strtoupper(trim($mode));
    if ($s==='1' || str_contains($s,'CASH'))return 'Cash';
    if ($s==='2' || str_contains($s,'UPI'))return 'UPI';
    if ($s==='3' || str_contains($s,'BANK') || str_contains($s,'NEFT') || str_contains($s,'IMPS') || str_contains($s,'RTGS'))return 'Bank Transfer';
    if ($s==='4' || str_contains($s,'CHEQUE') || str_contains($s,'CHECK'))return 'Cheque';
    if (str_contains($s,'CARD'))return 'Card';
    return 'Other';
}
function dd_bucket(string $date,int $days):string {
    $d=new DateTimeImmutable($date);
    if($days<=31)return $d->format('d M');
    if($days<=120)return $d->format('o-\WW');
    return $d->format('M Y');
}
function dd_has(array $permissions,string $path,int $action=1):bool { return isset($permissions[$path]) && in_array($action,$permissions[$path],true); }

$f=dd_date('date_from',date('Y-m-01')); $t=dd_date('date_to',date('Y-m-d'));
$days=(int)((new DateTimeImmutable($f))->diff(new DateTimeImmutable($t))->format('%r%a'))+1;
if($days<1 || $days>366)json_error('Choose a valid date range of up to 366 days.',422);

// Resolve the role's actual menu permissions; a parent permission alone grants no leaf data access.
$permissionRows=dd_rows('SELECT m.menu_path,p.action_ids FROM role_permissions p JOIN menus m ON m.id=p.menu_id WHERE p.role_id=:r AND p.status=1 AND m.status=1',[':r'=>$roleId]);
$perms=[];
foreach($permissionRows as $p){$perms[$p['menu_path']]=array_values(array_unique(array_map('intval',array_filter(explode(',',str_replace(' ','',(string)$p['action_ids'])),'strlen'))));}
$has=static fn(string $path,int $action=1):bool => dd_has($perms,$path,$action);
$any=static function(array $paths)use($has):bool {foreach($paths as $path)if($has($path))return true;return false;};
$food=$any(['sales.php','sales-list.php','sales-report.php']);
$foodCash=$any(['customer-payment-list.php','customer-ledger.php','sales-list.php','sales.php','cash-bank-report.php']);
$clinicPatients=$any(['patient-list.php','report-patients.php']);
$clinicAppointments=$any(['appointment-list.php','appointment-calendar.php','report-appointments.php']);
$clinicConsults=$any(['consultation-list.php','report-consultations.php']);
$clinic=$clinicPatients||$clinicAppointments||$clinicConsults;
$clinicCash=$any(['bill-list.php','report-financial.php','report-collections.php']);
$college=$any(['admission-list.php','student-admission-report.php']);
$collegeCash=$any(['student-payment.php','student-payment-list.php','fee-collection-report.php']);
$expense=$any(['expense-list.php','expense-payment-list.php','expense-report.php','cash-bank-report.php']);
$suppliers=$any(['supplier-payment-list.php','supplier-ledger.php','cash-bank-report.php']);
$stock=$any(['product-list.php','stock-detail-report.php']);
$pendingBills=$food || $clinicCash || $collegeCash;

$daily=[]; $modes=[]; $recent=[]; $cards=[]; $pending=[];
$sumFields=[];
$pushDaily=static function(string $sql,string $field) use (&$daily,$branchId,$f,$t):void {dd_day_add($daily,dd_sum_rows($sql,$branchId,$f,$t),$field);};
$pushModes=static function(array $rows)use(&$modes):void {foreach($rows as $r){$label=dd_mode((string)$r['mode']);$modes[$label]=($modes[$label]??0)+(float)$r['amount'];}};
$pushRecent=static function(array $rows,string $kind,string $url)use(&$recent):void {foreach($rows as $r)$recent[]=['date'=>$r['d'],'type'=>$kind,'reference'=>(string)$r['reference'],'detail'=>(string)($r['detail']??''),'url'=>$url];};

if($food){
    $pushDaily("SELECT invoice_date d,SUM(grand_total) amount FROM food_sales WHERE ".dd_scope('invoice_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL AND document_type=4")." GROUP BY invoice_date",'food_billed');
    $pushRecent(dd_rows("SELECT created_at d,COALESCE(sales_no,'Sale') reference,COALESCE(customer_name_snapshot,'') detail FROM food_sales WHERE branch_id=:b AND document_type=4 AND status=1 AND posting_status=1 AND reversed_at IS NULL AND invoice_date BETWEEN :f AND :t ORDER BY created_at DESC LIMIT 6",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Sales Invoice','sales-list.php');
}
if($foodCash){
    // These headers are the authoritative posted POS/customer receipts. Do not add sales.paid_amount again.
    $pushDaily("SELECT payment_date d,SUM(amount) amount FROM food_customer_payments WHERE ".dd_scope('payment_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL")." GROUP BY payment_date",'food_receipts');
    $pushModes(dd_rows("SELECT COALESCE(pm.method_name,'Other') mode,SUM(pd.amount) amount FROM food_customer_payment_details pd JOIN food_customer_payments p ON p.id=pd.customer_payment_id AND p.branch_id=pd.branch_id LEFT JOIN food_payment_methods pm ON pm.id=pd.payment_method_id AND pm.branch_id=pd.branch_id WHERE p.branch_id=:b AND p.payment_date BETWEEN :f AND :t AND p.status=1 AND p.posting_status=1 AND p.reversed_at IS NULL AND pd.status=1 GROUP BY mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]));
    $pushModes(dd_rows("SELECT COALESCE(NULLIF(p.payment_mode,''),'Other') mode,SUM(p.amount) amount FROM food_customer_payments p WHERE p.branch_id=:b AND p.payment_date BETWEEN :f AND :t AND p.status=1 AND p.posting_status=1 AND p.reversed_at IS NULL AND NOT EXISTS (SELECT 1 FROM food_customer_payment_details pd WHERE pd.customer_payment_id=p.id AND pd.branch_id=p.branch_id AND pd.status=1) GROUP BY mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]));
    $pushRecent(dd_rows("SELECT created_at d,COALESCE(payment_no,payment_group_no,'POS receipt') reference,CAST(amount AS CHAR) detail FROM food_customer_payments WHERE branch_id=:b AND payment_date BETWEEN :f AND :t AND status=1 AND posting_status=1 AND reversed_at IS NULL ORDER BY created_at DESC LIMIT 6",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Customer Payment','customer-payment-list.php');
}
if($clinicCash){
    $pushDaily("SELECT bill_date d,SUM(grand_total) amount FROM clinic_bills WHERE ".dd_scope('bill_date','AND status=1')." GROUP BY bill_date",'clinic_billed');
    $pushDaily("SELECT COALESCE(pay.payment_date,DATE(pay.created_at)) d,SUM(pay.amount) amount FROM clinic_bill_payments pay JOIN clinic_bills b ON b.id=pay.bill_id AND b.branch_id=pay.branch_id AND b.status=1 WHERE pay.branch_id=:branch AND pay.status=1 AND COALESCE(pay.payment_date,DATE(pay.created_at)) BETWEEN :date_from AND :date_to GROUP BY d",'clinic_receipts');
    $pushModes(dd_rows("SELECT COALESCE(NULLIF(pay.payment_mode,''),'Other') mode,SUM(pay.amount) amount FROM clinic_bill_payments pay JOIN clinic_bills b ON b.id=pay.bill_id AND b.branch_id=pay.branch_id AND b.status=1 WHERE pay.branch_id=:b AND pay.status=1 AND COALESCE(pay.payment_date,DATE(pay.created_at)) BETWEEN :f AND :t GROUP BY mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]));
    $pushRecent(dd_rows("SELECT b.created_at d,b.bill_no reference,'' detail FROM clinic_bills b WHERE b.branch_id=:b AND b.bill_date BETWEEN :f AND :t AND b.status=1 ORDER BY b.created_at DESC LIMIT 6",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Clinic Bill','bill-list.php');
}
if($collegeCash){
    $pushDaily("SELECT receipt_date d,SUM(amount) amount FROM college_fee_receipts WHERE ".dd_scope('receipt_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL")." GROUP BY receipt_date",'college_receipts');
    $pushModes(dd_rows("SELECT CAST(pd.payment_mode AS CHAR) mode,SUM(pd.amount) amount FROM college_fee_receipt_payment_details pd JOIN college_fee_receipts r ON r.id=pd.fee_receipt_id AND r.branch_id=pd.branch_id WHERE r.branch_id=:b AND r.status=1 AND r.posting_status=1 AND r.reversed_at IS NULL AND r.receipt_date BETWEEN :f AND :t AND pd.status=1 GROUP BY pd.payment_mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]));
    $pushModes(dd_rows("SELECT COALESCE(NULLIF(r.payment_mode,''),'Other') mode,SUM(r.amount) amount FROM college_fee_receipts r WHERE r.branch_id=:b AND r.status=1 AND r.posting_status=1 AND r.reversed_at IS NULL AND r.receipt_date BETWEEN :f AND :t AND NOT EXISTS (SELECT 1 FROM college_fee_receipt_payment_details pd WHERE pd.fee_receipt_id=r.id AND pd.branch_id=r.branch_id AND pd.status=1) GROUP BY mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]));
    $pushRecent(dd_rows("SELECT created_at d,receipt_no reference,CAST(amount AS CHAR) detail FROM college_fee_receipts WHERE branch_id=:b AND receipt_date BETWEEN :f AND :t AND status=1 AND posting_status=1 AND reversed_at IS NULL ORDER BY created_at DESC LIMIT 6",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Student Receipt','student-payment-list.php');
}
if($expense){
    $pushDaily("SELECT payment_date d,SUM(amount) amount FROM food_expense_payments WHERE ".dd_scope('payment_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL")." GROUP BY payment_date",'food_expense_out');
    // The supplied schema has no payment-event table for non-food expenses; paid_amount is a snapshot, not a bank-ledger entry.
    $pushDaily("SELECT expense_date d,SUM(paid_amount) amount FROM expenses WHERE ".dd_scope('expense_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL AND (module_code IS NULL OR module_code<>'food_supplementary')")." GROUP BY expense_date",'other_expense_recorded');
}
if($suppliers){
    $pushDaily("SELECT payment_date d,SUM(amount) amount FROM food_supplier_payments WHERE ".dd_scope('payment_date',"AND status=1 AND posting_status=1 AND reversed_at IS NULL")." GROUP BY payment_date",'supplier_out');
}
if($foodCash){
    $pushDaily("SELECT refund_date d,SUM(rf.amount) amount FROM food_sales_return_refunds rf JOIN food_sale_returns sr ON sr.id=rf.sale_return_id AND sr.branch_id=rf.branch_id AND sr.status=1 AND sr.posting_status=1 AND sr.reversed_at IS NULL WHERE rf.branch_id=:branch AND rf.refund_date BETWEEN :date_from AND :date_to AND rf.status=1 AND rf.reversed_at IS NULL GROUP BY refund_date",'refund_out');
}
if($clinicPatients){
    $pushDaily("SELECT DATE(created_at) d,COUNT(*) amount FROM clinic_patients WHERE branch_id=:branch AND created_at>=:date_from AND created_at<DATE_ADD(:date_to,INTERVAL 1 DAY) AND status=1 GROUP BY DATE(created_at)",'new_patients');
}
if($clinicConsults){
    $pushDaily("SELECT COALESCE(NULLIF(visit_date,'0000-00-00'),DATE(created_at)) d,COUNT(*) amount FROM clinic_consultations WHERE status=1 AND branch_id=:branch AND COALESCE(NULLIF(visit_date,'0000-00-00'),DATE(created_at)) BETWEEN :date_from AND :date_to GROUP BY d",'visits');
}
if($clinicAppointments){
    $pushDaily("SELECT appointment_date d,COUNT(*) amount FROM clinic_appointments WHERE ".dd_scope('appointment_date','AND status=1')." GROUP BY appointment_date",'appointments');
    if($has('appointment-list.php'))$pushRecent(dd_rows("SELECT created_at d,COALESCE(appointment_no,'Appointment') reference,'' detail FROM clinic_appointments WHERE branch_id=:b AND appointment_date BETWEEN :f AND :t AND status=1 ORDER BY created_at DESC LIMIT 5",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Appointment','appointment-list.php');
}
if($college){
    $pushDaily("SELECT admission_date d,COUNT(*) amount FROM college_admissions WHERE ".dd_scope('admission_date','AND status=1')." GROUP BY admission_date",'admissions');
    $pushRecent(dd_rows("SELECT created_at d,admission_no reference,'' detail FROM college_admissions WHERE branch_id=:b AND admission_date BETWEEN :f AND :t AND status=1 ORDER BY created_at DESC LIMIT 5",[':b'=>$branchId,':f'=>$f,':t'=>$t]),'Student Admission','admission-list.php');
}

$seriesNames=['food_billed','clinic_billed','food_receipts','clinic_receipts','college_receipts','new_patients','appointments','visits','admissions','food_expense_out','other_expense_recorded','supplier_out','refund_out'];
$totals=array_fill_keys($seriesNames,0.0);
$byBucket=[];
// Include empty dates, so zero-transaction intervals have honest flat lines rather than disappearing.
for($dt=new DateTimeImmutable($f),$end=new DateTimeImmutable($t);$dt<=$end;$dt=$dt->modify('+1 day')){
    $d=$dt->format('Y-m-d');$label=dd_bucket($d,$days);
    if(!isset($byBucket[$label]))$byBucket[$label]=array_fill_keys($seriesNames,0.0);
    foreach($seriesNames as $key){$v=(float)($daily[$d][$key]??0);$totals[$key]+=$v;$byBucket[$label][$key]+=$v;}
}
$collection=round($totals['food_receipts']+$totals['clinic_receipts']+$totals['college_receipts'],2);
$billed=round($totals['food_billed']+$totals['clinic_billed'],2); // College fees are plans, not invoices.
$outflows=round($totals['food_expense_out']+$totals['other_expense_recorded']+$totals['supplier_out']+$totals['refund_out'],2);
$outstanding=[];
if($food)$outstanding['Sales']=round(dd_scalar("SELECT COALESCE(SUM(GREATEST(balance_amount,0)),0) FROM food_sales WHERE branch_id=:b AND document_type=4 AND posting_status=1 AND status=1 AND reversed_at IS NULL",[':b'=>$branchId]),2);
if($clinicCash)$outstanding['Clinic']=round(dd_scalar('SELECT COALESCE(SUM(GREATEST(balance_amount,0)),0) FROM clinic_bills WHERE branch_id=:b AND status=1',[':b'=>$branchId]),2);
if($collegeCash)$outstanding['College']=round(dd_scalar("SELECT COALESCE(SUM(GREATEST(pl.net_payable-COALESCE(r.received,0),0)),0) FROM college_student_fee_plans pl LEFT JOIN (SELECT student_fee_plan_id,SUM(amount) received FROM college_fee_receipts WHERE branch_id=:b1 AND status=1 AND posting_status=1 AND reversed_at IS NULL GROUP BY student_fee_plan_id) r ON r.student_fee_plan_id=pl.id WHERE pl.branch_id=:b2 AND pl.status=1",[':b1'=>$branchId,':b2'=>$branchId]),2);
if($collection>=0 && ($foodCash||$clinicCash||$collegeCash)) $cards[]=['key'=>'collection','label'=>'Total Collections','value'=>$collection,'format'=>'money','icon'=>'hand-coins'];
if($food||$clinicCash)$cards[]=['key'=>'billed','label'=>'Sales + Clinic Billing','value'=>$billed,'format'=>'money','icon'=>'receipt-text'];
if($outstanding)$cards[]=['key'=>'outstanding','label'=>'Current Outstanding','value'=>round(array_sum($outstanding),2),'format'=>'money','icon'=>'wallet-cards'];
if($clinicPatients)$cards[]=['key'=>'patients','label'=>'New Patients','value'=>(int)$totals['new_patients'],'format'=>'number','icon'=>'users-round'];
if($college)$cards[]=['key'=>'admissions','label'=>'Student Admissions','value'=>(int)$totals['admissions'],'format'=>'number','icon'=>'graduation-cap'];
if($food)$cards[]=['key'=>'sales','label'=>'Sales Billed','value'=>round($totals['food_billed'],2),'format'=>'money','icon'=>'shopping-cart'];
if($clinicAppointments)$cards[]=['key'=>'appointments','label'=>'Appointments','value'=>(int)$totals['appointments'],'format'=>'number','icon'=>'calendar-check'];
if(($foodCash||$clinicCash||$collegeCash) && ($expense||$suppliers))$cards[]=['key'=>'movement','label'=>'Indicative Cash Movement','value'=>round($collection-$outflows,2),'format'=>'money','icon'=>'trending-up'];

if($clinicAppointments && $has('appointment-list.php')){
    $n=dd_scalar("SELECT COUNT(*) FROM clinic_appointments WHERE branch_id=:b AND status=1 AND appointment_date>=CURDATE() AND LOWER(COALESCE(appointment_status,'')) NOT IN ('cancelled','completed','no show','no_show') AND LOWER(COALESCE(appointment_state,'')) NOT IN ('cancelled','completed','no_show')",[':b'=>$branchId]);
    $pending[]=['label'=>'Upcoming Appointments','value'=>(int)$n,'unit'=>'appointments','url'=>'appointment-list.php'];
}
if($food && $any(['sales-list.php','sales.php','sales-report.php'])){$pending[]=['label'=>'Unpaid Sales Invoices','value'=>(int)dd_scalar("SELECT COUNT(*) FROM food_sales WHERE branch_id=:b AND status=1 AND posting_status=1 AND document_type=4 AND reversed_at IS NULL AND balance_amount>0.005",[':b'=>$branchId]),'unit'=>'invoices','url'=>$has('sales-list.php')?'sales-list.php':($has('sales.php')?'sales.php':'sales-report.php')];}
if($clinicCash && $any(['bill-list.php','report-outstanding.php','report-financial.php'])){$pending[]=['label'=>'Unpaid Clinic Bills','value'=>(int)dd_scalar('SELECT COUNT(*) FROM clinic_bills WHERE branch_id=:b AND status=1 AND balance_amount>0.005',[':b'=>$branchId]),'unit'=>'bills','url'=>$has('bill-list.php')?'bill-list.php':($has('report-outstanding.php')?'report-outstanding.php':'report-financial.php')];}
if($collegeCash){$pending[]=['label'=>'College Plans With Dues','value'=>(int)dd_scalar("SELECT COUNT(*) FROM college_student_fee_plans pl LEFT JOIN (SELECT student_fee_plan_id,SUM(amount) paid FROM college_fee_receipts WHERE branch_id=:b1 AND status=1 AND posting_status=1 AND reversed_at IS NULL GROUP BY student_fee_plan_id) r ON r.student_fee_plan_id=pl.id WHERE pl.branch_id=:b2 AND pl.status=1 AND pl.net_payable>COALESCE(r.paid,0)+0.005",[':b1'=>$branchId,':b2'=>$branchId]),'unit'=>'fee plans','url'=>$has('fee-due-report.php')?'fee-due-report.php':($has('student-payment-list.php')?'student-payment-list.php':($has('student-payment.php')?'student-payment.php':'fee-collection-report.php'))];}
if($stock){$pending[]=['label'=>'Low Stock Products','value'=>(int)dd_scalar("SELECT COUNT(*) FROM (SELECT p.id FROM food_products p LEFT JOIN food_stock_movements sm ON sm.branch_id=p.branch_id AND sm.product_id=p.id AND sm.status=1 WHERE p.branch_id=:b AND p.status=1 AND p.reorder_level>0 GROUP BY p.id,p.reorder_level HAVING COALESCE(SUM(sm.quantity_in-sm.quantity_out),0)<=p.reorder_level) x",[':b'=>$branchId]),'unit'=>'products','url'=>$has('product-list.php')?'product-list.php':'stock-detail-report.php'];}

$apptModes=[];
if($clinicAppointments && $has('appointment-list.php')){
    foreach(dd_rows("SELECT COALESCE(NULLIF(appointment_status,''),NULLIF(appointment_state,''),'Unknown') mode,COUNT(*) amount FROM clinic_appointments WHERE branch_id=:b AND status=1 AND appointment_date BETWEEN :f AND :t GROUP BY mode",[':b'=>$branchId,':f'=>$f,':t'=>$t]) as $r)$apptModes[]=['name'=>$r['mode'],'value'=>(int)$r['amount']];
}
$actions=[
 ['Sales POS','shopping-cart','sales.php'],['Clinic Billing','receipt-text','bill-list.php'],['Customer Payments','hand-coins','customer-payment-list.php'],
 ['Appointments','calendar-plus','appointment-list.php'],['Patients','user-round-plus','patient-list.php'],['Student Admissions','graduation-cap','admission-list.php'],
 ['Purchases','shopping-bag','purchase-list.php'],['Expenses','receipt','expense-list.php'],['Reports','chart-no-axes-combined','report-overview.php']
];
$quick=[];
foreach($actions as [$label,$icon,$path])if($has($path))$quick[]=['label'=>$label,'icon'=>$icon,'url'=>$path];
// Add at most eight recent records and never expose pages without view access.
usort($recent,static fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));
$recent=array_values(array_filter($recent,static fn($r)=>dd_has($perms,(string)$r['url'])));
$recent=array_slice($recent,0,8);
$trend=[];
foreach($byBucket as $label=>$row)$trend[]=['period'=>$label,'collections'=>round($row['food_receipts']+$row['clinic_receipts']+$row['college_receipts'],2),
    'food'=>round($row['food_receipts'],2),'clinic'=>round($row['clinic_receipts'],2),'college'=>round($row['college_receipts'],2),
    'billed'=>round($row['food_billed']+$row['clinic_billed'],2),
    'outflows'=>round($row['food_expense_out']+$row['other_expense_recorded']+$row['supplier_out']+$row['refund_out'],2),
    'patients'=>(int)$row['new_patients'],'visits'=>(int)$row['visits'],'admissions'=>(int)$row['admissions']];
$mix=[];
if($foodCash)$mix[]=['name'=>'Sales','value'=>round($totals['food_receipts'],2)];
if($clinicCash)$mix[]=['name'=>'Clinic','value'=>round($totals['clinic_receipts'],2)];
if($collegeCash)$mix[]=['name'=>'College','value'=>round($totals['college_receipts'],2)];
$paymentModes=[];foreach($modes as $name=>$v)$paymentModes[]=['name'=>$name,'value'=>round($v,2)];

json_success('Dashboard loaded.',[
 'branch'=>['name'=>$branch['branch_name'],'company'=>$branch['company_name']],
 'date_from'=>$f,'date_to'=>$t,'cards'=>$cards,'quick_actions'=>$quick,'pending'=>$pending,'recent'=>$recent,
 'charts'=>['trend'=>$trend,'payment_modes'=>$paymentModes,'contribution'=>$mix,'appointments'=>$apptModes],
 'visible'=>['food'=>$food,'clinic'=>$clinic,'clinic_patients'=>$clinicPatients,'clinic_visits'=>$clinicConsults,'clinic_appointments'=>$clinicAppointments,'college'=>$college,'food_cash'=>$foodCash,'clinic_cash'=>$clinicCash,'college_cash'=>$collegeCash,'outflows'=>$expense||$suppliers],
 'notes'=>[
   'Only data for the signed-in branch and accessible module permissions are returned.',
   'Billing is sales/clinic invoice totals; college fee plans are not treated as invoices. Collections use posted payment receipts, not invoice-header paid amounts.',
   'Outstanding is the CURRENT balance for accessible modules, not an historical balance as of the selected date.',
   'Indicative cash movement subtracts posted supplier, food expense and refund payments plus non-food recorded paid expenses; it is not accounting profit or audited cash flow.'
 ]
]);
