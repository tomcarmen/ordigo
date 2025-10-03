</main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    <p>&copy; 2024 OrdiGO - Sistema di gestione ordini per Festa Oratorio</p>
                </div>
                <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <span>Versione 1.0</span>
                    <span>•</span>
                    <span id="last-sync">Ultimo sync: <?= date('H:i:s') ?></span>
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
                    // Controlla aggiornamenti scorte
                    fetch('/api/check-updates')
                        .then(response => response.json())
                        .then(data => {
                            if (data.updates) {
                                location.reload();
                            }
                        })
                        .catch(error => console.error('Errore check updates:', error));
                }
            }, 30000); // Ogni 30 secondi
        }

        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            updateConnectionStatus();
            loadSyncQueue();
            startAutoRefresh();
            
            // Gestione mobile menu
            const mobileMenuButton = document.querySelector('[data-mobile-menu]');
            const mobileMenu = document.querySelector('.md\\:hidden > div');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });

        // Service Worker per funzionalità offline avanzate
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registrato:', registration);
                })
                .catch(error => {
                    console.log('Errore registrazione Service Worker:', error);
                });
        }
    </script>
</body>
</html>