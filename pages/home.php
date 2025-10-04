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

// Aggiunte (extras) per i prodotti mostrati
$extrasByProduct = [];
foreach ($popularProducts as $pp) {
    $pid = $pp['id'];
    $extrasByProduct[$pid] = $db->query(
        "SELECT name, selling_price, purchase_price, stock_quantity, min_stock_level FROM product_extras WHERE product_id = ? AND active = 1 ORDER BY name",
        [$pid]
    )->fetchAll();
}

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

    <!-- Features Section -->
    <section class="bg-white">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">Perché scegliere OrdiGO</h2>
                <p class="mt-4 text-lg text-gray-600">Strumenti moderni per gestire in modo semplice ordini, prodotti e report.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Gestione Prodotti -->
                <div class="group bg-gray-50 hover:bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-blue-100 text-blue-600 mb-4">
                        <i class="fas fa-box text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Gestione Prodotti</h3>
                    <p class="mt-2 text-sm text-gray-600">Crea, modifica e organizza prodotti con controllo scorte e categorie.</p>
                    <a href="?route=admin&page=products" class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Vai ai Prodotti <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Sistema Ordini -->
                <div class="group bg-gray-50 hover:bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-purple-100 text-purple-600 mb-4">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Sistema Ordini</h3>
                    <p class="mt-2 text-sm text-gray-600">Flusso ordini con stati, aggiornamenti in tempo reale e dashboard proiettore.</p>
                    <a href="#ordini" class="mt-4 inline-flex items-center text-purple-600 hover:text-purple-800 text-sm font-medium">
                        Crea un Ordine <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Report Avanzati -->
                <div class="group bg-gray-50 hover:bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-green-100 text-green-600 mb-4">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Report Avanzati</h3>
                    <p class="mt-2 text-sm text-gray-600">Statistiche vendite, prodotti più venduti e analisi per categoria.</p>
                    <a href="?route=report" class="mt-4 inline-flex items-center text-green-600 hover:text-green-800 text-sm font-medium">
                        Vedi Report <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- PWA & Offline -->
                <div class="group bg-gray-50 hover:bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-lg transition">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-amber-100 text-amber-600 mb-4">
                        <i class="fas fa-bolt text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">PWA & Offline</h3>
                    <p class="mt-2 text-sm text-gray-600">Funziona offline, sincronizza quando online e supporta installazione come app.</p>
                </div>
            </div>
        </div>
    </section>

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

                    <a href="?route=admin&page=categories&openModal=addCategory" class="group bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-6 rounded-lg shadow-lg transition-all transform hover:scale-105">
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

        <!-- Sezione CTA -->
        <section class="relative overflow-hidden rounded-2xl mb-12">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600"></div>
            <div class="relative px-6 py-10 sm:px-8 sm:py-12 lg:px-10">
                <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Pronto a gestire ordini in modo smart?</h2>
                        <p class="mt-3 text-blue-100">Configura prodotti e categorie, monitora gli ordini in tempo reale e usa la dashboard proiettore per il servizio.</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="?route=admin" class="bg-white text-blue-700 hover:bg-blue-50 px-6 py-3 rounded-lg font-semibold shadow-lg transition">
                            <i class="fas fa-cog mr-2"></i>Apri Admin
                        </a>
                        <a href="admin/projector.php" target="_blank" class="bg-indigo-500 hover:bg-indigo-400 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition">
                            <i class="fas fa-tv mr-2"></i>Apri Proiettore
                        </a>
                    </div>
                </div>
            </div>
        </section>

        

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
                        <?php $thumbBorderColor = htmlspecialchars($product['category_color'] ?? '#D1D5DB'); ?>
                        <div class="mb-3">
                            <?php if (!empty($product['image_url'])): ?>
                            <div class="h-28 w-full rounded-lg overflow-hidden shadow-sm bg-white" style="border: 2px solid <?= $thumbBorderColor ?>;">
                                <img class="h-full w-full object-cover" src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            </div>
                            <?php else: ?>
                            <div class="h-28 w-full rounded-lg shadow-sm bg-white flex items-center justify-center" style="border: 2px solid <?= $thumbBorderColor ?>;">
                                <i class="fas fa-box text-gray-400 text-2xl"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($product['name']) ?></h3>
                            <span class="text-lg font-bold text-green-600">€ <?= number_format((float)($product['selling_price'] ?? $product['price']), 2) ?></span>
                        </div>
                        
                        <?php if ($product['category_name']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mb-2" 
                              style="background-color: <?= $product['category_color'] ?>20; color: <?= $product['category_color'] ?>;">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </span>
                        <?php endif; ?>
                        
                        <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($product['description']) ?></p>
                        <?php $extras = $extrasByProduct[$product['id']] ?? []; if (!empty($extras)): ?>
                        <div class="mt-2">
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Aggiunte disponibili</h4>
                            <ul class="space-y-1">
                                <?php foreach ($extras as $ex): ?>
                                <li class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700"><?= htmlspecialchars($ex['name']) ?></span>
                                    <span class="text-gray-500">
                                        € <?= number_format((float)($ex['selling_price'] ?? 0), 2) ?>
                                        <span class="ml-2 <?= ((int)($ex['stock_quantity'] ?? 0)) <= ((int)($ex['min_stock_level'] ?? 0)) ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
                                            scorta: <?= (int)($ex['stock_quantity'] ?? 0) ?>
                                        </span>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
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