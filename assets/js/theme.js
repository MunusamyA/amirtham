(function (window, document) {
    "use strict";

    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; }, event: function (name) { return "amirtham:" + name; } };
    var CACHE_PREFIX = runtime.key("theme_v5_");
    var OLD_CACHE_PREFIXES = [runtime.key("theme_v4_"), runtime.key("theme_v3_"), runtime.key("theme_v2_"), runtime.key("theme_v1_")];
    var appliedVariableNames = {};

    function scopeKey(branchId, prefix) {
        return (prefix || CACHE_PREFIX) + (Number(branchId || 0) > 0 ? String(Number(branchId)) : "platform");
    }

    function currentBranchId() {
        if (!window.App) return null;
        var user = App.getUser() || {};
        return Number(user.branch_id || 0) > 0 ? Number(user.branch_id) : null;
    }

    function normalizeVariables(cssVariables) {
        var output = {};
        if (!cssVariables || typeof cssVariables !== "object") return output;
        Object.keys(cssVariables).forEach(function (name) {
            if (String(name).indexOf("--") !== 0) return;
            output[name] = String(cssVariables[name]);
        });
        return output;
    }

    function clearCssVariables(names) {
        var root = document.documentElement;
        (names || Object.keys(appliedVariableNames)).forEach(function (name) {
            if (String(name).indexOf("--") !== 0) return;
            root.style.removeProperty(name);
            delete appliedVariableNames[name];
        });
    }

    function applyCssVariables(cssVariables, replace) {
        var values = normalizeVariables(cssVariables);
        var root = document.documentElement;
        if (replace) clearCssVariables();
        Object.keys(values).forEach(function (name) {
            root.style.setProperty(name, values[name]);
            appliedVariableNames[name] = true;
        });
    }

    function setPreset(preset) {
        preset = String(preset || "forest").trim().toLowerCase();
        document.documentElement.setAttribute("data-theme-preset", preset || "forest");
        return preset;
    }

    function currentPreset() {
        return document.documentElement.getAttribute("data-theme-preset") || "forest";
    }

    function computedValue(name) {
        return window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function computedHex(name) {
        var value = computedValue(name);
        if (/^#[0-9a-fA-F]{6}$/.test(value)) return value.toLowerCase();
        var probe = document.createElement("span");
        probe.style.position = "fixed";
        probe.style.visibility = "hidden";
        probe.style.color = "var(" + name + ")";
        document.body.appendChild(probe);
        var rgb = window.getComputedStyle(probe).color;
        probe.remove();
        var match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (!match) return value;
        return "#" + [match[1], match[2], match[3]].map(function (part) {
            return Number(part).toString(16).padStart(2, "0");
        }).join("");
    }

    function valuesToCss(values, fields) {
        var css = {};
        (fields || []).forEach(function (field) {
            if (field && field.key && field.css_variable && values[field.key]) css[field.css_variable] = values[field.key];
        });
        return css;
    }

    function clearOldCaches(branchId) {
        try { OLD_CACHE_PREFIXES.forEach(function (prefix) { localStorage.removeItem(scopeKey(branchId, prefix)); }); }
        catch (error) {}
    }

    function cacheTheme(branchId, payload) {
        try {
            localStorage.setItem(scopeKey(branchId), JSON.stringify({
                preset: String((payload && payload.preset) || "forest"),
                css_variables: normalizeVariables((payload && payload.css_variables) || {})
            }));
            clearOldCaches(branchId);
        } catch (error) {}
    }

    function readCache(branchId) {
        try {
            var parsed = JSON.parse(localStorage.getItem(scopeKey(branchId)) || "{}") || {};
            return {
                preset: String(parsed.preset || "forest"),
                css_variables: normalizeVariables(parsed.css_variables || {})
            };
        } catch (error) {
            return { preset: "forest", css_variables: {} };
        }
    }

    function applyPayload(payload, replace) {
        payload = payload || {};
        setPreset(payload.preset || "forest");
        applyCssVariables(payload.css_variables || {}, replace !== false);
        document.dispatchEvent(new CustomEvent(runtime.event("theme-applied"), { detail: payload }));
    }

    async function loadCurrent(force) {
        if (!window.App || !App.getToken()) return null;
        var branchId = currentBranchId();
        clearOldCaches(branchId);
        if (!force) applyPayload(readCache(branchId), true);
        try {
            var result = await App.api("api/theme-settings.php");
            applyPayload(result.data, true);
            cacheTheme(result.data.branch_id, result.data);
            document.dispatchEvent(new CustomEvent(runtime.event("theme-loaded"), { detail: result.data }));
            return result.data;
        } catch (error) {
            if (window.App) App.showError(error, "Unable to load theme.");
            return null;
        }
    }

    function applyValues(values, fields) {
        applyCssVariables(valuesToCss(values, fields), false);
    }

    window.Theme = {
        applyCssVariables: applyCssVariables,
        clearCssVariables: clearCssVariables,
        applyValues: applyValues,
        setPreset: setPreset,
        currentPreset: currentPreset,
        computedValue: computedValue,
        computedHex: computedHex,
        applyPayload: applyPayload,
        loadCurrent: loadCurrent,
        cacheTheme: cacheTheme,
        currentBranchId: currentBranchId
    };

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { loadCurrent(false); }, { once: true });
    else loadCurrent(false);
})(window, document);
