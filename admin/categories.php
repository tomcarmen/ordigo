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
           COUNT(p_active.id) as active_products,
           COUNT(p_all.id) as product_count
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
        <button onclick="openModal('addCategoryModal')" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
            <i class="fas fa-plus mr-2"></i>Nuova Categoria
        </button>
    </div>

    <!-- Messaggi -->
    <?php if ($message): ?>
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
            <div class="flex">
                <i class="fas fa-check-circle text-green-400 mr-3 mt-1"></i>
                <p class="text-green-700"><?= htmlspecialchars($message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-400 mr-3 mt-1"></i>
                <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistiche rapide -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
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

        <div class="bg-white rounded-lg shadow p-6">
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

        <div class="bg-white rounded-lg shadow p-6">
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

        <div class="bg-white rounded-lg shadow p-6">
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
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
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
                        <tr class="hover:bg-gray-50">
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: <?= htmlspecialchars($category['color_hex'] ?? '#3B82F6') ?>">
                                    <?= htmlspecialchars($category['color_hex'] ?? '#3B82F6') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="font-medium"><?= $category['active_products'] ?></span> / <?= $category['product_count'] ?>
                                <span class="text-gray-500 text-xs">(attivi/totali)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($category['active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Attiva
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>Inattiva
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($category['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <a href="?route=admin&page=categories&action=edit&id=<?= $category['id'] ?>"
                                   class="text-primary hover:text-blue-600 mr-3">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($category['active_products'] == 0): ?>
                <a href="?route=admin&page=categories&action=delete&id=<?= $category['id'] ?>"
                                       onclick="return confirm('Sei sicuro di voler eliminare questa categoria?')"
                                       class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400" title="Impossibile eliminare: categoria con prodotti attivi associati">
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
<div id="addCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="POST" action="?route=admin&page=categories&action=add">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                <i class="fas fa-plus-circle mr-2 text-primary"></i>Aggiungi Nuova Categoria
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Categoria *</label>
                                    <input type="text" name="name" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Colore</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="color_hex" value="#3B82F6" 
                                               class="w-12 h-10 border border-gray-300 rounded cursor-pointer">
                                        <input type="text" name="color_hex_text" value="#3B82F6" 
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                                    <textarea name="description" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="active" checked 
                                           class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Categoria attiva</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Salva Categoria
                    </button>
                    <button type="button" onclick="closeModal('addCategoryModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annulla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_category): ?>
<!-- Modal Modifica Categoria -->
<div id="editCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="POST" action="?route=admin&page=categories&action=edit">
                <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                <i class="fas fa-edit mr-2 text-primary"></i>Modifica Categoria
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome Categoria *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($edit_category['name']) ?>" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Colore</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" name="color_hex" value="<?= htmlspecialchars($edit_category['color'] ?? '#3B82F6') ?>" 
                                               class="w-12 h-10 border border-gray-300 rounded cursor-pointer">
                                        <input type="text" name="color_hex_text" value="<?= htmlspecialchars($edit_category['color'] ?? '#3B82F6') ?>" 
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                                    <textarea name="description" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="active" <?= $edit_category['active'] ? 'checked' : '' ?> 
                                           class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                    <label class="ml-2 block text-sm text-gray-900">Categoria attiva</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Aggiorna Categoria
                    </button>
                <a href="?route=admin&page=categories" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annulla
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
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

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>