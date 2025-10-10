<?php
require_once 'config/database.php';

$db = getDB();

// Statistiche per la home (opzionali, pronte per futuri widget)
$stats = [
    'total_products' => $db->query("SELECT COUNT(*) as count FROM products WHERE active = 1")->fetch()['count'],
    'total_categories' => $db->query("SELECT COUNT(*) as count FROM categories WHERE active = 1")->fetch()['count'],
    'today_orders' => $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = DATE('now')")->fetch()['count'],
    'pending_orders' => $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch()['count']
];

?>

<div class="min-h-screen bg-gray-50">
    

    <!-- Collegamenti rapidi -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Collegamenti Rapidi</h2>
            <p class="text-gray-600">Accedi in un clic alle sezioni principali.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Vendite -->
            <a href="<?= asset_path('sales.php') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-indigo-500 text-white rounded-md flex items-center justify-center"><i class="fas fa-shopping-cart"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Vendite</p>
                            <p class="text-base font-medium text-gray-900">Cassa e checkout</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Ordini -->
            <a href="<?= asset_path('orders.php') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-md flex items-center justify-center"><i class="fas fa-clipboard-list"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Ordini</p>
                            <p class="text-base font-medium text-gray-900">Gestione e stato</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Tabellone -->
            <a href="<?= asset_path('tabellone.php') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-yellow-500 text-white rounded-md flex items-center justify-center"><i class="fas fa-tv"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Tabellone</p>
                            <p class="text-base font-medium text-gray-900">Schermo ordini pronti</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Admin Dashboard -->
            <a href="<?= asset_path('index.php?route=admin&page=dashboard') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-800 text-white rounded-md flex items-center justify-center"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Admin</p>
                            <p class="text-base font-medium text-gray-900">Dashboard</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Prodotti -->
            <a href="<?= asset_path('index.php?route=admin&page=products') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-600 text-white rounded-md flex items-center justify-center"><i class="fas fa-box"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Admin</p>
                            <p class="text-base font-medium text-gray-900">Prodotti</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Categorie -->
            <a href="<?= asset_path('index.php?route=admin&page=categories') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-600 text-white rounded-md flex items-center justify-center"><i class="fas fa-tags"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Admin</p>
                            <p class="text-base font-medium text-gray-900">Categorie</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Report -->
            <a href="<?= asset_path('index.php?route=admin&page=reports') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-700 text-white rounded-md flex items-center justify-center"><i class="fas fa-chart-bar"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Admin</p>
                            <p class="text-base font-medium text-gray-900">Report</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Spese Generali -->
            <a href="<?= asset_path('index.php?route=admin&page=general_expenses') ?>" class="block bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-600 text-white rounded-md flex items-center justify-center"><i class="fas fa-receipt"></i></div>
                        <div>
                            <p class="text-sm text-gray-600">Admin</p>
                            <p class="text-base font-medium text-gray-900">Spese Generali</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>
        </div>
    </div>
</div>