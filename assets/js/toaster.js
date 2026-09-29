(function (window, document) {
    "use strict";

    var TYPES = {
        success:   { icon: "✓" },
        danger:    { icon: "×" },
        error:     { icon: "×" },
        warning:   { icon: "!" },
        info:      { icon: "i" },
        primary:   { icon: "●" },
        secondary: { icon: "●" },
        dark:      { icon: "●" },
        light:     { icon: "●" },
        loading:   { icon: "↻" }
    };

    function container() {
        var element = document.getElementById("global-toaster");
        if (!element) {
            element = document.createElement("div");
            element.id = "global-toaster";
            element.className = "global-toaster";
            element.setAttribute("aria-live", "polite");
            document.body.appendChild(element);
        }
        return element;
    }

    function showToast(message, options) {
        options = options || {};

        var type = TYPES[options.type] ? options.type : "info";
        var config = TYPES[type];
        var duration = options.duration === undefined ? 3 : Number(options.duration);
        if (!isFinite(duration) || duration < 0) duration = 3;
        if (type === "loading" && options.duration === undefined) duration = 0;

        var toast = document.createElement("div");
        toast.className = "global-toast " + type;
        toast.setAttribute("role", type === "danger" || type === "error" ? "alert" : "status");

        var icon = document.createElement("div");
        icon.className = "global-toast-icon";
        icon.textContent = config.icon;

        var body = document.createElement("div");
        body.className = "global-toast-body";
        if (options.title) {
            var title = document.createElement("div");
            title.className = "global-toast-title";
            title.textContent = String(options.title);
            body.appendChild(title);
        }
        var messageElement = document.createElement("div");
        messageElement.className = "global-toast-message";
        messageElement.textContent = String(message);
        body.appendChild(messageElement);

        var closeButton = document.createElement("button");
        closeButton.type = "button";
        closeButton.className = "global-toast-close";
        closeButton.innerHTML = "&times;";
        closeButton.setAttribute("aria-label", "Close notification");

        toast.appendChild(icon);
        toast.appendChild(body);
        if (options.closeButton !== false) toast.appendChild(closeButton);

        var progress = null;
        var timer = null;
        var remaining = duration * 1000;
        var startedAt = 0;
        var closed = false;

        if (duration > 0) {
            progress = document.createElement("div");
            progress.className = "global-toast-progress";
            progress.style.animationDuration = duration + "s";
            toast.appendChild(progress);
        }

        function close() {
            if (closed) return;
            closed = true;
            if (timer) window.clearTimeout(timer);
            toast.classList.add("is-closing");
            window.setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 210);
        }

        function startTimer() {
            if (remaining <= 0 || closed) return;
            startedAt = Date.now();
            timer = window.setTimeout(close, remaining);
            if (progress) progress.style.animationPlayState = "running";
        }

        function pauseTimer() {
            if (!timer || closed) return;
            window.clearTimeout(timer);
            timer = null;
            remaining -= Date.now() - startedAt;
            if (progress) progress.style.animationPlayState = "paused";
        }

        closeButton.addEventListener("click", close);
        toast.addEventListener("mouseenter", pauseTimer);
        toast.addEventListener("mouseleave", startTimer);
        container().appendChild(toast);
        startTimer();

        return {
            element: toast,
            close: close,
            update: function (newMessage) {
                messageElement.textContent = String(newMessage);
            }
        };
    }

    window.showToast = showToast;
})(window, document);
