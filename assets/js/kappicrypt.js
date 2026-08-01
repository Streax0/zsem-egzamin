/**
 * KappiCrypt v2.1 - End-to-End Hybrid Encryption System
 * Standard: RSA-2048 (OAEP) + AES-256-GCM + Anti-Replay Nonce & Timestamp
 */
class KappiCrypt {
    static publicKey = null;
    static isReady = false;

    static async init() {
        try {
            const res = await fetch('../ajax/kappicrypt_pk.php?_t=' + Date.now());
            const data = await res.json();
            if (data.status === 'success' && data.publicKey) {
                this.publicKey = await this.importPublicKey(data.publicKey);
                this.isReady = true;
                
                // Styled Console Badge
                console.log(
                    '%c KappiCrypt %c RSA-2048 + AES-256-GCM + Anti-Replay Active ',
                    'background: #4f46e5; color: #ffffff; font-weight: 800; padding: 4px 8px; border-radius: 6px 0 0 6px;',
                    'background: #0f172a; color: #34d399; font-weight: 800; padding: 4px 8px; border-radius: 0 6px 6px 0; border: 1px solid rgba(52, 211, 153, 0.3);'
                );

                this.updateFormSecurityBadges();
            }
        } catch (e) {
            console.error('[KappiCrypt] Initialization failed:', e);
        }
    }

    static async importPublicKey(pem) {
        // Strip PEM headers
        const pemHeader = "-----BEGIN PUBLIC KEY-----";
        const pemFooter = "-----END PUBLIC KEY-----";
        const pemContents = pem.substring(
            pem.indexOf(pemHeader) + pemHeader.length,
            pem.indexOf(pemFooter)
        ).replace(/\s/g, '');
        
        const binaryDerString = window.atob(pemContents);
        const binaryDer = new ArrayBuffer(binaryDerString.length);
        const view = new Uint8Array(binaryDer);
        for (let i = 0; i < binaryDerString.length; i++) {
            view[i] = binaryDerString.charCodeAt(i);
        }

        return await window.crypto.subtle.importKey(
            "spki",
            binaryDer,
            {
                name: "RSA-OAEP",
                hash: "SHA-1",
            },
            true,
            ["encrypt"]
        );
    }

    static async encryptData(plainObject) {
        if (!this.isReady) throw new Error("System szyfrowania nie jest gotowy.");

        // Inject Anti-Replay metadata
        plainObject._ts = Date.now();
        plainObject._nonce = window.crypto.randomUUID ? window.crypto.randomUUID() : (Math.random().toString(36).substring(2) + Date.now().toString(36));

        // 1. Generate AES-GCM Key (256 bits)
        const aesKey = await window.crypto.subtle.generateKey(
            { name: "AES-GCM", length: 256 },
            true,
            ["encrypt", "decrypt"]
        );

        // 2. Export AES Key to raw bytes
        const exportedAesKey = await window.crypto.subtle.exportKey("raw", aesKey);

        // 3. Encrypt AES Key with RSA Public Key
        const wrappedKeyBuffer = await window.crypto.subtle.encrypt(
            { name: "RSA-OAEP" },
            this.publicKey,
            exportedAesKey
        );

        // 4. Encrypt Payload with AES-GCM
        const iv = window.crypto.getRandomValues(new Uint8Array(12));
        const encodedData = new TextEncoder().encode(JSON.stringify(plainObject));

        const encryptedDataBuffer = await window.crypto.subtle.encrypt(
            { name: "AES-GCM", iv: iv },
            aesKey,
            encodedData
        );

        // AES-GCM appends a 16-byte authentication tag
        const encryptedBytes = new Uint8Array(encryptedDataBuffer);
        const ct = encryptedBytes.slice(0, -16);
        const tag = encryptedBytes.slice(-16);

        // 5. Return Encoded JSON Payload
        return JSON.stringify({
            wrappedKey: this.arrayBufferToBase64(wrappedKeyBuffer),
            iv: this.arrayBufferToBase64(iv),
            ct: this.arrayBufferToBase64(ct),
            tag: this.arrayBufferToBase64(tag)
        });
    }

    static arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    static updateFormSecurityBadges() {
        document.querySelectorAll('form[data-kappicrypt="true"]').forEach(form => {
            let badge = form.querySelector('.kappicrypt-security-badge');
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'kappicrypt-security-badge mt-3 text-center';
                badge.innerHTML = `
                    <span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.78rem; font-weight: 700;">
                        <i class="bi bi-shield-lock-fill"></i> Połączenie zaszyfrowane (KappiCrypt RSA-2048 + AES-256)
                    </span>
                `;
                form.appendChild(badge);
            }
        });
    }

    static bindForms() {
        document.querySelectorAll('form[data-kappicrypt="true"]').forEach(form => {
            if (form.dataset.kappiBound) return;
            form.dataset.kappiBound = "true";

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!this.isReady) {
                    if (window.appNotice) {
                        window.appNotice('System szyfrowania nie jest gotowy. Odśwież stronę.', 'danger');
                    } else {
                        console.error('[KappiCrypt] System not ready.');
                    }
                    return;
                }

                // Gather form data
                const formData = new FormData(form);
                const plainObject = {};
                formData.forEach((value, key) => {
                    plainObject[key] = value;
                });

                // Get submitter button if present
                const submitter = e.submitter;
                if (submitter && submitter.name) {
                    plainObject[submitter.name] = submitter.value;
                }

                const btn = form.querySelector('button[type="submit"]');
                let origText = '';
                if (btn) {
                    origText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span><i class="bi bi-shield-lock-fill me-1"></i> Szyfrowanie...';
                }

                try {
                    const payload = await this.encryptData(plainObject);

                    // Append encrypted payload hidden field
                    let hidden = form.querySelector('input[name="kappicrypt_payload"]');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'kappicrypt_payload';
                        form.appendChild(hidden);
                    }
                    hidden.value = payload;

                    // Native submission
                    form.submit();
                } catch (err) {
                    console.error('[KappiCrypt] Encryption error:', err);
                    if (window.appNotice) {
                        window.appNotice('Błąd szyfrowania: ' + err.message, 'danger');
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    }
                }
            });
        });
    }
}

// Auto-init on page load
document.addEventListener('DOMContentLoaded', () => {
    KappiCrypt.init().then(() => {
        KappiCrypt.bindForms();
    });
});
