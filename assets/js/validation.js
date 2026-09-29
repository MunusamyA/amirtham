    (function (window, document) {
        "use strict";

        /*
        * Custom messages:
        * Validation.setMessages({ required: "Please complete this field." });
        * Validation.validateForm(form, { required: "Please complete this field." });
        * <input required data-required-message="Customer name is required.">
        */

        var PATTERNS = {
            mobile: /^[6-9][0-9]{9}$/,
            email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
            pan: /^[A-Z]{5}[0-9]{4}[A-Z]$/,
            aadhaar: /^[2-9][0-9]{11}$/,
            gst: /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/,
            pincode: /^[1-9][0-9]{5}$/,
            integer: /^-?[0-9]+$/,
            decimal: /^-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/,
            password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/
        };

        var MESSAGES = {
            required: "This field is required.",
            mobile: "Enter a valid 10-digit mobile number.",
            email: "Enter a valid email address.",
            pan: "Enter a valid PAN number.",
            aadhaar: "Enter a valid 12-digit Aadhaar number.",
            gst: "Enter a valid GST number.",
            pincode: "Enter a valid 6-digit pincode.",
            integer: "Enter a valid whole number.",
            decimal: "Enter a valid decimal number.",
            password: "Use 8+ characters with uppercase, lowercase, number and special character.",
            regex: "Enter a valid value."
        };

        function rulesFor(field) {
            var rules = (field.getAttribute("data-validation") || "")
                .split(/[|,\s]+/).filter(Boolean);
            if (field.required && rules.indexOf("required") === -1) rules.unshift("required");
            return rules;
        }

        function messageFor(field, rule, messages) {
            var fieldMessage = field.getAttribute("data-" + rule + "-message") ||
                field.getAttribute("data-validation-message");
            if (fieldMessage) return fieldMessage;

            var form = field.form;
            var formAttribute = form ? form.getAttribute("data-" + rule + "-message") : "";
            if (formAttribute) return formAttribute;

            var supplied = messages || (form && form._validationMessages) || {};
            return supplied[rule] || MESSAGES[rule] || "Enter a valid value.";
        }

        function formatPan(value) {
            var raw = String(value || "").replace(/[^a-zA-Z0-9]/g, "").toUpperCase();
            var formatted = "";
            for (var i = 0; i < raw.length && formatted.length < 10; i += 1) {
                var position = formatted.length;
                var alphabetPosition = position < 5 || position === 9;
                if ((alphabetPosition && /[A-Z]/.test(raw[i])) ||
                    (!alphabetPosition && /[0-9]/.test(raw[i]))) {
                    formatted += raw[i];
                }
            }
            return formatted;
        }

        function formatAadhaar(value) {
            var digits = String(value || "").replace(/\D/g, "").slice(0, 12);
            return digits.replace(/(\d{4})(?=\d)/g, "$1 ");
        }

        function gstCharacterAllowed(position, character) {
            character = String(character || "").toUpperCase();

            if (position === 0 || position === 1) {
                return /[0-9]/.test(character);
            }
            if (position >= 2 && position <= 6) {
                return /[A-Z]/.test(character);
            }
            if (position >= 7 && position <= 10) {
                return /[0-9]/.test(character);
            }
            if (position === 11) {
                return /[A-Z]/.test(character);
            }
            if (position === 12) {
                return /[1-9A-Z]/.test(character);
            }
            if (position === 13) {
                return character === "Z";
            }
            if (position === 14) {
                return /[0-9A-Z]/.test(character);
            }
            return false;
        }

        function formatGst(value) {
            var raw = String(value || "")
                .replace(/[^a-zA-Z0-9]/g, "")
                .toUpperCase();
            var formatted = "";

            for (var i = 0; i < raw.length && formatted.length < 15; i += 1) {
                if (gstCharacterAllowed(formatted.length, raw[i])) {
                    formatted += raw[i];
                }
            }

            return formatted.slice(0, 15);
        }

        function decimalPlaces(field) {
            var places = Number(field.getAttribute("data-decimal-places") || 2);
            if (!Number.isInteger(places) || places < 0) places = 2;
            return Math.min(places, 8);
        }

        function formatDecimal(value, places) {
            var text = String(value || "").replace(/[^0-9.-]/g, "");
            var negative = text.charAt(0) === "-";
            text = text.replace(/-/g, "");
            var dot = text.indexOf(".");
            var integerPart = (dot === -1 ? text : text.slice(0, dot)).replace(/\D/g, "");
            var fractionPart = dot === -1 ? "" : text.slice(dot + 1).replace(/\D/g, "").slice(0, places);
            return (negative ? "-" : "") + integerPart +
                (dot !== -1 && places > 0 ? "." + fractionPart : "");
        }

        function valueForRule(rule, value, field) {
            if (rule === "aadhaar") return value.replace(/\s/g, "");
            if (rule === "decimal") {
                var places = decimalPlaces(field);
                var decimalPattern = places === 0
                    ? /^-?[0-9]+$/
                    : new RegExp("^-?(?:[0-9]+(?:\\.[0-9]{1," + places + "})?|\\.[0-9]{1," + places + "})$");
                return decimalPattern.test(value) ? value : "__INVALID_DECIMAL__";
            }
            return value;
        }

        function displayControl(field) {
            if (field._globalSelect && field._globalSelect.input) {
                return field._globalSelect.input;
            }
            return field;
        }

        function errorAnchor(field) {
            if (field._globalSelect && field._globalSelect.wrapper) {
                return field._globalSelect.wrapper;
            }
            if (field.parentElement && field.parentElement.classList.contains("password-wrap")) {
                return field.parentElement;
            }
            return field;
        }

        function errorElement(field) {
            var id = field.getAttribute("data-error-id");
            if (id) return document.getElementById(id);
            var anchor = errorAnchor(field);
            var next = anchor.nextElementSibling;
            if (next && next.classList.contains("validation-error")) return next;

            var error = document.createElement("small");
            error.className = "validation-error";
            anchor.insertAdjacentElement("afterend", error);
            return error;
        }

        function showError(field, message) {
            field.setAttribute("aria-invalid", "true");
            var control = displayControl(field);
            control.setAttribute("aria-invalid", "true");
            if (field._globalSelect) field._globalSelect.wrapper.classList.add("is-invalid");
            var error = errorElement(field);
            error.textContent = message;
            error.classList.add("is-visible");
        }

        function clearError(field) {
            field.removeAttribute("aria-invalid");
            var control = displayControl(field);
            control.removeAttribute("aria-invalid");
            if (field._globalSelect) field._globalSelect.wrapper.classList.remove("is-invalid");
            var error = errorElement(field);
            error.textContent = "";
            error.classList.remove("is-visible");
        }

        function validateField(field, silent, messages) {
            if (field.disabled) return true;
            var value = String(field.value || "").trim();
            var rules = rulesFor(field);

            if (rules.indexOf("required") !== -1 && value === "") {
                if (!silent) showError(field, messageFor(field, "required", messages));
                return false;
            }
            if (value === "") {
                clearError(field);
                return true;
            }

            for (var i = 0; i < rules.length; i += 1) {
                var rule = rules[i];
                if (PATTERNS[rule] && !PATTERNS[rule].test(valueForRule(rule, value, field))) {
                    if (!silent) showError(field, messageFor(field, rule, messages));
                    return false;
                }
            }

            var customRegex = field.getAttribute("data-regex");
            if (customRegex) {
                try {
                    var flags = field.getAttribute("data-regex-flags") || "";
                    if (!(new RegExp(customRegex, flags)).test(value)) {
                        if (!silent) showError(field, messageFor(field, "regex", messages));
                        return false;
                    }
                } catch (error) {
                    if (!silent) showError(field, "Invalid validation pattern configuration.");
                    return false;
                }
            }
            clearError(field);
            return true;
        }

        function isControlKey(event) {
            return event.ctrlKey || event.metaKey || event.altKey ||
                ["Backspace", "Delete", "Tab", "Escape", "Enter", "ArrowLeft",
                "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End"].indexOf(event.key) !== -1;
        }

        function restrictKeydown(event) {
            if (isControlKey(event) || event.key.length !== 1) return;
            var rules = rulesFor(event.target);
            var key = event.key;
            var value = event.target.value;

            if (rules.some(function (rule) {
                return ["mobile", "aadhaar", "pincode"].indexOf(rule) !== -1;
            }) && !/[0-9]/.test(key)) {
                event.preventDefault();
            }
            var digitPosition = value.slice(0, event.target.selectionStart || 0).replace(/\D/g, "").length;
            if ((rules.indexOf("mobile") !== -1 && digitPosition === 0 && !/[6-9]/.test(key)) ||
                (rules.indexOf("aadhaar") !== -1 && digitPosition === 0 && !/[2-9]/.test(key)) ||
                (rules.indexOf("pincode") !== -1 && digitPosition === 0 && !/[1-9]/.test(key))) {
                event.preventDefault();
            }
            if (rules.indexOf("integer") !== -1 &&
                !(/[0-9]/.test(key) || (key === "-" && event.target.selectionStart === 0 &&
                    value.indexOf("-") === -1))) {
                event.preventDefault();
            }
            if (rules.indexOf("decimal") !== -1) {
                if (!/[0-9.-]/.test(key) || (key === "." && value.indexOf(".") !== -1) ||
                    (key === "-" && event.target.selectionStart !== 0)) {
                    event.preventDefault();
                }
            }
            if (rules.indexOf("pan") !== -1) {
                var beforeCursor = value.slice(0, event.target.selectionStart || 0)
                    .replace(/[^a-zA-Z0-9]/g, "");
                var panPosition = beforeCursor.length;
                var needsAlphabet = panPosition < 5 || panPosition === 9;
                if ((needsAlphabet && !/[a-zA-Z]/.test(key)) ||
                    (!needsAlphabet && !/[0-9]/.test(key)) || panPosition >= 10) {
                    event.preventDefault();
                }
            }
            if (rules.indexOf("gst") !== -1) {
                var selectionStart = event.target.selectionStart || 0;
                var selectionEnd = event.target.selectionEnd || 0;
                var hasSelection = selectionStart !== selectionEnd;
                var beforeCursor = value.slice(0, selectionStart)
                    .replace(/[^a-zA-Z0-9]/g, "");
                var gstPosition = beforeCursor.length;
                var upperKey = key.toUpperCase();

                if (!gstCharacterAllowed(gstPosition, upperKey)) {
                    event.preventDefault();
                    return;
                }

                var gstValue = value.replace(/[^a-zA-Z0-9]/g, "");
                if (gstValue.length >= 15 && !hasSelection) {
                    event.preventDefault();
                }
            }
        }

        function sanitizeField(field) {
            var rules = rulesFor(field);
            var value = field.value;

            if (rules.indexOf("email") !== -1) value = value.replace(/\s/g, "");
            if (rules.indexOf("mobile") !== -1) value = value.replace(/\D/g, "").slice(0, 10);
            if (rules.indexOf("aadhaar") !== -1) value = formatAadhaar(value);
            if (rules.indexOf("pincode") !== -1) value = value.replace(/\D/g, "").slice(0, 6);
            if (rules.indexOf("pan") !== -1) value = formatPan(value);
            if (rules.indexOf("gst") !== -1) value = formatGst(value);
            if (rules.indexOf("integer") !== -1) value = value.replace(/(?!^-)[^0-9]/g, "");
            if (rules.indexOf("decimal") !== -1) value = formatDecimal(value, decimalPlaces(field));
            field.value = value;
            if (value !== "" && rules.indexOf("password") !== -1) validateField(field, false);
            else if (value !== "") validateField(field, true);
            else clearError(field);
            return value;
        }

        function sanitizeInput(event) {
            sanitizeField(event.target);
        }

        function validateForm(form, messages) {
            if (messages && typeof messages === "object") {
                form._validationMessages = messages;
            }
            var valid = true;
            var firstInvalid = null;
            var fields = form.querySelectorAll("[data-validation], [data-regex], [required]");
            Array.prototype.forEach.call(fields, function (field) {
                if (!validateField(field, false, messages)) {
                    valid = false;
                    if (!firstInvalid) firstInvalid = field;
                }
            });
            if (firstInvalid) {
                if (firstInvalid._globalSelect) firstInvalid._globalSelect.focus();
                else firstInvalid.focus();
            }
            return valid;
        }

        function fieldByName(form, name) {
            var field = form.elements.namedItem(name);
            if (field && field.length && !field.tagName) field = field[0];
            if (field) return field;
            return form.querySelector('[name="' + String(name).replace(/"/g, '\\"') + '[]"]');
        }

        function applyErrors(form, errors) {
            var firstInvalid = null;
            Object.keys(errors || {}).forEach(function (name) {
                var field = fieldByName(form, name);
                if (!field) return;
                showError(field, String(errors[name] || "Enter a valid value."));
                if (!firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                if (firstInvalid._globalSelect) firstInvalid._globalSelect.focus();
                else firstInvalid.focus();
            }
            return firstInvalid !== null;
        }

        function clearForm(form) {
            var fields = form.querySelectorAll("[data-validation], [data-regex], [required], [aria-invalid='true']");
            Array.prototype.forEach.call(fields, clearError);
        }

        function setMessages(messages) {
            if (!messages || typeof messages !== "object") return;
            Object.keys(messages).forEach(function (rule) {
                if (typeof messages[rule] === "string" && messages[rule].trim() !== "") {
                    MESSAGES[rule] = messages[rule];
                }
            });
        }

        function addPattern(name, pattern, message) {
            name = String(name || "").trim();
            if (!name) return false;
            if (!(pattern instanceof RegExp)) {
                try { pattern = new RegExp(String(pattern)); } catch (error) { return false; }
            }
            PATTERNS[name] = pattern;
            if (typeof message === "string" && message.trim() !== "") MESSAGES[name] = message;
            return true;
        }

        function init(root, messages) {
            root = root || document;
            if (messages) setMessages(messages);
            var fields = root.querySelectorAll("[data-validation], [data-regex], [required]");
            Array.prototype.forEach.call(fields, function (field) {
                if (field.getAttribute("data-validation-bound") === "1") return;
                field.setAttribute("data-validation-bound", "1");
                field.addEventListener("keydown", restrictKeydown);
                field.addEventListener("input", sanitizeInput);
                field.addEventListener("blur", function () { validateField(field, false); });
            });

            var forms = root.querySelectorAll("form");
            Array.prototype.forEach.call(forms, function (form) {
                if (messages) form._validationMessages = messages;
                if (form.getAttribute("data-validation-form-bound") === "1") return;
                form.setAttribute("data-validation-form-bound", "1");
                form.addEventListener("submit", function (event) {
                    if (!validateForm(form)) event.preventDefault();
                });
            });
        }

        window.Validation = {
            init: init,
            validateField: validateField,
            validateForm: validateForm,
            formatField: sanitizeField,
            applyErrors: applyErrors,
            clearForm: clearForm,
            setMessages: setMessages,
            addPattern: addPattern,
            patterns: PATTERNS,
            messages: MESSAGES
        };

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function () { init(document); });
        } else {
            init(document);
        }
    })(window, document);
