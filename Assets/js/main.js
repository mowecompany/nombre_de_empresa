/* global Swal */
(function () {
    const loadingState = {
        requests: 0,
        minVisibleAt: 0,
        hideTimer: null,
        failSafeTimer: null,
        initialized: false,
    };

    const MIN_VISIBLE_MS = 80;
    const FAILSAFE_MS = 8000;
    const INACTIVITY_TIMEOUT_MS = 60 * 60 * 1000; // 1 hora
    let inactivityTimeoutId = null;

    function getLogoutUrl() {
        if (typeof base_url !== 'undefined' && base_url) {
            return String(base_url).replace(/\/$/, '') + '/Controllers/cerrar_sesion.php';
        }

        const origin = window.location.origin;
        const pathSegments = window.location.pathname.split('/').filter(Boolean);
        let basePath = '';
        if (pathSegments.length > 0) {
            const firstSegment = pathSegments[0];
            if (!firstSegment.includes('.php') && firstSegment !== 'Controllers' && firstSegment !== 'Assets' && firstSegment !== 'Views') {
                basePath = '/' + firstSegment;
            }
        }
        return origin + basePath + '/Controllers/cerrar_sesion.php';
    }

    function handleInactivityLogout() {
        const logoutUrl = getLogoutUrl();
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'warning',
                title: 'SESIÓN CERRADA POR INACTIVIDAD',
                html: '<p>Por motivos de seguridad no hubo actividad durante 1 hora y la sesión ha sido bloqueada. Mantén esta ventana abierta hasta que inicies sesión de nuevo.</p>',
                confirmButtonText: 'VOLVER A INICIAR SESIÓN',
                confirmButtonColor: '#2f4a5a',
                showCloseButton: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
            }).then(() => {
                window.location.href = logoutUrl;
            });
            return;
        }

        alert('La sesión se cerró por inactividad. Serás redirigido al login.');
        window.location.href = logoutUrl;
    }

    function resetInactivityTimer() {
        if (inactivityTimeoutId) {
            clearTimeout(inactivityTimeoutId);
        }
        inactivityTimeoutId = window.setTimeout(handleInactivityLogout, INACTIVITY_TIMEOUT_MS);
    }

    function getLoadingEl() {
        return document.getElementById('divLoading');
    }

    function setLoadingVisible(visible) {
        const el = getLoadingEl();
        if (!el) return;

        if (visible) {
            el.style.display = 'flex';
            el.classList.add('is-active');
            loadingState.minVisibleAt = Date.now() + MIN_VISIBLE_MS;
            return;
        }

        const wait = Math.max(0, loadingState.minVisibleAt - Date.now());
        if (loadingState.hideTimer) {
            clearTimeout(loadingState.hideTimer);
            loadingState.hideTimer = null;
        }
        loadingState.hideTimer = window.setTimeout(() => {
            const target = getLoadingEl();
            if (!target) return;
            target.classList.remove('is-active');
            target.style.display = 'none';
        }, wait);
    }

    function beginLoading() {
        loadingState.requests += 1;
        setLoadingVisible(true);

        if (loadingState.failSafeTimer) {
            clearTimeout(loadingState.failSafeTimer);
            loadingState.failSafeTimer = null;
        }

        loadingState.failSafeTimer = window.setTimeout(() => {
            loadingState.requests = 0;
            setLoadingVisible(false);
        }, FAILSAFE_MS);
    }

    function endLoading() {
        loadingState.requests = Math.max(0, loadingState.requests - 1);
        if (loadingState.requests === 0) {
            if (loadingState.failSafeTimer) {
                clearTimeout(loadingState.failSafeTimer);
                loadingState.failSafeTimer = null;
            }
            setLoadingVisible(false);
        }
    }

    function normalizeUrl(rawUrl) {
        try {
            return new URL(String(rawUrl || ''), window.location.href);
        } catch (_e) {
            return null;
        }
    }

    function shouldHandleNavigation(anchor) {
        if (!anchor) return false;
        const href = (anchor.getAttribute('href') || '').trim();
        if (!href || href === '#' || href.startsWith('javascript:')) return false;
        if (href.startsWith('mailto:') || href.startsWith('tel:')) return false;
        if (anchor.hasAttribute('download')) return false;
        if ((anchor.getAttribute('target') || '').toLowerCase() === '_blank') return false;

        const toUrl = normalizeUrl(href);
        if (!toUrl) return false;
        if (toUrl.origin !== window.location.origin) return false;

        const sameDoc = toUrl.pathname === window.location.pathname
            && toUrl.search === window.location.search
            && toUrl.hash !== '';
        if (sameDoc) return false;

        return true;
    }

    function shouldTrackRequest(url) {
        const parsed = normalizeUrl(url);
        if (!parsed) return true;
        const protocol = parsed.protocol.toLowerCase();
        if (protocol === 'data:' || protocol === 'blob:') return false;
        return true;
    }

    function isSilentRequest(resource, init) {
        try {
            if (init && init.headers) {
                if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
                    return init.headers.get('X-Silent-Request') === '1';
                }
                if (Array.isArray(init.headers)) {
                    return init.headers.some(([key, value]) => String(key).toLowerCase() === 'x-silent-request' && String(value) === '1');
                }
                if (typeof init.headers === 'object') {
                    return String(init.headers['X-Silent-Request'] || init.headers['x-silent-request'] || '') === '1';
                }
            }

            if (typeof Request !== 'undefined' && resource instanceof Request) {
                return resource.headers.get('X-Silent-Request') === '1';
            }
        } catch (_e) {
            return false;
        }

        return false;
    }

    function installRequestHooks() {
        if (loadingState.initialized) return;
        loadingState.initialized = true;

        if (typeof window.fetch === 'function') {
            const nativeFetch = window.fetch.bind(window);
            window.fetch = function (resource, init) {
                const url = resource instanceof Request ? resource.url : resource;
                const track = shouldTrackRequest(url) && !isSilentRequest(resource, init);
                if (track) beginLoading();
                return nativeFetch(resource, init)
                    .finally(() => {
                        if (track) endLoading();
                    });
            };
        }

        const nativeXhrOpen = XMLHttpRequest.prototype.open;
        const nativeXhrSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url, async, user, password) {
            this.__trackLoading = async !== false && shouldTrackRequest(url);
            return nativeXhrOpen.call(this, method, url, async, user, password);
        };

        XMLHttpRequest.prototype.send = function (body) {
            if (this.__trackLoading && this.__skipGlobalLoading !== true) {
                beginLoading();
                const finalize = () => endLoading();
                this.addEventListener('loadend', finalize, { once: true });
                this.addEventListener('error', finalize, { once: true });
                this.addEventListener('abort', finalize, { once: true });
                this.addEventListener('timeout', finalize, { once: true });
            }
            return nativeXhrSend.call(this, body);
        };

        if (window.jQuery && typeof window.jQuery === 'function') {
            window.jQuery(document)
                .ajaxStart(beginLoading)
                .ajaxStop(() => {
                    loadingState.requests = 1;
                    endLoading();
                })
                .ajaxError(handleAjaxError);
        }
    }

    window.showLoading = function () {
        beginLoading();
    };

    window.hideLoading = function () {
        loadingState.requests = 1;
        endLoading();
    };

    // Función para mostrar mensajes de error
    window.showError = function (message) {
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
            });
            return;
        }
        window.alert(message);
    };

    // Función para mostrar mensajes de éxito
    window.showSuccess = function (message) {
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'success',
                title: 'Exito',
                text: message,
            });
            return;
        }
        window.alert(message);
    };

    // Función para confirmar acciones
    window.confirmAction = function (message) {
        if (typeof Swal !== 'undefined' && Swal.fire) {
            return Swal.fire({
                title: 'Estas seguro?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Si, continuar',
                cancelButtonText: 'Cancelar',
            });
        }
        return Promise.resolve({ isConfirmed: window.confirm(message) });
    };

    // Función para formatear moneda
    window.formatMoney = function (amount) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    // Función para validar formularios
    window.validateForm = function (formId) {
        const form = document.getElementById(formId);
        if (!form) return false;

        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach((field) => {
            if (!String(field.value || '').trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return isValid;
    };

    // Helper to clear form inputs
    window.clearForm = function (formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.reset();
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            input.classList.remove('is-invalid');
        });
    };

    // Función para manejar errores de AJAX
    function handleAjaxError(_xhr, _status, error) {
        console.error('Error AJAX:', error);
        window.showError('Ha ocurrido un error. Por favor, intente nuevamente.');
    }

    // Inicialización de componentes
    document.addEventListener('DOMContentLoaded', function () {
        installRequestHooks();

        if (window.jQuery && typeof window.jQuery === 'function') {
            window.jQuery('[data-toggle="tooltip"]').tooltip();
            window.jQuery('[data-toggle="popover"]').popover();
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.hasAttribute('data-no-loading')) return;
            if ((form.getAttribute('target') || '').toLowerCase() === '_blank') return;

            window.setTimeout(() => {
                if (event.defaultPrevented) return;
                beginLoading();
            }, 0);
        });

        document.addEventListener('click', function (event) {
            const anchor = event.target.closest('a[href]');
            if (!anchor) return;
            if (!shouldHandleNavigation(anchor)) return;

            // Espera al final del ciclo del evento para detectar preventDefault en handlers de la app.
            window.setTimeout(() => {
                if (event.defaultPrevented) return;
                beginLoading();
            }, 0);
        }, true);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                loadingState.requests = 0;
                setLoadingVisible(false);
            }
        });

        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];
        activityEvents.forEach((eventName) => {
            document.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });

        resetInactivityTimer();
    });

    window.addEventListener('load', function () {
        loadingState.requests = 1;
        endLoading();
    });

    window.addEventListener('pageshow', function () {
        loadingState.requests = 0;
        setLoadingVisible(false);
    });
})();