<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

const PL_MODULE_CODE = 'food_supplementary';

function pl_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Profit & Loss is available only for tenant users.', 403);
    }
    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);
    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,c.company_name
         FROM branches b INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.',403);
    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
    ];
}

function pl_table_exists(string $table): bool
{
    static $cache=[];
    if (array_key_exists($table,$cache)) return $cache[$table];
    $stmt=db()->query('SHOW TABLES LIKE '.db()->quote($table));
    return $cache[$table]=(bool)($stmt&&$stmt->fetchColumn());
}

function pl_require_schema(): void
{
    foreach(['food_sales','food_sale_items','food_sale_returns','food_sale_return_items','food_purchases','food_purchase_items','food_stock_movements','food_products','expenses','expense_categories'] as $table){
        if(!pl_table_exists($table)) json_error('Profit & Loss schema is incomplete. Missing table: '.$table.'.',500);
    }
}

function pl_date($value,string $fallback): string
{
    $value=trim((string)$value);
    if($value==='') return $fallback;
    $d=DateTime::createFromFormat('Y-m-d',$value);
    $errors=DateTime::getLastErrors();
    if(!$d||($errors&&((int)$errors['warning_count']||(int)$errors['error_count']))||$d->format('Y-m-d')!==$value){
        json_error('Enter a valid Profit & Loss date.',422);
    }
    return $value;
}

function pl_products(int $branchId): array
{
    $stmt=db()->prepare('SELECT id,product_code,product_name,status FROM food_products WHERE branch_id=:branch_id ORDER BY product_name,product_code');
    $stmt->execute([':branch_id'=>$branchId]);
    $rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $rows[]=['id'=>(int)$r['id'],'product_code'=>(string)$r['product_code'],'product_name'=>(string)$r['product_name'],'status'=>(int)$r['status']];
    }
    return $rows;
}

function pl_base_costs(int $branchId): array
{
    $stmt=db()->prepare(
        'SELECT pi.purchase_id,pi.product_id,
                CASE WHEN SUM(pi.base_quantity)>0 THEN SUM(pi.taxable_amount)/SUM(pi.base_quantity) ELSE 0 END AS base_cost
         FROM food_purchase_items pi
         INNER JOIN food_purchases p ON p.id=pi.purchase_id AND p.branch_id=pi.branch_id
         WHERE pi.branch_id=:branch_id AND pi.status=1
           AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL
         GROUP BY pi.purchase_id,pi.product_id'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $map=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $map[(int)$r['purchase_id'].'|'.(int)$r['product_id']]=(float)$r['base_cost'];
    }
    return $map;
}

function pl_product_rows(int $branchId,string $fromDate,string $toDate,int $productId,array $costs): array
{
    $products=[];
    $add=function(int $id,string $code,string $name) use (&$products): void {
        if(!isset($products[$id])){
            $products[$id]=[
                'product_id'=>$id,'product_code'=>$code,'product_name'=>$name,
                'gross_sales'=>0.0,'sales_returns'=>0.0,'sales_cogs'=>0.0,'return_cogs_reversed'=>0.0,
            ];
        }
    };

    $sql='SELECT si.product_id,si.source_purchase_id,si.base_quantity,si.taxable_amount,
                 pr.product_code,pr.product_name
          FROM food_sale_items si
          INNER JOIN food_sales s ON s.id=si.sale_id AND s.branch_id=si.branch_id
          INNER JOIN food_products pr ON pr.id=si.product_id AND pr.branch_id=si.branch_id
          WHERE si.branch_id=:branch_id AND si.status=1
            AND s.document_type=4 AND s.posting_status=1 AND s.status=1 AND s.reversed_at IS NULL
            AND s.invoice_date BETWEEN :date_from AND :date_to';
    $params=[':branch_id'=>$branchId,':date_from'=>$fromDate,':date_to'=>$toDate];
    if($productId>0){$sql.=' AND si.product_id=:product_id';$params[':product_id']=$productId;}
    $stmt=db()->prepare($sql);$stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $pid=(int)$r['product_id'];$add($pid,(string)$r['product_code'],(string)$r['product_name']);
        $cost=$costs[(int)($r['source_purchase_id']??0).'|'.$pid]??0.0;
        $products[$pid]['gross_sales']+=(float)$r['taxable_amount'];
        $products[$pid]['sales_cogs']+=(float)$r['base_quantity']*$cost;
    }

    $sql='SELECT ri.product_id,ri.source_purchase_id,ri.base_quantity,ri.taxable_amount,ri.add_to_stock,
                 pr.product_code,pr.product_name
          FROM food_sale_return_items ri
          INNER JOIN food_sale_returns sr ON sr.id=ri.sale_return_id AND sr.branch_id=ri.branch_id
          INNER JOIN food_products pr ON pr.id=ri.product_id AND pr.branch_id=ri.branch_id
          WHERE ri.branch_id=:branch_id AND ri.status=1
            AND sr.posting_status=1 AND sr.status=1 AND sr.reversed_at IS NULL
            AND sr.return_date BETWEEN :date_from AND :date_to';
    $params=[':branch_id'=>$branchId,':date_from'=>$fromDate,':date_to'=>$toDate];
    if($productId>0){$sql.=' AND ri.product_id=:product_id';$params[':product_id']=$productId;}
    $stmt=db()->prepare($sql);$stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $pid=(int)$r['product_id'];$add($pid,(string)$r['product_code'],(string)$r['product_name']);
        $cost=$costs[(int)($r['source_purchase_id']??0).'|'.$pid]??0.0;
        $products[$pid]['sales_returns']+=(float)$r['taxable_amount'];
        if((int)$r['add_to_stock']===1){
            $products[$pid]['return_cogs_reversed']+=(float)$r['base_quantity']*$cost;
        }
    }

    $rows=[];
    foreach($products as $r){
        $r['gross_sales']=round($r['gross_sales'],2);
        $r['sales_returns']=round($r['sales_returns'],2);
        $r['net_sales']=round($r['gross_sales']-$r['sales_returns'],2);
        $r['sales_cogs']=round($r['sales_cogs'],2);
        $r['return_cogs_reversed']=round($r['return_cogs_reversed'],2);
        $r['net_cogs']=round($r['sales_cogs']-$r['return_cogs_reversed'],2);
        $r['gross_profit']=round($r['net_sales']-$r['net_cogs'],2);
        $r['gross_margin']=$r['net_sales']!=0?round($r['gross_profit']/$r['net_sales']*100,2):0.0;
        $rows[]=$r;
    }
    usort($rows,static fn(array $a,array $b): int => $b['gross_profit'] <=> $a['gross_profit']);
    return $rows;
}

function pl_expenses(int $branchId,string $fromDate,string $toDate): array
{
    $hasPaymentStatus=false;
    $stmt=db()->query("SHOW COLUMNS FROM expenses LIKE 'payment_status'");
    if($stmt&&$stmt->fetchColumn()) $hasPaymentStatus=true;
    $sql='SELECT e.expense_category_id,ec.category_code,ec.category_name,
                 SUM(CASE WHEN e.tax_mode=1 AND e.taxable_amount>0 THEN e.taxable_amount ELSE e.total_amount END) AS amount
          FROM expenses e
          INNER JOIN expense_categories ec ON ec.id=e.expense_category_id
          WHERE e.branch_id=:branch_id
            AND e.posting_status=1 AND e.status=1 AND e.reversed_at IS NULL
            AND e.expense_date BETWEEN :date_from AND :date_to
            AND (e.module_code=:module_code OR e.module_code IS NULL)
          GROUP BY e.expense_category_id,ec.category_code,ec.category_name
          ORDER BY amount DESC,ec.category_name';
    $stmt=db()->prepare($sql);
    $stmt->execute([':branch_id'=>$branchId,':date_from'=>$fromDate,':date_to'=>$toDate,':module_code'=>PL_MODULE_CODE]);
    $rows=[];$total=0.0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $amount=round((float)$r['amount'],2);$total+=$amount;
        $rows[]=['category_id'=>(int)$r['expense_category_id'],'category_code'=>(string)$r['category_code'],'category_name'=>(string)$r['category_name'],'amount'=>$amount];
    }
    return ['rows'=>$rows,'total'=>round($total,2),'has_payment_status'=>$hasPaymentStatus];
}

function pl_inventory_value(int $branchId,string $cutoffDate,array $costs,int $productId=0): float
{
    $sql='SELECT sm.product_id,sm.source_purchase_id,SUM(sm.quantity_in-sm.quantity_out) AS qty
          FROM food_stock_movements sm
          WHERE sm.branch_id=:branch_id AND sm.status=1 AND sm.movement_date<=:cutoff';
    $params=[':branch_id'=>$branchId,':cutoff'=>$cutoffDate];
    if($productId>0){$sql.=' AND sm.product_id=:product_id';$params[':product_id']=$productId;}
    $sql.=' GROUP BY sm.product_id,sm.source_purchase_id';
    $stmt=db()->prepare($sql);$stmt->execute($params);
    $value=0.0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $qty=max(0.0,(float)$r['qty']);
        $cost=$costs[(int)($r['source_purchase_id']??0).'|'.(int)$r['product_id']]??0.0;
        $value+=$qty*$cost;
    }
    return round($value,2);
}

$method=request_method();
if($method!=='GET') json_error('Method not allowed.',405);
pl_require_schema();
$access=require_permission('profit-loss.php',ACTION_VIEW);
$ctx=pl_tenant_context($access['user']);
$branchId=(int)$ctx['branch_id'];

if(isset($_GET['options'])){
    json_success('Profit & Loss options loaded.',[
        'allowed_actions'=>$access['actions'],
        'products'=>pl_products($branchId),
        'date_from'=>date('Y-m-01'),
        'date_to'=>date('Y-m-d'),
        'branch'=>$ctx,
    ]);
}

$fromDate=pl_date($_GET['date_from']??'',date('Y-m-01'));
$toDate=pl_date($_GET['date_to']??'',date('Y-m-d'));
if($toDate<$fromDate) json_error('To Date cannot be earlier than From Date.',422);
$productId=max(0,(int)($_GET['product_id']??0));
$costs=pl_base_costs($branchId);
$productRows=pl_product_rows($branchId,$fromDate,$toDate,$productId,$costs);
$expense=pl_expenses($branchId,$fromDate,$toDate);

$grossSales=0.0;$salesReturns=0.0;$salesCogs=0.0;$returnCogs=0.0;
foreach($productRows as $r){
    $grossSales+=$r['gross_sales'];$salesReturns+=$r['sales_returns'];
    $salesCogs+=$r['sales_cogs'];$returnCogs+=$r['return_cogs_reversed'];
}
$grossSales=round($grossSales,2);$salesReturns=round($salesReturns,2);
$netSales=round($grossSales-$salesReturns,2);
$salesCogs=round($salesCogs,2);$returnCogs=round($returnCogs,2);
$netCogs=round($salesCogs-$returnCogs,2);
$grossProfit=round($netSales-$netCogs,2);
$operatingExpenses=(float)$expense['total'];
$netProfit=round($grossProfit-$operatingExpenses,2);
$grossMargin=$netSales!=0?round($grossProfit/$netSales*100,2):0.0;
$netMargin=$netSales!=0?round($netProfit/$netSales*100,2):0.0;
$openingCutoff=(new DateTime($fromDate))->modify('-1 day')->format('Y-m-d');
$openingStock=pl_inventory_value($branchId,$openingCutoff,$costs,$productId);
$closingStock=pl_inventory_value($branchId,$toDate,$costs,$productId);

json_success('Profit & Loss loaded.',[
    'allowed_actions'=>$access['actions'],
    'date_from'=>$fromDate,
    'date_to'=>$toDate,
    'summary'=>[
        'gross_sales'=>$grossSales,
        'sales_returns'=>$salesReturns,
        'net_sales'=>$netSales,
        'sales_cogs'=>$salesCogs,
        'return_cogs_reversed'=>$returnCogs,
        'net_cogs'=>$netCogs,
        'gross_profit'=>$grossProfit,
        'operating_expenses'=>$operatingExpenses,
        'net_profit'=>$netProfit,
        'gross_margin'=>$grossMargin,
        'net_margin'=>$netMargin,
        'opening_stock_value'=>$openingStock,
        'closing_stock_value'=>$closingStock,
    ],
    'products'=>$productRows,
    'expenses'=>$expense['rows'],
    'notes'=>[
        'Revenue and COGS exclude GST.',
        'COGS uses the actual selected Purchase Batch cost from purchase item taxable value per base quantity.',
        'A Sales Return reverses COGS only when Add To Stock is Yes; non-restock returns keep their cost in COGS.',
        'Customer/Supplier/Expense payment timing does not change Profit & Loss; posted Sales, Returns and Expenses are used on their business dates.',
        'Shared branch Expenses (module_code NULL) are included together with Food Supplementary Expenses.',
    ],
]);
