<?php
// Abilita output buffering per consentire header() anche dopo contenuti inclusi
if (!headers_sent()) { @ob_start(); }
// Inclusione difensiva del database quando la pagina è aperta direttamente
if (!class_exists('Database')) {
    require_once __DIR__ . '/../config/database.php';
}

$db = getDB();
$page = $_GET['page'] ?? 'dashboard';

// Se la pagina admin è aperta direttamente senza il routing principale,
// reindirizza verso index.php con route=admin per includere header/footer e mantenere la GUI.
if (!isset($_GET['route']) || $_GET['route'] !== 'admin') {
    $targetPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    header('Location: ../index.php?route=admin&page=' . urlencode($targetPage));
    exit;
}

// Endpoint JSON per aggiornare scorte basse in tempo reale
if (($page === 'dashboard') && (isset($_GET['ajax']) && $_GET['ajax'] === 'low_stock')) {
    header('Content-Type: application/json; charset=utf-8');
    // Conteggio scorte basse
    $lowStockCount = $db->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND active = 1")->fetch()['count'];
    // Elenco prodotti con scorte basse
    $lowStockProducts = $db->query("
        SELECT p.id, p.name, p.stock_quantity, p.min_stock_level, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.stock_quantity <= p.min_stock_level AND p.active = 1
        ORDER BY p.stock_quantity ASC
    ")->fetchAll();
    echo json_encode([
        'count' => (int)$lowStockCount,
        'products' => array_map(function($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'category_name' => $p['category_name'] ?? 'Nessuna',
                'stock_quantity' => (int)$p['stock_quantity'],
                'min_stock_level' => (int)$p['min_stock_level'],
            ];
        }, $lowStockProducts)
    ]);
    exit;
}

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

<div class="min-h-screen bg-gray-50 p-4 md:p-6" x-data="{ sidebarOpen: false }">
    <!-- Layout Admin TailAdmin-like -->
    <div class="flex gap-4 md:gap-6">
        <!-- Sidebar -->
        <aside class="hidden md:block w-64 bg-white border border-gray-200 shadow-sm min-h-screen rounded-lg overflow-hidden">
            <div class="p-4 border-b">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded flex items-center justify-center"><i class="fas fa-cog"></i></div>
                    <span class="font-semibold text-gray-800">Admin OrdiGO</span>
                </div>
            </div>
            <nav class="p-2 space-y-1">
                <a href="?route=admin&page=dashboard" class="flex items-center px-3 py-2 rounded-md <?= $page === 'dashboard' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fas fa-chart-line mr-3"></i><span>Dashboard</span>
                </a>
                <a href="?route=admin&page=products" class="flex items-center px-3 py-2 rounded-md <?= $page === 'products' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fas fa-box mr-3"></i><span>Prodotti</span>
                </a>
                <a href="?route=admin&page=categories" class="flex items-center px-3 py-2 rounded-md <?= $page === 'categories' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fas fa-tags mr-3"></i><span>Categorie</span>
                </a>
                <a href="<?= asset_path('sales.php') ?>" class="flex items-center px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-shopping-cart mr-3"></i><span>Vendite</span>
                </a>
                <a href="?route=admin&page=reports" class="flex items-center px-3 py-2 rounded-md <?= $page === 'reports' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fas fa-chart-bar mr-3"></i><span>Report</span>
                </a>
            </nav>
        </aside>

        <!-- Content -->
        <div class="flex-1">
            <!-- Topbar (card arrotondata staccata dalla sidebar) -->
            <div class="px-4 mb-2 md:mb-3">
                <div class="bg-white ring-1 ring-gray-200/60 shadow-sm rounded-lg" style="border-bottom: 2px solid #60a5fa;">
                    <div class="px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <button class="md:hidden inline-flex items-center px-2 py-1 rounded hover:bg-gray-100" @click="sidebarOpen = !sidebarOpen"><i class="fas fa-bars"></i></button>
                        <h1 class="text-xl font-semibold text-gray-900">Pannello Amministrazione</h1>
                    </div>
                    <!-- Pulsanti rapidi rimossi per evitare duplicazioni -->
                    </div>
                </div>
            </div>

            <!-- Mobile sidebar -->
            <div class="md:hidden" x-show="sidebarOpen" x-transition>
                <nav class="bg-white border border-gray-200 shadow-sm p-2 space-y-1 rounded-xl">
                    <a href="?route=admin&page=dashboard" class="block px-3 py-2 rounded-md <?= $page === 'dashboard' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                        <i class="fas fa-chart-line mr-2"></i>Dashboard
                    </a>
                    <a href="?route=admin&page=products" class="block px-3 py-2 rounded-md <?= $page === 'products' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                        <i class="fas fa-box mr-2"></i>Prodotti
                    </a>
                    <a href="?route=admin&page=categories" class="block px-3 py-2 rounded-md <?= $page === 'categories' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                        <i class="fas fa-tags mr-2"></i>Categorie
                    </a>
                    <a href="?route=admin&page=reports" class="block px-3 py-2 rounded-md <?= $page === 'reports' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                        <i class="fas fa-chart-bar mr-2"></i>Report
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="p-4">

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
                                    <dd id="low-stock-count" class="text-lg font-medium text-gray-900"><?= $stats['low_stock'] ?></dd>
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

            <!-- Prodotti con Scorte Basse (aggiornato in tempo reale) -->
            <div id="low-stock-section" class="bg-white shadow rounded-lg" style="display: <?= $stats['low_stock'] > 0 ? 'block' : 'none' ?>;">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        Prodotti con Scorte Basse
                    </h3>
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
                            <tbody id="low-stock-list" class="bg-white divide-y divide-gray-200">
                                <!-- Popolato via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="low-stock-empty" class="text-sm text-gray-500" style="display: none;">
                        Nessun prodotto con scorte basse al momento.
                    </div>
                </div>
            </div>
            
            <script>
            (function() {
                function renderLowStock(data) {
                    var countEl = document.getElementById('low-stock-count');
                    var sectionEl = document.getElementById('low-stock-section');
                    var listEl = document.getElementById('low-stock-list');
                    var emptyEl = document.getElementById('low-stock-empty');
                    if (!countEl || !sectionEl || !listEl || !emptyEl) return;

                    // Aggiorna contatore
                    countEl.textContent = data.count || 0;

                    // Mostra/Nasconde la sezione
                    if (data.count > 0) {
                        sectionEl.style.display = 'block';
                        emptyEl.style.display = 'none';
                    } else {
                        sectionEl.style.display = 'none';
                        emptyEl.style.display = 'block';
                    }

                    // Popola la lista
                    listEl.innerHTML = '';
                    (data.products || []).forEach(function(p) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<div class="text-sm font-medium text-gray-900">' + escapeHtml(p.name) + '</div>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' + escapeHtml(p.category_name || 'Nessuna') + '</span>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<span class="text-sm font-medium text-red-600">' + p.stock_quantity + '</span>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<span class="text-sm text-gray-500">' + p.min_stock_level + '</span>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">' +
                                '<a href="?route=admin&page=products&action=edit&id=' + p.id + '" class="text-blue-600 hover:text-blue-900">' +
                                    '<i class="fas fa-edit mr-1"></i>Modifica' +
                                '</a>' +
                            '</td>';
                        listEl.appendChild(tr);
                    });
                }

                function escapeHtml(str) {
                    var div = document.createElement('div');
                    div.appendChild(document.createTextNode(str));
                    return div.innerHTML;
                }

                function fetchLowStock() {
                    var url = '?route=admin&page=dashboard&ajax=low_stock';
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function(res) { return res.json(); })
                        .then(function(data) { renderLowStock(data); })
                        .catch(function(err) { /* silenzioso */ });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    // Prima fetch immediata, poi ogni 10 secondi
                    fetchLowStock();
                    setInterval(fetchLowStock, 10000);
                });
            })();
            </script>

        <?php elseif ($page === 'products'): ?>
            <?php include 'products.php'; ?>
        
        <?php elseif ($page === 'categories'): ?>
            <?php include 'categories.php'; ?>
        
        <?php elseif ($page === 'reports'): ?>
            <?php include 'reports.php'; ?>
        
        <?php endif; ?>
            </div>
        </div>
    </div>
</div>