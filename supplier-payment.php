<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Supplier Payment';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
<main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1 id="pageHeading">Supplier Payment</h1>
        <p>Settle Opening Balance, Overall FIFO outstanding or one Particular Purchase.</p>
    </div>
    <a class="btn gray" href="supplier-payment-list.php"><i data-lucide="list"></i>Payment List</a>
</div>

<form id="paymentForm" novalidate>
<input type="hidden" id="paymentRef">
<div class="card form-card">
    <div class="card-header"><div><h2>Supplier Payment Information</h2><p>Edit/Delete automatically reverses the old allocation and recalculates affected Purchases.</p></div></div>
    <div class="card-body">
        <div class="card-section-title">Payment Information</div>
        <div class="form-row">
            <div class="field col-3"><label for="paymentNumber">Payment No</label><input id="paymentNumber" type="text" readonly value="Auto generated"></div>
            <div class="field col-3"><label for="paymentDate" class="required">Payment Date</label><input id="paymentDate" type="date" required></div>
            <div class="field col-3"><label for="supplierRef" class="required">Supplier</label><select id="supplierRef" required><option value="">Select Supplier</option></select></div>
            <div class="field col-3"><label for="paymentTypeRef" class="required">Payment For</label><select id="paymentTypeRef" required><option value="">Select Payment Type</option></select></div>
        </div>
        <div class="form-row" id="purchaseField" hidden>
            <div class="field col-6"><label for="purchaseRef" class="required">Particular Purchase</label><select id="purchaseRef"><option value="">Select Purchase</option></select></div>
        </div>

        <div class="card-section-title">Supplier Outstanding</div>
        <div class="form-row">
            <div class="field col-4"><label>Opening Balance Pending</label><input id="openingOutstanding" type="text" readonly placeholder="0.00"></div>
            <div class="field col-4"><label>Purchase Outstanding</label><input id="purchaseOutstanding" type="text" readonly placeholder="0.00"></div>
            <div class="field col-4"><label>Overall Outstanding</label><input id="overallOutstanding" type="text" readonly placeholder="0.00"></div>
        </div>

        <div class="form-row">
            <div class="field col-8">
                <div class="card-section-title">Payment Details</div>
                <div class="card table-card app-allocation-wrap">
                    <table class="data-table app-allocation-table">
                        <thead><tr><th>Mode</th><th>Account</th><th>Amount</th><th>Reference No</th><th>Cheque No</th><th>Cheque Date</th></tr></thead>
                        <tbody id="paymentRows">
                            <tr class="payment-row" data-mode="1"><td class="app-allocation-mode">Cash</td><td><select class="pay-account"><option value="">Select Cash Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Optional"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="2"><td class="app-allocation-mode">UPI</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="UTR / Ref No"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="3"><td class="app-allocation-mode">Bank Transfer</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Transaction / Ref No"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="4"><td class="app-allocation-mode">Cheque</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Optional"></td><td><input class="pay-cheque-no" type="text" maxlength="100" placeholder="Cheque No"></td><td><input class="pay-cheque-date" type="date"></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="app-total-line"><span>Actual Payment</span><strong id="actualPaymentDisplay">₹0.00</strong></div>
                <div class="card-section-title">Remarks</div>
                <div class="field"><label for="notes">Notes</label><textarea id="notes" rows="3" maxlength="255" placeholder="Optional notes"></textarea></div>
            </div>

            <div class="field col-4">
                <div class="app-side-card">
                    <div class="app-side-card-head">Selected Settlement</div>
                    <div class="app-side-card-body">
                        <div class="app-summary-row"><span>Payment For</span><strong id="selectedPaymentFor">—</strong></div>
                        <div class="app-summary-row"><span>Purchase / Target</span><strong id="selectedPurchase">—</strong></div>
                        <div class="app-summary-row total"><span>Target Outstanding</span><strong id="targetOutstandingDisplay">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Payment</span><strong id="summaryPayment">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Balance After</span><strong id="remainingOutstandingDisplay">₹0.00</strong></div>
                    </div>
                </div>
                <div class="card-section-title">Allocation Preview</div>
                <div class="card table-card">
                    <table class="data-table"><thead><tr><th>Target</th><th>Applied</th><th>Balance</th></tr></thead><tbody id="allocationBody"><tr><td colspan="3" class="muted">Select Supplier and Payment Type.</td></tr></tbody></table>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer"><div class="buttons"><a class="btn gray" href="supplier-payment-list.php">Cancel</a><button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="hand-coins"></i>Post Payment</button></div></div>
</div>
</form>

<script>
(function(){
'use strict';
var form=document.getElementById('paymentForm'),params=new URLSearchParams(location.search),editRef=params.get('ref')||'',viewMode=params.get('view')==='1';
var accounts=[],currentContext=null,previewTimer=null,previewSerial=0,contextSerial=0;
var supplierSelect=null,typeSelect=null,purchaseSelect=null,accountSelects=[];
function el(id){return document.getElementById(id);}function esc(v){return App.escapeHtml(String(v==null?'':v));}
function n(v){var x=Number(String(v==null?'':v).replace(/,/g,'').trim()||0);return Number.isFinite(x)?x:0;}
function money(v){return n(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function has(actions,id){return (actions||[]).map(Number).indexOf(Number(id))!==-1;}
function refresh(gs){if(gs&&typeof gs.refresh==='function')gs.refresh();}
function initSelects(){if(!window.GlobalSelect)return;supplierSelect=GlobalSelect.init(el('supplierRef'),{placeholder:'Select Supplier'});typeSelect=GlobalSelect.init(el('paymentTypeRef'),{placeholder:'Select Payment Type'});purchaseSelect=GlobalSelect.init(el('purchaseRef'),{placeholder:'Select Purchase'});}
function selectedType(){var o=el('paymentTypeRef').selectedOptions[0];return o?Number(o.dataset.type||0):0;}
function fillSuppliers(rows){el('supplierRef').innerHTML='<option value="">Select Supplier</option>';rows.forEach(function(r){var o=document.createElement('option');o.value=r.ref;o.dataset.supplierId=r.id;o.textContent=r.supplier_code+' - '+r.supplier_name+(r.mobile?' | '+r.mobile:'');el('supplierRef').appendChild(o);});refresh(supplierSelect);}
function fillTypes(rows){el('paymentTypeRef').innerHTML='<option value="">Select Payment Type</option>';rows.forEach(function(r){var o=document.createElement('option');o.value=r.value;o.dataset.type=r.type;o.textContent=r.label;el('paymentTypeRef').appendChild(o);});refresh(typeSelect);}
function fillAccountOptions(){accountSelects=[];document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var mode=Number(row.dataset.mode||0),select=row.querySelector('.pay-account'),old=select.value;select.innerHTML='<option value="">'+(mode===1?'Select Cash Account':'Select Bank Account')+'</option>';accounts.forEach(function(a){if(Number(a.account_type)!==(mode===1?1:2))return;var o=document.createElement('option');o.value=a.id;o.textContent=a.account_code+' - '+a.account_name;select.appendChild(o);});if(old)select.value=old;if(window.GlobalSelect){if(select._globalSelect&&select._globalSelect.destroy)select._globalSelect.destroy();accountSelects.push(GlobalSelect.init(select,{placeholder:mode===1?'Select Cash Account':'Select Bank Account'}));}});}
function collectPayments(){var rows=[];document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){rows.push({payment_mode:Number(row.dataset.mode||0),account_id:row.querySelector('.pay-account').value,amount:row.querySelector('.pay-amount').value.trim(),reference_no:row.querySelector('.pay-reference').value.trim(),cheque_no:row.querySelector('.pay-cheque-no').disabled?'':row.querySelector('.pay-cheque-no').value.trim(),cheque_date:row.querySelector('.pay-cheque-date').disabled?'':row.querySelector('.pay-cheque-date').value});});return rows;}
function actualPayment(){var total=0;document.querySelectorAll('.pay-amount').forEach(function(i){if(n(i.value)>0)total+=n(i.value);});el('actualPaymentDisplay').textContent='₹'+money(total);el('summaryPayment').textContent='₹'+money(total);return total;}
function payload(action){return{action:action||'preview',ref:editRef||'',supplier_ref:el('supplierRef').value,payment_type_ref:el('paymentTypeRef').value,purchase_ref:el('purchaseRef').value,payment_date:el('paymentDate').value,payments:collectPayments(),notes:el('notes').value.trim()};}
function clearPreview(msg){el('allocationBody').innerHTML='<tr><td colspan="3" class="muted">'+esc(msg||'Enter Payment Amount.')+'</td></tr>';}
function renderPreview(data){el('targetOutstandingDisplay').textContent='₹'+money(data.targets_total);el('remainingOutstandingDisplay').textContent='₹'+money(data.remaining_outstanding);var rows=data.allocations||[];if(!rows.length){clearPreview('No amount allocated yet.');return;}el('allocationBody').innerHTML=rows.map(function(r){return '<tr><td>'+esc(r.label)+'</td><td>₹'+money(r.allocated_amount)+'</td><td>₹'+money(r.pending_after)+'</td></tr>';}).join('');}
function updateSummary(){actualPayment();var t=selectedType(),to=el('paymentTypeRef').selectedOptions[0],po=el('purchaseRef').selectedOptions[0];el('selectedPaymentFor').textContent=to&&to.value?to.textContent:'—';el('selectedPurchase').textContent=t===1?'Opening Balance':(t===2?'FIFO Allocation':(po&&po.value?po.textContent.split(' | ')[0]:'—'));var target=0;if(currentContext){if(t===1)target=n(currentContext.opening_outstanding);else if(t===2)target=n(currentContext.overall_outstanding);else if(t===3&&po&&po.value)target=n(po.dataset.pending);}el('targetOutstandingDisplay').textContent='₹'+money(target);el('remainingOutstandingDisplay').textContent='₹'+money(Math.max(0,target-actualPayment()));}
function updateTypeAvailability(){if(!currentContext)return;document.querySelectorAll('#paymentTypeRef option').forEach(function(o){var t=Number(o.dataset.type||0);if(!t)return;var disabled=(t===1&&n(currentContext.opening_outstanding)<=0.001)||(t===2&&n(currentContext.overall_outstanding)<=0.001)||(t===3&&!(currentContext.purchases||[]).length);o.disabled=disabled;o.textContent=o.textContent.replace(/ \(No Pending\)$/,'')+(disabled?' (No Pending)':'');});var selected=el('paymentTypeRef').selectedOptions[0];if(selected&&selected.disabled)el('paymentTypeRef').value='';refresh(typeSelect);}
function setContext(ctx){currentContext=ctx||null;el('openingOutstanding').value=ctx?money(ctx.opening_outstanding):'';el('purchaseOutstanding').value=ctx?money(ctx.purchase_outstanding):'';el('overallOutstanding').value=ctx?money(ctx.overall_outstanding):'';var old=el('purchaseRef').value;el('purchaseRef').innerHTML='<option value="">Select Purchase</option>';((ctx&&ctx.purchases)||[]).forEach(function(r){var o=document.createElement('option');o.value=r.ref;o.dataset.purchaseId=r.id;o.dataset.pending=r.pending;o.textContent=r.purchase_no+' | '+r.purchase_date+' | Pending ₹'+money(r.pending);el('purchaseRef').appendChild(o);});if(Array.prototype.some.call(el('purchaseRef').options,function(o){return o.value===old;}))el('purchaseRef').value=old;refresh(purchaseSelect);updateTypeAvailability();updateTypeUi();}
async function loadContext(){var ref=el('supplierRef').value,id=++contextSerial;if(!ref){setContext(null);return;}try{var url='api/supplier-payments.php?supplier_context=1&supplier_ref='+encodeURIComponent(ref);if(editRef)url+='&payment_ref='+encodeURIComponent(editRef);var r=await App.api(url);if(id!==contextSerial)return;setContext(r.data);schedulePreview();}catch(e){if(id!==contextSerial)return;setContext(null);App.showError(e,'Unable to load Supplier outstanding.');}}
function updateTypeUi(){var t=selectedType();el('purchaseField').hidden=t!==3;if(t!==3){el('purchaseRef').value='';refresh(purchaseSelect);}updateSummary();schedulePreview();}
async function preview(){var serial=++previewSerial;updateSummary();if(!el('supplierRef').value||!el('paymentTypeRef').value){clearPreview('Select Supplier and Payment Type.');return;}if(selectedType()===3&&!el('purchaseRef').value){clearPreview('Select Particular Purchase.');return;}if(actualPayment()<=0.001){clearPreview('Enter Payment Amount.');return;}try{var r=await App.api('api/supplier-payments.php',{method:'POST',body:payload('preview')});if(serial!==previewSerial)return;renderPreview(r.data);}catch(e){if(serial!==previewSerial)return;clearPreview(e&&e.message?e.message:'Check Payment values.');}}
function schedulePreview(){updateSummary();clearTimeout(previewTimer);previewTimer=setTimeout(preview,280);}
function setPaymentRows(rows){var map={};(rows||[]).forEach(function(r){map[String(r.payment_mode)]=r;});document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var d=map[String(row.dataset.mode)]||{},select=row.querySelector('.pay-account');select.value=d.account_id||'';row.querySelector('.pay-amount').value=Number(d.amount||0)>0?String(Number(d.amount)):'';row.querySelector('.pay-reference').value=d.reference_no||'';row.querySelector('.pay-cheque-no').value=d.cheque_no||'';row.querySelector('.pay-cheque-date').value=d.cheque_date||'';});accountSelects.forEach(refresh);updateSummary();}
function selectSupplierById(id){var o=Array.prototype.find.call(el('supplierRef').options,function(x){return Number(x.dataset.supplierId||0)===Number(id);});el('supplierRef').value=o?o.value:'';refresh(supplierSelect);}
function selectTypeById(id){var o=Array.prototype.find.call(el('paymentTypeRef').options,function(x){return Number(x.dataset.type||0)===Number(id);});el('paymentTypeRef').value=o?o.value:'';refresh(typeSelect);}
function selectPurchaseById(id){var o=Array.prototype.find.call(el('purchaseRef').options,function(x){return Number(x.dataset.purchaseId||0)===Number(id);});if(o)el('purchaseRef').value=o.value;refresh(purchaseSelect);}
function applyView(){if(!viewMode)return;el('pageHeading').textContent='View Supplier Payment';el('saveButton').hidden=true;form.querySelectorAll('input,select,textarea').forEach(function(c){if(c.type!=='hidden')c.disabled=true;});refresh(supplierSelect);refresh(typeSelect);refresh(purchaseSelect);accountSelects.forEach(refresh);}
async function load(){try{var options=await App.api('api/supplier-payments.php?options=1');accounts=options.data.accounts||[];fillSuppliers(options.data.suppliers||[]);fillTypes(options.data.payment_types||[]);fillAccountOptions();if(editRef){var r=await App.api('api/supplier-payments.php?ref='+encodeURIComponent(editRef)),p=r.data.payment;el('pageHeading').textContent=viewMode?'View Supplier Payment':'Edit Supplier Payment';el('paymentNumber').value=p.payment_no||'';el('paymentDate').value=p.payment_date||'';el('notes').value=p.notes||'';selectSupplierById(p.supplier_id);selectTypeById(p.payment_type);setContext(r.data.supplier_context);selectPurchaseById(p.purchase_id);setPaymentRows(p.payments||[]);if(!viewMode){el('saveButton').innerHTML='<i data-lucide="save"></i>Update Payment';if(!has(r.data.allowed_actions,3))el('saveButton').disabled=true;}}else{el('paymentDate').value=options.data.today||new Date().toISOString().slice(0,10);el('paymentNumber').value='Auto generated on save';if(!has(options.data.allowed_actions,2))el('saveButton').disabled=true;}updateTypeUi();applyView();if(window.lucide)lucide.createIcons();}catch(e){el('saveButton').disabled=true;App.showError(e,'Unable to load Supplier Payment form.');}}
initSelects();
el('supplierRef').addEventListener('change',loadContext);el('paymentTypeRef').addEventListener('change',updateTypeUi);el('purchaseRef').addEventListener('change',schedulePreview);el('paymentRows').addEventListener('input',function(e){if(e.target.matches('input'))schedulePreview();});el('paymentRows').addEventListener('change',function(){schedulePreview();});
form.addEventListener('submit',async function(e){e.preventDefault();if(viewMode||el('saveButton').disabled)return;if(selectedType()===3&&!el('purchaseRef').value){App.showError(null,'Select a pending Particular Purchase.');return;}el('saveButton').disabled=true;try{var r=await App.api('api/supplier-payments.php',{method:'POST',body:payload('save')});showToast(r.message||'Supplier Payment saved successfully.',{type:'success',duration:2});location.href='supplier-payment-list.php';}catch(err){el('saveButton').disabled=false;App.showError(err,'Unable to save Supplier Payment.');}});
load();
})();
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main></div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body></html>
