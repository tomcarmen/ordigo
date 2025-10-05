<?php
/**
 * Proiettore Ordini - OrdiGO
 * Visualizzazione in tempo reale per cucina/bar
 */
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

// Recupero statistiche in tempo reale
function getRealtimeStats($db) {
    try {
        // Ordini oggi
        $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(created_at) = DATE('now')");
        $stmt->execute();
        $todayOrders = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prodotti con scorte basse
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level");
        $stmt->execute();
        $lowStock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ordini in attesa
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
        $stmt->execute();
        $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ricavi mensili
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')");
        $stmt->execute();
        $monthlyRevenue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'todayOrders' => $todayOrders,
            'lowStock' => $lowStock,
            'pendingOrders' => $pendingOrders,
            'monthlyRevenue' => $monthlyRevenue
        ];
    } catch (Exception $e) {
        return [
            'todayOrders' => ['count' => 0, 'total' => 0],
            'lowStock' => ['count' => 0],
            'pendingOrders' => ['count' => 0],
            'monthlyRevenue' => ['total' => 0]
        ];
    }
}

// Recupero prodotti più venduti
function getTopProducts($db, $limit = 5) {
    try {
        $stmt = $db->prepare("
            SELECT p.name, p.selling_price, COALESCE(SUM(oi.quantity), 0) as total_sold
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= date('now', '-30 days')
            GROUP BY p.id, p.name, p.selling_price
            ORDER BY total_sold DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Recupero ordini recenti
function getRecentOrders($db, $limit = 10) {
    try {
        $stmt = $db->prepare("
            SELECT id, customer_name, total_amount, status, created_at
            FROM orders
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Recupero dati per grafici
function getChartData($db) {
    try {
        // Vendite ultimi 7 giorni
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE created_at >= date('now', '-7 days')
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute();
        $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Vendite per categoria
        $stmt = $db->prepare("
            SELECT c.name, c.color, COALESCE(SUM(oi.total_price), 0) as revenue
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= date('now', '-30 days')
            GROUP BY c.id, c.name, c.color
            ORDER BY revenue DESC
        ");
        $stmt->execute();
        $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'dailySales' => $dailySales,
            'categoryStats' => $categoryStats
        ];
    } catch (Exception $e) {
        return [
            'dailySales' => [],
            'categoryStats' => []
        ];
    }
}

$stats = getRealtimeStats($db);
$topProducts = getTopProducts($db);
$recentOrders = getRecentOrders($db);
$chartData = getChartData($db);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrdiGO - Dashboard Proiettore</title>
    <link rel="stylesheet" href="../assets/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Stili ottimizzati per proiettore */
        body {
            background: linear-gradient(135deg, #4f46e5 0%, #7e22ce 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .projector-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .projector-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 50px rgba(0, 0, 0, 0.2);
        }
        
        .stat-number {
            font-size: 3.2rem;
            font-weight: 800;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.08);
            line-height: 1.1;
        }
        
        .stat-label {
            font-size: 1.3rem;
            font-weight: 600;
            opacity: 0.9;
            letter-spacing: -0.01em;
        }
        
        .chart-container {
            position: relative;
            height: 320px;
        }
        
        .ticker {
            animation: scroll-left 35s linear infinite;
            white-space: nowrap;
        }
        
        @keyframes scroll-left {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        .status-indicator {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.8);
        }
        
        .status-pending { background-color: #f59e0b; }
        .status-completed { background-color: #10b981; }
        .status-cancelled { background-color: #ef4444; }
        
        /* Effetto glow per elementi importanti */
        .glow-effect {
            box-shadow: 0 0 15px rgba(79, 70, 229, 0.4);
        }
        
        /* Animazione fade-in per elementi */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        /* Responsive per diversi tipi di proiettore */
        @media (min-width: 1920px) {
            .stat-number { font-size: 4.2rem; }
            .stat-label { font-size: 1.6rem; }
        }
        
        @media (max-width: 1366px) {
            .stat-number { font-size: 2.8rem; }
            .stat-label { font-size: 1.1rem; }
        }
    </style>
</head>
<body class="min-h-screen p-6 text-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header con logo e ora -->
    <div class="projector-card rounded-xl p-6 mb-8 glow-effect fade-in">
        <div class="flex flex-col sm:flex-row justify-between items-center">
            <div class="flex items-center space-x-5 mb-4 sm:mb-0">
                <div class="bg-gradient-to-br from-indigo-500 to-purple-700 text-white p-4 rounded-xl shadow-lg">
                    <i class="fas fa-chart-bar text-3xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-700">OrdiGO Dashboard</h1>
                    <p class="text-gray-600 text-lg">Sistema di Gestione Ordini in Tempo Reale</p>
                </div>
            </div>
            <div class="text-center sm:text-right">
                <div id="current-time" class="text-3xl font-bold text-indigo-700"></div>
                <div id="current-date" class="text-gray-600 text-lg"></div>
                <div class="flex items-center justify-center sm:justify-end mt-3">
                    <div id="connection-status" class="status-indicator bg-green-500 pulse-animation"></div>
                    <span class="text-sm font-medium text-gray-700">Sistema Online</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche principali -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Ordini oggi -->
        <div class="projector-card rounded-xl p-6 text-center shadow-lg fade-in" style="animation-delay: 0.1s;">
            <div class="bg-gradient-to-br from-emerald-400 to-green-600 text-white p-4 rounded-xl shadow-lg mb-4 mx-auto w-16 h-16 flex items-center justify-center">
                <i class="fas fa-shopping-cart text-2xl"></i>
            </div>
            <div class="stat-number text-emerald-600"><?= $stats['todayOrders']['count'] ?></div>
            <div class="stat-label text-gray-700 mb-1">Ordini Oggi</div>
            <div class="text-lg font-bold text-emerald-600 mt-2">
                €<?= number_format($stats['todayOrders']['total'], 2) ?>
            </div>
        </div>

        <!-- Scorte basse -->
        <div class="projector-card rounded-xl p-6 text-center shadow-lg fade-in" style="animation-delay: 0.2s;">
            <div class="bg-gradient-to-br from-red-400 to-red-600 text-white p-4 rounded-xl shadow-lg mb-4 mx-auto w-16 h-16 flex items-center justify-center pulse-animation">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="stat-number text-red-600"><?= $stats['lowStock']['count'] ?></div>
            <div class="stat-label text-gray-700 mb-1">Scorte Basse</div>
            <div class="text-sm font-semibold text-red-600 mt-2">Richiede Attenzione</div>
        </div>

        <!-- Ordini in attesa -->
        <div class="projector-card rounded-xl p-6 text-center shadow-lg fade-in" style="animation-delay: 0.3s;">
            <div class="bg-gradient-to-br from-amber-400 to-yellow-600 text-white p-4 rounded-xl shadow-lg mb-4 mx-auto w-16 h-16 flex items-center justify-center">
                <i class="fas fa-clock text-2xl"></i>
            </div>
            <div class="stat-number text-amber-600"><?= $stats['pendingOrders']['count'] ?></div>
            <div class="stat-label text-gray-700 mb-1">In Attesa</div>
            <div class="text-sm font-semibold text-amber-600 mt-2">Da Processare</div>
        </div>

        <!-- Ricavi mensili -->
        <div class="projector-card rounded-xl p-6 text-center shadow-lg fade-in" style="animation-delay: 0.4s;">
            <div class="bg-gradient-to-br from-indigo-400 to-blue-600 text-white p-4 rounded-xl shadow-lg mb-4 mx-auto w-16 h-16 flex items-center justify-center">
                <i class="fas fa-euro-sign text-2xl"></i>
            </div>
            <div class="stat-number text-indigo-600">€<?= number_format($stats['monthlyRevenue']['total'], 0) ?></div>
            <div class="stat-label text-gray-700 mb-1">Ricavi Mese</div>
            <div class="text-sm font-semibold text-indigo-600 mt-2">Corrente</div>
        </div>
    </div>

    <!-- Grafici e dati -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-8 mb-8">
        <!-- Grafico vendite giornaliere -->
        <div class="projector-card rounded-xl p-6 shadow-lg fade-in" style="animation-delay: 0.7s;">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                    Vendite Ultimi 7 Giorni
                </h3>
                <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">Trend</span>
            </div>
            <div class="chart-container">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>

        <!-- Grafico vendite per categoria -->
        <div class="projector-card rounded-xl p-6 shadow-lg fade-in" style="animation-delay: 0.8s;">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-pie text-purple-500 mr-3"></i>
                    Vendite per Categoria
                </h3>
                <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-3 py-1 rounded-full">Distribuzione</span>
            </div>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Prodotti più venduti e ordini recenti -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-8 mb-8">
        <!-- Prodotti più venduti -->
        <div class="projector-card rounded-xl p-6 shadow-lg fade-in" style="animation-delay: 0.5s;">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-trophy text-yellow-500 mr-3"></i>
                Top Prodotti (30gg)
            </h3>
            <div class="space-y-3">
                <?php foreach ($topProducts as $index => $product): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg ring-1 ring-gray-200 hover:bg-gray-100 transition">
                    <div class="flex items-center space-x-3">
                        <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="text-sm text-gray-600">€<?= number_format($product['selling_price'] ?? 0, 2) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-lg text-blue-600"><?= $product['total_sold'] ?></div>
                        <div class="text-xs text-gray-500">venduti</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Ordini recenti -->
        <div class="projector-card rounded-xl p-6 shadow-lg fade-in" style="animation-delay: 0.6s;">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-list text-green-500 mr-3"></i>
                Ordini Recenti
            </h3>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                <?php foreach ($recentOrders as $order): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg ring-1 ring-gray-200 hover:bg-gray-100 transition">
                    <div class="flex items-center space-x-3">
                        <div class="status-indicator status-<?= $order['status'] ?>"></div>
                        <div>
                            <div class="font-semibold text-gray-800">#<?= $order['id'] ?></div>
                            <div class="text-sm text-gray-600"><?= htmlspecialchars($order['customer_name']) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-green-600">€<?= number_format($order['total_amount'], 2) ?></div>
                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($order['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Ticker informazioni -->
    <div class="projector-card rounded-2xl p-4 overflow-x-auto ring-1 ring-gray-200/60 shadow-lg">
        <div class="ticker text-lg font-semibold text-gray-700">
            <span class="mr-8">📊 Dashboard aggiornata in tempo reale</span>
            <span class="mr-8">🔄 Sincronizzazione automatica ogni 30 secondi</span>
            <span class="mr-8">📱 Compatibile con tutti i dispositivi</span>
            <span class="mr-8">🎯 Ottimizzato per proiezione</span>
            <span class="mr-8">⚡ Sistema OrdiGO - Gestione Ordini Professionale</span>
        </div>
    </div>

    <script>
        // Aggiornamento ora in tempo reale
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('it-IT');
            document.getElementById('current-date').textContent = now.toLocaleDateString('it-IT', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Configurazione grafici
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        font: { size: 14 },
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    ticks: { font: { size: 12 } }
                },
                x: {
                    ticks: { font: { size: 12 } }
                }
            }
        };

        // Grafico vendite giornaliere
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($chartData['dailySales'], 'date')) ?>,
                datasets: [{
                    label: 'Ordini',
                    data: <?= json_encode(array_column($chartData['dailySales'], 'orders')) ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Ricavi (€)',
                    data: <?= json_encode(array_column($chartData['dailySales'], 'revenue')) ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    ...chartOptions.scales,
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        ticks: { font: { size: 12 } }
                    }
                }
            }
        });

        // Grafico categorie
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($chartData['categoryStats'], 'name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($chartData['categoryStats'], 'revenue')) ?>,
                    backgroundColor: <?= json_encode(array_map(function($cat) { 
                        return $cat['color'] ?: '#6B7280'; 
                    }, $chartData['categoryStats'])) ?>,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                ...chartOptions,
                cutout: '60%'
            }
        });

        // Auto-refresh ogni 30 secondi
        setInterval(() => {
            location.reload();
        }, 30000);

        // Aggiornamento ora ogni secondo
        setInterval(updateTime, 1000);
        updateTime();

        // Gestione fullscreen
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F11') {
                e.preventDefault();
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else {
                    document.exitFullscreen();
                }
            }
        });

        // Monitoraggio connessione
        window.addEventListener('online', () => {
            document.getElementById('connection-status').className = 'status-indicator bg-green-500';
        });

        window.addEventListener('offline', () => {
            document.getElementById('connection-status').className = 'status-indicator bg-red-500';
        });

        console.log('OrdiGO Projector Dashboard loaded successfully');
    </script>
</div>
</body>
</html>