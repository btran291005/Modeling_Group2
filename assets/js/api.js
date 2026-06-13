'use strict';
 
const Api = (() => {
 
    // ──────────────────────────────────────────────────────────
    // PRIVATE
    // ──────────────────────────────────────────────────────────
 
    /**
     * Base URL cho thư mục api/ — tự động tính từ gốc site.
     * Ví dụ: http://localhost/gadget-valuation/api/
     *
     * Nếu deploy dưới subfolder, đặt: const BASE = '/ten-subfolder/api/';
     */
    const BASE = (() => {
        // Lấy từ <meta name="api-base" content="/api/"> nếu có
        const meta = document.querySelector('meta[name="api-base"]');
        if (meta) return meta.content.replace(/\/?$/, '/');
 
        // Fallback: tự tính từ pathname (lên 1 cấp so với admin/ hoặc staff/)
        const parts = window.location.pathname.split('/').filter(Boolean);
        // Bỏ segment cuối (admin, staff) để lấy gốc
        if (['admin', 'staff'].includes(parts[parts.length - 2] ?? '')) {
            parts.splice(-2, 1);
        } else if (['admin', 'staff'].includes(parts[parts.length - 1] ?? '')) {
            parts.pop();
        }
        return '/' + parts.join('/') + (parts.length ? '/' : '') + 'api/';
    })();
 
    /**
     * Hàm fetch nội bộ — tất cả public method đều qua đây.
     *
     * @param {string} endpoint  Tên file, VD: "valuation_api.php"
     * @param {string} action    Giá trị của ?action=
     * @param {Object} options   Tùy chọn bổ sung { method, body, signal }
     * @returns {Promise<{ok, data, msg}>}
     */
    async function _request(endpoint, action, options = {}) {
        const url = new URL(BASE + endpoint, window.location.origin);
        url.searchParams.set('action', action);
 
        const fetchOptions = {
            method:      options.method ?? 'GET',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal:      options.signal ?? null,
        };
 
        // Body chỉ dùng với POST
        if (options.body !== undefined) {
            fetchOptions.method = 'POST';
            if (options.body instanceof FormData) {
                fetchOptions.body = options.body;
                // Không set Content-Type — browser tự xử lý boundary cho FormData
            } else if (typeof options.body === 'object') {
                fetchOptions.body = JSON.stringify(options.body);
                fetchOptions.headers['Content-Type'] = 'application/json; charset=utf-8';
            } else {
                fetchOptions.body = options.body;
            }
        }
 
        try {
            const response = await fetch(url.toString(), fetchOptions);
 
            // HTTP lỗi nhưng vẫn parse JSON nếu server gửi body
            let json;
            try {
                json = await response.json();
            } catch {
                return {
                    ok:   false,
                    data: null,
                    msg:  `HTTP ${response.status}: Server không trả về JSON hợp lệ.`,
                };
            }
 
            // Nếu server trả HTTP 401/403 nhưng không redirect, tự redirect
            if (response.status === 401) {
                window.location.href = '/index.php?reason=unauthenticated';
                return { ok: false, data: null, msg: 'Phiên đăng nhập hết hạn.' };
            }
 
            return json; // { ok, data, msg }
 
        } catch (err) {
            if (err.name === 'AbortError') {
                return { ok: false, data: null, msg: 'Request đã bị hủy.' };
            }
            console.error('[Api] Network error:', err);
            return {
                ok:   false,
                data: null,
                msg:  'Không thể kết nối server. Kiểm tra lại mạng.',
            };
        }
    }
 
    // ──────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────
 
    return {
 
        /**
         * GET request.
         *
         * Ví dụ: const res = await Api.get('account_api.php', 'list', { page: 1 });
         *
         * @param {string} endpoint   Tên file api (không cần path đầy đủ)
         * @param {string} action     Giá trị ?action=
         * @param {Object} params     Query string bổ sung (optional)
         * @param {AbortSignal} signal  Để cancel request (optional)
         * @returns {Promise<{ok, data, msg}>}
         */
        async get(endpoint, action, params = {}, signal = null) {
            // Build URLSearchParams cho params bổ sung (ngoài action)
            const url = new URL(BASE + endpoint, window.location.origin);
            url.searchParams.set('action', action);
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined) url.searchParams.set(k, v);
            });
 
            const fetchOptions = {
                method:      'GET',
                headers:     { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal,
            };
 
            try {
                const response = await fetch(url.toString(), fetchOptions);
                if (response.status === 401) {
                    window.location.href = '/index.php?reason=unauthenticated';
                    return { ok: false, data: null, msg: 'Phiên đăng nhập hết hạn.' };
                }
                let json;
                try { json = await response.json(); }
                catch { return { ok: false, data: null, msg: `HTTP ${response.status}: Không parse được JSON.` }; }
                return json;
            } catch (err) {
                if (err.name === 'AbortError') return { ok: false, data: null, msg: 'Request đã bị hủy.' };
                console.error('[Api.get]', err);
                return { ok: false, data: null, msg: 'Lỗi kết nối mạng.' };
            }
        },
 
        /**
         * POST request với FormData.
         *
         * Ví dụ:
         *   const fd = new FormData();
         *   fd.append('email', 'a@b.com');
         *   const res = await Api.post('account_api.php', 'create', fd);
         *
         * @param {string}      endpoint  Tên file api
         * @param {string}      action    Giá trị ?action=
         * @param {FormData|Object|string} body  Dữ liệu gửi lên
         * @param {AbortSignal} signal    Để cancel (optional)
         * @returns {Promise<{ok, data, msg}>}
         */
        async post(endpoint, action, body = null, signal = null) {
            return _request(endpoint, action, { body: body ?? new FormData(), signal });
        },
 
        /**
         * POST shorthand khi dữ liệu là Object đơn giản (không cần upload file).
         * Server nhận JSON body → dùng json_decode(file_get_contents('php://input'))
         *
         * @param {string} endpoint
         * @param {string} action
         * @param {Object} data
         * @returns {Promise<{ok, data, msg}>}
         */
        async postJson(endpoint, action, data = {}) {
            return _request(endpoint, action, { body: data });
        },
 
        /**
         * Upload file (ảnh, v.v.) kèm các field khác.
         * Body là FormData — browser tự handle multipart.
         *
         * @param {string}   endpoint
         * @param {string}   action
         * @param {FormData} formData
         * @param {Function} onProgress   Callback(percent: number) — optional
         * @returns {Promise<{ok, data, msg}>}
         */
        async upload(endpoint, action, formData, onProgress = null) {
            // Nếu không cần progress tracking, dùng post() thường
            if (!onProgress) {
                return this.post(endpoint, action, formData);
            }
 
            // XMLHttpRequest để có onprogress event
            return new Promise((resolve) => {
                const url = new URL(BASE + endpoint, window.location.origin);
                url.searchParams.set('action', action);
 
                const xhr = new XMLHttpRequest();
                xhr.open('POST', url.toString());
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.withCredentials = true;
 
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        onProgress(Math.round((e.loaded / e.total) * 100));
                    }
                };
 
                xhr.onload = () => {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch {
                        resolve({ ok: false, data: null, msg: 'Server trả về dữ liệu không hợp lệ.' });
                    }
                };
 
                xhr.onerror = () => resolve({ ok: false, data: null, msg: 'Lỗi kết nối mạng.' });
                xhr.send(formData);
            });
        },
 
        // ──────────────────────────────────────────────────────
        // UTILITY — dùng ở mọi nơi trong JS
        // ──────────────────────────────────────────────────────
 
        /**
         * Format số VNĐ.
         * Ví dụ: Api.vnd(15000000) → "15.000.000 ₫"
         */
        vnd(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style:                 'currency',
                currency:              'VND',
                maximumFractionDigits: 0,
            }).format(amount ?? 0);
        },
 
        /**
         * Escape HTML để tránh XSS khi render dữ liệu từ server vào DOM.
         */
        esc(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str ?? ''));
            return d.innerHTML;
        },
 
        /**
         * Tạo AbortController để cancel request khi cần.
         * Dùng khi user gõ search nhanh (debounce + cancel request cũ).
         *
         * Ví dụ:
         *   const ctrl = Api.abort();
         *   const res  = await Api.get('...', '...', {}, ctrl.signal);
         *   // Sau đó nếu cần hủy: ctrl.abort();
         */
        abort() {
            return new AbortController();
        },
    };
 
})(); // End IIFE — Api object có sẵn toàn cục