<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrdiGO - Gestione Ordini Festa Oratorio</title>
    <?php
        $twPath = __DIR__ . '/../assets/tailwind.css';
        $twVer = file_exists($twPath) ? filemtime($twPath) : time();
    ?>
    <!-- Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_path('assets/tailwind.css') . '?v=' . $twVer ?>">
    <!-- Inclusione Tailwind via CDN per garantire utilità JIT in pagina -->
    <script>
        window.tailwindFallbackConfig = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#60a5fa' }
                    },
                    fontFamily: {
                        sans: ["Inter","ui-sans-serif","system-ui","Segoe UI","Helvetica Neue","Arial","Noto Sans","sans-serif"]
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (window.tailwind && window.tailwind.config && window.tailwindFallbackConfig) {
            window.tailwind.config = Object.assign({}, window.tailwindFallbackConfig, window.tailwind.config);
        } else if (window.tailwindFallbackConfig) {
            window.tailwind = { config: window.tailwindFallbackConfig };
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PWA e Offline Support -->
    <link rel="manifest" href="<?= asset_path('manifest.json') ?>">
    <meta name="theme-color" content="#667eea">
    <script src="<?php echo asset_path('js/offline.js') . '?v=' . (file_exists(__DIR__ . '/../js/offline.js') ? filemtime(__DIR__ . '/../js/offline.js') : time()); ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        .connection-status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .connection-status.online {
            background-color: #10b981;
            color: white;
        }
        /* Alpine.js: nasconde gli elementi finché non sono inizializzati */
        [x-cloak] { display: none !important; }
        .connection-status.offline {
            background-color: #ef4444;
            color: white;
            animation: pulse 2s infinite;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1001;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        /* Effetto lampeggiante per allarmi */
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
        .blink {
            animation: blink 1s ease-in-out infinite;
        }
    </style>
    <style>
        /* Stili personalizzati per proiettore */
        @media (min-width: 1024px) {
            .projector-mode {
                font-size: 1.2rem;
            }
            .projector-mode .text-lg {
                font-size: 1.5rem;
            }
            .projector-mode .text-xl {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800 font-sans">
    <!-- Navigation -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm ring-1 ring-gray-200/60" x-data="{ mobileOpen: false }" @keydown.escape="mobileOpen = false" style="border-bottom: 2px solid #60a5fa;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-primary">
                            <i class="fas fa-utensils mr-2"></i>OrdiGO
                        </h1>
                    </div>
                    <div class="hidden md:ml-6 md:flex md:space-x-8">
                        <a href="<?= asset_path('index.php?route=home') ?>" class="<?= ($route == 'home') ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-primary' ?> inline-flex items-center px-2 pt-1 border-b-2 text-sm font-medium transition-colors">
                            <i class="fas fa-home mr-2"></i>Home
                        </a>
                        <a href="<?= asset_path('index.php?route=admin&page=products') ?>" class="<?= ($route == 'admin') ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-primary' ?> inline-flex items-center px-2 pt-1 border-b-2 text-sm font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                        <a href="<?= asset_path('sales.php') ?>" class="border-transparent text-gray-600 hover:text-primary inline-flex items-center px-2 pt-1 border-b-2 text-sm font-medium transition-colors">
                            <i class="fas fa-shopping-cart mr-2"></i>Vendite
                        </a>
                        <a href="<?= asset_path('admin/projector.php') ?>" class="border-transparent text-gray-600 hover:text-primary inline-flex items-center px-2 pt-1 border-b-2 text-sm font-medium transition-colors" target="_blank">
                            <i class="fas fa-tv mr-2"></i>Dashboard Proiettore
                        </a>
                    </div>
                </div>
                
                <!-- Status indicators -->
                <div class="flex items-center space-x-4">
                    <!-- Connessione: indicatore topbar con testo stacked -->
                    <div class="flex items-center text-sm">
                        <div id="topbar-connection-indicator" class="w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                        <div class="flex flex-col leading-tight">
                            <span id="topbar-connection-text" class="text-gray-700 font-medium">Online</span>
                            <span class="text-gray-500 flex items-center">
                                <i id="topbar-sync-icon" class="fas fa-sync-alt text-gray-400 mr-1 hidden"></i>
                                <span id="topbar-sync-text">Sincronizzato</span>
                            </span>
                        </div>
                    </div>

                    <!-- Mobile menu button -->
                    <button type="button" class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary" aria-controls="mobile-menu" :aria-expanded="mobileOpen.toString()" @click="mobileOpen = !mobileOpen">
                        <span class="sr-only">Apri menu</span>
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="md:hidden">
            <div x-cloak x-show="mobileOpen" x-transition.opacity x-transition.duration.200ms class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-white/80 backdrop-blur ring-1 ring-gray-200/60 rounded-b-lg shadow-sm" id="mobile-menu">
                <a href="<?= asset_path('index.php?route=home') ?>" class="<?= ($route == 'home') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-home mr-2"></i>Home
                </a>
                <a href="<?= asset_path('index.php?route=admin&page=products') ?>" class="<?= ($route == 'admin') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-cog mr-2"></i>Admin
                </a>
                <a href="<?= asset_path('sales.php') ?>" class="text-gray-700 hover:bg-gray-100 block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-shopping-cart mr-2"></i>Vendite
                </a>
            </div>
        </div>
    </nav>

    <!-- Indicatore stato connessione rimosso (usiamo solo quello in topbar) -->

    <!-- Main content -->
    <main class="<?= ($route == 'admin') ? 'py-6 px-4' : 'max-w-7xl mx-auto py-6 sm:px-6 lg:px-8' ?>">
        <!-- Alert per scorte basse -->
        <?php
        try {
            $db = getDB();
            $stmt = $db->query("
                SELECT name, stock_quantity, min_stock_level 
                FROM products 
                WHERE stock_quantity <= min_stock_level AND active = 1
            ");
            $scorte_basse = $stmt->fetchAll();
            
            if (!empty($scorte_basse)): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                <strong>Attenzione!</strong> I seguenti prodotti hanno scorte basse:
                            </p>
                            <ul class="mt-2 text-sm text-red-600">
                                <?php foreach ($scorte_basse as $prodotto): ?>
                                    <li>• <?= htmlspecialchars($prodotto['name']) ?> (<?= $prodotto['stock_quantity'] ?>/<?= $prodotto['min_stock_level'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif;
        } catch (Exception $e) {
            // Silently handle database errors
        }
        ?>