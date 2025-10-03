<?php
// Il database è già incluso dal file index.php principale

$db = Database::getInstance();
$page = $_GET['page'] ?? 'dashboard';

// Statistiche per dashboard
$stats = [];
if ($page === 'dashboard') {
    $stats['total_products'] = $db->query("SELECT COUNT(*) as count FROM products WHERE active = 1")->fetch()['count'];
    $stats['total_categories'] = $db->query("SELECT COUNT(*) as count FROM categories WHERE active = 1")->fetch()['count'];
    $stats['low_stock'] = $db->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND active = 1")->fetch()['count'];
    $stats['today_orders'] = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = DATE('now')")->fetch()['count'];
    $stats['today_revenue'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(created_at) = DATE('now') AND status != 'cancelled'")->fetch()['total'];
}

// I template sono già inclusi dal file index.php principale
?>

<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header Admin -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Pannello Amministrazione</h1>
                        <p class="mt-1 text-sm text-gray-600">Gestisci prodotti, categorie e monitora le vendite</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="?route=admin&page=products&action=add" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>Nuovo Prodotto
                        </a>
                        <a href="?route=admin&page=categories&action=add" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-tag mr-2"></i>Nuova Categoria
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Navigazione Admin -->
        <div class="bg-white shadow rounded-lg mb-6">
            <nav class="flex space-x-8 px-6 py-3">
                <a href="?route=admin&page=dashboard" class="<?= $page === 'dashboard' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?> py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                    <i class="fas fa-chart-line mr-2"></i>Dashboard
                </a>
                <a href="?route=admin&page=products" class="<?= $page === 'products' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?> py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                    <i class="fas fa-box mr-2"></i>Prodotti
                </a>
                <a href="?route=admin&page=categories" class="<?= $page === 'categories' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?> py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                    <i class="fas fa-tags mr-2"></i>Categorie
                </a>
            </nav>
        </div>

        <?php if ($page === 'dashboard'): ?>
            <!-- Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card Prodotti -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Prodotti Attivi</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?= $stats['total_products'] ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Categorie -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-tags text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Categorie</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?= $stats['total_categories'] ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Scorte Basse -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Scorte Basse</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?= $stats['low_stock'] ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Ordini Oggi -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Ordini Oggi</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?= $stats['today_orders'] ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ricavi Oggi -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Ricavi di Oggi</h3>
                    <div class="text-3xl font-bold text-green-600">€ <?= number_format($stats['today_revenue'], 2) ?></div>
                </div>
            </div>

            <!-- Prodotti con Scorte Basse -->
            <?php if ($stats['low_stock'] > 0): ?>
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        Prodotti con Scorte Basse
                    </h3>
                    <?php
                    $lowStockProducts = $db->query("
                        SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.stock_quantity <= p.min_stock_level AND p.active = 1
                        ORDER BY p.stock_quantity ASC
                    ")->fetchAll();
                    ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prodotto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scorta Attuale</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scorta Minima</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?= htmlspecialchars($product['category_name'] ?? 'Nessuna') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-red-600"><?= $product['stock_quantity'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500"><?= $product['min_stock_level'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?route=admin&page=products&action=edit&id=<?= $product['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit mr-1"></i>Modifica
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($page === 'products'): ?>
            <?php include 'products.php'; ?>
        
        <?php elseif ($page === 'categories'): ?>
            <?php include 'categories.php'; ?>
        
        <?php endif; ?>
    </div>
</div>