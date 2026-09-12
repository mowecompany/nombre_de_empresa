/*
 * SquareCropper - recorte cuadrado + compresion en el navegador.
 * Sin dependencias externas (funciona offline dentro de Electron).
 *
 * Uso:
 *   SquareCropper.attach({
 *     inputId: 'imagen',
 *     size: 1000,
 *     maxBytes: 500 * 1024,
 *     feedbackId: 'imagenFeedback',
 *     previewIds: ['imagenPreviewCrear'],
 *     placeholderIds: ['imagenPreviewCrearPlaceholder'],
 *     onReady: function (info) {},
 *     onClear: function () {}
 *   });
 *
 * Metodos publicos: reopen(), loadFromUrl(url), reset(clearInput)
 */
(function (global) {
    'use strict';

    var MIMES_OK = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/webp', 'image/gif'];

    function formatBytes(bytes) {
        if (!bytes || bytes < 0) return '0 KB';
        var kb = bytes / 1024;
        if (kb < 1024) return kb.toFixed(1) + ' KB';
        return (kb / 1024).toFixed(2) + ' MB';
    }

    function el(tag, styles, text) {
        var node = document.createElement(tag);
        if (styles) node.setAttribute('style', styles);
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function button(label, primary) {
        var b = el('button', 'padding:8px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid ' +
            (primary ? '#1d4ed8' : '#d0d7de') + ';background:' + (primary ? '#1d4ed8' : '#ffffff') + ';color:' +
            (primary ? '#ffffff' : '#344054') + ';', label);
        b.type = 'button';
        return b;
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function (resolve) {
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) { resolve(blob); }, type, quality);
            } else {
                var dataUrl = canvas.toDataURL(type, quality);
                var parts = dataUrl.split(',');
                var binary = atob(parts[1]);
                var buffer = new Uint8Array(binary.length);
                for (var i = 0; i < binary.length; i++) buffer[i] = binary.charCodeAt(i);
                resolve(new Blob([buffer], { type: type }));
            }
        });
    }

    function canvasHasAlpha(canvas) {
        try {
            var ctx = canvas.getContext('2d');
            var w = canvas.width;
            var h = canvas.height;
            var data = ctx.getImageData(0, 0, w, h).data;
            for (var i = 3; i < data.length; i += 40) {
                if (data[i] < 250) return true;
            }
            return false;
        } catch (e) {
            return true;
        }
    }

    function Cropper(options) {
        this.opts = options || {};
        this.size = this.opts.size || 1000;
        this.maxBytes = this.opts.maxBytes || 500 * 1024;
        this.input = document.getElementById(this.opts.inputId);
        if (!this.input) return;
        this.viewport = 300;
        this.image = null;
        this.scale = 1;
        this.minScale = 1;
        this.offsetX = 0;
        this.offsetY = 0;
        this.dragging = false;
        this.internalChange = false;
        this.build();
        this.bind();
    }

    Cropper.prototype.build = function () {
        var self = this;

        var panel = el('div', 'display:none;margin-top:12px;padding:12px;border:1px solid #d0d7de;border-radius:10px;background:#f8fafc;');
        this.panel = panel;

        var title = el('div', 'font-size:12px;font-weight:700;color:#344054;margin-bottom:8px;text-transform:uppercase;', 'Ajusta el encuadre');
        panel.appendChild(title);

        var stage = el('div', 'position:relative;width:' + this.viewport + 'px;height:' + this.viewport +
            'px;margin:0 auto;border-radius:8px;overflow:hidden;background:#e9edf2;cursor:grab;touch-action:none;');
        this.canvas = document.createElement('canvas');
        this.canvas.width = this.viewport;
        this.canvas.height = this.viewport;
        this.canvas.setAttribute('style', 'display:block;width:100%;height:100%;');
        stage.appendChild(this.canvas);
        var marco = el('div', 'position:absolute;inset:0;border:2px solid rgba(29,78,216,0.85);border-radius:8px;pointer-events:none;');
        stage.appendChild(marco);
        panel.appendChild(stage);
        this.stage = stage;

        var zoomWrap = el('div', 'display:flex;align-items:center;gap:8px;margin-top:10px;');
        zoomWrap.appendChild(el('span', 'font-size:11px;color:#667085;', 'ZOOM'));
        this.zoom = document.createElement('input');
        this.zoom.type = 'range';
        this.zoom.min = '30';
        this.zoom.max = '400';
        this.zoom.step = '1';
        this.zoom.value = '100';
        this.zoom.setAttribute('style', 'flex:1;');
        zoomWrap.appendChild(this.zoom);
        this.zoomLabel = el('span', 'font-size:11px;color:#667085;min-width:44px;text-align:right;', '100%');
        zoomWrap.appendChild(this.zoomLabel);
        panel.appendChild(zoomWrap);

        var actions = el('div', 'display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;justify-content:center;');
        this.btnCenter = button('Centrar');
        this.btnFit = button('Usar toda la imagen');
        this.btnApply = button('Aplicar recorte', true);
        this.btnCancel = button('Cancelar');
        actions.appendChild(this.btnCenter);
        actions.appendChild(this.btnFit);
        actions.appendChild(this.btnApply);
        actions.appendChild(this.btnCancel);
        panel.appendChild(actions);

        var host = this.opts.mountId ? document.getElementById(this.opts.mountId) : null;
        if (host) {
            host.appendChild(panel);
        } else if (this.input.parentNode) {
            this.input.parentNode.appendChild(panel);
        }

        this.btnCenter.addEventListener('click', function () { self.center(); });
        this.btnFit.addEventListener('click', function () { self.fitWhole(); });
        this.btnApply.addEventListener('click', function () { self.apply(); });
        this.btnCancel.addEventListener('click', function () { self.cancel(); });
    };

    Cropper.prototype.bind = function () {
        var self = this;

        this.input.addEventListener('change', function () {
            if (self.internalChange) {
                self.internalChange = false;
                return;
            }
            self.handleFile();
        });

        this.zoom.addEventListener('input', function () {
            self.applyZoom(parseInt(self.zoom.value, 10), true);
        });

        this.stage.addEventListener('pointerdown', function (ev) {
            if (!self.image) return;
            self.dragging = true;
            self.lastX = ev.clientX;
            self.lastY = ev.clientY;
            self.stage.style.cursor = 'grabbing';
            if (self.stage.setPointerCapture) self.stage.setPointerCapture(ev.pointerId);
        });
        this.stage.addEventListener('pointermove', function (ev) {
            if (!self.dragging || !self.image) return;
            self.offsetX += ev.clientX - self.lastX;
            self.offsetY += ev.clientY - self.lastY;
            self.lastX = ev.clientX;
            self.lastY = ev.clientY;
            self.clamp();
            self.draw();
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (name) {
            self.stage.addEventListener(name, function () {
                self.dragging = false;
                self.stage.style.cursor = 'grab';
            });
        });
        this.stage.addEventListener('wheel', function (ev) {
            if (!self.image) return;
            ev.preventDefault();
            var dy = ev.deltaY * (ev.deltaMode === 1 ? 16 : ev.deltaMode === 2 ? 100 : 1);
            var actual = parseInt(self.zoom.value, 10);
            var next = Math.round(actual * Math.exp(-dy * 0.0015));
            if (next === actual) next = actual + (dy < 0 ? 1 : -1);
            self.applyZoom(next, true);
        }, { passive: false });
    };

    Cropper.prototype.applyZoom = function (valor, keepCenter) {
        if (!this.image) return;
        var pct = Math.max(30, Math.min(400, valor || 100));
        var prevScale = this.scale;
        this.zoom.value = String(pct);
        if (this.zoomLabel) this.zoomLabel.textContent = pct + '%';
        this.scale = this.minScale * (pct / 100);
        if (keepCenter && prevScale > 0) {
            var k = this.scale / prevScale;
            var c = this.viewport / 2;
            this.offsetX = c - (c - this.offsetX) * k;
            this.offsetY = c - (c - this.offsetY) * k;
        }
        this.clamp();
        this.draw();
    };

    Cropper.prototype.feedback = function (message, tone) {
        var node = this.opts.feedbackId ? document.getElementById(this.opts.feedbackId) : null;
        if (!node) return;
        if (!message) {
            node.style.display = 'none';
            node.innerHTML = '';
            return;
        }
        var color = tone === 'error' ? '#b42318' : (tone === 'ok' ? '#027a48' : '#344054');
        var bg = tone === 'error' ? '#fef3f2' : (tone === 'ok' ? '#ecfdf3' : '#f8fafc');
        node.style.display = 'block';
        node.setAttribute('style', 'display:block;margin-top:8px;padding:10px;border-radius:6px;font-size:12px;background:' +
            bg + ';color:' + color + ';');
        node.innerHTML = message;
    };

    Cropper.prototype.setPreview = function (src) {
        (this.opts.previewIds || []).forEach(function (id) {
            var img = document.getElementById(id);
            if (!img) return;
            if (src) {
                img.src = src;
                img.style.display = 'block';
            } else {
                img.src = '';
                img.style.display = 'none';
            }
        });
        (this.opts.placeholderIds || []).forEach(function (id) {
            var ph = document.getElementById(id);
            if (ph) ph.style.display = src ? 'none' : 'inline';
        });
        var wraps = this.opts.wrapIds || [];
        wraps.forEach(function (id) {
            var wrap = document.getElementById(id);
            if (wrap) wrap.style.display = src ? 'block' : 'none';
        });
    };

    Cropper.prototype.handleFile = function () {
        var self = this;
        var file = this.input.files && this.input.files[0];
        if (!file) {
            this.reset(false);
            return;
        }

        var tipo = (file.type || '').toLowerCase();
        if (MIMES_OK.indexOf(tipo) === -1) {
            this.input.value = '';
            this.panel.style.display = 'none';
            this.setPreview(null);
            this.feedback('Formato no compatible. Usa PNG, JPG, JPEG o WEBP.', 'error');
            if (this.opts.onClear) this.opts.onClear();
            return;
        }

        if (file.size > 25 * 1024 * 1024) {
            this.input.value = '';
            this.feedback('La imagen supera los 25 MB. Elige un archivo más pequeño.', 'error');
            if (this.opts.onClear) this.opts.onClear();
            return;
        }

        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            self.image = img;
            self.sourceName = file.name;
            self.sourceInfo = img.naturalWidth + ' × ' + img.naturalHeight + ' px, ' + formatBytes(file.size);
            self.panel.style.display = 'block';
            self.center();
            self.feedback('Imagen original: ' + self.sourceInfo +
                '.<br>Ajusta el encuadre y pulsa <strong>Aplicar recorte</strong>.', 'info');
            URL.revokeObjectURL(url);
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            self.input.value = '';
            self.feedback('No se pudo leer la imagen seleccionada.', 'error');
        };
        img.src = url;
    };

    /* Carga una imagen ya guardada (misma URL del servidor) para recortarla. */
    Cropper.prototype.loadFromUrl = function (url) {
        var self = this;
        if (!url) return;
        var img = new Image();
        img.onload = function () {
            self.image = img;
            self.sourceName = 'imagen_actual';
            self.sourceInfo = img.naturalWidth + ' × ' + img.naturalHeight + ' px';
            self.panel.style.display = 'block';
            self.center();
            self.feedback('Imagen actual: ' + self.sourceInfo +
                '.<br>Ajusta el encuadre y pulsa <strong>Aplicar recorte</strong>.', 'info');
            if (self.panel.scrollIntoView) self.panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
        img.onerror = function () {
            self.feedback('No se pudo cargar la imagen actual para recortarla.', 'error');
        };
        img.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'cropper=' + Date.now();
    };

    /* Reabre el recuadro con la imagen original y el encuadre anterior. */
    Cropper.prototype.reopen = function () {
        if (!this.image) {
            this.feedback('Primero elige una imagen.', 'error');
            return;
        }
        this.panel.style.display = 'block';
        this.draw();
        this.feedback('Ajusta de nuevo el encuadre y pulsa <strong>Aplicar recorte</strong>.', 'info');
        if (this.panel.scrollIntoView) this.panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    Cropper.prototype.cancel = function () {
        if (this.processed) {
            // Ya habia un recorte aplicado: solo cerrar el panel y conservarlo.
            this.panel.style.display = 'none';
            return;
        }
        this.reset(true);
    };

    Cropper.prototype.center = function () {
        if (!this.image) return;
        var w = this.image.naturalWidth;
        var h = this.image.naturalHeight;
        this.minScale = Math.max(this.viewport / w, this.viewport / h);
        this.applyZoom(100, false);
        this.scale = this.minScale;
        this.offsetX = (this.viewport - w * this.scale) / 2;
        this.offsetY = (this.viewport - h * this.scale) / 2;
        this.clamp();
        this.draw();
    };

    Cropper.prototype.fitWhole = function () {
        if (!this.image) return;
        var w = this.image.naturalWidth;
        var h = this.image.naturalHeight;
        var target = Math.min(this.viewport / w, this.viewport / h);
        var pct = Math.max(30, Math.min(400, Math.round((target / this.minScale) * 100)));
        this.zoom.value = String(pct);
        if (this.zoomLabel) this.zoomLabel.textContent = pct + '%';
        this.scale = this.minScale * (pct / 100);
        this.offsetX = (this.viewport - w * this.scale) / 2;
        this.offsetY = (this.viewport - h * this.scale) / 2;
        this.draw();
    };

    Cropper.prototype.clamp = function () {
        if (!this.image) return;
        var w = this.image.naturalWidth * this.scale;
        var h = this.image.naturalHeight * this.scale;
        if (w >= this.viewport) {
            this.offsetX = Math.min(0, Math.max(this.viewport - w, this.offsetX));
        } else {
            this.offsetX = Math.min(this.viewport - w, Math.max(0, this.offsetX));
        }
        if (h >= this.viewport) {
            this.offsetY = Math.min(0, Math.max(this.viewport - h, this.offsetY));
        } else {
            this.offsetY = Math.min(this.viewport - h, Math.max(0, this.offsetY));
        }
    };

    Cropper.prototype.draw = function () {
        var ctx = this.canvas.getContext('2d');
        ctx.clearRect(0, 0, this.viewport, this.viewport);
        if (!this.image) return;
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(
            this.image,
            this.offsetX,
            this.offsetY,
            this.image.naturalWidth * this.scale,
            this.image.naturalHeight * this.scale
        );
    };

    Cropper.prototype.render = function (side) {
        var canvas = document.createElement('canvas');
        canvas.width = side;
        canvas.height = side;
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, side, side);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        var factor = side / this.viewport;
        ctx.drawImage(
            this.image,
            this.offsetX * factor,
            this.offsetY * factor,
            this.image.naturalWidth * this.scale * factor,
            this.image.naturalHeight * this.scale * factor
        );
        return canvas;
    };

    Cropper.prototype.apply = function () {
        var self = this;
        if (!this.image) return;
        this.btnApply.disabled = true;
        this.feedback('Procesando imagen…', 'info');

        var sides = [this.size, 900, 800, 700, 600, 500, 400];
        var conAlfa = null;

        var jpegFallback = function (best) {
            // Sin transparencia: JPG sobre fondo blanco hasta cumplir el limite.
            var qualities = [0.9, 0.82, 0.75, 0.65, 0.55];
            var jpegSides = [self.size, 900, 800, 700, 600];

            var tryJpeg = function (si, qi, mejor) {
                if (si >= jpegSides.length) {
                    self.finish(mejor || best);
                    return;
                }
                if (qi >= qualities.length) {
                    tryJpeg(si + 1, 0, mejor);
                    return;
                }
                var side = jpegSides[si];
                var base = self.render(side);
                var plano = document.createElement('canvas');
                plano.width = side;
                plano.height = side;
                var ctx = plano.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, side, side);
                ctx.drawImage(base, 0, 0);
                canvasToBlob(plano, 'image/jpeg', qualities[qi]).then(function (blob) {
                    var cand = { blob: blob, side: side, canvas: plano, ext: 'jpg', mime: 'image/jpeg' };
                    if (blob && blob.size <= self.maxBytes) {
                        self.finish(cand);
                        return;
                    }
                    var nuevoMejor = (!mejor || (blob && blob.size < mejor.blob.size)) ? cand : mejor;
                    tryJpeg(si, qi + 1, nuevoMejor);
                });
            };

            tryJpeg(0, 0, null);
        };

        var step = function (index, best) {
            if (index >= sides.length) {
                if (conAlfa === false) {
                    jpegFallback(best);
                } else {
                    self.finish(best);
                }
                return;
            }
            var canvas = self.render(sides[index]);
            if (conAlfa === null) conAlfa = canvasHasAlpha(canvas);
            canvasToBlob(canvas, 'image/png').then(function (blob) {
                var candidato = { blob: blob, side: sides[index], canvas: canvas, ext: 'png', mime: 'image/png' };
                if (blob && blob.size <= self.maxBytes) {
                    self.finish(candidato);
                    return;
                }
                var mejor = (!best || (blob && blob.size < best.blob.size)) ? candidato : best;
                step(index + 1, mejor);
            });
        };

        step(0, null);
    };

    Cropper.prototype.finish = function (candidato) {
        this.btnApply.disabled = false;
        if (!candidato || !candidato.blob) {
            this.feedback('No se pudo procesar la imagen. Intenta con otra.', 'error');
            return;
        }

        var ext = candidato.ext || 'png';
        var mime = candidato.mime || 'image/png';
        var nombre = 'imagen_' + Date.now() + '.' + ext;
        var file;
        try {
            file = new File([candidato.blob], nombre, { type: mime });
        } catch (e) {
            file = candidato.blob;
            file.name = nombre;
        }

        if (window.DataTransfer && this.input.files !== undefined) {
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                this.internalChange = true;
                this.input.files = dt.files;
            } catch (e) {
                this.internalChange = false;
            }
        }

        var dataUrl = candidato.canvas.toDataURL(mime, 0.92);
        this.setPreview(dataUrl);
        this.panel.style.display = 'none';
        this.processed = {
            file: file,
            size: candidato.blob.size,
            side: candidato.side,
            dataUrl: dataUrl,
            format: ext.toUpperCase(),
            canReopen: true
        };

        this.feedback('Listo: ' + candidato.side + ' × ' + candidato.side + ' px, ' +
            formatBytes(candidato.blob.size) + ' (' + ext.toUpperCase() + ').' +
            '<br>Puedes pulsar <strong>Editar recorte</strong> si quieres ajustarla de nuevo.', 'ok');

        if (this.opts.onReady) this.opts.onReady(this.processed);
    };

    Cropper.prototype.reset = function (clearInput) {
        this.image = null;
        this.processed = null;
        this.sourceName = null;
        this.panel.style.display = 'none';
        this.setPreview(null);
        this.feedback(null);
        if (clearInput) {
            this.internalChange = true;
            this.input.value = '';
            this.internalChange = false;
        }
        if (this.opts.onClear) this.opts.onClear();
    };

    global.SquareCropper = {
        attach: function (options) {
            return new Cropper(options);
        }
    };
})(window);
