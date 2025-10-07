</main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-12 ring-1 ring-gray-800/60">
        <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
<h3 class="text-lg font-semibold text-white">ÒrdiGO</h3>
                    <p class="mt-3 text-sm">Sistema di gestione ordini per la Festa dell'Oratorio. Veloce, moderno e pronto all'uso.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Navigazione</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a class="hover:text-white" href="?route=home"><i class="fas fa-home mr-2"></i>Home</a></li>
                        <li><a class="hover:text-white" href="?route=admin&page=products"><i class="fas fa-cog mr-2"></i>Admin</a></li>
                        <li><a class="hover:text-white" href="orders.php"><i class="fas fa-clipboard-list mr-2"></i>Ordini</a></li>
                        <li><a class="hover:text-white" href="?route=admin&page=reports"><i class="fas fa-chart-bar mr-2"></i>Report</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Stato</h3>
                    <div class="mt-3 text-sm space-y-2">
                        <p>Versione 1.0</p>
                        <p id="last-sync">Ultimo sync: <?= date('H:i:s') ?></p>
                    </div>
                </div>
            </div>
            <div class="mt-10 border-t border-gray-700 pt-6 flex items-center justify-between">
                <p class="text-sm">&copy; 2024 OrdiGO. Tutti i diritti riservati.</p>
                <div class="flex items-center space-x-4"></div>
            </div>
        </div>
    </footer>

    <!-- JavaScript per funzionalità offline e sincronizzazione -->
    <script>
        // Gestione offline
        let isOnline = navigator.onLine;
        let syncQueue = [];
        let isSyncing = false;

        // Controlla stato connessione
        function updateConnectionStatus() {
            const indicator = document.getElementById('topbar-connection-indicator');
            const statusText = document.getElementById('topbar-connection-text');
            const syncText = document.getElementById('topbar-sync-text');
            
            if (indicator) {
                if (navigator.onLine) {
                    indicator.className = 'w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse';
                    if (statusText) statusText.textContent = 'Online';
                    if (syncText) syncText.textContent = isSyncing ? 'In attesa' : 'Sincronizzato';
                    processSyncQueue();
                } else {
                    indicator.className = 'w-3 h-3 bg-yellow-500 rounded-full mr-2';
                    if (statusText) statusText.textContent = 'Offline';
                    if (syncText) syncText.textContent = syncQueue.length > 0 ? 'In attesa' : 'Offline';
                }
            }
        }

        // Aggiorna etichetta di sincronizzazione manualmente
        function updateSyncLabel() {
            const syncText = document.getElementById('topbar-sync-text');
            const syncIcon = document.getElementById('topbar-sync-icon');
            if (!syncText) return;
            if (!navigator.onLine) {
                const pending = syncQueue.length > 0;
                syncText.textContent = pending ? 'In attesa' : 'Offline';
                if (syncIcon) {
                    syncIcon.classList.toggle('hidden', !pending);
                    syncIcon.classList.remove('animate-spin');
                }
                return;
            }
            const syncing = isSyncing;
            syncText.textContent = syncing ? 'In attesa' : 'Sincronizzato';
            if (syncIcon) {
                syncIcon.classList.toggle('hidden', !syncing);
                syncIcon.classList.toggle('animate-spin', syncing);
            }
        }

        // Event listeners per stato connessione
        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);

        // Funzione per aggiungere operazioni alla coda di sincronizzazione
        function addToSyncQueue(operation) {
            syncQueue.push({
                ...operation,
                timestamp: new Date().toISOString()
            });
            
            // Salva in localStorage per persistenza
            localStorage.setItem('ordigo_sync_queue', JSON.stringify(syncQueue));
        }

        // Processa la coda di sincronizzazione quando torna online
        function processSyncQueue() {
            if (syncQueue.length === 0) return;
            isSyncing = true;
            updateSyncLabel();
            
            syncQueue.forEach(async (operation, index) => {
                try {
                    const response = await fetch('/api/sync', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(operation)
                    });
                    
                    if (response.ok) {
                        syncQueue.splice(index, 1);
                        localStorage.setItem('ordigo_sync_queue', JSON.stringify(syncQueue));
                        updateLastSync();
                        updateSyncLabel();
                    }
                } catch (error) {
                    console.error('Errore sincronizzazione:', error);
                }
            });
            isSyncing = false;
            updateSyncLabel();
        }

        // Aggiorna timestamp ultimo sync
        function updateLastSync() {
            const lastSyncElement = document.getElementById('last-sync');
            if (lastSyncElement) {
                lastSyncElement.textContent = `Ultimo sync: ${new Date().toLocaleTimeString()}`;
            }
        }

        // Carica coda di sincronizzazione da localStorage
        function loadSyncQueue() {
            const saved = localStorage.getItem('ordigo_sync_queue');
            if (saved) {
                syncQueue = JSON.parse(saved);
            }
        }

        // Funzioni per gestione form offline
        function handleFormSubmit(form, endpoint) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (navigator.onLine) {
                // Invia direttamente se online
                return fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
            } else {
                // Aggiungi alla coda se offline
                addToSyncQueue({
                    endpoint: endpoint,
                    method: 'POST',
                    data: data
                });
                
                // Simula successo per UX
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({ success: true, offline: true })
                });
            }
        }

        // Notifiche toast
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const baseClasses = 'fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50';
            const colorClass = type === 'error' ? 'bg-red-600 text-white' :
                               type === 'warning' ? 'bg-red-500 text-white' :
                               type === 'success' ? 'bg-green-600 text-white' :
                               'bg-blue-600 text-white';
            const blinkClass = (type === 'error' || type === 'warning') ? 'blink' : '';
            toast.className = `${baseClasses} ${colorClass} ${blinkClass}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 4000);
        }

        // Auto-refresh per aggiornamenti in tempo reale
        function startAutoRefresh() {
            setInterval(() => {
                if (navigator.onLine) {
                    // Controlla aggiornamenti scorte (endpoint opzionale)
                    fetch('/api/check-updates')
                        .then(async response => {
                            const contentType = response.headers.get('content-type') || '';
                            if (!response.ok) throw new Error('HTTP ' + response.status);
                            if (contentType.includes('application/json')) {
                                return response.json();
                            }
                            // Non JSON: evita parse error e tratta come nessun aggiornamento
                            return { updates: false };
                        })
                        .then(data => {
                            if (data && data.updates) {
                                location.reload();
                            }
                        })
                        .catch(error => console.warn('Check updates non disponibile:', error));
                }
            }, 30000); // Ogni 30 secondi
        }

        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            updateConnectionStatus();
            loadSyncQueue();
            startAutoRefresh();
            updateSyncLabel();
            
            // Gestione mobile menu spostata su Alpine.js in header

            // Scorciatoia tastiera: premi "A" per aprire Vendite
            const salesUrl = '<?= asset_path('sales.php') ?>';
            function isTypingTarget(target) {
                if (!target) return false;
                const tag = (target.tagName || '').toUpperCase();
                return target.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            }
            document.addEventListener('keydown', function(e) {
                // Evita conflitti mentre si digita nei campi
                if (isTypingTarget(e.target)) return;
                // Nessun modificatore e tasto "a"
                const key = (e.key || '').toLowerCase();
                if (!e.ctrlKey && !e.altKey && !e.metaKey && !e.shiftKey && key === 'a') {
                    e.preventDefault();
                    window.location.href = salesUrl;
                }
            });
        });

        // Service Worker per funzionalità offline avanzate
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= asset_path('sw.js') ?>')
                .then(registration => {
                    console.log('Service Worker registrato:', registration);
                    // Tenta di aggiornare il SW appena possibile
                    try { if (registration.update) registration.update(); } catch (e) {}
                    // Ricarica la pagina quando cambia il controller (nuovo SW attivo)
                    let swReloaded = false;
                    navigator.serviceWorker.addEventListener('controllerchange', () => {
                        if (swReloaded) return;
                        swReloaded = true;
                        console.log('SW controller changed, reloading');
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.log('Errore registrazione Service Worker:', error);
                });
        }
    </script>
    <script>
        // Dopo operazione conclusa con successo, pulisci cache e disattiva SW per evitare contenuti stantii
        (function() {
            try {
                const params = new URLSearchParams(window.location.search);
                if (params.get('type') === 'success') {
                    if ('caches' in window) {
                        caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k)))).catch(()=>{});
                    }
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.getRegistrations().then(regs => {
                            regs.forEach(r => r.unregister());
                        }).catch(()=>{});
                    }
                }
            } catch(e) {}
        })();
    </script>
  </body>
  </html>