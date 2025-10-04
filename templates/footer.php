</main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-12 ring-1 ring-gray-800/60">
        <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-white">OrdiGO</h3>
                    <p class="mt-3 text-sm">Sistema di gestione ordini per la Festa dell'Oratorio. Veloce, moderno e pronto all'uso.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Navigazione</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a class="hover:text-white" href="?route=home"><i class="fas fa-home mr-2"></i>Home</a></li>
                        <li><a class="hover:text-white" href="?route=admin&page=products"><i class="fas fa-cog mr-2"></i>Admin</a></li>
                        <li><a class="hover:text-white" href="?route=report"><i class="fas fa-chart-bar mr-2"></i>Report</a></li>
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
                <div class="flex items-center space-x-4">
                    <a href="admin/projector.php" target="_blank" class="inline-flex items-center text-sm px-3 py-1 rounded-md bg-gray-800 hover:bg-gray-700 text-white shadow-sm transition"><i class="fas fa-tv mr-2"></i>Dashboard Proiettore</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript per funzionalità offline e sincronizzazione -->
    <script>
        // Gestione offline
        let isOnline = navigator.onLine;
        let syncQueue = [];

        // Controlla stato connessione
        function updateConnectionStatus() {
            const indicator = document.querySelector('.w-3.h-3');
            const statusText = indicator.nextElementSibling;
            
            if (navigator.onLine) {
                indicator.className = 'w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse';
                statusText.textContent = 'Online';
                processSyncQueue();
            } else {
                indicator.className = 'w-3 h-3 bg-yellow-500 rounded-full mr-2';
                statusText.textContent = 'Offline';
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
            
            syncQueue.forEach(async (operation, index) => {
                try {
                    const response = await fetch('<?= asset_path('api/sync.php') ?>', {
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
                    }
                } catch (error) {
                    console.error('Errore sincronizzazione:', error);
                }
            });
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
            toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Auto-refresh per aggiornamenti in tempo reale
        function startAutoRefresh() {
            setInterval(() => {
                if (navigator.onLine) {
                    // Controlla aggiornamenti scorte (endpoint opzionale)
                    fetch('<?= asset_path('api/check-updates.php') ?>')
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
            
            // Gestione mobile menu spostata su Alpine.js in header
        });

        // Service Worker per funzionalità offline avanzate
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
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