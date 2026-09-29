<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/bootstrap.php';
require_once dirname(__DIR__) . '/include/supplier-finance.php';
function purchase_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error('Purchase management is available only for tenant users.', 403);
    }
    $branchId = (int)($user['branch_id'] ?? 0);
    if ($branchId < 1) json_error('No active branch is assigned to your account.', 403);
    $stmt = db()->prepare(
        'SELECT b.id AS branch_id, b.company_id, b.branch_name, b.state_code, c.company_name
         FROM branches b
         INNER JOIN companies c ON c.id=b.company_id
         WHERE b.id=:branch_id AND b.status=1 AND c.status=1 LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Your assigned tenant branch is invalid or inactive.', 403);
    return [
        'branch_id'=>(int)$row['branch_id'],
        'company_id'=>(int)$row['company_id'],
        'branch_name'=>(string)$row['branch_name'],
        'company_name'=>(string)$row['company_name'],
        'state_code'=>$row['state_code']===null?null:(string)$row['state_code'],
    ];
}
function purchase_require_schema(): void
{
    $requiredTables = [
        'branches',
        'companies',
        'users',
        'food_suppliers',
        'food_products',
        'food_units',
        'hsn_master',
        'food_purchases',
        'food_purchase_items',
        'accounts',
        'food_purchase_payments',
        'food_stock_movements',
        'food_purchase_returns',
        'food_supplier_payments',
        'food_supplier_payment_allocations',
    ];
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $stmt = db()->query('SHOW TABLES LIKE ' . db()->quote($table));
        if (!$stmt || !$stmt->fetchColumn()) {
            $missingTables[] = $table;
        }
    }
    if ($missingTables) {
        json_error(
            'Purchase schema is incomplete. Missing table(s): ' . implode(', ', $missingTables) .
            '. Run sql/purchase-complete-single-batch-schema.sql first.',
            500
        );
    }
    $requiredColumns = [
        'branches' => ['state_code'],
        'food_purchases' => [
            'purchase_no', 'batch_number', 'supplier_invoice_date',
            'supplier_state_code', 'branch_state_code',
            'overall_discount_type', 'overall_discount_value',
            'overall_discount_amount', 'cess_total', 'before_round_total',
            'item_discount_total', 'round_off_enabled', 'paid_amount', 'balance_amount', 'payment_status'
        ],
        'food_purchase_items' => [
            'selected_unit_id', 'primary_unit_id', 'secondary_unit_id',
            'conversion_rate', 'primary_quantity', 'secondary_quantity', 'base_quantity', 'free_quantity',
            'unit_price', 'hsn_id',
            'purchase_tax_type', 'discount_type', 'discount_value',
            'overall_discount_share', 'gst_rate', 'cgst_rate', 'sgst_rate',
            'igst_rate', 'cess_rate', 'cess_amount'
        ],
        'food_stock_movements' => ['source_purchase_id','unit_id','conversion_rate','quantity_in','quantity_out','remarks'],
    ];
    $missingColumns = [];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = db()->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . db()->quote($column)
            );
            if (!$stmt || !$stmt->fetchColumn()) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }
    if ($missingColumns) {
        json_error(
            'Purchase schema is incomplete. Missing column(s): ' . implode(', ', $missingColumns) .
            '. Run sql/purchase-complete-single-batch-schema.sql first.',
            500
        );
    }
}
function purchase_nullable($value): ?string
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}
function purchase_valid_date($value, string $field, bool $required = false): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        if ($required) json_error('Purchase validation failed.', 422, [$field => 'This date is required.']);
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        json_error('Purchase validation failed.', 422, [$field => 'Enter a valid date.']);
    }
    return $value;
}
function purchase_ref_to_id($value): int
{
    if (!is_string($value) || trim($value)==='') json_error('Purchase reference is required.', 422, ['ref'=>'Purchase reference is required.']);
    try { return decryptReference(trim($value), 'purchase'); }
    catch (Throwable $e) { json_error('Invalid purchase reference.', 422, ['ref'=>'Invalid purchase reference.']); }
    return 0;
}
function purchase_generate_no(int $branchId): string
{
    $stmt = db()->prepare(
        "SELECT purchase_no FROM food_purchases
         WHERE branch_id=:branch_id AND purchase_no REGEXP '^PUR[0-9]+$'
         ORDER BY CAST(SUBSTRING(purchase_no,4) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([':branch_id'=>$branchId]);
    $last=(string)($stmt->fetchColumn()?:''); $next=1;
    if($last!=='' && preg_match('/^PUR([0-9]+)$/i',$last,$m)) $next=((int)$m[1])+1;
    return 'PUR'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}
function purchase_batch_no_from_purchase_no(string $purchaseNo): string
{
    if (preg_match('/^PUR([0-9]+)$/i', trim($purchaseNo), $m)) {
        return 'BAT' . $m[1];
    }
    // Defensive fallback. Normal Purchase numbers are always PUR0001...
    return 'BAT' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($purchaseNo)));
}
function purchase_supplier(int $branchId, int $supplierId, bool $requireActive=true): array
{
    $sql='SELECT id,supplier_code,supplier_name,state_code,gstin,status FROM food_suppliers WHERE id=:id AND branch_id=:branch_id';
    if($requireActive)$sql.=' AND status=1'; $sql.=' LIMIT 1';
    $stmt=db()->prepare($sql);$stmt->execute([':id'=>$supplierId,':branch_id'=>$branchId]);$row=$stmt->fetch();
    if(!$row)json_error('Supplier is unavailable for this branch.',422,['supplier_id'=>'Select an active supplier.']);
    $row['id']=(int)$row['id'];$row['status']=(int)$row['status'];return $row;
}
function purchase_account(int $branchId, int $accountId): array
{
    $stmt=db()->prepare('SELECT id,account_code,account_name,account_type,status FROM accounts WHERE id=:id AND branch_id=:branch_id AND status=1 LIMIT 1');
    $stmt->execute([':id'=>$accountId,':branch_id'=>$branchId]);$row=$stmt->fetch();
    if(!$row)json_error('Selected payment account is inactive or unavailable.',422);
    $row['id']=(int)$row['id'];$row['account_type']=(int)$row['account_type'];return $row;
}
/** Choose the branch's active default account for a payment mode. */
function purchase_default_account_id(int $branchId, int $mode): int
{
    $type = $mode === 1 ? 1 : 2;
    $order = $mode === 1
        ? 'is_default_cash DESC, account_name ASC, id ASC'
        : ($mode === 2
            ? "CASE WHEN NULLIF(TRIM(upi_id), '') IS NOT NULL THEN 0 ELSE 1 END ASC, account_name ASC, id ASC"
            : 'account_name ASC, id ASC');
    $stmt = db()->prepare(
        'SELECT id FROM accounts WHERE branch_id=:branch_id AND account_type=:account_type AND status=1 ORDER BY ' . $order . ' LIMIT 1'
    );
    $stmt->execute([':branch_id'=>$branchId, ':account_type'=>$type]);
    return (int)($stmt->fetchColumn() ?: 0);
}

/** Use supplier's recorded State Code, or derive it from its GSTIN when not recorded. */
function purchase_effective_supplier_state(array $supplier): ?string
{
    $state = purchase_nullable($supplier['state_code'] ?? null);
    if ($state !== null) return $state;
    $gstin = strtoupper(trim((string)($supplier['gstin'] ?? '')));
    return preg_match('/^([0-9]{2})[A-Z0-9]{13}$/', $gstin, $matches)
        ? $matches[1] : null;
}

function purchase_unit(int $branchId, int $unitId, bool $requireActive = true): array
{
    $sql = 'SELECT id,unit_code,unit_name,unit_symbol,status FROM food_units WHERE id=:id AND branch_id=:branch_id';
    if ($requireActive) $sql .= ' AND status=1';
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':id'=>$unitId, ':branch_id'=>$branchId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Selected Unit is inactive or unavailable for this branch.', 422, ['items'=>'Select a valid Unit.']);
    }
    $row['id'] = (int)$row['id'];
    $row['status'] = (int)$row['status'];
    return $row;
}
function purchase_product(int $branchId, int $productId, bool $requireActive=true): array
{
    $sql='SELECT p.id,p.product_code,p.product_name,p.primary_unit_id,p.secondary_unit_id,p.secondary_conversion,
                 p.hsn_id,p.purchase_price,p.purchase_tax_type,p.sale_price,p.track_batch,p.track_expiry,p.status,
                 pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                 su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol,
                 h.hsn_code,h.gst_rate,h.cgst_rate,h.sgst_rate,h.igst_rate,h.cess_rate
          FROM food_products p
          INNER JOIN food_units pu ON pu.id=p.primary_unit_id AND pu.branch_id=p.branch_id
          LEFT JOIN food_units su ON su.id=p.secondary_unit_id AND su.branch_id=p.branch_id
          LEFT JOIN hsn_master h ON h.id=p.hsn_id
          WHERE p.id=:id AND p.branch_id=:branch_id';
    if($requireActive)$sql.=' AND p.status=1'; $sql.=' LIMIT 1';
    $stmt=db()->prepare($sql);$stmt->execute([':id'=>$productId,':branch_id'=>$branchId]);$row=$stmt->fetch();
    if(!$row)json_error('Selected product is inactive or unavailable.',422,['product_id'=>'Select an active product.']);
    foreach(['id','primary_unit_id','purchase_tax_type','track_batch','track_expiry','status'] as $k)$row[$k]=(int)$row[$k];
    $row['secondary_unit_id']=$row['secondary_unit_id']===null?null:(int)$row['secondary_unit_id'];
    $row['hsn_id']=$row['hsn_id']===null?null:(int)$row['hsn_id'];
    foreach(['secondary_conversion','purchase_price','sale_price','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $k)$row[$k]=$row[$k]===null?0.0:(float)$row[$k];
    return $row;
}
function purchase_options(int $branchId, bool $includeInactive = false): array
{
    $supplierSql='SELECT id,supplier_code,supplier_name,state_code,gstin,status FROM food_suppliers WHERE branch_id=:branch_id';
    if (!$includeInactive) $supplierSql .= ' AND status=1';
    $supplierSql .= ' ORDER BY supplier_name';
    $sup=db()->prepare($supplierSql);
    $sup->execute([':branch_id'=>$branchId]);
    $products=db()->prepare(
        'SELECT p.id,p.product_code,p.product_name,p.primary_unit_id,p.secondary_unit_id,p.secondary_conversion,p.hsn_id,
                p.purchase_price,p.purchase_tax_type,p.track_batch,p.track_expiry,p.status,
                pu.unit_name AS primary_unit_name,pu.unit_symbol AS primary_unit_symbol,
                su.unit_name AS secondary_unit_name,su.unit_symbol AS secondary_unit_symbol,
                h.hsn_code,h.gst_rate,h.cgst_rate,h.sgst_rate,h.igst_rate,h.cess_rate
         FROM food_products p
         INNER JOIN food_units pu ON pu.id=p.primary_unit_id AND pu.branch_id=p.branch_id
         LEFT JOIN food_units su ON su.id=p.secondary_unit_id AND su.branch_id=p.branch_id
         LEFT JOIN hsn_master h ON h.id=p.hsn_id
         WHERE p.branch_id=:branch_id' . ($includeInactive ? '' : ' AND p.status=1') . ' ORDER BY p.product_name'
    );
    $products->execute([':branch_id'=>$branchId]);
    $unitSql='SELECT id,unit_code,unit_name,unit_symbol,status FROM food_units WHERE branch_id=:branch_id';
    if (!$includeInactive) $unitSql .= ' AND status=1';
    $unitSql .= ' ORDER BY unit_name,unit_symbol';
    $unitStmt=db()->prepare($unitSql);
    $unitStmt->execute([':branch_id'=>$branchId]);
    $accountSql='SELECT id,account_code,account_name,account_type,bank_name,account_number,upi_id,is_default_cash,status FROM accounts WHERE branch_id=:branch_id';
    if (!$includeInactive) $accountSql .= ' AND status=1';
    $accountSql .= ' ORDER BY account_type,account_name';
    $acc=db()->prepare($accountSql);
    $acc->execute([':branch_id'=>$branchId]);
    $productRows=[];
    foreach($products->fetchAll() as $row){
        foreach(['id','primary_unit_id','purchase_tax_type','track_batch','track_expiry','status'] as $k)$row[$k]=(int)$row[$k];
        $row['secondary_unit_id']=$row['secondary_unit_id']===null?null:(int)$row['secondary_unit_id'];
        $row['hsn_id']=$row['hsn_id']===null?null:(int)$row['hsn_id'];
        foreach(['secondary_conversion','purchase_price','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate'] as $k)$row[$k]=$row[$k]===null?0.0:(float)$row[$k];
        $productRows[]=$row;
    }
    $accounts=[];
    foreach($acc->fetchAll() as $row){$row['id']=(int)$row['id'];$row['account_type']=(int)$row['account_type'];$row['is_default_cash']=(int)($row['is_default_cash']??0);$row['status']=(int)$row['status'];$accounts[]=$row;}
    $suppliers=[];foreach($sup->fetchAll() as $row){$row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$suppliers[]=$row;}
    $units=[];foreach($unitStmt->fetchAll() as $row){$row['id']=(int)$row['id'];$row['status']=(int)$row['status'];$units[]=$row;}
    return ['suppliers'=>$suppliers,'products'=>$productRows,'units'=>$units,'accounts'=>$accounts];
}
function purchase_calculate(array $context, array $data, array $oldPurchase = []): array
{
    $branchId=(int)$context['branch_id'];
    $supplierId=(int)($data['supplier_id']??0);
    if($supplierId<1)json_error('Purchase validation failed.',422,['supplier_id'=>'Supplier is required.']);
    $supplier=purchase_supplier($branchId,$supplierId,true);
    $purchaseDate=purchase_valid_date($data['purchase_date']??'', 'purchase_date', true);
    // Batch Number is generated by the server from Purchase No.
    // Never trust or require a browser supplied batch number.
    $supplierInvoiceNo=purchase_nullable($data['supplier_invoice_number']??null);
    $supplierInvoiceDate=purchase_valid_date($data['supplier_invoice_date']??'', 'supplier_invoice_date', false);
    $notes=purchase_nullable($data['notes']??null);
    $overallType=(int)($data['overall_discount_type']??1);
    if(!in_array($overallType,[1,2],true))$overallType=1;
    $overallValue=$data['overall_discount_value']??0;
    if(!is_numeric($overallValue)||(float)$overallValue<0)json_error('Purchase validation failed.',422,['overall_discount_value'=>'Overall Discount must be zero or greater.']);
    $overallValue=round((float)$overallValue,2);
    if($overallType===1&&$overallValue>100)json_error('Purchase validation failed.',422,['overall_discount_value'=>'Percentage discount cannot exceed 100%.']);
    $roundEnabled=(int)($data['round_off_enabled']??0)===1?1:0;
    $rawItems=$data['items']??[];
    if(!is_array($rawItems)||count($rawItems)<1)json_error('Add at least one Product before saving the Purchase.',422,['items'=>'At least one Product is required.']);
    /* Existing draft rows are the trusted source for edit-time reverse
       calculation. The browser only sends item_id; rates are never trusted
       from the browser. */
    $oldItemsById=[];
    foreach(($oldPurchase['items']??[]) as $oldItem){
        $oldItemId=(int)($oldItem['id']??0);
        if($oldItemId>0)$oldItemsById[$oldItemId]=$oldItem;
    }
    $sameSupplierForReverse = !empty($oldPurchase) &&
        (int)($oldPurchase['supplier_id']??0) === $supplierId;
    $branchState=purchase_nullable($context['state_code']??null);
    $supplierState=purchase_effective_supplier_state($supplier);
    $supplyType=($branchState&&$supplierState)?($branchState===$supplierState?'intra_state':'inter_state'):null;
    // State Code / Tax Treatment is never a mandatory Purchase form field.
    // Retain the saved classification for an unchanged supplier before fallback.
    if ($supplyType === null && $sameSupplierForReverse &&
        in_array(($oldPurchase['supply_type'] ?? null), ['intra_state','inter_state'], true)) {
        $supplyType = $oldPurchase['supply_type'];
    }
    // Without reliable supplier/branch geography, preserve supplier_state_code as
    // NULL (never invent a state code). Default to a clearly disclosed same-state
    // calculation instead of silently omitting the GST rate from the bill total.
    // Operators must verify tax classification against the invoice before posting.
    if ($supplyType === null) $supplyType = 'intra_state';
    $items=[];$subtotal=0.0;$itemDiscountTotal=0.0;$preOverallTaxableTotal=0.0;$hasTax=false;
    $seenProductIds=[];
    foreach(array_values($rawItems) as $index=>$raw){
        if(!is_array($raw))json_error('Invalid Product row.',422);
        $rowNo=$index+1;
        $productId=(int)($raw['product_id']??0);if($productId<1)json_error('Purchase validation failed.',422,['items'=>'Product is required in row '.$rowNo.'.']);
        $product=purchase_product($branchId,$productId,true);
        if(isset($seenProductIds[$productId])){
            $label=trim(((string)($product['product_code']??'')).' - '.((string)($product['product_name']??'')), ' -');
            json_error('Purchase validation failed.',422,[
                'items'=>($label!==''?$label:'Selected Product').' is already added to this Purchase. Each Product can appear only once.'
            ]);
        }
        $seenProductIds[$productId]=true;
        $itemId=(int)($raw['item_id']??0);
        $oldItem=$itemId>0&&isset($oldItemsById[$itemId])?$oldItemsById[$itemId]:null;
        $sameSavedItem=$oldItem && (int)$oldItem['product_id']===$productId;
        /*
         * PURCHASE UNIT SNAPSHOT RULE
         * ---------------------------
         * Product Master is the authoritative source for Primary Unit,
         * Secondary Unit and Conversion for every NEW Purchase row.
         * Browser payloads cannot override that configuration.
         *
         * When an existing Draft is edited, keep its already-saved unit
         * snapshot so a later Product Master edit does not silently rewrite
         * the Draft. Posted Purchases are read-only.
         */
        if ($sameSavedItem) {
            $primaryUnitId = (int)($oldItem['primary_unit_id'] ?? 0);
            $secondaryUnitId = !empty($oldItem['secondary_unit_id'])
                ? (int)$oldItem['secondary_unit_id']
                : null;
            if ($primaryUnitId < 1) {
                json_error('Purchase validation failed.', 409, [
                    'items'=>'The saved Purchase Item unit snapshot is incomplete in row '.$rowNo.'. Recreate this draft row.'
                ]);
            }
            $primaryUnit = purchase_unit($branchId, $primaryUnitId, false);
            $secondaryUnit = $secondaryUnitId !== null
                ? purchase_unit($branchId, $secondaryUnitId, false)
                : null;
            $hasSecondary = $secondaryUnitId !== null;
            $conversion = $hasSecondary
                ? (float)($oldItem['conversion_rate'] ?? 0)
                : 1.0;
        } else {
            $primaryUnitId = (int)$product['primary_unit_id'];
            $secondaryUnitId = !empty($product['secondary_unit_id'])
                ? (int)$product['secondary_unit_id']
                : null;
            $primaryUnit = purchase_unit($branchId, $primaryUnitId, true);
            $secondaryUnit = $secondaryUnitId !== null
                ? purchase_unit($branchId, $secondaryUnitId, true)
                : null;
            $hasSecondary = $secondaryUnitId !== null;
            $conversion = $hasSecondary
                ? (float)$product['secondary_conversion']
                : 1.0;
        }
        if ($hasSecondary && $conversion <= 0) {
            json_error('Purchase validation failed.',422,[
                'items'=>'Primary/Secondary conversion is invalid in row '.$rowNo.'.'
            ]);
        }
        $primaryQtyRaw = $raw['primary_quantity'] ?? null;
        $secondaryQtyRaw = $raw['secondary_quantity'] ?? null;
        $primaryRateRaw = $raw['unit_price'] ?? 0;
        $freeQtyRaw = $raw['free_quantity'] ?? 0;
        $inputSelectedUnitId = (int)($raw['selected_unit_id'] ?? $primaryUnitId);
        $legacyOneUnitPayload = $primaryQtyRaw === null && $secondaryQtyRaw === null;
        if (
            $inputSelectedUnitId !== $primaryUnitId &&
            (!$hasSecondary || $inputSelectedUnitId !== (int)$secondaryUnitId)
        ) {
            json_error('Selected Unit does not belong to this Product.', 422, [
                'items'=>'Select a valid Unit in row '.$rowNo.'.'
            ]);
        }
        if (!is_numeric($freeQtyRaw) || (float)$freeQtyRaw < 0) {
            json_error('Purchase validation failed.',422,['items'=>'Free Quantity is invalid in row '.$rowNo.'.']);
        }
        $freeQtyInput = round((float)$freeQtyRaw,3);
        /* Backward-compatible support for an old one-unit payload. */
        if ($legacyOneUnitPayload) {
            $legacyQty = $raw['quantity'] ?? 0;
            $legacyUnit = $inputSelectedUnitId;
            if (!is_numeric($legacyQty)) $legacyQty = 0;
            if ($hasSecondary && $legacyUnit === (int)$secondaryUnitId) {
                $primaryQtyRaw = 0;
                $secondaryQtyRaw = $legacyQty;
                $legacyRate = $raw['unit_price'] ?? 0;
                $primaryRateRaw = is_numeric($legacyRate) ? ((float)$legacyRate * $conversion) : 0;
            } else {
                $primaryQtyRaw = $legacyQty;
                $secondaryQtyRaw = 0;
            }
        }
        if (!is_numeric($primaryQtyRaw) || (float)$primaryQtyRaw < 0) {
            json_error('Purchase validation failed.',422,['items'=>'Primary Quantity is invalid in row '.$rowNo.'.']);
        }
        if (!is_numeric($secondaryQtyRaw) || (float)$secondaryQtyRaw < 0) {
            json_error('Purchase validation failed.',422,['items'=>'Secondary Quantity is invalid in row '.$rowNo.'.']);
        }
        if (!is_numeric($primaryRateRaw) || (float)$primaryRateRaw < 0) {
            json_error('Purchase validation failed.',422,['items'=>'Primary Rate must be zero or greater in row '.$rowNo.'.']);
        }
        $primaryQty = round((float)$primaryQtyRaw,3);
        $secondaryQty = $hasSecondary ? round((float)$secondaryQtyRaw,3) : 0.0;
        if ($primaryQty <= 0 && $secondaryQty <= 0) {
            json_error('Purchase validation failed.',422,['items'=>'Enter Primary Qty or Secondary Qty in row '.$rowNo.'.']);
        }
        $primaryRate = round((float)$primaryRateRaw,2);
        $secondaryRate = $hasSecondary ? round($primaryRate / $conversion,6) : 0.0;
        $baseQty = round(($primaryQty * $conversion) + $secondaryQty,3);
        $qty = round($primaryQty + ($hasSecondary ? $secondaryQty / $conversion : 0),3); // legacy equivalent Primary Qty
        /* Free Qty follows the unit selected in the one-unit Purchase UI,
           but is stored as Primary-equivalent quantity just like `quantity`.
           It increases stock only and never changes gross/tax/payable totals. */
        $freeQty = $freeQtyInput;
        if ($legacyOneUnitPayload && $hasSecondary && $inputSelectedUnitId === (int)$secondaryUnitId) {
            $freeQty = round($freeQtyInput / $conversion,3);
        }
        $freeBaseQty = round($freeQty * $conversion,3);
        // food_purchase_items.unit_price is the canonical Primary Unit cost snapshot.
        $rate = $primaryRate;
        $selectedUnitId = $inputSelectedUnitId;
        $discountType=(int)($raw['discount_type']??1);if(!in_array($discountType,[1,2],true))$discountType=1;
        $discountValue=$raw['discount_value']??0;if(!is_numeric($discountValue)||(float)$discountValue<0)json_error('Purchase validation failed.',422,['items'=>'Invalid Discount in row '.$rowNo.'.']);
        $discountValue=round((float)$discountValue,2);
        if($discountType===1&&$discountValue>100)json_error('Purchase validation failed.',422,['items'=>'Percentage Discount cannot exceed 100% in row '.$rowNo.'.']);
        $taxType=(int)($raw['purchase_tax_type']??$product['purchase_tax_type']);if(!in_array($taxType,[1,2],true))$taxType=(int)$product['purchase_tax_type'];
        $expiry=purchase_valid_date($raw['expiry_date']??'', 'items', false);
        if((int)$product['track_expiry']===0)$expiry=null;
        if((int)$product['track_expiry']===1 && $expiry===null)json_error('Purchase validation failed.',422,['items'=>'Expiry Date is required in row '.$rowNo.'.']);
        $useSavedTaxSnapshot=$sameSupplierForReverse && $sameSavedItem;
        if($useSavedTaxSnapshot){
            $gst=(float)($oldItem['gst_rate']??0);
            $cgst=(float)($oldItem['cgst_rate']??0);
            $sgst=(float)($oldItem['sgst_rate']??0);
            $igst=(float)($oldItem['igst_rate']??0);
            $cess=(float)($oldItem['cess_rate']??0);
        } else {
            $gst=(float)$product['gst_rate'];$cgst=0.0;$sgst=0.0;$igst=0.0;$cess=(float)$product['cess_rate'];
            if($supplyType==='intra_state'){
                $sourceCgst=(float)($product['cgst_rate']??0);
                $sourceSgst=(float)($product['sgst_rate']??0);
                if(abs(($sourceCgst+$sourceSgst)-$gst)<0.01){
                    $cgst=$sourceCgst;$sgst=$sourceSgst;
                } else {
                    $cgst=round($gst/2,3);$sgst=round($gst-$cgst,3);
                }
            }
            elseif($supplyType==='inter_state'){
                $sourceIgst=(float)($product['igst_rate']??0);
                $igst=abs($sourceIgst-$gst)<0.01?$sourceIgst:$gst;
            }
        }
        if($gst+$cess>0)$hasTax=true;
        $gross=round(($primaryQty*$primaryRate)+($secondaryQty*$secondaryRate),2);
        $itemDiscount=$discountType===1?round($gross*$discountValue/100,2):$discountValue;
        if($itemDiscount>$gross+0.001)json_error('Purchase validation failed.',422,['items'=>'Fixed Discount cannot exceed line Gross in row '.$rowNo.'.']);
        $netGross=max(0,round($gross-$itemDiscount,2));
        $combinedRate=$gst+$cess;
        $preTaxable=$taxType===1 && $combinedRate>0 ? round($netGross/(1+$combinedRate/100),2) : $netGross;
        $items[]=[
            'source_item_id'=>$itemId,
            'product_id'=>$productId,'product'=>$product,'selected_unit_id'=>$selectedUnitId,
            'primary_unit_id'=>$primaryUnitId,'secondary_unit_id'=>$secondaryUnitId,
            'primary_unit_name'=>(string)($primaryUnit['unit_name'] ?? ''),'primary_unit_symbol'=>(string)($primaryUnit['unit_symbol'] ?? ''),
            'secondary_unit_name'=>$secondaryUnit ? (string)($secondaryUnit['unit_name'] ?? '') : '',
            'secondary_unit_symbol'=>$secondaryUnit ? (string)($secondaryUnit['unit_symbol'] ?? '') : '',
            'conversion_rate'=>$conversion,'primary_quantity'=>$primaryQty,'secondary_quantity'=>$secondaryQty,
            'quantity'=>$qty,'base_quantity'=>$baseQty,'free_quantity'=>$freeQty,'free_base_quantity'=>$freeBaseQty,'unit_price'=>$rate,
            'purchase_tax_type'=>$taxType,'discount_type'=>$discountType,'discount_value'=>$discountValue,
            'discount_amount'=>$itemDiscount,'pre_overall_taxable'=>$preTaxable,
            'expiry_date'=>$expiry,'hsn_id'=>$sameSavedItem ? ($oldItem['hsn_id'] ?? $product['hsn_id']) : $product['hsn_id'],
            'gst_rate'=>$gst,'cgst_rate'=>$cgst,'sgst_rate'=>$sgst,'igst_rate'=>$igst,'cess_rate'=>$cess,
        ];
        $subtotal+=$gross;$itemDiscountTotal+=$itemDiscount;$preOverallTaxableTotal+=$preTaxable;
    }
    $subtotal=round($subtotal,2);$itemDiscountTotal=round($itemDiscountTotal,2);$preOverallTaxableTotal=round($preOverallTaxableTotal,2);
    $overallAmount=$overallType===1?round($preOverallTaxableTotal*$overallValue/100,2):$overallValue;
    if($overallAmount>$preOverallTaxableTotal+0.001)json_error('Purchase validation failed.',422,['overall_discount_value'=>'Overall fixed Discount cannot exceed Taxable Amount.']);
    $allocated=0.0;$taxableTotal=0.0;$cgstTotal=0.0;$sgstTotal=0.0;$igstTotal=0.0;$cessTotal=0.0;$beforeRound=0.0;
    $last=count($items)-1;
    foreach($items as $i=>&$item){
        if($overallAmount<=0||$preOverallTaxableTotal<=0)$share=0.0;
        elseif($i===$last)$share=round($overallAmount-$allocated,2);
        else{$share=round($overallAmount*($item['pre_overall_taxable']/$preOverallTaxableTotal),2);$allocated+=$share;}
        $taxable=max(0,round($item['pre_overall_taxable']-$share,2));
        $cgstAmt=round($taxable*$item['cgst_rate']/100,2);$sgstAmt=round($taxable*$item['sgst_rate']/100,2);$igstAmt=round($taxable*$item['igst_rate']/100,2);$cessAmt=round($taxable*$item['cess_rate']/100,2);
        $lineTotal=round($taxable+$cgstAmt+$sgstAmt+$igstAmt+$cessAmt,2);
        $item['overall_discount_share']=$share;$item['taxable_amount']=$taxable;$item['cgst_amount']=$cgstAmt;$item['sgst_amount']=$sgstAmt;$item['igst_amount']=$igstAmt;$item['cess_amount']=$cessAmt;$item['line_total']=$lineTotal;
        $taxableTotal+=$taxable;$cgstTotal+=$cgstAmt;$sgstTotal+=$sgstAmt;$igstTotal+=$igstAmt;$cessTotal+=$cessAmt;$beforeRound+=$lineTotal;
    }unset($item);
    foreach(['taxableTotal','cgstTotal','sgstTotal','igstTotal','cessTotal','beforeRound'] as $n)$$n=round($$n,2);
    $grand=$roundEnabled?round($beforeRound,0,PHP_ROUND_HALF_UP):$beforeRound;$grand=round($grand,2);$roundOff=round($grand-$beforeRound,2);
    $payments=[];$paid=0.0;$seen=[];
    $rawPayments=$data['payments']??[];if(!is_array($rawPayments))$rawPayments=[];
    foreach($rawPayments as $raw){
        if(!is_array($raw))continue;$mode=(int)($raw['payment_mode']??0);if(!in_array($mode,[1,2,3,4],true))continue;
        if(isset($seen[$mode]))json_error('Only one allocation is allowed for each payment mode.',422);$seen[$mode]=true;
        $amount=$raw['amount']??0;if($amount===''||$amount===null)$amount=0;
        if(!is_numeric($amount)||(float)$amount<0)json_error('Payment Amount must be zero or greater.',422);
        $amount=round((float)$amount,2);if($amount<=0)continue;
        $accountId=(int)($raw['account_id']??0);if($accountId<1)$accountId=purchase_default_account_id($branchId,$mode);if($accountId<1)json_error('Create an active Cash/Bank Account before entering this payment.',422);
        $account=purchase_account($branchId,$accountId);
        if($mode===1 && $account['account_type']!==1)json_error('Cash payment must use a Cash Account.',422);
        if($mode!==1 && $account['account_type']!==2)json_error('UPI, Bank and Cheque payments must use a Bank Account.',422);
        $reference=purchase_nullable($raw['reference_no']??null);$chequeNo=null;$chequeDate=null;
        if($mode===4){$chequeNo=purchase_nullable($raw['cheque_no']??$raw['reference_no']??null);$chequeDate=purchase_valid_date($raw['cheque_date']??'','cheque_date',true);}
        $payments[]=['payment_mode'=>$mode,'account_id'=>$accountId,'amount'=>$amount,'reference_no'=>$reference,'cheque_no'=>$chequeNo,'cheque_date'=>$chequeDate];$paid+=$amount;
    }
    $paid=round($paid,2);if($paid>$grand+0.01)json_error('Paid Now cannot exceed Grand Total.',422,['payments'=>'Reduce payment allocation.']);
    $balance=max(0,round($grand-$paid,2));$paymentStatus=$paid<=0.001?1:($balance<=0.01?3:2);
    return [
        'supplier'=>$supplier,'supplier_id'=>$supplierId,'purchase_date'=>$purchaseDate,'supplier_invoice_number'=>$supplierInvoiceNo,'supplier_invoice_date'=>$supplierInvoiceDate,
        'supplier_state_code'=>$supplierState,'branch_state_code'=>$branchState,'supply_type'=>$supplyType,'tax_mode'=>$hasTax?1:0,
        'subtotal'=>$subtotal,'item_discount_total'=>$itemDiscountTotal,'overall_discount_type'=>$overallType,'overall_discount_value'=>$overallValue,'overall_discount_amount'=>$overallAmount,
        'discount_total'=>round($itemDiscountTotal+$overallAmount,2),'taxable_total'=>$taxableTotal,'cgst_total'=>$cgstTotal,'sgst_total'=>$sgstTotal,'igst_total'=>$igstTotal,'cess_total'=>$cessTotal,
        'before_round_total'=>$beforeRound,'round_off'=>$roundOff,'round_off_enabled'=>$roundEnabled,'grand_total'=>$grand,'paid_amount'=>$paid,'balance_amount'=>$balance,'payment_status'=>$paymentStatus,
        'notes'=>$notes,'items'=>$items,'payments'=>$payments,
    ];
}
function purchase_record(array $context, int $id): array
{
    $stmt=db()->prepare(
        'SELECT p.*,s.supplier_code,s.supplier_name
         FROM food_purchases p INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id
         WHERE p.id=:id AND p.branch_id=:branch_id LIMIT 1'
    );$stmt->execute([':id'=>$id,':branch_id'=>(int)$context['branch_id']]);$p=$stmt->fetch();if(!$p)json_error('Purchase was not found.',404);
    foreach(['id','branch_id','supplier_id','tax_mode','posting_status','status','overall_discount_type','round_off_enabled','payment_status'] as $k)$p[$k]=(int)$p[$k];
    foreach(['subtotal','discount_total','overall_discount_value','overall_discount_amount','taxable_total','cgst_total','sgst_total','igst_total','cess_total','before_round_total','round_off','grand_total','paid_amount','balance_amount'] as $k)$p[$k]=(float)$p[$k];
    $p['ref']=encryptReference('purchase',(int)$p['id']);
    $it=db()->prepare('SELECT pi.*, pu.unit_name AS primary_unit_name, pu.unit_symbol AS primary_unit_symbol, su.unit_name AS secondary_unit_name, su.unit_symbol AS secondary_unit_symbol FROM food_purchase_items pi LEFT JOIN food_units pu ON pu.id=pi.primary_unit_id AND pu.branch_id=pi.branch_id LEFT JOIN food_units su ON su.id=pi.secondary_unit_id AND su.branch_id=pi.branch_id WHERE pi.purchase_id=:purchase_id AND pi.branch_id=:branch_id AND pi.status=1 ORDER BY pi.id');$it->execute([':purchase_id'=>$id,':branch_id'=>(int)$context['branch_id']]);$items=[];
    foreach($it->fetchAll() as $r){foreach(['id','product_id','selected_unit_id','primary_unit_id','purchase_tax_type','discount_type'] as $k)$r[$k]=$r[$k]===null?null:(int)$r[$k];$r['secondary_unit_id']=$r['secondary_unit_id']===null?null:(int)$r['secondary_unit_id'];foreach(['conversion_rate','primary_quantity','secondary_quantity','quantity','base_quantity','free_quantity','unit_price','discount_value','discount_amount','overall_discount_share','taxable_amount','gst_rate','cgst_rate','sgst_rate','igst_rate','cess_rate','cgst_amount','sgst_amount','igst_amount','cess_amount','line_total'] as $k)$r[$k]=(float)$r[$k];$items[]=$r;}
    $pay=db()->prepare('SELECT pp.*,a.account_name,a.account_type FROM food_purchase_payments pp INNER JOIN accounts a ON a.id=pp.account_id WHERE pp.purchase_id=:purchase_id AND pp.branch_id=:branch_id AND pp.status=1 ORDER BY pp.payment_mode');$pay->execute([':purchase_id'=>$id,':branch_id'=>(int)$context['branch_id']]);$payments=[];foreach($pay->fetchAll() as $r){$r['payment_mode']=(int)$r['payment_mode'];$r['account_id']=(int)$r['account_id'];$r['amount']=(float)$r['amount'];$payments[]=$r;}
    $p['items']=$items;$p['payments']=$payments;
    if ((int)$p['posting_status'] === 1 && (int)$p['status'] === 1 && empty($p['reversed_at'])) {
        $finance = sf_purchase_snapshot(db(), (int)$context['branch_id'], $id);
        $p['paid_amount'] = $finance['paid_total'];
        $p['balance_amount'] = $finance['pending'];
        $p['return_total'] = $finance['return_total'];
        $p['supplier_paid'] = $finance['supplier_paid'];
        $p['payment_status'] = $finance['pending'] <= 0.01 ? 3 : ($finance['paid_total'] > 0.001 ? 2 : 1);
    }
    return $p;
}
function purchase_save(array $access, array $context, array $data, bool $isUpdate): array
{
    $branchId=(int)$context['branch_id'];$userId=(int)$access['user']['id'];$id=0;$old=null;
    if($isUpdate){$id=purchase_ref_to_id($data['ref']??'');$old=purchase_record($context,$id);if((int)$old['posting_status']===1)json_error('Posted Purchases are read-only and cannot be edited.',409);}
    $calc=purchase_calculate($context,$data,$isUpdate?$old:[]);$mode=strtolower(trim((string)($data['save_mode']??'draft')));$posting=$mode==='post'?1:0;
    $purchaseNo=$isUpdate?(string)$old['purchase_no']:purchase_generate_no($branchId);
    $batchNumber=$isUpdate && trim((string)($old['batch_number']??''))!==''
        ? (string)$old['batch_number']
        : purchase_batch_no_from_purchase_no($purchaseNo);
    $pdo=db();$pdo->beginTransaction();
    try{
        if($isUpdate){
            $stmt=$pdo->prepare('UPDATE food_purchases SET supplier_id=:supplier_id,batch_number=:batch_number,supplier_invoice_number=:supplier_invoice_number,supplier_invoice_date=:supplier_invoice_date,supplier_state_code=:supplier_state_code,branch_state_code=:branch_state_code,purchase_date=:purchase_date,tax_mode=:tax_mode,supply_type=:supply_type,subtotal=:subtotal,item_discount_total=:item_discount_total,discount_total=:discount_total,overall_discount_type=:overall_discount_type,overall_discount_value=:overall_discount_value,overall_discount_amount=:overall_discount_amount,taxable_total=:taxable_total,cgst_total=:cgst_total,sgst_total=:sgst_total,igst_total=:igst_total,cess_total=:cess_total,before_round_total=:before_round_total,round_off=:round_off,round_off_enabled=:round_off_enabled,grand_total=:grand_total,paid_amount=:paid_amount,balance_amount=:balance_amount,payment_status=:payment_status,notes=:notes,posting_status=:posting_status,status=1,updated_at=NOW() WHERE id=:id AND branch_id=:branch_id');
        }else{
            $stmt=$pdo->prepare('INSERT INTO food_purchases(branch_id,supplier_id,purchase_no,batch_number,supplier_invoice_number,supplier_invoice_date,supplier_state_code,branch_state_code,purchase_date,tax_mode,supply_type,subtotal,item_discount_total,discount_total,overall_discount_type,overall_discount_value,overall_discount_amount,taxable_total,cgst_total,sgst_total,igst_total,cess_total,before_round_total,round_off,round_off_enabled,grand_total,paid_amount,balance_amount,payment_status,notes,posting_status,status,created_by,created_at,updated_at) VALUES(:branch_id,:supplier_id,:purchase_no,:batch_number,:supplier_invoice_number,:supplier_invoice_date,:supplier_state_code,:branch_state_code,:purchase_date,:tax_mode,:supply_type,:subtotal,:item_discount_total,:discount_total,:overall_discount_type,:overall_discount_value,:overall_discount_amount,:taxable_total,:cgst_total,:sgst_total,:igst_total,:cess_total,:before_round_total,:round_off,:round_off_enabled,:grand_total,:paid_amount,:balance_amount,:payment_status,:notes,:posting_status,1,:created_by,NOW(),NOW())');
        }
        $params=[':branch_id'=>$branchId,':supplier_id'=>$calc['supplier_id'],':batch_number'=>$batchNumber,':supplier_invoice_number'=>$calc['supplier_invoice_number'],':supplier_invoice_date'=>$calc['supplier_invoice_date'],':supplier_state_code'=>$calc['supplier_state_code'],':branch_state_code'=>$calc['branch_state_code'],':purchase_date'=>$calc['purchase_date'],':tax_mode'=>$calc['tax_mode'],':supply_type'=>$calc['supply_type'],':subtotal'=>$calc['subtotal'],':item_discount_total'=>$calc['item_discount_total'],':discount_total'=>$calc['discount_total'],':overall_discount_type'=>$calc['overall_discount_type'],':overall_discount_value'=>$calc['overall_discount_value'],':overall_discount_amount'=>$calc['overall_discount_amount'],':taxable_total'=>$calc['taxable_total'],':cgst_total'=>$calc['cgst_total'],':sgst_total'=>$calc['sgst_total'],':igst_total'=>$calc['igst_total'],':cess_total'=>$calc['cess_total'],':before_round_total'=>$calc['before_round_total'],':round_off'=>$calc['round_off'],':round_off_enabled'=>$calc['round_off_enabled'],':grand_total'=>$calc['grand_total'],':paid_amount'=>$calc['paid_amount'],':balance_amount'=>$calc['balance_amount'],':payment_status'=>$calc['payment_status'],':notes'=>$calc['notes'],':posting_status'=>$posting];
        if($isUpdate)$params[':id']=$id;else{$params[':purchase_no']=$purchaseNo;$params[':created_by']=$userId;}$stmt->execute($params);if(!$isUpdate)$id=(int)$pdo->lastInsertId();
        if($isUpdate){$pdo->prepare('DELETE FROM food_purchase_payments WHERE purchase_id=:id AND branch_id=:branch_id')->execute([':id'=>$id,':branch_id'=>$branchId]);$pdo->prepare('DELETE FROM food_purchase_items WHERE purchase_id=:id AND branch_id=:branch_id')->execute([':id'=>$id,':branch_id'=>$branchId]);}
        $itemSql='INSERT INTO food_purchase_items(purchase_id,branch_id,product_id,selected_unit_id,primary_unit_id,secondary_unit_id,conversion_rate,primary_quantity,secondary_quantity,expiry_date,quantity,base_quantity,free_quantity,hsn_id,unit_price,purchase_tax_type,discount_type,discount_value,discount_amount,overall_discount_share,taxable_amount,tax_rate,gst_rate,cgst_rate,sgst_rate,igst_rate,cess_rate,cgst_amount,sgst_amount,igst_amount,cess_amount,line_total,status,created_by,created_at,updated_at) VALUES(:purchase_id,:branch_id,:product_id,:selected_unit_id,:primary_unit_id,:secondary_unit_id,:conversion_rate,:primary_quantity,:secondary_quantity,:expiry_date,:quantity,:base_quantity,:free_quantity,:hsn_id,:unit_price,:purchase_tax_type,:discount_type,:discount_value,:discount_amount,:overall_discount_share,:taxable_amount,:tax_rate,:gst_rate,:cgst_rate,:sgst_rate,:igst_rate,:cess_rate,:cgst_amount,:sgst_amount,:igst_amount,:cess_amount,:line_total,1,:created_by,NOW(),NOW())';
        $itemStmt=$pdo->prepare($itemSql);
        foreach($calc['items'] as $item){
            $itemStmt->execute([':purchase_id'=>$id,':branch_id'=>$branchId,':product_id'=>$item['product_id'],':selected_unit_id'=>$item['selected_unit_id'],':primary_unit_id'=>$item['primary_unit_id'],':secondary_unit_id'=>$item['secondary_unit_id'],':conversion_rate'=>$item['conversion_rate'],':primary_quantity'=>$item['primary_quantity'],':secondary_quantity'=>$item['secondary_quantity'],':expiry_date'=>$item['expiry_date'],':quantity'=>$item['quantity'],':base_quantity'=>$item['base_quantity'],':free_quantity'=>$item['free_quantity'],':hsn_id'=>$item['hsn_id'],':unit_price'=>$item['unit_price'],':purchase_tax_type'=>$item['purchase_tax_type'],':discount_type'=>$item['discount_type'],':discount_value'=>$item['discount_value'],':discount_amount'=>$item['discount_amount'],':overall_discount_share'=>$item['overall_discount_share'],':taxable_amount'=>$item['taxable_amount'],':tax_rate'=>$item['gst_rate'],':gst_rate'=>$item['gst_rate'],':cgst_rate'=>$item['cgst_rate'],':sgst_rate'=>$item['sgst_rate'],':igst_rate'=>$item['igst_rate'],':cess_rate'=>$item['cess_rate'],':cgst_amount'=>$item['cgst_amount'],':sgst_amount'=>$item['sgst_amount'],':igst_amount'=>$item['igst_amount'],':cess_amount'=>$item['cess_amount'],':line_total'=>$item['line_total'],':created_by'=>$userId]);
            if($posting){
                $mv=$pdo->prepare(
                    'INSERT INTO food_stock_movements(
                        branch_id,product_id,source_purchase_id,movement_date,
                        movement_type,reference_type,reference_id,reference_no,
                        quantity_in,quantity_out,unit_id,conversion_rate,remarks,
                        status,created_by,created_at
                     ) VALUES(
                        :branch_id,:product_id,:source_purchase_id,:movement_date,
                        1,\'purchase\',:reference_id,:reference_no,
                        :quantity_in,0,:unit_id,:conversion_rate,:remarks,
                        1,:created_by,NOW()
                     )'
                );
                $mv->execute([
                    ':branch_id'=>$branchId,
                    ':product_id'=>$item['product_id'],
                    ':source_purchase_id'=>$id,
                    ':movement_date'=>$calc['purchase_date'],
                    ':reference_id'=>$id,
                    ':reference_no'=>$purchaseNo,
                    ':quantity_in'=>round($item['base_quantity']+$item['free_base_quantity'],3),
                    ':unit_id'=>$item['secondary_unit_id']!==null?(int)$item['secondary_unit_id']:($item['primary_unit_id']>0?(int)$item['primary_unit_id']:null),
                    ':conversion_rate'=>$item['conversion_rate'],
                    ':remarks'=>'Purchase '.$purchaseNo,
                    ':created_by'=>$userId
                ]);
            }
        }
        $payStmt=$pdo->prepare('INSERT INTO food_purchase_payments(purchase_id,branch_id,account_id,payment_mode,amount,reference_no,cheque_no,cheque_date,status,created_by,created_at,updated_at) VALUES(:purchase_id,:branch_id,:account_id,:payment_mode,:amount,:reference_no,:cheque_no,:cheque_date,1,:created_by,NOW(),NOW())');
        foreach($calc['payments'] as $pay)$payStmt->execute([':purchase_id'=>$id,':branch_id'=>$branchId,':account_id'=>$pay['account_id'],':payment_mode'=>$pay['payment_mode'],':amount'=>$pay['amount'],':reference_no'=>$pay['reference_no'],':cheque_no'=>$pay['cheque_no'],':cheque_date'=>$pay['cheque_date'],':created_by'=>$userId]);
        if ($posting) { sf_refresh_purchase($pdo, $branchId, $id); }
        $pdo->commit();
        audit_log($userId,$isUpdate?ACTION_UPDATE:ACTION_CREATE,['company_id'=>(int)$context['company_id'],'branch_id'=>$branchId,'menu_id'=>(int)$access['menu']['id'],'record_id'=>$id,'old_data'=>$old]);
        return purchase_record($context,$id);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
$method=request_method();purchase_require_schema();
if($method==='GET'&&isset($_GET['options'])){
    $access=require_permission('purchase-form.php',ACTION_VIEW);$context=purchase_tenant_context($access['user']);
    $nextPurchaseNo=purchase_generate_no((int)$context['branch_id']);
    json_success('Purchase form options loaded.',['allowed_actions'=>$access['actions'],'next_purchase_no'=>$nextPurchaseNo,'next_batch_no'=>purchase_batch_no_from_purchase_no($nextPurchaseNo),'branch'=>$context,'options'=>purchase_options((int)$context['branch_id'])]);
}
if($method==='GET'&&isset($_GET['ref'])){
    $access=require_permission('purchase-form.php',ACTION_VIEW);$context=purchase_tenant_context($access['user']);
    json_success('Purchase loaded.',['allowed_actions'=>$access['actions'],'purchase'=>purchase_record($context,purchase_ref_to_id($_GET['ref'])),'options'=>purchase_options((int)$context['branch_id'], true),'branch'=>$context]);
}
if($method==='GET'&&isset($_GET['datatable'])){
    $access=require_permission('purchase-list.php',ACTION_VIEW);$context=purchase_tenant_context($access['user']);$branchId=(int)$context['branch_id'];$formActions=effective_actions_for_menu($access['user'],menu_by_path('purchase-form.php'));
    $draw=max(0,(int)($_GET['draw']??0));$start=max(0,(int)($_GET['start']??0));$search=trim((string)($_GET['search']['value']??''));$posting=isset($_GET['posting_status'])&&$_GET['posting_status']!==''?(int)$_GET['posting_status']:-1;$payment=isset($_GET['payment_status'])&&$_GET['payment_status']!==''?(int)$_GET['payment_status']:0;
    $where=['p.branch_id=:branch_id', 'p.status=1', 'p.reversed_at IS NULL'];
    $params=[':branch_id'=>$branchId];
    if($search!==''){
        $like='%'.$search.'%';
        $where[]='(p.purchase_no LIKE :s_no OR s.supplier_code LIKE :s_code OR s.supplier_name LIKE :s_name OR p.supplier_invoice_number LIKE :s_invoice OR p.batch_number LIKE :s_batch)';
        $params+=[':s_no'=>$like,':s_code'=>$like,':s_name'=>$like,':s_invoice'=>$like,':s_batch'=>$like];
    }
    $supplierId=max(0,(int)($_GET['supplier_id']??0));
    if($supplierId>0){$where[]='p.supplier_id=:supplier_id';$params[':supplier_id']=$supplierId;}
    if(in_array($posting,[0,1],true)){$where[]='p.posting_status=:posting_status';$params[':posting_status']=$posting;}
    // Branch id is an authenticated integer. Each aggregate is scoped to that branch.
    $direct="(SELECT purchase_id,SUM(amount) amount FROM food_purchase_payments WHERE branch_id=$branchId AND status=1 GROUP BY purchase_id)";
    $later="(SELECT a.purchase_id,SUM(a.allocated_amount) amount FROM food_supplier_payment_allocations a INNER JOIN food_supplier_payments sp ON sp.id=a.supplier_payment_id AND sp.branch_id=a.branch_id WHERE a.branch_id=$branchId AND a.status=1 AND a.allocation_type=1 AND sp.status=1 AND sp.posting_status=1 AND sp.reversed_at IS NULL GROUP BY a.purchase_id)";
    $returns="(SELECT purchase_id,SUM(grand_total) amount FROM food_purchase_returns WHERE branch_id=$branchId AND status=1 AND posting_status=1 AND reversed_at IS NULL GROUP BY purchase_id)";
    $paid='(CASE WHEN p.posting_status=1 THEN ROUND(COALESCE(dp.amount,0)+COALESCE(lp.amount,0),2) ELSE p.paid_amount END)';
    $balance="(CASE WHEN p.posting_status=1 THEN GREATEST(0,ROUND(p.grand_total-COALESCE(pr.amount,0)-$paid,2)) ELSE p.balance_amount END)";
    $paymentStatus="(CASE WHEN $balance<=0.01 THEN 3 WHEN $paid>0.001 THEN 2 ELSE 1 END)";
    $from=' FROM food_purchases p INNER JOIN food_suppliers s ON s.id=p.supplier_id AND s.branch_id=p.branch_id'
        ." LEFT JOIN $direct dp ON dp.purchase_id=p.id LEFT JOIN $later lp ON lp.purchase_id=p.id LEFT JOIN $returns pr ON pr.purchase_id=p.id";
    if(in_array($payment,[1,2,3],true)){$where[]="$paymentStatus=:payment_status";$params[':payment_status']=$payment;}
    $total=db()->prepare('SELECT COUNT(*) FROM food_purchases p WHERE p.branch_id=:branch_id AND p.status=1 AND p.reversed_at IS NULL');
    $total->execute([':branch_id'=>$branchId]);$recordsTotal=(int)$total->fetchColumn();
    $count=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.implode(' AND ',$where));
    $count->execute($params);$recordsFiltered=(int)$count->fetchColumn();
    $summaryStmt=db()->prepare(
        'SELECT
            COUNT(*) AS purchase_count,
            COALESCE(SUM(p.grand_total),0) AS grand_total,
            COALESCE(SUM('.$paid.'),0) AS paid_amount,
            COALESCE(SUM('.$balance.'),0) AS balance_amount'
        .$from.
        ' WHERE '.implode(' AND ',$where)
    );
    foreach($params as $k=>$v){
        $summaryStmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    }
    $summaryStmt->execute();
    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $cols=['p.purchase_no','p.purchase_date','s.supplier_name','p.supplier_invoice_number','p.grand_total',$paid,$balance,'p.posting_status'];
    $oc=(int)($_GET['order'][0]['column']??1);
    $dir=strtolower((string)($_GET['order'][0]['dir']??'desc'))==='asc'?'ASC':'DESC';
    $ob=$cols[$oc]??'p.purchase_date';
    $requestedLength=(int)($_GET['length']??10);
    $length=$requestedLength<0
        ?100000
        :max(1,min(100000,$requestedLength));
    $sql="SELECT p.id,p.purchase_no,p.batch_number,p.purchase_date,p.supplier_invoice_number,p.grand_total,$paid AS paid_amount,$balance AS balance_amount,$paymentStatus AS payment_status,p.posting_status,s.supplier_code,s.supplier_name".$from.' WHERE '.implode(' AND ',$where).' ORDER BY '.$ob.' '.$dir.',p.id DESC LIMIT :start,:length';
    $stmt=db()->prepare($sql);
    foreach($params as $k=>$v)$stmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT);$stmt->bindValue(':length',$length,PDO::PARAM_INT);$stmt->execute();$rows=[];
    foreach($stmt->fetchAll() as $r){
        foreach(['grand_total','paid_amount','balance_amount'] as $key)$r[$key]=(float)$r[$key];
        $r['payment_status']=(int)$r['payment_status'];$r['posting_status']=(int)$r['posting_status'];
        $r['payment_status_label']=$r['payment_status']===3?'Paid':($r['payment_status']===2?'Partially Paid':'Unpaid');
        $r['posting_status_label']=$r['posting_status']===1?'Posted':'Draft';
        $r['ref']=encryptReference('purchase',(int)$r['id']);
        $r['open_url']='purchase-form.php?ref='.rawurlencode($r['ref']);
        $r['edit_url']=$r['open_url'];unset($r['id']);$rows[]=$r;
    }
    $sup=db()->prepare('SELECT id,supplier_code,supplier_name FROM food_suppliers WHERE branch_id=:branch_id ORDER BY supplier_name');
    $sup->execute([':branch_id'=>$branchId]);
    json_success('Purchases loaded.',[
        'datatable'=>[
            'draw'=>$draw,
            'recordsTotal'=>$recordsTotal,
            'recordsFiltered'=>$recordsFiltered,
            'data'=>$rows
        ],
        'summary'=>[
            'purchase_count'=>(int)($summary['purchase_count']??0),
            'grand_total'=>(float)($summary['grand_total']??0),
            'paid_amount'=>(float)($summary['paid_amount']??0),
            'balance_amount'=>(float)($summary['balance_amount']??0),
        ],
        'allowed_actions'=>$access['actions'],
        'list_actions'=>$access['actions'],
        'form_actions'=>$formActions,
        'suppliers'=>$sup->fetchAll()
    ]);
}
if($method==='POST'||$method==='PUT'){
    $isUpdate=$method==='PUT';$access=require_permission('purchase-form.php',$isUpdate?ACTION_UPDATE:ACTION_CREATE);$context=purchase_tenant_context($access['user']);$data=request_data();$purchase=purchase_save($access,$context,$data,$isUpdate);json_success((int)$purchase['posting_status']===1?'Purchase posted successfully.':($isUpdate?'Purchase draft updated successfully.':'Purchase draft saved successfully.'),['purchase'=>$purchase],$isUpdate?200:201);
}
json_error('Method not allowed.',405);
