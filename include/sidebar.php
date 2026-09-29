
<?php
require_once __DIR__ . '/web-config.php';
?>

<aside class="sidebar" id="appSidebar" aria-label="Main navigation">

    <div class="sidebar-brand">

        <div class="brand-mark" id="sidebarBrandMark" aria-hidden="true">
            <span class="brand-stem"></span>
        </div>

        <div class="app-brand-text">

            <div class="brand-title">
                <?php echo web_h(app_name()); ?>
            </div>

            <div class="app-brand-subtitle">
                <?php echo web_h(app_subtitle()); ?>
            </div>

        </div>

    </div>

    <nav class="sidebar-nav" id="appSidebarMenu">
        <div class="sidebar-loading">
            Loading permitted menus...
        </div>
    </nav>

    <div class="sidebar-mini-footer">
        <?php echo web_h(app_short_name()); ?> · V 0.0.1
    </div>

</aside>

<div class="sidebar-flyout" id="sidebarFlyout" hidden></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
(function (window, document) {
    "use strict";

    /*
     * Load company logo using the existing authenticated
     * application API helper.
     *
     * IMPORTANT:
     * Do not manually read localStorage.getItem("auth_token").
     * Vimal Aqua uses an application-specific token key.
     */

    async function loadSidebarBrand() {

        const brandMark = document.getElementById("sidebarBrandMark");

        if (!brandMark) {
            return;
        }

        try {

            if (
                !window.App ||
                typeof window.App.api !== "function" ||
                typeof window.App.getToken !== "function"
            ) {
                return;
            }

            if (!window.App.getToken()) {
                return;
            }

            /*
             * App.api() automatically attaches:
             *
             * Authorization: Bearer <current token>
             *
             * It also handles authentication expiry.
             */
            const result = await window.App.api("api/sidebar.php");

            if (!result || result.success !== true) {
                return;
            }

            const payload = result.data || {};
            const user = payload.user || {};

            const logoUrl = String(
                user.company_logo_url || ""
            ).trim();

            /*
             * No uploaded logo:
             * Keep the existing default brand mark.
             */
            if (!logoUrl) {
                return;
            }

            /*
             * Avoid replacing the default logo until
             * the uploaded image loads successfully.
             */
            const logo = document.createElement("img");

            logo.className = "sidebar-brand-logo";

            logo.alt = String(
                user.company_name || "Company"
            ) + " Logo";

            logo.onload = function () {

                brandMark.classList.add("brand-mark-logo");

                brandMark.replaceChildren(logo);

            };

            logo.onerror = function () {

                /*
                 * If the uploaded image cannot load,
                 * preserve the default brand mark.
                 */
                console.warn(
                    "Sidebar company logo could not be loaded."
                );

            };

            logo.src = logoUrl;

        } catch (error) {

            /*
             * Do not interrupt the sidebar if logo
             * loading fails.
             *
             * App.api() already handles HTTP 401.
             */
            if (!error || error.status !== 401) {

                console.warn(
                    "Sidebar logo loading failed:",
                    error
                );

            }

        }

    }

    /*
     * Run only after the application API helper is available.
     */
    function initializeSidebarBrand() {

        loadSidebarBrand();

    }

    if (document.readyState === "loading") {

        document.addEventListener(
            "DOMContentLoaded",
            initializeSidebarBrand,
            { once: true }
        );

    } else {

        initializeSidebarBrand();

    }

})(window, document);
</script>
