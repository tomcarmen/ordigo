<?php
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$productId = $_GET['id'] ?? $_POST['id'] ?? null;
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

$db = Database::getInstance();

// Gestione eliminazione (GET request)
if ($action === 'delete' && $productId) {
    $stmt = $db->query("DELETE FROM products WHERE id = ?", [$productId]);
    $message = "Prodotto eliminato definitivamente dal database!";
    $messageType = "success";
    $action = 'list'; // Reset action per mostrare la lista
}

// Gestione form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
        $purchasePrice = isset($_POST['purchase_price']) ? floatval(str_replace(',', '.', (string)$_POST['purchase_price'])) : 0.0;
        $sellingPrice = isset($_POST['selling_price']) ? floatval(str_replace(',', '.', (string)$_POST['selling_price'])) : $purchasePrice;
        $categoryId = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $stockQuantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
        $minStockLevel = isset($_POST['min_stock_level']) ? intval($_POST['min_stock_level']) : 0;
        $imageUrl = isset($_POST['image_url']) ? trim((string)$_POST['image_url']) : '';
        $active = isset($_POST['active']) ? 1 : 0;
        $fromModal = isset($_POST['from_modal']) && $_POST['from_modal'] === '1';

        // Upload immagine opzionale
        if (!empty($_FILES['image_file']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/products';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $originalName = $_FILES['image_file']['name'] ?? ('product_' . time());
            $safeBase = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $filename = $safeBase . '_' . time() . ($ext ? ('.' . $ext) : '');
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (@move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                // URL pubblico relativo alla base dell'app (no leading slash)
                $imageUrl = 'uploads/products/' . $filename;
            }
        }
        
        if ($action === 'add') {
            // Controllo duplicati: stesso nome nella stessa categoria (case-insensitive)
            if ($categoryId === null) {
                $existsStmt = $db->query(
                    "SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND category_id IS NULL",
                    [$name]
                );
            } else {
                $existsStmt = $db->query(
                    "SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND category_id = ?",
                    [$name, $categoryId]
                );
            }
            if ($existsStmt && $existsStmt->fetch()) {
                $message = "Prodotto duplicato.";
                $messageType = "error";
                // Se la richiesta proviene dalla modale, riapri la modale mostrando il messaggio
                if ($fromModal) {
                    header("Location: ?route=admin&page=products&openModal=addProduct&message=" . urlencode($message) . "&type=" . urlencode($messageType));
                } else {
                    header("Location: ?route=admin&page=products&action=add&message=" . urlencode($message) . "&type=" . urlencode($messageType));
                }
                exit;
            }
            $stmt = $db->query("
                INSERT INTO products (name, description, purchase_price, selling_price, category_id, stock_quantity, min_stock_level, image_url, active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$name, $description, $purchasePrice, $sellingPrice, $categoryId, $stockQuantity, $minStockLevel, $imageUrl, $active]);
            // Salva aggiunte (extras) per il nuovo prodotto
            $newProductId = $db->lastInsertId();
            if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                $extras = $_POST['extras'];
                $names = $extras['name'] ?? [];
                $pp = $extras['purchase_price'] ?? [];
                $sp = $extras['selling_price'] ?? [];
                $sq = $extras['stock_quantity'] ?? [];
                $ms = $extras['min_stock_level'] ?? [];
                $count = is_array($names) ? count($names) : 0;
                $seen = [];
                for ($i = 0; $i < $count; $i++) {
                    $n = isset($names[$i]) ? trim((string)$names[$i]) : '';
                    $key = strtolower($n);
                    if ($n === '' || isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $db->query("INSERT INTO product_extras (product_id, name, purchase_price, selling_price, stock_quantity, min_stock_level, active) VALUES (?, ?, ?, ?, ?, ?, 1)", [
                        $newProductId,
                        $n,
                        isset($pp[$i]) ? floatval(str_replace(',', '.', (string)$pp[$i])) : 0,
                        isset($sp[$i]) ? floatval(str_replace(',', '.', (string)$sp[$i])) : 0,
                        isset($sq[$i]) ? intval($sq[$i]) : 0,
                        isset($ms[$i]) ? intval($ms[$i]) : 0
                    ]);
                }
            }
            // Salva offerte stock per il nuovo prodotto
            if (isset($_POST['offers']) && is_array($_POST['offers'])) {
                $offers = $_POST['offers'];
                $qtys = $offers['quantity'] ?? [];
                $prices = $offers['offer_price'] ?? [];
                $countOffers = is_array($qtys) ? count($qtys) : 0;
                $seenQty = [];
                for ($i = 0; $i < $countOffers; $i++) {
                    $q = isset($qtys[$i]) ? intval($qtys[$i]) : 0;
                    $p = isset($prices[$i]) ? floatval(str_replace(',', '.', (string)$prices[$i])) : 0.0;
                    if ($q <= 0 || $p <= 0) continue;
                    $key = (string)$q;
                    if (isset($seenQty[$key])) continue;
                    $seenQty[$key] = true;
                    $db->query("INSERT INTO product_offers (product_id, quantity, offer_price, active) VALUES (?, ?, ?, 1)", [
                        $newProductId,
                        $q,
                        $p
                    ]);
                }
            }
            
            $message = "Prodotto aggiunto con successo!";
            $messageType = "success";
            // Post/Redirect/Get lato client per evitare reinvio su refresh
            $redirectUrl = '?route=admin&page=products&message=' . urlencode($message) . '&type=' . urlencode($messageType);
            // Se proviene dalla modale, lascia chiusa (successo) ma potremmo voler mostrare toast
            if ($fromModal) {
                // Non riapriamo la modale su successo, ma manteniamo il messaggio nella lista
                // Niente openModal qui
            }
            header("Location: " . $redirectUrl);
            exit;
        } else {
            // Controllo duplicati in modifica: esclude l'ID corrente (case-insensitive)
            $pid = intval($productId ?? 0);
            if ($categoryId === null) {
                $existsStmt = $db->query(
                    "SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND category_id IS NULL AND id != ?",
                    [$name, $pid]
                );
            } else {
                $existsStmt = $db->query(
                    "SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND category_id = ? AND id != ?",
                    [$name, $categoryId, $pid]
                );
            }
            if ($existsStmt && $existsStmt->fetch()) {
                $message = "Prodotto duplicato.";
                $messageType = "error";
                header("Location: ?route=admin&page=products&action=edit&id=" . urlencode((string)$pid) . "&message=" . urlencode($message) . "&type=" . urlencode($messageType));
                exit;
            }
            $stmt = $db->query("
                UPDATE products 
                SET name = ?, description = ?, purchase_price = ?, selling_price = ?, category_id = ?, stock_quantity = ?, min_stock_level = ?, image_url = ?, active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [$name, $description, $purchasePrice, $sellingPrice, $categoryId, $stockQuantity, $minStockLevel, $imageUrl, $active, $productId]);

            // Aggiorna aggiunte (extras): cancella e reinserisci dalla form
            $pid = intval($productId ?? 0);
            $db->query("DELETE FROM product_extras WHERE product_id = ?", [$pid]);
            if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                $extras = $_POST['extras'];
                $names = $extras['name'] ?? [];
                $pp = $extras['purchase_price'] ?? [];
                $sp = $extras['selling_price'] ?? [];
                $sq = $extras['stock_quantity'] ?? [];
                $ms = $extras['min_stock_level'] ?? [];
                $count = is_array($names) ? count($names) : 0;
                $seen = [];
                for ($i = 0; $i < $count; $i++) {
                    $n = isset($names[$i]) ? trim((string)$names[$i]) : '';
                    $key = strtolower($n);
                    if ($n === '' || isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $db->query("INSERT INTO product_extras (product_id, name, purchase_price, selling_price, stock_quantity, min_stock_level, active) VALUES (?, ?, ?, ?, ?, ?, 1)", [
                        $pid,
                        $n,
                        isset($pp[$i]) ? floatval(str_replace(',', '.', (string)$pp[$i])) : 0,
                        isset($sp[$i]) ? floatval(str_replace(',', '.', (string)$sp[$i])) : 0,
                        isset($sq[$i]) ? intval($sq[$i]) : 0,
                        isset($ms[$i]) ? intval($ms[$i]) : 0
                    ]);
                }
            }
            // Aggiorna offerte stock: cancella e reinserisci dalla form
            $db->query("DELETE FROM product_offers WHERE product_id = ?", [$pid]);
            if (isset($_POST['offers']) && is_array($_POST['offers'])) {
                $offers = $_POST['offers'];
                $qtys = $offers['quantity'] ?? [];
                $prices = $offers['offer_price'] ?? [];
                $countOffers = is_array($qtys) ? count($qtys) : 0;
                $seenQty = [];
                for ($i = 0; $i < $countOffers; $i++) {
                    $q = isset($qtys[$i]) ? intval($qtys[$i]) : 0;
                    $p = isset($prices[$i]) ? floatval(str_replace(',', '.', (string)$prices[$i])) : 0.0;
                    if ($q <= 0 || $p <= 0) continue;
                    $key = (string)$q;
                    if (isset($seenQty[$key])) continue;
                    $seenQty[$key] = true;
                    $db->query("INSERT INTO product_offers (product_id, quantity, offer_price, active) VALUES (?, ?, ?, 1)", [
                        $pid,
                        $q,
                        $p
                    ]);
                }
            }

            $message = "Prodotto aggiornato con successo!";
            $messageType = "success";
            // Dopo aggiornamento, torna alla lista per chiudere la modale
            $redirectUrl = '?route=admin&page=products&message=' . urlencode($message) . '&type=' . urlencode($messageType);
            header("Location: " . $redirectUrl);
            exit;
        }
        
        // Non fare redirect quando incluso, mostra solo il messaggio
        $action = 'list'; // Reset action per mostrare la lista
    }
    
    // Rimosso: aggiornamento scorta via grid

    // Gestione offerte rimossa su richiesta: nessuna azione per add_offer/delete_offer
}

// Carica categorie per select
$categories = $db->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();

// Carica prodotto per modifica
$product = null;
if ($action === 'edit' && $productId) {
    $product = $db->query("SELECT * FROM products WHERE id = ?", [$productId])->fetch();
    if (!$product) {
header("Location: ?route=admin&page=products&message=" . urlencode("Prodotto non trovato!") . "&type=error");
        exit;
    }
}

// Carica aggiunte (extras) per la modifica
$productExtras = [];
if ($action === 'edit' && $productId) {
    $productExtras = $db->query("SELECT * FROM product_extras WHERE product_id = ? ORDER BY name", [$productId])->fetchAll();
}

// Carica offerte (offers) per la modifica
$productOffers = [];
if ($action === 'edit' && $productId) {
    $productOffers = $db->query("SELECT * FROM product_offers WHERE product_id = ? ORDER BY quantity", [$productId])->fetchAll();
}

// Messaggi
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}
// Flag per evidenziare il campo Nome in caso di duplicato
$nameError = ($messageType === 'error' && $message === 'Prodotto duplicato.');
?>

<?php
// Helper per comporre URL asset funzionanti sia in root (/) che in sottocartella (/ordigo)
if (!function_exists('asset_path')) {
    function asset_path($path) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $base = $base ? $base : '';
        // Normalizza e garantisce uno slash singolo tra base e path
        return $base . '/' . ltrim((string)$path, '/\\');
    }
}
?>

<?php if (isset($message) && $message !== ''): ?>
<div class="mb-4 p-4 rounded-lg ring-1 ring-gray-200/60 shadow-sm <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>" aria-live="<?= $messageType === 'error' ? 'assertive' : 'polite' ?>" aria-atomic="true">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Lista Prodotti -->
    <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-box mr-3 text-primary"></i>Gestione Prodotti
                </h1>
                <button type="button" onclick="openAddProductModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md shadow-sm ring-1 ring-inset ring-green-500/30 transition-colors duration-150">
                    <i class="fas fa-plus mr-2"></i>Nuovo Prodotto
                </button>
            </div>

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
                <div class="flex-1 flex items-center gap-3">
                    <input type="text" id="search-products" placeholder="Cerca prodotti..." class="w-full md:w-auto rounded-md px-3 py-2 text-sm border border-gray-300 ring-1 ring-gray-200 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                    <select id="filter-category" class="rounded-md px-3 py-2 text-sm border border-gray-300 ring-1 ring-gray-200 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <!-- spazio per eventuali filtri aggiuntivi -->
                </div>
            </div>

            <?php
            // Statistiche rapide per header Prodotti (simili a Categorie)
            try {
                $totalProducts = (int)($db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'] ?? 0);
                $activeProducts = (int)($db->query("SELECT COUNT(*) as count FROM products WHERE active = 1")->fetch()['count'] ?? 0);
                $totalCategories = (int)($db->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'] ?? 0);
                $activeCategories = (int)($db->query("SELECT COUNT(*) as count FROM categories WHERE active = 1")->fetch()['count'] ?? 0);
            } catch (Exception $e) {
                $totalProducts = $totalProducts ?? 0;
                $activeProducts = $activeProducts ?? 0;
                $totalCategories = $totalCategories ?? 0;
                $activeCategories = $activeCategories ?? 0;
            }
            ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-tags text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Totale Categorie</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $totalCategories ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Categorie Attive</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $activeCategories ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Prodotti Totali</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $totalProducts ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-chart-pie text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Prodotti Attivi</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $activeProducts ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            $products = $db->query("
                SELECT p.*, c.name as category_name, c.color as category_color
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                ORDER BY p.name
            ")->fetchAll();

            // Carica tutte le aggiunte (extras) dei prodotti in un'unica query e indicizzale per product_id
            $extrasByProductId = [];
            if (!empty($products)) {
                $productIds = array_map(function($p){ return $p['id']; }, $products);
                // Costruisci placeholders dinamici per l'IN
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $extrasStmt = $db->query(
                    "SELECT product_id, name, purchase_price, selling_price, stock_quantity, min_stock_level, active 
                     FROM product_extras 
                     WHERE product_id IN (" . $placeholders . ") 
                     ORDER BY name",
                    $productIds
                );
                $allExtras = $extrasStmt ? $extrasStmt->fetchAll() : [];
                foreach ($allExtras as $ex) {
                    $pid = $ex['product_id'];
                    if (!isset($extrasByProductId[$pid])) $extrasByProductId[$pid] = [];
                    $extrasByProductId[$pid][] = $ex;
                }
            }

            // Carica tutte le offerte (offers) dei prodotti e indicizzale per product_id
            $offersByProductId = [];
            if (!empty($products)) {
                $productIds = array_map(function($p){ return $p['id']; }, $products);
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $offersStmt = $db->query(
                    "SELECT product_id, quantity, offer_price, active 
                     FROM product_offers 
                     WHERE product_id IN (" . $placeholders . ") 
                     ORDER BY quantity",
                    $productIds
                );
                $allOffers = $offersStmt ? $offersStmt->fetchAll() : [];
                foreach ($allOffers as $of) {
                    $pid = $of['product_id'];
                    if (!isset($offersByProductId[$pid])) $offersByProductId[$pid] = [];
                    $offersByProductId[$pid][] = $of;
                }
            }
            ?>
            
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 ring-1 ring-inset ring-gray-100">
                    <h3 class="text-lg font-medium text-gray-900">Elenco Prodotti</h3>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="products-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prodotto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prezzi & Margine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scorta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                        <tr data-category="<?= $product['category_id'] ?>" data-name="<?= strtolower($product['name']) ?>" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if ($product['image_url']): ?>
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg overflow-hidden shadow-sm bg-white" style="border: 2px solid <?= htmlspecialchars($product['category_color'] ?? '#D1D5DB') ?>;">
                                        <img class="h-full w-full object-cover" src="<?= htmlspecialchars(asset_path($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    </div>
                                    <?php else: ?>
                                    <div class="flex-shrink-0 h-10 w-10 rounded-lg shadow-sm bg-white flex items-center justify-center" style="border: 2px solid <?= htmlspecialchars($product['category_color'] ?? '#D1D5DB') ?>;">
                                        <i class="fas fa-box text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?><?= strlen($product['description']) > 50 ? '...' : '' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($product['category_name']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium shadow-sm" style="background-color: <?= $product['category_color'] ?>20; color: <?= $product['category_color'] ?>;">
                                    <?= htmlspecialchars($product['category_name']) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-sm text-gray-500">Nessuna</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">€ <?= number_format($product['selling_price'] ?? 0, 2) ?></div>
                                <div class="text-xs text-gray-600">Acq: € <?= number_format($product['purchase_price'] ?? 0, 2) ?></div>
                                <div class="text-xs <?= (($product['selling_price'] ?? 0) - ($product['purchase_price'] ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600' ?>">Margine: € <?= number_format(($product['selling_price'] ?? 0) - ($product['purchase_price'] ?? 0), 2) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-medium <?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-red-600' : 'text-gray-900' ?>">
                                        <?= $product['stock_quantity'] ?>
                                    </span>
                                    <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                    <i class="fas fa-exclamation-triangle text-yellow-500" title="Scorta bassa"></i>
                                    <?php endif; ?>
                                    
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($product['active']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>Attivo
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-times-circle mr-1"></i>Inattivo
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <a href="?route=admin&page=products&action=edit&id=<?= $product['id'] ?>" class="inline-flex items-center p-2 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100">
                                    <i class="fas fa-edit mr-1"></i>Modifica
                                </a>
                                <button type="button" 
                                        onclick="if(confirm('Sei sicuro di voler eliminare il prodotto &quot;<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>&quot;?')) { window.location.href='?route=admin&page=products&action=delete&id=<?= $product['id'] ?>'; }"
                                        class="inline-flex items-center p-2 rounded-md bg-red-50 text-red-600 hover:bg-red-100 border-0 cursor-pointer">
                                    <i class="fas fa-trash mr-1"></i>Elimina
                                </button>
                            </td>
                        </tr>
                        <?php 
                            $extrasForProduct = $extrasByProductId[$product['id']] ?? []; 
                            if (!empty($extrasForProduct)): 
                        ?>
                        <tr class="bg-blue-50">
                            <td colspan="6" class="px-6 py-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">Aggiunte</div>
                                <div class="overflow-x-auto ring-1 ring-gray-200/60 rounded-md bg-blue-50">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aggiunta</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prezzo Acq.</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prezzo Vend.</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scorta</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Allarme</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($extrasForProduct as $extra): 
                                                $lowStock = (($extra['stock_quantity'] ?? 0) <= ($extra['min_stock_level'] ?? 0));
                                            ?>
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($extra['name']) ?></td>
                                                <td class="px-4 py-2 text-sm text-gray-900">€ <?= number_format($extra['purchase_price'] ?? 0, 2) ?></td>
                                                <td class="px-4 py-2 text-sm text-gray-900">€ <?= number_format($extra['selling_price'] ?? 0, 2) ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <span class="font-medium <?= $lowStock ? 'text-red-600' : 'text-gray-900' ?>"><?= (int)($extra['stock_quantity'] ?? 0) ?></span>
                                                    <?php if ($lowStock): ?>
                                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Scorta bassa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-900"><?= (int)($extra['min_stock_level'] ?? 0) ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php if (($extra['active'] ?? 1)): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-check-circle mr-1"></i>Attiva</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fas fa-times-circle mr-1"></i>Inattiva</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php 
                            $offersForProduct = $offersByProductId[$product['id']] ?? []; 
                            if (!empty($offersForProduct)): 
                        ?>
                        <tr class="bg-amber-50">
                            <td colspan="6" class="px-6 py-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">Offerte</div>
                                <div class="overflow-x-auto ring-1 ring-gray-200/60 rounded-md bg-amber-50">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantità</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prezzo Offerta</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($offersForProduct as $offer): ?>
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-gray-900"><?= (int)($offer['quantity'] ?? 0) ?></td>
                                                <td class="px-4 py-2 text-sm text-gray-900">€ <?= number_format($offer['offer_price'] ?? 0, 2) ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php if (($offer['active'] ?? 1)): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-check-circle mr-1"></i>Attiva</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fas fa-times-circle mr-1"></i>Inattiva</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Modal Form Aggiungi/Modifica Prodotto -->
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full p-4 sm:p-0" role="dialog" aria-modal="true" aria-labelledby="<?= $action === 'add' ? 'addPageModalTitle' : 'editPageModalTitle' ?>">
        <div class="relative sm:top-12 mx-auto p-4 sm:p-6 border border-gray-100 w-full max-w-full sm:max-w-5xl shadow-xl rounded-xl bg-white">
            <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                <?= $action === 'add' ? 'Aggiungi Nuovo Prodotto' : 'Modifica Prodotto' ?>
            </h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nome Prodotto *</label>
                        <input type="text" name="name" id="name" required 
                               value="<?= htmlspecialchars($product['name'] ?? '') ?>"
                               class="mt-1 block w-full bg-green-50 border <?= $nameError ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500' ?> rounded-md shadow-sm py-2 px-3 focus:outline-none">
                    </div>
                    
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                        <select name="category_id" id="category_id" 
                                class="mt-1 block w-full bg-green-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona categoria</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= ($product['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="purchase_price" class="block text-sm font-medium text-gray-700">Prezzo Acquisto (€)</label>
                        <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0"
                               value="<?= $product['purchase_price'] ?? '' ?>"
                               class="mt-1 block w-full bg-blue-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="selling_price" class="block text-sm font-medium text-gray-700">Prezzo Vendita (€) *</label>
                        <input type="number" name="selling_price" id="selling_price" step="0.01" min="0" required 
                               value="<?= $product['selling_price'] ?? '' ?>"
                               class="mt-1 block w-full bg-blue-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700">Quantità in Scorta *</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required 
                               value="<?= $product['stock_quantity'] ?? '0' ?>"
                               class="mt-1 block w-full bg-amber-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="min_stock_level" class="block text-sm font-medium text-gray-700">Scorta Minima *</label>
                        <input type="number" name="min_stock_level" id="min_stock_level" min="0" required 
                               value="<?= $product['min_stock_level'] ?? '5' ?>"
                               class="mt-1 block w-full bg-amber-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="image_url" class="block text-sm font-medium text-gray-700">URL Immagine</label>
                        <input type="text" name="image_url" id="image_url" 
                               value="<?= htmlspecialchars($product['image_url'] ?? '') ?>"
                               class="mt-1 block w-full bg-purple-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="image_file" class="block text-sm font-medium text-gray-700">Carica Immagine</label>
                        <input type="file" name="image_file" id="image_file" accept="image/*"
                               class="mt-1 block w-full text-sm bg-purple-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <textarea name="description" id="description" rows="3" 
                              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>
                
                <!-- Sezione Aggiunte (Extras) -->
                <div class="bg-blue-50 ring-1 ring-blue-200/60 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 mb-2">Aggiunte</h4>
                    <div id="extras-container-page" class="space-y-3">
                        <?php if ($action === 'edit' && !empty($productExtras)): ?>
                            <?php foreach ($productExtras as $extra): ?>
                                <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end extras-row">
                                    <div>
                                        <label class="block text-sm text-gray-700">Nome</label>
                                        <input type="text" name="extras[name][]" value="<?= htmlspecialchars($extra['name']) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700">Prezzo Acquisto (€)</label>
                                        <input type="number" step="0.01" min="0" name="extras[purchase_price][]" value="<?= htmlspecialchars((string)($extra['purchase_price'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700">Prezzo Vendita (€)</label>
                                        <input type="number" step="0.01" min="0" name="extras[selling_price][]" value="<?= htmlspecialchars((string)($extra['selling_price'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700">Qtà iniziale</label>
                                        <input type="number" min="0" name="extras[stock_quantity][]" value="<?= htmlspecialchars((string)($extra['stock_quantity'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700">Allarme scorta</label>
                                        <input type="number" min="0" name="extras[min_stock_level][]" value="<?= htmlspecialchars((string)($extra['min_stock_level'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div class="flex justify-end md:justify-start">
                                        <button type="button" class="inline-flex items-center px-2 py-2 bg-red-100 text-red-600 rounded remove-extra-row"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="inline-flex items-center px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded mt-2" onclick="addExtrasRow('extras-container-page')"><i class="fas fa-plus mr-1"></i>Aggiungi aggiunta</button>
                    <p class="text-xs text-gray-500 mt-2">Ogni aggiunta ha prezzo acquisto, prezzo vendita, quantità iniziale e allarme scorta.</p>
                </div>
                
                <!-- Sezione Offerte Stock -->
                <div class="bg-amber-50 ring-1 ring-amber-200/60 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 mb-2">Offerte Stock</h4>
                    <div id="offers-container-page" class="space-y-3">
                        <?php if ($action === 'edit' && !empty($productOffers)): ?>
                            <?php foreach ($productOffers as $offer): ?>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end offers-row">
                                    <div>
                                        <label class="block text-sm text-gray-700">Quantità</label>
                                        <input type="number" min="1" name="offers[quantity][]" value="<?= htmlspecialchars((string)($offer['quantity'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-700">Prezzo Offerta (€)</label>
                                        <input type="number" step="0.01" min="0" name="offers[offer_price][]" value="<?= htmlspecialchars((string)($offer['offer_price'] ?? 0)) ?>" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3">
                                    </div>
                                    <div class="flex justify-end md:justify-start">
                                        <button type="button" class="inline-flex items-center px-2 py-2 bg-red-100 text-red-600 rounded remove-offer-row"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="inline-flex items-center px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded mt-2" onclick="addOffersRow('offers-container-page')"><i class="fas fa-plus mr-1"></i>Aggiungi offerta</button>
                    <p class="text-xs text-gray-500 mt-2">Esempio: 6 pezzi a € 5, 10 pezzi a € 8.</p>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="active" id="active" value="1" 
                           <?= ($product['active'] ?? 1) ? 'checked' : '' ?>
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="active" class="ml-2 block text-sm text-gray-900">Prodotto attivo</label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="?route=admin&page=products" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Annulla
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <?= $action === 'add' ? 'Aggiungi Prodotto' : 'Aggiorna Prodotto' ?>
                    </button>
                </div>
            </form>
            <?php if ($action === 'edit'): ?>
            <script>
            window.extrasInitialDataPage = <?= json_encode($productExtras ?? []) ?>;
            </script>
            <script>
            window.offersInitialDataPage = <?= json_encode($productOffers ?? []) ?>;
            </script>
            <?php else: ?>
            <script>
            window.extrasInitialDataPage = [];
            </script>
            <script>
            window.offersInitialDataPage = [];
            </script>
            <?php endif; ?>
            </div>
        </div>

    <?php if ($action === 'edit' && $product): ?>
    <!-- Sezione Offerte rimossa -->
    <?php endif; ?>
<?php endif; ?>

<!-- Modal Aggiungi Prodotto -->
<div id="add-product-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden p-4 sm:p-0" role="dialog" aria-modal="true" aria-labelledby="addProductModalTitle">
    <div class="relative sm:top-12 mx-auto p-4 sm:p-6 border border-gray-100 w-full max-w-full sm:max-w-2xl shadow-xl rounded-xl bg-white">
        <div class="mt-3">
            <h3 id="addProductModalTitle" class="text-lg font-medium text-gray-900 mb-4">Aggiungi Nuovo Prodotto</h3>
            <?php if (isset($_GET['openModal']) && $_GET['openModal'] === 'addProduct' && isset($_GET['message']) && $_GET['message'] !== ''): ?>
            <div class="mb-4 p-4 rounded-lg ring-1 ring-gray-200/60 shadow-sm <?= ($_GET['type'] ?? '') === 'error' ? 'bg-red-100 text-red-700' : (($_GET['type'] ?? '') === 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700') ?>">
                <?= htmlspecialchars($_GET['message']) ?>
            </div>
            <?php endif; ?>
            <form id="add-product-form" method="POST" enctype="multipart/form-data" action="?route=admin&page=products&action=add" class="space-y-4 sm:space-y-6">
                <input type="hidden" name="from_modal" value="1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="add_name" class="block text-sm font-medium text-gray-700">Nome Prodotto *</label>
                        <input type="text" name="name" id="add_name" required 
                               class="mt-1 block w-full bg-green-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                        <select name="category_id" id="add_category_id" 
                                class="mt-1 block w-full bg-green-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona categoria</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_purchase_price" class="block text-sm font-medium text-gray-700">Prezzo Acquisto (€)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="add_purchase_price"
                               class="mt-1 block w-full bg-blue-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_selling_price" class="block text-sm font-medium text-gray-700">Prezzo Vendita (€) *</label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="add_selling_price" required 
                               class="mt-1 block w-full bg-blue-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_stock_quantity" class="block text-sm font-medium text-gray-700">Quantità in Scorta *</label>
                        <input type="number" min="0" name="stock_quantity" id="add_stock_quantity" required 
                               class="mt-1 block w-full bg-amber-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_min_stock_level" class="block text-sm font-medium text-gray-700">Livello Minimo Scorta</label>
                        <input type="number" min="0" name="min_stock_level" id="add_min_stock_level" 
                               class="mt-1 block w-full bg-amber-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_image_url" class="block text-sm font-medium text-gray-700">URL Immagine</label>
                        <input type="text" name="image_url" id="add_image_url" 
                               class="mt-1 block w-full bg-purple-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="add_image_file" class="block text-sm font-medium text-gray-700">Carica Immagine</label>
                        <input type="file" name="image_file" id="add_image_file" accept="image/*"
                               class="mt-1 block w-full text-sm bg-purple-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label for="add_description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <textarea name="description" id="add_description" rows="3" 
                              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <!-- Sezione Aggiunte (Extras) nella modale -->
                <div class="bg-blue-50 ring-1 ring-blue-200/60 rounded-lg p-3 sm:p-4">
                    <h4 class="text-md font-medium text-gray-900 mb-2">Aggiunte</h4>
                    <div id="extras-container-add-modal" class="space-y-3"></div>
                    <button type="button" class="inline-flex items-center px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded mt-2" onclick="addExtrasRow('extras-container-add-modal')"><i class="fas fa-plus mr-1"></i>Aggiungi aggiunta</button>
                    <p class="text-xs text-gray-500 mt-2">Ogni aggiunta ha prezzo acquisto, prezzo vendita, quantità iniziale e allarme scorta.</p>
                </div>
                <!-- Sezione Offerte Stock nella modale -->
                <div class="bg-amber-50 ring-1 ring-amber-200/60 rounded-lg p-3 sm:p-4">
                    <h4 class="text-md font-medium text-gray-900 mb-2">Offerte Stock</h4>
                    <div id="offers-container-add-modal" class="space-y-3"></div>
                    <button type="button" class="inline-flex items-center px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded mt-2" onclick="addOffersRow('offers-container-add-modal')"><i class="fas fa-plus mr-1"></i>Aggiungi offerta</button>
                    <p class="text-xs text-gray-500 mt-2">Definisci offerte: quantità e prezzo totale scontato.</p>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="active" id="add_active" value="1" checked 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="add_active" class="ml-2 block text-sm text-gray-900">Prodotto attivo</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddProductModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Aggiungi Prodotto</button>
                </div>
            </form>
        </div>
    </div>
    
</div>



<script>
// Ricerca e filtri
document.getElementById('search-products')?.addEventListener('input', function() {
    const searchTerm = (this.value || '').toLowerCase();
    const rows = document.querySelectorAll('#products-table tbody tr');
    let lastProductVisible = true; // visibilità dell'ultima riga prodotto incontrata

    rows.forEach(row => {
        const nameAttr = row.getAttribute('data-name');
        if (nameAttr !== null) {
            const name = nameAttr.toLowerCase();
            const visible = name.includes(searchTerm);
            row.style.display = visible ? '' : 'none';
            lastProductVisible = visible;
        } else {
            // Righe accessorie (offerte, extras) seguono la visibilità della riga prodotto precedente
            row.style.display = lastProductVisible ? '' : 'none';
        }
    });
});

document.getElementById('filter-category')?.addEventListener('change', function() {
    const categoryId = this.value;
    const rows = document.querySelectorAll('#products-table tbody tr');
    let lastProductVisible = true; // visibilità dell'ultima riga prodotto incontrata

    rows.forEach(row => {
        const hasCategory = row.hasAttribute('data-category');
        if (hasCategory) {
            const rowCategory = row.getAttribute('data-category') || '';
            const visible = (!categoryId || rowCategory === categoryId);
            row.style.display = visible ? '' : 'none';
            lastProductVisible = visible;
        } else {
            // Righe accessorie seguono la visibilità della riga prodotto precedente
            row.style.display = lastProductVisible ? '' : 'none';
        }
    });
});

// Focusable selectors condivisi
const focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
// Funzionalità modale scorta rimossa su richiesta

// Conferma eliminazione - FUNZIONE NON PIÙ UTILIZZATA
// function confirmDelete(productId, productName) {
//     if (confirm(`Sei sicuro di voler eliminare il prodotto "${productName}"?`)) {
//         const form = document.createElement('form');
//         form.method = 'POST';
//         form.innerHTML = `
//             <input type="hidden" name="action" value="delete">
//             <input type="hidden" name="id" value="${productId}">
//         `;
//         document.body.appendChild(form);
//         form.submit();
//     }
// }

// Modal Aggiungi Prodotto: accessibile con focus trap, ESC e ripristino focus al trigger
let addProductTrigger = null;
let addProductKeydownHandler = null;

// Persistenza dati modale via sessionStorage
function saveAddProductFormData() {
    const data = {
        name: document.getElementById('add_name')?.value || '',
        category_id: document.getElementById('add_category_id')?.value || '',
        purchase_price: document.getElementById('add_purchase_price')?.value || '',
        selling_price: document.getElementById('add_selling_price')?.value || '',
        stock_quantity: document.getElementById('add_stock_quantity')?.value || '',
        min_stock_level: document.getElementById('add_min_stock_level')?.value || '',
        image_url: document.getElementById('add_image_url')?.value || '',
        description: document.getElementById('add_description')?.value || '',
        active: document.getElementById('add_active')?.checked ? '1' : '0'
    };
    try { sessionStorage.setItem('addProductFormData', JSON.stringify(data)); } catch (e) {}
}

function populateAddProductFormFromStorage() {
    let raw = null;
    try { raw = sessionStorage.getItem('addProductFormData'); } catch (e) {}
    if (!raw) return;
    let data = {};
    try { data = JSON.parse(raw); } catch (e) { return; }
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    setVal('add_name', data.name);
    setVal('add_category_id', data.category_id);
    setVal('add_purchase_price', data.purchase_price);
    setVal('add_selling_price', data.selling_price);
    setVal('add_stock_quantity', data.stock_quantity);
    setVal('add_min_stock_level', data.min_stock_level);
    setVal('add_image_url', data.image_url);
    const descEl = document.getElementById('add_description'); if (descEl) descEl.value = data.description ?? '';
    const activeEl = document.getElementById('add_active'); if (activeEl) activeEl.checked = (data.active === '1');
}

function trapFocusInAddProductModal() {
    const modal = document.getElementById('add-product-modal');
    if (!modal) return;
    const focusable = modal.querySelectorAll(focusableSelectors);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    addProductKeydownHandler = function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        } else if (e.key === 'Escape') {
            closeAddProductModal();
        }
    };

    modal.addEventListener('keydown', addProductKeydownHandler);
}

function openAddProductModal() {
    const modal = document.getElementById('add-product-modal');
    addProductTrigger = document.activeElement;
    modal.classList.remove('hidden');
    trapFocusInAddProductModal();
    const firstInput = document.getElementById('add_name');
    if (firstInput) firstInput.focus();
    // Se la modale è aperta dalla lista (senza openModal in query), pulisci dati salvati
    try {
      const params = new URLSearchParams(window.location.search);
      if (!params.get('openModal')) {
        sessionStorage.removeItem('addProductFormData');
      }
    } catch (e) { /* silenzioso */ }
    // Popola dai dati salvati se presenti
    populateAddProductFormFromStorage();
}

function closeAddProductModal() {
    const modal = document.getElementById('add-product-modal');
    modal.classList.add('hidden');
    if (addProductKeydownHandler) {
        modal.removeEventListener('keydown', addProductKeydownHandler);
        addProductKeydownHandler = null;
    }
    if (addProductTrigger && typeof addProductTrigger.focus === 'function') {
        addProductTrigger.focus();
    }
    addProductTrigger = null;
}

// Chiudi modal con click su backdrop
document.getElementById('add-product-modal')?.addEventListener('click', (e) => {
    const modal = document.getElementById('add-product-modal');
    if (e.target === modal) {
        closeAddProductModal();
    }
});

// Auto-apertura Add Product modal da query string
(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('openModal') === 'addProduct') {
    // Assicura che la lista sia visibile, non sulla pagina edit/add
    try {
      if (typeof openAddProductModal === 'function') {
        // Attende il prossimo tick per sicurezza
        setTimeout(() => {
          openAddProductModal();
          // Se c'è un errore, mantieni i dati; su successo pulisci storage
          const type = params.get('type');
          if (type === 'success') {
            try { sessionStorage.removeItem('addProductFormData'); } catch (e) {}
          }
        }, 0);
      }
    } catch (e) {}
  }
  // Aggancia salvataggio dati alla modale (input/change/submit)
  const form = document.getElementById('add-product-form');
  if (form) {
    form.addEventListener('input', saveAddProductFormData);
    form.addEventListener('change', saveAddProductFormData);
    form.addEventListener('submit', () => { saveAddProductFormData(); });
  }
})();

// Pulisce dati modale salvati dopo un'operazione conclusa con successo
(function() {
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.get('type') === 'success') {
      sessionStorage.removeItem('addProductFormData');
    }
  } catch (e) { /* silenzioso */ }
})();

// --- Gestione Aggiunte (Extras) ---
function addExtrasRow(containerId, preset) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const row = document.createElement('div');
  row.className = 'grid grid-cols-1 md:grid-cols-6 gap-3 items-end extras-row';
  row.innerHTML = `
    <div>
      <label class="block text-sm text-gray-700">Nome</label>
      <input type="text" name="extras[name][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div>
      <label class="block text-sm text-gray-700">Prezzo Acquisto (€)</label>
      <input type="number" step="0.01" min="0" name="extras[purchase_price][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div>
      <label class="block text-sm text-gray-700">Prezzo Vendita (€)</label>
      <input type="number" step="0.01" min="0" name="extras[selling_price][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div>
      <label class="block text-sm text-gray-700">Qtà iniziale</label>
      <input type="number" min="0" name="extras[stock_quantity][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div>
      <label class="block text-sm text-gray-700">Allarme scorta</label>
      <input type="number" min="0" name="extras[min_stock_level][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div class="flex justify-end md:justify-start">
      <button type="button" class="inline-flex items-center px-2 py-2 bg-red-100 text-red-600 rounded remove-extra-row"><i class="fas fa-trash"></i></button>
    </div>
  `;
  container.appendChild(row);
  // Preset values if provided
  if (preset) {
    const inputs = row.querySelectorAll('input');
    const map = ['name','purchase_price','selling_price','stock_quantity','min_stock_level'];
    inputs.forEach((inp) => {
      const m = map.find(k => inp.name.includes(k));
      if (m && preset[m] !== undefined) inp.value = preset[m];
    });
  }
  // Hook remove button
  row.querySelector('.remove-extra-row')?.addEventListener('click', () => {
    row.remove();
  });
}

// Inizializza extras su pagina edit
(function initExtrasPage() {
  try {
    const data = window.extrasInitialDataPage || [];
    const container = document.getElementById('extras-container-page');
    // Evita duplicazioni: se il server ha già renderizzato le righe, non aggiungere via JS
    const alreadyHasRows = !!(container && container.querySelector('.extras-row'));
    if (container && Array.isArray(data) && data.length && !alreadyHasRows) {
      data.forEach(d => addExtrasRow('extras-container-page', d));
    }
  } catch (e) { /* silenzioso */ }
})();

// Inizializza extras su modale add
(function initExtrasAddModal() {
  const container = document.getElementById('extras-container-add-modal');
  if (!container) return;
  // Nessun preset; l'utente può aggiungere manualmente
})();

// --- Gestione Offerte (Offers) ---
function addOffersRow(containerId, preset) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const row = document.createElement('div');
  row.className = 'grid grid-cols-1 md:grid-cols-4 gap-3 items-end offers-row';
  row.innerHTML = `
    <div>
      <label class="block text-sm text-gray-700">Quantità</label>
      <input type="number" min="1" name="offers[quantity][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div>
      <label class="block text-sm text-gray-700">Prezzo Offerta (€)</label>
      <input type="number" step="0.01" min="0" name="offers[offer_price][]" class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3" />
    </div>
    <div class="flex justify-end md:justify-start">
      <button type="button" class="inline-flex items-center px-2 py-2 bg-red-100 text-red-600 rounded remove-offer-row"><i class="fas fa-trash"></i></button>
    </div>
  `;
  container.appendChild(row);
  // Preset values if provided
  if (preset) {
    const inputs = row.querySelectorAll('input');
    const map = ['quantity','offer_price'];
    inputs.forEach((inp) => {
      const m = map.find(k => inp.name.includes(k));
      if (m && preset[m] !== undefined) inp.value = preset[m];
    });
  }
  // Hook remove button
  row.querySelector('.remove-offer-row')?.addEventListener('click', () => {
    row.remove();
  });
}

// Inizializza offerte su pagina edit
(function initOffersPage() {
  try {
    const data = window.offersInitialDataPage || [];
    const container = document.getElementById('offers-container-page');
    const alreadyHasRows = !!(container && container.querySelector('.offers-row'));
    if (container && Array.isArray(data) && data.length && !alreadyHasRows) {
      data.forEach(d => addOffersRow('offers-container-page', d));
    }
  } catch (e) { /* silenzioso */ }
})();

// Inizializza offerte su modale add
(function initOffersAddModal() {
  const container = document.getElementById('offers-container-add-modal');
  if (!container) return;
})();
</script>