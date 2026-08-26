/**
 * ZSEM Tech - Shared WebAuthn / Passkeys Utility Library
 */
(function (window) {
    'use strict';

    function parseWebAuthnBinary(str) {
        if (typeof str !== 'string') return str;
        let b64 = str;
        if (str.startsWith('=?BINARY?B?') && str.endsWith('?=')) {
            b64 = str.substring(11, str.length - 2);
        }
        b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
        const padding = '=='.slice(0, (4 - b64.length % 4) % 4);
        b64 += padding;
        const raw = window.atob(b64);
        const buffer = new ArrayBuffer(raw.length);
        const view = new Uint8Array(buffer);
        for (let i = 0; i < raw.length; i++) {
            view[i] = raw.charCodeAt(i);
        }
        return buffer;
    }

    function base64urlToBuffer(baseurl64) {
        return parseWebAuthnBinary(baseurl64);
    }

    function bufferToBase64url(buffer) {
        const byteView = new Uint8Array(buffer);
        let str = '';
        for (const charCode of byteView) {
            str += String.fromCharCode(charCode);
        }
        const base64 = window.btoa(str);
        return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    function bufferToBase64(buffer) {
        const byteView = new Uint8Array(buffer);
        let str = '';
        for (const charCode of byteView) {
            str += String.fromCharCode(charCode);
        }
        return window.btoa(str);
    }

    window.WebAuthnUtils = {
        parseWebAuthnBinary: parseWebAuthnBinary,
        base64urlToBuffer: base64urlToBuffer,
        bufferToBase64url: bufferToBase64url,
        bufferToBase64: bufferToBase64
    };

    // Backward compatibility aliases
    window.parseWebAuthnBinary = parseWebAuthnBinary;
    window.base64urlToBuffer = base64urlToBuffer;
    window.bufferToBase64url = bufferToBase64url;
    window.bufferToBase64 = bufferToBase64;
})(window);
