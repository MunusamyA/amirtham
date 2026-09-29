<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Patient Form';
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
        <h1 id="pageHeading">Add Patient</h1>
        <p id="pageDescription">Create a new Clinic Patient record.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn gray" href="patient-list.php"><i data-lucide="list"></i>Patient List</a>
        <a class="btn btn-primary" id="profileButton" href="#" hidden><i data-lucide="user-round"></i>Patient Profile</a>
    </div>
</div>

<div class="card form-card">
<form id="patientForm" novalidate>
<div class="card-header"><div><h2>Patient Information</h2><p>Maintain Patient personal and contact details.</p></div></div>
<div class="card-body">

<div class="card-section-title">Basic Details</div>
<div class="form-row">
    <div class="field col-3">
        <label>Patient Code</label>
        <input id="patientCode" type="text" readonly aria-readonly="true" placeholder="Auto generated">
    </div>
    <div class="field col-5">
        <label for="patientName" class="required">Patient Name</label>
        <input id="patientName" name="patient_name" type="text" maxlength="150" required
               placeholder="Enter Patient Name" data-required-message="Patient Name is required.">
    </div>
    <div class="field col-2">
        <label for="dateOfBirth">Date of Birth</label>
        <input id="dateOfBirth" name="date_of_birth" type="date">
    </div>
    <div class="field col-2">
        <label>Age</label>
        <input id="patientAge" type="text" readonly aria-readonly="true" placeholder="-">
    </div>
</div>

<div class="form-row">
    <div class="field col-3">
        <label for="gender">Gender</label>
        <select id="gender" name="gender">
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <div class="field col-3">
        <label for="bloodGroup">Blood Group</label>
        <select id="bloodGroup" name="blood_group">
            <option value="">Select Blood Group</option>
            <option value="A+">A+</option><option value="A-">A-</option>
            <option value="B+">B+</option><option value="B-">B-</option>
            <option value="AB+">AB+</option><option value="AB-">AB-</option>
            <option value="O+">O+</option><option value="O-">O-</option>
        </select>
    </div>
    <div class="field col-3">
        <label for="identityNumber">Identity Number</label>
        <input id="identityNumber" name="identity_number" type="text" maxlength="100" placeholder="Optional">
    </div>
    <div class="field col-3">
        <label for="patientStatus" class="required">Status</label>
        <select id="patientStatus" name="status" required>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
</div>

<div class="card-section-title">Contact Details</div>
<div class="form-row">
    <div class="field col-3">
        <label for="mobile" class="required">Mobile</label>
        <input id="mobile" name="mobile" type="text" inputmode="numeric" maxlength="10" required
               placeholder="10-digit mobile number" data-regex="^[0-9]{10}$"
               data-required-message="Mobile Number is required."
               data-regex-message="Enter a valid 10-digit Mobile Number.">
    </div>
    <div class="field col-3">
        <label for="alternateMobile">Alternate Mobile</label>
        <input id="alternateMobile" name="alternate_mobile" type="text" inputmode="numeric" maxlength="10"
               placeholder="Optional" data-regex="^[0-9]{10}$"
               data-regex-message="Enter a valid 10-digit Alternate Mobile Number.">
    </div>
    <div class="field col-6">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="190" placeholder="Optional" data-validation="email">
    </div>
</div>

<div class="form-row">
    <div class="field col-12">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="3" placeholder="Enter Patient Address"></textarea>
    </div>
</div>

<div class="card-section-title">Emergency Contact</div>
<div class="form-row">
    <div class="field col-6">
        <label for="emergencyName">Emergency Contact Name</label>
        <input id="emergencyName" name="emergency_contact_name" type="text" maxlength="150" placeholder="Optional">
    </div>
    <div class="field col-6">
        <label for="emergencyMobile">Emergency Contact Mobile</label>
        <input id="emergencyMobile" name="emergency_contact_mobile" type="text" inputmode="numeric" maxlength="10"
               placeholder="Optional" data-regex="^[0-9]{10}$"
               data-regex-message="Enter a valid 10-digit Emergency Contact Mobile.">
    </div>
</div>

<div class="card-section-title">Medical Notes</div>
<div class="form-row">
    <div class="field col-6">
        <label for="knownAllergies">Known Allergies</label>
        <textarea id="knownAllergies" name="known_allergies" rows="3" placeholder="Optional"></textarea>
    </div>
    <div class="field col-6">
        <label for="medicalConditions">Existing Medical Conditions</label>
        <textarea id="medicalConditions" name="medical_conditions" rows="3" placeholder="Optional"></textarea>
    </div>
</div>

<div class="form-row" id="auditRow" hidden>
    <div class="field col-4"><label>Created By</label><input id="createdByName" type="text" readonly></div>
    <div class="field col-4"><label>Created At</label><input id="createdAt" type="text" readonly></div>
    <div class="field col-4"><label>Updated At</label><input id="updatedAt" type="text" readonly></div>
</div>

</div>
<div class="card-footer">
    <a class="btn gray" href="patient-list.php"><i data-lucide="x"></i>Cancel</a>
    <button class="btn btn-primary" id="saveButton" type="submit"><i data-lucide="save"></i><span id="saveButtonText">Save Patient</span></button>
</div>
</form>
</div>

<script>
(function () {
    'use strict';

    var form=document.getElementById('patientForm');
    var ref=new URLSearchParams(window.location.search).get('ref')||'';
    var actions=[],saving=false;
    var genderSelect=null,bloodGroupSelect=null,statusSelect=null;

    function el(id){return document.getElementById(id);}
    function has(id){return actions.map(Number).indexOf(Number(id))!==-1;}
    function setSelectValue(select,value,instance){
        select.value=value==null?'':String(value);
        select.dispatchEvent(new Event('change',{bubbles:true}));
        if(instance&&typeof instance.sync==='function') instance.sync();
    }
    function calculateAge(){
        var value=String(el('dateOfBirth').value||'').trim();
        if(!value){el('patientAge').value='';return;}
        var dob=new Date(value+'T00:00:00'),today=new Date();
        if(Number.isNaN(dob.getTime())||dob>today){el('patientAge').value='';return;}
        var age=today.getFullYear()-dob.getFullYear();
        var m=today.getMonth()-dob.getMonth();
        if(m<0||(m===0&&today.getDate()<dob.getDate())) age--;
        el('patientAge').value=age>=0?String(age):'';
    }
    function extractErrors(error){
        if(error&&error.errors&&typeof error.errors==='object') return error.errors;
        if(error&&error.data&&error.data.errors&&typeof error.data.errors==='object') return error.data.errors;
        return {};
    }
    function normalize(){
        ['patientName','mobile','alternateMobile','email','address','emergencyName','emergencyMobile','knownAllergies','medicalConditions','identityNumber']
        .forEach(function(id){el(id).value=String(el(id).value||'').trim();});
    }
    function collect(){
        return {
            action:'save',ref:ref||undefined,
            patient_name:el('patientName').value.trim(),
            date_of_birth:el('dateOfBirth').value,
            gender:el('gender').value,
            mobile:el('mobile').value.trim(),
            alternate_mobile:el('alternateMobile').value.trim(),
            email:el('email').value.trim(),
            address:el('address').value.trim(),
            emergency_contact_name:el('emergencyName').value.trim(),
            emergency_contact_mobile:el('emergencyMobile').value.trim(),
            blood_group:el('bloodGroup').value,
            known_allergies:el('knownAllergies').value.trim(),
            medical_conditions:el('medicalConditions').value.trim(),
            identity_number:el('identityNumber').value.trim(),
            status:el('patientStatus').value
        };
    }
    function applyRecord(p){
        el('patientCode').value=p.patient_code||'';
        el('patientName').value=p.patient_name||'';
        el('dateOfBirth').value=p.date_of_birth||'';
        calculateAge();
        setSelectValue(el('gender'),p.gender||'',genderSelect);
        el('mobile').value=p.mobile||'';
        el('alternateMobile').value=p.alternate_mobile||'';
        el('email').value=p.email||'';
        el('address').value=p.address||'';
        el('emergencyName').value=p.emergency_contact_name||'';
        el('emergencyMobile').value=p.emergency_contact_mobile||'';
        setSelectValue(el('bloodGroup'),p.blood_group||'',bloodGroupSelect);
        el('knownAllergies').value=p.known_allergies||'';
        el('medicalConditions').value=p.medical_conditions||'';
        el('identityNumber').value=p.identity_number||'';
        setSelectValue(el('patientStatus'),Number(p.status)===0?'0':'1',statusSelect);
        el('createdByName').value=p.created_by_name||'-';
        el('createdAt').value=p.created_at||'-';
        el('updatedAt').value=p.updated_at||'-';
        el('auditRow').hidden=false;
        if(p.ref){
            el('profileButton').href='patient-profile.php?ref='+encodeURIComponent(p.ref);
            el('profileButton').hidden=false;
        }
    }

    async function load(){
        try{
            if(window.GlobalSelect){
                genderSelect=GlobalSelect.init(el('gender'),{placeholder:'Select Gender'});
                bloodGroupSelect=GlobalSelect.init(el('bloodGroup'),{placeholder:'Select Blood Group'});
                statusSelect=GlobalSelect.init(el('patientStatus'),{placeholder:'Select Status'});
            }

            var options=await App.api('api/patients.php?options=1');
            actions=(options.data.allowed_actions||[]).map(Number);

            if(!ref){
                el('patientCode').value=options.data.next_patient_code||'';
                setSelectValue(el('patientStatus'),'1',statusSelect);
                el('saveButton').disabled=!has(2);
            }else{
                var response=await App.api('api/patients.php?ref='+encodeURIComponent(ref));
                actions=(response.data.allowed_actions||actions).map(Number);
                applyRecord(response.data.record||{});
                el('pageHeading').textContent='Edit Patient';
                el('pageDescription').textContent='Update Clinic Patient details.';
                el('saveButtonText').textContent='Update Patient';
                el('saveButton').disabled=!has(3);
            }

            if(window.Validation&&typeof Validation.init==='function') Validation.init(form);
            if(window.lucide) window.lucide.createIcons();
        }catch(error){
            el('saveButton').disabled=true;
            App.showError(error,'Unable to prepare Patient Form.');
        }
    }

    el('dateOfBirth').addEventListener('change',calculateAge);

    form.addEventListener('submit',async function(event){
        event.preventDefault();
        if(saving) return;

        if(window.Validation&&typeof Validation.clearForm==='function') Validation.clearForm(form);
        normalize();
        calculateAge();

        if(window.Validation&&typeof Validation.validateForm==='function'){
            if(!Validation.validateForm(form)) return;
        }

        saving=true;
        el('saveButton').disabled=true;

        try{
            var response=await App.api('api/patients.php',{method:'POST',body:collect()});
            showToast(response.message||'Patient saved successfully.',{type:'success',duration:2});
            window.location.href='patient-list.php';
        }catch(error){
            saving=false;
            el('saveButton').disabled=false;
            if(window.Validation&&typeof Validation.applyErrors==='function'){
                Validation.applyErrors(form,extractErrors(error));
            }
            App.showError(error,'Unable to save Patient.');
        }
    });

    load();
})();
</script>
</section>
<?php require __DIR__ . '/include/footer.php'; ?>
</main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
