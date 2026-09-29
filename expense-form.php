<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Expense';
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
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <h1 id="pageHeading">Add Expense</h1>
            <span class="dt-status inactive" id="nonGstBadge" hidden>NON GST</span>
        </div>
        <p>Press <strong>Ctrl + Shift + U</strong> to switch GST / NON GST. Payment Status is calculated automatically from actual Expense Payments.</p>
    </div>
    <div class="buttons">
        <a class="btn gray" href="expense-list.php"><i data-lucide="list"></i>Expense List</a>
        <a class="btn btn-primary" id="makePaymentButton" href="#" hidden><i data-lucide="hand-coins"></i>Make Payment</a>
    </div>
</div>

<div class="card form-card" id="expenseCard">
<form id="expenseForm" novalidate>
    <div class="card-header">
        <div>
            <h2>Expense Information</h2>
            <p id="formModeText">Save as Draft or Save &amp; Post. Optional Cash / UPI / Bank / Cheque payment entered below is posted only with Save &amp; Post.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="card-section-title">Basic Information</div>
        <div class="form-row">
            <div class="field col-3">
                <label for="expenseNo">Expense No</label>
                <input id="expenseNo" type="text" readonly value="Auto generated">
            </div>
            <div class="field col-3">
                <label for="expenseDate" class="required">Expense Date</label>
                <input id="expenseDate" type="date" required>
            </div>
            <div class="field col-3">
                <label for="expenseCategoryId" class="required">Expense Category</label>
                <select id="expenseCategoryId" required><option value="">Select Category</option></select>
            </div>
            <div class="field col-3">
                <label for="payeeName" class="required">Payee Name</label>
                <input id="payeeName" type="text" maxlength="150" autocomplete="off" placeholder="Payee / Vendor" required>
            </div>
        </div>

        <div class="form-row">
            <div class="field col-3">
                <label for="invoiceNo">Invoice / Bill No</label>
                <input id="invoiceNo" type="text" maxlength="100" autocomplete="off" placeholder="Optional">
            </div>
            <div class="field col-3">
                <label for="invoiceDate">Invoice / Bill Date</label>
                <input id="invoiceDate" type="date">
            </div>
            <div class="field col-3 gst-only">
                <label for="payeeGstin">Payee GSTIN</label>
                <input id="payeeGstin" type="text" maxlength="15" autocomplete="off" placeholder="15 character GSTIN">
            </div>
            <div class="field col-3 gst-only">
                <label for="payeeStateCode">Payee State Code</label>
                <input id="payeeStateCode" type="text" inputmode="numeric" maxlength="2" autocomplete="off" placeholder="2 digits">
            </div>
        </div>

        <div class="card-section-title">Amount &amp; Tax</div>
        <div class="form-row">
            <div class="field col-3">
                <label for="taxableAmount" class="required">Expense Amount</label>
                <input id="taxableAmount" type="text" inputmode="decimal" placeholder="0.00" required>
            </div>
            <div class="field col-3 gst-only">
                <label for="gstRate">GST Rate (%)</label>
                <input id="gstRate" type="text" inputmode="decimal" placeholder="0.000">
            </div>
            <div class="field col-6">
                <label>Tax Information</label>
                <div class="muted" id="taxInfo">GST mode. Enter Payee State Code or GSTIN to determine CGST/SGST or IGST.</div>
            </div>
        </div>

        <div class="card-section-title">Payment (Optional)</div>
        <div id="paymentEntryArea">
            <div class="form-row">
                <div class="field col-3">
                    <label for="paymentDate">Payment Date</label>
                    <input id="paymentDate" type="date">
                </div>
                <div class="field col-9">
                    <label>Payment Information</label>
                    <div class="muted">Payment is created only when you click <strong>Save &amp; Post</strong>. Saving Draft does not create any cash/bank movement.</div>
                </div>
            </div>

            <div class="card table-card app-allocation-wrap">
                <table class="app-allocation-table" id="expensePaymentTable">
                    <thead>
                    <tr>
                        <th>Mode</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th>Reference No</th>
                        <th>Cheque No</th>
                        <th>Cheque Date</th>
                    </tr>
                    </thead>
                    <tbody id="paymentRows">
                    <tr class="payment-row" data-mode="1">
                        <td><strong>Cash</strong></td>
                        <td><select class="pay-account"><option value="">Select Cash Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><span class="muted">—</span><input class="pay-reference" type="hidden" value=""></td>
                        <td><span class="muted">—</span><input class="pay-cheque-no" type="hidden" value=""></td>
                        <td><span class="muted">—</span><input class="pay-cheque-date" type="hidden" value=""></td>
                    </tr>
                    <tr class="payment-row" data-mode="2">
                        <td><strong>UPI</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="150" placeholder="UPI / UTR No"></td>
                        <td><span class="muted">—</span><input class="pay-cheque-no" type="hidden" value=""></td>
                        <td><span class="muted">—</span><input class="pay-cheque-date" type="hidden" value=""></td>
                    </tr>
                    <tr class="payment-row" data-mode="3">
                        <td><strong>Bank</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="150" placeholder="Transaction / Ref No"></td>
                        <td><span class="muted">—</span><input class="pay-cheque-no" type="hidden" value=""></td>
                        <td><span class="muted">—</span><input class="pay-cheque-date" type="hidden" value=""></td>
                    </tr>
                    <tr class="payment-row" data-mode="4">
                        <td><strong>Cheque</strong></td>
                        <td><select class="pay-account"><option value="">Select Bank Account</option></select></td>
                        <td><input class="pay-amount" type="text" inputmode="decimal" placeholder="0.00"></td>
                        <td><input class="pay-reference" type="text" maxlength="150" placeholder="Optional Reference"></td>
                        <td><input class="pay-cheque-no" type="text" maxlength="100" placeholder="Cheque No"></td>
                        <td><input class="pay-cheque-date" type="date"></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-split-grid">
            <div class="app-side-card">
                <div class="app-side-card-head">Tax Summary</div>
                <div class="app-side-card-body">
                    <div class="app-summary-row"><span>Expense Amount</span><strong id="sumTaxable">₹0.00</strong></div>
                    <div class="app-summary-row gst-only"><span>CGST</span><strong id="sumCgst">₹0.00</strong></div>
                    <div class="app-summary-row gst-only"><span>SGST</span><strong id="sumSgst">₹0.00</strong></div>
                    <div class="app-summary-row gst-only"><span>IGST</span><strong id="sumIgst">₹0.00</strong></div>
                    <div class="app-summary-row total"><span>TOTAL EXPENSE</span><strong id="sumTotal">₹0.00</strong></div>
                </div>
            </div>
            <div class="app-side-card">
                <div class="app-side-card-head">Payment Position</div>
                <div class="app-side-card-body">
                    <div class="app-summary-row"><span>Total Expense</span><strong id="positionTotal">₹0.00</strong></div>
                    <div class="app-summary-row"><span id="positionPaidLabel">Paid Now</span><strong id="positionPaid">₹0.00</strong></div>
                    <div class="app-summary-row"><span>Balance Amount</span><strong id="positionBalance">₹0.00</strong></div>
                    <div class="app-summary-row total"><span>Payment Status</span><strong id="positionStatus">Unpaid</strong></div>
                </div>
            </div>
        </div>

        <div class="card-section-title">Description</div>
        <div class="field">
            <label for="description">Description / Remarks</label>
            <textarea id="description" rows="3" maxlength="255" placeholder="Optional expense description"></textarea>
        </div>
    </div>
    <div class="card-footer">
        <div class="buttons">
            <a class="btn gray" href="expense-list.php">Cancel</a>
            <button class="btn gray" id="saveDraftButton" type="button"><i data-lucide="save"></i>Save Draft</button>
            <button class="btn btn-primary" id="savePostButton" type="button"><i data-lucide="check-circle"></i>Save &amp; Post</button>
        </div>
    </div>
</form>
</div>

<script>
(function(window,document){
'use strict';
var params=new URLSearchParams(location.search),ref=params.get('ref')||'',viewParam=params.get('view')==='1';
var form=document.getElementById('expenseForm'),actions=[],paymentActions=[],branch={},categorySelect=null,taxMode=1,posted=false,loadedPaid=0,loadedBalance=0,saving=false,accounts=[],accountSelects=[];
function el(id){return document.getElementById(id);}function n(v){var x=Number(String(v==null?'':v).replace(/,/g,'').trim()||0);return Number.isFinite(x)?x:0;}function r2(v){return Math.round((n(v)+Number.EPSILON)*100)/100;}function money(v){return'₹'+n(v).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}function has(id){return actions.map(Number).indexOf(Number(id))!==-1;}function hasPayment(id){return paymentActions.map(Number).indexOf(Number(id))!==-1;}function refresh(){if(categorySelect&&categorySelect.refresh)categorySelect.refresh();}function refreshGs(gs){if(gs&&gs.refresh)gs.refresh();}
function setCategories(rows,selected){var s=el('expenseCategoryId');s.innerHTML='<option value="">Select Category</option>';(rows||[]).forEach(function(row){var o=document.createElement('option');o.value=row.id;o.textContent=(row.category_code?row.category_code+' - ':'')+row.category_name+(Number(row.status)===0?' (Inactive)':'');s.appendChild(o);});if(selected)s.value=String(selected);refresh();}
function gstinState(){var g=String(el('payeeGstin').value||'').trim().toUpperCase();if(/^[0-9]{2}/.test(g)&&!String(el('payeeStateCode').value||'').trim())el('payeeStateCode').value=g.slice(0,2);}
function setTaxMode(mode){taxMode=Number(mode)===0?0:1;el('nonGstBadge').hidden=taxMode!==0;document.querySelectorAll('.gst-only').forEach(function(node){node.hidden=taxMode===0;});calculate();}
function toggleTaxMode(){if(posted)return;if(n(el('taxableAmount').value)>0||n(el('gstRate').value)>0){if(!window.confirm('Changing tax mode will recalculate the Expense amount. Continue?'))return;}setTaxMode(taxMode===0?1:0);}
function collectPayments(){var rows=[];document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var amount=n(row.querySelector('.pay-amount').value);if(amount<=0)return;rows.push({payment_mode:Number(row.dataset.mode),account_id:row.querySelector('.pay-account').value,amount:r2(amount),reference_no:row.querySelector('.pay-reference').value.trim(),cheque_no:row.querySelector('.pay-cheque-no').value.trim(),cheque_date:row.querySelector('.pay-cheque-date').value});});return rows;}
function paymentTotal(){var total=0;collectPayments().forEach(function(row){total+=n(row.amount);});return r2(total);}
function fillAccounts(){accountSelects=[];document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var mode=Number(row.dataset.mode),s=row.querySelector('.pay-account'),wanted=mode===1?1:2,defaultId='';s.innerHTML='<option value="">'+(wanted===1?'Select Cash Account':'Select Bank Account')+'</option>';accounts.filter(function(a){return Number(a.account_type)===wanted;}).forEach(function(a){var o=document.createElement('option');o.value=a.id;o.textContent=(a.account_code?a.account_code+' - ':'')+a.account_name;s.appendChild(o);if(mode===1&&Number(a.is_default_cash)===1)defaultId=String(a.id);});if(defaultId)s.value=defaultId;if(window.GlobalSelect)accountSelects.push(GlobalSelect.init(s,{placeholder:wanted===1?'Select Cash Account':'Select Bank Account'}));});}
function calculate(){gstinState();var base=Math.max(0,r2(el('taxableAmount').value)),rate=taxMode===1?Math.max(0,n(el('gstRate').value)):0,cgst=0,sgst=0,igst=0;var bs=String(branch.state_code||''),ps=String(el('payeeStateCode').value||'').trim();if(taxMode===1&&rate>0&&bs&&ps){if(bs===ps){cgst=r2(base*(rate/2)/100);sgst=r2(base*(rate-rate/2)/100);el('taxInfo').textContent='Intra-state GST: CGST + SGST.';}else{igst=r2(base*rate/100);el('taxInfo').textContent='Inter-state GST: IGST.';}}else if(taxMode===0){el('taxInfo').textContent='NON GST mode. GST amounts are zero.';}else{el('taxInfo').textContent='GST mode. Enter Payee State Code or GSTIN to determine CGST/SGST or IGST.';}var total=r2(base+cgst+sgst+igst);el('sumTaxable').textContent=money(base);el('sumCgst').textContent=money(cgst);el('sumSgst').textContent=money(sgst);el('sumIgst').textContent=money(igst);el('sumTotal').textContent=money(total);el('positionTotal').textContent=money(total);var paid=posted?loadedPaid:paymentTotal(),balance=posted?loadedBalance:Math.max(0,r2(total-paid)),status=paid<=0.001?'Unpaid':(balance<=0.01?'Paid':'Partially Paid');el('positionPaidLabel').textContent=posted?'Paid Amount':'Paid Now';el('positionPaid').textContent=money(paid);el('positionBalance').textContent=money(balance);el('positionStatus').textContent=status;}
function payload(posting){return{action:'save',ref:ref,posting_status:posting,expense_category_id:el('expenseCategoryId').value,expense_date:el('expenseDate').value,payee_name:el('payeeName').value.trim(),invoice_no:el('invoiceNo').value.trim(),invoice_date:el('invoiceDate').value,payee_gstin:el('payeeGstin').value.trim().toUpperCase(),payee_state_code:el('payeeStateCode').value.trim(),tax_mode:taxMode,gst_rate:el('gstRate').value.trim(),taxable_amount:el('taxableAmount').value.trim(),description:el('description').value.trim(),payment_date:el('paymentDate').value,payments:collectPayments()};}
function setReadOnly(){form.querySelectorAll('input,select,textarea').forEach(function(c){if(c.id!=='expenseNo')c.disabled=true;});el('saveDraftButton').hidden=true;el('savePostButton').hidden=true;refresh();accountSelects.forEach(refreshGs);}
function applyRecord(x,categories){posted=Number(x.posting_status)===1;loadedPaid=n(x.paid_amount);loadedBalance=n(x.balance_amount);el('expenseNo').value=x.expense_no||'';el('expenseDate').value=x.expense_date||'';setCategories(categories,x.expense_category_id);el('payeeName').value=x.payee_name||'';el('invoiceNo').value=x.invoice_no||'';el('invoiceDate').value=x.invoice_date||'';el('payeeGstin').value=x.payee_gstin||'';el('payeeStateCode').value=x.payee_state_code||'';el('gstRate').value=n(x.gst_rate)>0?String(n(x.gst_rate)):'';el('taxableAmount').value=n(x.taxable_amount)>0?String(n(x.taxable_amount)):'';el('description').value=x.description||'';if(!el('paymentDate').value)el('paymentDate').value=x.expense_date||'';setTaxMode(Number(x.tax_mode));if(posted){el('pageHeading').textContent='View Expense';el('formModeText').textContent='Posted Expense is read-only. Use Make Payment for the remaining balance.';el('paymentEntryArea').hidden=true;if(loadedBalance>0.001&&hasPayment(2)){el('makePaymentButton').href='expense-payment.php?expense='+encodeURIComponent(ref)+'&lock=1';el('makePaymentButton').hidden=false;}setReadOnly();}else{el('pageHeading').textContent='Edit Expense Draft';el('saveDraftButton').disabled=!has(3);el('savePostButton').disabled=!has(3);el('paymentEntryArea').hidden=!hasPayment(2);if(viewParam)setReadOnly();}calculate();}
function validatePayment(total){var pay=paymentTotal();if(pay>total+0.001){App.showError(null,'Payment Amount cannot exceed Total Expense.');return false;}if(pay>0.001&&!el('paymentDate').value){App.showError(null,'Payment Date is required when Payment Amount is entered.');return false;}var valid=true;document.querySelectorAll('#paymentRows .payment-row').forEach(function(row){var amount=n(row.querySelector('.pay-amount').value);if(amount<=0)return;if(!row.querySelector('.pay-account').value)valid=false;if(Number(row.dataset.mode)===4&&(!row.querySelector('.pay-cheque-no').value.trim()||!row.querySelector('.pay-cheque-date').value))valid=false;});if(!valid){App.showError(null,'Select Account for each Payment Amount. Cheque payment also requires Cheque No and Cheque Date.');return false;}return true;}
async function save(posting){if(saving||posted)return;calculate();var total=n(String(el('sumTotal').textContent).replace(/[^0-9.-]/g,''));if(!el('expenseDate').value||!el('expenseCategoryId').value||!el('payeeName').value.trim()||n(el('taxableAmount').value)<=0){App.showError(null,'Enter Expense Date, Category, Payee and Expense Amount.');return;}if(posting===1&&!validatePayment(total))return;if(posting===1&&!window.confirm('Post this Expense? The Expense becomes read-only and any entered Payment will be posted to the selected Cash/Bank Account.'))return;saving=true;el('saveDraftButton').disabled=true;el('savePostButton').disabled=true;try{var r=await App.api('api/expenses.php',{method:'POST',body:payload(posting)});showToast(r.message||'Expense saved successfully.',{type:'success',duration:2});location.href='expense-list.php';}catch(e){saving=false;el('saveDraftButton').disabled=false;el('savePostButton').disabled=false;App.showError(e,'Unable to save Expense.');}}
async function load(){try{if(window.GlobalSelect)categorySelect=GlobalSelect.init(el('expenseCategoryId'),{placeholder:'Select Expense Category'});var o=await App.api('api/expenses.php?options=1');actions=(o.data.allowed_actions||[]).map(Number);paymentActions=(o.data.payment_actions||[]).map(Number);branch=o.data.branch||{};accounts=o.data.accounts||[];setCategories(o.data.categories||[],null);fillAccounts();if(!hasPayment(2))el('paymentEntryArea').hidden=true;if(ref){var r=await App.api('api/expenses.php?ref='+encodeURIComponent(ref));actions=(r.data.allowed_actions||actions).map(Number);paymentActions=(r.data.payment_actions||paymentActions).map(Number);branch=r.data.branch||branch;accounts=r.data.accounts||accounts;applyRecord(r.data.expense||{},r.data.categories||[]);}else{el('expenseNo').value=o.data.next_expense_no||'Auto generated';el('expenseDate').value=o.data.today||new Date().toISOString().slice(0,10);el('paymentDate').value=el('expenseDate').value;el('saveDraftButton').disabled=!has(2);el('savePostButton').disabled=!has(2);setTaxMode(1);calculate();}if(window.lucide)lucide.createIcons();}catch(e){el('saveDraftButton').disabled=true;el('savePostButton').disabled=true;App.showError(e,'Unable to prepare Expense Form.');}}
document.addEventListener('keydown',function(event){if(event.ctrlKey&&event.shiftKey&&String(event.key).toLowerCase()==='u'){event.preventDefault();toggleTaxMode();}});
['taxableAmount','gstRate','payeeStateCode','payeeGstin'].forEach(function(id){el(id).addEventListener('input',calculate);});el('payeeGstin').addEventListener('change',calculate);el('expenseDate').addEventListener('change',function(){if(!el('paymentDate').value)el('paymentDate').value=el('expenseDate').value;});el('paymentRows').addEventListener('input',function(e){if(e.target.matches('input'))calculate();});el('paymentRows').addEventListener('change',calculate);el('saveDraftButton').addEventListener('click',function(){save(0);});el('savePostButton').addEventListener('click',function(){save(1);});load();
})(window,document);
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
