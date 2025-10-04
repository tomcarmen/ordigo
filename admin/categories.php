<?php
/**
 * Gestione Categorie - OrdiGO
 * Sistema di gestione categorie per prodotti
 */

$db = Database::getInstance();

// Gestione azioni CRUD
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

switch ($action) {
    case 'add':
        if ($_POST) {
            try {
                $stmt = $db->query("
                    INSERT INTO categories (name, color, description, active) 
                    VALUES (?, ?, ?, ?)
                ", [
                    $_POST['name'],
                    $_POST['color_hex'] ?? '#3B82F6',
                    $_POST['description'] ?? '',
                    isset($_POST['active']) ? 1 : 0
                ]);
                $message = "Categoria aggiunta con successo!";
            } catch (Exception $e) {
                $error = "Errore nell'aggiunta della categoria: " . $e->getMessage();
            }
        }
        break;
        
    case 'edit':
        if ($_POST) {
            try {
                $stmt = $db->query("
                    UPDATE categories 
                    SET name = ?, color = ?, description = ?, active = ?
                    WHERE id = ?
                ", [
                    $_POST['name'],
                    $_POST['color_hex'],
                    $_POST['description'],
                    isset($_POST['active']) ? 1 : 0,
                    $_POST['id']
                ]);
                $message = "Categoria aggiornata con successo!";
            } catch (Exception $e) {
                $error = "Errore nell'aggiornamento della categoria: " . $e->getMessage();
            }
        }
        break;
        
    case 'delete':
        if (isset($_GET['id'])) {
            try {
                // Verifica se ci sono prodotti attivi associati
                $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND active = 1", [$_GET['id']]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $error = "Impossibile eliminare la categoria: ci sono " . $result['count'] . " prodotti attivi associati.";
                } else {
                    $stmt = $db->query("DELETE FROM categories WHERE id = ?", [$_GET['id']]);
                    $message = "Categoria eliminata con successo!";
                }
            } catch (Exception $e) {
                $error = "Errore nell'eliminazione della categoria: " . $e->getMessage();
            }
        }
        break;
}

// Recupera tutte le categorie con conteggio separato per prodotti attivi e totali
$stmt = $db->query("
    SELECT c.id, c.name, c.description, c.color as color_hex, c.active, c.created_at,
           COUNT(DISTINCT p_active.id) as active_products,
           COUNT(DISTINCT p_all.id) as product_count
    FROM categories c
    LEFT JOIN products p_active ON c.id = p_active.category_id AND p_active.active = 1
    LEFT JOIN products p_all ON c.id = p_all.category_id
    GROUP BY c.id, c.name, c.description, c.color, c.active, c.created_at
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Recupera categoria per modifica se richiesta
$edit_category = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->query("SELECT * FROM categories WHERE id = ?", [$_GET['id']]);
    $edit_category = $stmt->fetch();
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-tags mr-3 text-primary"></i>Gestione Categorie
        </h1>
        <button onclick="openModal('addCategoryModal')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md shadow-sm ring-1 ring-inset ring-green-500/30 transition-colors duration-150">
            <i class="fas fa-plus mr-2"></i>Nuova Categoria
        </button>
    </div>

    <!-- Messaggi -->
    <?php if ($message): ?>
        <div class="mb-4 bg-green-50 ring-1 ring-inset ring-green-200 p-4 rounded-md shadow-sm" role="status" aria-live="polite" aria-atomic="true">
            <div class="flex">
                <i class="fas fa-check-circle text-green-400 mr-3 mt-1" aria-hidden="true"></i>
                <p class="text-green-700"><?= htmlspecialchars($message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-4 bg-red-50 ring-1 ring-inset ring-red-200 p-4 rounded-md shadow-sm" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-400 mr-3 mt-1" aria-hidden="true"></i>
                <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistiche rapide -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-tags text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Totale Categorie</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= count($categories) ?></p>
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
                    <p class="text-2xl font-semibold text-gray-900"><?= count(array_filter($categories, fn($c) => $c['active'])) ?></p>
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
                    <p class="text-2xl font-semibold text-gray-900"><?= array_sum(array_column($categories, 'product_count')) ?></p>
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
                    <p class="text-2xl font-semibold text-gray-900"><?= array_sum(array_column($categories, 'active_products')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella categorie -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200/60 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 ring-1 ring-inset ring-gray-100">
            <h3 class="text-lg font-medium text-gray-900">Elenco Categorie</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colore</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prodotti</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Creazione</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($categories as $category): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?= htmlspecialchars($category['color_hex'] ?? '#3B82F6') ?>"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($category['name'] ?? '') ?></div>
                                        <?php if (!empty($category['description'])): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($category['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white ring-1 ring-black/10 shadow-sm" style="background-color: <?= htmlspecialchars($category['color_hex'] ?? '#3B82F6') ?>">
                                    <?= htmlspecialchars($category['color_hex'] ?? '#3B82F6') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="font-medium"><?= $category['active_products'] ?></span> / <?= $category['product_count'] ?>
                                <span class="text-gray-500 text-xs">(attivi/totali)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($category['active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-200">
                                        <i class="fas fa-check-circle mr-1"></i>Attiva
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">
                                        <i class="fas fa-times-circle mr-1"></i>Inattiva
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($category['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <a href="?route=admin&page=categories&action=edit&id=<?= $category['id'] ?>"
                                   class="inline-flex items-center p-2 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 mr-3 ring-1 ring-inset ring-blue-200 transition-colors duration-150">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($category['active_products'] == 0): ?>
                <a href="?route=admin&page=categories&action=delete&id=<?= $category['id'] ?>"
                                       onclick="return confirm('Sei sicuro di voler eliminare questa categoria?')"
                                       class="inline-flex items-center p-2 rounded-md bg-red-50 text-red-600 hover:bg-red-100 ring-1 ring-inset ring-red-200 transition-colors duration-150">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="inline-flex items-center p-2 rounded-md bg-gray-100 text-gray-400 ring-1 ring-inset ring-gray-200 transition-colors duration-150" title="Impossibile eliminare: categoria con prodotti attivi associati">
                                        <i class="fas fa-trash"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Aggiungi Categoria -->
<div id="addCategoryModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50" role="dialog" aria-modal="true" aria-labelledby="addCategoryTitle">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="inline-block align-bottom bg-white rounded-xl ring-1 ring-gray-200/60 text-left overflow-hidden shadow-2xl transform transition-all my-8 sm:my-8 sm:align-middle w-full max-w-md sm:max-w-lg mx-4 sm:mx-0">
            <form method="POST" action="?route=admin&page=categories&action=add">
                <div class="bg-white px-4 sm:px-6 py-5 sm:py-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 id="addCategoryTitle" class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                <i class="fas fa-plus-circle mr-2 text-primary"></i>Aggiungi Nuova Categoria
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Categoria *</label>
                                    <input type="text" name="name" required 
                                           class="w-full px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Colore</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="color_hex" value="#3B82F6" 
                                               class="w-12 h-10 rounded cursor-pointer shadow-sm ring-1 ring-inset ring-gray-300">
                                        <input type="text" name="color_hex_text" value="#3B82F6" 
                                               class="flex-1 px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                                    <textarea name="description" rows="3" 
                                              class="w-full px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="active" checked 
                                           class="h-4 w-4 text-primary focus:ring-primary ring-1 ring-inset ring-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Categoria attiva</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Salva Categoria
                    </button>
                    <button type="button" onclick="closeModal('addCategoryModal')" class="mt-3 w-full inline-flex justify-center rounded-md ring-1 ring-inset ring-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annulla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_category): ?>
<!-- Modal Modifica Categoria -->
<div id="editCategoryModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50" role="dialog" aria-modal="true" aria-labelledby="editCategoryTitle">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="inline-block align-bottom bg-white rounded-xl ring-1 ring-gray-200/60 text-left overflow-hidden shadow-2xl transform transition-all my-8 sm:my-8 sm:align-middle w-full max-w-md sm:max-w-lg mx-4 sm:mx-0">
            <form method="POST" action="?route=admin&page=categories&action=edit">
                <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                <div class="bg-white px-4 sm:px-6 py-5 sm:py-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 id="editCategoryTitle" class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                <i class="fas fa-edit mr-2 text-primary"></i>Modifica Categoria
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Categoria *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($edit_category['name']) ?>" required 
                                           class="w-full px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Colore</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="color_hex" value="<?= htmlspecialchars($edit_category['color'] ?? '#3B82F6') ?>" 
                                               class="w-12 h-10 rounded cursor-pointer shadow-sm ring-1 ring-inset ring-gray-300">
                                        <input type="text" name="color_hex_text" value="<?= htmlspecialchars($edit_category['color'] ?? '#3B82F6') ?>" 
                                               class="flex-1 px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                                    <textarea name="description" rows="3" 
                                              class="w-full px-3 py-2 rounded-md shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="active" <?= $edit_category['active'] ? 'checked' : '' ?> 
                                           class="h-4 w-4 text-primary focus:ring-primary ring-1 ring-inset ring-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Categoria attiva</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Aggiorna Categoria
                    </button>
                <a href="?route=admin&page=categories" class="mt-3 w-full inline-flex justify-center rounded-md ring-1 ring-inset ring-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annulla
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-apertura Add Category modal da query string
(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('openModal') === 'addCategory') {
    try {
      setTimeout(() => openModal('addCategoryModal'), 0);
    } catch (e) {}
  }
})();
// Sincronizzazione color picker
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    colorInputs.forEach(colorInput => {
        const textInput = colorInput.nextElementSibling;
        
        colorInput.addEventListener('change', function() {
            textInput.value = this.value;
        });
        
        textInput.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorInput.value = this.value;
            }
        });
    });
});

const focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
let modalTriggerElement = null;
const modalKeydownHandlers = {};

function trapFocus(modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  const focusable = modal.querySelectorAll(focusableSelectors);
  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  const handler = function(e) {
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
      closeModal(modalId);
    }
  };

  modal.addEventListener('keydown', handler);
  modalKeydownHandlers[modalId] = handler;
  if (first) first.focus();
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modalTriggerElement = document.activeElement;
    modal.classList.remove('hidden');
    trapFocus(modalId);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('hidden');
    const handler = modalKeydownHandlers[modalId];
    if (handler) {
      modal.removeEventListener('keydown', handler);
      delete modalKeydownHandlers[modalId];
    }
    if (modalTriggerElement && typeof modalTriggerElement.focus === 'function') {
      modalTriggerElement.focus();
    }
    modalTriggerElement = null;
}
</script>
<script>
// Accessibilità modali: focus trap e chiusura con ESC
document.addEventListener('DOMContentLoaded', () => {
  const modals = ['addCategoryModal', 'editCategoryModal'];
  const focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function trapFocus(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    const focusable = modal.querySelectorAll(focusableSelectors);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    function handleKeyDown(e) {
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
        closeModal(modalId);
      }
    }

    modal.addEventListener('keydown', handleKeyDown);
    // Sposta il focus sul primo elemento utile
    if (first) first.focus();
  }

  // Estendi openModal per attivare focus trap
  window.openModal = function(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    el.classList.remove('hidden');
    trapFocus(modalId);
  };

  // Chiudi con click sul backdrop
  modals.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', (e) => {
      if (e.target === el) {
        closeModal(id);
      }
    });
  });
});
</script>