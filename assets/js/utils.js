/**
 * ZSEM Tech - Core Utility Functions
 */
(function (window) {
    'use strict';

    /**
     * Safely escapes HTML special characters (including quotes) to prevent XSS.
     * @param {*} value
     * @returns {string}
     */
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.AppUtils = window.AppUtils || {};
    window.AppUtils.escapeHtml = escapeHtml;
    window.escapeHtml = escapeHtml;
})(window);
