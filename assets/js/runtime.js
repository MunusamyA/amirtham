(function (window, document) {
    "use strict";

    var config = window.STARTER_CONFIG || {};
    var appKey = String(config.appKey || "amirtham").toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^[-_]+|[-_]+$/g, "") || "amirtham";

    function key(name) {
        return appKey + "_" + String(name || "");
    }

    function event(name) {
        return appKey + ":" + String(name || "");
    }

    function safeJson(raw, fallback) {
        try { return raw ? JSON.parse(raw) : fallback; } catch (error) { return fallback; }
    }

    window.AppRuntime = {
        config: config,
        appKey: appKey,
        key: key,
        event: event,
        appName: String(config.appName || "Amirtham Integrated Management"),
        appSubtitle: String(config.appSubtitle || "Business Management Platform")
    };

    // Pre-paint state so sidebar/theme do not flash between page navigations.
    try {
        if (sessionStorage.getItem(key("sidebar_collapsed")) === "1" && window.innerWidth > 1024) {
            document.documentElement.classList.add("sidebar-precollapsed");
        }
    } catch (error) {}

    try {
        var appearance = sessionStorage.getItem(key("appearance")) || "light";
        document.documentElement.setAttribute("data-appearance", appearance === "dark" ? "dark" : "light");
        document.documentElement.setAttribute("data-ui-theme", String(config.uiTheme || "default"));
    } catch (error) {}

    try {
        var user = safeJson(localStorage.getItem(key("auth_user")), {}) || {};
        var scope = Number(user.branch_id || 0) > 0 ? String(Number(user.branch_id)) : "platform";
        var cached = safeJson(localStorage.getItem(key("theme_v2_" + scope)), {}) || {};
        Object.keys(cached).forEach(function (name) {
            if (String(name).indexOf("--") === 0) {
                document.documentElement.style.setProperty(name, cached[name]);
            }
        });
    } catch (error) {}
})(window, document);
