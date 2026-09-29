(function (window, document) {
    "use strict";

    function notify(message, type) {
        if (typeof window.showToast === "function") {
            window.showToast(message, { type: type || "danger", duration: 4 });
        }
    }

    function readDimensions(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);

            if (file.type.indexOf("image/") === 0) {
                var image = new Image();
                image.onload = function () {
                    URL.revokeObjectURL(url);
                    resolve({ width: image.width, height: image.height, duration: 0 });
                };
                image.onerror = function () { URL.revokeObjectURL(url); reject(); };
                image.src = url;
                return;
            }

            if (file.type.indexOf("video/") === 0) {
                var video = document.createElement("video");
                video.preload = "metadata";
                video.onloadedmetadata = function () {
                    URL.revokeObjectURL(url);
                    resolve({ width: video.videoWidth, height: video.videoHeight, duration: video.duration });
                };
                video.onerror = function () { URL.revokeObjectURL(url); reject(); };
                video.src = url;
                return;
            }

            URL.revokeObjectURL(url);
            resolve({ width: 0, height: 0, duration: 0 });
        });
    }

    var modalContent, modalImage, modalTitle, modalObjectUrl;

    function ensureImageModal() {
        if (modalContent && modalContent.parentNode) return true;
        if (!document.body) return false;

        modalContent = document.createElement("div");
        modalContent.className = "app-modal-form hidden";
        modalContent.innerHTML =
            '<div class="modal-header">' +
                '<div class="modal-header-copy"><h2>Image Preview</h2><p>Preview uploaded image.</p></div>' +
                '<button class="modal-close" type="button" data-modal-close aria-label="Close image preview" title="Close"><i data-lucide="x"></i></button>' +
            '</div>' +
            '<div class="modal-body"><img alt="Image preview" style="display:block;max-width:100%;max-height:72vh;width:auto;height:auto;margin:0 auto;object-fit:contain"></div>' +
            '<div class="modal-footer"><div class="buttons"><button class="btn gray" type="button" data-modal-close>Close</button></div></div>';

        modalTitle = modalContent.querySelector("h2");
        modalImage = modalContent.querySelector("img");
        document.body.appendChild(modalContent);
        return true;
    }

    function clearModalUrl() {
        if (!modalObjectUrl) return;
        URL.revokeObjectURL(modalObjectUrl);
        modalObjectUrl = null;
    }

    function openImagePreview(source, title) {
        if (!window.AppModal || typeof window.AppModal.open !== "function") {
            notify("Common modal component is unavailable.", "danger");
            return false;
        }
        if (!ensureImageModal()) return false;

        clearModalUrl();

        var src = "";
        if (source instanceof File || source instanceof Blob) {
            modalObjectUrl = URL.createObjectURL(source);
            src = modalObjectUrl;
        } else {
            src = String(source || "").trim();
        }
        if (!src) return false;

        modalTitle.textContent = String(title || "Image Preview");
        modalImage.src = src;
        modalImage.alt = String(title || "Image preview");

        var opened = window.AppModal.open(modalContent, {
            size: "xl",
            focusSelector: "[data-modal-close]",
            onClose: function () {
                modalImage.removeAttribute("src");
                clearModalUrl();
            }
        });

        if (opened && window.lucide && typeof window.lucide.createIcons === "function") {
            window.lucide.createIcons();
        }
        return opened;
    }

    function FileUploader(input, options) {
        this.input = input;
        this.options = Object.assign({
            multiple: input.multiple,
            minFiles: 0,
            maxFiles: input.multiple ? 10 : 1,
            minSizeMB: 0,
            maxSizeMB: 10,
            maxImageSizeMB: 0,
            maxVideoSizeMB: 0,
            allowedTypes: ["jpg", "jpeg", "png", "webp", "gif", "mp4", "webm", "mov"],
            minWidth: 0,
            maxWidth: 0,
            minHeight: 0,
            maxHeight: 0,
            maxVideoDuration: 0,
            prompt: "Drag and drop files here",
            chooseText: "or click to choose",
            clearText: "Clear all",
            currentFile: null,
            removeInput: null
        }, options || {});

        this.files = [];
        this.nextFileId = 1;
        this.currentFile = null;
        this.currentRemoved = false;
        this.removeInput = typeof this.options.removeInput === "string"
            ? document.querySelector(this.options.removeInput)
            : this.options.removeInput;

        this.build();
        this.bind();
        this.setCurrentFile(this.options.currentFile);
    }

    FileUploader.prototype.setRemoveFlag = function (value) {
        this.currentRemoved = Boolean(value);
        if (this.removeInput) this.removeInput.value = this.currentRemoved ? "1" : "0";
    };

    FileUploader.prototype.setCurrentFile = function (file) {
        this.files = [];
        this.input.value = "";
        this.syncInput();
        this.setRemoveFlag(false);

        if (typeof file === "string") file = { url: file, name: "Current file" };
        this.currentFile = file && file.url
            ? { url: String(file.url), name: String(file.name || "Current file") }
            : null;

        this.render();
        return this;
    };

    FileUploader.prototype.hasCurrentFile = function () {
        return Boolean(this.currentFile && !this.currentRemoved);
    };

    FileUploader.prototype.removeCurrentFile = function () {
        if (!this.currentFile) return;
        this.setRemoveFlag(true);
        this.render();
    };

    FileUploader.prototype.build = function () {
        this.input.multiple = Boolean(this.options.multiple);
        this.input.style.display = "none";

        this.area = document.createElement("div");
        this.area.className = "file-upload";
        this.area.tabIndex = 0;
        this.area.setAttribute("role", "button");
        this.area.setAttribute("aria-label", "Choose files");

        this.prompt = document.createElement("div");
        this.prompt.className = "file-upload-prompt";
        this.prompt.innerHTML = "<strong></strong><br><small></small>";
        this.prompt.querySelector("strong").textContent = this.options.prompt;
        this.prompt.querySelector("small").textContent = this.options.chooseText;

        this.toolbar = document.createElement("div");
        this.toolbar.className = "file-upload-toolbar";
        this.count = document.createElement("span");
        this.clearButton = document.createElement("button");
        this.clearButton.type = "button";
        this.clearButton.className = "file-upload-clear";
        this.clearButton.textContent = this.options.clearText;
        this.toolbar.appendChild(this.count);
        this.toolbar.appendChild(this.clearButton);

        this.preview = document.createElement("div");
        this.preview.className = "file-upload-preview";

        this.area.appendChild(this.prompt);
        this.area.appendChild(this.toolbar);
        this.area.appendChild(this.preview);
        this.input.parentNode.insertBefore(this.area, this.input.nextSibling);
    };

    FileUploader.prototype.bind = function () {
        var self = this;

        this.area.addEventListener("click", function (event) {
            if (!event.target.closest("button,video,a,img")) self.input.click();
        });

        this.area.addEventListener("keydown", function (event) {
            if ((event.key === "Enter" || event.key === " ") && event.target === self.area) {
                event.preventDefault();
                self.input.click();
            }
        });

        ["dragenter", "dragover"].forEach(function (name) {
            self.area.addEventListener(name, function (event) {
                event.preventDefault();
                self.area.classList.add("is-dragging");
            });
        });

        ["dragleave", "drop"].forEach(function (name) {
            self.area.addEventListener(name, function (event) {
                event.preventDefault();
                self.area.classList.remove("is-dragging");
            });
        });

        this.area.addEventListener("drop", function (event) {
            self.addFiles(Array.prototype.slice.call(event.dataTransfer.files || []));
        });

        this.input.addEventListener("change", function () {
            self.addFiles(Array.prototype.slice.call(self.input.files || []));
        });

        this.clearButton.addEventListener("click", function (event) {
            event.stopPropagation();
            self.clear();
        });

        if (this.input.form) {
            this.input.form.addEventListener("submit", function (event) {
                var count = self.files.length || (self.hasCurrentFile() ? 1 : 0);
                if (count < self.options.minFiles) {
                    event.preventDefault();
                    notify("Select at least " + self.options.minFiles + " file(s).", "warning");
                }
            });
        }
    };

    FileUploader.prototype.addFiles = async function (newFiles) {
        for (var i = 0; i < newFiles.length; i += 1) {
            var file = newFiles[i];
            if (!this.options.multiple) this.files = [];

            if (this.files.length >= this.options.maxFiles) {
                notify("Maximum " + this.options.maxFiles + " file(s) allowed.", "warning");
                break;
            }

            var extension = String(file.name || "").split(".").pop().toLowerCase();
            if (this.options.allowedTypes.indexOf(extension) === -1) {
                notify(file.name + " has an unsupported file type.", "danger");
                continue;
            }

            var sizeMB = file.size / (1024 * 1024);
            var maxSize = file.type.indexOf("video/") === 0
                ? Number(this.options.maxVideoSizeMB || this.options.maxSizeMB)
                : Number(this.options.maxImageSizeMB || this.options.maxSizeMB);

            if (sizeMB < this.options.minSizeMB || sizeMB > maxSize) {
                notify(file.name + " has an invalid file size.", "danger");
                continue;
            }

            try {
                var details = await readDimensions(file);

                if ((this.options.minWidth && details.width < this.options.minWidth) ||
                    (this.options.maxWidth && details.width > this.options.maxWidth) ||
                    (this.options.minHeight && details.height < this.options.minHeight) ||
                    (this.options.maxHeight && details.height > this.options.maxHeight)) {
                    notify(file.name + " has invalid dimensions.", "danger");
                    continue;
                }

                if (this.options.maxVideoDuration && details.duration > this.options.maxVideoDuration) {
                    notify(file.name + " exceeds the allowed video duration.", "danger");
                    continue;
                }
            } catch (error) {
                notify(file.name + " could not be previewed.", "danger");
                continue;
            }

            this.files.push({ id: this.nextFileId++, file: file });
            this.setRemoveFlag(false);
        }

        this.syncInput();
        this.render();
    };

    FileUploader.prototype.syncInput = function () {
        if (typeof DataTransfer === "undefined") return;
        var transfer = new DataTransfer();
        this.files.forEach(function (item) { transfer.items.add(item.file); });
        this.input.files = transfer.files;
    };

    FileUploader.prototype.remove = function (fileId) {
        this.files = this.files.filter(function (item) { return item.id !== fileId; });
        this.syncInput();
        this.render();
    };

    FileUploader.prototype.clear = function () {
        if (this.files.length) {
            this.files = [];
            this.input.value = "";
            this.syncInput();
            this.setRemoveFlag(false);
            this.render();
            return;
        }
        if (this.hasCurrentFile()) this.removeCurrentFile();
    };

    FileUploader.prototype.image = function (source, name) {
        var image = document.createElement("img");
        image.className = "file-upload-media";
        image.alt = name || "Image";
        image.title = "Click to preview";
        image.tabIndex = 0;
        image.setAttribute("role", "button");
        image.style.cursor = "pointer";

        function open(event) {
            event.preventDefault();
            event.stopPropagation();
            openImagePreview(source, name);
        }

        image.addEventListener("click", open);
        image.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") open(event);
        });
        return image;
    };

    FileUploader.prototype.appendCard = function (media, nameText, removeCallback) {
        var card = document.createElement("div");
        card.className = "file-upload-card";

        var name = document.createElement("small");
        name.className = "file-upload-name";
        name.textContent = nameText;

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "file-upload-remove";
        remove.innerHTML = "&times;";
        remove.setAttribute("aria-label", "Remove " + nameText);
        remove.addEventListener("click", function (event) {
            event.stopPropagation();
            removeCallback();
        });

        card.appendChild(media);
        card.appendChild(name);
        card.appendChild(remove);
        this.preview.appendChild(card);
    };

    FileUploader.prototype.render = function () {
        var self = this;
        var hasCurrent = this.hasCurrentFile() && this.files.length === 0;
        var hasFiles = this.files.length > 0;
        var hasAny = hasCurrent || hasFiles;

        this.preview.innerHTML = "";
        this.area.classList.toggle("has-files", hasAny);
        this.prompt.hidden = hasAny;
        this.toolbar.hidden = !hasAny;
        this.preview.hidden = !hasAny;
        this.count.textContent = hasFiles ? this.files.length + " file(s) selected" : (hasCurrent ? "Current file" : "");

        if (hasCurrent) {
            var currentImage = this.image(this.currentFile.url, this.currentFile.name);
            currentImage.src = this.currentFile.url;
            this.appendCard(currentImage, this.currentFile.name, function () {
                self.removeCurrentFile();
            });
        }

        this.files.forEach(function (item) {
            var file = item.file;
            var url = URL.createObjectURL(file);
            var media;

            if (file.type.indexOf("video/") === 0) {
                media = document.createElement("video");
                media.className = "file-upload-media";
                media.controls = true;
                media.src = url;
                media.onloadeddata = function () { URL.revokeObjectURL(url); };
            } else {
                media = self.image(file, file.name);
                media.src = url;
                media.onload = function () { URL.revokeObjectURL(url); };
            }

            self.appendCard(media, file.name, function () {
                self.remove(item.id);
            });
        });

        if (hasFiles && this.files.length < this.options.maxFiles) {
            var add = document.createElement("div");
            add.className = "file-upload-add";
            add.textContent = "+ Add more";
            this.preview.appendChild(add);
        }
    };

    function init(selector, options) {
        var input = typeof selector === "string" ? document.querySelector(selector) : selector;
        if (!input || input.tagName !== "INPUT" || input.type !== "file") {
            throw new Error("FileUpload requires a file input.");
        }
        if (input._fileUploader) return input._fileUploader;
        input._fileUploader = new FileUploader(input, options);
        return input._fileUploader;
    }

    window.FileUpload = {
        init: init,
        openImagePreview: openImagePreview
    };
})(window, document);
