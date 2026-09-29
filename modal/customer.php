<div class="pos-modal" id="customerModal" hidden>
    <div class="pos-modal-backdrop" data-close-modal="customerModal"></div>
    <div class="pos-modal-dialog">
        <div class="pos-modal-head">
            <div><h2>Add Customer</h2><p>Create a Customer without leaving Sales POS.</p></div>
            <button class="icon-button" type="button" data-close-modal="customerModal"><i data-lucide="x"></i></button>
        </div>
        <form id="quickCustomerForm" novalidate>
            <div class="pos-modal-body">
                <div class="pos-form-grid modal-grid">
                    <div class="field full">
                        <label for="quickCustomerName" class="required">Customer Name</label>
                        <input id="quickCustomerName" name="customer_name" type="text" maxlength="150" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="quickCustomerMobile">Mobile</label>
                        <input id="quickCustomerMobile" name="mobile" type="text" inputmode="numeric" maxlength="20" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="quickCustomerState" class="required">State Code</label>
                        <input id="quickCustomerState" name="state_code" type="text" inputmode="numeric" maxlength="2" required placeholder="33">
                    </div>
                    <div class="field full">
                        <label for="quickCustomerGstin">GSTIN</label>
                        <input id="quickCustomerGstin" name="gstin" type="text" maxlength="15" autocomplete="off">
                    </div>
                </div>
                <input type="hidden" name="status" value="1">
            </div>
            <div class="pos-modal-footer">
                <button class="btn gray" type="button" data-close-modal="customerModal">Cancel</button>
                <button class="btn btn-primary" id="quickCustomerSave" type="submit">Save Customer</button>
            </div>
        </form>
    </div>
</div>
