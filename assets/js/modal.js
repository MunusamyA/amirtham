(function (window, document) {
    "use strict";

    var host = null;
    var dialog = null;
    var active = null;

    function ensureHost() {
        host = host || document.getElementById("appModalHost");
        dialog = dialog || document.getElementById("appModalDialog");
        if (host && document.body && host.parentNode !== document.body) document.body.appendChild(host);
        return Boolean(host && dialog);
    }

    function firstFocusable(root) {
        if (!root) return null;
        return root.querySelector(
            "[autofocus], input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href], [tabindex]:not([tabindex='-1'])"
        );
    }

    function restoreContent(state) {
        if (!state || !state.content || !state.parent) return;
        if (state.nextSibling && state.nextSibling.parentNode === state.parent) {
            state.parent.insertBefore(state.content, state.nextSibling);
        } else {
            state.parent.appendChild(state.content);
        }
        if (state.wasHiddenClass) state.content.classList.add("hidden");
        if (state.wasHiddenAttribute) state.content.setAttribute("hidden", "");
    }

    function close(reason) {
        if (!ensureHost() || !active) return false;
        var state = active;
        active = null;

        host.classList.remove("open");
        host.hidden = true;
        host.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
        dialog.removeAttribute("data-size");

        restoreContent(state);

        if (state.restoreFocus && typeof state.restoreFocus.focus === "function") {
            try { state.restoreFocus.focus({ preventScroll: true }); } catch (error) { state.restoreFocus.focus(); }
        }
        if (typeof state.onClose === "function") {
            state.onClose(reason || "close");
        }
        window.dispatchEvent(new CustomEvent("app:modal-closed", { detail: { reason: reason || "close" } }));
        return true;
    }

    function open(content, options) {
        options = options || {};
        if (!ensureHost()) return false;
        if (typeof content === "string") content = document.querySelector(content);
        if (!content || content.nodeType !== 1) return false;

        if (active) close("replace");

        var parent = content.parentNode;
        if (!parent) return false;

        active = {
            content: content,
            parent: parent,
            nextSibling: content.nextSibling,
            wasHiddenClass: content.classList.contains("hidden"),
            wasHiddenAttribute: content.hasAttribute("hidden"),
            restoreFocus: options.restoreFocus === false ? null : (document.activeElement || null),
            onClose: typeof options.onClose === "function" ? options.onClose : null,
            closeOnBackdrop: options.closeOnBackdrop !== false,
            closeOnEscape: options.closeOnEscape !== false
        };

        content.classList.remove("hidden");
        content.removeAttribute("hidden");
        dialog.innerHTML = "";
        dialog.dataset.size = String(options.size || "md");
        dialog.appendChild(content);

        host.hidden = false;
        host.setAttribute("aria-hidden", "false");
        host.classList.add("open");
        document.body.classList.add("modal-open");

        window.requestAnimationFrame(function () {
            var focusTarget = options.focusSelector ? content.querySelector(options.focusSelector) : firstFocusable(content);
            if (focusTarget && typeof focusTarget.focus === "function") focusTarget.focus();
        });

        window.dispatchEvent(new CustomEvent("app:modal-opened", { detail: { size: dialog.dataset.size } }));
        return true;
    }

    function isOpen() {
        return Boolean(active && host && !host.hidden);
    }

    document.addEventListener("click", function (event) {
        if (!active) return;
        var closeButton = event.target.closest("[data-modal-close]");
        if (closeButton) {
            event.preventDefault();
            close("button");
            return;
        }
        if (event.target === host && active.closeOnBackdrop) close("backdrop");
    });

    document.addEventListener("keydown", function (event) {
        if (!active || event.key !== "Escape" || !active.closeOnEscape) return;
        event.preventDefault();
        close("escape");
    });

    window.AppModal = {
        open: open,
        close: close,
        isOpen: isOpen
    };

    ensureHost();
})(window, document);
