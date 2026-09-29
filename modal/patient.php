<div class="modal-backdrop app-modal-host" id="patientQuickModal" aria-hidden="true">
    <div class="app-modal-dialog" data-size="xl" role="dialog" aria-modal="true"
         aria-labelledby="patientQuickModalTitle">

        <form id="patientQuickForm" class="app-modal-form" novalidate autocomplete="off">

            <div class="modal-header">
                <div class="modal-header-copy">
                    <h2 id="patientQuickModalTitle">Add New Patient</h2>
                    <p>Create a Patient without leaving the Appointment form.</p>
                </div>

                <button class="modal-close" id="patientQuickClose" type="button"
                        aria-label="Close Patient modal">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body">

                <div class="form-row">
                    <div class="field col-4">
                        <label for="quickPatientCode">Patient Code</label>
                        <input id="quickPatientCode" type="text" readonly
                               aria-readonly="true" placeholder="Auto generated">
                    </div>

                    <div class="field col-8">
                        <label for="quickPatientName" class="required">Patient Name</label>
                        <input id="quickPatientName" name="patient_name" type="text"
                               maxlength="150" required autocomplete="off"
                               placeholder="Enter Patient Name"
                               data-required-message="Patient Name is required.">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label for="quickPatientDob">Date of Birth</label>
                        <input id="quickPatientDob" name="date_of_birth" type="date">
                    </div>

                    <div class="field col-4">
                        <label for="quickPatientAge">Age</label>
                        <input id="quickPatientAge" type="text" readonly
                               aria-readonly="true" placeholder="-">
                    </div>

                    <div class="field col-4">
                        <label for="quickPatientGender">Gender</label>
                        <select id="quickPatientGender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label for="quickPatientMobile" class="required">Mobile</label>
                        <input id="quickPatientMobile" name="mobile" type="text"
                               inputmode="numeric" maxlength="10" required
                               data-validation="mobile"
                               data-required-message="Mobile Number is required."
                               placeholder="10-digit mobile">
                    </div>

                    <div class="field col-4">
                        <label for="quickPatientAlternateMobile">Alternate Mobile</label>
                        <input id="quickPatientAlternateMobile" name="alternate_mobile"
                               type="text" inputmode="numeric" maxlength="10"
                               data-validation="mobile" placeholder="Alternate mobile">
                    </div>

                    <div class="field col-4">
                        <label for="quickPatientBloodGroup">Blood Group</label>
                        <select id="quickPatientBloodGroup" name="blood_group">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-6">
                        <label for="quickPatientEmail">Email</label>
                        <input id="quickPatientEmail" name="email" type="email"
                               maxlength="190" data-validation="email"
                               placeholder="patient@example.com">
                    </div>

                    <div class="field col-6">
                        <label for="quickPatientIdentity">Identity Number</label>
                        <input id="quickPatientIdentity" name="identity_number"
                               type="text" maxlength="100" placeholder="Optional">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-12">
                        <label for="quickPatientAddress">Address</label>
                        <textarea id="quickPatientAddress" name="address"
                                  rows="2" placeholder="Patient Address"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-6">
                        <label for="quickPatientEmergencyName">Emergency Contact Name</label>
                        <input id="quickPatientEmergencyName" name="emergency_contact_name"
                               type="text" maxlength="150" placeholder="Optional">
                    </div>

                    <div class="field col-6">
                        <label for="quickPatientEmergencyMobile">Emergency Contact Mobile</label>
                        <input id="quickPatientEmergencyMobile" name="emergency_contact_mobile"
                               type="text" inputmode="numeric" maxlength="10"
                               data-validation="mobile" placeholder="10-digit mobile">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-6">
                        <label for="quickPatientAllergies">Known Allergies</label>
                        <textarea id="quickPatientAllergies" name="known_allergies"
                                  rows="2" placeholder="Optional"></textarea>
                    </div>

                    <div class="field col-6">
                        <label for="quickPatientConditions">Existing Medical Conditions</label>
                        <textarea id="quickPatientConditions" name="medical_conditions"
                                  rows="2" placeholder="Optional"></textarea>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <div class="buttons">
                    <button class="btn gray" id="patientQuickCancel" type="button">
                        Cancel
                    </button>

                    <button class="btn btn-primary" id="patientQuickSave" type="submit">
                        <i data-lucide="save"></i>
                        Save Patient
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
(function (window, document) {
    'use strict';

    var modal = document.getElementById('patientQuickModal');
    var form = document.getElementById('patientQuickForm');
    var saveButton = document.getElementById('patientQuickSave');
    var onSaved = null;
    var genderSelect = null;
    var bloodGroupSelect = null;

    if (!modal || !form) return;

    function hasAction(list, id) {
        return (list || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function ensureSelects() {
        if (!window.GlobalSelect) return;

        if (!genderSelect) {
            genderSelect = GlobalSelect.init('#quickPatientGender', {
                placeholder: 'Select Gender'
            });
        }

        if (!bloodGroupSelect) {
            bloodGroupSelect = GlobalSelect.init('#quickPatientBloodGroup', {
                placeholder: 'Select Blood Group'
            });
        }
    }

    function setSelect(instance, select, value) {
        value = value == null ? '' : String(value);

        if (instance && typeof instance.setValue === 'function') {
            instance.setValue(value);
            return;
        }

        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));

        if (instance && typeof instance.sync === 'function') {
            instance.sync();
        }
    }

    function calculateAge() {
        var value = String(document.getElementById('quickPatientDob').value || '').trim();
        var ageInput = document.getElementById('quickPatientAge');

        if (!value) {
            ageInput.value = '';
            return;
        }

        var dob = new Date(value + 'T00:00:00');
        var today = new Date();

        if (Number.isNaN(dob.getTime()) || dob > today) {
            ageInput.value = '';
            return;
        }

        var age = today.getFullYear() - dob.getFullYear();
        var month = today.getMonth() - dob.getMonth();

        if (month < 0 || (month === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        ageInput.value = age >= 0 ? String(age) : '';
    }

    function openModal() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        if (window.lucide) {
            window.lucide.createIcons();
        }

        window.setTimeout(function () {
            document.getElementById('quickPatientName').focus();
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

        document.getElementById('quickPatientCode').value = '';
        document.getElementById('quickPatientAge').value = '';

        ensureSelects();

        setSelect(
            genderSelect,
            document.getElementById('quickPatientGender'),
            ''
        );

        setSelect(
            bloodGroupSelect,
            document.getElementById('quickPatientBloodGroup'),
            ''
        );

        if (window.Validation && typeof Validation.clearForm === 'function') {
            Validation.clearForm(form);
        }
    }

    async function openCreate(options) {
        options = options || {};

        try {
            var access = await App.api('api/patients.php?options=1');

            var actions =
                access &&
                access.data &&
                Array.isArray(access.data.allowed_actions)
                    ? access.data.allowed_actions
                    : [];

            if (!hasAction(actions, 2)) {
                throw new Error('You do not have permission to create Patients.');
            }

            resetForm();

            document.getElementById('quickPatientCode').value =
                access &&
                access.data
                    ? access.data.next_patient_code || ''
                    : '';

            onSaved =
                typeof options.onSaved === 'function'
                    ? options.onSaved
                    : null;

            openModal();
        } catch (error) {
            App.showError(error, 'Unable to open Patient form.');
        }
    }

    document.getElementById('patientQuickClose')
        .addEventListener('click', closeModal);

    document.getElementById('patientQuickCancel')
        .addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            modal.classList.contains('open')
        ) {
            closeModal();
        }
    });

    document.getElementById('quickPatientDob')
        .addEventListener('change', calculateAge);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (window.Validation) {
            Validation.clearForm(form);

            if (!Validation.validateForm(form)) {
                return;
            }
        }

        calculateAge();

        var data = {
            action: 'save',
            patient_name: form.patient_name.value.trim(),
            date_of_birth: form.date_of_birth.value,
            gender: form.gender.value,
            mobile: form.mobile.value.trim(),
            alternate_mobile: form.alternate_mobile.value.trim(),
            email: form.email.value.trim(),
            address: form.address.value.trim(),
            emergency_contact_name: form.emergency_contact_name.value.trim(),
            emergency_contact_mobile: form.emergency_contact_mobile.value.trim(),
            blood_group: form.blood_group.value,
            known_allergies: form.known_allergies.value.trim(),
            medical_conditions: form.medical_conditions.value.trim(),
            identity_number: form.identity_number.value.trim(),
            status: 1
        };

        saveButton.disabled = true;

        try {
            var result = await App.api('api/patients.php', {
                method: 'POST',
                body: data
            });

            var saved =
                result && result.data
                    ? result.data
                    : {};

            showToast(
                result.message || 'Patient created successfully.',
                {
                    type: 'success',
                    duration: 2
                }
            );

            var callback = onSaved;

            closeModal();

            if (typeof callback === 'function') {
                callback(saved);
            }
        } catch (error) {
            if (
                window.Validation &&
                typeof Validation.applyErrors === 'function'
            ) {
                Validation.applyErrors(
                    form,
                    error.errors || {}
                );
            }

            App.showError(
                error,
                'Unable to create Patient.'
            );
        } finally {
            saveButton.disabled = false;
        }
    });

    window.AppPatientForm = {
        openCreate: openCreate,
        close: closeModal
    };

})(window, document);
</script>
