<div
    class="modal-backdrop app-modal-host"
    id="treatmentProcedureQuickModal"
    aria-hidden="true"
>
    <div
        class="app-modal-dialog"
        data-size="lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="treatmentProcedureQuickTitle"
    >
        <form
            id="treatmentProcedureQuickForm"
            class="app-modal-form"
            novalidate
            autocomplete="off"
        >
            <div class="modal-header">
                <div class="modal-header-copy">
                    <h2 id="treatmentProcedureQuickTitle">
                        Add Treatment / Procedure
                    </h2>
                    <p>Create a reusable Treatment / Procedure without leaving this form.</p>
                </div>

                <button
                    class="modal-close"
                    id="treatmentProcedureQuickClose"
                    type="button"
                    aria-label="Close"
                >
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="field col-4">
                        <label>Procedure Code</label>
                        <input
                            id="quickProcedureCode"
                            type="text"
                            readonly
                        >
                    </div>

                    <div class="field col-4">
                        <label for="quickProcedureName" class="required">
                            Treatment / Procedure Name
                        </label>
                        <input
                            id="quickProcedureName"
                            name="procedure_name"
                            type="text"
                            maxlength="255"
                            required
                            placeholder="Enter Treatment / Procedure Name"
                            data-required-message="Treatment / Procedure Name is required."
                        >
                    </div>

                    <div class="field col-2">
                        <label for="quickProcedurePrice" class="required">Price</label>
                        <input id="quickProcedurePrice" name="price" type="text" inputmode="decimal" required value="0.00">
                    </div>

                    <div class="field col-2">
                        <label for="quickProcedureStatus" class="required">Status</label>
                        <select
                            id="quickProcedureStatus"
                            name="status"
                            required
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="buttons">
                    <button
                        class="btn gray"
                        id="treatmentProcedureQuickCancel"
                        type="button"
                    >
                        Cancel
                    </button>

                    <button
                        class="btn btn-primary"
                        id="treatmentProcedureQuickSave"
                        type="submit"
                    >
                        <i data-lucide="save"></i>
                        Save Treatment / Procedure
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function(window,document){
    'use strict';

    var modal = document.getElementById('treatmentProcedureQuickModal');
    var form = document.getElementById('treatmentProcedureQuickForm');
    var saveButton = document.getElementById('treatmentProcedureQuickSave');
    var onSaved = null;

    function openModal() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('modal-open');

        window.setTimeout(function(){
            document.getElementById('quickProcedureName').focus();
        },50);

        if(window.lucide){
            window.lucide.createIcons();
        }
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('modal-open');
        onSaved = null;
    }

    function resetForm() {
        form.reset();
        document.getElementById('quickProcedureCode').value = '';
        document.getElementById('quickProcedureStatus').value = '1';
        document.getElementById('quickProcedurePrice').value = '0.00';

        if (window.Validation) {
            Validation.clearForm(form);
        }
    }

    async function openCreate(options) {
        options = options || {};

        try {
            var response = await App.api(
                'api/treatment-procedures.php?options=1'
            );

            var data = response && response.data ? response.data : {};
            var actions = Array.isArray(data.allowed_actions)
                ? data.allowed_actions.map(Number)
                : [];

            if (actions.indexOf(2) === -1) {
                showToast(
                    'You do not have permission to create Treatment / Procedure.',
                    {type:'danger',duration:3}
                );
                return;
            }

            resetForm();

            document.getElementById('quickProcedureCode').value =
                data.next_procedure_code || '';

            onSaved =
                typeof options.onSaved === 'function'
                    ? options.onSaved
                    : null;

            openModal();
        } catch (error) {
            App.showError(
                error,
                'Unable to prepare Treatment / Procedure form.'
            );
        }
    }

    form.addEventListener('submit',async function(event){
        event.preventDefault();

        Validation.clearForm(form);

        if (!Validation.validateForm(form)) {
            return;
        }

        saveButton.disabled = true;

        try {
            var data = new FormData(form);
            data.set('action','save');

            var response = await App.api(
                'api/treatment-procedures.php',
                {
                    method:'POST',
                    body:data
                }
            );

            var payload = response && response.data
                ? response.data
                : {};

            showToast(
                response.message || 'Treatment / Procedure saved successfully.',
                {type:'success',duration:2}
            );

            var callback = onSaved;
            closeModal();

            if (typeof callback === 'function') {
                callback(payload.record || payload);
            }
        } catch (error) {
            Validation.applyErrors(form,error.errors || {});
            App.showError(
                error,
                'Unable to save Treatment / Procedure.'
            );
        } finally {
            saveButton.disabled = false;
        }
    });

    document.getElementById('treatmentProcedureQuickClose')
        .addEventListener('click',closeModal);

    document.getElementById('treatmentProcedureQuickCancel')
        .addEventListener('click',closeModal);

    modal.addEventListener('click',function(event){
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown',function(event){
        if (
            event.key === 'Escape' &&
            modal.classList.contains('open')
        ) {
            closeModal();
        }
    });

    window.AppTreatmentProcedureForm = {
        openCreate:openCreate,
        close:closeModal
    };

})(window,document);
</script>
