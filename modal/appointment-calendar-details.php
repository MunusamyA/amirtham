<div
    class="modal-backdrop app-modal-host"
    id="appointmentCalendarDetailsModal"
    aria-hidden="true"
>
    <div
        class="app-modal-dialog"
        data-size="xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="appointmentCalendarDetailsTitle"
    >
        <div class="app-modal-form">
            <div class="modal-header">
                <div class="modal-header-copy">
                    <h2 id="appointmentCalendarDetailsTitle">
                        Appointment / Patient Details
                    </h2>
                    <p id="appointmentCalendarDetailsSubtitle">
                        Selected calendar appointment
                    </p>
                </div>

                <button
                    class="modal-close"
                    id="appointmentCalendarDetailsClose"
                    type="button"
                    aria-label="Close"
                >
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="card-section-title">Appointment</div>

                <div class="form-row">
                    <div class="field col-3">
                        <label>Appointment No.</label>
                        <input id="calendarModalAppointmentNo" type="text" readonly>
                    </div>

                    <div class="field col-3">
                        <label>Date</label>
                        <input id="calendarModalAppointmentDate" type="text" readonly>
                    </div>

                    <div class="field col-3">
                        <label>Time</label>
                        <input id="calendarModalAppointmentTime" type="text" readonly>
                    </div>

                    <div class="field col-3">
                        <label>Status</label>
                        <input id="calendarModalAppointmentStatus" type="text" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label>Visit Type</label>
                        <input id="calendarModalVisitType" type="text" readonly>
                    </div>

                    <div class="field col-4">
                        <label>Doctor / Consultant</label>
                        <input id="calendarModalConsultant" type="text" readonly>
                    </div>

                    <div class="field col-4">
                        <label>Reason / Complaint</label>
                        <input id="calendarModalReason" type="text" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-12">
                        <label>Notes</label>
                        <textarea id="calendarModalNotes" rows="2" readonly></textarea>
                    </div>
                </div>

                <div class="card-section-title">Patient</div>

                <div class="form-row">
                    <div class="field col-3">
                        <label>Patient Code</label>
                        <input id="calendarModalPatientCode" type="text" readonly>
                    </div>

                    <div class="field col-3">
                        <label>Patient Name</label>
                        <input id="calendarModalPatientName" type="text" readonly>
                    </div>

                    <div class="field col-2">
                        <label>Age</label>
                        <input id="calendarModalPatientAge" type="text" readonly>
                    </div>

                    <div class="field col-2">
                        <label>Gender</label>
                        <input id="calendarModalPatientGender" type="text" readonly>
                    </div>

                    <div class="field col-2">
                        <label>Blood Group</label>
                        <input id="calendarModalBloodGroup" type="text" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field col-4">
                        <label>Mobile</label>
                        <input id="calendarModalMobile" type="text" readonly>
                    </div>

                    <div class="field col-4">
                        <label>Created By</label>
                        <input id="calendarModalCreatedBy" type="text" readonly>
                    </div>

                    <div class="field col-4">
                        <label>Created At</label>
                        <input id="calendarModalCreatedAt" type="text" readonly>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="buttons">
                    <button
                        class="btn gray"
                        id="appointmentCalendarDetailsCancel"
                        type="button"
                    >
                        Close
                    </button>

                    <a
                        class="btn gray"
                        id="appointmentCalendarViewButton"
                        href="#"
                    >
                        <i data-lucide="eye"></i>
                        View Appointment
                    </a>

                    <a
                        class="btn gray"
                        id="appointmentCalendarEditButton"
                        href="#"
                    >
                        <i data-lucide="pencil"></i>
                        Edit Appointment
                    </a>

                    <a
                        class="btn btn-primary"
                        id="appointmentCalendarConsultationButton"
                        href="#"
                    >
                        <i data-lucide="clipboard-plus"></i>
                        Start Consultation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(window,document){
    'use strict';

    var modal =
        document.getElementById(
            'appointmentCalendarDetailsModal'
        );

    function value(id, text) {
        document.getElementById(id).value =
            text == null || text === ''
                ? '-'
                : String(text);
    }

    function openModal() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('modal-open');

        if(window.lucide){
            window.lucide.createIcons();
        }
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('modal-open');
    }

    async function open(ref) {
        if (!ref) return;

        try {
            var response =
                await App.api(
                    'api/appointments.php?ref=' +
                    encodeURIComponent(ref)
                );

            var data =
                response &&
                response.data &&
                typeof response.data === 'object'
                    ? response.data
                    : {};

            var row = data.record || {};
            var actions =
                Array.isArray(data.allowed_actions)
                    ? data.allowed_actions.map(Number)
                    : [];

            value(
                'calendarModalAppointmentNo',
                row.appointment_no
            );

            value(
                'calendarModalAppointmentDate',
                row.appointment_date
            );

            value(
                'calendarModalAppointmentTime',
                row.appointment_time
            );

            value(
                'calendarModalAppointmentStatus',
                row.appointment_status
            );

            value(
                'calendarModalVisitType',
                row.visit_type
            );

            value(
                'calendarModalConsultant',
                row.consultant_name
            );

            value(
                'calendarModalReason',
                row.reason_complaint
            );

            document.getElementById(
                'calendarModalNotes'
            ).value =
                row.notes || '';

            value(
                'calendarModalPatientCode',
                row.patient_code
            );

            value(
                'calendarModalPatientName',
                row.patient_name
            );

            value(
                'calendarModalPatientAge',
                row.age
            );

            value(
                'calendarModalPatientGender',
                row.gender
            );

            value(
                'calendarModalBloodGroup',
                row.blood_group
            );

            value(
                'calendarModalMobile',
                row.mobile
            );

            value(
                'calendarModalCreatedBy',
                row.created_by_name
            );

            value(
                'calendarModalCreatedAt',
                row.created_at
            );

            document.getElementById(
                'appointmentCalendarDetailsSubtitle'
            ).textContent =
                (row.appointment_no || '') +
                ' - ' +
                (row.patient_name || '');

            var viewButton =
                document.getElementById(
                    'appointmentCalendarViewButton'
                );

            var editButton =
                document.getElementById(
                    'appointmentCalendarEditButton'
                );

            var consultationButton =
                document.getElementById(
                    'appointmentCalendarConsultationButton'
                );

            viewButton.href =
                'appointment-form.php?ref=' +
                encodeURIComponent(ref) +
                '&view=1';

            editButton.href =
                'appointment-form.php?ref=' +
                encodeURIComponent(ref);

            consultationButton.href =
                'consultation-form.php?appointment_ref=' +
                encodeURIComponent(ref);

            viewButton.hidden =
                actions.indexOf(1) === -1;

            editButton.hidden =
                actions.indexOf(3) === -1;

            consultationButton.hidden =
                ['Cancelled','No Show','Completed']
                    .indexOf(
                        String(
                            row.appointment_status || ''
                        )
                    ) !== -1;

            openModal();
        } catch (error) {
            App.showError(
                error,
                'Unable to load Appointment / Patient details.'
            );
        }
    }

    document.getElementById(
        'appointmentCalendarDetailsClose'
    ).addEventListener(
        'click',
        closeModal
    );

    document.getElementById(
        'appointmentCalendarDetailsCancel'
    ).addEventListener(
        'click',
        closeModal
    );

    modal.addEventListener(
        'click',
        function(event) {
            if(event.target === modal){
                closeModal();
            }
        }
    );

    document.addEventListener(
        'keydown',
        function(event) {
            if(
                event.key === 'Escape' &&
                modal.classList.contains('open')
            ){
                closeModal();
            }
        }
    );

    window.AppAppointmentCalendarDetails = {
        open:open,
        close:closeModal
    };

})(window,document);
</script>
