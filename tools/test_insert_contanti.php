<?php
// Test inserimento ordine con metodo 'Contanti' e rollback
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$pdo->exec('PRAGMA busy_timeout = 20000');
$pdo->exec("PRAGMA journal_mode = WAL");

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, total_amount, status, payment_method, notes) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
    $orderNumber = 'TEST-' . date('Ymd-His');
    $ok = $stmt->execute([$orderNumber, 'Cliente Test', '0000000000', 12.50, 'Contanti', 'Ordine di test']);
    if (!$ok) {
        throw new Exception('Insert failed');
    }
    $id = $pdo->lastInsertId();
    echo "INSERITO_ID=$id\n";

    // Verifica recupero
    $check = $pdo->prepare("SELECT id, order_number, payment_method FROM orders WHERE id = ?");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) { throw new Exception('Record not found after insert'); }
    echo "RECORD=ID:" . $row['id'] . ", ORD:" . $row['order_number'] . ", PM:" . $row['payment_method'] . "\n";

    // Rollback
    $pdo->rollBack();
    echo "ROLLBACK_OK=1\n";
} catch (Exception $e) {
    try { $pdo->rollBack(); } catch (Exception $ee) {}
    echo 'ERRORE_TEST=' . $e->getMessage() . "\n";
    exit(1);
}

?>