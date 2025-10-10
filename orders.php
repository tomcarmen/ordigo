<?php
// Buffer e sessione per compatibilità con header/footer
if (!headers_sent()) { @ob_start(); }
@session_start();
// Imposta route corrente per evidenziare correttamente la navigazione
$route = 'orders';

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

// Endpoint AJAX: avanzamento stato ordine
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'advance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;
    $action = isset($payload['action']) ? trim($payload['action']) : '';

    if ($orderId <= 0 || !in_array($action, ['mark_ready', 'mark_completed', 'reopen_preparing'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parametri non validi']);
        exit;
    }

    try {
        // Recupera stato corrente
        $stmt = $db->query("SELECT id, order_number, customer_name, total_amount, status FROM orders WHERE id = ?", [$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ordine non trovato']);
            exit;
        }

        if ($action === 'mark_ready') {
            if ($order['status'] !== 'preparing') {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Transizione non valida: deve essere in preparazione']);
                exit;
            }
            $db->query("UPDATE orders SET status = 'ready', ready_at = CURRENT_TIMESTAMP WHERE id = ?", [$orderId]);
            // Recupera il timestamp ready_at
            $row = $db->query("SELECT ready_at FROM orders WHERE id = ?", [$orderId])->fetch();
            $order['status'] = 'ready';
            $order['ready_at'] = $row ? $row['ready_at'] : null;
        } elseif ($action === 'mark_completed') {
            if ($order['status'] !== 'ready') {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Transizione non valida: deve essere pronto']);
                exit;
            }
            $db->query("UPDATE orders SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?", [$orderId]);
            $order['status'] = 'completed';
            $row = $db->query("SELECT completed_at FROM orders WHERE id = ?", [$orderId])->fetch();
            $order['completed_at'] = $row ? $row['completed_at'] : null;
        } elseif ($action === 'reopen_preparing') {
            if ($order['status'] !== 'completed') {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Transizione non valida: deve essere ritirato']);
                exit;
            }
            $db->query("UPDATE orders SET status = 'preparing', completed_at = NULL, ready_at = NULL, created_at = CURRENT_TIMESTAMP WHERE id = ?", [$orderId]);
            $order['status'] = 'preparing';
            $row = $db->query("SELECT created_at FROM orders WHERE id = ?", [$orderId])->fetch();
            $order['created_at'] = $row ? $row['created_at'] : $order['created_at'];
        }

        echo json_encode(['ok' => true, 'order' => $order]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore interno']);
    }
    exit;
}

// Endpoint AJAX: lista ordini (per polling auto-refresh)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: application/json');
    try {
        // Recupera ordini per stato
        $preparing = $db->query("SELECT id, order_number, customer_name, total_amount, created_at FROM orders WHERE status = 'preparing' ORDER BY created_at ASC")->fetchAll();
        $ready = $db->query("SELECT id, order_number, customer_name, total_amount, created_at, ready_at FROM orders WHERE status = 'ready' ORDER BY created_at ASC")->fetchAll();
        // Solo ordini ritirati oggi
        $completed = $db->query("SELECT id, order_number, customer_name, total_amount, created_at, ready_at, completed_at FROM orders WHERE status = 'completed' AND DATE(completed_at) = DATE('now') ORDER BY completed_at DESC")->fetchAll();

        // Arricchisci con gli items
        $enrich = function(array $orders) use ($db) {
            if (!$orders) return $orders;
            $ids = array_map(fn($o) => $o['id'], $orders);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemsByOrder = [];
            try {
                $stmtItems = $db->query("SELECT order_id, product_name, quantity, unit_price, total_price, extras FROM order_items WHERE order_id IN ($placeholders) ORDER BY id", $ids);
                while ($row = $stmtItems->fetch()) {
                    $oid = $row['order_id'];
                    if (!isset($itemsByOrder[$oid])) { $itemsByOrder[$oid] = []; }
                    $extras = [];
                    if (!empty($row['extras'])) {
                        $decoded = json_decode($row['extras'], true);
                        if (is_array($decoded)) { $extras = $decoded; }
                    }
                    $itemsByOrder[$oid][] = [
                        'product_name' => $row['product_name'],
                        'quantity' => (int)$row['quantity'],
                        'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : null,
                        'total_price' => isset($row['total_price']) ? (float)$row['total_price'] : null,
                        'extras' => $extras
                    ];
                }
            } catch (Exception $e) {}
            foreach ($orders as &$o) {
                $o['items'] = $itemsByOrder[$o['id']] ?? [];
            }
            unset($o);
            return $orders;
        };

        $preparing = $enrich($preparing);
        $ready = $enrich($ready);
        $completed = $enrich($completed);

        echo json_encode(['ok' => true, 'preparing' => $preparing, 'ready' => $ready, 'completed' => $completed]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore interno']);
    }
    exit;
}

// Dati iniziali per la pagina: ordini in preparazione e pronti
$preparing = [];
$ready = [];
$completed = [];
try {
    $stmt = $db->query("SELECT id, order_number, customer_name, total_amount, created_at FROM orders WHERE status = 'preparing' ORDER BY created_at ASC");
    $preparing = $stmt->fetchAll();
    $stmt = $db->query("SELECT id, order_number, customer_name, total_amount, created_at, ready_at FROM orders WHERE status = 'ready' ORDER BY created_at ASC");
    $ready = $stmt->fetchAll();
    // Ordini ritirati oggi
    $stmt = $db->query("SELECT id, order_number, customer_name, total_amount, created_at, ready_at, completed_at FROM orders WHERE status = 'completed' AND DATE(completed_at) = DATE('now') ORDER BY completed_at DESC");
    $completed = $stmt->fetchAll();

    // Arricchisci con contenuti (order_items)
    $enrich = function(array $orders) use ($db) {
        if (!$orders) return $orders;
        $ids = array_map(fn($o) => $o['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemsByOrder = [];
        try {
            $stmtItems = $db->query("SELECT order_id, product_name, quantity, unit_price, total_price, extras FROM order_items WHERE order_id IN ($placeholders) ORDER BY id", $ids);
            while ($row = $stmtItems->fetch()) {
                $oid = $row['order_id'];
                if (!isset($itemsByOrder[$oid])) { $itemsByOrder[$oid] = []; }
                $extras = [];
                if (!empty($row['extras'])) {
                    $decoded = json_decode($row['extras'], true);
                    if (is_array($decoded)) { $extras = $decoded; }
                }
                $itemsByOrder[$oid][] = [
                    'product_name' => $row['product_name'],
                    'quantity' => (int)$row['quantity'],
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : null,
                    'total_price' => isset($row['total_price']) ? (float)$row['total_price'] : null,
                    'extras' => $extras
                ];
            }
        } catch (Exception $e) {}
        // Assegna items a ogni ordine
        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];
        }
        unset($o);
        return $orders;
    };
    $preparing = $enrich($preparing);
    $ready = $enrich($ready);
    $completed = $enrich($completed);
} catch (Exception $e) {
    // Ignora errori di query in rendering
}

// Template
require_once __DIR__ . '/templates/header.php';
?>

<div x-data="ordersApp(<?php echo htmlspecialchars(json_encode($preparing), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($ready), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($completed), ENT_QUOTES, 'UTF-8'); ?>)" x-init="init()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="mb-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">
        <i class="fas fa-clipboard-list mr-3 text-primary"></i>Gestione Ordini
      </h1>
    </div>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <button @click="active='all'" :class="active==='all' ? 'ring-2 ring-primary' : ''" class="text-left bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 hover:shadow-md transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center">
          <i class="fas fa-layer-group"></i>
        </div>
        <div>
          <div class="text-sm text-gray-600">Tutti</div>
          <div class="text-2xl font-bold text-gray-900" x-text="(preparing.length + ready.length + completed.length)"></div>
        </div>
      </div>
    </button>
    <button @click="active='preparing'" :class="active==='preparing' ? 'ring-2 ring-primary' : ''" class="text-left bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 hover:shadow-md transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-700 flex items-center justify-center">
          <i class="fas fa-clock"></i>
        </div>
        <div>
          <div class="text-sm text-gray-600">In preparazione</div>
          <div class="text-2xl font-bold text-gray-900" x-text="preparing.length"></div>
        </div>
      </div>
    </button>
    <button @click="active='ready'" :class="active==='ready' ? 'ring-2 ring-primary' : ''" class="text-left bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 hover:shadow-md transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center">
          <i class="fas fa-hourglass-half"></i>
        </div>
        <div>
          <div class="text-sm text-gray-600">Pronti</div>
          <div class="text-2xl font-bold text-gray-900" x-text="ready.length"></div>
        </div>
      </div>
    </button>
    <button @click="active='completed'" :class="active==='completed' ? 'ring-2 ring-primary' : ''" class="text-left bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 hover:shadow-md transition">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center">
          <i class="fas fa-check"></i>
        </div>
        <div>
          <div class="text-sm text-gray-600">Ritirati</div>
          <div class="text-2xl font-bold text-gray-900" x-text="completed.length"></div>
        </div>
      </div>
    </button>
  </div>

  <!-- Barra ricerca e comandi sotto le card -->
  <div class="mb-6">
    <div class="bg-primary/10 rounded-xl shadow-sm ring-1 ring-primary p-4">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
        <div class="flex-1">
          <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input x-model="searchOrder" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Cerca #ordine" class="w-full pl-10 pr-10 py-2.5 rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary text-base shadow-sm" />
          </div>
        </div>
        <div class="flex items-center gap-1 sm:ml-2">
          <button @click="sortDir='asc'" :class="chipClass(sortDir==='asc')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-sort-alpha-down mr-1"></i>A→Z</button>
          <button @click="sortDir='desc'" :class="chipClass(sortDir==='desc')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-sort-alpha-up mr-1"></i>Z→A</button>
        </div>
        <div class="flex items-center gap-1 sm:ml-2">
          <span class="text-sm text-gray-700 mr-1">Ordina per:</span>
          <button @click="sortBy='number'" :class="chipClass(sortBy==='number')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-hashtag mr-1"></i>Numero</button>
          <button @click="sortBy='created'" :class="chipClass(sortBy==='created')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-clock mr-1"></i>Inserimento</button>
        </div>
        <div class="flex items-center gap-1 sm:ml-2">
          <button @click="viewMode='card'" :class="chipClass(viewMode==='card')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-th mr-1"></i>Card</button>
          <button @click="viewMode='row'" :class="chipClass(viewMode==='row')" class="inline-flex items-center px-3 py-1 rounded border border-primary"><i class="fas fa-list mr-1"></i>Riga</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Lista unica di card per lo stato attivo -->
  <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 mb-6">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-800 flex items-center">
        <span class="w-2 h-2 rounded-full mr-2" :class="active==='preparing' ? 'bg-yellow-400' : (active==='ready' ? 'bg-blue-500' : (active==='completed' ? 'bg-green-500' : 'bg-gray-400'))"></span>
        <span x-text="active==='preparing' ? 'In preparazione' : (active==='ready' ? 'Pronti' : (active==='completed' ? 'Ritirati' : 'Tutti'))"></span>
      </h2>
      <span class="text-sm text-gray-500" x-text="filteredOrders().length + ' ordini'"></span>
    </div>
    <div class="p-4">
      <template x-if="filteredOrders().length === 0">
        <div class="text-gray-500" x-text="active==='preparing' ? 'Nessun ordine in preparazione' : (active==='ready' ? 'Nessun ordine pronto' : 'Nessun ordine ritirato oggi')"></div>
      </template>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="viewMode==='card' && filteredOrders().length > 0">
        <template x-for="o in filteredOrders()" :key="o.id">
          <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 hover:shadow-md transition p-4">
          <div class="rounded-t-lg -mx-4 -mt-4 px-4 py-2 bg-primary text-white">
            <div class="flex items-center justify-between">
              <div class="text-xl font-extrabold">#<span x-text="o.order_number"></span></div>
              <div>
                <!-- Label stato a destra con colore di stato -->
                <template x-if="(o.status||'')==='preparing'">
                  <span class="inline-flex items-center px-2 py-0.5 rounded border border-blue-500 bg-yellow-100 text-yellow-800 text-xs font-bold uppercase">In preparazione</span>
                </template>
                <template x-if="(o.status||'')==='ready'">
                  <span class="inline-flex items-center px-2 py-0.5 rounded border border-blue-500 bg-blue-100 text-blue-800 text-xs font-bold uppercase">Pronto</span>
                </template>
                <template x-if="(o.status||'')==='completed'">
                  <span class="inline-flex items-center px-2 py-0.5 rounded border border-blue-500 bg-green-100 text-green-800 text-xs font-bold uppercase">Ritirato</span>
                </template>
              </div>
            </div>
          </div>
          <div class="flex items-start justify-between mt-2">
            <div>
              <div class="text-sm font-semibold text-gray-900" x-text="o.customer_name"></div>
            </div>
            <div class="flex items-center gap-2">
              <template x-if="(o.status||'')==='preparing'">
                <span class="inline-flex items-center px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs" :title="'Avvio prep: ' + formatDate(o.created_at)">
                  <i class="fas fa-clock mr-1"></i>
                  <span x-text="elapsed(o.created_at, tick)"></span>
                </span>
              </template>
              <template x-if="(o.status||'')==='ready'">
                <span class="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs" :title="'Pronto da: ' + formatDate(o.ready_at)">
                  <i class="fas fa-hourglass-half mr-1"></i>
                  <span x-text="elapsed(o.ready_at, tick)"></span>
                </span>
              </template>
              <template x-if="(o.status||'')==='completed'">
                <span class="inline-flex items-center px-2 py-1 rounded bg-green-100 text-green-800 text-xs" :title="'Ritirato da: ' + formatDate(o.completed_at)">
                  <i class="fas fa-stopwatch mr-1"></i>
                  <span x-text="elapsed(o.completed_at, tick)"></span>
                </span>
              </template>
            </div>
          </div>
            <div class="mt-3">
              <ul class="text-sm text-gray-800 font-mono list-none pl-0">
                <template x-for="it in o.items" :key="it.product_name + '-' + it.quantity">
                  <li class="py-1">
                    <div class="flex items-center justify-between">
                      <span x-text="it.quantity + '× ' + it.product_name"></span>
                      <span class="font-semibold" x-text="formatCurrency((it.total_price != null ? it.total_price : (it.unit_price||0) * (it.quantity||0)))"></span>
                    </div>
                    <template x-if="it.extras && it.extras.length">
                      <div class="mt-0.5 text-xs text-gray-600">
                        <template x-for="e in it.extras" :key="(e.id||e.name)+'-'+(e.quantity||1)">
                          <div class="flex items-center justify-between">
                            <span>+ <span x-text="e.name || ''"></span></span>
                            <span x-text="formatCurrency((e.total_price != null ? e.total_price : (e.unit_price||0) * (e.quantity||1)))"></span>
                          </div>
                        </template>
                      </div>
                    </template>
                  </li>
                </template>
              </ul>
              <div class="mt-2 font-mono text-sm font-semibold text-gray-900 text-right">Totale: <span x-text="formatCurrency(o.total_amount)"></span></div>
            </div>
            <div class="mt-4 pt-3 border-t flex justify-end">
              <template x-if="(o.status||'')==='preparing'">
                <button @click="markReady(o)" class="inline-flex items-center px-4 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-sm shadow">
                  <i class="fas fa-check mr-2"></i>Pronto
                </button>
              </template>
              <template x-if="(o.status||'')==='ready'">
                <button @click="markCompleted(o)" class="inline-flex items-center px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm shadow">
                  <i class="fas fa-box-open mr-2"></i>Ritirato
                </button>
              </template>
              <template x-if="(o.status||'')==='completed'">
                <button @click="reopenPreparing(o)" class="inline-flex items-center px-4 py-2 rounded-md bg-yellow-600 hover:bg-yellow-700 text-white text-sm shadow">
                  <i class="fas fa-undo mr-2"></i>Riapri (preparazione)
                </button>
              </template>
            </div>
          </div>
        </template>
      </div>
      <!-- Vista riga -->
      <div class="divide-y" x-show="viewMode==='row' && filteredOrders().length > 0">
        <template x-for="o in filteredOrders()" :key="o.id">
          <div class="py-3 px-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
              <div class="text-sm font-bold text-gray-900">#<span x-text="o.order_number"></span></div>
              <div class="text-sm font-semibold text-gray-900" x-text="o.customer_name"></div>
              
              <div>
                <!-- Label stato -->
                <template x-if="(o.status||'')==='preparing'">
                  <span class="inline-flex items-center px-2 py-1 rounded border border-yellow-300 bg-yellow-50 text-yellow-700 text-xs mr-1">In preparazione</span>
                </template>
                <template x-if="(o.status||'')==='ready'">
                  <span class="inline-flex items-center px-2 py-1 rounded border border-blue-300 bg-blue-50 text-blue-700 text-xs mr-1">Pronto</span>
                </template>
                <template x-if="(o.status||'')==='completed'">
                  <span class="inline-flex items-center px-2 py-1 rounded border border-green-300 bg-green-50 text-green-700 text-xs mr-1">Ritirato</span>
                </template>
                <template x-if="(o.status||'')==='preparing'">
                  <span class="inline-flex items-center px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs" :title="'Avvio prep: ' + formatDate(o.created_at)">
                    <i class="fas fa-clock mr-1"></i>
                    <span x-text="elapsed(o.created_at, tick)"></span>
                  </span>
                </template>
                <template x-if="(o.status||'')==='ready'">
                  <span class="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs" :title="'Pronto da: ' + formatDate(o.ready_at)">
                    <i class="fas fa-hourglass-half mr-1"></i>
                    <span x-text="elapsed(o.ready_at, tick)"></span>
                  </span>
                </template>
                <template x-if="(o.status||'')==='completed'">
                  <span class="inline-flex items-center px-2 py-1 rounded bg-green-100 text-green-800 text-xs" :title="'Ritirato da: ' + formatDate(o.completed_at)">
                    <i class="fas fa-stopwatch mr-1"></i>
                    <span x-text="elapsed(o.completed_at, tick)"></span>
                  </span>
                </template>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="text-sm font-mono text-gray-800">Totale: <span x-text="formatCurrency(o.total_amount)"></span></div>
              <template x-if="(o.status||'')==='preparing'">
                <button @click="markReady(o)" class="inline-flex items-center px-3 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-sm shadow"><i class="fas fa-check mr-2"></i>Pronto</button>
              </template>
              <template x-if="(o.status||'')==='ready'">
                <button @click="markCompleted(o)" class="inline-flex items-center px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm shadow"><i class="fas fa-box-open mr-2"></i>Ritirato</button>
              </template>
              <template x-if="(o.status||'')==='completed'">
                <button @click="reopenPreparing(o)" class="inline-flex items-center px-3 py-1.5 rounded-md bg-yellow-600 hover:bg-yellow-700 text-white text-sm shadow"><i class="fas fa-undo mr-2"></i>Riapri (preparazione)</button>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>

  <div x-show="false" class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Colonna: In preparazione -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200" x-show="false">
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center"><span class="w-2 h-2 rounded-full bg-yellow-400 mr-2"></span>In preparazione</h2>
        <span class="text-sm text-gray-500" x-text="preparing.length + ' ordini'"></span>
      </div>
      <div class="divide-y">
        <template x-if="preparing.length === 0">
          <div class="p-4 text-gray-500">Nessun ordine in preparazione</div>
        </template>
        <template x-for="o in preparing" :key="o.id">
          <div class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-base font-semibold">#<span x-text="o.order_number"></span> - <span x-text="o.customer_name"></span></div>
              </div>
              <div class="flex items-center gap-2">
                <div class="text-sm text-gray-600">Totale: <span x-text="formatCurrency(o.total_amount)"></span></div>
                <span class="inline-flex items-center px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs" :title="'Avvio prep: ' + formatDate(o.created_at)">
                  <i class="fas fa-clock mr-1"></i>
                  <span x-text="elapsed(o.created_at, tick)"></span>
                </span>
                <button @click="markReady(o)" class="inline-flex items-center px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-sm shadow">
                  <i class="fas fa-check mr-2"></i>Pronto
                </button>
              </div>
            </div>
            <div class="mt-3">
              <ul class="text-sm text-gray-700 list-none pl-0">
                <template x-for="it in o.items" :key="it.product_name + '-' + it.quantity">
                  <li class="py-1">
                    <div class="flex items-center justify-between">
                      <span x-text="it.quantity + '× ' + it.product_name"></span>
                      <span class="font-semibold" x-text="formatCurrency((it.total_price != null ? it.total_price : (it.unit_price||0) * (it.quantity||0)))"></span>
                    </div>
                    <template x-if="it.extras && it.extras.length">
                      <div class="mt-0.5 text-xs text-gray-600">
                        <template x-for="e in it.extras" :key="(e.id||e.name)+'-'+(e.quantity||1)">
                          <div class="flex items-center justify-between">
                            <span>+ <span x-text="e.name || ''"></span></span>
                            <span x-text="formatCurrency((e.total_price != null ? e.total_price : (e.unit_price||0) * (e.quantity||1)))"></span>
                          </div>
                        </template>
                      </div>
                    </template>
                  </li>
                </template>
              </ul>
              <template x-if="active==='completed'">
                <div class="mt-2">
                  <button @click="reopenPreparing(o)" class="inline-flex items-center px-3 py-1.5 rounded-md bg-yellow-600 hover:bg-yellow-700 text-white text-sm shadow"><i class="fas fa-undo mr-2"></i>Riapri (in preparazione)</button>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Colonna: Pronti -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200" x-show="false">
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center"><span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>Pronti</h2>
        <span class="text-sm text-gray-500" x-text="ready.length + ' ordini'"></span>
      </div>
      <div class="divide-y">
        <template x-if="ready.length === 0">
          <div class="p-4 text-gray-500">Nessun ordine pronto</div>
        </template>
        <template x-for="o in ready" :key="o.id">
          <div class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-base font-semibold">#<span x-text="o.order_number"></span> - <span x-text="o.customer_name"></span></div>
              </div>
              <div class="flex items-center gap-2">
                <div class="text-sm text-gray-600">Totale: <span x-text="formatCurrency(o.total_amount)"></span></div>
                <span class="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs" :title="'Pronto da: ' + formatDate(o.ready_at || o.created_at)">
                  <i class="fas fa-hourglass-half mr-1"></i>
                  <span x-text="elapsed(o.ready_at || o.created_at, tick)"></span>
                </span>
                <button @click="markCompleted(o)" class="inline-flex items-center px-3 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm shadow">
                  <i class="fas fa-box-open mr-2"></i>Ritirato
                </button>
              </div>
            </div>
            <div class="mt-3">
              <ul class="text-sm text-gray-700 list-none pl-0">
                <template x-for="it in o.items" :key="it.product_name + '-' + it.quantity">
                  <li class="py-1">
                    <div class="flex items-center justify-between">
                      <span x-text="it.quantity + '× ' + it.product_name"></span>
                      <span class="font-semibold" x-text="formatCurrency((it.total_price != null ? it.total_price : (it.unit_price||0) * (it.quantity||0)))"></span>
                    </div>
                    <template x-if="it.extras && it.extras.length">
                      <div class="mt-0.5 text-xs text-gray-600">
                        <template x-for="e in it.extras" :key="(e.id||e.name)+'-'+(e.quantity||1)">
                          <div class="flex items-center justify-between">
                            <span>+ <span x-text="e.name || ''"></span></span>
                            <span x-text="formatCurrency((e.total_price != null ? e.total_price : (e.unit_price||0) * (e.quantity||1)))"></span>
                          </div>
                        </template>
                      </div>
                    </template>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </template>
      </div>
    </div>
    <!-- Colonna: Ritirati -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200" x-show="false">
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center"><span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>Ritirati</h2>
        <span class="text-sm text-gray-500" x-text="completed.length + ' ordini'"></span>
      </div>
      <div class="divide-y">
        <template x-if="completed.length === 0">
          <div class="p-4 text-gray-500">Nessun ordine ritirato oggi</div>
        </template>
        <template x-for="o in completed" :key="o.id">
          <div class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-base font-semibold">#<span x-text="o.order_number"></span> - <span x-text="o.customer_name"></span></div>
              </div>
              <div class="flex items-center gap-2">
                <div class="text-sm text-gray-600">Totale: <span x-text="formatCurrency(o.total_amount)"></span></div>
                <span class="inline-flex items-center px-2 py-1 rounded bg-green-100 text-green-800 text-xs" :title="'Ritirato da: ' + formatDate(o.completed_at || o.ready_at || o.created_at)">
                  <i class="fas fa-stopwatch mr-1"></i>
                  <span x-text="elapsed(o.completed_at || o.ready_at || o.created_at, tick)"></span>
                </span>
              </div>
            </div>
            <div class="mt-3">
              <ul class="text-sm text-gray-700 list-none pl-0">
                <template x-for="it in o.items" :key="it.product_name + '-' + it.quantity">
                  <li class="py-1">
                    <div class="flex items-center justify-between">
                      <span x-text="it.quantity + '× ' + it.product_name"></span>
                      <span class="font-semibold" x-text="formatCurrency((it.total_price != null ? it.total_price : (it.unit_price||0) * (it.quantity||0)))"></span>
                    </div>
                    <template x-if="it.extras && it.extras.length">
                      <div class="mt-0.5 text-xs text-gray-600">
                        <template x-for="e in it.extras" :key="(e.id||e.name)+'-'+(e.quantity||1)">
                          <div class="flex items-center justify-between">
                            <span>+ <span x-text="e.name || ''"></span></span>
                            <span x-text="formatCurrency((e.total_price != null ? e.total_price : (e.unit_price||0) * (e.quantity||1)))"></span>
                          </div>
                        </template>
                      </div>
                    </template>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>

  <script>
    function ordersApp(initialPreparing, initialReady, initialCompleted) {
      return {
        preparing: initialPreparing || [],
        ready: initialReady || [],
        completed: initialCompleted || [],
        active: 'all',
        searchOrder: '',
        sortBy: 'number',
        sortDir: 'asc',
        viewMode: 'card',
        tick: Date.now(),
        init() {
          // Tick per aggiornare timer
          setInterval(() => { this.tick = Date.now(); }, 1000);
          // Assicura il campo status sugli ordini iniziali
          this.preparing = (this.preparing || []).map(o => ({ ...o, status: 'preparing' }));
          this.ready = (this.ready || []).map(o => ({ ...o, status: 'ready' }));
          this.completed = (this.completed || []).map(o => ({ ...o, status: 'completed' }));
          // Polling periodico per auto-refresh ordini
          setInterval(() => { this.refreshOrders(); }, 5000);
        },
        // Helper JS per costruire URL assoluti coerenti con la pagina corrente
        asset_path(rel) {
          try {
            const base = window.location.origin + window.location.pathname.replace(/[^\/]+$/, '');
            return new URL(rel, base).href;
          } catch (e) { return rel; }
        },
        formatCurrency(v) {
          try {
            return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(v || 0);
          } catch (e) {
            const num = Number(v || 0);
            return '€' + num.toFixed(2);
          }
        },
        chipClass(active) {
          return active ? 'bg-gray-200 text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200';
        },
        async refreshOrders(){
          try {
            const res = await fetch(this.asset_path('orders.php?ajax=list'));
            const data = await res.json();
            if (!data.ok) return;
            this.preparing = (data.preparing || []).map(o => ({ ...o, status: 'preparing' }));
            this.ready = (data.ready || []).map(o => ({ ...o, status: 'ready' }));
            this.completed = (data.completed || []).map(o => ({ ...o, status: 'completed' }));
            // Forza aggiornamento timer immediato per badging tempo
            this.tick = Date.now();
          } catch(e) { /* ignora errori di rete */ }
        },
        ordersCount(list) { return Array.isArray(list) ? list.length : 0; },
        itemsCount(list) {
          if (!Array.isArray(list)) return 0;
          return list.reduce((sum, o) => sum + (Array.isArray(o.items) ? o.items.reduce((s, it) => s + (it.quantity || 0), 0) : 0), 0);
        },
        formatDate(iso) {
          if (!iso) return '';
          let t = iso;
          // Se il timestamp non specifica timezone, trattalo come UTC
          const hasTZ = /[zZ]|[+-]\d{2}:?\d{2}$/.test(iso);
          if (!hasTZ) {
            if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(iso)) {
              t = iso.replace(' ', 'T') + 'Z';
            } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(iso)) {
              t = iso + 'Z';
            }
          }
          const d = new Date(t);
          try {
            return d.toLocaleString('it-IT', {
              timeZone: 'Europe/Rome',
              year: 'numeric', month: '2-digit', day: '2-digit',
              hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
          } catch (e) {
            return (iso || '').replace('T', ' ').split('.')[0];
          }
        },
        // Restituisce tempo trascorso; passa tick per reattività
        elapsed(from, _tick) {
          if (!from) return '—';
          let t = from;
          // Se manca timezone, interpreta come UTC
          const hasTZ = /[zZ]|[+-]\d{2}:?\d{2}$/.test(from);
          if (!hasTZ) {
            if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(from)) {
              t = from.replace(' ', 'T') + 'Z';
            } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(from)) {
              t = from + 'Z';
            }
          }
          const start = new Date(t);
          const diff = Math.max(0, Date.now() - start.getTime());
          const sec = Math.floor(diff / 1000);
          const m = Math.floor(sec / 60);
          const s = sec % 60;
          return `${m}m ${s.toString().padStart(2,'0')}s`;
        },
        currentList(){
          if (this.active === 'preparing') return this.preparing;
          if (this.active === 'ready') return this.ready;
          if (this.active === 'completed') return this.completed;
          // Tutti
          return ([]).concat(this.preparing || [], this.ready || [], this.completed || []);
        },
        filteredOrders(){
          let list = this.currentList();
          const q = String(this.searchOrder || '').trim();
          if (q) { list = list.filter(o => String(o.order_number || '').startsWith(q)); }
          list = list.slice().sort((a,b) => {
            if (this.sortBy === 'created') {
              const ta = Date.parse(a.created_at || '') || 0;
              const tb = Date.parse(b.created_at || '') || 0;
              return this.sortDir === 'asc' ? (ta - tb) : (tb - ta);
            }
            const A = String(a.order_number||'');
            const B = String(b.order_number||'');
            return this.sortDir==='asc' ? A.localeCompare(B, undefined, {numeric:true}) : B.localeCompare(A, undefined, {numeric:true});
          });
          return list;
        },
        async markReady(order) {
          try {
            const res = await fetch(this.asset_path('orders.php?ajax=advance'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ order_id: order.id, action: 'mark_ready' })
            });
            const data = await res.json();
            if (!data.ok) { throw new Error(data.error || 'Operazione fallita'); }
            // Sposta ordine da preparing a ready
            this.preparing = this.preparing.filter(o => o.id !== order.id);
            order.ready_at = data.order && data.order.ready_at ? data.order.ready_at : new Date().toISOString();
            order.status = 'ready';
            this.ready.push(order);
            if (typeof showToast === 'function') { showToast('Ordine #' + order.order_number + ' segnato come pronto', 'success'); }
            // Reset campo di ricerca dopo cambio stato
            this.searchOrder = '';
            // Forza aggiornamento timer immediato
            this.tick = Date.now();
          } catch (e) {
            if (typeof showToast === 'function') { showToast(e.message || 'Errore', 'error'); }
          }
        },
        async markCompleted(order) {
          try {
            const res = await fetch(this.asset_path('orders.php?ajax=advance'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ order_id: order.id, action: 'mark_completed' })
            });
            const data = await res.json();
            if (!data.ok) { throw new Error(data.error || 'Operazione fallita'); }
            // Rimuovi ordine dalla lista ready
            this.ready = this.ready.filter(o => o.id !== order.id);
            order.completed_at = data.order && data.order.completed_at ? data.order.completed_at : new Date().toISOString();
            order.status = 'completed';
            this.completed.unshift(order);
            if (typeof showToast === 'function') { showToast('Ordine #' + order.order_number + ' ritirato', 'success'); }
            // Reset campo di ricerca dopo cambio stato
            this.searchOrder = '';
            // Forza aggiornamento timer immediato
            this.tick = Date.now();
          } catch (e) {
            if (typeof showToast === 'function') { showToast(e.message || 'Errore', 'error'); }
          }
        },
        async reopenPreparing(order) {
          try {
            const res = await fetch(this.asset_path('orders.php?ajax=advance'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ order_id: order.id, action: 'reopen_preparing' })
            });
            const data = await res.json();
            if (!data.ok) { throw new Error(data.error || 'Operazione fallita'); }
            // Rimuovi ordine dai completati e riportalo in preparazione
            this.completed = this.completed.filter(o => o.id !== order.id);
            order.created_at = data.order && data.order.created_at ? data.order.created_at : new Date().toISOString();
            order.ready_at = null;
            order.completed_at = null;
            order.status = 'preparing';
            this.preparing.push(order);
            if (typeof showToast === 'function') { showToast('Ordine #' + order.order_number + ' riaperto in preparazione', 'success'); }
            // Reset campo di ricerca dopo cambio stato
            this.searchOrder = '';
            // Forza aggiornamento timer immediato
            this.tick = Date.now();
          } catch (e) {
            if (typeof showToast === 'function') { showToast(e.message || 'Errore', 'error'); }
          }
        }
      }
    }
  </script>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>