<?php
require_once 'config/database.php';

$db = getDB();

// Statistiche per la home
$stats = [
    'total_products' => $db->query("SELECT COUNT(*) as count FROM products WHERE active = 1")->fetch()['count'],
    'total_categories' => $db->query("SELECT COUNT(*) as count FROM categories WHERE active = 1")->fetch()['count'],
    'today_orders' => $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = DATE('now')")->fetch()['count'],
    'pending_orders' => $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch()['count']
];

// Prodotti più venduti (simulato per ora)
$popularProducts = $db->query("
    SELECT p.*, c.name as category_name, c.color as category_color
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.active = 1 
    ORDER BY p.name 
    LIMIT 6
")->fetchAll();

// Ordini recenti
$recentOrders = $db->query("
    SELECT * FROM orders 
    WHERE DATE(created_at) = DATE('now')
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="min-h-screen bg-gray-50">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-extrabold sm:text-5xl md:text-6xl">
                    <span class="block">OrdiGO</span>
                    <span class="block text-blue-200 text-2xl sm:text-3xl mt-2">Sistema di Gestione Ordini</span>
                </h1>
                <p class="mt-6 max-w-2xl mx-auto text-xl text-blue-100">
                    Gestisci facilmente gli ordini della tua Festa Oratorio con un sistema moderno, veloce e completamente offline.
                </p>
                <div class="mt-8 flex justify-center space-x-4">
                    <a href="?route=admin" class="bg-white text-blue-600 hover:bg-blue-50 px-8 py-3 rounded-lg font-semibold text-lg shadow-lg transition-colors">
                        <i class="fas fa-cog mr-2"></i>Pannello Admin
                    </a>
                    <a href="#ordini" class="bg-blue-500 hover:bg-blue-400 text-white px-8 py-3 rounded-lg font-semibold text-lg shadow-lg transition-colors">
                        <i class="fas fa-shopping-cart mr-2"></i>Nuovo Ordine
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche Rapide -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Prodotti Attivi -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-box text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Prodotti Attivi</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?= $stats['total_products'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categorie -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-tags text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Categorie</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?= $stats['total_categories'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ordini Oggi -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Ordini Oggi</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?= $stats['today_orders'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ordini in Attesa -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">In Attesa</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?= $stats['pending_orders'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sezione Azioni Rapide -->
        <div class="bg-white shadow-lg rounded-lg mb-12">
            <div class="px-6 py-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Azioni Rapide</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="?route=admin&page=products&action=add" class="group bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white p-6 rounded-lg shadow-lg transition-all transform hover:scale-105">
                        <div class="flex items-center justify-center mb-4">
                            <i class="fas fa-plus-circle text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-center">Nuovo Prodotto</h3>
                        <p class="text-blue-100 text-center mt-2">Aggiungi un nuovo prodotto al menu</p>
                    </a>

                    <a href="?route=admin&page=categories&action=add" class="group bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-6 rounded-lg shadow-lg transition-all transform hover:scale-105">
                        <div class="flex items-center justify-center mb-4">
                            <i class="fas fa-tag text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-center">Nuova Categoria</h3>
                        <p class="text-green-100 text-center mt-2">Organizza i prodotti per categoria</p>
                    </a>

                    <a href="#ordini" class="group bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white p-6 rounded-lg shadow-lg transition-all transform hover:scale-105">
                        <div class="flex items-center justify-center mb-4">
                            <i class="fas fa-shopping-cart text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-center">Nuovo Ordine</h3>
                        <p class="text-purple-100 text-center mt-2">Crea un nuovo ordine cliente</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Prodotti in Evidenza -->
        <div class="bg-white shadow-lg rounded-lg mb-12">
            <div class="px-6 py-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Prodotti del Menu</h2>
                    <a href="?route=admin&page=products" class="text-blue-600 hover:text-blue-800 font-medium">
                        Vedi tutti <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($popularProducts as $product): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($product['name']) ?></h3>
                            <span class="text-lg font-bold text-green-600">€ <?= number_format($product['price'], 2) ?></span>
                        </div>
                        
                        <?php if ($product['category_name']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mb-2" 
                              style="background-color: <?= $product['category_color'] ?>20; color: <?= $product['category_color'] ?>;">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </span>
                        <?php endif; ?>
                        
                        <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($product['description']) ?></p>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">
                                Scorta: <span class="<?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-red-600 font-medium' : 'text-gray-700' ?>">
                                    <?= $product['stock_quantity'] ?>
                                </span>
                            </span>
                            <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium">
                                <i class="fas fa-plus mr-1"></i>Aggiungi
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Ordini Recenti -->
        <?php if (!empty($recentOrders)): ?>
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Ordini di Oggi</h2>
                    <a href="?route=admin&page=orders" class="text-blue-600 hover:text-blue-800 font-medium">
                        Vedi tutti <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero Ordine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Totale</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ora</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($order['order_number']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($order['customer_name'] ?: 'Cliente anonimo') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    € <?= number_format($order['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'preparing' => 'bg-blue-100 text-blue-800',
                                        'ready' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusLabels = [
                                        'pending' => 'In Attesa',
                                        'preparing' => 'In Preparazione',
                                        'ready' => 'Pronto',
                                        'completed' => 'Completato',
                                        'cancelled' => 'Annullato'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$order['status']] ?>">
                                        <?= $statusLabels[$order['status']] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('H:i', strtotime($order['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">Nessun ordine oggi</h3>
                <p class="text-gray-500">Gli ordini di oggi appariranno qui quando verranno creati.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Sezione Nuovo Ordine (placeholder) -->
<div id="ordini" class="bg-gray-100 py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Sistema Ordini</h2>
        <p class="text-xl text-gray-600 mb-8">Il sistema di gestione ordini sarà implementato nelle prossime fasi dello sviluppo.</p>
        <div class="bg-white rounded-lg shadow-lg p-8">
            <i class="fas fa-tools text-6xl text-blue-500 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">In Sviluppo</h3>
            <p class="text-gray-600">Questa funzionalità sarà disponibile presto. Per ora puoi gestire prodotti e categorie dal pannello amministrativo.</p>
        </div>
    </div>
</div>