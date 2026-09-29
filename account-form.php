<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Account Form';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
<title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
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

<div class="page-head">
  <div>
    <h1 id="pageHeading">Add Account</h1>
    <p>Maintain only your own branch cash and bank accounts.</p>
  </div>
  <a class="btn gray" href="account-list.php"><i data-lucide="list"></i> Account List</a>
</div>

<div class="card form-card">
<form id="accountForm" novalidate>
<input type="hidden" name="ref" id="accountRef">
<div class="card-header"><div><h2>Account Details</h2><p>Bank-only fields appear automatically when Account Type is Bank.</p></div></div>
<div class="card-body">
  <div class="form-row">
    <div class="field col-4">
      <label for="accountCode">Account Code</label>
      <input id="accountCode" name="account_code" type="text" readonly aria-readonly="true" placeholder="Auto generated">
    </div>
    <div class="field col-4">
      <label for="accountName" class="required">Account Name</label>
      <input id="accountName" name="account_name" type="text" maxlength="150" required placeholder="Example: HDFC Main Current Account" data-required-message="Account Name is required.">
    </div>
    <div class="field col-4">
      <label for="accountType" class="required">Account Type</label>
      <select id="accountType" name="account_type" required data-required-message="Account Type is required.">
        <option value="">Select Account Type</option>
        <option value="1">Cash</option>
        <option value="2">Bank</option>
      </select>
    </div>
  </div>

  <div id="bankFields" hidden>
    <div class="form-row">
      <div class="field col-4">
        <label for="bankAccountType" class="required">Bank Account Type</label>
        <select id="bankAccountType" name="bank_account_type" data-required-message="Bank Account Type is required.">
          <option value="">Select Type</option>
          <option value="1">Current Account</option>
          <option value="2">Savings Account</option>
          <option value="3">Other</option>
        </select>
      </div>
      <div class="field col-4">
        <label for="bankName" class="required">Bank Name</label>
        <input id="bankName" name="bank_name" type="text" maxlength="150" placeholder="Enter bank name" data-required-message="Bank Name is required.">
      </div>
      <div class="field col-4">
        <label for="accountNumber" class="required">Account Number</label>
        <input id="accountNumber" name="account_number" type="text" maxlength="100" inputmode="numeric" autocomplete="off" placeholder="Enter account number" data-required-message="Account Number is required.">
      </div>
    </div>
    <div class="form-row">
      <div class="field col-4">
        <label for="ifscCode">IFSC Code</label>
        <input id="ifscCode" name="ifsc_code" type="text" maxlength="11" placeholder="Example: HDFC0001234">
      </div>
      <div class="field col-4">
        <label for="upiId">UPI ID</label>
        <input id="upiId" name="upi_id" type="text" maxlength="150" placeholder="Example: company@hdfc">
      </div>
    </div>
  </div>

  <div class="form-row">
    <div class="field col-4">
      <label for="openingBalance">Opening Balance</label>
      <input id="openingBalance" name="opening_balance" type="text" inputmode="decimal" placeholder="0.00" data-validation="decimal" data-decimal-places="2">
    </div>
    <div class="field col-4">
      <label for="openingBalanceDate">Opening Balance Date</label>
      <input id="openingBalanceDate" name="opening_balance_date" type="date">
    </div>
    <div class="field col-4" id="defaultCashField" hidden>
      <label for="isDefaultCash">Default Cash Account</label>
      <select id="isDefaultCash" name="is_default_cash">
        <option value="0">No</option>
        <option value="1">Yes</option>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="field col-4">
      <label for="status" class="required">Status</label>
      <select id="status" name="status" required>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>
  </div>
</div>
<div class="card-footer" style="display:flex;justify-content:flex-end;gap:8px;">
  <a class="btn gray" href="account-list.php">Cancel</a>
  <button class="btn btn-primary" type="submit" id="saveButton"><i data-lucide="save"></i> Save Account</button>
</div>
</form>
</div>

<script>
(function(){
  "use strict";
  var form=document.getElementById("accountForm");
  var saveButton=document.getElementById("saveButton");
  var bankFields=document.getElementById("bankFields");
  var defaultCashField=document.getElementById("defaultCashField");
  var reference=new URLSearchParams(location.search).get("ref")||"";

  function hasAction(actions,id){ return (actions||[]).map(Number).indexOf(Number(id))!==-1; }
  function toggleConditional(){
    var type=String(form.account_type.value||"");
    var isBank=type==="2", isCash=type==="1";
    bankFields.hidden=!isBank; defaultCashField.hidden=!isCash;
    [form.bank_account_type,form.bank_name,form.account_number].forEach(function(el){ if(el) el.required=isBank; });
    if(!isBank){ form.bank_account_type.value=""; form.bank_name.value=""; form.account_number.value=""; form.ifsc_code.value=""; form.upi_id.value=""; }
    if(!isCash) form.is_default_cash.value="0";
  }

  form.account_type.addEventListener("change",toggleConditional);
  form.ifsc_code.addEventListener("input",function(){ this.value=this.value.toUpperCase().replace(/\s+/g,""); });

  function fill(a){
    document.getElementById("accountRef").value=a.ref||"";
    form.account_code.value=a.account_code||"";
    form.account_name.value=a.account_name||"";
    form.account_type.value=String(a.account_type||"");
    toggleConditional();
    form.bank_account_type.value=a.bank_account_type==null?"":String(a.bank_account_type);
    form.bank_name.value=a.bank_name||"";
    form.account_number.value=a.account_number||"";
    form.ifsc_code.value=a.ifsc_code||"";
    form.upi_id.value=a.upi_id||"";
    form.opening_balance.value=a.opening_balance==null?"":String(Number(a.opening_balance));
    form.opening_balance_date.value=a.opening_balance_date||"";
    form.is_default_cash.value=String(Number(a.is_default_cash||0));
    form.status.value=String(Number(a.status)===0?0:1);
  }

  async function load(){
    try{
      if(reference){
        document.getElementById("pageHeading").textContent="Edit Account";
        saveButton.innerHTML='<i data-lucide="save"></i> Update Account';
        var r=await App.api("api/accounts.php?ref="+encodeURIComponent(reference)); fill(r.data.account);
        if(!hasAction(r.data.allowed_actions,3)) saveButton.disabled=true;
      }else{
        var r=await App.api("api/accounts.php?options=1"); form.account_code.value=r.data.next_account_code||"";
        if(!hasAction(r.data.allowed_actions,2)) saveButton.disabled=true;
      }
      toggleConditional(); if(window.lucide) window.lucide.createIcons();
    }catch(e){ saveButton.disabled=true; App.showError(e,"Unable to load account form."); }
  }

  form.addEventListener("submit",async function(ev){
    ev.preventDefault(); Validation.clearForm(form); toggleConditional();
    if(!Validation.validateForm(form)) return;
    var fd=new FormData(form); if(reference) fd.set("_method","PUT");
    saveButton.disabled=true;
    try{
      var r=await App.api("api/accounts.php",{method:"POST",body:fd});
      showToast(r.message,{type:"success",duration:2}); setTimeout(function(){location.href="account-list.php";},600);
    }catch(e){ Validation.applyErrors(form,e.errors||{}); App.showError(e,"Unable to save account."); saveButton.disabled=false; }
  });

  load();
})();
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main></div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body></html>
