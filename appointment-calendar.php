<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Appointment Calendar';
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

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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
<script src="assets/js/global-select.js"></script>

<div class="page-head">
    <div>
        <h1>Appointment Calendar</h1>
        <p>View Month, Week and Day appointments. Click an empty slot to create an Appointment.</p>
    </div>

    <div class="buttons">
        <a class="btn gray" href="appointment-list.php">
            <i data-lucide="list"></i>
            Appointment List
        </a>

        <a class="btn btn-primary" id="calendarAddAppointment" href="appointment-form.php">
            <i data-lucide="plus"></i>
            Add Appointment
        </a>
    </div>
</div>

<div class="card form-card">
    <div class="card-header">
        <div>
            <h2>Calendar Filters</h2>
            <p>Filter appointments without changing stored data.</p>
        </div>
    </div>

    <div class="card-body">
        <div class="form-row">
            <div class="field col-4">
                <label for="calendarPatient">Patient</label>
                <select id="calendarPatient">
                    <option value="">All Patients</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="calendarVisitType">Visit Type</label>
                <select id="calendarVisitType">
                    <option value="">All Visit Types</option>
                    <option value="New Patient">New Patient</option>
                    <option value="Follow-up">Follow-up</option>
                    <option value="Treatment Visit">Treatment Visit</option>
                    <option value="Review">Review</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="calendarAppointmentStatus">Appointment Status</label>
                <select id="calendarAppointmentStatus">
                    <option value="">All Appointment Status</option>
                    <option value="Scheduled">Scheduled</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Checked In">Checked In</option>
                    <option value="In Consultation">In Consultation</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="No Show">No Show</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card form-card">
    <div class="card-body">
        <div id="appointmentCalendar"></div>
    </div>
</div>

<?php require __DIR__ . '/modal/appointment-calendar-details.php'; ?>

<script>
(function (window, document) {
    'use strict';

    var patientSelect = GlobalSelect.init('#calendarPatient', {
        placeholder: 'All Patients'
    });

    var visitTypeSelect = GlobalSelect.init('#calendarVisitType', {
        placeholder: 'All Visit Types'
    });

    var appointmentStatusSelect = GlobalSelect.init('#calendarAppointmentStatus', {
        placeholder: 'All Appointment Status'
    });

    function patientItems(rows) {
        return (rows || []).map(function (row) {
            return {
                value: row.ref,
                text: row.label || (
                    (row.patient_code || '') +
                    ' - ' +
                    (row.patient_name || '')
                )
            };
        });
    }

    function queryForRange(info) {
        var params = new URLSearchParams();

        params.set('calendar', '1');
        params.set('start', String(info.startStr || '').slice(0, 10));
        params.set('end', String(info.endStr || '').slice(0, 10));

        var patientRef = document.getElementById('calendarPatient').value;
        var visitType = document.getElementById('calendarVisitType').value;
        var appointmentStatus = document.getElementById('calendarAppointmentStatus').value;

        if (patientRef) {
            params.set('patient_ref', patientRef);
        }

        if (visitType) {
            params.set('visit_type', visitType);
        }

        if (appointmentStatus) {
            params.set('appointment_status', appointmentStatus);
        }

        return params;
    }

    var calendar = new FullCalendar.Calendar(
        document.getElementById('appointmentCalendar'),
        {
            initialView: 'dayGridMonth',
            height: 'auto',
            nowIndicator: true,
            selectable: true,
            dayMaxEvents: true,

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },

            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            },

            events: function (info, successCallback, failureCallback) {
                App.api(
                    'api/appointments.php?' +
                    queryForRange(info).toString()
                )
                .then(function (response) {
                    var data =
                        response &&
                        response.data &&
                        typeof response.data === 'object'
                            ? response.data
                            : {};

                    var actions =
                        Array.isArray(data.allowed_actions)
                            ? data.allowed_actions.map(Number)
                            : [];

                    document.getElementById('calendarAddAppointment').style.display =
                        actions.indexOf(2) !== -1
                            ? 'inline-flex'
                            : 'none';

                    successCallback(
                        Array.isArray(data.events)
                            ? data.events
                            : []
                    );
                })
                .catch(function (error) {
                    App.showError(
                        error,
                        'Unable to load Appointment Calendar.'
                    );

                    failureCallback(error);
                });
            },

            eventClick: function (info) {
                info.jsEvent.preventDefault();

                if (
                    window.AppAppointmentCalendarDetails
                ) {
                    AppAppointmentCalendarDetails.open(
                        info.event.id
                    );
                }
            },

            dateClick: function (info) {
                var dateText =
                    String(info.dateStr || '').slice(0, 10);

                var timeText = '';

                if (String(info.dateStr || '').indexOf('T') !== -1) {
                    var dateValue = info.date;

                    timeText =
                        String(dateValue.getHours()).padStart(2, '0') +
                        ':' +
                        String(dateValue.getMinutes()).padStart(2, '0');
                }

                var url =
                    'appointment-form.php?date=' +
                    encodeURIComponent(dateText);

                if (timeText) {
                    url +=
                        '&time=' +
                        encodeURIComponent(timeText);
                }

                window.location.href = url;
            },

            eventDidMount: function (info) {
                var props = info.event.extendedProps || {};

                info.el.title =
                    (props.appointment_no || '') +
                    ' | ' +
                    (props.patient_name || '') +
                    ' | ' +
                    (props.visit_type || '') +
                    ' | ' +
                    (props.appointment_status || '');
            }
        }
    );

    async function load() {
        try {
            var response =
                await App.api(
                    'api/appointments.php?options=1'
                );

            var data =
                response &&
                response.data &&
                typeof response.data === 'object'
                    ? response.data
                    : {};

            patientSelect.setOptions(
                patientItems(
                    Array.isArray(data.patients)
                        ? data.patients
                        : []
                ),
                ''
            );

            calendar.render();

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            App.showError(
                error,
                'Unable to prepare Appointment Calendar.'
            );
        }
    }

    [
        'calendarPatient',
        'calendarVisitType',
        'calendarAppointmentStatus'
    ].forEach(function (id) {
        document.getElementById(id)
            .addEventListener(
                'change',
                function () {
                    calendar.refetchEvents();
                }
            );
    });

    load();

})(window, document);
</script>

</section>

<?php require __DIR__ . '/include/footer.php'; ?>

</main>
</div>

<script src="assets/js/appearance.js"></script>
<script>
if(window.lucide){
    window.lucide.createIcons();
}
</script>

</body>
</html>
