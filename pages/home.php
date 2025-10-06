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
                    <span class="block">ÒrdiGO</span>
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

    

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">




        



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