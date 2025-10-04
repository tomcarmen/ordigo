<?php
/**
 * Report e Statistiche - OrdiGO
 * Generazione report vendite e analisi
 */

$db = Database::getInstance();

// Parametri per filtri
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primo giorno del mese corrente
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Oggi
$category_filter = $_GET['category'] ?? '';
$period = $_GET['period'] ?? 'month'; // day, week, month, year

// Statistiche generali
$stats = [];

// Vendite totali nel periodo
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$date_from, $date_to]);
$stats['sales'] = $stmt->fetch();

// Prodotti più venduti
$category_condition = $category_filter ? "AND p.category_id = ?" : "";
$category_params = $category_filter ? [$date_from, $date_to, $category_filter] : [$date_from, $date_to];

$stmt = $db->query("
    SELECT 
        p.name,
        p.selling_price,
        c.name as category_name,
        c.color,
        SUM(oi.quantity) as total_sold,
        SUM(oi.total_price) as total_revenue,
        COUNT(DISTINCT oi.order_id) as orders_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? 
    AND o.status = 'completed'
    $category_condition
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
", $category_params);
$top_products = $stmt->fetchAll();

// Vendite per categoria
$stmt = $db->query("
    SELECT 
        c.name,
        c.color,
        COUNT(DISTINCT o.id) as orders_count,
        SUM(oi.quantity) as items_sold,
        SUM(oi.total_price) as revenue
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
    WHERE c.active = 1
    GROUP BY c.id
    ORDER BY revenue DESC
", [$date_from, $date_to]);
$category_stats = $stmt->fetchAll();

// Trend vendite per periodo
$trend_data = [];
switch ($period) {
    case 'day':
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as period,
                COUNT(*) as orders,
                SUM(total_amount) as revenue
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
            GROUP BY DATE(created_at)
            ORDER BY period
        ", [$date_from, $date_to]);
        break;
    case 'week':
        $stmt = $db->query("
            SELECT 
                strftime('%Y-W%W', created_at) as period,
                COUNT(*) as orders,
                SUM(total_amount) as revenue
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
            GROUP BY strftime('%Y-W%W', created_at)
            ORDER BY period
        ", [$date_from, $date_to]);
        break;
    default: // month
        $stmt = $db->query("
            SELECT 
                strftime('%Y-%m', created_at) as period,
                COUNT(*) as orders,
                SUM(total_amount) as revenue
            FROM orders 
            WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
            GROUP BY strftime('%Y-%m', created_at)
            ORDER BY period
        ", [$date_from, $date_to]);
}
$trend_data = $stmt->fetchAll();

// Scorte basse
$stmt = $db->query("
    SELECT 
        p.name,
        p.stock_quantity,
        p.min_stock_level,
        c.name as category_name,
        c.color,
        (p.min_stock_level - p.stock_quantity) as deficit
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= p.min_stock_level AND p.active = 1
    ORDER BY deficit DESC
");
$low_stock = $stmt->fetchAll();

// Ordini recenti
$stmt = $db->query("
    SELECT 
        o.*,
        COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 20
", [$date_from, $date_to]);
$recent_orders = $stmt->fetchAll();

// Categorie per filtro
$stmt = $db->query("SELECT id, name FROM categories WHERE active = 1 ORDER BY name");
$categories = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header con filtri -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 space-y-4 lg:space-y-0">
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-chart-bar mr-3 text-primary"></i>Report e Statistiche
        </h1>
        
        <!-- Filtri -->
        <div class="flex flex-wrap gap-4 no-print">
            <form method="GET" action="?route=report" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Da</label>
                    <input type="date" name="date_from" value="<?= $date_from ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">A</label>
                    <input type="date" name="date_to" value="<?= $date_to ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Categoria</label>
                    <select name="category" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">Tutte</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Periodo</label>
                    <select name="period" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="day" <?= $period == 'day' ? 'selected' : '' ?>>Giornaliero</option>
                        <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Settimanale</option>
                        <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Mensile</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-md shadow-sm hover:bg-blue-600 transition-colors text-sm">
                    <i class="fas fa-filter mr-1"></i>Filtra
                </button>
            </form>
        </div>
    </div>

    <!-- Statistiche principali -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Ordini Totali</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['sales']['total_orders']) ?></p>
                    <p class="text-xs text-gray-500"><?= number_format($stats['sales']['completed_orders']) ?> completati</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-euro-sign text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Ricavi Totali</p>
                    <p class="text-2xl font-semibold text-gray-900">€<?= number_format($stats['sales']['total_revenue'], 2) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Valore Medio Ordine</p>
                    <p class="text-2xl font-semibold text-gray-900">€<?= number_format($stats['sales']['avg_order_value'], 2) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Scorte Basse</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= count($low_stock) ?></p>
                    <p class="text-xs text-gray-500">prodotti</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Grafico trend vendite -->
        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-chart-area mr-2 text-primary"></i>Trend Vendite
            </h3>
            <div class="h-64">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- Vendite per categoria -->
        <div class="bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 hover:shadow-md transition">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-chart-pie mr-2 text-primary"></i>Vendite per Categoria
            </h3>
            <div class="h-64">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Prodotti più venduti -->
        <div class="bg-white rounded-lg shadow overflow-hidden ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-trophy mr-2 text-yellow-500"></i>Top Prodotti
                </h3>
            </div>
            <div class="divide-y divide-gray-200">
                <?php foreach (array_slice($top_products, 0, 5) as $index => $product): ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-medium text-gray-600">
                                <?= $index + 1 ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <span class="inline-block w-2 h-2 rounded-full mr-1" style="background-color: <?= $product['color'] ?>"></span>
                                    <?= htmlspecialchars($product['category_name']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900"><?= $product['total_sold'] ?> pz</p>
                            <p class="text-xs text-gray-500">€<?= number_format($product['total_revenue'], 2) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Scorte basse -->
        <div class="bg-white rounded-lg shadow overflow-hidden ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>Scorte Basse
                </h3>
            </div>
            <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                <?php if (empty($low_stock)): ?>
                    <div class="px-6 py-6 text-center text-gray-600 bg-green-50">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                        <p>Tutte le scorte sono sufficienti!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($low_stock as $item): ?>
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-gray-500">
                                        <span class="inline-block w-2 h-2 rounded-full mr-1" style="background-color: <?= $item['color'] ?>"></span>
                                        <?= htmlspecialchars($item['category_name']) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-red-600"><?= $item['stock_quantity'] ?>/<?= $item['min_stock_level'] ?></p>
                                    <p class="text-xs text-red-500">-<?= $item['deficit'] ?> pz</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ordini recenti -->
        <div class="bg-white rounded-lg shadow overflow-hidden ring-1 ring-gray-100 hover:shadow-md transition">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-clock mr-2 text-blue-500"></i>Ordini Recenti
                </h3>
            </div>
            <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                <?php foreach (array_slice($recent_orders, 0, 10) as $order): ?>
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">#<?= htmlspecialchars($order['order_number']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    <?php if ($order['customer_name']): ?>
                                        • <?= htmlspecialchars($order['customer_name']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">€<?= number_format($order['total_amount'], 2) ?></p>
                                <p class="text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($order['status']) {
                                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                                            case 'ready': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'preparing': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'pending': echo 'bg-gray-100 text-gray-800'; break;
                                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                        }
                                        ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Azioni rapide -->
    <div class="mt-8 bg-white rounded-lg shadow p-6 ring-1 ring-gray-100 no-print">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
            <i class="fas fa-download mr-2 text-primary"></i>Esporta Report
        </h3>
        <div class="flex flex-wrap gap-4">
            <button onclick="exportReport('pdf')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-pdf mr-2 text-red-500"></i>Esporta PDF
            </button>
            <button onclick="exportReport('excel')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-excel mr-2 text-green-500"></i>Esporta Excel
            </button>
            <button onclick="printReport()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-print mr-2 text-blue-500"></i>Stampa
            </button>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dati per i grafici
const trendData = <?= json_encode($trend_data) ?>;
const categoryData = <?= json_encode($category_stats) ?>;

// Grafico trend vendite
const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: trendData.map(d => d.period),
        datasets: [{
            label: 'Ricavi (€)',
            data: trendData.map(d => d.revenue || 0),
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Ordini',
            data: trendData.map(d => d.orders || 0),
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Ricavi (€)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Numero Ordini'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// Grafico categorie
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(d => d.name),
        datasets: [{
            data: categoryData.map(d => d.revenue || 0),
            backgroundColor: categoryData.map(d => d.color),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Funzioni export
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open('?' + params.toString(), '_blank');
}

function printReport() {
    window.print();
}

// Auto-refresh ogni 5 minuti
setInterval(() => {
    location.reload();
}, 300000);
</script>

<style>
@media print {
    .no-print { display: none !important; }
    body { font-size: 12px; }
    .container { max-width: none; margin: 0; padding: 0; }
}
</style>