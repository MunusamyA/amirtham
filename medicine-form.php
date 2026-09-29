<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle='Medicine';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
<title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
<?php render_frontend_config_script(); ?>
<script src="assets/js/runtime.js"></script>
<link rel="stylesheet" href="assets/css/core.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/theme.css">
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="app-shell"><?php require __DIR__ . '/include/sidebar.php'; ?><main class="main-stage"><?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script><script src="assets/js/app.js"></script><script src="assets/js/theme.js"></script><script src="assets/js/layout.js"></script><script src="assets/js/validation.js"></script><script src="assets/js/global-select.js"></script>
<div class="page-head"><div><h1 id="pageHeading">Add Medicine</h1><p>Ayurveda Medicine Master with selling price for Prescription and Billing.</p></div><a class="btn gray" href="medicine-list.php"><i data-lucide="list"></i> Medicine List</a></div>
<div class="card form-card"><form id="medicineForm" novalidate>
<div class="card-header"><div><h2>Medicine Details</h2><p>Medicine Code is generated automatically.</p></div></div>
<div class="card-body">
<div class="form-row">
<div class="field col-3"><label>Medicine Code</label><input id="medicineCode" type="text" readonly></div>
<div class="field col-5"><label for="medicineName" class="required">Medicine Name</label><input id="medicineName" name="medicine_name" type="text" maxlength="255" required placeholder="Medicine Name" data-required-message="Medicine Name is required."></div>
<div class="field col-4"><label for="medicineType" class="required">Medicine Type</label><select id="medicineType" name="medicine_type" required data-required-message="Medicine Type is required."><option value="">Select Medicine Type</option><option>Kashayam</option><option>Churna</option><option>Tablet</option><option>Capsule</option><option>Lehya</option><option>Tailam</option><option>Ghrita</option><option>Arishta / Asava</option><option>Syrup</option><option>Powder</option><option>Oil</option><option>Other</option></select></div>
</div>
<div class="form-row">
<div class="field col-3"><label for="medicineUnit">Unit</label><input id="medicineUnit" name="unit" type="text" maxlength="100" placeholder="Bottle / Tablet / Pack"></div>
<div class="field col-3"><label for="packSize">Pack Size</label><input id="packSize" name="pack_size" type="text" maxlength="100" placeholder="200 ml / 100 gm / 10 Nos"></div>
<div class="field col-3"><label for="sellingPrice" class="required">Selling Price</label><input id="sellingPrice" name="selling_price" type="text" inputmode="decimal" required value="0.00" placeholder="0.00" data-required-message="Selling Price is required."></div>
<div class="field col-3"><label for="medicineStatus" class="required">Status</label><select id="medicineStatus" name="status" required><option value="1">Active</option><option value="0">Inactive</option></select></div>
</div>
<div class="form-row" id="auditRow" hidden><div class="field col-4"><label>Created By</label><input id="createdByName" type="text" readonly></div><div class="field col-4"><label>Created At</label><input id="createdAt" type="text" readonly></div><div class="field col-4"><label>Updated At</label><input id="updatedAt" type="text" readonly></div></div>
</div>
<div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="save"></i><span id="saveButtonText">Save Medicine</span></button><a class="btn gray" href="medicine-list.php">Cancel</a></div></div>
</form></div>
<script>
(function(window,document){'use strict';
var form=document.getElementById('medicineForm');var params=new URLSearchParams(location.search);var reference=params.get('ref')||'';var viewMode=params.get('view')==='1';var saveButton=document.getElementById('saveButton');var actions=[];
var typeSelect=GlobalSelect.init('#medicineType',{placeholder:'Select Medicine Type'});var statusSelect=GlobalSelect.init('#medicineStatus',{placeholder:'Select Status'});
function dataOf(r){return r&&r.data&&typeof r.data==='object'?r.data:{};}
function setSelect(instance,el,value){el.value=value==null?'':String(value);if(instance&&typeof instance.sync==='function')instance.sync();el.value=value==null?'':String(value);}
async function load(){try{if(reference){var response=await App.api('api/medicines.php?ref='+encodeURIComponent(reference));var data=dataOf(response),row=data.record||{};actions=(data.allowed_actions||[]).map(Number);document.getElementById('pageHeading').textContent=viewMode?'View Medicine':'Edit Medicine';document.getElementById('saveButtonText').textContent='Update Medicine';document.getElementById('medicineCode').value=row.medicine_code||'';form.medicine_name.value=row.medicine_name||'';setSelect(typeSelect,form.medicine_type,row.medicine_type||'');form.unit.value=row.unit||'';form.pack_size.value=row.pack_size||'';form.selling_price.value=row.selling_price||'0.00';setSelect(statusSelect,form.status,Number(row.status)===0?'0':'1');document.getElementById('createdByName').value=row.created_by_name||'-';document.getElementById('createdAt').value=row.created_at||'-';document.getElementById('updatedAt').value=row.updated_at||'-';document.getElementById('auditRow').hidden=false;if(!viewMode&&actions.indexOf(3)===-1)saveButton.disabled=true;}else{var response=await App.api('api/medicines.php?options=1');var data=dataOf(response);actions=(data.allowed_actions||[]).map(Number);document.getElementById('medicineCode').value=data.next_medicine_code||'';setSelect(statusSelect,form.status,'1');if(actions.indexOf(2)===-1)saveButton.disabled=true;}if(viewMode){Array.prototype.forEach.call(form.querySelectorAll('input,select,textarea'),function(el){el.disabled=true;});saveButton.hidden=true;}if(window.lucide)window.lucide.createIcons();}catch(error){saveButton.disabled=true;App.showError(error,'Unable to prepare Medicine form.');}}
form.addEventListener('submit',async function(event){event.preventDefault();if(viewMode)return;Validation.clearForm(form);if(!Validation.validateForm(form))return;var data=new FormData(form);data.set('action','save');if(reference)data.set('ref',reference);saveButton.disabled=true;try{var response=await App.api('api/medicines.php',{method:'POST',body:data});showToast(response.message||'Medicine saved successfully.',{type:'success',duration:2});setTimeout(function(){location.href='medicine-list.php';},650);}catch(error){Validation.applyErrors(form,error.errors||{});App.showError(error,'Unable to save Medicine.');saveButton.disabled=false;}});
load();
})(window,document);
</script>
</section><?php require __DIR__ . '/include/footer.php'; ?></main></div><script src="assets/js/appearance.js"></script><script>if(window.lucide){window.lucide.createIcons();}</script></body></html>
