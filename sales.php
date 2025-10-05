<?php
// Buffer e sessione per compatibilità con header/footer
if (!headers_sent()) { @ob_start(); }
@session_start();
// Imposta route corrente per evidenziare correttamente la navigazione
$route = 'sales';
// Helper asset_path: garantisce URL corretti anche se la pagina è aperta direttamente
if (!function_exists('asset_path')) {
    function asset_path($path) {
        $path = (string)$path;
        if ($path === '') return '';
        // Non modificare URL assoluti o data URI
        if (preg_match('/^(https?:|data:|\/\/)/i', $path)) {
            return $path;
        }
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $base = $base ? $base : '';
        // Normalizza e garantisce uno slash singolo tra base e path
        return $base . '/' . ltrim($path, '/\\');
    }
}

require_once __DIR__ . '/config/database.php';
$db = getDB();

// Endpoint AJAX: checkout ordine
if (isset($_GET['ajax']) && $_GET['ajax'] === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload || !isset($payload['items']) || !is_array($payload['items'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payload non valido']);
        exit;
    }

    $orderNumber = isset($payload['order_number']) ? trim($payload['order_number']) : '';
    if ($orderNumber === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Numero comanda obbligatorio']);
        exit;
    }
    $customerName = isset($payload['customer_name']) ? trim($payload['customer_name']) : '';
    $paymentMethod = isset($payload['payment_method']) ? $payload['payment_method'] : 'cash';
    $notes = isset($payload['notes']) ? trim($payload['notes']) : null;
    $items = $payload['items'];

    try {
        $db->beginTransaction();

        // Calcolo totale lato server con prezzi correnti DB e offerte/extras
        $total = 0.0;
        $resolvedItems = [];
        foreach ($items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) { continue; }

            // Leggi prodotto e stock corrente
            $stmt = $db->query("SELECT id, name, selling_price, purchase_price, stock_quantity FROM products WHERE id = ?", [$pid]);
            $prod = $stmt->fetch();
            if (!$prod) { continue; }
            $unit = (float)($prod['selling_price'] ?? 0);
            if ($unit <= 0) { $unit = (float)($prod['purchase_price'] ?? 0); }

            // Verifica disponibilità stock prodotto
            $currentStock = isset($prod['stock_quantity']) ? (int)$prod['stock_quantity'] : null;
            if ($currentStock !== null && $currentStock < $qty) {
                throw new Exception('Stock insufficiente per ' . $prod['name']);
            }

            // Offerte bundle
            $offerId = isset($item['offer_id']) ? (int)$item['offer_id'] : 0;
            $bundleApplied = 0;
            $bundleQty = 0;
            $bundlePrice = 0.0;
            if ($offerId > 0) {
                $stmtOffer = $db->query("SELECT id, quantity, offer_price FROM product_offers WHERE id = ? AND product_id = ? AND active = 1", [$offerId, $pid]);
                $offer = $stmtOffer->fetch();
                if ($offer) {
                    $bundleQty = (int)$offer['quantity'];
                    $bundlePrice = (float)$offer['offer_price'];
                    if ($bundleQty > 0) {
                        $bundleApplied = intdiv($qty, $bundleQty);
                    }
                }
            }

            $remainder = $bundleApplied > 0 ? ($qty - $bundleApplied * $bundleQty) : $qty;
            $lineTotal = ($bundleApplied * $bundlePrice) + ($remainder * $unit);

            // Extras
            $extrasPayload = isset($item['extras']) && is_array($item['extras']) ? $item['extras'] : [];
            $extrasResolved = [];
            $extrasTotal = 0.0;
            foreach ($extrasPayload as $ex) {
                $exId = (int)($ex['id'] ?? 0);
                $exQty = (int)($ex['quantity'] ?? $qty);
                if ($exId <= 0 || $exQty <= 0) continue;
                $stmtEx = $db->query("SELECT id, name, selling_price, stock_quantity FROM product_extras WHERE id = ? AND product_id = ? AND active = 1", [$exId, $pid]);
                $exDB = $stmtEx->fetch();
                if (!$exDB) continue;
                $exUnit = (float)$exDB['selling_price'];
                $exLine = $exUnit * $exQty;
                // Verifica stock extra
                $exStock = isset($exDB['stock_quantity']) ? (int)$exDB['stock_quantity'] : null;
                if ($exStock !== null && $exStock < $exQty) {
                    throw new Exception('Stock aggiunta insufficiente: ' . $exDB['name']);
                }
                $extrasTotal += $exLine;
                $extrasResolved[] = [
                    'id' => (int)$exDB['id'],
                    'name' => $exDB['name'],
                    'unit_price' => $exUnit,
                    'quantity' => $exQty,
                    'total_price' => $exLine,
                ];
            }

            $lineTotal += $extrasTotal;
            $total += $lineTotal;

            $resolvedItems[] = [
                'product_id' => (int)$prod['id'],
                'product_name' => $prod['name'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'total_price' => $lineTotal,
                'offer' => $offerId > 0 ? ['offer_id' => $offerId, 'bundle_qty' => $bundleQty, 'bundle_price' => $bundlePrice, 'bundles_applied' => $bundleApplied] : null,
                'extras' => !empty($extrasResolved) ? json_encode($extrasResolved) : null,
                'extras_raw' => $extrasResolved,
            ];
        }

        if ($total <= 0 || empty($resolvedItems)) {
            throw new Exception('Nessun item valido nel carrello');
        }

        // Inserisce ordine con numero comanda fornito e stato 'preparing'
        $db->query("INSERT INTO orders (order_number, customer_name, total_amount, status, payment_method, notes) VALUES (?, ?, ?, 'preparing', ?, ?)", [
            $orderNumber, $customerName, $total, $paymentMethod, $notes
        ]);
        $orderId = (int)$db->lastInsertId();

        foreach ($resolvedItems as $ri) {
            $db->query("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price, extras) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                $orderId, $ri['product_id'], $ri['product_name'], $ri['quantity'], $ri['unit_price'], $ri['total_price'], $ri['extras']
            ]);
            // Aggiorna stock prodotto
            $db->query("UPDATE products SET stock_quantity = CASE WHEN stock_quantity IS NULL THEN NULL ELSE stock_quantity - ? END WHERE id = ?", [$ri['quantity'], $ri['product_id']]);
            // Aggiorna stock extras
            if (!empty($ri['extras_raw'])) {
                foreach ($ri['extras_raw'] as $exr) {
                    $db->query("UPDATE product_extras SET stock_quantity = CASE WHEN stock_quantity IS NULL THEN NULL ELSE stock_quantity - ? END WHERE id = ?", [
                        (int)$exr['quantity'], (int)$exr['id']
                    ]);
                }
            }
        }

        $db->commit();
        echo json_encode(['ok' => true, 'order_number' => $orderNumber, 'order_id' => $orderId, 'total' => $total]);
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint AJAX: polling stock prodotti ed extras
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stock') {
    header('Content-Type: application/json');
    try {
        $productsStock = $db->query("SELECT id, stock_quantity, min_stock_level FROM products WHERE active = 1")->fetchAll();
        $extrasStock = $db->query("SELECT id, product_id, stock_quantity, min_stock_level FROM product_extras WHERE active = 1")->fetchAll();
        echo json_encode(['ok' => true, 'products' => $productsStock, 'extras' => $extrasStock]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Dati UI: categorie e prodotti
$categories = $db->query("SELECT id, name, color, icon FROM categories WHERE active = 1 ORDER BY name")->fetchAll();
$productsRaw = $db->query("SELECT id, name, description, selling_price, purchase_price, category_id, image_url, stock_quantity, min_stock_level FROM products WHERE active = 1 ORDER BY name")->fetchAll();
// Carica extras e offers
$extrasRaw = $db->query("SELECT id, product_id, name, selling_price, stock_quantity, min_stock_level FROM product_extras WHERE active = 1 ORDER BY name")->fetchAll();
$offersRaw = $db->query("SELECT id, product_id, quantity, offer_price FROM product_offers WHERE active = 1 ORDER BY quantity")->fetchAll();
$extrasByProduct = [];
foreach ($extrasRaw as $ex) {
    $pid = (int)$ex['product_id'];
    if (!isset($extrasByProduct[$pid])) $extrasByProduct[$pid] = [];
    $extrasByProduct[$pid][] = [
        'id' => (int)$ex['id'],
        'name' => $ex['name'],
        'price' => (float)$ex['selling_price'],
        'stock_quantity' => isset($ex['stock_quantity']) ? (int)$ex['stock_quantity'] : 0,
        'min_stock_level' => isset($ex['min_stock_level']) ? (int)$ex['min_stock_level'] : 0,
    ];
}
$offersByProduct = [];
foreach ($offersRaw as $of) {
    $pid = (int)$of['product_id'];
    if (!isset($offersByProduct[$pid])) $offersByProduct[$pid] = [];
    $offersByProduct[$pid][] = [
        'id' => (int)$of['id'],
        'quantity' => (int)$of['quantity'],
        'offer_price' => (float)$of['offer_price'],
    ];
}
$products = array_map(function($p) use ($extrasByProduct, $offersByProduct){
    $unit = isset($p['selling_price']) && (float)$p['selling_price'] > 0 ? (float)$p['selling_price'] : ((float)($p['purchase_price'] ?? 0));
    return [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'description' => $p['description'] ?? '',
        'price' => (float)$unit,
        'category_id' => isset($p['category_id']) ? (int)$p['category_id'] : null,
        'image_url' => $p['image_url'] ?? null,
        'stock_quantity' => isset($p['stock_quantity']) ? (int)$p['stock_quantity'] : 0,
        'min_stock_level' => isset($p['min_stock_level']) ? (int)$p['min_stock_level'] : 0,
        'extras' => array_values($extrasByProduct[(int)$p['id']] ?? []),
        'offers' => array_values($offersByProduct[(int)$p['id']] ?? []),
    ];
}, $productsRaw);

// Template
require_once __DIR__ . '/templates/header.php';
?>

<!-- Header POS rimosso: non visualizzare -->

<div x-data="posApp(<?php echo htmlspecialchars(json_encode($categories), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($products), ENT_QUOTES, 'UTF-8'); ?>)" x-init="init()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <!-- Toolbar filtri e ricerca (card arrotondata con ombra) -->
  <div class="flex flex-wrap items-center gap-3 mb-6 bg-white ring-1 ring-gray-200 rounded-xl shadow-sm px-3 py-2">
    <div class="flex overflow-x-auto gap-2 py-2 pr-2">
      <button @click="selectCategory(null)" :class="{'ring-2 ring-primary': selectedCategory===null}" class="px-3 py-1.5 rounded-full bg-white ring-1 ring-gray-200 hover:ring-primary hover:bg-primary/10 transition shadow-sm whitespace-nowrap font-medium">Tutte</button>
      <template x-for="c in categories" :key="c.id">
        <button @click="selectCategory(c.id)" :class="{'ring-2 ring-primary': selectedCategory===c.id}" class="px-3 py-1.5 rounded-full bg-white ring-1 ring-gray-200 hover:ring-primary hover:bg-primary/10 transition shadow-sm whitespace-nowrap font-medium" :style="`--tw-ring-color: ${c.color || '#60a5fa'}40`" x-text="c.name"></button>
      </template>
    </div>
    <div class="relative w-auto">
      <input type="text" x-model="search" placeholder="Cerca prodotti..." class="w-64 sm:w-80 rounded-xl bg-white ring-1 ring-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary transition shadow-sm" />
      <div class="pointer-events-none absolute right-3 top-2.5 text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M21 20l-5.8-5.8A7 7 0 1014.2 14.2L20 20zM4 10a6 6 0 1112 0 6 6 0 01-12 0z"/></svg>
      </div>
    </div>
    <div class="ml-auto flex items-center gap-2 relative hidden">
      <!-- Badge totale articoli nel carrello (spostato a destra, più grande, bordino verde) -->
      <div class="absolute -top-7 -right-3 z-20" x-show="cartItemCount() > 0" x-transition.scale.origin.top>
        <span class="inline-flex items-center justify-center px-3 py-1.5 rounded-full bg-white text-primary text-sm font-semibold shadow border-2 border-red-500" x-text="cartItemCount()"></span>
      </div>
      <button @click="toggleCart()" :class="{'scale-105': bumpCart}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary hover:bg-primary/90 text-white shadow-lg shadow-black/30 hover:shadow-black/50 focus:outline-none focus:ring-4 focus:ring-black/30 transition duration-200 will-change-transform">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4h-2l-1 2h2l3 9h8l3-7h-12zM7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
        <span x-text="formatCurrency(cartTotal())" class="font-semibold"></span>
      </button>
    </div>
  </div>

  <!-- Bottone carrello flottante in basso a destra -->
  <div class="fixed bottom-5 right-5" :class="cartOpen ? 'z-10' : 'z-50'">
    <div class="relative">
      <button x-ref="cartBtn" @click="toggleCart()" :class="{'scale-105': bumpCart}" class="relative inline-flex items-center gap-3 px-9 py-3.5 rounded-full bg-green-600 hover:bg-green-700 text-white shadow-2xl shadow-green-300 border border-green-700 transition duration-200 will-change-transform">
        <!-- Badge numero prodotti, vicino al bordo del bottone allungato -->
        <div class="absolute -top-5 right-1" x-show="cartItemCount() > 0" x-transition.scale.origin.top>
          <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-white text-primary text-sm font-semibold shadow border-2 border-red-500" x-text="cartItemCount()"></span>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4h-2l-1 2h2l3 9h8l3-7h-12zM7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
        <span x-text="formatCurrency(cartTotal())" class="text-base font-bold"></span>
      </button>
    </div>
  </div>

  <!-- Griglia prodotti -->
  <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-5">
    <template x-for="p in filteredProducts()" :key="p.id">
      <div class="group rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden hover:shadow-md transition duration-300" :class="{'ring-primary': isLowStock(p)}">
        <div class="relative">
          <img :src="productImage(p)" :alt="p.name" class="h-48 w-full object-cover" loading="lazy" onerror="this.onerror=null;this.src='<?= asset_path('icons/icon-192x192.svg') ?>';" />
          <div class="absolute bottom-3 right-3 z-10 inline-flex items-center px-4 py-2 rounded-lg bg-black/70 text-white text-base font-semibold shadow">
            <span x-text="formatCurrency(unitTotal(p))"></span>
          </div>
          <div class="absolute top-3 right-3 inline-flex items-center gap-2">
            <span class="px-4 py-2 rounded-xl text-lg font-semibold" :class="isLowStock(p) ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="stockLabel(p)"></span>
          </div>
          <template x-if="!canAddProduct(p)">
            <span class="absolute inset-x-0 top-0 m-2 inline-flex items-center justify-center px-2 py-1 rounded-md bg-primary/90 text-white text-xs font-semibold shadow">Esaurito</span>
          </template>
        </div>
        <div class="p-5">
          <h3 class="font-semibold text-base line-clamp-2" x-text="p.name"></h3>
          <p class="text-gray-500 text-sm line-clamp-2" x-text="p.description"></p>
          <!-- Selezione offerte -->
          <template x-if="(p.offers||[]).length > 0">
            <div class="mt-3">
              <div class="text-xs text-gray-600 mb-1">Offerte Stock</div>
              <div class="flex flex-wrap gap-2.5">
                <template x-for="of in p.offers" :key="of.id">
                  <label class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1.5 rounded border cursor-pointer" :class="{'bg-gray-100': selectedOffer[p.id]===of.id}">
                    <input type="radio" :name="'offer_'+p.id" :value="of.id" @change="selectedOffer[p.id]=of.id" :checked="selectedOffer[p.id]===of.id" />
                    <span x-text="of.quantity + 'x ' + formatCurrency(of.offer_price)"></span>
                  </label>
                </template>
              </div>
            </div>
          </template>

          <!-- Selezione aggiunte -->
          <template x-if="(p.extras||[]).length > 0">
            <div class="mt-3">
              <div class="text-xs text-gray-600 mb-1">Aggiunte</div>
              <div class="space-y-1.5">
                <template x-for="ex in p.extras" :key="ex.id">
                  <label class="flex items-center justify-between text-sm px-3 py-2 rounded border transition" :class="{'bg-blue-50 border-blue-300': (selectedExtras[p.id]||[]).some(e=>e.id===ex.id)}">
                    <div class="flex items-center gap-2">
                      <input type="checkbox" :disabled="ex.stock_quantity===0" @change="toggleExtra(p.id, ex)" :checked="(selectedExtras[p.id]||[]).some(e=>e.id===ex.id)" />
                      <span x-text="ex.name"></span>
                    </div>
                    <div class="flex items-center gap-3">
                      <span class="font-medium" x-text="formatCurrency(ex.price)"></span>
                      <span class="px-2.5 py-1.5 rounded-lg text-base font-medium" :class="isLowStockExtra(ex) ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="(ex.stock_quantity ?? 0)"></span>
                    </div>
                  </label>
                </template>
              </div>
            </div>
          </template>

          <div class="mt-4 flex items-center gap-2.5">
            <button @click="confirmAdd(p, $event)" :disabled="!canAddProduct(p) || (!selectedOffer[p.id] && getPendingSingles(p)===0)" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary hover:bg-primary/90 disabled:bg-gray-300 text-white transition duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 4h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>
              <span>Aggiungi</span>
            </button>
            <div class="inline-flex items-center rounded-xl border overflow-hidden">
              <button @click="decPendingSingles(p)" class="h-10 w-10 bg-gray-50 hover:bg-gray-100 transition">-</button>
              <div class="px-4 text-base min-w-[3rem] text-center" x-text="getPendingSingles(p)"></div>
              <button @click="incPendingSingles(p)" class="h-10 w-10 bg-gray-50 hover:bg-gray-100 transition">+</button>
            </div>
          </div>
          
        </div>
      </div>
    </template>
  </div>

  <!-- Drawer carrello -->
  <div class="fixed inset-0 z-40" x-show="cartOpen" x-transition.opacity>
    <div class="absolute inset-0 bg-black/40" @click="toggleCart()"></div>
    <div class="absolute right-0 top-0 h-full w-full sm:w-[420px] bg-white shadow-xl flex flex-col" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
      <div class="p-4 border-b flex items-center justify-between">
        <h2 class="font-bold text-lg">Carrello</h2>
        <button @click="toggleCart()" class="rounded-md p-2 hover:bg-gray-100">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-4 space-y-3">
        <template x-if="cart.length === 0">
          <p class="text-gray-500">Il carrello è vuoto</p>
        </template>
        <template x-for="item in cart" :key="item.id + '-' + (item.offer_id || 'none') + '-' + ((item.extras||[]).map(e=>e.id).sort().join('_'))">
          <div class="flex items-center gap-3 p-3 rounded-lg border">
            <img :src="productImage(item)" class="h-12 w-12 rounded object-cover" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <div class="inline-flex items-center gap-2">
                  <span class="font-semibold text-sm" x-text="item.name"></span>
                  <template x-if="item.offer_id">
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800" x-text="offerLabel(item)"></span>
                  </template>
                  <template x-if="!item.offer_id && (products.find(pp=>pp.id===item.id)?.offers?.length>0)">
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-primary text-white">Singolo</span>
                  </template>
                </div>
                <span class="text-sm" x-text="formatCurrency(itemTotal(item))"></span>
              </div>
              <template x-if="item.extras && item.extras.length>0">
                <div class="mt-1 text-xs text-gray-600">
                  <template x-for="ex in item.extras" :key="ex.id">
                    <div class="flex items-center justify-between">
                      <span x-text="ex.name"></span>
                      <span x-text="formatCurrency(ex.price * (ex.quantity||1) * item.quantity)"></span>
                    </div>
                  </template>
                </div>
              </template>
              <div class="mt-2 flex items-center gap-2">
                <button @click="changeQty(item, -1)" class="h-8 w-8 rounded bg-gray-100 hover:bg-gray-200">-</button>
                <input type="number" min="1" x-model.number="item.quantity" class="w-16 text-center border rounded" />
                <button @click="changeQty(item, 1)" class="h-8 w-8 rounded bg-gray-100 hover:bg-gray-200">+</button>
                <button @click="removeItem(item)" class="ml-auto text-primary hover:underline text-sm">Rimuovi</button>
              </div>
            </div>
          </div>
        </template>
      </div>
      <div class="border-t p-4 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-gray-600">Totale</span>
          <span class="font-bold" x-text="formatCurrency(cartTotal())"></span>
        </div>
        <div class="flex items-center gap-3">
          <button @click="clearCart()" :disabled="cart.length===0" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6h18v2H3zm2 4h14l-1.5 9.5a2 2 0 01-2 1.5h-7a2 2 0 01-2-1.5L5 10zm5-6h4l1 2H9l1-2z"/></svg>
            <span>Svuota carrello</span>
          </button>
        </div>
        <button @click="openCheckoutModal()" :disabled="cart.length===0" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12h14v2H5zm0-4h14v2H5zm0 8h14v2H5z"/></svg>
          <span>Conferma e incassa</span>
        </button>
        <p class="text-xs text-gray-500">Al confermare, l’ordine viene inserito nel DB.</p>
      </div>
    </div>
  </div>
  <!-- Modal checkout: Step 2 dettagli ordine -->
  <div class="fixed inset-0 z-[60]" x-cloak x-show="checkoutModalOpen" x-transition.opacity>
    <div class="absolute inset-0 bg-black/50" @click="checkoutModalOpen=false"></div>
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[92%] max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-gray-200">
      <div class="p-4 border-b flex items-center justify-between">
        <h3 class="font-bold text-lg">Dettagli pagamento</h3>
        <button @click="checkoutModalOpen=false" class="rounded-md p-2 hover:bg-gray-100" aria-label="Chiudi">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="p-4 space-y-3">
        <div>
          <label class="text-sm text-gray-700">Numero comanda</label>
          <input type="text" x-model="orderNumber" placeholder="Numero comanda (obbligatorio)" class="mt-1 w-full rounded border px-3 py-2" />
        </div>
        <div>
          <label class="text-sm text-gray-700">Tipo di pagamento</label>
          <div class="mt-1 flex items-center gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="radio" x-model="paymentMethod" value="cash" /> <span>Contanti</span></label>
            <label class="inline-flex items-center gap-2"><input type="radio" x-model="paymentMethod" value="card" /> <span>Bancomat</span></label>
            <label class="inline-flex items-center gap-2"><input type="radio" x-model="paymentMethod" value="digital" /> <span>Satispay</span></label>
          </div>
        </div>
        <div>
          <label class="text-sm text-gray-700">Nome cliente</label>
          <input type="text" x-model="customerName" placeholder="Nome cliente (opzionale)" class="mt-1 w-full rounded border px-3 py-2" />
        </div>
        <div class="pt-2 flex items-center justify-between">
          <span class="text-gray-600">Totale</span>
          <span class="font-bold" x-text="formatCurrency(cartTotal())"></span>
        </div>
        <div class="flex items-center justify-end gap-2 pt-1">
          <button @click="checkoutModalOpen=false" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annulla</button>
          <button @click="checkout()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12h14v2H5zm0-4h14v2H5zm0 8h14v2H5z"/></svg>
            <span>Conferma</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function posApp(categories, products){
  return {
    categories: categories || [],
    products: products || [],
    selectedCategory: null,
    search: '',
    cart: [],
    cartOpen: false,
    paymentMethod: 'cash',
    customerName: '',
    orderNumber: '',
    selectedOffer: {},
    selectedExtras: {},
    pendingSingles: {},
    bumpCart: false,
    checkoutModalOpen: false,

    init(){
      // Ripristina carrello
      try {
        const saved = sessionStorage.getItem('pos_cart');
        if (saved) this.cart = JSON.parse(saved);
      } catch (e) {}
      // Polling stock
      this.pollStock();
      this._pollTimer = setInterval(()=>this.pollStock(), 8000);
    },

    persist(){
      sessionStorage.setItem('pos_cart', JSON.stringify(this.cart));
    },

    selectCategory(id){
      this.selectedCategory = id;
    },

    filteredProducts(){
      const q = this.search.trim().toLowerCase();
      return this.products.filter(p => {
        const catOK = this.selectedCategory == null || p.category_id === this.selectedCategory;
        const qOK = !q || (p.name.toLowerCase().includes(q) || (p.description||'').toLowerCase().includes(q));
        return catOK && qOK;
      });
    },

    productImage(p){
      const base = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') ?>';
      let url = p && p.image_url ? p.image_url : 'icons/icon-192x192.svg';
      // Non prefissare URL assoluti o data URI
      if (/^(https?:|data:|\/\/)/i.test(url)) return url;
      url = String(url).replace(/^\/+/, '');
      return base + '/' + url;
    },

    isLowStock(p){
      const s = typeof p.stock_quantity === 'number' ? p.stock_quantity : null;
      const m = typeof p.min_stock_level === 'number' ? p.min_stock_level : null;
      if (s === null || m === null) return false;
      return s <= m;
    },

    isLowStockExtra(ex){
      const s = typeof ex.stock_quantity === 'number' ? ex.stock_quantity : null;
      const m = typeof ex.min_stock_level === 'number' ? ex.min_stock_level : null;
      if (s === null || m === null) return false;
      return s <= m;
    },

    stockLabel(p){
      const s = typeof p.stock_quantity === 'number' ? p.stock_quantity : null;
      if (s === null) return 'Scorta N/D';
      return s > 0 ? `${s}` : 'Esaurito';
    },

    canAddProduct(p){
      const s = typeof p.stock_quantity === 'number' ? p.stock_quantity : null;
      return s === null ? true : s > 0;
    },

    // Confronta variante per un item: stessa offerta e stesse aggiunte (ids)
    _extrasKey(list){
      try { return (list||[]).map(e=>e.id).sort((a,b)=>a-b).join('-'); } catch(e){ return ''; }
    },
    _sameVariant(item, offerId, extrasList){
      const a = this._extrasKey(item && item.extras || []);
      const b = this._extrasKey(extrasList || []);
      const io = item && (item.offer_id||null);
      const oo = offerId || null;
      return io === oo && a === b;
    },

    // Helper: quantità e prezzo dell'offerta selezionata
    getOffer(pid, offerId){
      try {
        const p = this.products.find(pp => pp.id === pid);
        if (!p || !Array.isArray(p.offers)) return null;
        return p.offers.find(o => o.id === offerId) || null;
      } catch(e){ return null; }
    },
    getOfferQty(pid, offerId){
      const o = this.getOffer(pid, offerId);
      const q = o && (o.quantity || o.qty);
      const n = Number(q || 0);
      return Number.isFinite(n) && n > 0 ? n : 1;
    },
    getOfferPrice(pid, offerId){
      const o = this.getOffer(pid, offerId);
      const v = o && (o.offer_price ?? o.price);
      const n = Number(v || 0);
      return Number.isFinite(n) && n >= 0 ? n : 0;
    },

    offerLabel(i){
      try {
        if (!i || !i.offer_id) return '';
        const q = this.getOfferQty(i.id, i.offer_id);
        const p = this.getOfferPrice(i.id, i.offer_id);
        return `${q}x ${this.formatCurrency(p)}`;
      } catch(e){ return ''; }
    },

    // Quantità per aggiunte selezionate (extras)
    getExtraQty(pid, exId){
      // Con quantità fissa a 1, restituiamo sempre 1
      return 1;
    },
    incExtraQty(pid, exId, stock){
      // Quantità extras fissa a 1: nessuna azione
    },
    decExtraQty(pid, exId){
      // Quantità extras fissa a 1: nessuna azione
    },

    addToCart(p, delta, evt, forceSingles = false){
      const step = delta || 1;
      // Determina variante corrente (offerta + aggiunte selezionate)
      const selectedOfferId = this.selectedOffer[p.id] || null;
      const offerId = forceSingles ? null : selectedOfferId;
      const extrasList = (this.selectedExtras[p.id]||[]).map(e=>({id:e.id,name:e.name,price:e.price,quantity:1}));
      // Trova item esistente con stessa variante
      let item = this.cart.find(i => i.id === p.id && this._sameVariant(i, offerId, extrasList));
      const currentQty = item ? (item.quantity||0) : 0;
      const packQty = offerId ? this.getOfferQty(p.id, offerId) : 1;
      const desiredQty = Math.max(0, currentQty + (step * packQty));
      // Verifica stock prodotto considerando tutte le varianti nel carrello
      const stock = typeof p.stock_quantity === 'number' ? p.stock_quantity : null;
      if (stock !== null) {
        const totalExisting = this.cart.filter(i => i.id === p.id).reduce((s,i)=> s + (i.quantity||0), 0);
        const proposedTotal = totalExisting - currentQty + desiredQty;
        if (proposedTotal > stock){
          alert(`Scorta insufficiente per ${p.name}. ${stock}`);
          return;
        }
      }
      if (!item){
        item = { id: p.id, name: p.name, price: p.price, quantity: 0, image_url: p.image_url, offer_id: offerId, extras: extrasList };
        this.cart.push(item);
      }
      // Sincronizza sempre le aggiunte/quantità della variante
      item.extras = extrasList;
      item.quantity = desiredQty;
      if (item.quantity === 0){
        this.cart = this.cart.filter(i => !(i.id === p.id && this._sameVariant(i, offerId, extrasList)));
      }
      this.persist();
      // Se il click proviene dal bottone "Aggiungi" e abbiamo aggiunto un bundle, azzera la selezione offerta
      if (evt && offerId) {
        this.selectedOffer[p.id] = null;
      }
      // Microanimazione: bump sul bottone carrello
      if (step > 0) {
        this.bumpCart = true;
        setTimeout(() => { this.bumpCart = false; }, 250);
        // Animazione di volo verso il carrello (con foto prodotto)
        try {
          this.animateToCart(evt, p);
        } catch(e) {}
      }
    },

    animateToCart(evt, p){
      try {
        const cartBtn = this.$refs.cartBtn;
        if (!cartBtn) return;
        const card = evt && evt.currentTarget ? evt.currentTarget.closest('.group') : null;
        const imgEl = card ? card.querySelector('img') : null;
        const originRect = (imgEl || (evt && evt.currentTarget) || cartBtn).getBoundingClientRect();
        const targetRect = cartBtn.getBoundingClientRect();
        const fallbackSrc = "<?= asset_path('icons/icon-192x192.svg') ?>";
        const src = (imgEl && imgEl.src) || (p && p.image_url) || fallbackSrc;
        const ghost = document.createElement('img');
        ghost.src = src;
        ghost.style.position = 'fixed';
        const size = Math.min(64, Math.max(48, originRect.width * 0.25));
        ghost.style.width = `${size}px`;
        ghost.style.height = `${size}px`;
        ghost.style.objectFit = 'cover';
        ghost.style.borderRadius = '12px';
        ghost.style.left = `${originRect.left + originRect.width/2 - size/2}px`;
        ghost.style.top = `${originRect.top + originRect.height/2 - size/2}px`;
        ghost.style.boxShadow = '0 10px 24px rgba(0,0,0,0.25)';
        ghost.style.border = '2px solid #16a34a';
        ghost.style.zIndex = '9999';
        ghost.style.pointerEvents = 'none';
        document.body.appendChild(ghost);
        const dx = (targetRect.left + targetRect.width/2) - (originRect.left + originRect.width/2);
        const dy = (targetRect.top + targetRect.height/2) - (originRect.top + originRect.height/2);
        const dist = Math.hypot(dx, dy);
        const lift = Math.max(80, Math.min(160, dist * 0.25));
        const side = dx >= 0 ? -1 : 1; // piega leggermente a sinistra/destra
        const hx = lift * 0.35 * side;
        const anim = ghost.animate([
          { transform: 'translate(0,0) scale(1) rotate(0deg)', opacity: 1, offset: 0, easing: 'ease-out' },
          { transform: `translate(${dx*0.25 + hx}px, ${dy*0.25 - lift*0.8}px) scale(0.95) rotate(8deg)`, opacity: 0.9, offset: 0.25, easing: 'ease-in-out' },
          { transform: `translate(${dx*0.5 + hx*0.6}px, ${dy*0.5 - lift}px) scale(0.85) rotate(15deg)`, opacity: 0.8, offset: 0.5 },
          { transform: `translate(${dx*0.75 + hx*0.2}px, ${dy*0.75 - lift*0.35}px) scale(0.6) rotate(6deg)`, opacity: 0.3, offset: 0.75, easing: 'ease-in' },
          { transform: `translate(${dx}px, ${dy}px) scale(0.35) rotate(0deg)`, opacity: 0.05, offset: 1 }
        ], { duration: 1000, easing: 'linear' });
        anim.onfinish = () => ghost.remove();
      } catch(e) {}
    },

    changeQty(item, delta){
      const p = this.products.find(pp => pp.id === item.id);
      const step = delta || 0;
      const desired = Math.max(1, (item.quantity || 1) + step);
      const stock = p && typeof p.stock_quantity === 'number' ? p.stock_quantity : null;
      if (stock !== null && desired > stock){
        alert(`Scorta insufficiente. ${stock}`);
        return;
      }
      item.quantity = desired;
      this.persist();
    },

    removeItem(item){
      const offerId = item.offer_id || null;
      const extrasList = (item.extras||[]).map(e=>({id:e.id,name:e.name,price:e.price,quantity:1}));
      this.cart = this.cart.filter(i => !(i.id === item.id && this._sameVariant(i, offerId, extrasList)));
      this.persist();
    },

    cartItemCount(){
      return this.cart.reduce((sum, i) => sum + (i.quantity || 0), 0);
    },

    cartTotal(){
      return this.cart.reduce((sum, i) => {
        const qty = i.quantity || 0;
        let base = 0;
        if (i.offer_id) {
          const packQty = this.getOfferQty(i.id, i.offer_id);
          const packPrice = this.getOfferPrice(i.id, i.offer_id);
          const packs = Math.floor(qty / packQty);
          const remainder = qty % packQty;
          base = (packs * packPrice) + (remainder * i.price);
        } else {
          base = i.price * qty;
        }
        const extras = (i.extras||[]).reduce((s,e)=> s + (e.price * (e.quantity||1) * qty), 0);
        return sum + base + extras;
      }, 0);
    },

    itemTotal(i){
      const qty = i.quantity || 0;
      let base = 0;
      if (i.offer_id) {
        const packQty = this.getOfferQty(i.id, i.offer_id);
        const packPrice = this.getOfferPrice(i.id, i.offer_id);
        const packs = Math.floor(qty / packQty);
        const remainder = qty % packQty;
        base = (packs * packPrice) + (remainder * i.price);
      } else {
        base = i.price * qty;
      }
      const extras = (i.extras||[]).reduce((s,e)=> s + (e.price * (e.quantity||1) * qty), 0);
      return base + extras;
    },

    unitTotal(p){
      const offerId = this.selectedOffer[p.id] || null;
      if (offerId) {
        const packQty = this.getOfferQty(p.id, offerId);
        const packPrice = this.getOfferPrice(p.id, offerId);
        const extras = (this.selectedExtras[p.id]||[]).reduce((s,e)=> s + ((e.price||0) * packQty), 0);
        return (packPrice || 0) + extras;
      }
      const base = p.price || 0;
      const extras = (this.selectedExtras[p.id]||[]).reduce((s,e)=> s + ((e.price||0) * 1), 0);
      return base + extras;
    },

    // Selettore quantità pendente per aggiunta singoli
    getPendingSingles(p){
      try { return Math.max(0, Number(this.pendingSingles[p.id] || 0)); } catch(e){ return 0; }
    },
    setPendingSingles(p, val){
      const n = Math.max(0, Number(val || 0));
      this.pendingSingles[p.id] = n;
    },
    incPendingSingles(p){
      const cur = this.getPendingSingles(p);
      this.setPendingSingles(p, cur + 1);
    },
    decPendingSingles(p){
      const cur = this.getPendingSingles(p);
      this.setPendingSingles(p, Math.max(0, cur - 1));
    },
    confirmAdd(p, evt){
      const offerId = this.selectedOffer[p.id] || null;
      if (offerId){
        // Se è selezionata un'offerta, aggiunge un pacchetto
        this.addToCart(p, 1, evt, false);
        return;
      }
      const qty = this.getPendingSingles(p);
      if (qty > 0){
        // Aggiunge la quantità selezionata di singoli
        this.addToCart(p, qty, evt, true);
        // Reset dopo l'aggiunta
        this.setPendingSingles(p, 0);
      }
    },

    inCartQty(p){
      return this.cart.filter(i => i.id === p.id).reduce((sum,i)=> sum + (i.quantity||0), 0);
    },

    formatCurrency(v){
      try { return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(v || 0); } catch(e){ return (v||0).toFixed(2); }
    },

    toggleCart(){ this.cartOpen = !this.cartOpen; },

    clearCart(){
      this.cart = [];
      this.persist();
    },

    toggleExtra(pid, ex){
      const list = this.selectedExtras[pid] || [];
      const idx = list.findIndex(e => e.id === ex.id);
      if (idx >= 0) {
        list.splice(idx,1);
      } else {
        // Quantità extras fissa a 1
        list.push({ id: ex.id, name: ex.name, price: ex.price, quantity: 1 });
      }
      this.selectedExtras[pid] = list;
      // Prova a sincronizzare nel carrello se la variante corrente coincide
      this.syncSelectedExtrasIntoCart(pid);
    },

    // Aggiorna le aggiunte dell'item nel carrello se la variante (offerta + set di extras) coincide
    syncSelectedExtrasIntoCart(pid){
      const offerId = this.selectedOffer[pid] || null;
      const extrasList = (this.selectedExtras[pid]||[]).map(e=>({id:e.id,name:e.name,price:e.price,quantity:1}));
      const item = this.cart.find(i => i.id === pid && this._sameVariant(i, offerId, extrasList));
      if (item){
        item.extras = extrasList;
        this.persist();
      }
    },

    async pollStock(){
      try {
        const res = await fetch('sales.php?ajax=stock');
        const data = await res.json();
        if (!data.ok) return;
        const map = new Map(data.products.map(p=>[p.id,p]));
        this.products = this.products.map(p => {
          const s = map.get(p.id);
          if (s) { p.stock_quantity = s.stock_quantity; p.min_stock_level = s.min_stock_level; }
          return p;
        });
        const exMap = new Map(data.extras.map(e=>[e.id,e]));
        this.products.forEach(p => {
          (p.extras||[]).forEach(ex => {
            const s = exMap.get(ex.id);
            if (s) { ex.stock_quantity = s.stock_quantity; ex.min_stock_level = s.min_stock_level; }
          })
        })
      } catch(e){}
    },

    openCheckoutModal(){
      if (this.cart.length === 0) return;
      this.checkoutModalOpen = true;
    },

    async checkout(){
      if (this.cart.length === 0) return;
      if (!this.orderNumber || !String(this.orderNumber).trim()) { alert('Inserisci il numero comanda'); return; }
      if (!this.paymentMethod) { alert('Seleziona il tipo di pagamento'); return; }
      const payload = {
        items: this.cart.map(i => ({
          id: i.id,
          quantity: i.quantity,
          offer_id: i.offer_id || null,
          extras: (i.extras || []).map(e => ({ id: e.id, quantity: 1 * i.quantity }))
        })),
        customer_name: this.customerName || null,
        payment_method: this.paymentMethod || 'cash',
        order_number: this.orderNumber
      };
      try {
        const res = await fetch('sales.php?ajax=checkout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Errore checkout');
        // Reset carrello con animazione
        this.cart = [];
        this.persist();
        this.cartOpen = false;
        this.checkoutModalOpen = false;
        alert(`Ordine ${data.order_number} creato! Totale ${this.formatCurrency(data.total)}`);
        this.orderNumber = '';
        this.customerName = '';
      } catch (e){
        alert('Checkout fallito: ' + e.message);
      }
    }
  }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>