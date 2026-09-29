(function (window, $, document) {
    "use strict";

    var ACTION = Object.freeze({
        COPY: 5,
        CSV: 6,
        EXCEL: 7,
        PDF: 8,
        PRINT: 9
    });

    function has(actions, actionId) {
        return (actions || []).map(Number).indexOf(Number(actionId)) !== -1;
    }

    function ensureAvailable() {
        if (!$ || !$.fn || !$.fn.DataTable) {
            if (typeof window.showToast === "function") {
                window.showToast("DataTables library could not be loaded.", { type: "danger", duration: 5 });
            }
            return false;
        }
        return true;
    }

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(context, args);
            }, wait || 300);
        };
    }

    function createElement(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function buildShell(tableElement, options) {
        var existing = tableElement.closest(".app-datatable");
        if (existing && existing._appControls) return existing._appControls;

        var shell = createElement("div", "app-datatable");
        tableElement.parentNode.insertBefore(shell, tableElement);

        var searchRow = createElement("div", "app-table-search-row");
        var searchLabel = createElement("label", "app-table-search-label");
        var searchCaption = createElement("span", "app-table-search-caption", options.appSearchLabel || "Search");
        var searchInput = createElement("input", "app-table-search-input");
        searchInput.type = "search";
        searchInput.autocomplete = "off";
        searchInput.placeholder = options.appSearchPlaceholder || "Search...";
        searchInput.setAttribute("aria-label", options.appSearchLabel || "Search");
        searchLabel.appendChild(searchCaption);
        searchLabel.appendChild(searchInput);
        searchRow.appendChild(searchLabel);

        var toolbar = createElement("div", "app-table-toolbar");
        var toolbarLeft = createElement("div", "app-table-toolbar-left");
        var lengthWrap = createElement("label", "app-table-length");
        lengthWrap.appendChild(createElement("span", "app-table-length-prefix", "Show"));
        var lengthSelect = createElement("select", "app-table-length-select");
        var lengths = options.appPageLengths || [10, 25, 50, 100];
        lengths.forEach(function (value) {
            var option = document.createElement("option");
            option.value = String(value);
            option.textContent = String(value);
            if (Number(value) === Number(options.pageLength || 10)) option.selected = true;
            lengthSelect.appendChild(option);
        });
        lengthWrap.appendChild(lengthSelect);
        lengthWrap.appendChild(createElement("span", "app-table-length-suffix", "entries"));

        var filters = createElement("div", "app-table-filters");
        toolbarLeft.appendChild(lengthWrap);
        toolbarLeft.appendChild(filters);

        var exports = createElement("div", "app-table-exports");
        toolbar.appendChild(toolbarLeft);
        toolbar.appendChild(exports);

        var frame = createElement("div", "app-table-frame");
        var loader = createElement("div", "app-table-loader");
        loader.hidden = true;
        loader.setAttribute("role", "status");
        loader.setAttribute("aria-live", "polite");
        var spinner = createElement("span", "app-table-loader-spinner");
        spinner.setAttribute("aria-hidden", "true");
        var loaderText = createElement("span", "app-table-loader-text", options.appLoaderText || "Loading data...");
        loader.appendChild(spinner);
        loader.appendChild(loaderText);

        var footer = createElement("div", "app-table-footer");
        var info = createElement("div", "app-table-info", "Showing 0 to 0 of 0 entries");
        info.setAttribute("aria-live", "polite");
        var pagination = createElement("nav", "app-table-pagination");
        pagination.setAttribute("aria-label", "Table pagination");
        footer.appendChild(info);
        footer.appendChild(pagination);

        frame.appendChild(tableElement);
        frame.appendChild(loader);
        shell.appendChild(searchRow);
        shell.appendChild(toolbar);
        shell.appendChild(frame);
        shell.appendChild(footer);

        var controls = {
            shell: shell,
            searchRow: searchRow,
            searchInput: searchInput,
            toolbar: toolbar,
            toolbarLeft: toolbarLeft,
            lengthSelect: lengthSelect,
            filters: filters,
            exports: exports,
            frame: frame,
            loader: loader,
            loaderText: loaderText,
            footer: footer,
            info: info,
            pagination: pagination,
            exportButtons: {}
        };
        shell._appControls = controls;
        return controls;
    }

    function showLoader(controls, message) {
        if (!controls || !controls.loader) return;
        controls.loaderText.textContent = message || "Loading data...";
        controls.loader.hidden = false;
        controls.frame.classList.add("is-loading");
    }

    function hideLoader(controls) {
        if (!controls || !controls.loader) return;
        controls.loader.hidden = true;
        controls.frame.classList.remove("is-loading");
    }

    function makePageButton(label, pageIndex, current, disabled, onClick) {
        var button = createElement("button", "app-table-page-button", label);
        button.type = "button";
        if (current) {
            button.classList.add("is-current");
            button.setAttribute("aria-current", "page");
        }
        if (disabled) button.disabled = true;
        if (!disabled && typeof onClick === "function") {
            button.addEventListener("click", function () { onClick(pageIndex); });
        }
        return button;
    }

    function appendEllipsis(container) {
        var ellipsis = createElement("span", "app-table-page-ellipsis", "…");
        ellipsis.setAttribute("aria-hidden", "true");
        container.appendChild(ellipsis);
    }

    function renderFooter(table, controls) {
        if (!table || !controls || !controls.info || !controls.pagination) return;

        var pageInfo = table.page.info();
        var filtered = Number(pageInfo.recordsDisplay || 0);
        var total = Number(pageInfo.recordsTotal || 0);
        var start = filtered > 0 ? Number(pageInfo.start || 0) + 1 : 0;
        var end = filtered > 0 ? Number(pageInfo.end || 0) : 0;

        var text = "Showing " + start + " to " + end + " of " + filtered + " entries";
        if (filtered !== total) text += " (filtered from " + total + " total entries)";
        controls.info.textContent = text;

        var pagination = controls.pagination;
        pagination.innerHTML = "";
        var pages = Math.max(0, Number(pageInfo.pages || 0));
        var current = Math.max(0, Number(pageInfo.page || 0));

        function go(pageIndex) {
            if (pageIndex < 0 || pageIndex >= pages || pageIndex === current) return;
            table.page(pageIndex).draw("page");
        }

        pagination.appendChild(makePageButton("Previous", current - 1, false, pages === 0 || current <= 0, go));

        if (pages > 0) {
            var visible = [];

            // Compact pagination. Five pages or fewer are shown in full.
            // Larger sets use ellipses, e.g. 1 2 3 4 … 6 near the start,
            // and adapt around the current page for later pages.
            if (pages <= 5) {
                for (var i = 0; i < pages; i += 1) visible.push(i);
            } else if (current <= 3) {
                visible = [0, 1, 2, 3, -1, pages - 1];
            } else if (current >= pages - 3) {
                visible = [0, -1, pages - 4, pages - 3, pages - 2, pages - 1];
            } else {
                visible = [0, -1, current - 1, current, current + 1, -1, pages - 1];
            }

            visible.forEach(function (pageIndex) {
                if (pageIndex === -1) {
                    appendEllipsis(pagination);
                    return;
                }
                pagination.appendChild(makePageButton(String(pageIndex + 1), pageIndex, pageIndex === current, false, go));
            });
        }

        pagination.appendChild(makePageButton("Next", current + 1, false, pages === 0 || current >= pages - 1, go));
    }

    function buildExportControls(table, controls, buttonConfigs) {
        controls.exports.innerHTML = "";
        controls.exportButtons = {};
        if (!Array.isArray(buttonConfigs) || !buttonConfigs.length) {
            controls.exports.hidden = true;
            return;
        }

        controls.exports.hidden = false;
        var ids = [ACTION.COPY, ACTION.CSV, ACTION.EXCEL, ACTION.PDF, ACTION.PRINT];
        buttonConfigs.forEach(function (config, index) {
            var button = createElement("button", "app-table-export-button", config.text || "Export");
            button.type = "button";
            button.hidden = true; // Permission API decides which buttons become visible.
            button.setAttribute("data-export-index", String(index));
            button.addEventListener("click", function () {
                if (table && table.button) table.button(index).trigger();
            });
            controls.exports.appendChild(button);
            controls.exportButtons[ids[index] || (1000 + index)] = button;
        });
    }

    function bindControls(table, controls, options) {
        var searchDelay = Number(options.searchDelay || 350);
        controls.searchInput.addEventListener("input", debounce(function () {
            table.search(controls.searchInput.value || "").draw();
        }, searchDelay));

        controls.lengthSelect.addEventListener("change", function () {
            table.page.len(Number(controls.lengthSelect.value || 10)).draw();
        });

        table.on("draw", function () {
            hideLoader(controls);
            renderFooter(table, controls);
            if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
        });

        table.on("xhr", function () {
            hideLoader(controls);
        });

        table.on("error", function () {
            hideLoader(controls);
        });
    }

    function serverSideExportAction(e, dt, button, config) {
        var self = this;
        var settings = dt.settings()[0];
        var oldStart = settings._iDisplayStart;
        dt.one("preXhr", function (e2, settings2, data) {
            data.start = 0;
            data.length = 100000;
            dt.one("preDraw", function () {
                var cls = button[0].className || "";
                if (cls.indexOf("buttons-copy") >= 0) $.fn.dataTable.ext.buttons.copyHtml5.action.call(self, e, dt, button, config);
                else if (cls.indexOf("buttons-csv") >= 0) $.fn.dataTable.ext.buttons.csvHtml5.action.call(self, e, dt, button, config);
                else if (cls.indexOf("buttons-excel") >= 0) $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);
                else if (cls.indexOf("buttons-pdf") >= 0) $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);
                else if (cls.indexOf("buttons-print") >= 0) $.fn.dataTable.ext.buttons.print.action.call(self, e, dt, button, config);

                dt.one("preXhr", function (e3, settings3, data2) {
                    settings3._iDisplayStart = oldStart;
                    data2.start = oldStart;
                });
                window.setTimeout(function () { dt.ajax.reload(null, false); }, 0);
                return false;
            });
        });
        dt.ajax.reload();
    }

    function bindResponsiveAdjust(table) {
        if (!table || !table.columns) return;
        window.addEventListener((window.AppRuntime ? AppRuntime.event("layout-resize") : "amirtham:layout-resize"), function () {
            window.setTimeout(function () { table.columns.adjust(); }, 40);
        });
        document.addEventListener((window.AppRuntime ? AppRuntime.event("theme-loaded") : "amirtham:theme-loaded"), function () {
            window.setTimeout(function () { table.columns.adjust(); }, 40);
        });
    }

    function applyExportPermissions(table, actions, indexes) {
        if (!table || !table._appControls) return;
        indexes = indexes || { copy: 0, csv: 1, excel: 2, pdf: 3, print: 4 };
        var rules = [
            [indexes.copy, ACTION.COPY],
            [indexes.csv, ACTION.CSV],
            [indexes.excel, ACTION.EXCEL],
            [indexes.pdf, ACTION.PDF],
            [indexes.print, ACTION.PRINT]
        ];
        rules.forEach(function (rule) {
            if (rule[0] === undefined || rule[0] === null) return;
            var node = table._appControls.exportButtons[rule[1]];
            if (node) node.hidden = !has(actions, rule[1]);
        });

        var anyVisible = Object.keys(table._appControls.exportButtons).some(function (id) {
            return !table._appControls.exportButtons[id].hidden;
        });
        table._appControls.exports.hidden = !anyVisible;
    }

    function addFilter(table, config) {
        if (!table || !table._appControls || !config) return null;
        var wrap = createElement("label", "app-table-filter");
        if (config.label) wrap.appendChild(createElement("span", "app-table-filter-label", config.label));
        var select = createElement("select", "app-table-filter-select");
        (config.options || []).forEach(function (item) {
            var option = document.createElement("option");
            if (typeof item === "object") {
                option.value = String(item.value === undefined ? "" : item.value);
                option.textContent = String(item.label === undefined ? item.value : item.label);
            } else {
                option.value = String(item);
                option.textContent = String(item);
            }
            select.appendChild(option);
        });
        if (config.value !== undefined) select.value = String(config.value);
        select.addEventListener("change", function () {
            if (typeof config.onChange === "function") config.onChange(select.value, table, select);
        });
        wrap.appendChild(select);
        table._appControls.filters.appendChild(wrap);
        return select;
    }

    function init(selector, options) {
        if (!ensureAvailable()) return null;
        options = options || {};
        var tableElement = typeof selector === "string" ? document.querySelector(selector) : selector;
        if (!tableElement) return null;

        var controls = buildShell(tableElement, options);
        showLoader(controls, options.appLoaderText || "Loading data...");

        var originalAjax = options.ajax;
        var buttonConfigs = Array.isArray(options.buttons) ? options.buttons.slice() : [];
        var defaults = {
            processing: false, // Never render the DataTables default processing UI.
            serverSide: true,
            searching: true,
            searchDelay: 350,
            pageLength: 10,
            lengthMenu: [[10,25,50,100],[10,25,50,100]],
            autoWidth: false,
            lengthChange: false,
            // DataTables is the engine only. B stays hidden; info/pagination are rendered by AppDataTable outside the table frame.
            dom: '<"dt-engine-buttons"B>t'
        };

        var merged = $.extend(true, {}, defaults, options);
        merged.processing = false;
        merged.lengthChange = false;
        merged.dom = defaults.dom;

        if (typeof originalAjax === "function") {
            merged.ajax = function (data, callback, settings) {
                showLoader(controls, options.appLoaderText || "Loading data...");
                var completed = false;
                function done(json) {
                    if (completed) return;
                    completed = true;
                    hideLoader(controls);
                    callback(json);
                }
                try {
                    var result = originalAjax.call(this, data, done, settings);
                    if (result && typeof result.catch === "function") {
                        result.catch(function () { hideLoader(controls); });
                    }
                    return result;
                } catch (error) {
                    hideLoader(controls);
                    throw error;
                }
            };
        }

        var table = $(tableElement).DataTable(merged);
        table._appControls = controls;
        buildExportControls(table, controls, buttonConfigs);
        bindControls(table, controls, merged);
        bindResponsiveAdjust(table);
        renderFooter(table, controls);
        return table;
    }

    window.AppDataTable = {
        ACTION: ACTION,
        has: has,
        init: init,
        addFilter: addFilter,
        ensureAvailable: ensureAvailable,
        serverSideExportAction: serverSideExportAction,
        applyExportPermissions: applyExportPermissions,
        showLoader: function (table, message) {
            if (table && table._appControls) showLoader(table._appControls, message);
        },
        hideLoader: function (table) {
            if (table && table._appControls) hideLoader(table._appControls);
        }
    };
})(window, window.jQuery, document);
