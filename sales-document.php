<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Sales Document';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo web_h($pageTitle); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        body{background:#eef1f5;margin:0}.doc-toolbar{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;gap:10px;padding:10px 16px;background:#fff;border-bottom:1px solid #e4e7ec}.doc-sheet{width:min(960px,calc(100% - 24px));margin:18px auto;background:#fff;padding:30px;border:1px solid #ddd;box-shadow:0 4px 18px rgba(0,0,0,.06);color:#111}.doc-head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #111;padding-bottom:15px}.doc-head h1{margin:0 0 5px;font-size:1.45rem}.doc-meta{display:grid;grid-template-columns:auto auto;gap:5px 18px;text-align:right;font-size:.86rem}.doc-customer{margin:18px 0;display:grid;grid-template-columns:1fr 1fr;gap:20px}.doc-table{width:100%;border-collapse:collapse}.doc-table th,.doc-table td{border:1px solid #bbb;padding:7px;font-size:.78rem}.doc-table th{background:#f4f4f4;text-align:left}.right{text-align:right}.doc-summary{margin:14px 0 0 auto;width:min(420px,100%);display:grid;gap:6px}.doc-summary>div{display:flex;justify-content:space-between;gap:15px}.doc-summary .grand{padding-top:8px;border-top:2px solid #111;font-size:1.05rem;font-weight:800}.doc-notes{margin-top:20px;padding-top:12px;border-top:1px solid #ccc;font-size:.82rem}.muted{color:#666}@media(max-width:650px){.doc-sheet{padding:15px}.doc-head,.doc-customer{grid-template-columns:1fr;display:grid}.doc-meta{text-align:left}.doc-table-wrap{overflow-x:auto}.doc-table{min-width:760px}}@media print{body{background:#fff}.doc-toolbar{display:none}.doc-sheet{width:100%;margin:0;padding:0;border:0;box-shadow:none}}
    </style>
</head>
<body>
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<div class="doc-toolbar">
    <a class="btn gray" href="sales-list.php">Back to Sales List</a>
    <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
</div>
<div class="doc-sheet" id="documentSheet"><div class="muted">Loading document...</div></div>
<script>
(function(){
    "use strict";
    var ref=new URLSearchParams(location.search).get("ref")||"";
    var labels={1:"QUOTATION",2:"PROFORMA BILL",3:"SALES BILL",4:"FINAL INVOICE"};
    function e(v){return String(v===null||v===undefined?"":v).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");}
    function n(v){var x=Number(v||0);return Number.isFinite(x)?x:0;}
    function m(v){return "₹"+n(v).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});}
    function render(s){
        var itemRows=(s.items||[]).map(function(item,i){
            var tax=n(item.cgst_amount)+n(item.sgst_amount)+n(item.igst_amount)+n(item.cess_amount);
            return '<tr><td>'+(i+1)+'</td><td>'+e(item.product_name||'-')+'</td><td>'+e(item.batch_number||item.purchase_no||'-')+'</td><td>'+e(item.selected_unit_symbol||item.selected_unit_name||'-')+'</td><td class="right">'+e(n(item.quantity).toFixed(3))+'</td><td class="right">'+e(m(item.unit_price))+'</td><td class="right">'+e(m(item.discount_amount))+'</td><td class="right">'+e(m(tax))+'</td><td class="right">'+e(m(item.line_total))+'</td></tr>';
        }).join("");
        var taxMode=Number(s.tax_mode)===0?'<strong>NON GST</strong>':'';
        var source=s.source_sales_no?'<div><span class="muted">Source</span><strong>'+e(s.source_sales_no)+'</strong></div>':'';
        document.getElementById("documentSheet").innerHTML=
            '<div class="doc-head"><div><h1>'+e(labels[Number(s.document_type)]||'SALES DOCUMENT')+'</h1><div>'+taxMode+'</div></div><div class="doc-meta"><span>Document No</span><strong>'+e(s.sales_no||'-')+'</strong><span>Date</span><strong>'+e(s.invoice_date||'-')+'</strong><span>Due Date</span><strong>'+e(s.due_date||'-')+'</strong>'+source+'</div></div>'+ 
            '<div class="doc-customer"><div><div class="muted">Customer</div><strong>'+e(s.customer_name_snapshot||s.current_customer_name||'-')+'</strong><div>'+e(s.customer_mobile||'')+'</div><div>'+e(s.customer_gstin_snapshot?('GSTIN: '+s.customer_gstin_snapshot):'')+'</div></div><div><div class="muted">Reference</div><strong>'+e(s.customer_reference||'-')+'</strong><div class="muted" style="margin-top:8px">Place of Supply</div><strong>'+e(s.customer_state_code||'-')+'</strong></div></div>'+ 
            '<div class="doc-table-wrap"><table class="doc-table"><thead><tr><th>#</th><th>Product</th><th>Batch</th><th>Unit</th><th>Qty</th><th>Rate</th><th>Discount</th><th>Tax</th><th>Amount</th></tr></thead><tbody>'+itemRows+'</tbody></table></div>'+ 
            '<div class="doc-summary"><div><span>Gross</span><strong>'+e(m(s.subtotal))+'</strong></div><div><span>Item Discount</span><strong>'+e(m(s.item_discount_total))+'</strong></div><div><span>Overall Discount</span><strong>'+e(m(s.overall_discount_amount))+'</strong></div><div><span>Taxable</span><strong>'+e(m(s.taxable_total))+'</strong></div>'+
            (Number(s.tax_mode)===1?'<div><span>CGST</span><strong>'+e(m(s.cgst_total))+'</strong></div><div><span>SGST</span><strong>'+e(m(s.sgst_total))+'</strong></div><div><span>IGST</span><strong>'+e(m(s.igst_total))+'</strong></div><div><span>Cess</span><strong>'+e(m(s.cess_total))+'</strong></div>':'')+
            '<div><span>Round Off</span><strong>'+e(m(s.round_off))+'</strong></div><div class="grand"><span>GRAND TOTAL</span><strong>'+e(m(s.grand_total))+'</strong></div></div>'+ 
            (Number(s.document_type)===4?'<div class="doc-summary"><div><span>Paid</span><strong>'+e(m(s.paid_amount))+'</strong></div><div><span>Balance</span><strong>'+e(m(s.balance_amount))+'</strong></div></div>':'')+
            '<div class="doc-notes"><strong>Remarks:</strong> '+e(s.notes||'-')+'</div>';
    }
    if(!ref){document.getElementById("documentSheet").innerHTML='<div>Invalid document reference.</div>';return;}
    App.api("api/sales.php?ref="+encodeURIComponent(ref)).then(function(result){render(result.data.sale);}).catch(function(error){App.showError(error,"Unable to load Sales Document.");document.getElementById("documentSheet").innerHTML='<div>Unable to load document.</div>';});
})();
</script>
</body>
</html>
