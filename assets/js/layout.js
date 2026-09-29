(function (window, document) {
    "use strict";

    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; }, event: function (name) { return "amirtham:" + name; }, appName: "Amirtham Integrated Management" };
    var desktopSidebarCollapsed = false;
    var notificationTimer = null;
    var sidebarSessionKey = runtime.key("sidebar_collapsed");
    var sidebarMenuCachePrefix = runtime.key("sidebar_menu_");
    var flyoutCloseTimer = null;

    function readSidebarSessionState() {
        try {
            return sessionStorage.getItem(sidebarSessionKey) === "1";
        } catch (error) {
            return false;
        }
    }

    function writeSidebarSessionState(collapsed) {
        try {
            sessionStorage.setItem(sidebarSessionKey, collapsed ? "1" : "0");
        } catch (error) {}
    }

    function iconName(value, fallback) {
        value = String(value || "").trim().toLowerCase();
        var aliases = {
            "mail-cog": "mail",
            "mail-settings": "mail"
        };
        value = aliases[value] || value;
        return value || fallback || "circle";
    }

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === "function") {
            window.lucide.createIcons();
        }
    }

    function pageMatches(path) {
        var current = window.location.pathname.split("/").pop() || "dashboard.php";
        return current === String(path || "").split("?")[0];
    }

    function usableHref(item) {
        var path = String(item.menu_path || "");
        return /\.php(?:\?|$)/i.test(path) ? path : "#";
    }


    function isDesktopCollapsed() {
        return window.innerWidth > 1024 && document.body.classList.contains("sidebar-collapsed");
    }

    function itemHasActive(item) {
        if (pageMatches(item.menu_path)) return true;
        return (item.children || []).some(itemHasActive);
    }

    function sidebarCacheKey(user) {
        user = user || {};
        return sidebarMenuCachePrefix + [Number(user.id || 0), Number(user.role_id || 0), Number(user.branch_id || 0)].join("_");
    }

    function readSidebarMenuCache(user) {
        try {
            var raw = sessionStorage.getItem(sidebarCacheKey(user));
            var parsed = raw ? JSON.parse(raw) : null;
            return parsed && Array.isArray(parsed.menus) ? parsed : null;
        } catch (error) { return null; }
    }

    function writeSidebarMenuCache(user, menus) {
        try {
            sessionStorage.setItem(sidebarCacheKey(user), JSON.stringify({ menus: menus || [], saved_at: Date.now() }));
        } catch (error) {}
    }

    function cancelFlyoutClose() {
        if (flyoutCloseTimer) window.clearTimeout(flyoutCloseTimer);
        flyoutCloseTimer = null;
    }

    function hideSidebarFlyout(immediate) {
        var flyout = document.getElementById("sidebarFlyout");
        if (!flyout) return;
        cancelFlyoutClose();
        var close = function () {
            flyout.hidden = true;
            flyout.innerHTML = "";
            flyout.removeAttribute("data-open-for");
        };
        if (immediate) close();
        else flyoutCloseTimer = window.setTimeout(close, 140);
    }

    function buildFlyoutItems(items, container, depth) {
        depth = depth || 0;
        (items || []).forEach(function (item) {
            var children = item.children || [];
            if (children.length) {
                var heading = document.createElement("div");
                heading.className = "sidebar-flyout-group";
                heading.textContent = item.menu_name || "Menu";
                heading.style.setProperty("--flyout-depth", String(depth));
                container.appendChild(heading);
                buildFlyoutItems(children, container, depth + 1);
                return;
            }
            var link = document.createElement("a");
            link.className = "sidebar-flyout-link" + (pageMatches(item.menu_path) ? " active" : "");
            link.href = usableHref(item);
            link.style.setProperty("--flyout-depth", String(depth));
            link.innerHTML = '<i data-lucide="' + iconName(item.icon, "circle") + '"></i><span></span>';
            link.querySelector("span").textContent = item.menu_name || "Menu";
            if (link.getAttribute("href") === "#") link.addEventListener("click", function (event) { event.preventDefault(); });
            container.appendChild(link);
        });
    }

    function cssPixels(name, fallback) {
        var value = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function showSidebarFlyout(anchor, item) {
        if (!isDesktopCollapsed() || !(item.children || []).length) return;
        var flyout = document.getElementById("sidebarFlyout");
        var sidebar = document.getElementById("appSidebar");
        var topbar = document.querySelector(".topbar");
        if (!flyout || !sidebar || !anchor) return;

        cancelFlyoutClose();
        flyout.innerHTML = "";

        var title = document.createElement("div");
        title.className = "sidebar-flyout-title";
        title.textContent = item.menu_name || "Menu";
        flyout.appendChild(title);

        var body = document.createElement("div");
        body.className = "sidebar-flyout-body";
        buildFlyoutItems(item.children || [], body, 0);
        flyout.appendChild(body);

        flyout.hidden = false;
        flyout.dataset.openFor = String(item.id || item.menu_name || "menu");

        var sideRect = sidebar.getBoundingClientRect();
        var anchorRect = anchor.getBoundingClientRect();
        var topbarRect = topbar ? topbar.getBoundingClientRect() : null;
        var configuredTopbar = cssPixels("--topbar-height", 56);
        var configuredWidth = cssPixels("--submenu-width", 238);
        var gap = cssPixels("--submenu-gap", 8);
        var viewportGap = cssPixels("--viewport-gap", 8);

        var minTop = Math.max(viewportGap, topbarRect ? topbarRect.bottom + gap : configuredTopbar + gap);
        var maxWidth = Math.max(180, window.innerWidth - sideRect.right - (viewportGap * 2));
        var width = Math.min(configuredWidth, maxWidth);

        flyout.style.width = width + "px";
        flyout.style.left = Math.min(sideRect.right + gap, window.innerWidth - width - viewportGap) + "px";

        var availableHeight = Math.max(120, window.innerHeight - minTop - viewportGap);
        flyout.style.maxHeight = availableHeight + "px";

        var flyoutHeight = Math.min(flyout.scrollHeight, availableHeight);
        var top = Math.max(minTop, anchorRect.top - gap);
        var maxTop = window.innerHeight - flyoutHeight - viewportGap;
        top = Math.min(top, Math.max(minTop, maxTop));

        flyout.style.top = top + "px";
        refreshIcons();
    }

    function paintSidebarMenus(menus) {
        var menu = document.getElementById("appSidebarMenu");
        if (!menu) return;
        menu.innerHTML = "";
        if (!(menus || []).length) menu.innerHTML = '<div class="sidebar-loading">No permitted menus</div>';
        else renderMenus(menus, menu, 0);
    }

    function scrollActiveMenuIntoView() {
        var sidebarNav = document.getElementById("appSidebarMenu");
        if (!sidebarNav || isDesktopCollapsed()) return;

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                var activeLink = sidebarNav.querySelector(".nav-link.active");
                if (!activeLink) return;

                var navRect = sidebarNav.getBoundingClientRect();
                var activeRect = activeLink.getBoundingClientRect();

                // Scroll only when the active item is outside the visible menu area.
                if (activeRect.top < navRect.top || activeRect.bottom > navRect.bottom) {
                    var targetScroll = sidebarNav.scrollTop +
                        (activeRect.top - navRect.top) -
                        (sidebarNav.clientHeight / 2) +
                        (activeRect.height / 2);

                    sidebarNav.scrollTo({
                        top: Math.max(0, targetScroll),
                        behavior: "auto"
                    });
                }
            });
        });
    }

    function closeMenuGroup(group) {
        if (!group) return;

        var directButton = null;
        var directSubmenu = null;

        Array.prototype.forEach.call(group.children || [], function (child) {
            if (!child.classList) return;
            if (child.classList.contains("nav-parent")) directButton = child;
            if (child.classList.contains("submenu")) directSubmenu = child;
        });

        if (directSubmenu) {
            directSubmenu.classList.remove("open");

            // Reset nested accordion state as well, so reopening starts clean.
            Array.prototype.forEach.call(directSubmenu.querySelectorAll(".submenu.open"), function (nestedSubmenu) {
                nestedSubmenu.classList.remove("open");
            });
            Array.prototype.forEach.call(directSubmenu.querySelectorAll('.nav-parent[aria-expanded="true"]'), function (nestedButton) {
                nestedButton.setAttribute("aria-expanded", "false");
            });
        }

        if (directButton) directButton.setAttribute("aria-expanded", "false");
    }

    function closeSiblingMenus(target, currentGroup) {
        Array.prototype.forEach.call(target.children || [], function (otherGroup) {
            if (otherGroup === currentGroup) return;
            if (!otherGroup.classList || !otherGroup.classList.contains("nav-group")) return;
            closeMenuGroup(otherGroup);
        });
    }

    function renderMenus(items, target, depth) {
        depth = depth || 0;

        (items || []).forEach(function (item) {
            var children = item.children || [];
            var hasChildren = children.length > 0;
            var activeInside = itemHasActive(item);

            var group = document.createElement("div");
            group.className = "nav-group" + (activeInside ? " has-active" : "");
            group.__menuItem = item;

            if (hasChildren) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "nav-parent";
                button.setAttribute("aria-expanded", activeInside ? "true" : "false");
                button.setAttribute("title", item.menu_name || "Menu");
                button.innerHTML = '<i data-lucide="' + iconName(item.icon, "folder") + '"></i>' +
                    '<span class="nav-label"><strong></strong></span>' +
                    '<i class="chevron" data-lucide="chevron-down"></i>';
                button.querySelector("strong").textContent = item.menu_name || "Menu";
                group.appendChild(button);

                var submenu = document.createElement("div");
                submenu.className = activeInside ? "submenu open" : "submenu";
                renderMenus(children, submenu, depth + 1);
                group.appendChild(submenu);

                button.addEventListener("click", function () {
                    if (isDesktopCollapsed()) {
                        showSidebarFlyout(button, item);
                        return;
                    }

                    var willOpen = !submenu.classList.contains("open");

                    // Accordion behaviour: only one submenu at the same level stays open.
                    if (willOpen) closeSiblingMenus(target, group);

                    submenu.classList.toggle("open", willOpen);
                    button.setAttribute("aria-expanded", willOpen ? "true" : "false");
                });

                group.addEventListener("mouseenter", function () {
                    if (isDesktopCollapsed()) showSidebarFlyout(button, item);
                });
                group.addEventListener("mouseleave", function () {
                    if (isDesktopCollapsed()) hideSidebarFlyout(false);
                });
            } else {
                var link = document.createElement("a");
                link.className = "nav-link" + (pageMatches(item.menu_path) ? " active" : "");
                link.href = usableHref(item);
                link.setAttribute("title", item.menu_name || "Menu");
                link.innerHTML = '<i data-lucide="' + iconName(item.icon, "circle") + '"></i><span class="nav-label"></span>';
                link.querySelector(".nav-label").textContent = item.menu_name || "Menu";
                if (link.getAttribute("href") === "#") {
                    link.addEventListener("click", function (event) { event.preventDefault(); });
                }
                group.appendChild(link);
            }

            target.appendChild(group);
        });

        refreshIcons();
    }

    function closeMobileSidebar() {
        var sidebar = document.getElementById("appSidebar");
        var overlay = document.getElementById("sidebarOverlay");
        if (sidebar) sidebar.classList.remove("open");
        if (overlay) overlay.classList.remove("open");
        document.body.style.overflow = "";
    }

    function openMobileSidebar() {
        var sidebar = document.getElementById("appSidebar");
        var overlay = document.getElementById("sidebarOverlay");
        if (sidebar) sidebar.classList.add("open");
        if (overlay) overlay.classList.add("open");
        document.body.style.overflow = "hidden";
    }

    function updateCollapseButton() {
        var button = document.getElementById("sidebarCollapseToggle");
        if (!button) return;

        var collapsed = document.body.classList.contains("sidebar-collapsed") && window.innerWidth > 1024;
        var mobileOpen = false;
        var sidebar = document.getElementById("appSidebar");

        if (window.innerWidth <= 1024 && sidebar) {
            mobileOpen = sidebar.classList.contains("open");
        }

        // Always use the three-line hamburger icon in the topbar.
        button.innerHTML = '<i data-lucide="menu"></i>';
        button.setAttribute("aria-expanded", window.innerWidth <= 1024 ? (mobileOpen ? "true" : "false") : (collapsed ? "false" : "true"));
        button.setAttribute("aria-label", window.innerWidth <= 1024 ? (mobileOpen ? "Close menu" : "Open menu") : (collapsed ? "Expand sidebar" : "Collapse sidebar"));
        button.setAttribute("title", window.innerWidth <= 1024 ? (mobileOpen ? "Close menu" : "Open menu") : (collapsed ? "Expand sidebar" : "Collapse sidebar"));
        refreshIcons();
    }

    function notifyLayoutChanged() {
        window.clearTimeout(notifyLayoutChanged.timer);
        notifyLayoutChanged.timer = window.setTimeout(function () {
            window.dispatchEvent(new CustomEvent(runtime.event("layout-resize")));
        }, 240);
    }

    function setSidebarCollapsed(collapsed) {
        if (window.innerWidth <= 1024) return;
        desktopSidebarCollapsed = Boolean(collapsed);
        document.body.classList.toggle("sidebar-collapsed", desktopSidebarCollapsed);
        hideSidebarFlyout(true);
        writeSidebarSessionState(desktopSidebarCollapsed);
        document.documentElement.classList.remove("sidebar-precollapsed");
        updateCollapseButton();
        notifyLayoutChanged();
    }

    function toggleDesktopSidebar() {
        setSidebarCollapsed(!document.body.classList.contains("sidebar-collapsed"));
    }

    function restoreDesktopSidebar() {
        if (window.innerWidth <= 1024) {
            document.body.classList.remove("sidebar-collapsed");
            document.documentElement.classList.remove("sidebar-precollapsed");
            updateCollapseButton();
            return;
        }
        desktopSidebarCollapsed = readSidebarSessionState();
        document.body.classList.toggle("sidebar-collapsed", desktopSidebarCollapsed);
        document.documentElement.classList.remove("sidebar-precollapsed");
        updateCollapseButton();
    }

    function closeDropdowns(except) {
        ["notificationMenu", "profileMenu"].forEach(function (id) {
            var menu = document.getElementById(id);
            if (menu && menu !== except) menu.classList.remove("open");
        });
        ["notificationButton", "profileButton"].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.setAttribute("aria-expanded", "false");
        });
    }

    function toggleDropdown(button, menu) {
        if (!button || !menu) return;
        var open = !menu.classList.contains("open");
        closeDropdowns(menu);
        menu.classList.toggle("open", open);
        button.setAttribute("aria-expanded", open ? "true" : "false");
    }

    function notificationSeenKey() {
        var user = window.App ? App.getUser() : {};
        return runtime.key("notifications_seen_") + String(user.id || 0);
    }

    function formatNotificationTime(value) {
        if (!value) return "";
        var parsed = new Date(String(value).replace(" ", "T"));
        if (isNaN(parsed.getTime())) return value;
        return parsed.toLocaleString("en-IN", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
    }

    function renderNotifications(items) {
        var list = document.getElementById("notificationList");
        var badge = document.getElementById("notificationBadge");
        if (!list || !badge) return;
        list.innerHTML = "";
        if (!(items || []).length) {
            list.innerHTML = '<div class="dropdown-empty"><i data-lucide="bell-off"></i><span>No notifications yet</span></div>';
            badge.classList.add("hidden");
            refreshIcons();
            return;
        }

        window.__appNotificationItems = items || [];
        var lastSeen = Number(localStorage.getItem(notificationSeenKey()) || 0);
        var latestId = 0;
        var unread = 0;
        items.forEach(function (item) {
            latestId = Math.max(latestId, Number(item.id || 0));
            if (Number(item.id || 0) > lastSeen) unread += 1;
            var row = document.createElement("div");
            row.className = "notification-item" + (Number(item.id || 0) > lastSeen ? " unread" : "");
            row.innerHTML = '<span class="notification-icon"><i data-lucide="' + iconName(item.icon, "activity") + '"></i></span>' +
                '<span class="notification-copy"><strong></strong><small></small></span>';
            row.querySelector("strong").textContent = item.message || "Activity updated";
            row.querySelector("small").textContent = formatNotificationTime(item.created_at);
            list.appendChild(row);
        });
        badge.dataset.latestId = String(latestId);
        if (unread > 0) {
            badge.textContent = unread > 99 ? "99+" : String(unread);
            badge.classList.remove("hidden");
        } else {
            badge.classList.add("hidden");
        }
        refreshIcons();
    }

    async function loadNotifications(silent) {
        if (!window.App || !App.getToken()) return;
        try {
            var result = await App.api("api/notifications.php?limit=10");
            renderNotifications(result.data.notifications || []);
        } catch (error) {
            if (!silent) App.showError(error, "Unable to load notifications.");
        }
    }

    function markNotificationsSeen() {
        var badge = document.getElementById("notificationBadge");
        var highest = Number(badge && badge.dataset.latestId || 0);
        if (highest > 0) localStorage.setItem(notificationSeenKey(), String(highest));
        var badgeEl = document.getElementById("notificationBadge");
        if (badgeEl) badgeEl.classList.add("hidden");
        Array.prototype.forEach.call(document.querySelectorAll(".notification-item.unread"), function (row) { row.classList.remove("unread"); });
    }

    async function initLayout() {
        if (!window.App || !App.requireAuth()) return;

        var cached = App.getUser();
        var userName = document.getElementById("appUserName");
        var roleName = document.getElementById("appRoleName");
        var branchText = document.getElementById("appCompanyBranch");
        var profileName = document.getElementById("profileMenuName");
        var profileEmail = document.getElementById("profileMenuEmail");

        function paintUser(user) {
            if (userName) userName.textContent = user.name || "User";
            if (roleName) roleName.textContent = user.role_name || "";
            if (branchText) branchText.textContent = [user.company_name, user.branch_name].filter(Boolean).join(" · ") || runtime.appName;
            if (profileName) profileName.textContent = user.name || "User";
            if (profileEmail) profileEmail.textContent = user.email || user.username || "";
        }
        paintUser(cached);
        var cachedMenus = readSidebarMenuCache(cached);
        if (cachedMenus) {
            paintSidebarMenus(cachedMenus.menus);
            scrollActiveMenuIntoView();
        }

        try {
            var result = await App.api("api/sidebar.php");
            var user = result.data.user || {};
            App.saveLogin(App.getToken(), user);
            paintUser(user);
            paintSidebarMenus(result.data.menus || []);
            scrollActiveMenuIntoView();
            writeSidebarMenuCache(user, result.data.menus || []);
            if (window.Theme) Theme.loadCurrent(true);
            loadNotifications(true);
        } catch (error) {
            if (!cachedMenus) paintSidebarMenus([]);
            App.showError(error, "Unable to load application layout.");
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        restoreDesktopSidebar();

        var collapse = document.getElementById("sidebarCollapseToggle");
        var overlay = document.getElementById("sidebarOverlay");
        var logout = document.getElementById("appLogoutButton");
        var notificationButton = document.getElementById("notificationButton");
        var notificationMenu = document.getElementById("notificationMenu");
        var notificationRefresh = document.getElementById("notificationRefresh");
        var profileButton = document.getElementById("profileButton");
        var profileMenu = document.getElementById("profileMenu");
        var sidebarFlyout = document.getElementById("sidebarFlyout");

        if (overlay) overlay.addEventListener("click", function () {
            closeMobileSidebar();
            updateCollapseButton();
        });

        if (collapse) collapse.addEventListener("click", function () {
            if (window.innerWidth <= 1024) {
                var sidebar = document.getElementById("appSidebar");

                if (sidebar && sidebar.classList.contains("open")) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }

                updateCollapseButton();
                return;
            }

            toggleDesktopSidebar();
        });
        if (logout) logout.addEventListener("click", function () { App.logout(); });
        if (sidebarFlyout) {
            sidebarFlyout.addEventListener("mouseenter", cancelFlyoutClose);
            sidebarFlyout.addEventListener("mouseleave", function () { hideSidebarFlyout(false); });
        }

        if (profileButton && profileMenu) profileButton.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleDropdown(profileButton, profileMenu);
        });
        if (notificationButton && notificationMenu) notificationButton.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleDropdown(notificationButton, notificationMenu);
            if (notificationMenu.classList.contains("open")) {
                loadNotifications(true).then(markNotificationsSeen);
            }
        });
        if (notificationRefresh) notificationRefresh.addEventListener("click", function (event) {
            event.stopPropagation();
            loadNotifications(false);
        });

        document.addEventListener("click", function (event) {
            if (!event.target.closest(".top-dropdown")) closeDropdowns(null);
            if (!event.target.closest(".sidebar-flyout") && !event.target.closest(".nav-parent")) hideSidebarFlyout(true);
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeDropdowns(null);
                closeMobileSidebar();
                updateCollapseButton();
                hideSidebarFlyout(true);
            }
        });

        window.addEventListener("resize", function () {
            if (window.innerWidth > 1024) closeMobileSidebar();
            restoreDesktopSidebar();
            hideSidebarFlyout(true);
            notifyLayoutChanged();
        });

        notificationTimer = window.setInterval(function () { loadNotifications(true); }, 60000);
        window.addEventListener("beforeunload", function () { if (notificationTimer) window.clearInterval(notificationTimer); });

        initLayout();
        refreshIcons();
    });
})(window, document);
