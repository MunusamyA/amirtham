(function (window, document) {
    "use strict";

    var INSTANCE_COUNTER = 0;

    function GlobalSelect(select, options) {
        if (!select || select.tagName !== "SELECT") {
            throw new Error("GlobalSelect requires a select element.");
        }
        if (select._globalSelect) return select._globalSelect;

        this.select = select;
        this.options = Object.assign({
            placeholder: select.getAttribute("data-placeholder") || "Select an option",
            noResults: "No matching options"
        }, options || {});
        this.filtered = [];
        this.focusedIndex = -1;
        this.isOpen = false;
        this.id = "global-select-" + (++INSTANCE_COUNTER);

        this.build();
        this.bind();
        this.refresh();
        select._globalSelect = this;
    }

    GlobalSelect.prototype.build = function () {
        var wrapper = document.createElement("div");
        wrapper.className = "global-select";
        wrapper.dataset.globalSelectId = this.id;

        this.select.parentNode.insertBefore(wrapper, this.select);
        wrapper.appendChild(this.select);
        this.select.classList.add("global-select-native");

        var control = document.createElement("div");
        control.className = "global-select-control";

        this.input = document.createElement("input");
        this.input.type = "text";
        this.input.className = "global-select-input";
        this.input.placeholder = this.options.placeholder;
        this.input.autocomplete = "off";
        this.input.spellcheck = false;
        this.input.setAttribute("role", "combobox");
        this.input.setAttribute("aria-autocomplete", "list");
        this.input.setAttribute("aria-expanded", "false");
        this.input.setAttribute("aria-controls", this.id + "-listbox");

        control.appendChild(this.input);
        wrapper.appendChild(control);

        this.panel = document.createElement("div");
        this.panel.className = "global-select-panel";
        this.panel.dataset.globalSelectOwner = this.id;
        this.panel.hidden = true;

        this.list = document.createElement("div");
        this.list.id = this.id + "-listbox";
        this.list.className = "global-select-options";
        this.list.setAttribute("role", "listbox");
        this.panel.appendChild(this.list);

        // Render outside form/card containers so overflow rules can never clip it.
        document.body.appendChild(this.panel);

        this.wrapper = wrapper;
        this.control = control;
    };

    GlobalSelect.prototype.bind = function () {
        var self = this;

        this.input.addEventListener("focus", function () {
            if (self.select.disabled) return;
            self.open("");
        });

        this.input.addEventListener("click", function () {
            if (self.select.disabled) return;
            if (self.isOpen) {
                self.positionPanel();
                return;
            }
            self.open("");
        });

        this.input.addEventListener("input", function () {
            var query = self.input.value;
            var selected = self.selectedOption();
            if (!selected || query !== selected.textContent) {
                self.setNativeValue("", true);
            }
            self.input.value = query;
            self.focusedIndex = -1;
            self.open(query);
        });

        this.input.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown") {
                event.preventDefault();
                if (!self.isOpen) self.open("");
                self.move(1);
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                if (!self.isOpen) self.open("");
                self.move(-1);
            } else if (event.key === "Enter") {
                if (self.isOpen) {
                    event.preventDefault();
                    self.chooseFocused();
                }
            } else if (event.key === "Escape") {
                event.preventDefault();
                self.close(true);
            } else if (event.key === "Tab") {
                self.close(true);
            }
        });

        this.select.addEventListener("change", function () {
            self.updateInput();
            self.wrapper.classList.remove("is-invalid");
        });

        document.addEventListener("pointerdown", function (event) {
            if (!self.isOpen) return;
            if (self.wrapper.contains(event.target) || self.panel.contains(event.target)) return;
            self.close(true);
        });

        window.addEventListener("resize", function () {
            if (self.isOpen) self.positionPanel();
        });

        document.addEventListener("scroll", function () {
            if (self.isOpen) self.positionPanel();
        }, true);
    };

    GlobalSelect.prototype.optionData = function () {
        return Array.prototype.map.call(this.select.options, function (option) {
            return {
                value: option.value,
                text: option.textContent,
                disabled: option.disabled
            };
        }).filter(function (option) {
            return option.value !== "";
        });
    };

    GlobalSelect.prototype.selectedOption = function () {
        var selected = this.select.options[this.select.selectedIndex];
        return selected && selected.value !== "" ? selected : null;
    };

    GlobalSelect.prototype.render = function (query) {
        var self = this;
        query = String(query || "").trim().toLowerCase();
        this.filtered = this.optionData().filter(function (option) {
            return option.text.toLowerCase().indexOf(query) !== -1;
        });
        this.list.innerHTML = "";

        if (!this.filtered.length) {
            var empty = document.createElement("div");
            empty.className = "global-select-empty";
            empty.textContent = this.options.noResults;
            this.list.appendChild(empty);
            return;
        }

        this.filtered.forEach(function (option, index) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "global-select-option";
            button.textContent = option.text;
            button.disabled = option.disabled;
            button.setAttribute("role", "option");
            button.dataset.optionIndex = String(index);

            if (String(option.value) === String(self.select.value)) {
                button.classList.add("is-selected");
                button.setAttribute("aria-selected", "true");
            }

            button.addEventListener("pointerdown", function (event) {
                event.preventDefault();
                if (!option.disabled) self.selectValue(option.value);
            });

            self.list.appendChild(button);
            option.button = button;
        });

        this.updateFocus();
    };

    GlobalSelect.prototype.positionPanel = function () {
        if (!this.isOpen) return;

        var rect = this.wrapper.getBoundingClientRect();
        var gap = 5;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        this.panel.style.left = Math.round(rect.left) + "px";
        this.panel.style.width = Math.round(rect.width) + "px";
        this.panel.style.maxWidth = Math.max(0, Math.round(window.innerWidth - rect.left - 8)) + "px";

        // First place it below so it can be measured.
        this.panel.style.top = Math.round(rect.bottom + gap) + "px";
        this.panel.style.bottom = "auto";

        var panelRect = this.panel.getBoundingClientRect();
        var roomBelow = viewportHeight - rect.bottom - 10;
        var roomAbove = rect.top - 10;

        if (panelRect.height > roomBelow && roomAbove > roomBelow) {
            this.panel.style.top = "auto";
            this.panel.style.bottom = Math.round(viewportHeight - rect.top + gap) + "px";
        }
    };

    GlobalSelect.prototype.open = function (query) {
        if (this.select.disabled) return;

        this.isOpen = true;
        this.panel.hidden = false;
        this.wrapper.classList.add("is-open");
        this.input.setAttribute("aria-expanded", "true");
        this.focusedIndex = -1;
        this.render(query === undefined ? this.input.value : query);
        this.positionPanel();
    };

    GlobalSelect.prototype.close = function (restoreLabel) {
        this.isOpen = false;
        this.panel.hidden = true;
        this.wrapper.classList.remove("is-open");
        this.input.setAttribute("aria-expanded", "false");
        if (restoreLabel) this.updateInput();
    };

    GlobalSelect.prototype.move = function (direction) {
        if (!this.filtered.length) return;

        var attempts = 0;
        do {
            this.focusedIndex += direction;
            if (this.focusedIndex < 0) this.focusedIndex = this.filtered.length - 1;
            if (this.focusedIndex >= this.filtered.length) this.focusedIndex = 0;
            attempts += 1;
        } while (this.filtered[this.focusedIndex] && this.filtered[this.focusedIndex].disabled && attempts <= this.filtered.length);

        this.updateFocus();
    };

    GlobalSelect.prototype.updateFocus = function () {
        this.filtered.forEach(function (option, index) {
            if (!option.button) return;
            option.button.classList.toggle("is-focused", index === this.focusedIndex);
        }, this);

        var focused = this.filtered[this.focusedIndex];
        if (focused && focused.button) {
            focused.button.scrollIntoView({ block: "nearest" });
        }
    };

    GlobalSelect.prototype.chooseFocused = function () {
        var option = this.filtered[this.focusedIndex];
        if (!option && this.filtered.length === 1) option = this.filtered[0];
        if (option && !option.disabled) this.selectValue(option.value);
    };

    GlobalSelect.prototype.setNativeValue = function (value, dispatch) {
        var changed = String(this.select.value) !== String(value);
        this.select.value = String(value);
        if (dispatch && changed) {
            this.select.dispatchEvent(new Event("change", { bubbles: true }));
        }
    };

    GlobalSelect.prototype.selectValue = function (value) {
        this.setNativeValue(value, true);
        this.updateInput();
        this.close(false);
        this.input.focus();
    };

    GlobalSelect.prototype.updateInput = function () {
        var selected = this.selectedOption();
        this.input.value = selected ? selected.textContent : "";
        this.input.disabled = this.select.disabled;
        this.wrapper.classList.toggle("is-disabled", this.select.disabled);
    };

    GlobalSelect.prototype.refresh = function () {
        this.updateInput();
        if (this.isOpen) {
            this.render("");
            this.positionPanel();
        }
        return this;
    };

    GlobalSelect.prototype.setOptions = function (items, selectedValue) {
        var placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = this.options.placeholder;

        this.select.innerHTML = "";
        this.select.appendChild(placeholder);

        (items || []).forEach(function (item) {
            var option = document.createElement("option");
            option.value = item.value;
            option.textContent = item.text;
            option.disabled = Boolean(item.disabled);
            this.select.appendChild(option);
        }, this);

        this.setNativeValue(
            selectedValue === undefined || selectedValue === null ? "" : selectedValue,
            false
        );

        return this.refresh();
    };

    GlobalSelect.prototype.focus = function () {
        this.input.focus();
    };

    function init(selector, options) {
        var select = typeof selector === "string" ? document.querySelector(selector) : selector;
        return new GlobalSelect(select, options);
    }

    window.GlobalSelect = { init: init };

    document.addEventListener("DOMContentLoaded", function () {
        Array.prototype.forEach.call(
            document.querySelectorAll("select[data-global-select]"),
            function (select) {
                init(select);
            }
        );
    });
})(window, document);
