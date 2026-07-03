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
                console.log('KappiCrypt is ready for End-to-End Encryption.');
            }
        } catch (e) {
            console.error('KappiCrypt Initialization failed:', e);
        }
    }

    static async importPublicKey(pem) {
        // Strip headers
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
        if (!this.isReady) throw new Error("KappiCrypt not initialized");

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

        // AES-GCM appends a 16-byte authentication tag to the end of the ciphertext
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

    static bindForms() {
        document.querySelectorAll('form[data-kappicrypt="true"]').forEach(form => {
            // Unbind existing listeners to avoid duplicates if re-run
            if (form.dataset.kappiBound) return;
            form.dataset.kappiBound = "true";

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!this.isReady) {
                    window.appNotice('System szyfrowania nie jest gotowy. Odśwież stronę.', 'danger');
                    return;
                }

                // Gather data
                const formData = new FormData(form);
                const plainObject = {};
                formData.forEach((value, key) => {
                    plainObject[key] = value;
                });

                // Get clicked button if any (for multiple submit buttons)
                const submitter = e.submitter;
                if (submitter && submitter.name) {
                    plainObject[submitter.name] = submitter.value;
                }

                // Encrypt
                try {
                    // Show some loading state
                    const btn = form.querySelector('button[type="submit"]');
                    const origText = btn ? btn.innerHTML : '';
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="bi bi-lock"></i> Szyfrowanie...';
                    }

                    const payload = await this.encryptData(plainObject);

                    // Create hidden field
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'kappicrypt_payload';
                    hidden.value = payload;
                    form.appendChild(hidden);

                    // Remove names from original fields so they are not submitted in plaintext
                    form.querySelectorAll('input, select, textarea').forEach(input => {
                        if (input.name && input.name !== 'kappicrypt_payload' && input.name !== 'csrf_token') {
                            input.removeAttribute('name');
                        }
                    });

                    // Bypass this event listener and submit natively
                    form.submit();
                } catch (err) {
                    console.error('Błąd szyfrowania formularza:', err);
                    window.appNotice('Błąd szyfrowania: ' + err.message, 'danger');
                    
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btn.dataset.origHtml || 'Wyślij';
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
