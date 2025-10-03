<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrdiGO - Gestione Ordini Festa Oratorio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        accent: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PWA e Offline Support -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <script src="js/offline.js"></script>
    
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
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b-4 border-primary">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-primary">
                            <i class="fas fa-utensils mr-2"></i>OrdiGO
                        </h1>
                    </div>
                    <div class="hidden md:ml-6 md:flex md:space-x-8">
                        <a href="?route=home" class="<?= ($route == 'home') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-home mr-2"></i>Home
                        </a>
                        <a href="?route=admin&page=products" class="<?= ($route == 'admin') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                        <a href="?route=report" class="<?= ($route == 'report') ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-chart-bar mr-2"></i>Report
                        </a>
                        <a href="admin/projector.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" target="_blank">
                            <i class="fas fa-tv mr-2"></i>Dashboard Proiettore
                        </a>
                    </div>
                </div>
                
                <!-- Status indicators -->
                <div class="flex items-center space-x-4">
                    <!-- Offline indicator -->
                    <div class="flex items-center text-sm">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                        <span class="text-gray-600">Offline Ready</span>
                    </div>
                    
                    <!-- Sync status -->
                    <div class="flex items-center text-sm">
                        <i class="fas fa-sync-alt text-gray-400 mr-2"></i>
                        <span class="text-gray-600">Sincronizzato</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-gray-50">
                <a href="?route=home" class="<?= ($route == 'home') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-200' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-home mr-2"></i>Home
                </a>
                <a href="?route=admin&page=products" class="<?= ($route == 'admin') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-200' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-cog mr-2"></i>Admin
                </a>
                <a href="?route=report" class="<?= ($route == 'report') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-200' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-chart-bar mr-2"></i>Report
                </a>
            </div>
        </div>
    </nav>

    <!-- Indicatore stato connessione -->
    <div id="connection-status" class="connection-status online">Online</div>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
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