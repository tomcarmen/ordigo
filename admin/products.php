<?php
// Debug diretto nella pagina
echo "<!-- DEBUG: POST data: " . print_r($_POST, true) . " -->";
echo "<!-- DEBUG: GET data: " . print_r($_GET, true) . " -->";
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$productId = $_GET['id'] ?? $_POST['id'] ?? null;
echo "<!-- DEBUG: Action: " . $action . ", ProductId: " . $productId . " -->";
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

$db = Database::getInstance();

// Gestione eliminazione (GET request)
if ($action === 'delete' && $productId) {
    echo "<!-- DEBUG: Tentativo eliminazione prodotto ID: " . $productId . " -->";
    $stmt = $db->query("DELETE FROM products WHERE id = ?", [$productId]);
    echo "<!-- DEBUG: Query eseguita, righe eliminate: " . $stmt->rowCount() . " -->";
    $message = "Prodotto eliminato definitivamente dal database!";
    $messageType = "success";
    $action = 'list'; // Reset action per mostrare la lista
}

// Gestione form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $categoryId = intval($_POST['category_id']) ?: null;
        $stockQuantity = intval($_POST['stock_quantity']);
        $minStockLevel = intval($_POST['min_stock_level']);
        $imageUrl = trim($_POST['image_url']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if ($action === 'add') {
            $stmt = $db->query("
                INSERT INTO products (name, description, price, category_id, stock_quantity, min_stock_level, image_url, active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [$name, $description, $price, $categoryId, $stockQuantity, $minStockLevel, $imageUrl, $active]);
            
            $message = "Prodotto aggiunto con successo!";
            $messageType = "success";
        } else {
            $stmt = $db->query("
                UPDATE products 
                SET name = ?, description = ?, price = ?, category_id = ?, stock_quantity = ?, min_stock_level = ?, image_url = ?, active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [$name, $description, $price, $categoryId, $stockQuantity, $minStockLevel, $imageUrl, $active, $productId]);
            
            $message = "Prodotto aggiornato con successo!";
            $messageType = "success";
        }
        
        // Non fare redirect quando incluso, mostra solo il messaggio
        $action = 'list'; // Reset action per mostrare la lista
    }
    
    if ($action === 'update_stock' && $productId) {
        $newStock = intval($_POST['new_stock']);
        $stmt = $db->query("UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$newStock, $productId]);
        $message = "Scorta aggiornata con successo!";
        $messageType = "success";
        $action = 'list'; // Reset action per mostrare la lista
    }

    // Gestione offerte: aggiunta
    if ($action === 'add_offer') {
        $offerProductId = intval($_POST['offer_product_id'] ?? 0);
        if ($offerProductId) {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $discountType = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
            $discountValue = floatval($_POST['discount_value'] ?? 0);
            $minQuantity = intval($_POST['min_quantity'] ?? 1);
            $maxQuantity = ($_POST['max_quantity'] !== '' && $_POST['max_quantity'] !== null) ? intval($_POST['max_quantity']) : null;
            $startDate = trim($_POST['start_date'] ?? '') ?: null;
            $endDate = trim($_POST['end_date'] ?? '') ?: null;
            $active = isset($_POST['active']) ? 1 : 0;

            if ($title !== '' && $discountValue > 0) {
                $db->query(
                    "INSERT INTO stock_offers (product_id, title, description, discount_type, discount_value, min_quantity, max_quantity, start_date, end_date, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$offerProductId, $title, $description, $discountType, $discountValue, $minQuantity, $maxQuantity, $startDate, $endDate, $active]
                );
                $message = "Offerta aggiunta con successo!";
                $messageType = "success";
            } else {
                $message = "Titolo e valore sconto sono obbligatori.";
                $messageType = "error";
            }
        } else {
            $message = "Prodotto non valido per l'offerta.";
            $messageType = "error";
        }
        // Resta nella pagina di modifica del prodotto
        $productId = $offerProductId ?: $productId;
        $action = 'edit';
    }

    // Gestione offerte: eliminazione
    if ($action === 'delete_offer') {
        $offerId = intval($_POST['offer_id'] ?? 0);
        $offerProductId = intval($_POST['offer_product_id'] ?? 0);
        if ($offerId) {
            $db->query("DELETE FROM stock_offers WHERE id = ?", [$offerId]);
            $message = "Offerta eliminata con successo!";
            $messageType = "success";
        } else {
            $message = "Offerta non trovata.";
            $messageType = "error";
        }
        $productId = $offerProductId ?: $productId;
        $action = 'edit';
    }
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

// Messaggi
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}
?>

<?php if (isset($message)): ?>
<div class="mb-4 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Lista Prodotti -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Gestione Prodotti</h3>
                <div class="flex space-x-3">
                    <a href="?route=admin&page=products&action=add" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>Nuovo Prodotto
                    </a>
                    <input type="text" id="search-products" placeholder="Cerca prodotti..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <select id="filter-category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php
            $products = $db->query("
                SELECT p.*, c.name as category_name, c.color as category_color
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                ORDER BY p.name
            ")->fetchAll();
            ?>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="products-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prodotto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prezzo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scorta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                        <tr data-category="<?= $product['category_id'] ?>" data-name="<?= strtolower($product['name']) ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if ($product['image_url']): ?>
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full object-cover" src="<?= htmlspecialchars($product['image_url']) ?>" alt="">
                                    </div>
                                    <?php else: ?>
                                    <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded-full flex items-center justify-center">
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: <?= $product['category_color'] ?>20; color: <?= $product['category_color'] ?>;">
                                    <?= htmlspecialchars($product['category_name']) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-sm text-gray-500">Nessuna</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">€ <?= number_format($product['price'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-medium <?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-red-600' : 'text-gray-900' ?>">
                                        <?= $product['stock_quantity'] ?>
                                    </span>
                                    <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                    <i class="fas fa-exclamation-triangle text-yellow-500" title="Scorta bassa"></i>
                                    <?php endif; ?>
                                    <button onclick="openStockModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['stock_quantity'] ?>)" class="text-blue-600 hover:text-blue-900 text-xs">
                                        <i class="fas fa-edit"></i>
                                    </button>
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
                                        <a href="?route=admin&page=products&action=edit&id=<?= $product['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit mr-1"></i>Modifica
                                        </a>
                                <button type="button" 
                                        onclick="if(confirm('Sei sicuro di voler eliminare il prodotto &quot;<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>&quot;?')) { window.location.href='?route=admin&page=products&action=delete&id=<?= $product['id'] ?>'; }"
                                        class="text-red-600 hover:text-red-900 bg-transparent border-0 p-0 cursor-pointer">
                                    <i class="fas fa-trash mr-1"></i>Elimina
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Aggiungi/Modifica Prodotto -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                <?= $action === 'add' ? 'Aggiungi Nuovo Prodotto' : 'Modifica Prodotto' ?>
            </h3>
            
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nome Prodotto *</label>
                        <input type="text" name="name" id="name" required 
                               value="<?= htmlspecialchars($product['name'] ?? '') ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                        <select name="category_id" id="category_id" 
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona categoria</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= ($product['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Prezzo (€) *</label>
                        <input type="number" name="price" id="price" step="0.01" min="0" required 
                               value="<?= $product['price'] ?? '' ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700">Quantità in Scorta *</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required 
                               value="<?= $product['stock_quantity'] ?? '0' ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="min_stock_level" class="block text-sm font-medium text-gray-700">Scorta Minima *</label>
                        <input type="number" name="min_stock_level" id="min_stock_level" min="0" required 
                               value="<?= $product['min_stock_level'] ?? '5' ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="image_url" class="block text-sm font-medium text-gray-700">URL Immagine</label>
                        <input type="url" name="image_url" id="image_url" 
                               value="<?= htmlspecialchars($product['image_url'] ?? '') ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <textarea name="description" id="description" rows="3" 
                              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
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
        </div>
    </div>

    <?php if ($action === 'edit' && $product): ?>
    <!-- Sezione Offerte per il prodotto -->
    <div class="bg-white shadow rounded-lg mt-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                <i class="fas fa-percent text-primary mr-2"></i>Offerte per "<?= htmlspecialchars($product['name']) ?>"
            </h3>
            <?php 
            $offers = $db->query("SELECT * FROM stock_offers WHERE product_id = ? ORDER BY active DESC, start_date DESC", [$product['id']])->fetchAll();
            ?>

            <div class="overflow-x-auto mb-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titolo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sconto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantità</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periodo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($offers)): ?>
                            <?php foreach ($offers as $offer): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($offer['title']) ?></div>
                                        <?php if (!empty($offer['description'])): ?>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($offer['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm">
                                            <?= $offer['discount_type'] === 'fixed' ? '€ ' . number_format($offer['discount_value'], 2) : number_format($offer['discount_value'], 2) . '%' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?= intval($offer['min_quantity']) ?><?= $offer['max_quantity'] ? ' - ' . intval($offer['max_quantity']) : '+' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?= $offer['start_date'] ? htmlspecialchars(substr($offer['start_date'], 0, 10)) : '-' ?> → <?= $offer['end_date'] ? htmlspecialchars(substr($offer['end_date'], 0, 10)) : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($offer['active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Attiva
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Non attiva
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" onsubmit="return confirm('Eliminare questa offerta?');" class="inline">
                                            <input type="hidden" name="action" value="delete_offer">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <input type="hidden" name="offer_product_id" value="<?= $product['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash mr-1"></i>Elimina
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-500" colspan="6">Nessuna offerta configurata per questo prodotto.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Form aggiungi nuova offerta -->
            <h4 class="text-md font-semibold text-gray-900 mb-3">Aggiungi nuova offerta</h4>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_offer">
                <input type="hidden" name="offer_product_id" value="<?= $product['id'] ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Titolo *</label>
                        <input type="text" name="title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo di sconto *</label>
                        <select name="discount_type" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="percentage">Percentuale (%)</option>
                            <option value="fixed">Importo fisso (€)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valore sconto *</label>
                        <input type="number" step="0.01" min="0" name="discount_value" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantità minima *</label>
                        <input type="number" min="1" name="min_quantity" value="1" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantità massima</label>
                        <input type="number" min="1" name="max_quantity" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="active" id="offer_active" value="1" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="offer_active" class="ml-2 block text-sm text-gray-900">Offerta attiva</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <textarea name="description" rows="2" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data inizio</label>
                        <input type="date" name="start_date" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data fine</label>
                        <input type="date" name="end_date" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Aggiungi Offerta
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal Aggiorna Scorta -->
<div id="stock-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Aggiorna Scorta</h3>
            <form id="stock-form" method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="id" id="stock-product-id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prodotto</label>
                    <p id="stock-product-name" class="text-sm text-gray-600"></p>
                </div>
                
                <div class="mb-4">
                    <label for="new_stock" class="block text-sm font-medium text-gray-700">Nuova Quantità</label>
                    <input type="number" name="new_stock" id="new_stock" min="0" required 
                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeStockModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Annulla
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Aggiorna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Ricerca e filtri
document.getElementById('search-products')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#products-table tbody tr');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(searchTerm) ? '' : 'none';
    });
});

document.getElementById('filter-category')?.addEventListener('change', function() {
    const categoryId = this.value;
    const rows = document.querySelectorAll('#products-table tbody tr');
    
    rows.forEach(row => {
        const rowCategory = row.dataset.category;
        row.style.display = (!categoryId || rowCategory === categoryId) ? '' : 'none';
    });
});

// Modal scorta
function openStockModal(productId, productName, currentStock) {
    document.getElementById('stock-product-id').value = productId;
    document.getElementById('stock-product-name').textContent = productName;
    document.getElementById('new_stock').value = currentStock;
    document.getElementById('stock-modal').classList.remove('hidden');
}

function closeStockModal() {
    document.getElementById('stock-modal').classList.add('hidden');
}

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
</script>