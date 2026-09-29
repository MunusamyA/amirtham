(function (window, document) {
    "use strict";

    function text(id, value) {
        var node = document.getElementById(id);
        if (node) node.textContent = value == null || value === "" ? "-" : String(value);
    }

    function escapeHtml(value) {
        return window.App ? App.escapeHtml(value) : String(value || "");
    }

    function relativeTime(value) {
        if (!value) return "";
        var date = new Date(String(value).replace(" ", "T"));
        if (Number.isNaN(date.getTime())) return String(value);
        var seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) return "just now";
        if (seconds < 3600) return Math.floor(seconds / 60) + "m ago";
        if (seconds < 86400) return Math.floor(seconds / 3600) + "h ago";
        return Math.floor(seconds / 86400) + "d ago";
    }

    function renderActivity(items) {
        var root = document.getElementById("recentActivity");
        if (!root) return;
        if (!items || !items.length) {
            root.innerHTML = '<div class="empty">No audit activity yet.</div>';
            return;
        }
        root.innerHTML = items.map(function (item) {
            return '<div class="activity-item">' +
                '<span class="activity-icon primary"><i data-lucide="' + escapeHtml(item.icon || "activity") + '"></i></span>' +
                '<div class="activity-copy"><strong>' + escapeHtml(item.message || "Activity") + '</strong><span>' + escapeHtml(item.detail || "") + '</span></div>' +
                '<span class="activity-time">' + escapeHtml(relativeTime(item.created_at)) + '</span>' +
                '</div>';
        }).join("");
        if (window.lucide) window.lucide.createIcons();
    }

    async function load() {
        if (!window.App || !App.requireAuth()) return;
        try {
            var result = await App.api("api/dashboard.php");
            var summary = result.data.summary || {};
            var context = result.data.context || {};
            text("kpiBusinesses", summary.businesses || 0);
            text("kpiBranches", summary.branches || 0);
            text("kpiUsers", summary.users || 0);
            text("kpiRoles", summary.roles || 0);
            text("contextEmployees", summary.employees || 0);
            text("contextUser", context.name || "-");
            text("contextRole", context.role_name || "-");
            text("contextBusiness", context.company_name || (Number(context.role_type) === 2 ? "Platform" : "-"));
            text("contextBranch", context.branch_name || (Number(context.role_type) === 2 ? "Platform" : "-"));
            renderActivity(result.data.recent_activity || []);
        } catch (error) {
            App.showError(error, "Unable to load dashboard.");
            renderActivity([]);
        }
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", load, { once: true });
    else load();
})(window, document);
