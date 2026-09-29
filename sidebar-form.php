<?php
require_once __DIR__ . '/include/web-config.php'; $pageTitle = 'Sidebar Menu Form'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ((isset($headStyles) && is_array($headStyles) ? $headStyles : []) as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ((isset($headScripts) && is_array($headScripts) ? $headScripts : []) as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars((string)$scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
</head>
<body><div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
<main class="main-stage"><?php require __DIR__ . '/include/topbar.php'; ?>
<section class="page-content">
<script src="assets/js/toaster.js"></script><script src="assets/js/app.js"></script><script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script><script src="assets/js/datatable.js"></script>
<div class="page-head"><h1 id="title">Add Sidebar Menu</h1><a class="btn gray" href="sidebar-list.php">Menu List</a></div>
<div class="card form-card"><form id="menuForm" novalidate>
    <div class="card-header"><div><h2>Menu Details</h2><p>Choose a management section first. Existing menu paths and action permissions remain unchanged.</p></div></div>
    <div class="card-body"><div class="form-grid">
        <div class="field"><label>Menu name</label><input name="menu_name" required></div>
        <div class="field"><label>Management section</label><select name="menu_section" id="menuSection" required>
            <option value="common">Common / System</option><option value="clinic">Clinic Management</option>
            <option value="college">College Management</option><option value="food_supplementary">Food Supplementary</option>
        </select></div>
        <div class="field"><label>Parent menu</label><select name="parent_id" id="parentMenu"><option value="">Main menu</option></select></div>
        <div class="field"><label>Menu path / page</label><input name="menu_path" required placeholder="example-list.php"></div>
        <div class="field"><label>Icon name</label><input name="icon" placeholder="users"></div>
        <div class="field"><label>Sort order</label><input name="sort_order" type="number" value="0" data-validation="integer"></div>
        <div class="field"><label>Status</label><select name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
        <div class="field full"><label>Available actions</label><div class="buttons" id="actionChecks"></div></div>
    </div></div>
    <div class="card-footer"><div class="buttons"><button class="btn btn-primary" id="saveButton" type="submit">Save Menu</button><a class="btn gray" href="sidebar-list.php">Cancel</a></div></div>
</form></div>
<script src="assets/js/validation.js"></script>
<script>
(function () {
    "use strict";
    var form=document.getElementById("menuForm");
    var id=Number(new URLSearchParams(location.search).get("id")||0);
    var actionMaster=[], allMenus=[];
    var parentSelect=document.getElementById("parentMenu"), sectionSelect=document.getElementById("menuSection");
    var roots={clinic:"management-clinic",college:"management-college",food_supplementary:"management-food-supplementary"};
    function buildActions(selected) {
        selected=(selected||[]).map(Number); var box=document.getElementById("actionChecks");
        box.innerHTML=""; var currentGroup=null;
        actionMaster.forEach(function(action){
            if(currentGroup!==action.group_name){
                currentGroup=action.group_name; var heading=document.createElement("div");
                heading.className="muted";heading.style.cssText="width:100%;font-weight:700;margin:8px 0 2px";
                heading.textContent=currentGroup;box.appendChild(heading);
            }
            var label=document.createElement("label");
            label.style.cssText="display:inline-flex;align-items:center;gap:6px;padding:9px 11px;border:1px solid var(--line);border-radius:8px;margin:0";
            label.title=action.purpose||"";
            var input=document.createElement("input");input.style.cssText="width:auto;height:auto";
            input.type="checkbox";input.name="actions";input.value=String(action.id);
            input.checked=selected.indexOf(Number(action.id))!==-1;
            if(Number(action.id)===1){input.checked=true;input.disabled=true;}
            label.appendChild(input);label.appendChild(document.createTextNode(action.id+" - "+action.action_name));box.appendChild(label);
        });
    }
    function childOfCurrent(candidate) {
        var seen={}; var p=candidate;
        while(p && p.parent_id!==null && !seen[p.id]) {
            seen[p.id]=true;
            if(Number(p.parent_id)===id) return true;
            p=allMenus.find(function(x){return Number(x.id)===Number(p.parent_id);});
        }
        return false;
    }
    function refreshParents(selected, pickDefault) {
        selected=String(selected||"");parentSelect.innerHTML="";
        var root=document.createElement("option");root.value="";root.textContent="Main menu";parentSelect.appendChild(root);
        var section=sectionSelect.value;
        var subset=allMenus.filter(function(x){return x.menu_section===section && Number(x.id)!==id && !childOfCurrent(x);});
        var byId={};subset.forEach(function(m){byId[m.id]=m;});
        subset.forEach(function(menu){
            var option=document.createElement("option");option.value=menu.id;
            var depth=0,p=byId[menu.parent_id],seen={};
            while(p && depth<8 && !seen[p.id]) {seen[p.id]=true;depth++;p=byId[p.parent_id];}
            option.textContent=(depth?"— ".repeat(depth):"")+menu.menu_name+(Number(menu.status)===0?" (Inactive)":"");
            parentSelect.appendChild(option);
        });
        if(selected && Array.prototype.some.call(parentSelect.options,function(o){return o.value===selected;})) parentSelect.value=selected;
        else if(pickDefault && roots[section]) {
            var parent=allMenus.find(function(x){return x.menu_path===roots[section];});
            if(parent && Array.prototype.some.call(parentSelect.options,function(o){return o.value===String(parent.id);}))parentSelect.value=String(parent.id);
        }
    }
    sectionSelect.addEventListener("change",function(){refreshParents("",!id);});
    async function load(){
        try {
            var list=await App.api("api/menus.php"); actionMaster=list.data.actions||[];allMenus=list.data.menus||[];
            if(!id){refreshParents("",true);buildActions([1]);return;}
            document.getElementById("title").textContent="Edit Sidebar Menu";
            var result=await App.api("api/menus.php?id="+encodeURIComponent(id));
            actionMaster=result.data.actions||actionMaster;var menu=result.data.menu;
            ["menu_name","menu_path","icon","sort_order","status","menu_section"].forEach(function(name){
                if(form.elements[name])form.elements[name].value=menu[name]===null?"":menu[name];
            });
            refreshParents(menu.parent_id,false);
            buildActions(String(menu.available_action_ids||"").split(",").filter(Boolean).map(Number));
        }catch(error){App.showError(error,"Unable to load sidebar menu form.");}
    }
    form.addEventListener("submit",async function(event){
        event.preventDefault();Validation.clearForm(form);if(!Validation.validateForm(form))return;
        var actions=[1];Array.prototype.forEach.call(form.querySelectorAll('input[name="actions"]:checked'),function(input){actions.push(Number(input.value));});
        actions=Array.from(new Set(actions)).sort(function(a,b){return a-b;});
        var data={id:id||undefined,menu_name:form.elements.menu_name.value.trim(),menu_section:sectionSelect.value,
            parent_id:parentSelect.value||null,menu_path:form.elements.menu_path.value.trim(),icon:form.elements.icon.value.trim(),
            sort_order:Number(form.elements.sort_order.value||0),status:Number(form.elements.status.value),available_action_ids:actions};
        var button=document.getElementById("saveButton");button.disabled=true;
        try {
            var result=await App.api("api/menus.php",{method:id?"PUT":"POST",body:data});
            showToast(result.message,{type:"success",duration:2});if(App.clearSidebarCache)App.clearSidebarCache();
            setTimeout(function(){location.href="sidebar-list.php";},650);
        }catch(error){App.showError(error,"Unable to save sidebar menu.");button.disabled=false;}
    });
    load();
})();
</script>
</section><?php require __DIR__ . '/include/footer.php'; ?></main></div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body></html>
