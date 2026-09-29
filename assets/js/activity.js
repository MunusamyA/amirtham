(function (window, document) {
    "use strict";

    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; } };
    var LAST_ACTIVITY_KEY = runtime.key("last_user_activity");
    var LAST_PING_KEY = runtime.key("last_activity_ping");
    var MIN_PING_INTERVAL_MS = 2 * 60 * 1000;
    var CHECK_INTERVAL_MS = 15 * 1000;
    var pingInFlight = false;

    function now() { return Date.now(); }

    function userConfig() {
        return window.App ? (App.getUser() || {}) : {};
    }

    function idleMinutes() {
        var user = userConfig();
        var value = Number(user.idle_expiry_minutes || user.idle_timeout_minutes || 60);
        if (!Number.isFinite(value) || value < 5) value = 60;
        return value;
    }

    function absoluteExpiresAtMs() {
        var value = Number(userConfig().absolute_expires_at_unix || 0);
        return Number.isFinite(value) && value > 0 ? value * 1000 : 0;
    }

    function readNumber(key, fallback) {
        try {
            var value = Number(localStorage.getItem(key) || 0);
            return Number.isFinite(value) && value > 0 ? value : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function writeNumber(key, value) {
        try { localStorage.setItem(key, String(value)); } catch (error) {}
    }

    function lastActivity() {
        return readNumber(LAST_ACTIVITY_KEY, now());
    }

    function markLocalActivity() {
        if (!window.App || !App.getToken()) return;
        var time = now();
        writeNumber(LAST_ACTIVITY_KEY, time);
        maybePing(time);
    }

    async function maybePing(time) {
        if (pingInFlight || !window.App || !App.getToken()) return;
        var lastPing = readNumber(LAST_PING_KEY, 0);
        if ((time - lastPing) < MIN_PING_INTERVAL_MS) return;

        pingInFlight = true;
        try {
            var result = await App.api("api/session-activity.php", { method: "POST" });
            var data = result.data || {};
            App.updateToken(data.token, {
                idle_expiry_minutes: Number(data.idle_expiry_minutes || data.idle_timeout_minutes || idleMinutes()),
                idle_timeout_minutes: Number(data.idle_expiry_minutes || data.idle_timeout_minutes || idleMinutes()),
                idle_expires_at_unix: Number(data.idle_expires_at_unix || 0),
                absolute_login_expiry_minutes: Number(data.absolute_login_expiry_minutes || 0),
                absolute_expires_at_unix: Number(data.absolute_expires_at_unix || 0)
            });
            writeNumber(LAST_PING_KEY, now());
        } catch (error) {
            // App.api handles 401 by clearing login and redirecting.
            if (error && error.status !== 401 && window.App) {
                App.showError(error, "Unable to renew session activity.");
            }
        } finally {
            pingInFlight = false;
        }
    }

    function checkExpiry() {
        if (!window.App || !App.getToken()) return;

        var absoluteMs = absoluteExpiresAtMs();
        if (absoluteMs > 0 && now() >= absoluteMs) {
            App.goToLogin("Your login has reached its maximum duration. Please login again.");
            return;
        }

        var idleMs = idleMinutes() * 60 * 1000;
        if ((now() - lastActivity()) >= idleMs) {
            App.goToLogin("Your session expired due to inactivity. Please login again.");
        }
    }

    function bind() {
        if (!window.App || !App.getToken()) return;

        // These represent real browser interaction. Background API polling does not renew the token.
        ["pointerdown", "keydown", "input", "change", "touchstart", "scroll"].forEach(function (name) {
            document.addEventListener(name, markLocalActivity, { passive: true, capture: true });
        });

        window.addEventListener("focus", checkExpiry);
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) checkExpiry();
        });

        checkExpiry();
        window.setInterval(checkExpiry, CHECK_INTERVAL_MS);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bind, { once: true });
    } else {
        bind();
    }
})(window, document);
