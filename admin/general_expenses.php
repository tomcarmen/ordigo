<?php
// Inclusione difensiva del database
if (!class_exists('Database')) {
    require_once __DIR__ . '/../config/database.php';
}
$db = Database::getInstance();

// Gestione invio form (crea/modifica/elimina spesa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $target = asset_path('index.php') . '?route=admin&page=general_expenses';

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $stmt = $db->getConnection()->prepare("DELETE FROM general_expenses WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: ' . $target);
        exit;
    }

    $description = trim($_POST['description'] ?? '');
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $notes = trim($_POST['notes'] ?? '');
    $expense_date = trim($_POST['expense_date'] ?? '');

    if ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $description !== '' && $amount > 0) {
            if ($expense_date === '') { $expense_date = date('Y-m-d'); }
            $stmt = $db->getConnection()->prepare("UPDATE general_expenses SET description = ?, amount = ?, notes = ?, expense_date = ? WHERE id = ?");
            $stmt->execute([$description, $amount, $notes, $expense_date, $id]);
        }
        header('Location: ' . $target);
        exit;
    }

    // default: create
    if ($description !== '' && $amount > 0) {
        if ($expense_date === '') { $expense_date = date('Y-m-d'); }
        $stmt = $db->getConnection()->prepare("INSERT INTO general_expenses (description, amount, notes, expense_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$description, $amount, $notes, $expense_date]);
    }
    header('Location: ' . $target);
    exit;
}

// Se in edit, carica la spesa selezionata
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$expense_to_edit = null;
if ($edit_id > 0) {
$stmt = $db->getConnection()->prepare("SELECT id, description, amount, notes, expense_date FROM general_expenses WHERE id = ?");
    $stmt->execute([$edit_id]);
    $expense_to_edit = $stmt->fetch();
}

// Carica spese
$expenses = $db->query("SELECT id, description, amount, notes, expense_date, created_at FROM general_expenses ORDER BY expense_date DESC, created_at DESC")->fetchAll();
$total_expenses = $db->query("SELECT COALESCE(SUM(amount),0) as total FROM general_expenses")->fetch()['total'] ?? 0;
?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 flex items-center">
            <i class="fas fa-receipt mr-2 text-primary"></i>Spese Generali
        </h2>
        <p class="text-sm text-gray-600">Aggiungi costi pre-evento. Queste spese riducono il guadagno totale.</p>
    </div>

    <!-- Azioni principali: solo bottone Aggiungi -->
    <div class="mb-6">
        <button type="button" id="openAddExpenseBtn" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/90">
            <i class="fas fa-plus mr-2"></i>Aggiungi Spesa
        </button>
    </div>

    <?php /* Modifica inline rimossa: la modifica avviene via modal */ ?>

    <!-- Totale spese -->
    <div class="bg-white rounded-lg shadow p-5 ring-1 ring-gray-100 mb-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600"><i class="fas fa-minus-circle text-xl"></i></div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Spese Totali</p>
                <p class="text-2xl font-semibold text-gray-900">€<?= number_format($total_expenses, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- Lista spese -->
    <div class="bg-white rounded-lg shadow overflow-hidden ring-1 ring-gray-100">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrizione</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importo (€)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($expenses as $e): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($e['expense_date'] ? date('Y-m-d', strtotime($e['expense_date'])) : date('Y-m-d', strtotime($e['created_at']))) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($e['description']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">€<?= number_format($e['amount'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($e['notes'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="flex items-center gap-2">
                            <button type="button" class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100 edit-expense-btn"
                                data-id="<?= intval($e['id']) ?>"
                                data-description="<?= htmlspecialchars($e['description'], ENT_QUOTES) ?>"
                                data-amount="<?= number_format((float)$e['amount'], 2, '.', '') ?>"
                                data-date="<?= htmlspecialchars(($e['expense_date'] ? date('Y-m-d', strtotime($e['expense_date'])) : date('Y-m-d', strtotime($e['created_at']))), ENT_QUOTES) ?>"
                                data-notes="<?= htmlspecialchars(($e['notes'] ?? ''), ENT_QUOTES) ?>">
                                <i class="fas fa-pen mr-1"></i>Modifica
                            </button>
                            <form method="POST" action="?route=admin&page=general_expenses" class="inline" onsubmit="return confirm('Confermi l\'eliminazione?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= intval($e['id']) ?>">
                                <button type="submit" class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-red-50 text-red-700 hover:bg-red-100">
                                    <i class="fas fa-trash mr-1"></i>Elimina
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Nessuna spesa registrata.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modale: Aggiungi Spesa -->
<div id="addExpenseModal" class="fixed inset-0 hidden z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Aggiungi Spesa</h3>
            <button type="button" id="closeAddExpenseModal" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="?route=admin&page=general_expenses" id="addExpenseForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="create">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Descrizione spesa</label>
                <input type="text" name="description" required class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Es. Affitto gazebo">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Importo (€)</label>
                <input type="number" name="amount" min="0" step="0.01" required class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Data spesa</label>
                <input type="date" name="expense_date" class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text sm font-medium text-gray-700">Note</label>
                <input type="text" name="notes" class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Opzionale">
            </div>
            <div class="md:col-span-2 flex justify-end gap-3 mt-2">
                <button type="button" onclick="closeAddExpenseModal()" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-times mr-2"></i>Annulla</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/90"><i class="fas fa-plus mr-2"></i>Aggiungi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modale: Modifica Spesa -->
<div id="editExpenseModal" class="fixed inset-0 hidden z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Modifica Spesa</h3>
            <button type="button" id="closeEditExpenseModal" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="?route=admin&page=general_expenses" id="editExpenseForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Descrizione spesa</label>
                <input type="text" name="description" required class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Es. Affitto gazebo">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Importo (€)</label>
                <input type="number" name="amount" min="0" step="0.01" required class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow sm" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Data spesa</label>
                <input type="date" name="expense_date" class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <input type="text" name="notes" class="mt-1 w-full rounded-md px-3 py-2 text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Opzionale">
            </div>
            <div class="md:col-span-2 flex justify-end gap-3 mt-2">
                <button type="button" onclick="closeEditExpenseModal()" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-times mr-2"></i>Annulla</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/90"><i class="fas fa-save mr-2"></i>Salva modifiche</button>
            </div>
        </form>
    </div>
</div>

<script>
// Apertura/chiusura modale Aggiungi
const openAddBtn = document.getElementById('openAddExpenseBtn');
const addModal = document.getElementById('addExpenseModal');
const closeAddBtn = document.getElementById('closeAddExpenseModal');
function openAddExpenseModal(){ addModal?.classList.remove('hidden'); }
function closeAddExpenseModal(){ addModal?.classList.add('hidden'); }
openAddBtn?.addEventListener('click', openAddExpenseModal);
closeAddBtn?.addEventListener('click', closeAddExpenseModal);
addModal?.addEventListener('click', (e) => { if (e.target === addModal) closeAddExpenseModal(); });

// Apertura/chiusura modale Modifica + precompilazione
const editModal = document.getElementById('editExpenseModal');
const closeEditBtn = document.getElementById('closeEditExpenseModal');
function openEditExpenseModal(){ editModal?.classList.remove('hidden'); }
function closeEditExpenseModal(){ editModal?.classList.add('hidden'); }
closeEditBtn?.addEventListener('click', closeEditExpenseModal);
editModal?.addEventListener('click', (e) => { if (e.target === editModal) closeEditExpenseModal(); });

document.querySelectorAll('.edit-expense-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const descr = btn.getAttribute('data-description') || '';
        const amount = btn.getAttribute('data-amount') || '';
        const date = btn.getAttribute('data-date') || '';
        const notes = btn.getAttribute('data-notes') || '';
        const form = document.getElementById('editExpenseForm');
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('input[name="description"]').value = descr;
        form.querySelector('input[name="amount"]').value = amount;
        form.querySelector('input[name="expense_date"]').value = date;
        form.querySelector('input[name="notes"]').value = notes;
        openEditExpenseModal();
    });
});

// Esc chiude le modali
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAddExpenseModal();
        closeEditExpenseModal();
    }
});
</script>

<?php /* fine pagina */ ?>