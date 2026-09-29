<div class="modal-backdrop app-modal-host" id="supplierQuickModal" aria-hidden="true">
    <div class="app-modal-dialog" data-size="lg" role="dialog" aria-modal="true" aria-labelledby="supplierQuickModalTitle">
        <form id="supplierQuickForm" class="app-modal-form" novalidate autocomplete="off">
            <div class="modal-header">
                <div class="modal-header-copy">
                    <h2 id="supplierQuickModalTitle">Add Supplier</h2>
                    <p>Create a Supplier without leaving the Purchase form.</p>
                </div>
                <button class="modal-close" id="supplierQuickClose" type="button" aria-label="Close Supplier modal">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="field col-6">
                        <label for="quickSupplierName" class="required">Supplier Name</label>
                        <input id="quickSupplierName" name="supplier_name" type="text" maxlength="150" required
                               data-required-message="Supplier Name is required." placeholder="Enter Supplier Name">
                    </div>
                    <div class="field col-6">
                        <label for="quickContactPerson">Contact Person</label>
                        <input id="quickContactPerson" name="contact_person" type="text" maxlength="150"
                               placeholder="Contact Person">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label for="quickSupplierMobile">Mobile</label>
                        <input id="quickSupplierMobile" name="mobile" type="text" inputmode="numeric" maxlength="10"
                               data-validation="mobile" placeholder="10-digit mobile">
                    </div>
                    <div class="field col-4">
                        <label for="quickSupplierEmail">Email</label>
                        <input id="quickSupplierEmail" name="email" type="email" maxlength="190"
                               data-validation="email" placeholder="supplier@example.com">
                    </div>
                    <div class="field col-4">
                        <label for="quickSupplierState" class="required">State Code</label>
                        <input id="quickSupplierState" name="state_code" type="text" inputmode="numeric" maxlength="2"
                               required pattern="[0-9]{2}" data-required-message="State Code is required."
                               placeholder="Example: 33">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label for="quickSupplierGstin">GSTIN</label>
                        <input id="quickSupplierGstin" name="gstin" type="text" maxlength="15"
                               data-validation="gst" placeholder="GSTIN">
                    </div>
                    <div class="field col-4">
                        <label for="quickSupplierPan">PAN</label>
                        <input id="quickSupplierPan" name="pan" type="text" maxlength="10"
                               data-validation="pan" placeholder="PAN">
                    </div>
                    <div class="field col-4">
                        <label for="quickOpeningBalance">Opening Balance</label>
                        <input id="quickOpeningBalance" name="opening_balance" type="text" inputmode="decimal"
                               data-validation="decimal" data-decimal-places="2" placeholder="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-12">
                        <label for="quickSupplierAddress">Address</label>
                        <textarea id="quickSupplierAddress" name="address" rows="3" maxlength="1000"
                                  placeholder="Supplier Address"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="buttons">
                    <button class="btn gray" id="supplierQuickCancel" type="button">Cancel</button>
                    <button class="btn btn-primary" id="supplierQuickSave" type="submit">
                        <i data-lucide="save"></i>
                        Save Supplier
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function (window, document) {
    'use strict';

    var modal = document.getElementById('supplierQuickModal');
    var form = document.getElementById('supplierQuickForm');
    var saveButton = document.getElementById('supplierQuickSave');
    var onSaved = null;

    if (!modal || !form) return;

    function hasAction(list, id) {
        return (list || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function openModal() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        if (window.lucide) window.lucide.createIcons();
        window.setTimeout(function () {
            document.getElementById('quickSupplierName').focus();
        }, 30);
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        onSaved = null;
    }

    function resetForm() {
        form.reset();
        document.getElementById('quickOpeningBalance').value = '';
        if (window.Validation && typeof Validation.clearForm === 'function') {
            Validation.clearForm(form);
        }
    }

    async function openCreate(options) {
        options = options || {};
        try {
            var access = await App.api('api/suppliers.php?options=1');
            var actions = access && access.data && Array.isArray(access.data.allowed_actions)
                ? access.data.allowed_actions
                : [];

            if (!hasAction(actions, 2)) {
                throw new Error('You do not have permission to create Suppliers.');
            }

            resetForm();
            onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;
            openModal();
        } catch (error) {
            App.showError(error, 'Unable to open Supplier form.');
        }
    }

    document.getElementById('supplierQuickClose').addEventListener('click', closeModal);
    document.getElementById('supplierQuickCancel').addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    document.getElementById('quickSupplierGstin').addEventListener('blur', function () {
        this.value = String(this.value || '').toUpperCase().replace(/\s+/g, '');
        var stateInput = document.getElementById('quickSupplierState');
        var match = this.value.match(/^([0-9]{2})/);
        if (match && !stateInput.value.trim()) stateInput.value = match[1];
    });

    document.getElementById('quickSupplierPan').addEventListener('input', function () {
        this.value = String(this.value || '').toUpperCase().replace(/\s+/g, '');
    });

    document.getElementById('quickSupplierState').addEventListener('input', function () {
        this.value = String(this.value || '').replace(/\D/g, '').slice(0, 2);
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (window.Validation) {
            Validation.clearForm(form);
            if (!Validation.validateForm(form)) return;
        }

        var stateCode = document.getElementById('quickSupplierState').value.trim();
        if (!/^\d{2}$/.test(stateCode)) {
            showToast('Enter a valid 2-digit Supplier State Code.', { type: 'danger', duration: 3 });
            document.getElementById('quickSupplierState').focus();
            return;
        }

        var data = {
            supplier_name: form.supplier_name.value.trim(),
            contact_person: form.contact_person.value.trim(),
            mobile: form.mobile.value.trim(),
            email: form.email.value.trim(),
            gstin: form.gstin.value.trim().toUpperCase(),
            pan: form.pan.value.trim().toUpperCase(),
            state_code: stateCode,
            address: form.address.value.trim(),
            opening_balance: form.opening_balance.value.trim() || '0.00',
            status: 1
        };

        saveButton.disabled = true;
        try {
            var result = await App.api('api/suppliers.php', {
                method: 'POST',
                body: data
            });

            var saved = result && result.data ? result.data : {};
            showToast(result.message || 'Supplier created successfully.', { type: 'success', duration: 2 });

            var callback = onSaved;
            closeModal();
            if (typeof callback === 'function') callback(saved);
        } catch (error) {
            if (window.Validation && typeof Validation.applyErrors === 'function') {
                Validation.applyErrors(form, error.errors || {});
            }
            App.showError(error, 'Unable to create Supplier.');
        } finally {
            saveButton.disabled = false;
        }
    });

    window.AppSupplierForm = {
        openCreate: openCreate,
        close: closeModal
    };
})(window, document);
</script>
