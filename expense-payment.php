<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Expense Payment';
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
        <h1 id="pageHeading">Expense Payment</h1>
        <p>Pay a posted Expense using Cash / UPI / Bank / Cheque. Edit and Delete recalculate the Expense automatically.</p>
    </div>
    <a class="btn gray" href="expense-payment-list.php"><i data-lucide="list"></i>Payment List</a>
</div>

<form id="paymentForm" novalidate>
<div class="card form-card">
    <div class="card-header"><div><h2>Expense Payment Information</h2><p>Payment Status is never selected manually. It is calculated from Total Expense and all active posted Expense Payments.</p></div></div>
    <div class="card-body">
        <div class="card-section-title">Payment Information</div>
        <div class="form-row">
            <div class="field col-3"><label for="paymentNo">Payment No</label><input id="paymentNo" type="text" readonly value="Auto generated"></div>
            <div class="field col-3"><label for="paymentDate" class="required">Payment Date</label><input id="paymentDate" type="date" required></div>
            <div class="field col-6"><label for="expenseRef" class="required">Expense</label><select id="expenseRef" required><option value="">Select Expense</option></select></div>
        </div>

        <div class="card-section-title">Expense Position</div>
        <div class="form-row">
            <div class="field col-3"><label>Category</label><input id="categoryName" type="text" readonly></div>
            <div class="field col-3"><label>Payee</label><input id="payeeName" type="text" readonly></div>
            <div class="field col-2"><label>Total Expense</label><input id="expenseTotal" type="text" readonly></div>
            <div class="field col-2"><label>Paid Already</label><input id="expensePaid" type="text" readonly></div>
            <div class="field col-2"><label>Pending</label><input id="expensePending" type="text" readonly></div>
        </div>

        <div class="form-row">
            <div class="field col-8">
                <div class="card-section-title">Payment Details</div>
                <div class="card table-card app-allocation-wrap">
                    <table class="data-table app-allocation-table">
                        <thead><tr><th>Mode</th><th>Account</th><th>Amount</th><th>Reference No</th><th>Cheque No</th><th>Cheque Date</th></tr></thead>
                        <tbody id="paymentRows">
                            <tr class="payment-row" data-mode="1"><td class="app-allocation-mode">Cash</td><td><select class="pay-account"><option value="">Select Cash Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Optional"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="2"><td class="app-allocation-mode">UPI</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="UPI / UTR No"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="3"><td class="app-allocation-mode">Bank Transfer</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Transaction / Ref No"></td><td><input class="pay-cheque-no" type="text" disabled placeholder="—"></td><td><input class="pay-cheque-date" type="date" disabled></td></tr>
                            <tr class="payment-row" data-mode="4"><td class="app-allocation-mode">Cheque</td><td><select class="pay-account"><option value="">Select Bank Account</option></select></td><td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td><td><input class="pay-reference" type="text" maxlength="150" placeholder="Optional"></td><td><input class="pay-cheque-no" type="text" maxlength="100" placeholder="Cheque No"></td><td><input class="pay-cheque-date" type="date"></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-section-title">Remarks</div>
                <div class="field"><label for="notes">Notes</label><textarea id="notes" rows="3" maxlength="255" placeholder="Optional notes"></textarea></div>
            </div>
            <div class="field col-4">
                <div class="app-side-card">
                    <div class="app-side-card-head">Payment Summary</div>
                    <div class="app-side-card-body">
                        <div class="app-summary-row"><span>Expense Pending</span><strong id="summaryPending">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Actual Payment</span><strong id="summaryPayment">₹0.00</strong></div>
                        <div class="app-summary-row total"><span>Balance After</span><strong id="summaryBalance">₹0.00</strong></div>
                        <div class="app-summary-row"><span>Status After</span><strong id="summaryStatus">Unpaid</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer"><div class="buttons"><a class="btn gray" href="expense-payment-list.php">Cancel</a><button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="hand-coins"></i>Post Payment</button></div></div>
</div>
</form>

<script>
(function(){
'use strict';
var params=new URLSearchParams(location.search),editRef=params.get('ref')||'',preselectedExpense=params.get('expense')||'',viewMode=params.get('view')==='1',lockExpense=params.get('lock')==='1';
var form=document.getElementById('paymentForm'),actions=[],accounts=[],currentExpense=null,expenseSelect=null,accountSelects={},previewTimer=null,previewSerial=0,contextSerial=0,saving=false,lockedExpenseRef='';
function el(id){return document.getElementById(id);}function n(v){var x=Number(String(v==null?'':v).replace(/,/g,'').trim()||0);return Number.isFinite(x)?x:0;}function money(v){return'₹'+n(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function has(id){return actions.map(Number).indexOf(Number(id))!==-1;}function refresh(gs){if(gs&&gs.refresh)gs.refresh();}
function expenseText(x){return x.expense_no+' | '+x.expense_date+' | '+x.category_name+' | '+x.payee_name+' | Pending '+money(x.balance_amount);}
function setNativeValue(native,value,controller){native.value=value==null?'':String(value);if(controller){if(typeof controller.setValue==='function')controller.setValue(native.value);else refresh(controller);}}
function setExpenses(rows,selectedRef){var s=el('expenseRef'),items=[{value:'',text:'Select Expense'}];s.innerHTML='<option value="">Select Expense</option>';(rows||[]).forEach(function(x){var o=document.createElement('option');o.value=x.ref;o.dataset.expenseId=x.id;o.textContent=expenseText(x);s.appendChild(o);items.push({value:String(x.ref),text:expenseText(x)});});if(expenseSelect&&typeof expenseSelect.setOptions==='function'){expenseSelect.setOptions(items,selectedRef||'');}else{setNativeValue(s,selectedRef||'',expenseSelect);} }
function fillAccounts(selectedByMode){selectedByMode=selectedByMode||{};accountSelects={};document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var mode=Number(row.dataset.mode),s=row.querySelector('.pay-account'),wanted=mode===1?1:2,defaultId='',selected=selectedByMode[String(mode)]||'',items=[{value:'',text:wanted===1?'Select Cash Account':'Select Bank Account'}];s.innerHTML='<option value="">'+(wanted===1?'Select Cash Account':'Select Bank Account')+'</option>';accounts.filter(function(a){return Number(a.account_type)===wanted;}).forEach(function(a){var text=(a.account_code?a.account_code+' - ':'')+a.account_name,o=document.createElement('option');o.value=a.id;o.textContent=text;s.appendChild(o);items.push({value:String(a.id),text:text});if(mode===1&&Number(a.is_default_cash)===1)defaultId=String(a.id);});if(!selected)selected=defaultId;if(window.GlobalSelect){var ctrl=GlobalSelect.init(s,{placeholder:wanted===1?'Select Cash Account':'Select Bank Account'});accountSelects[String(mode)]=ctrl;if(ctrl&&typeof ctrl.setOptions==='function')ctrl.setOptions(items,selected||'');else setNativeValue(s,selected||'',ctrl);}else{s.value=selected||'';}});}
function collectPayments(){var rows=[];document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var amount=n(row.querySelector('.pay-amount').value);if(amount<=0)return;rows.push({payment_mode:Number(row.dataset.mode),account_id:row.querySelector('.pay-account').value,amount:amount,reference_no:row.querySelector('.pay-reference').value.trim(),cheque_no:row.querySelector('.pay-cheque-no').value.trim(),cheque_date:row.querySelector('.pay-cheque-date').value});});return rows;}
function actualPayment(){var total=0;collectPayments().forEach(function(x){total+=n(x.amount);});return Math.round((total+Number.EPSILON)*100)/100;}
function updateSummary(){var pending=n(currentExpense&&currentExpense.balance_amount),payment=actualPayment(),after=Math.max(0,Math.round((pending-payment+Number.EPSILON)*100)/100);el('summaryPending').textContent=money(pending);el('summaryPayment').textContent=money(payment);el('summaryBalance').textContent=money(after);var paidBefore=n(currentExpense&&currentExpense.paid_amount),paidAfter=paidBefore+payment;el('summaryStatus').textContent=paidAfter<=0.001?'Unpaid':(after<=0.01?'Paid':'Partially Paid');}
function setExpense(ctx){currentExpense=ctx||null;el('categoryName').value=ctx?ctx.category_name:'';el('payeeName').value=ctx?ctx.payee_name:'';el('expenseTotal').value=ctx?money(ctx.total_amount):'';el('expensePaid').value=ctx?money(ctx.paid_amount):'';el('expensePending').value=ctx?money(ctx.balance_amount):'';updateSummary();}
async function loadContext(){var ref=el('expenseRef').value,id=++contextSerial;if(!ref){setExpense(null);return;}try{var url='api/expense-payments.php?expense_context=1&expense_ref='+encodeURIComponent(ref);if(editRef)url+='&payment_ref='+encodeURIComponent(editRef);var r=await App.api(url);if(id!==contextSerial)return;setExpense(r.data);schedulePreview();}catch(e){if(id!==contextSerial)return;setExpense(null);App.showError(e,'Unable to load Expense outstanding.');}}
function payload(action){return{action:action||'preview',ref:editRef,expense_ref:el('expenseRef').value,payment_date:el('paymentDate').value,payments:collectPayments(),notes:el('notes').value.trim()};}
async function preview(){var serial=++previewSerial;updateSummary();if(!el('expenseRef').value||actualPayment()<=0.001)return;try{var r=await App.api('api/expense-payments.php',{method:'POST',body:payload('preview')});if(serial!==previewSerial)return;setExpense(r.data.expense);el('summaryPayment').textContent=money(r.data.actual_payment);el('summaryBalance').textContent=money(r.data.balance_after);var paidAfter=n(r.data.expense.paid_amount)+n(r.data.actual_payment);el('summaryStatus').textContent=paidAfter<=0.001?'Unpaid':(n(r.data.balance_after)<=0.01?'Paid':'Partially Paid');}catch(e){if(serial!==previewSerial)return;updateSummary();}}
function schedulePreview(){updateSummary();clearTimeout(previewTimer);previewTimer=setTimeout(preview,280);}
function setPaymentRows(rows){var map={},selected={};(rows||[]).forEach(function(r){map[String(r.payment_mode)]=r;selected[String(r.payment_mode)]=String(r.account_id||'');});fillAccounts(selected);document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var mode=String(row.dataset.mode),d=map[mode]||{};setNativeValue(row.querySelector('.pay-account'),d.account_id||'',accountSelects[mode]);row.querySelector('.pay-amount').value=n(d.amount)>0?String(n(d.amount)):'';row.querySelector('.pay-reference').value=d.reference_no||'';row.querySelector('.pay-cheque-no').value=d.cheque_no||'';row.querySelector('.pay-cheque-date').value=d.cheque_date||'';});updateSummary();}
function lockExpenseSelection(forceRef){lockedExpenseRef=forceRef||lockedExpenseRef||preselectedExpense||'';if(!lockExpense&&!editRef)return;if(!lockedExpenseRef)return;var native=el('expenseRef');setNativeValue(native,lockedExpenseRef,expenseSelect);native.disabled=true;var root=native.closest('.global-select')||native.parentElement;if(root){root.querySelectorAll('.global-select-input,button,input').forEach(function(c){c.disabled=true;});root.classList.add('is-disabled');}refresh(expenseSelect);}
function applyView(){if(!viewMode)return;el('pageHeading').textContent='View Expense Payment';el('saveButton').hidden=true;form.querySelectorAll('input,select,textarea').forEach(function(c){c.disabled=true;});refresh(expenseSelect);Object.keys(accountSelects).forEach(function(k){refresh(accountSelects[k]);});}
async function load(){try{if(window.GlobalSelect)expenseSelect=GlobalSelect.init(el('expenseRef'),{placeholder:'Select Expense'});var o=await App.api('api/expense-payments.php?options=1');actions=(o.data.allowed_actions||[]).map(Number);accounts=o.data.accounts||[];setExpenses(o.data.expenses||[],preselectedExpense);if(editRef){var r=await App.api('api/expense-payments.php?ref='+encodeURIComponent(editRef)),p=r.data.payment;actions=(r.data.allowed_actions||actions).map(Number);accounts=r.data.accounts||accounts;lockedExpenseRef=p.expense_ref||preselectedExpense||'';setExpenses(r.data.expenses||[],lockedExpenseRef);el('pageHeading').textContent=viewMode?'View Expense Payment':'Edit Expense Payment';el('paymentNo').value=p.payment_no||'';el('paymentDate').value=p.payment_date||'';el('notes').value=p.notes||'';setNativeValue(el('expenseRef'),lockedExpenseRef,expenseSelect);setExpense(r.data.expense_context);setPaymentRows(p.payments||[]);lockExpenseSelection(lockedExpenseRef);if(!viewMode){el('saveButton').innerHTML='<i data-lucide="save"></i>Update Payment';el('saveButton').disabled=!has(3);}}else{fillAccounts({});el('paymentNo').value='Auto generated on save';el('paymentDate').value=o.data.today||new Date().toISOString().slice(0,10);el('saveButton').disabled=!has(2);if(preselectedExpense){lockedExpenseRef=preselectedExpense;setNativeValue(el('expenseRef'),preselectedExpense,expenseSelect);await loadContext();lockExpenseSelection(preselectedExpense);}}applyView();if(window.lucide)lucide.createIcons();}catch(e){el('saveButton').disabled=true;App.showError(e,'Unable to prepare Expense Payment.');}}
el('expenseRef').addEventListener('change',loadContext);el('paymentRows').addEventListener('input',function(e){if(e.target.matches('input'))schedulePreview();});el('paymentRows').addEventListener('change',schedulePreview);form.addEventListener('submit',async function(e){e.preventDefault();if(viewMode||saving||el('saveButton').disabled)return;if(!el('expenseRef').value||!el('paymentDate').value||actualPayment()<=0.001){App.showError(null,'Select Expense, Payment Date and enter Payment Amount.');return;}saving=true;el('saveButton').disabled=true;try{var r=await App.api('api/expense-payments.php',{method:'POST',body:payload('save')});showToast(r.message||'Expense Payment saved successfully.',{type:'success',duration:2});location.href='expense-payment-list.php';}catch(err){saving=false;el('saveButton').disabled=false;App.showError(err,'Unable to save Expense Payment.');}});load();
})();
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main></div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body></html>
