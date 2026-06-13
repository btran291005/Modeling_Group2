// ============================================================
// FILE: assets/js/api.js
// Class Wrapper chuyên xử lý việc gọi API (Fetch)
// Dùng chung cho toàn bộ dự án
// ============================================================
'use strict';

const Api = (function () {

    // Hàm xử lý lỗi chung
    function handleHttpError(response) {
        if (!response.ok) {
            console.error(`HTTP error! Status: ${response.status}`);
        }
        return response;
    }

    return {
        /**
         * Gửi request GET
         * @param {string} url - Đường dẫn file API (VD: '../api/account_api.php')
         * @param {string} action - Tên action
         * @param {object} params - Các tham số thêm (nếu có)
         */
        async get(url, action, params = {}) {
            try {
                const query = new URLSearchParams({ action, ...params }).toString();
                const res = await fetch(`${url}?${query}`);
                handleHttpError(res);
                return await res.json();
            } catch (err) {
                console.error('Api.get error:', err);
                return { ok: false, success: false, message: 'Lỗi kết nối mạng hoặc server.', msg: 'Lỗi kết nối.' };
            }
        },

        /**
         * Gửi request POST dạng FormData (Dùng cho form hoặc upload)
         */
        async post(url, action, formData = new FormData()) {
            try {
                const res = await fetch(`${url}?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                handleHttpError(res);
                return await res.json();
            } catch (err) {
                console.error('Api.post error:', err);
                return { ok: false, success: false, message: 'Lỗi kết nối mạng hoặc server.', msg: 'Lỗi kết nối.' };
            }
        },

        /**
         * Gửi request POST dạng JSON (Dùng cho các payload object js)
         */
        async postJson(url, action, dataObj = {}) {
            try {
                const res = await fetch(`${url}?action=${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dataObj)
                });
                handleHttpError(res);
                return await res.json();
            } catch (err) {
                console.error('Api.postJson error:', err);
                return { ok: false, success: false, message: 'Lỗi kết nối mạng hoặc server.', msg: 'Lỗi kết nối.' };
            }
        },

        /**
         * Escape HTML để chống XSS khi render JS
         */
        esc(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Format tiền tệ VNĐ
         */
        vnd(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND',
                maximumFractionDigits: 0
            }).format(amount ?? 0);
        }
    };
})();