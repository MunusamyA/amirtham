<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_hsn_form_rendered'])) {
    return;
}
$GLOBALS['app_hsn_form_rendered'] = true;
?>
<form id="appHsnForm" class="app-modal-form hidden" data-component="hsn-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-hsn-form-title>Add HSN</h2>
            <p>Maintain the HSN code and applicable tax split. GST can automatically suggest CGST, SGST and IGST.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close HSN form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appHsnCode">HSN Code</label>
                <input id="appHsnCode" name="hsn_code" type="text" inputmode="numeric" autocomplete="off" maxlength="8" required
                       data-regex="^[0-9]{2,8}$"
                       data-required-message="HSN code is required."
                       data-regex-message="HSN code must contain 2 to 8 digits."
                       placeholder="Example: 0910">
            </div>

            <div class="field two-span">
                <label for="appHsnDescription">Description</label>
                <input id="appHsnDescription" name="description" type="text" maxlength="255" placeholder="Example: Spices">
            </div>

            <div class="field">
                <label for="appHsnGst">GST %</label>
                <input id="appHsnGst" name="gst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="GST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnCgst">CGST %</label>
                <input id="appHsnCgst" name="cgst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="CGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnSgst">SGST %</label>
                <input id="appHsnSgst" name="sgst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="SGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnIgst">IGST %</label>
                <input id="appHsnIgst" name="igst_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="IGST must be between 0 and 100.">
            </div>

            <div class="field">
                <label for="appHsnCess">Cess %</label>
                <input id="appHsnCess" name="cess_rate" type="text" inputmode="decimal" autocomplete="off" value="0.00"
                       data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:100(?:[.]0{1,2})?|(?:[0-9]{1,2})(?:[.][0-9]{1,2})?|[.][0-9]{1,2})$"
                       data-regex-message="Cess must be between 0 and 100.">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-hsn-save>Save HSN</button>
        </div>
    </div>
</form>
<script src="assets/js/hsn-form.js"></script>
