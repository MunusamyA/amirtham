<?php
declare(strict_types=1);

require_once __DIR__ . '/web-config.php';

$commonPageTitle = isset($pageTitle) && $pageTitle !== ''
    ? (string) $pageTitle
    : app_name();
?>
<header class="topbar">
    <div class="topbar-left">
        <button
            class="icon-button hover-primary"
            id="sidebarCollapseToggle"
            type="button"
            aria-label="Toggle sidebar"
            aria-expanded="true"
            title="Toggle sidebar"
        >
            <i data-lucide="menu"></i>
        </button>

        <div class="topbar-page-title">
            <strong><?php echo htmlspecialchars($commonPageTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
            <small id="appCompanyBranch"><?php echo web_h(app_name()); ?></small>
        </div>
    </div>

    <div class="topbar-actions">
        <!-- Uses only the existing .btn, .small, .gray and .btn-primary styles. -->
        <div class="topbar-actions" id="amManagementSwitcher"
             role="group" aria-label="Management section" hidden>
            <button type="button" class="btn small gray" data-am-section="clinic"
                    aria-label="Clinic Management" aria-pressed="false"
                    title="Clinic Management" hidden disabled>
                <i data-lucide="stethoscope" aria-hidden="true"></i>
                <span data-am-label="clinic">Clinic Management</span>
            </button>
            <button type="button" class="btn small gray" data-am-section="college"
                    aria-label="College Management" aria-pressed="false"
                    title="College Management" hidden disabled>
                <i data-lucide="graduation-cap" aria-hidden="true"></i>
                <span data-am-label="college">College Management</span>
            </button>
            <button type="button" class="btn small gray" data-am-section="food_supplementary"
                    aria-label="Food Supplementary" aria-pressed="false"
                    title="Food Supplementary" hidden disabled>
                <i data-lucide="package" aria-hidden="true"></i>
                <span data-am-label="food_supplementary">Food Supplementary</span>
            </button>
        </div>

        <button
            class="icon-button appearance-toggle"
            id="appearanceToggle"
            type="button"
            aria-label="Dark mode"
            title="Dark mode"
        >
            <i data-lucide="moon"></i>
        </button>

        <div class="top-dropdown notification-wrap" id="notificationWrap">
            <button
                class="icon-button"
                id="notificationButton"
                type="button"
                aria-label="Notifications"
                aria-expanded="false"
                title="Notifications"
            >
                <i data-lucide="bell"></i>
                <span class="badge-dot hidden" id="notificationBadge">0</span>
            </button>

            <div class="top-dropdown-menu notification-menu" id="notificationMenu">
                <div class="dropdown-head">
                    <div>
                        <strong>Notifications</strong>
                        <small>Recent system activity</small>
                    </div>
                    <button
                        class="dropdown-icon-button"
                        id="notificationRefresh"
                        type="button"
                        title="Refresh notifications"
                    >
                        <i data-lucide="refresh-cw"></i>
                    </button>
                </div>

                <div class="notification-list" id="notificationList">
                    <div class="dropdown-empty">Loading...</div>
                </div>
            </div>
        </div>

        <div class="top-dropdown profile-wrap" id="profileWrap">
            <button
                class="profile-button"
                id="profileButton"
                type="button"
                aria-expanded="false"
            >
                <span class="avatar" aria-hidden="true">
                    <i data-lucide="user-round"></i>
                </span>

                <span class="topbar-user-copy">
                    <strong id="appUserName">User</strong>
                    <small id="appRoleName"></small>
                </span>

                <i data-lucide="chevron-down" class="profile-chevron"></i>
            </button>

            <div class="top-dropdown-menu profile-menu" id="profileMenu">
                <div class="profile-menu-header">
                    <strong id="profileMenuName">User</strong>
                    <small id="profileMenuEmail"></small>
                </div>

                <a href="my-profile.php">
                    <i data-lucide="circle-user-round"></i>
                    <span>My Profile</span>
                </a>

                <div class="dropdown-separator"></div>

                <button
                    class="profile-menu-action danger"
                    id="appLogoutButton"
                    type="button"
                >
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </div>
</header>

<?php require __DIR__ . '/modal.php'; ?>
<script src="assets/js/appearance.js"></script>
<script>
/* CSS-free management selector: uses the project's existing button components. */
(function () {
    'use strict';
    var COOKIE_NAME = 'amirtham_management_section_v1';
    var holder = document.getElementById('amManagementSwitcher');
    if (!holder) return;
    var buttons = Array.prototype.slice.call(holder.querySelectorAll('[data-am-section]'));
    var pageTitle = document.querySelector('.topbar-page-title');
    var originallyHiddenTitle = !!(pageTitle && pageTitle.classList.contains('hidden'));
    var permitted = Object.create(null);
    var selected = '';
    var ready = false;
    var labels = {
        clinic: ['Clinic Management', 'Clinic'],
        college: ['College Management', 'College'],
        food_supplementary: ['Food Supplementary', 'Food']
    };

    function fitExistingLayout() {
        // Existing theme controls all colours and dimensions; only button content changes.
        var width = document.documentElement.clientWidth || window.innerWidth;
        var iconsOnly = width <= 480;
        var shortLabels = width < 1500;
        buttons.forEach(function (button) {
            var key = button.getAttribute('data-am-section');
            var label = button.querySelector('[data-am-label]');
            if (!label || !labels[key]) return;
            label.textContent = shortLabels ? labels[key][1] : labels[key][0];
            label.hidden = iconsOnly;
        });
        if (pageTitle && !originallyHiddenTitle) {
            pageTitle.classList.toggle('hidden', ready && !holder.hidden && width <= 760);
        }
    }

    function draw() {
        var any = false;
        buttons.forEach(function (button) {
            var key = button.getAttribute('data-am-section');
            var available = !!permitted[key];
            var active = available && key === selected;
            button.hidden = !available;
            button.disabled = !available || !ready;
            button.setAttribute('aria-pressed', String(active));
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('gray', !active);
            any = any || available;
        });
        holder.hidden = !(ready && any);
        fitExistingLayout();
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function loadSections() {
        if (!window.App || typeof App.api !== 'function') return;
        App.api('api/sidebar.php?management_meta=1').then(function (response) {
            var info = response && response.data && response.data.management;
            if (!info || !Array.isArray(info.sections)) return;
            permitted = Object.create(null);
            info.sections.forEach(function (section) {
                if (section && section.key) permitted[String(section.key)] = true;
            });
            selected = String(info.selected || '');
            ready = true;
            draw();
        }).catch(function () {
            // Existing topbar controls continue working if module metadata is unavailable.
        });
    }

    holder.addEventListener('click', function (event) {
        var button = event.target.closest('[data-am-section]');
        if (!button || !holder.contains(button) || !ready) return;
        var key = button.getAttribute('data-am-section');
        if (!permitted[key] || selected === key) return;
        selected = key;
        ready = false;
        draw();
        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(key) + '; Path=/; SameSite=Lax';
        if (window.App && typeof App.clearSidebarCache === 'function') App.clearSidebarCache();
        window.location.reload();
    });

    window.addEventListener('resize', fitExistingLayout, { passive: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', loadSections);
    else loadSections();
})();
</script>
