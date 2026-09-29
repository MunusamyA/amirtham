(function (window) { 
    "use strict"; 

    var runtime = window.AppRuntime || { key: function (name) { return "amirtham_" + name; } }; 
    var TOKEN_KEY = runtime.key("auth_token"); 
    var USER_KEY = runtime.key("auth_user"); 
    var LOGIN_PAGE = "login.php"; 
    var LAST_ACTIVITY_KEY = runtime.key("last_user_activity"); 
    var LAST_ACTIVITY_PING_KEY = runtime.key("last_activity_ping"); 

    function getToken() { 
        return localStorage.getItem(TOKEN_KEY) || ""; 
    } 

    function getUser() { 
        try { 
            return JSON.parse(localStorage.getItem(USER_KEY) || "{}") || {}; 
        } catch (error) { 
            return {}; 
        } 
    } 

    function saveLogin(token, user) { 
        localStorage.setItem(TOKEN_KEY, token); 
        localStorage.setItem(USER_KEY, JSON.stringify(user || {})); 
        var now = String(Date.now()); 
        localStorage.setItem(LAST_ACTIVITY_KEY, now); 
        localStorage.setItem(LAST_ACTIVITY_PING_KEY, now); 
    } 

    function updateToken(token, userUpdates) { 
        if (token) { 
            localStorage.setItem(TOKEN_KEY, String(token)); 
        } 
        if (userUpdates && typeof userUpdates === "object") { 
            var user = getUser(); 
            Object.keys(userUpdates).forEach(function (key) { 
                user[key] = userUpdates[key]; 
            }); 
            localStorage.setItem(USER_KEY, JSON.stringify(user)); 
        } 
    } 

    function clearLogin(message) { 
        localStorage.removeItem(TOKEN_KEY); 
        localStorage.removeItem(USER_KEY); 
        localStorage.removeItem(LAST_ACTIVITY_KEY); 
        localStorage.removeItem(LAST_ACTIVITY_PING_KEY); 
        try { 
            Object.keys(sessionStorage).forEach(function (key) { 
                if (key.indexOf(runtime.key("sidebar_menu_")) === 0) sessionStorage.removeItem(key); 
            }); 
        } catch (error) {} 
        if (message) { 
            sessionStorage.setItem(runtime.key("logout_message"), message); 
        } 
    } 

    function goToLogin(message) { 
        clearLogin(message); 
        window.location.replace(LOGIN_PAGE); 
    } 

    function requireAuth() { 
        var token = getToken(); 
        if (!token) { 
            goToLogin("Please login to continue."); 
            return false; 
        } 
        return true; 
    } 

    function apiError(message, silent, status, errors) { 
        var error = new Error(message); 
        error.silent = Boolean(silent); 
        error.status = Number(status || 0); 
        error.errors = errors || {}; 
        return error; 
    } 

    async function api(url, options) { 
        options = options || {}; 
        var needsAuth = options.auth !== false; 
        var responseType = String(options.responseType || "json").toLowerCase();
        var requestOptions = { 
            method: options.method || "GET", 
            headers: Object.assign({}, options.headers || {}) 
        }; 

        if (needsAuth) { 
            var token = getToken(); 
            if (!token) { 
                goToLogin("Your login has expired. Please login again."); 
                throw apiError("Authentication required.", true, 401); 
            } 
            requestOptions.headers.Authorization = "Bearer " + token; 
        } 

        if (options.body !== undefined && options.body !== null) { 
            if (options.body instanceof FormData) { 
                requestOptions.body = options.body; 
            } else if (typeof options.body === "string") { 
                requestOptions.body = options.body; 
                if (!requestOptions.headers["Content-Type"]) { 
                    requestOptions.headers["Content-Type"] = "application/json"; 
                } 
            } else { 
                requestOptions.body = JSON.stringify(options.body); 
                requestOptions.headers["Content-Type"] = "application/json"; 
            } 
        } 

        var response; 
        try { 
            response = await fetch(url, requestOptions); 
        } catch (error) { 
            throw apiError("Unable to connect to the server.", false, 0); 
        } 

        if (responseType === "blob") {
            if (response.status === 401 && needsAuth) {
                var authMessage = "Your login has expired. Please login again.";

                try {
                    var authText = await response.text();
                    var authResult = authText ? JSON.parse(authText) : {};
                    if (authResult && authResult.message) {
                        authMessage = authResult.message;
                    }
                } catch (error) {}

                goToLogin(authMessage);
                throw apiError(authMessage, true, 401);
            }

            if (!response.ok) {
                var blobErrorMessage = "Request failed.";

                try {
                    var blobErrorText = await response.text();

                    try {
                        var blobErrorResult = blobErrorText ? JSON.parse(blobErrorText) : {};
                        blobErrorMessage = blobErrorResult.message || blobErrorMessage;
                    } catch (error) {
                        if (blobErrorText) {
                            blobErrorMessage = blobErrorText;
                        }
                    }
                } catch (error) {}

                throw apiError(blobErrorMessage, false, response.status);
            }

            return await response.blob();
        }

        var text = await response.text(); 
        var result; 
        try { 
            result = text ? JSON.parse(text) : {}; 
        } catch (error) { 
            throw apiError( 
                "Server returned invalid JSON. Check the PHP error log.", 
                false, 
                response.status 
            ); 
        } 

        if (response.status === 401 && needsAuth) { 
            goToLogin(result.message || "Your login has expired. Please login again."); 
            throw apiError(result.message || "Session expired.", true, 401, result.errors); 
        } 

        if (!response.ok || result.success !== true) { 
            throw apiError(result.message || "Request failed.", false, response.status, result.errors); 
        } 

        return result; 
    }

    async function openPdf(url) {
        var pdfWindow = window.open("", "_blank");

        if (!pdfWindow) {
            if (typeof window.showToast === "function") {
                window.showToast(
                    "Please allow popups to open the receipt.",
                    { type: "warning", duration: 4 }
                );
            }
            return false;
        }

        try {
            var blob = await api(url, {
                responseType: "blob",
                headers: {
                    "Accept": "application/pdf"
                }
            });

            var pdfBlob = new Blob([blob], {
                type: "application/pdf"
            });

            var pdfUrl = URL.createObjectURL(pdfBlob);

            pdfWindow.location.replace(pdfUrl);

            window.setTimeout(function () {
                URL.revokeObjectURL(pdfUrl);
            }, 120000);

            return true;
        } catch (error) {
            try {
                pdfWindow.close();
            } catch (closeError) {}

            showError(error, "Unable to open receipt.");
            return false;
        }
    }

    async function logout() { 
        var message = "Logout successful."; 
        try { 
            var result = await api("api/logout.php", { method: "POST" }); 
            message = result.message || message; 
        } catch (error) { 
            if (error.status !== 401) { 
                message = "Logged out locally. Server logout could not be confirmed."; 
            } 
        } 
        goToLogin(message); 
    } 

    function showError(error, fallback) { 
        if (error && error.silent) { 
            return; 
        } 
        var message = error && error.message ? error.message : (fallback || "Request failed."); 
        if (typeof window.showToast === "function") { 
            window.showToast(message, { type: "danger", duration: 4 }); 
        } 
    } 

    function initPasswordToggles() { 
        var eyeOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>'; 
        var eyeClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 3 18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a18 18 0 0 1-2.1 3.2M6.6 6.6C3.6 8.5 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 4.1-.9"/></svg>'; 
        Array.prototype.forEach.call(document.querySelectorAll("[data-password-toggle]"), function (toggle) { 
            if (toggle.dataset.ready === "1") return; 
            var input = document.getElementById(toggle.getAttribute("data-password-toggle")); 
            if (!input) return; 
            toggle.dataset.ready = "1"; 
            toggle.innerHTML = eyeOpen; 
            toggle.addEventListener("click", function () { 
                var show = input.type === "password"; 
                input.type = show ? "text" : "password"; 
                toggle.innerHTML = show ? eyeClosed : eyeOpen; 
                toggle.setAttribute("aria-label", show ? "Hide password" : "Show password"); 
            }); 
        }); 
    } 


    function escapeHtml(value) { 
        return String(value == null ? "" : value) 
            .replace(/&/g, "&amp;") 
            .replace(/</g, "&lt;") 
            .replace(/>/g, "&gt;") 
            .replace(/"/g, "&quot;") 
            .replace(/'/g, "&#039;"); 
    } 

    function iconActionHtml(options) { 
        options = options || {}; 
        var label = String(options.label || "Action"); 
        var icon = String(options.icon || "circle"); 
        var tone = String(options.tone || "default"); 
        var cls = "table-icon-action" + (tone !== "default" ? " " + tone : ""); 
        if (options.href) { 
            return '<a class="' + cls + '" href="' + escapeHtml(options.href) + '" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '"><i data-lucide="' + escapeHtml(icon) + '"></i></a>'; 
        } 
        return '<button class="' + cls + '" type="button" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '"' + (options.dataId != null ? ' data-id="' + escapeHtml(options.dataId) + '"' : '') + '><i data-lucide="' + escapeHtml(icon) + '"></i></button>'; 
    } 

    function createIconAction(options) { 
        options = options || {}; 
        var node = options.href ? document.createElement("a") : document.createElement("button"); 
        if (!options.href) node.type = "button"; 
        if (options.href) node.href = options.href; 
        node.className = "table-icon-action" + (options.tone && options.tone !== "default" ? " " + options.tone : ""); 
        var label = String(options.label || "Action"); 
        node.title = label; 
        node.setAttribute("aria-label", label); 
        node.innerHTML = '<i data-lucide="' + escapeHtml(options.icon || "circle") + '"></i>'; 
        if (typeof options.onClick === "function") node.addEventListener("click", options.onClick); 
        return node; 
    } 

    function clearSidebarCache() { 
        try { 
            Object.keys(sessionStorage).forEach(function (key) { 
                if (key.indexOf(runtime.key("sidebar_menu_")) === 0) sessionStorage.removeItem(key); 
            }); 
        } catch (error) {} 
    } 

    window.App = { 
        api: api,
        openPdf: openPdf,
        getToken: getToken, 
        getUser: getUser, 
        saveLogin: saveLogin, 
        updateToken: updateToken, 
        clearLogin: clearLogin, 
        goToLogin: goToLogin, 
        requireAuth: requireAuth, 
        logout: logout, 
        showError: showError, 
        escapeHtml: escapeHtml, 
        iconActionHtml: iconActionHtml, 
        createIconAction: createIconAction, 
        clearSidebarCache: clearSidebarCache, 
        initPasswordToggles: initPasswordToggles 
    }; 

    if (document.readyState === "loading") { 
        document.addEventListener("DOMContentLoaded", initPasswordToggles); 
    } else { 
        initPasswordToggles(); 
    } 
})(window);
