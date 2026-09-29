(function (window, document) {
    "use strict";

    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; }, event: function (name) { return "amirtham:" + name; } };
    var KEY = runtime.key("appearance");

    function read() {
        try { return sessionStorage.getItem(KEY) === "dark" ? "dark" : "light"; }
        catch (error) { return "light"; }
    }

    function updateButton(mode) {
        var button = document.getElementById("appearanceToggle");
        if (!button) return;
        var nextLabel = mode === "dark" ? "Light mode" : "Dark mode";
        button.setAttribute("title", nextLabel);
        button.setAttribute("aria-label", nextLabel);
        button.setAttribute("aria-pressed", mode === "dark" ? "true" : "false");
        button.innerHTML = '<i data-lucide="' + (mode === "dark" ? "sun" : "moon") + '"></i>';
        if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
    }

    function apply(mode, persist) {
        mode = mode === "dark" ? "dark" : "light";
        document.documentElement.setAttribute("data-appearance", mode);
        document.documentElement.style.colorScheme = mode;
        if (persist !== false) {
            try { sessionStorage.setItem(KEY, mode); } catch (error) {}
        }
        updateButton(mode);
        try { window.dispatchEvent(new CustomEvent(runtime.event("appearance-change"), { detail: { mode: mode } })); } catch (error) {}
        return mode;
    }

    function toggle(event) {
        if (event && typeof event.preventDefault === "function") event.preventDefault();
        return apply(read() === "dark" ? "light" : "dark", true);
    }

    function bind() {
        apply(read(), false);
        var button = document.getElementById("appearanceToggle");
        if (!button || button.dataset.appearanceBound === "1") return;
        button.dataset.appearanceBound = "1";
        button.addEventListener("click", toggle);
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", bind, { once: true });
    else bind();

    window.Appearance = { get: read, apply: apply, toggle: toggle };
})(window, document);
