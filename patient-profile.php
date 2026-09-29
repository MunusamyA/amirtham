<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Patient Profile';
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

<div class="page-head">
    <div>
        <h1 id="profileHeading">Patient Profile</h1>
        <p id="profileDescription">View Clinic Patient details.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn gray" href="patient-list.php"><i data-lucide="list"></i>Patient List</a>
        <a class="btn btn-primary" id="editPatientButton" href="#" hidden><i data-lucide="pencil"></i>Edit Patient</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><span class="kpi-icon blue"><i data-lucide="badge-user"></i></span><div><div class="kpi-label">Patient Code</div><div class="kpi-value" id="kpiPatientCode">-</div><div class="kpi-meta">Clinic identifier</div></div></div>
    <div class="card kpi-card"><span class="kpi-icon teal"><i data-lucide="cake"></i></span><div><div class="kpi-label">Age</div><div class="kpi-value" id="kpiAge">-</div><div class="kpi-meta">Calculated from DOB</div></div></div>
    <div class="card kpi-card"><span class="kpi-icon green"><i data-lucide="phone"></i></span><div><div class="kpi-label">Mobile</div><div class="kpi-value" id="kpiMobile">-</div><div class="kpi-meta">Primary contact</div></div></div>
    <div class="card kpi-card"><span class="kpi-icon orange"><i data-lucide="activity"></i></span><div><div class="kpi-label">Status</div><div class="kpi-value" id="kpiStatus">-</div><div class="kpi-meta">Patient status</div></div></div>
</div>

<div class="card form-card">
<div class="card-header"><div><h2>Patient Details</h2><p>Personal, contact and medical notes.</p></div></div>
<div class="card-body">

<div class="card-section-title">Basic Details</div>
<div class="form-row">
    <div class="field col-4"><label>Patient Name</label><input id="patientName" type="text" readonly></div>
    <div class="field col-4"><label>Date of Birth</label><input id="dateOfBirth" type="text" readonly></div>
    <div class="field col-4"><label>Gender</label><input id="gender" type="text" readonly></div>
</div>
<div class="form-row">
    <div class="field col-4"><label>Blood Group</label><input id="bloodGroup" type="text" readonly></div>
    <div class="field col-4"><label>Identity Number</label><input id="identityNumber" type="text" readonly></div>
    <div class="field col-4"><label>Status</label><input id="patientStatus" type="text" readonly></div>
</div>

<div class="card-section-title">Contact Details</div>
<div class="form-row">
    <div class="field col-4"><label>Mobile</label><input id="mobile" type="text" readonly></div>
    <div class="field col-4"><label>Alternate Mobile</label><input id="alternateMobile" type="text" readonly></div>
    <div class="field col-4"><label>Email</label><input id="email" type="text" readonly></div>
</div>
<div class="form-row"><div class="field col-12"><label>Address</label><textarea id="address" rows="3" readonly></textarea></div></div>

<div class="card-section-title">Emergency Contact</div>
<div class="form-row">
    <div class="field col-6"><label>Emergency Contact Name</label><input id="emergencyName" type="text" readonly></div>
    <div class="field col-6"><label>Emergency Contact Mobile</label><input id="emergencyMobile" type="text" readonly></div>
</div>

<div class="card-section-title">Medical Notes</div>
<div class="form-row">
    <div class="field col-6"><label>Known Allergies</label><textarea id="knownAllergies" rows="3" readonly></textarea></div>
    <div class="field col-6"><label>Existing Medical Conditions</label><textarea id="medicalConditions" rows="3" readonly></textarea></div>
</div>

<div class="card-section-title">Audit</div>
<div class="form-row">
    <div class="field col-4"><label>Created By</label><input id="createdByName" type="text" readonly></div>
    <div class="field col-4"><label>Created At</label><input id="createdAt" type="text" readonly></div>
    <div class="field col-4"><label>Updated At</label><input id="updatedAt" type="text" readonly></div>
</div>

</div>
</div>

<script>
(function(){
    'use strict';

    var ref=new URLSearchParams(window.location.search).get('ref')||'';
    var actions=[];

    function el(id){return document.getElementById(id);}
    function has(id){return actions.map(Number).indexOf(Number(id))!==-1;}
    function text(v){return v==null||v===''?'-':String(v);}
    function formatDate(v){
        if(!v) return '-';
        var p=String(v).split('-');
        return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v;
    }

    async function load(){
        if(!ref){
            App.showError(null,'Patient reference is required.');
            return;
        }

        try{
            var response=await App.api('api/patients.php?ref='+encodeURIComponent(ref));
            actions=(response.data.allowed_actions||[]).map(Number);
            var p=response.data.record||{};

            el('profileHeading').textContent=text(p.patient_name);
            el('profileDescription').textContent=text(p.patient_code)+' · Patient Profile';

            el('kpiPatientCode').textContent=text(p.patient_code);
            el('kpiAge').textContent=(p.age===null||p.age===undefined)?'-':String(p.age)+' yrs';
            el('kpiMobile').textContent=text(p.mobile);
            el('kpiStatus').textContent=Number(p.status)===1?'Active':'Inactive';

            el('patientName').value=text(p.patient_name);
            el('dateOfBirth').value=formatDate(p.date_of_birth);
            el('gender').value=text(p.gender);
            el('bloodGroup').value=text(p.blood_group);
            el('identityNumber').value=text(p.identity_number);
            el('patientStatus').value=Number(p.status)===1?'Active':'Inactive';
            el('mobile').value=text(p.mobile);
            el('alternateMobile').value=text(p.alternate_mobile);
            el('email').value=text(p.email);
            el('address').value=text(p.address);
            el('emergencyName').value=text(p.emergency_contact_name);
            el('emergencyMobile').value=text(p.emergency_contact_mobile);
            el('knownAllergies').value=text(p.known_allergies);
            el('medicalConditions').value=text(p.medical_conditions);
            el('createdByName').value=text(p.created_by_name);
            el('createdAt').value=text(p.created_at);
            el('updatedAt').value=text(p.updated_at);

            if(has(3)){
                el('editPatientButton').href='patient-form.php?ref='+encodeURIComponent(ref);
                el('editPatientButton').hidden=false;
            }

            if(window.lucide) window.lucide.createIcons();
        }catch(error){
            App.showError(error,'Unable to load Patient Profile.');
        }
    }

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
