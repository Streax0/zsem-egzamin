/**
 * ZSEM Tech — Offline PWA Engine & IndexedDB Sync Manager
 * Manages local test results storage during network disconnection
 * and transparently synchronizes progress and XP upon reconnection.
 */
(function() {
    'use strict';

    const DB_NAME = 'ZsemTechOfflineDB';
    const DB_VERSION = 1;
    const STORE_RESULTS = 'offline_results';

    class OfflineManager {
        constructor() {
            this.db = null;
            this.isSyncing = false;
            this.init();
        }

        async init() {
            try {
                this.db = await this.openDatabase();
                this.setupNetworkListeners();
                this.setupServiceWorkerListener();
                this.updateNetworkUI();
                
                // If online at start, attempt sync of any residual pending records
                if (navigator.onLine) {
                    setTimeout(() => this.syncAll(), 2500);
                }
            } catch (err) {
                console.warn('[OfflineEngine] Initialization warning:', err);
            }
        }

        openDatabase() {
            return new Promise((resolve, reject) => {
                if (!window.indexedDB) {
                    return reject(new Error('IndexedDB not supported'));
                }
                const request = window.indexedDB.open(DB_NAME, DB_VERSION);
                request.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains(STORE_RESULTS)) {
                        db.createObjectStore(STORE_RESULTS, { keyPath: 'id', autoIncrement: true });
                    }
                };
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        }

        async saveOfflineResult(resultData) {
            if (!this.db) this.db = await this.openDatabase();
            return new Promise((resolve, reject) => {
                const tx = this.db.transaction([STORE_RESULTS], 'readwrite');
                const store = tx.objectStore(STORE_RESULTS);
                const record = {
                    ...resultData,
                    client_saved_at: new Date().toISOString(),
                    synced: false
                };
                const req = store.add(record);
                req.onsuccess = () => {
                    this.showToast('📡 Zapisano wynik offline. Zostanie zsynchronizowany po powrocie internetu.', 'info');
                    this.updateNetworkUI();
                    resolve(req.result);
                };
                req.onerror = () => reject(req.error);
            });
        }

        async getPendingResults() {
            if (!this.db) this.db = await this.openDatabase();
            return new Promise((resolve, reject) => {
                const tx = this.db.transaction([STORE_RESULTS], 'readonly');
                const store = tx.objectStore(STORE_RESULTS);
                const req = store.getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        }

        async clearPendingResults(ids) {
            if (!this.db || !Array.isArray(ids) || ids.length === 0) return;
            return new Promise((resolve, reject) => {
                const tx = this.db.transaction([STORE_RESULTS], 'readwrite');
                const store = tx.objectStore(STORE_RESULTS);
                ids.forEach(id => store.delete(id));
                tx.oncomplete = () => {
                    this.updateNetworkUI();
                    resolve();
                };
                tx.onerror = () => reject(tx.error);
            });
        }

        async syncAll() {
            if (this.isSyncing || !navigator.onLine) return;
            try {
                const pending = await this.getPendingResults();
                if (!pending || pending.length === 0) return;

                this.isSyncing = true;
                const syncUrl = (window.__ZSEM_BASE_URL || '') + 'ajax/sync_offline_progress.php';
                const response = await fetch(syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        batch: pending,
                        csrf_token: window.__ZSEM_CSRF_TOKEN || ''
                    })
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);
                const result = await response.json();

                if (result.success) {
                    const ids = pending.map(p => p.id);
                    await this.clearPendingResults(ids);
                    const xpMsg = result.total_xp_awarded > 0 ? ` (+${result.total_xp_awarded} XP)` : '';
                    this.showToast(`✅ Zsynchronizowano ${result.synced_count} wynik(ów) offline!${xpMsg}`, 'success');
                }
            } catch (err) {
                console.warn('[OfflineEngine] Sync attempt failed, will retry later:', err);
            } finally {
                this.isSyncing = false;
            }
        }

        setupNetworkListeners() {
            window.addEventListener('online', () => {
                this.updateNetworkUI();
                this.showToast('🟢 Przywrócono połączenie internetowe. Synchronizuję dane...', 'info');
                this.syncAll();
            });

            window.addEventListener('offline', () => {
                this.updateNetworkUI();
                this.showToast('📡 Brak połączenia internetowego. Tryb offline aktywny.', 'warning');
            });
        }

        setupServiceWorkerListener() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'TRIGGER_OFFLINE_SYNC') {
                        this.syncAll();
                    }
                });
            }
        }

        async updateNetworkUI() {
            let pill = document.getElementById('zsem-network-status-pill');
            const pending = await this.getPendingResults().catch(() => []);
            const pendingCount = pending.length;

            if (!pill) {
                pill = document.createElement('div');
                pill.id = 'zsem-network-status-pill';
                pill.style.position = 'fixed';
                pill.style.bottom = '16px';
                pill.style.right = '16px';
                pill.style.zIndex = '9999';
                pill.style.fontSize = '12px';
                pill.style.fontWeight = '600';
                pill.style.padding = '6px 14px';
                pill.style.borderRadius = '30px';
                pill.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
                pill.style.transition = 'all 0.3s ease';
                pill.style.display = 'none';
                document.body.appendChild(pill);
            }

            if (!navigator.onLine) {
                pill.style.display = 'block';
                pill.style.background = '#e11d48';
                pill.style.color = '#ffffff';
                pill.innerHTML = `<i class="bi bi-wifi-off me-1"></i>Tryb Offline ${pendingCount > 0 ? `(${pendingCount} do synchronizacji)` : ''}`;
            } else if (pendingCount > 0) {
                pill.style.display = 'block';
                pill.style.background = '#f59e0b';
                pill.style.color = '#0f172a';
                pill.innerHTML = `<i class="bi bi-arrow-repeat me-1 spin"></i>Synchronizacja (${pendingCount})...`;
                pill.onclick = () => this.syncAll();
            } else {
                pill.style.display = 'none';
            }
        }

        showToast(message, type = 'info') {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast(message, type);
                return;
            }
            console.log(`[PWA Notice ${type}]:`, message);
        }
    }

    window.ZsemOfflineManager = new OfflineManager();
})();
