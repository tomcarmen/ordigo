/**
 * Sistema di gestione offline per OrdiGO
 * Gestisce la sincronizzazione dei dati tra postazioni
 */

class OfflineManager {
    constructor() {
        this.dbName = 'OrdiGO_Offline';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncQueue = [];
        this.syncInProgress = false;
        
        this.init();
    }
    
    async init() {
        try {
            await this.initIndexedDB();
            this.setupEventListeners();
            this.registerServiceWorker();
            this.startPeriodicSync();
            
            console.log('[Offline] Manager initialized successfully');
        } catch (error) {
            console.error('[Offline] Initialization failed:', error);
        }
    }
    
    // Inizializzazione IndexedDB
    async initIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Store per operazioni pendenti
                if (!db.objectStoreNames.contains('pendingOperations')) {
                    const pendingStore = db.createObjectStore('pendingOperations', {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    pendingStore.createIndex('timestamp', 'timestamp');
                    pendingStore.createIndex('type', 'type');
                    pendingStore.createIndex('status', 'status');
                }
                
                // Store per cache dati
                if (!db.objectStoreNames.contains('dataCache')) {
                    const cacheStore = db.createObjectStore('dataCache', {
                        keyPath: 'key'
                    });
                    cacheStore.createIndex('lastUpdated', 'lastUpdated');
                    cacheStore.createIndex('type', 'type');
                }
                
                // Store per configurazione sync
                if (!db.objectStoreNames.contains('syncConfig')) {
                    const configStore = db.createObjectStore('syncConfig', {
                        keyPath: 'key'
                    });
                }
            };
        });
    }
    
    // Registrazione Service Worker
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                // Evita doppie registrazioni se già presente
                const existingRegs = await navigator.serviceWorker.getRegistrations();
                if (existingRegs && existingRegs.length > 0) {
                    console.log('[Offline] Service Worker già registrato');
                    return;
                }

                // Calcola base dell'app (es. /ordigo) dalla pathname
                const parts = (window.location.pathname || '/').split('/');
                // Rimuovi ultimo segmento (file) e la directory finale se presente (es. /admin)
                let base = parts.slice(0, Math.max(parts.length - 2, 1)).join('/') || '/';
                if (!base.startsWith('/')) base = '/' + base;
                const swUrl = `${window.location.origin}${base}${base.endsWith('/') ? '' : '/'}sw.js`;

                const registration = await navigator.serviceWorker.register(swUrl);
                console.log('[Offline] Service Worker registered:', registration);
                
                // Gestione aggiornamenti SW
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });
                
            } catch (error) {
                console.error('[Offline] Service Worker registration failed:', error);
            }
        }
    }
    
    // Setup event listeners
    setupEventListeners() {
        // Monitoraggio stato connessione
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showConnectionStatus('online');
            this.processSyncQueue();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showConnectionStatus('offline');
        });
        
        // Intercettazione form submissions
        document.addEventListener('submit', (event) => {
            if (!this.isOnline) {
                event.preventDefault();
                this.handleOfflineSubmission(event.target);
            }
        });
        
        // Intercettazione richieste AJAX
        this.interceptAjaxRequests();
    }
    
    // Intercettazione richieste AJAX
    interceptAjaxRequests() {
        const originalFetch = window.fetch;
        const self = this;
        
        window.fetch = async function(...args) {
            try {
                const response = await originalFetch.apply(this, args);
                return response;
            } catch (error) {
                if (!self.isOnline) {
                    return self.handleOfflineRequest(args[0], args[1]);
                }
                throw error;
            }
        };
    }
    
    // Gestione richieste offline
    async handleOfflineRequest(url, options = {}) {
        const operation = {
            url: url,
            method: options.method || 'GET',
            body: options.body,
            headers: options.headers,
            timestamp: Date.now(),
            status: 'pending',
            type: this.getOperationType(url, options.method)
        };
        
        await this.addPendingOperation(operation);
        
        // Ritorna una risposta mock per operazioni di lettura
        if (operation.method === 'GET') {
            const cachedData = await this.getCachedData(url);
            if (cachedData) {
                return new Response(JSON.stringify(cachedData.data), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' }
                });
            }
        }
        
        // Per operazioni di scrittura, ritorna conferma
        return new Response(JSON.stringify({
            success: true,
            offline: true,
            message: 'Operazione salvata per sincronizzazione'
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
        });
    }
    
    // Gestione submission form offline
    async handleOfflineSubmission(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        const operation = {
            url: form.action || window.location.href,
            method: form.method || 'POST',
            data: data,
            timestamp: Date.now(),
            status: 'pending',
            type: this.getOperationType(form.action, form.method)
        };
        
        await this.addPendingOperation(operation);
        this.showOfflineMessage('Dati salvati per sincronizzazione');
    }
    
    // Determina tipo operazione
    getOperationType(url, method) {
        if (url.includes('products')) return 'product';
        if (url.includes('orders')) return 'order';
        if (url.includes('categories')) return 'category';
        if (url.includes('customers')) return 'customer';
        return 'general';
    }
    
    // Aggiunta operazione pendente
    async addPendingOperation(operation) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pendingOperations'], 'readwrite');
            const store = transaction.objectStore('pendingOperations');
            
            const request = store.add(operation);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    // Recupero operazioni pendenti
    async getPendingOperations() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pendingOperations'], 'readonly');
            const store = transaction.objectStore('pendingOperations');
            
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    // Rimozione operazione pendente
    async removePendingOperation(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pendingOperations'], 'readwrite');
            const store = transaction.objectStore('pendingOperations');
            
            const request = store.delete(id);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
    
    // Cache dati
    async cacheData(key, data, type = 'general') {
        const cacheEntry = {
            key: key,
            data: data,
            type: type,
            lastUpdated: Date.now()
        };
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['dataCache'], 'readwrite');
            const store = transaction.objectStore('dataCache');
            
            const request = store.put(cacheEntry);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
    
    // Recupero dati cache
    async getCachedData(key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['dataCache'], 'readonly');
            const store = transaction.objectStore('dataCache');
            
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    // Processamento coda sincronizzazione
    async processSyncQueue() {
        if (this.syncInProgress || !this.isOnline) return;
        
        this.syncInProgress = true;
        
        try {
            const pendingOperations = await this.getPendingOperations();
            console.log(`[Offline] Processing ${pendingOperations.length} pending operations`);
            
            for (const operation of pendingOperations) {
                try {
                    await this.syncOperation(operation);
                    await this.removePendingOperation(operation.id);
                    console.log(`[Offline] Synced operation ${operation.id}`);
                } catch (error) {
                    console.error(`[Offline] Failed to sync operation ${operation.id}:`, error);
                }
            }
            
            if (pendingOperations.length > 0) {
                this.showSyncMessage(`${pendingOperations.length} operazioni sincronizzate`);
            }
            
        } catch (error) {
            console.error('[Offline] Sync queue processing failed:', error);
        } finally {
            this.syncInProgress = false;
        }
    }
    
    // Sincronizzazione singola operazione
    async syncOperation(operation) {
        const options = {
            method: operation.method,
            headers: {
                'Content-Type': 'application/json',
                'X-Sync-Operation': 'true'
            }
        };
        
        if (operation.data) {
            options.body = JSON.stringify(operation.data);
        } else if (operation.body) {
            options.body = operation.body;
        }
        
        const response = await fetch(operation.url, options);
        
        if (!response.ok) {
            throw new Error(`Sync failed: ${response.status} ${response.statusText}`);
        }
        
        return response.json();
    }
    
    // Sincronizzazione periodica
    startPeriodicSync() {
        setInterval(() => {
            if (this.isOnline) {
                this.processSyncQueue();
            }
        }, 30000); // Ogni 30 secondi
    }
    
    // Mostra stato connessione
    showConnectionStatus(status) {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.className = `connection-status ${status}`;
            statusElement.textContent = status === 'online' ? 'Online' : 'Offline';
        }
        
        // Notifica toast
        const message = status === 'online' ? 
            'Connessione ripristinata' : 
            'Modalità offline attiva';
        this.showToast(message, status === 'online' ? 'success' : 'warning');
    }
    
    // Mostra messaggio offline
    showOfflineMessage(message) {
        this.showToast(message, 'info');
    }
    
    // Mostra messaggio sincronizzazione
    showSyncMessage(message) {
        this.showToast(message, 'success');
    }
    
    // Mostra notifica aggiornamento
    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.innerHTML = `
            <div class="bg-blue-500 text-white p-4 rounded-lg shadow-lg">
                <p>Nuova versione disponibile!</p>
                <button onclick="window.location.reload()" class="bg-white text-blue-500 px-4 py-2 rounded mt-2">
                    Aggiorna
                </button>
            </div>
        `;
        document.body.appendChild(notification);
    }
    
    // Sistema toast generico
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const blinkClass = (type === 'error' || type === 'warning') ? 'blink' : '';
        toast.className = `toast ${blinkClass}`;
        const bg = (type === 'error' || type === 'warning') ? 'bg-red-600 text-white' :
                    (type === 'success' ? 'bg-green-600 text-white' : 'bg-blue-600 text-white');
        toast.innerHTML = `
            <div class="toast-content ${bg} p-4 shadow-lg rounded">
                <p class="font-semibold">${message}</p>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    getToastColor(type) {
        const colors = {
            success: 'green',
            error: 'red',
            warning: 'yellow',
            info: 'blue'
        };
        return colors[type] || 'blue';
    }
    
    // API pubblica
    async forceSync() {
        await this.processSyncQueue();
    }
    
    async clearCache() {
        const transaction = this.db.transaction(['dataCache'], 'readwrite');
        const store = transaction.objectStore('dataCache');
        await store.clear();
    }
    
    async getStats() {
        const pending = await this.getPendingOperations();
        return {
            isOnline: this.isOnline,
            pendingOperations: pending.length,
            syncInProgress: this.syncInProgress
        };
    }
}

// Inizializzazione automatica
document.addEventListener('DOMContentLoaded', () => {
    window.offlineManager = new OfflineManager();
});

// Esportazione per uso moduli
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineManager;
}