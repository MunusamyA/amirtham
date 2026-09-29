<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

const RP_MODULE_CODE = 'food_supplementary';

function rp_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
    return $cache[$table] = (bool)($stmt && $stmt->fetchColumn());
}

function rp_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!rp_table_exists($table)) return $cache[$key] = false;
    $stmt = db()->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . db()->quote($column));
    return $cache[$key] = (bool)($stmt && $stmt->fetchColumn());
}

function rp_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Reports are available only for tenant users.', 403);
    }
    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);
    $stmt = db()->prepare(
        'SELECT b.id AS branch_id,b.company_id,b.branch_name,b.state_code,c.company_name
         FROM branches b INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id' => $branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);
    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'state_code' => (string)($row['state_code'] ?? ''),
        'company_name' => (string)$row['company_name'],
    ];
}

function rp_scope_config(string $scope): array
{
    $map = [
        'sales' => ['page'=>'sales-report.php','report'=>'sales','forced_tax'=>null],
        'gst_sales' => ['page'=>'gst-sales-report.php','report'=>'sales','forced_tax'=>1],
        'non_gst_sales' => ['page'=>'non-gst-sales-report.php','report'=>'sales','forced_tax'=>0],
        'purchase' => ['page'=>'purchase-report.php','report'=>'purchase','forced_tax'=>null],
        'gst_purchase' => ['page'=>'gst-purchase-report.php','report'=>'purchase','forced_tax'=>1],
        'non_gst_purchase' => ['page'=>'non-gst-purchase-report.php','report'=>'purchase','forced_tax'=>0],
        'stock' => ['page'=>'stock-detail-report.php','report'=>'stock','forced_tax'=>null],
        'customer_outstanding' => ['page'=>'customer-outstanding-report.php','report'=>'customer_outstanding','forced_tax'=>null],
        'supplier_outstanding' => ['page'=>'supplier-outstanding-report.php','report'=>'supplier_outstanding','forced_tax'=>null],
        'expense' => ['page'=>'expense-report.php','report'=>'expense','forced_tax'=>null],
        'tax_expense' => ['page'=>'tax-expense-report.php','report'=>'expense','forced_tax'=>'dynamic'],
        'cash_bank' => ['page'=>'cash-bank-report.php','report'=>'cash_bank','forced_tax'=>null],
    ];
    if (!isset($map[$scope])) json_error('Invalid report scope.', 422);
    return $map[$scope];
}

function rp_valid_date($value, string $fallback, string $field = 'date'): string
{
    $value = trim((string)$value);
    if ($value === '') return $fallback;
    $d = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors && ((int)$errors['warning_count'] || (int)$errors['error_count'])) || $d->format('Y-m-d') !== $value) {
        json_error('Enter a valid ' . $field . '.', 422);
    }
    return $value;
}

function rp_financial_year_start(): string
{
    $year = (int)date('Y');
    if ((int)date('n') < 4) $year--;
    return sprintf('%04d-04-01', $year);
}

function rp_tax_mode($raw, $forced): ?int
{
    if ($forced === 0 || $forced === 1) return (int)$forced;
    if ($forced === 'dynamic') {
        return ((int)$raw === 0) ? 0 : 1;
    }
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '-1' || strtolower($raw) === 'all') return null;
    $mode = (int)$raw;
    return in_array($mode, [0,1], true) ? $mode : null;
}

function rp_payment_status_label(int $value): string
{
    return [1=>'Unpaid',2=>'Partially Paid',3=>'Paid'][$value] ?? 'Unknown';
}

function rp_tax_label(int $value): string
{
    return $value === 1 ? 'GST' : 'NON GST';
}

function rp_mode_label(int $value): string
{
    return [1=>'Cash',2=>'UPI',3=>'Bank Transfer',4=>'Cheque'][$value] ?? 'Payment';
}


function rp_qty_text(float $value): string
{
    $value = round($value, 3);
    if (abs($value) < 0.0005) return '0';
    $text = number_format($value, 3, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text === '-0' ? '0' : $text;
}

function rp_mixed_quantity_text(
    float $baseQty,
    ?int $secondaryUnitId,
    float $conversion,
    string $primaryUnit,
    string $secondaryUnit
): string {
    $primaryUnit = trim($primaryUnit) !== '' ? trim($primaryUnit) : 'Primary';

    if (!$secondaryUnitId || $conversion <= 0 || trim($secondaryUnit) === '') {
        return rp_qty_text($baseQty) . ' ' . $primaryUnit;
    }

    $negative = $baseQty < -0.0005;
    $absolute = abs(round($baseQty, 3));
    $primary = (int)floor(($absolute + 0.0000001) / $conversion);
    $secondary = round($absolute - ($primary * $conversion), 3);

    if ($secondary >= $conversion - 0.0005) {
        $primary++;
        $secondary = 0.0;
    }

    $parts = [];
    if ($primary > 0) $parts[] = $primary . ' ' . $primaryUnit;
    if ($secondary > 0.0004 || $parts === []) {
        $parts[] = rp_qty_text($secondary) . ' ' . trim($secondaryUnit);
    }

    return ($negative ? '-' : '') . implode(' + ', $parts);
}

function rp_item_quantity_text(
    float $primaryQty,
    float $secondaryQty,
    string $primaryUnit,
    string $secondaryUnit
): string {
    $primaryUnit = trim($primaryUnit) !== '' ? trim($primaryUnit) : 'Primary';
    $parts = [];

    if (abs($primaryQty) > 0.0004) {
        $parts[] = rp_qty_text($primaryQty) . ' ' . $primaryUnit;
    }

    if (trim($secondaryUnit) !== '' && abs($secondaryQty) > 0.0004) {
        $parts[] = rp_qty_text($secondaryQty) . ' ' . trim($secondaryUnit);
    }

    return $parts !== [] ? implode(' + ', $parts) : '0 ' . $primaryUnit;
}

function rp_customers(int $branchId): array
{
    if (!rp_table_exists('food_customers')) return [];
    $stmt = db()->prepare('SELECT id,customer_code,customer_name,status FROM food_customers WHERE branch_id=:branch_id ORDER BY customer_name,customer_code');
    $stmt->execute([':branch_id'=>$branchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rp_suppliers(int $branchId): array
{
    if (!rp_table_exists('food_suppliers')) return [];
    $stmt = db()->prepare('SELECT id,supplier_code,supplier_name,status FROM food_suppliers WHERE branch_id=:branch_id ORDER BY supplier_name,supplier_code');
    $stmt->execute([':branch_id'=>$branchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rp_products(int $branchId): array
{
    if (!rp_table_exists('food_products')) return [];
    $stmt = db()->prepare('SELECT id,product_code,product_name,status FROM food_products WHERE branch_id=:branch_id ORDER BY product_name,product_code');
    $stmt->execute([':branch_id'=>$branchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rp_expense_categories(int $companyId): array
{
    if (!rp_table_exists('expense_categories')) return [];
    $stmt = db()->prepare('SELECT id,category_code,category_name,status FROM expense_categories WHERE company_id=:company_id AND (module_code=:module_code OR module_code IS NULL) ORDER BY category_name,category_code');
    $stmt->execute([':company_id'=>$companyId,':module_code'=>RP_MODULE_CODE]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rp_accounts(int $branchId): array
{
    if (!rp_table_exists('accounts')) return [];
    $stmt = db()->prepare('SELECT id,account_code,account_name,account_type,opening_balance,opening_balance_date,status FROM accounts WHERE branch_id=:branch_id ORDER BY account_type,account_name');
    $stmt->execute([':branch_id'=>$branchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rp_common_options(array $ctx, array $access, string $report): array
{
    $branchId=(int)$ctx['branch_id'];$companyId=(int)$ctx['company_id'];
    $data=[
        'allowed_actions'=>$access['actions'],
        'branch'=>$ctx,
        'date_from'=>date('Y-m-01'),
        'date_to'=>date('Y-m-d'),
        'payment_statuses'=>[
            ['id'=>1,'label'=>'Unpaid'],['id'=>2,'label'=>'Partially Paid'],['id'=>3,'label'=>'Paid']
        ],
    ];
    if (in_array($report,['sales','customer_outstanding'],true)) $data['customers']=rp_customers($branchId);
    if (in_array($report,['purchase','supplier_outstanding'],true)) $data['suppliers']=rp_suppliers($branchId);
    if (in_array($report,['sales','purchase','stock'],true)) $data['products']=rp_products($branchId);
    if ($report==='expense') $data['categories']=rp_expense_categories($companyId);
    if ($report==='cash_bank') {
        $data['accounts']=rp_accounts($branchId);
        $data['transaction_types']=[
            ['value'=>'','label'=>'All Transactions'],
            ['value'=>'customer_payment','label'=>'Customer Payment'],
            ['value'=>'sales_receipt','label'=>'Sales Receipt'],
            ['value'=>'sales_refund','label'=>'Sales Return Refund'],
            ['value'=>'purchase_payment','label'=>'Purchase Payment'],
            ['value'=>'supplier_payment','label'=>'Supplier Payment'],
            ['value'=>'expense_payment','label'=>'Expense Payment'],
        ];
    }
    if (in_array($report,['customer_outstanding','supplier_outstanding'],true)) {
        $data['date_from']=rp_financial_year_start();
    }
    return $data;
}

function rp_summary_from_unique(array $rows, string $idKey, array $sumKeys): array
{
    $seen=[];$summary=['documents'=>0];
    foreach($sumKeys as $k) $summary[$k]=0.0;
    foreach($rows as $row){
        $id=(string)($row[$idKey]??'');
        if($id===''||isset($seen[$id])) continue;
        $seen[$id]=true;$summary['documents']++;
        foreach($sumKeys as $k) $summary[$k]=round($summary[$k]+(float)($row[$k]??0),2);
    }
    return $summary;
}

function rp_sales(array $ctx, ?int $taxMode): array
{
    foreach(['food_sales','food_sale_items','food_products'] as $t) {
        if(!rp_table_exists($t)) json_error('Sales Report schema is incomplete. Missing table: '.$t.'.',500);
    }

    $branchId=(int)$ctx['branch_id'];
    $from=rp_valid_date($_GET['date_from']??'',date('Y-m-01'),'From Date');
    $to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');
    if($to<$from) json_error('To Date cannot be earlier than From Date.',422);

    $customerId=max(0,(int)($_GET['customer_id']??0));
    $productId=max(0,(int)($_GET['product_id']??0));
    $paymentStatus=max(0,(int)($_GET['payment_status']??0));
    $posting=(int)($_GET['posting_status']??1);
    if(!in_array($posting,[-1,0,1],true)) $posting=1;

    /* seller_type_name remains the immutable invoice snapshot.
       seller_type_id is exposed when the upgraded schema is available. */
    $sellerTypeIdSelect = rp_column_exists('food_sale_items','seller_type_id')
        ? 'si.seller_type_id'
        : 'NULL AS seller_type_id';

    $sql="SELECT s.id AS sale_id,s.sales_no,s.invoice_date,s.due_date,s.customer_id,s.customer_reference,
                 COALESCE(c.customer_code,'') AS customer_code,COALESCE(s.customer_name_snapshot,c.customer_name,'Walk-in') AS customer_name,
                 COALESCE(s.customer_gstin_snapshot,c.gstin,'') AS customer_gstin,s.tax_mode,s.supply_type,
                 s.subtotal,s.discount_total,s.taxable_total,s.cgst_total,s.sgst_total,s.igst_total,s.cess_total,s.round_off,s.grand_total,s.paid_amount,s.balance_amount,s.payment_status,s.posting_status,s.created_at,
                 COALESCE(u.name,'') AS created_by_name,
                 si.id AS item_id,si.product_id,p.product_code,p.product_name,
                 COALESCE(cat.category_name,'') AS category_name,COALESCE(sub.category_name,'') AS subcategory_name,
                 COALESCE(src.batch_number,'') AS batch_number,
                 si.selected_unit_id,si.primary_unit_id,si.secondary_unit_id,si.conversion_rate,
                 si.primary_quantity,si.secondary_quantity,si.quantity,si.base_quantity,
                 $sellerTypeIdSelect,si.seller_type_name,si.unit_price,
                 si.discount_amount,si.overall_discount_share,si.taxable_amount AS item_taxable,si.gst_rate,
                 si.cgst_amount AS item_cgst,si.sgst_amount AS item_sgst,si.igst_amount AS item_igst,si.cess_amount AS item_cess,si.line_total,
                 COALESCE(pu.unit_symbol,pu.unit_name,'') AS primary_unit,
                 COALESCE(su.unit_symbol,su.unit_name,'') AS secondary_unit,
                 COALESCE(sel.unit_symbol,sel.unit_name,'') AS selected_unit
          FROM food_sales s
          INNER JOIN food_sale_items si ON si.sale_id=s.id AND si.branch_id=s.branch_id AND si.status=1
          INNER JOIN food_products p ON p.id=si.product_id AND p.branch_id=si.branch_id
          LEFT JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
          LEFT JOIN food_product_categories cat ON cat.id=p.category_id AND cat.branch_id=p.branch_id
          LEFT JOIN food_product_categories sub ON sub.id=p.subcategory_id AND sub.branch_id=p.branch_id
          LEFT JOIN food_purchases src ON src.id=si.source_purchase_id AND src.branch_id=si.branch_id
          LEFT JOIN food_units pu ON pu.id=si.primary_unit_id AND pu.branch_id=si.branch_id
          LEFT JOIN food_units su ON su.id=si.secondary_unit_id AND su.branch_id=si.branch_id
          LEFT JOIN food_units sel ON sel.id=si.selected_unit_id AND sel.branch_id=si.branch_id
          LEFT JOIN users u ON u.id=s.created_by
          WHERE s.branch_id=:branch_id AND s.document_type=4 AND s.status=1 AND s.reversed_at IS NULL
            AND s.invoice_date BETWEEN :date_from AND :date_to";

    $params=[':branch_id'=>$branchId,':date_from'=>$from,':date_to'=>$to];
    if($taxMode!==null){$sql.=' AND s.tax_mode=:tax_mode';$params[':tax_mode']=$taxMode;}
    if($customerId>0){$sql.=' AND s.customer_id=:customer_id';$params[':customer_id']=$customerId;}
    if($productId>0){$sql.=' AND si.product_id=:product_id';$params[':product_id']=$productId;}
    if($paymentStatus>0){$sql.=' AND s.payment_status=:payment_status';$params[':payment_status']=$paymentStatus;}
    if($posting>=0){$sql.=' AND s.posting_status=:posting_status';$params[':posting_status']=$posting;}

    $sql.=' ORDER BY s.invoice_date DESC,s.id DESC,si.id ASC';
    $stmt=db()->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];

    foreach($rows as &$r){
        foreach([
            'sale_id','customer_id','tax_mode','payment_status','posting_status',
            'item_id','product_id','selected_unit_id','primary_unit_id','secondary_unit_id','seller_type_id'
        ] as $k) {
            $r[$k]=isset($r[$k]) && $r[$k] !== null ? (int)$r[$k] : null;
        }

        foreach([
            'subtotal','discount_total','taxable_total','cgst_total','sgst_total','igst_total','cess_total','round_off',
            'grand_total','paid_amount','balance_amount','conversion_rate','primary_quantity','secondary_quantity',
            'quantity','base_quantity','unit_price','discount_amount','overall_discount_share','item_taxable',
            'gst_rate','item_cgst','item_sgst','item_igst','item_cess','line_total'
        ] as $k) $r[$k]=(float)($r[$k]??0);

        $conversion=(float)$r['conversion_rate'];
        $r['secondary_rate']=($r['secondary_unit_id'] && $conversion>0)
            ? round((float)$r['unit_price']/$conversion,6)
            : 0.0;
        $r['quantity_display']=rp_item_quantity_text(
            (float)$r['primary_quantity'],
            (float)$r['secondary_quantity'],
            (string)$r['primary_unit'],
            (string)$r['secondary_unit']
        );
        $r['base_quantity_display']=rp_mixed_quantity_text(
            (float)$r['base_quantity'],
            $r['secondary_unit_id'] ? (int)$r['secondary_unit_id'] : null,
            $conversion,
            (string)$r['primary_unit'],
            (string)$r['secondary_unit']
        );

        $r['tax_mode_label']=rp_tax_label((int)$r['tax_mode']);
        $r['payment_status_label']=rp_payment_status_label((int)$r['payment_status']);
        $r['posting_status_label']=(int)$r['posting_status']===1?'Posted':'Draft';
        $r['view_url']='sales.php?ref='.rawurlencode(encryptReference('sale',(int)$r['sale_id']));
    }
    unset($r);

    $summary=rp_summary_from_unique($rows,'sale_id',[
        'taxable_total','cgst_total','sgst_total','igst_total','cess_total','grand_total','paid_amount','balance_amount'
    ]);

    return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to,'tax_mode'=>$taxMode];
}

function rp_purchase(array $ctx, ?int $taxMode): array
{
    foreach(['food_purchases','food_purchase_items','food_products','food_suppliers'] as $t) {
        if(!rp_table_exists($t)) json_error('Purchase Report schema is incomplete. Missing table: '.$t.'.',500);
    }

    $branchId=(int)$ctx['branch_id'];
    $from=rp_valid_date($_GET['date_from']??'',date('Y-m-01'),'From Date');
    $to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');
    if($to<$from) json_error('To Date cannot be earlier than From Date.',422);

    $supplierId=max(0,(int)($_GET['supplier_id']??0));
    $productId=max(0,(int)($_GET['product_id']??0));
    $paymentStatus=max(0,(int)($_GET['payment_status']??0));
    $posting=(int)($_GET['posting_status']??1);
    if(!in_array($posting,[-1,0,1],true)) $posting=1;

    $sql="SELECT p.id AS purchase_id,p.purchase_no,p.batch_number,p.purchase_date,p.supplier_invoice_number,p.supplier_invoice_date,p.supplier_id,
                 s.supplier_code,s.supplier_name,s.gstin AS supplier_gstin,p.tax_mode,p.supply_type,
                 p.subtotal,p.discount_total,p.taxable_total,p.cgst_total,p.sgst_total,p.igst_total,p.cess_total,p.round_off,p.grand_total,p.paid_amount,p.balance_amount,p.payment_status,p.posting_status,p.created_at,
                 COALESCE(u.name,'') AS created_by_name,
                 pi.id AS item_id,pi.product_id,pr.product_code,pr.product_name,
                 COALESCE(cat.category_name,'') AS category_name,COALESCE(sub.category_name,'') AS subcategory_name,
                 pi.expiry_date,pi.selected_unit_id,pi.primary_unit_id,pi.secondary_unit_id,pi.conversion_rate,
                 pi.primary_quantity,pi.secondary_quantity,pi.quantity,pi.base_quantity,pi.free_quantity,pi.unit_price,
                 pi.discount_amount,pi.overall_discount_share,pi.taxable_amount AS item_taxable,pi.gst_rate,
                 pi.cgst_amount AS item_cgst,pi.sgst_amount AS item_sgst,pi.igst_amount AS item_igst,pi.cess_amount AS item_cess,pi.line_total,
                 COALESCE(pu.unit_symbol,pu.unit_name,'') AS primary_unit,
                 COALESCE(su.unit_symbol,su.unit_name,'') AS secondary_unit,
                 COALESCE(sel.unit_symbol,sel.unit_name,'') AS selected_unit
          FROM food_purchases p
          INNER JOIN food_purchase_items pi ON pi.purchase_id=p.id AND pi.branch_id=p.branch_id AND pi.status=1
          INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
          INNER JOIN food_products pr ON pr.id=pi.product_id AND pr.branch_id=pi.branch_id
          LEFT JOIN food_product_categories cat ON cat.id=pr.category_id AND cat.branch_id=pr.branch_id
          LEFT JOIN food_product_categories sub ON sub.id=pr.subcategory_id AND sub.branch_id=pr.branch_id
          LEFT JOIN food_units pu ON pu.id=pi.primary_unit_id AND pu.branch_id=pi.branch_id
          LEFT JOIN food_units su ON su.id=pi.secondary_unit_id AND su.branch_id=pi.branch_id
          LEFT JOIN food_units sel ON sel.id=pi.selected_unit_id AND sel.branch_id=pi.branch_id
          LEFT JOIN users u ON u.id=p.created_by
          WHERE p.branch_id=:branch_id AND p.status=1 AND p.reversed_at IS NULL
            AND p.purchase_date BETWEEN :date_from AND :date_to";

    $params=[':branch_id'=>$branchId,':date_from'=>$from,':date_to'=>$to];
    if($taxMode!==null){$sql.=' AND p.tax_mode=:tax_mode';$params[':tax_mode']=$taxMode;}
    if($supplierId>0){$sql.=' AND p.supplier_id=:supplier_id';$params[':supplier_id']=$supplierId;}
    if($productId>0){$sql.=' AND pi.product_id=:product_id';$params[':product_id']=$productId;}
    if($paymentStatus>0){$sql.=' AND p.payment_status=:payment_status';$params[':payment_status']=$paymentStatus;}
    if($posting>=0){$sql.=' AND p.posting_status=:posting_status';$params[':posting_status']=$posting;}

    $sql.=' ORDER BY p.purchase_date DESC,p.id DESC,pi.id ASC';
    $stmt=db()->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];

    foreach($rows as &$r){
        foreach([
            'purchase_id','supplier_id','tax_mode','payment_status','posting_status','item_id','product_id',
            'selected_unit_id','primary_unit_id','secondary_unit_id'
        ] as $k) {
            $r[$k]=isset($r[$k]) && $r[$k] !== null ? (int)$r[$k] : null;
        }

        foreach([
            'subtotal','discount_total','taxable_total','cgst_total','sgst_total','igst_total','cess_total','round_off',
            'grand_total','paid_amount','balance_amount','conversion_rate','primary_quantity','secondary_quantity','quantity',
            'base_quantity','free_quantity','unit_price','discount_amount','overall_discount_share','item_taxable',
            'gst_rate','item_cgst','item_sgst','item_igst','item_cess','line_total'
        ] as $k) $r[$k]=(float)($r[$k]??0);

        $conversion=(float)$r['conversion_rate'];
        $r['secondary_rate']=($r['secondary_unit_id'] && $conversion>0)
            ? round((float)$r['unit_price']/$conversion,6)
            : 0.0;
        $r['quantity_display']=rp_item_quantity_text(
            (float)$r['primary_quantity'],
            (float)$r['secondary_quantity'],
            (string)$r['primary_unit'],
            (string)$r['secondary_unit']
        );
        $r['base_quantity_display']=rp_mixed_quantity_text(
            (float)$r['base_quantity'],
            $r['secondary_unit_id'] ? (int)$r['secondary_unit_id'] : null,
            $conversion,
            (string)$r['primary_unit'],
            (string)$r['secondary_unit']
        );

        $r['tax_mode_label']=rp_tax_label((int)$r['tax_mode']);
        $r['payment_status_label']=rp_payment_status_label((int)$r['payment_status']);
        $r['posting_status_label']=(int)$r['posting_status']===1?'Posted':'Draft';
        $r['view_url']='purchase-form.php?ref='.rawurlencode(encryptReference('purchase',(int)$r['purchase_id']));
    }
    unset($r);

    $summary=rp_summary_from_unique($rows,'purchase_id',[
        'taxable_total','cgst_total','sgst_total','igst_total','cess_total','grand_total','paid_amount','balance_amount'
    ]);

    return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to,'tax_mode'=>$taxMode];
}

function rp_stock(array $ctx): array
{
    foreach(['food_stock_movements','food_products'] as $t) {
        if(!rp_table_exists($t)) json_error('Stock Detail Report schema is incomplete. Missing table: '.$t.'.',500);
    }

    $branchId=(int)$ctx['branch_id'];
    $from=rp_valid_date($_GET['date_from']??'',date('Y-m-01'),'From Date');
    $to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');
    if($to<$from) json_error('To Date cannot be earlier than From Date.',422);

    $productId=max(0,(int)($_GET['product_id']??0));
    $batch=trim((string)($_GET['batch']??''));

    $hasPurchaseSnapshot=rp_table_exists('food_purchase_items')&&rp_table_exists('food_purchases');
    $costJoin='';
    $costSelect='0 AS base_cost,NULL AS expiry_date,
                 pr.primary_unit_id AS snapshot_primary_unit_id,
                 pr.secondary_unit_id AS snapshot_secondary_unit_id,
                 CASE WHEN pr.secondary_unit_id IS NOT NULL THEN COALESCE(pr.secondary_conversion,0) ELSE 1 END AS snapshot_conversion_rate';

    if($hasPurchaseSnapshot){
        $costJoin="LEFT JOIN (
            SELECT pi.purchase_id,pi.product_id,
                   MAX(pi.expiry_date) AS expiry_date,
                   MAX(pi.primary_unit_id) AS primary_unit_id,
                   MAX(pi.secondary_unit_id) AS secondary_unit_id,
                   MAX(pi.conversion_rate) AS conversion_rate,
                   CASE WHEN SUM(pi.base_quantity)>0
                        THEN SUM(pi.taxable_amount)/SUM(pi.base_quantity)
                        ELSE 0 END AS base_cost
            FROM food_purchase_items pi
            INNER JOIN food_purchases hp
               ON hp.id=pi.purchase_id
              AND hp.branch_id=pi.branch_id
            WHERE pi.status=1
              AND hp.status=1
              AND hp.posting_status=1
              AND hp.reversed_at IS NULL
            GROUP BY pi.purchase_id,pi.product_id
        ) pc ON pc.purchase_id=sm.source_purchase_id AND pc.product_id=sm.product_id";

        $costSelect='COALESCE(pc.base_cost,0) AS base_cost,pc.expiry_date,
                     COALESCE(pc.primary_unit_id,pr.primary_unit_id) AS snapshot_primary_unit_id,
                     COALESCE(pc.secondary_unit_id,pr.secondary_unit_id) AS snapshot_secondary_unit_id,
                     CASE
                        WHEN pc.primary_unit_id IS NOT NULL AND pc.secondary_unit_id IS NOT NULL THEN pc.conversion_rate
                        WHEN pc.primary_unit_id IS NOT NULL THEN 1
                        WHEN pr.secondary_unit_id IS NOT NULL THEN COALESCE(pr.secondary_conversion,0)
                        ELSE 1
                     END AS snapshot_conversion_rate';
    }

    $unitJoin = $hasPurchaseSnapshot
        ? "LEFT JOIN food_units pu ON pu.id=COALESCE(pc.primary_unit_id,pr.primary_unit_id) AND pu.branch_id=sm.branch_id
           LEFT JOIN food_units su ON su.id=COALESCE(pc.secondary_unit_id,pr.secondary_unit_id) AND su.branch_id=sm.branch_id"
        : "LEFT JOIN food_units pu ON pu.id=pr.primary_unit_id AND pu.branch_id=sm.branch_id
           LEFT JOIN food_units su ON su.id=pr.secondary_unit_id AND su.branch_id=sm.branch_id";

    $sql="SELECT pr.id AS product_id,pr.product_code,pr.product_name,
                 COALESCE(cat.category_name,'') AS category_name,
                 COALESCE(sub.category_name,'') AS subcategory_name,
                 sm.source_purchase_id,
                 COALESCE(pur.batch_number,'No Batch / Adjustment') AS batch_number,
                 pur.purchase_date,
                 $costSelect,
                 COALESCE(pu.unit_symbol,pu.unit_name,'') AS primary_unit,
                 COALESCE(su.unit_symbol,su.unit_name,'') AS secondary_unit,
                 SUM(CASE WHEN sm.movement_date<:date_from THEN sm.quantity_in-sm.quantity_out ELSE 0 END) AS opening_qty,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from2 AND :date_to AND sm.movement_type=1 THEN sm.quantity_in ELSE 0 END) AS purchase_in,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from3 AND :date_to2 AND sm.movement_type=2 THEN sm.quantity_out ELSE 0 END) AS sale_out,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from4 AND :date_to3 AND sm.movement_type=3 THEN sm.quantity_out ELSE 0 END) AS purchase_return_out,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from5 AND :date_to4 AND sm.movement_type=4 THEN sm.quantity_in ELSE 0 END) AS sale_return_in,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from6 AND :date_to5 AND sm.movement_type=5 THEN sm.quantity_in ELSE 0 END) AS adjustment_in,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from7 AND :date_to6 AND sm.movement_type=5 THEN sm.quantity_out ELSE 0 END) AS adjustment_out,
                 SUM(CASE WHEN sm.movement_date<=:date_to7 THEN sm.quantity_in-sm.quantity_out ELSE 0 END) AS closing_qty,
                 SUM(CASE WHEN sm.movement_date BETWEEN :date_from8 AND :date_to8 THEN 1 ELSE 0 END) AS movement_count
          FROM food_stock_movements sm
          INNER JOIN food_products pr ON pr.id=sm.product_id AND pr.branch_id=sm.branch_id
          LEFT JOIN food_product_categories cat ON cat.id=pr.category_id AND cat.branch_id=pr.branch_id
          LEFT JOIN food_product_categories sub ON sub.id=pr.subcategory_id AND sub.branch_id=pr.branch_id
          LEFT JOIN food_purchases pur ON pur.id=sm.source_purchase_id AND pur.branch_id=sm.branch_id
          $costJoin
          $unitJoin
          WHERE sm.branch_id=:branch_id AND sm.status=1 AND sm.movement_date<=:date_to9";

    $params=[
        ':branch_id'=>$branchId,
        ':date_from'=>$from,':date_from2'=>$from,':date_from3'=>$from,':date_from4'=>$from,
        ':date_from5'=>$from,':date_from6'=>$from,':date_from7'=>$from,':date_from8'=>$from,
        ':date_to'=>$to,':date_to2'=>$to,':date_to3'=>$to,':date_to4'=>$to,
        ':date_to5'=>$to,':date_to6'=>$to,':date_to7'=>$to,':date_to8'=>$to,':date_to9'=>$to
    ];

    if($productId>0){
        $sql.=' AND sm.product_id=:product_id';
        $params[':product_id']=$productId;
    }
    if($batch!==''){
        $sql.=' AND COALESCE(pur.batch_number,\'No Batch / Adjustment\')=:batch';
        $params[':batch']=$batch;
    }

    $sql.=' GROUP BY pr.id,pr.product_code,pr.product_name,cat.category_name,sub.category_name,
                    sm.source_purchase_id,pur.batch_number,pur.purchase_date,
                    snapshot_primary_unit_id,snapshot_secondary_unit_id,snapshot_conversion_rate,
                    pu.unit_symbol,pu.unit_name,su.unit_symbol,su.unit_name';

    if($hasPurchaseSnapshot) $sql.=',pc.base_cost,pc.expiry_date';

    $sql.=' HAVING ABS(opening_qty)+ABS(purchase_in)+ABS(sale_out)+ABS(purchase_return_out)+
                  ABS(sale_return_in)+ABS(adjustment_in)+ABS(adjustment_out)+ABS(closing_qty)>0.0001
            ORDER BY pr.product_name,batch_number';

    $stmt=db()->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];

    $summary=[
        'batches'=>0,
        'opening_qty'=>0.0,
        'in_qty'=>0.0,
        'out_qty'=>0.0,
        'closing_qty'=>0.0,
        'closing_value'=>0.0,
        'quantity_note'=>'Quantity totals are raw base-unit totals. Use each row display value for mixed-unit stock.'
    ];
    $batches=[];

    foreach($rows as &$r){
        foreach([
            'product_id','source_purchase_id','movement_count',
            'snapshot_primary_unit_id','snapshot_secondary_unit_id'
        ] as $k) {
            $r[$k]=isset($r[$k]) && $r[$k] !== null ? (int)$r[$k] : null;
        }

        foreach([
            'base_cost','snapshot_conversion_rate','opening_qty','purchase_in','sale_out',
            'purchase_return_out','sale_return_in','adjustment_in','adjustment_out','closing_qty'
        ] as $k) $r[$k]=(float)($r[$k]??0);

        $conversion=(float)$r['snapshot_conversion_rate'];
        if(!$r['snapshot_secondary_unit_id']) $conversion=1.0;

        $r['stock_in']=round($r['purchase_in']+$r['sale_return_in']+$r['adjustment_in'],3);
        $r['stock_out']=round($r['sale_out']+$r['purchase_return_out']+$r['adjustment_out'],3);
        $r['opening_value']=round($r['opening_qty']*$r['base_cost'],2);
        $r['closing_value']=round($r['closing_qty']*$r['base_cost'],2);

        foreach([
            'opening_qty'=>'opening_qty_display',
            'purchase_in'=>'purchase_in_display',
            'sale_out'=>'sale_out_display',
            'purchase_return_out'=>'purchase_return_out_display',
            'sale_return_in'=>'sale_return_in_display',
            'adjustment_in'=>'adjustment_in_display',
            'adjustment_out'=>'adjustment_out_display',
            'stock_in'=>'stock_in_display',
            'stock_out'=>'stock_out_display',
            'closing_qty'=>'closing_qty_display'
        ] as $source=>$target) {
            $r[$target]=rp_mixed_quantity_text(
                (float)$r[$source],
                $r['snapshot_secondary_unit_id'] ? (int)$r['snapshot_secondary_unit_id'] : null,
                $conversion,
                (string)$r['primary_unit'],
                (string)$r['secondary_unit']
            );
        }

        $summary['opening_qty']+=$r['opening_qty'];
        $summary['in_qty']+=$r['stock_in'];
        $summary['out_qty']+=$r['stock_out'];
        $summary['closing_qty']+=$r['closing_qty'];
        $summary['closing_value']+=$r['closing_value'];

        if((int)$r['source_purchase_id']>0) {
            $r['view_url']='purchase-form.php?ref='.rawurlencode(
                encryptReference('purchase',(int)$r['source_purchase_id'])
            );
        } else {
            $r['view_url']='';
        }

        $batches[(string)$r['batch_number']]=true;
    }
    unset($r);

    $summary['batches']=count($batches);
    foreach(['opening_qty','in_qty','out_qty','closing_qty'] as $k) {
        $summary[$k]=round((float)$summary[$k],3);
    }
    $summary['closing_value']=round((float)$summary['closing_value'],2);

    return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to];
}

function rp_customer_outstanding(array $ctx): array
{
    foreach(['food_customers','food_sales'] as $t) if(!rp_table_exists($t)) json_error('Customer Outstanding schema is incomplete. Missing table: '.$t.'.',500);
    $branchId=(int)$ctx['branch_id'];$from=rp_valid_date($_GET['date_from']??'',rp_financial_year_start(),'From Date');$to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');if($to<$from)json_error('To Date cannot be earlier than From Date.',422);
    $customerId=max(0,(int)($_GET['customer_id']??0));$only=(int)($_GET['outstanding_only']??1)===1;
    $returnJoin='LEFT JOIN (SELECT sale_id,SUM(grand_total) AS return_total FROM food_sale_returns WHERE branch_id=:ret_branch AND posting_status=1 AND status=1 AND reversed_at IS NULL GROUP BY sale_id) sr ON sr.sale_id=s.id';
    $allocJoin='';
    if(rp_table_exists('food_customer_payment_allocations')&&rp_table_exists('food_customer_payments')){
        $allocJoin="LEFT JOIN (
            SELECT a.sale_id,SUM(a.allocated_amount) AS allocated_amount,SUM(a.credit_amount) AS credit_amount,SUM(a.discount_amount) AS discount_amount
            FROM food_customer_payment_allocations a INNER JOIN food_customer_payments cp ON cp.id=a.customer_payment_id AND cp.branch_id=a.branch_id
            WHERE a.branch_id=:alloc_branch AND a.status=1 AND a.allocation_type=1 AND cp.posting_status=1 AND cp.status=1 AND cp.reversed_at IS NULL GROUP BY a.sale_id
        ) pa ON pa.sale_id=s.id";
    }
    $sql="SELECT s.id AS sale_id,s.customer_id,c.customer_code,c.customer_name,c.mobile,s.sales_no,s.invoice_date,s.due_date,s.grand_total,
                 COALESCE(sr.return_total,0) AS return_total,".(($allocJoin!=='')?"COALESCE(pa.allocated_amount,0) AS allocated_amount,COALESCE(pa.credit_amount,0) AS credit_amount,COALESCE(pa.discount_amount,0) AS settlement_discount,":"0 AS allocated_amount,0 AS credit_amount,0 AS settlement_discount,")."
                 s.paid_amount,s.balance_amount,s.payment_status,
                 CASE WHEN s.balance_amount>0 THEN GREATEST(DATEDIFF(:as_of,COALESCE(s.due_date,s.invoice_date)),0) ELSE 0 END AS days_due,
                 COALESCE(u.name,'') AS created_by_name,s.created_at
          FROM food_sales s INNER JOIN food_customers c ON c.id=s.customer_id AND c.branch_id=s.branch_id
          $returnJoin $allocJoin LEFT JOIN users u ON u.id=s.created_by
          WHERE s.branch_id=:branch_id AND s.document_type=4 AND s.posting_status=1 AND s.status=1 AND s.reversed_at IS NULL AND s.invoice_date BETWEEN :date_from AND :date_to";
    $params=[':branch_id'=>$branchId,':ret_branch'=>$branchId,':date_from'=>$from,':date_to'=>$to,':as_of'=>$to];if($allocJoin!=='')$params[':alloc_branch']=$branchId;
    if($customerId>0){$sql.=' AND s.customer_id=:customer_id';$params[':customer_id']=$customerId;}if($only)$sql.=' AND s.balance_amount>0.005';$sql.=' ORDER BY s.invoice_date,c.customer_name,s.id';
    $stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $openingAlloc=[];
    if(rp_table_exists('food_customer_payment_allocations')&&rp_table_exists('food_customer_payments')){
        $st=db()->prepare('SELECT cp.customer_id,SUM(a.allocated_amount+a.credit_amount+a.discount_amount) AS amount FROM food_customer_payment_allocations a INNER JOIN food_customer_payments cp ON cp.id=a.customer_payment_id AND cp.branch_id=a.branch_id WHERE a.branch_id=:branch_id AND a.status=1 AND a.allocation_type=2 AND cp.posting_status=1 AND cp.status=1 AND cp.reversed_at IS NULL GROUP BY cp.customer_id');$st->execute([':branch_id'=>$branchId]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x)$openingAlloc[(int)$x['customer_id']]=(float)$x['amount'];
    }
    $cs=db()->prepare('SELECT id,customer_code,customer_name,mobile,opening_balance,opening_balance_date,created_at FROM food_customers WHERE branch_id=:branch_id'.($customerId>0?' AND id=:customer_id':'').' ORDER BY customer_name');$cp=[':branch_id'=>$branchId];if($customerId>0)$cp[':customer_id']=$customerId;$cs->execute($cp);
    foreach($cs->fetchAll(PDO::FETCH_ASSOC) as $c){$opening=(float)$c['opening_balance'];if($opening<=0.005)continue;$date=(string)($c['opening_balance_date']?:substr((string)$c['created_at'],0,10));if($date>$to)continue;$paid=$openingAlloc[(int)$c['id']]??0.0;$bal=max(0,round($opening-$paid,2));if($only&&$bal<=0.005)continue;$rows[]=['sale_id'=>0,'customer_id'=>(int)$c['id'],'customer_code'=>$c['customer_code'],'customer_name'=>$c['customer_name'],'mobile'=>$c['mobile'],'sales_no'=>'Opening Balance','invoice_date'=>$date,'due_date'=>$date,'grand_total'=>$opening,'return_total'=>0.0,'allocated_amount'=>$paid,'credit_amount'=>0.0,'settlement_discount'=>0.0,'paid_amount'=>$paid,'balance_amount'=>$bal,'payment_status'=>$bal<=0.005?3:($paid>0?2:1),'days_due'=>$bal>0?max(0,(int)((strtotime($to)-strtotime($date))/86400)):0,'created_by_name'=>'','created_at'=>$c['created_at'],'view_url'=>'customer-ledger.php?customer='.rawurlencode(encryptReference('customer',(int)$c['id']))];}
    $summary=['documents'=>count($rows),'invoice_total'=>0.0,'returns'=>0.0,'paid'=>0.0,'outstanding'=>0.0];foreach($rows as &$r){foreach(['sale_id','customer_id','payment_status','days_due'] as $k)$r[$k]=(int)($r[$k]??0);foreach(['grand_total','return_total','allocated_amount','credit_amount','settlement_discount','paid_amount','balance_amount'] as $k)$r[$k]=(float)($r[$k]??0);$r['payment_status_label']=rp_payment_status_label((int)$r['payment_status']);if(!isset($r['view_url']))$r['view_url']='sales.php?ref='.rawurlencode(encryptReference('sale',(int)$r['sale_id']));$summary['invoice_total']+=$r['grand_total'];$summary['returns']+=$r['return_total'];$summary['paid']+=$r['paid_amount'];$summary['outstanding']+=$r['balance_amount'];}unset($r);foreach(['invoice_total','returns','paid','outstanding'] as $k)$summary[$k]=round($summary[$k],2);
    usort($rows,static fn($a,$b)=>strcmp((string)$a['invoice_date'],(string)$b['invoice_date'])?:strcmp((string)$a['customer_name'],(string)$b['customer_name']));
    return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to];
}

function rp_supplier_outstanding(array $ctx): array
{
    foreach(['food_suppliers','food_purchases'] as $t) if(!rp_table_exists($t)) json_error('Supplier Outstanding schema is incomplete. Missing table: '.$t.'.',500);
    $branchId=(int)$ctx['branch_id'];$from=rp_valid_date($_GET['date_from']??'',rp_financial_year_start(),'From Date');$to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');if($to<$from)json_error('To Date cannot be earlier than From Date.',422);$supplierId=max(0,(int)($_GET['supplier_id']??0));$only=(int)($_GET['outstanding_only']??1)===1;
    $returnJoin=rp_table_exists('food_purchase_returns')?'LEFT JOIN (SELECT purchase_id,SUM(grand_total) AS return_total FROM food_purchase_returns WHERE branch_id=:ret_branch AND posting_status=1 AND status=1 AND reversed_at IS NULL GROUP BY purchase_id) prt ON prt.purchase_id=p.id':'LEFT JOIN (SELECT NULL AS purchase_id,0 AS return_total) prt ON 1=0';
    $directJoin=rp_table_exists('food_purchase_payments')?'LEFT JOIN (SELECT purchase_id,SUM(amount) AS direct_paid FROM food_purchase_payments WHERE branch_id=:direct_branch AND status=1 GROUP BY purchase_id) pp ON pp.purchase_id=p.id':'LEFT JOIN (SELECT NULL AS purchase_id,0 AS direct_paid) pp ON 1=0';
    $allocJoin='';
    if(rp_table_exists('food_supplier_payment_allocations')&&rp_table_exists('food_supplier_payments')){
        $typeCond=rp_column_exists('food_supplier_payment_allocations','allocation_type')?' AND a.allocation_type=1':'';
        $allocJoin="LEFT JOIN (SELECT a.purchase_id,SUM(a.allocated_amount) AS supplier_paid FROM food_supplier_payment_allocations a INNER JOIN food_supplier_payments sp ON sp.id=a.supplier_payment_id AND sp.branch_id=a.branch_id WHERE a.branch_id=:alloc_branch AND a.status=1 $typeCond AND sp.posting_status=1 AND sp.status=1 AND sp.reversed_at IS NULL GROUP BY a.purchase_id) spa ON spa.purchase_id=p.id";
    }
    $sql="SELECT p.id AS purchase_id,p.supplier_id,s.supplier_code,s.supplier_name,s.mobile,p.purchase_no,p.batch_number,p.supplier_invoice_number,p.purchase_date,p.grand_total,COALESCE(prt.return_total,0) AS return_total,COALESCE(pp.direct_paid,0) AS direct_paid,".(($allocJoin!=='')?'COALESCE(spa.supplier_paid,0) AS supplier_paid,':'0 AS supplier_paid,')."p.paid_amount,p.balance_amount,p.payment_status,GREATEST(DATEDIFF(:as_of,p.purchase_date),0) AS days_outstanding,COALESCE(u.name,'') AS created_by_name,p.created_at FROM food_purchases p INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id $returnJoin $directJoin $allocJoin LEFT JOIN users u ON u.id=p.created_by WHERE p.branch_id=:branch_id AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL AND p.purchase_date BETWEEN :date_from AND :date_to";
    $params=[':branch_id'=>$branchId,':date_from'=>$from,':date_to'=>$to,':as_of'=>$to];if(rp_table_exists('food_purchase_returns'))$params[':ret_branch']=$branchId;if(rp_table_exists('food_purchase_payments'))$params[':direct_branch']=$branchId;if($allocJoin!=='')$params[':alloc_branch']=$branchId;if($supplierId>0){$sql.=' AND p.supplier_id=:supplier_id';$params[':supplier_id']=$supplierId;}if($only)$sql.=' AND p.balance_amount>0.005';$sql.=' ORDER BY p.purchase_date,s.supplier_name,p.id';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $openingAlloc=[];if(rp_table_exists('food_supplier_payment_allocations')&&rp_table_exists('food_supplier_payments')&&rp_column_exists('food_supplier_payment_allocations','allocation_type')){$st=db()->prepare('SELECT sp.supplier_id,SUM(a.allocated_amount) AS amount FROM food_supplier_payment_allocations a INNER JOIN food_supplier_payments sp ON sp.id=a.supplier_payment_id AND sp.branch_id=a.branch_id WHERE a.branch_id=:branch_id AND a.status=1 AND a.allocation_type=2 AND sp.posting_status=1 AND sp.status=1 AND sp.reversed_at IS NULL GROUP BY sp.supplier_id');$st->execute([':branch_id'=>$branchId]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x)$openingAlloc[(int)$x['supplier_id']]=(float)$x['amount'];}
    $dateExpr=rp_column_exists('food_suppliers','opening_balance_date')?'opening_balance_date':'NULL';$ss=db()->prepare("SELECT id,supplier_code,supplier_name,mobile,opening_balance,$dateExpr AS opening_balance_date,created_at FROM food_suppliers WHERE branch_id=:branch_id".($supplierId>0?' AND id=:supplier_id':'').' ORDER BY supplier_name');$sp=[':branch_id'=>$branchId];if($supplierId>0)$sp[':supplier_id']=$supplierId;$ss->execute($sp);foreach($ss->fetchAll(PDO::FETCH_ASSOC) as $s){$opening=(float)$s['opening_balance'];if($opening<=0.005)continue;$date=(string)($s['opening_balance_date']?:substr((string)$s['created_at'],0,10));if($date>$to)continue;$paid=$openingAlloc[(int)$s['id']]??0.0;$bal=max(0,round($opening-$paid,2));if($only&&$bal<=0.005)continue;$rows[]=['purchase_id'=>0,'supplier_id'=>(int)$s['id'],'supplier_code'=>$s['supplier_code'],'supplier_name'=>$s['supplier_name'],'mobile'=>$s['mobile'],'purchase_no'=>'Opening Balance','batch_number'=>'','supplier_invoice_number'=>'','purchase_date'=>$date,'grand_total'=>$opening,'return_total'=>0.0,'direct_paid'=>0.0,'supplier_paid'=>$paid,'paid_amount'=>$paid,'balance_amount'=>$bal,'payment_status'=>$bal<=0.005?3:($paid>0?2:1),'days_outstanding'=>$bal>0?max(0,(int)((strtotime($to)-strtotime($date))/86400)):0,'created_by_name'=>'','created_at'=>$s['created_at'],'view_url'=>'supplier-ledger.php?supplier='.rawurlencode(encryptReference('supplier',(int)$s['id']))];}
    $summary=['documents'=>count($rows),'purchase_total'=>0.0,'returns'=>0.0,'paid'=>0.0,'outstanding'=>0.0];foreach($rows as &$r){foreach(['purchase_id','supplier_id','payment_status','days_outstanding'] as $k)$r[$k]=(int)($r[$k]??0);foreach(['grand_total','return_total','direct_paid','supplier_paid','paid_amount','balance_amount'] as $k)$r[$k]=(float)($r[$k]??0);$r['payment_status_label']=rp_payment_status_label((int)$r['payment_status']);if(!isset($r['view_url']))$r['view_url']='purchase-form.php?ref='.rawurlencode(encryptReference('purchase',(int)$r['purchase_id']));$summary['purchase_total']+=$r['grand_total'];$summary['returns']+=$r['return_total'];$summary['paid']+=$r['paid_amount'];$summary['outstanding']+=$r['balance_amount'];}unset($r);foreach(['purchase_total','returns','paid','outstanding'] as $k)$summary[$k]=round($summary[$k],2);usort($rows,static fn($a,$b)=>strcmp((string)$a['purchase_date'],(string)$b['purchase_date'])?:strcmp((string)$a['supplier_name'],(string)$b['supplier_name']));return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to];
}

function rp_expense(array $ctx, ?int $taxMode): array
{
    foreach(['expenses','expense_categories'] as $t) if(!rp_table_exists($t)) json_error('Expense Report schema is incomplete. Missing table: '.$t.'.',500);
    $branchId=(int)$ctx['branch_id'];$from=rp_valid_date($_GET['date_from']??'',date('Y-m-01'),'From Date');$to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');if($to<$from)json_error('To Date cannot be earlier than From Date.',422);$categoryId=max(0,(int)($_GET['category_id']??0));$paymentStatus=max(0,(int)($_GET['payment_status']??0));$posting=(int)($_GET['posting_status']??1);if(!in_array($posting,[-1,0,1],true))$posting=1;
    $hasNew=rp_column_exists('expenses','expense_no')&&rp_column_exists('expenses','paid_amount')&&rp_column_exists('expenses','balance_amount')&&rp_column_exists('expenses','payment_status');
    $paymentJoin='';$paymentSelect="'' AS payment_details,'' AS last_payment_date";
    if(rp_table_exists('food_expense_payments')&&rp_table_exists('food_expense_payment_details')&&rp_table_exists('accounts')){$paymentJoin="LEFT JOIN (SELECT ep.expense_id,MAX(ep.payment_date) AS last_payment_date,GROUP_CONCAT(DISTINCT CONCAT(CASE d.payment_mode WHEN 1 THEN 'Cash' WHEN 2 THEN 'UPI' WHEN 3 THEN 'Bank Transfer' WHEN 4 THEN 'Cheque' ELSE 'Payment' END,' · ',a.account_name,' · ',FORMAT(d.amount,2)) ORDER BY d.id SEPARATOR ' | ') AS payment_details FROM food_expense_payments ep INNER JOIN food_expense_payment_details d ON d.expense_payment_id=ep.id AND d.branch_id=ep.branch_id AND d.status=1 INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id WHERE ep.branch_id=:pay_branch AND ep.posting_status=1 AND ep.status=1 AND ep.reversed_at IS NULL GROUP BY ep.expense_id) pay ON pay.expense_id=e.id";$paymentSelect="COALESCE(pay.payment_details,'') AS payment_details,COALESCE(pay.last_payment_date,'') AS last_payment_date";}
    $selectNew=$hasNew?"e.expense_no,e.invoice_no,e.invoice_date,e.payee_gstin,e.payee_state_code,e.gst_rate,e.paid_amount,e.balance_amount,e.payment_status,":"CONCAT('EXP',LPAD(e.id,4,'0')) AS expense_no,'' AS invoice_no,NULL AS invoice_date,'' AS payee_gstin,'' AS payee_state_code,0 AS gst_rate,0 AS paid_amount,e.total_amount AS balance_amount,1 AS payment_status,";
    $sql="SELECT e.id AS expense_id,$selectNew e.expense_date,e.payee_name,e.description,e.tax_mode,e.taxable_amount,e.cgst_amount,e.sgst_amount,e.igst_amount,e.total_amount,e.posting_status,e.created_at,ec.category_code,ec.category_name,COALESCE(u.name,'') AS created_by_name,$paymentSelect FROM expenses e INNER JOIN expense_categories ec ON ec.id=e.expense_category_id LEFT JOIN users u ON u.id=e.created_by $paymentJoin WHERE e.branch_id=:branch_id AND e.status=1 AND e.reversed_at IS NULL AND e.expense_date BETWEEN :date_from AND :date_to AND (e.module_code=:module_code OR e.module_code IS NULL)";
    $params=[':branch_id'=>$branchId,':date_from'=>$from,':date_to'=>$to,':module_code'=>RP_MODULE_CODE];if($paymentJoin!=='')$params[':pay_branch']=$branchId;if($taxMode!==null){$sql.=' AND e.tax_mode=:tax_mode';$params[':tax_mode']=$taxMode;}if($categoryId>0){$sql.=' AND e.expense_category_id=:category_id';$params[':category_id']=$categoryId;}if($hasNew&&$paymentStatus>0){$sql.=' AND e.payment_status=:payment_status';$params[':payment_status']=$paymentStatus;}if($posting>=0){$sql.=' AND e.posting_status=:posting_status';$params[':posting_status']=$posting;}$sql.=' ORDER BY e.expense_date DESC,e.id DESC';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $summary=['expenses'=>count($rows),'taxable'=>0.0,'cgst'=>0.0,'sgst'=>0.0,'igst'=>0.0,'total'=>0.0,'paid'=>0.0,'balance'=>0.0];foreach($rows as &$r){foreach(['expense_id','tax_mode','payment_status','posting_status'] as $k)$r[$k]=(int)($r[$k]??0);foreach(['gst_rate','taxable_amount','cgst_amount','sgst_amount','igst_amount','total_amount','paid_amount','balance_amount'] as $k)$r[$k]=(float)($r[$k]??0);$r['tax_mode_label']=rp_tax_label((int)$r['tax_mode']);$r['payment_status_label']=rp_payment_status_label((int)$r['payment_status']);$r['posting_status_label']=(int)$r['posting_status']===1?'Posted':'Draft';$r['view_url']='expense-form.php?ref='.rawurlencode(encryptReference('expense',(int)$r['expense_id']));$summary['taxable']+=$r['taxable_amount'];$summary['cgst']+=$r['cgst_amount'];$summary['sgst']+=$r['sgst_amount'];$summary['igst']+=$r['igst_amount'];$summary['total']+=$r['total_amount'];$summary['paid']+=$r['paid_amount'];$summary['balance']+=$r['balance_amount'];}unset($r);foreach(['taxable','cgst','sgst','igst','total','paid','balance'] as $k)$summary[$k]=round($summary[$k],2);return ['rows'=>$rows,'summary'=>$summary,'date_from'=>$from,'date_to'=>$to,'tax_mode'=>$taxMode];
}

function rp_cb_add(array &$rows,array $r): void{$r['money_in']=round((float)($r['money_in']??0),2);$r['money_out']=round((float)($r['money_out']??0),2);$r['account_id']=(int)($r['account_id']??0);$r['sort_key']=(string)$r['business_date'].' '.substr((string)($r['created_at']??''),11,8).' '.str_pad((string)($r['source_id']??0),12,'0',STR_PAD_LEFT);$rows[]=$r;}

function rp_cb_collect(int $branchId,int $accountId,string $from,string $to): array
{
    $rows=[];
    if(rp_table_exists('food_customer_payment_details')&&rp_table_exists('food_customer_payments')){$sql="SELECT d.id source_id,d.account_id,d.amount,d.created_at,cp.id payment_id,cp.payment_no,cp.payment_date,cp.source_sale_id,c.customer_code,c.customer_name,a.account_code,a.account_name,pm.method_name,d.payment_reference,d.cheque_no,d.cheque_date FROM food_customer_payment_details d INNER JOIN food_customer_payments cp ON cp.id=d.customer_payment_id AND cp.branch_id=d.branch_id LEFT JOIN food_customers c ON c.id=cp.customer_id AND c.branch_id=cp.branch_id INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id LEFT JOIN food_payment_methods pm ON pm.id=d.payment_method_id AND pm.branch_id=d.branch_id WHERE d.branch_id=:branch_id AND d.status=1 AND cp.posting_status=1 AND cp.status=1 AND cp.reversed_at IS NULL AND cp.payment_date BETWEEN :f AND :t";$p=[':branch_id'=>$branchId,':f'=>$from,':t'=>$to];if($accountId>0){$sql.=' AND d.account_id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){$isSale=(int)($x['source_sale_id']??0)>0;$part=trim((string)($x['customer_code']??''));$part.=($part!==''?' - ':'').(string)($x['customer_name']??'');rp_cb_add($rows,['source_id'=>(int)$x['source_id'],'business_date'=>$x['payment_date'],'created_at'=>$x['created_at'],'reference'=>$x['payment_no']?:('CP#'.$x['payment_id']),'transaction_type'=>$isSale?'sales_receipt':'customer_payment','transaction'=>$isSale?'Sales Receipt':'Customer Payment','particular'=>$part,'account_id'=>$x['account_id'],'account'=>trim($x['account_code'])!==''?$x['account_code'].' - '.$x['account_name']:$x['account_name'],'mode'=>$x['method_name']?:'Receipt','payment_reference'=>$x['payment_reference']??'','cheque_no'=>$x['cheque_no']??'','cheque_date'=>$x['cheque_date']??'','money_in'=>$x['amount'],'money_out'=>0,'view_url'=>$isSale?'sales.php?ref='.rawurlencode(encryptReference('sale',(int)$x['source_sale_id'])):'customer-payment.php?ref='.rawurlencode(encryptReference('customer_payment',(int)$x['payment_id'])).'&view=1']);}}
    if(rp_table_exists('food_sales_return_refunds')){$sql="SELECT rf.id source_id,rf.account_id,rf.amount,rf.refund_date,rf.created_at,rf.sale_return_id,sr.return_no,c.customer_code,c.customer_name,a.account_code,a.account_name,pm.method_name,rf.payment_reference,rf.cheque_no,rf.cheque_date FROM food_sales_return_refunds rf INNER JOIN food_sale_returns sr ON sr.id=rf.sale_return_id AND sr.branch_id=rf.branch_id LEFT JOIN food_customers c ON c.id=rf.customer_id AND c.branch_id=rf.branch_id INNER JOIN accounts a ON a.id=rf.account_id AND a.branch_id=rf.branch_id LEFT JOIN food_payment_methods pm ON pm.id=rf.payment_method_id AND pm.branch_id=rf.branch_id WHERE rf.branch_id=:branch_id AND rf.status=1 AND rf.reversed_at IS NULL AND sr.posting_status=1 AND sr.status=1 AND sr.reversed_at IS NULL AND rf.refund_date BETWEEN :f AND :t";$p=[':branch_id'=>$branchId,':f'=>$from,':t'=>$to];if($accountId>0){$sql.=' AND rf.account_id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){$part=trim((string)($x['customer_code']??''));$part.=($part!==''?' - ':'').(string)($x['customer_name']??'');rp_cb_add($rows,['source_id'=>(int)$x['source_id'],'business_date'=>$x['refund_date'],'created_at'=>$x['created_at'],'reference'=>$x['return_no']?:('Return#'.$x['sale_return_id']),'transaction_type'=>'sales_refund','transaction'=>'Sales Return Refund','particular'=>$part,'account_id'=>$x['account_id'],'account'=>trim($x['account_code'])!==''?$x['account_code'].' - '.$x['account_name']:$x['account_name'],'mode'=>$x['method_name']?:'Refund','payment_reference'=>$x['payment_reference']??'','cheque_no'=>$x['cheque_no']??'','cheque_date'=>$x['cheque_date']??'','money_in'=>0,'money_out'=>$x['amount'],'view_url'=>'sales-return.php?ref='.rawurlencode(encryptReference('sale_return',(int)$x['sale_return_id']))]);}}
    if(rp_table_exists('food_purchase_payments')){$sql="SELECT pp.id source_id,pp.account_id,pp.payment_mode,pp.amount,pp.reference_no,pp.cheque_no,pp.cheque_date,pp.created_at,p.id purchase_id,p.purchase_no,p.purchase_date,s.supplier_code,s.supplier_name,a.account_code,a.account_name FROM food_purchase_payments pp INNER JOIN food_purchases p ON p.id=pp.purchase_id AND p.branch_id=pp.branch_id INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id INNER JOIN accounts a ON a.id=pp.account_id AND a.branch_id=pp.branch_id WHERE pp.branch_id=:branch_id AND pp.status=1 AND p.posting_status=1 AND p.status=1 AND p.reversed_at IS NULL AND p.purchase_date BETWEEN :f AND :t";$p=[':branch_id'=>$branchId,':f'=>$from,':t'=>$to];if($accountId>0){$sql.=' AND pp.account_id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){rp_cb_add($rows,['source_id'=>(int)$x['source_id'],'business_date'=>$x['purchase_date'],'created_at'=>$x['created_at'],'reference'=>$x['purchase_no'],'transaction_type'=>'purchase_payment','transaction'=>'Purchase Payment','particular'=>$x['supplier_code'].' - '.$x['supplier_name'],'account_id'=>$x['account_id'],'account'=>trim($x['account_code'])!==''?$x['account_code'].' - '.$x['account_name']:$x['account_name'],'mode'=>rp_mode_label((int)$x['payment_mode']),'payment_reference'=>$x['reference_no']??'','cheque_no'=>$x['cheque_no']??'','cheque_date'=>$x['cheque_date']??'','money_in'=>0,'money_out'=>$x['amount'],'view_url'=>'purchase-form.php?ref='.rawurlencode(encryptReference('purchase',(int)$x['purchase_id']))]);}}
    if(rp_table_exists('food_supplier_payment_details')&&rp_table_exists('food_supplier_payments')){$sql="SELECT d.id source_id,d.account_id,d.payment_mode,d.amount,d.reference_no,d.cheque_no,d.cheque_date,d.created_at,sp.id payment_id,sp.payment_no,sp.payment_date,s.supplier_code,s.supplier_name,a.account_code,a.account_name FROM food_supplier_payment_details d INNER JOIN food_supplier_payments sp ON sp.id=d.supplier_payment_id AND sp.branch_id=d.branch_id INNER JOIN food_suppliers s ON s.id=sp.supplier_id AND s.branch_id=sp.branch_id INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id WHERE d.branch_id=:branch_id AND d.status=1 AND sp.posting_status=1 AND sp.status=1 AND sp.reversed_at IS NULL AND sp.payment_date BETWEEN :f AND :t";$p=[':branch_id'=>$branchId,':f'=>$from,':t'=>$to];if($accountId>0){$sql.=' AND d.account_id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){rp_cb_add($rows,['source_id'=>(int)$x['source_id'],'business_date'=>$x['payment_date'],'created_at'=>$x['created_at'],'reference'=>$x['payment_no'],'transaction_type'=>'supplier_payment','transaction'=>'Supplier Payment','particular'=>$x['supplier_code'].' - '.$x['supplier_name'],'account_id'=>$x['account_id'],'account'=>trim($x['account_code'])!==''?$x['account_code'].' - '.$x['account_name']:$x['account_name'],'mode'=>rp_mode_label((int)$x['payment_mode']),'payment_reference'=>$x['reference_no']??'','cheque_no'=>$x['cheque_no']??'','cheque_date'=>$x['cheque_date']??'','money_in'=>0,'money_out'=>$x['amount'],'view_url'=>'supplier-payment.php?ref='.rawurlencode(encryptReference('supplier_payment',(int)$x['payment_id'])).'&view=1']);}}
    if(rp_table_exists('food_expense_payment_details')&&rp_table_exists('food_expense_payments')){$sql="SELECT d.id source_id,d.account_id,d.payment_mode,d.amount,d.reference_no,d.cheque_no,d.cheque_date,d.created_at,ep.id payment_id,ep.payment_no,ep.payment_date,e.expense_no,e.payee_name,ec.category_name,a.account_code,a.account_name FROM food_expense_payment_details d INNER JOIN food_expense_payments ep ON ep.id=d.expense_payment_id AND ep.branch_id=d.branch_id INNER JOIN expenses e ON e.id=ep.expense_id AND e.branch_id=ep.branch_id INNER JOIN expense_categories ec ON ec.id=e.expense_category_id INNER JOIN accounts a ON a.id=d.account_id AND a.branch_id=d.branch_id WHERE d.branch_id=:branch_id AND d.status=1 AND ep.posting_status=1 AND ep.status=1 AND ep.reversed_at IS NULL AND e.posting_status=1 AND e.status=1 AND e.reversed_at IS NULL AND ep.payment_date BETWEEN :f AND :t";$p=[':branch_id'=>$branchId,':f'=>$from,':t'=>$to];if($accountId>0){$sql.=' AND d.account_id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){rp_cb_add($rows,['source_id'=>(int)$x['source_id'],'business_date'=>$x['payment_date'],'created_at'=>$x['created_at'],'reference'=>$x['payment_no'],'transaction_type'=>'expense_payment','transaction'=>'Expense Payment','particular'=>$x['category_name'].' · '.$x['payee_name'].' · '.$x['expense_no'],'account_id'=>$x['account_id'],'account'=>trim($x['account_code'])!==''?$x['account_code'].' - '.$x['account_name']:$x['account_name'],'mode'=>rp_mode_label((int)$x['payment_mode']),'payment_reference'=>$x['reference_no']??'','cheque_no'=>$x['cheque_no']??'','cheque_date'=>$x['cheque_date']??'','money_in'=>0,'money_out'=>$x['amount'],'view_url'=>'expense-payment.php?ref='.rawurlencode(encryptReference('expense_payment',(int)$x['payment_id'])).'&view=1']);}}
    usort($rows,static fn($a,$b)=>strcmp((string)$a['sort_key'],(string)$b['sort_key']));return $rows;
}

function rp_cb_opening(int $branchId,int $accountId,string $from): float
{
    $sql='SELECT COALESCE(SUM(opening_balance),0) FROM accounts WHERE branch_id=:branch_id AND COALESCE(opening_balance_date,DATE(created_at))<=:f';$p=[':branch_id'=>$branchId,':f'=>$from];if($accountId>0){$sql.=' AND id=:a';$p[':a']=$accountId;}$st=db()->prepare($sql);$st->execute($p);$opening=(float)$st->fetchColumn();
    $prior=rp_cb_collect($branchId,$accountId,'1900-01-01',date('Y-m-d',strtotime($from.' -1 day')));foreach($prior as $r)$opening+=(float)$r['money_in']-(float)$r['money_out'];return round($opening,2);
}

function rp_cash_bank(array $ctx): array
{
    if(!rp_table_exists('accounts'))json_error('Cash / Bank Report schema is incomplete. Missing accounts.',500);$branchId=(int)$ctx['branch_id'];$from=rp_valid_date($_GET['date_from']??'',date('Y-m-01'),'From Date');$to=rp_valid_date($_GET['date_to']??'',date('Y-m-d'),'To Date');if($to<$from)json_error('To Date cannot be earlier than From Date.',422);$accountId=max(0,(int)($_GET['account_id']??0));$type=trim((string)($_GET['transaction_type']??''));$valid=['','customer_payment','sales_receipt','sales_refund','purchase_payment','supplier_payment','expense_payment'];if(!in_array($type,$valid,true))$type='';$opening=rp_cb_opening($branchId,$accountId,$from);$rows=rp_cb_collect($branchId,$accountId,$from,$to);if($type!=='')$rows=array_values(array_filter($rows,static fn($r)=>(string)$r['transaction_type']===$type));$running=$opening;$in=0.0;$out=0.0;foreach($rows as &$r){$in+=$r['money_in'];$out+=$r['money_out'];$running=round($running+$r['money_in']-$r['money_out'],2);$r['running_balance']=$running;$r['date_time']=$r['business_date'].' '.substr((string)$r['created_at'],11,8);unset($r['sort_key']);}unset($r);return ['rows'=>$rows,'summary'=>['opening_balance'=>$opening,'money_in'=>round($in,2),'money_out'=>round($out,2),'net_movement'=>round($in-$out,2),'closing_balance'=>$running],'date_from'=>$from,'date_to'=>$to];
}

$method=request_method();if($method!=='GET')json_error('Method not allowed.',405);
$scope=trim((string)($_GET['scope']??''));$cfg=rp_scope_config($scope);$access=require_permission($cfg['page'],ACTION_VIEW);$ctx=rp_tenant_context($access['user']);
if(isset($_GET['options'])) json_success('Report options loaded.',rp_common_options($ctx,$access,$cfg['report']));
$tax=rp_tax_mode($_GET['tax_mode']??'', $cfg['forced_tax']);
switch($cfg['report']){
    case 'sales':$data=rp_sales($ctx,$tax);break;
    case 'purchase':$data=rp_purchase($ctx,$tax);break;
    case 'stock':$data=rp_stock($ctx);break;
    case 'customer_outstanding':$data=rp_customer_outstanding($ctx);break;
    case 'supplier_outstanding':$data=rp_supplier_outstanding($ctx);break;
    case 'expense':$data=rp_expense($ctx,$tax);break;
    case 'cash_bank':$data=rp_cash_bank($ctx);break;
    default:json_error('Unsupported report.',422);
}
$data['allowed_actions']=$access['actions'];$data['branch']=$ctx;json_success('Detailed report loaded.',$data);
