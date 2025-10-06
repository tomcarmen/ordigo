<?php
// Script di migrazione forzata per tabella orders
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();
$pdo->exec('PRAGMA busy_timeout = 5000');
$pdo->exec("PRAGMA journal_mode = WAL");

function hasNewItalianCheck($sql) {
    $normalized = strtolower(preg_replace('/\s+/', '', $sql));
    return strpos($normalized, "check(payment_methodin('contanti','bancomat','satispay'))") !== false;
}

try {
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
    $ordersSql = $stmt->fetchColumn();
    if (!$ordersSql) {
        echo "Tabella orders non trovata.\n";
        exit(1);
    }
    $hasNew = hasNewItalianCheck($ordersSql);
    echo 'HAS_NEW_CHECK_BEFORE=' . ($hasNew ? '1' : '0') . "\n";

    if (!$hasNew) {
        // Ricrea tabella con vincolo aggiornato e mappa valori
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN EXCLUSIVE');
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS orders_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number VARCHAR(20) UNIQUE NOT NULL,
                customer_name VARCHAR(200),
                customer_phone VARCHAR(20),
                total_amount DECIMAL(10,2) NOT NULL,
                status TEXT CHECK(status IN ('pending', 'preparing', 'ready', 'completed', 'cancelled')) DEFAULT 'pending',
                payment_method TEXT CHECK(payment_method IN ('Contanti', 'Bancomat', 'Satispay')) DEFAULT 'Contanti',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                ready_at DATETIME,
                completed_at DATETIME
            );
        ");
        $pdo->exec("
            INSERT INTO orders_new (id, order_number, customer_name, customer_phone, total_amount, status, payment_method, notes, created_at, ready_at, completed_at)
            SELECT id, order_number, customer_name, customer_phone, total_amount, status,
                   CASE payment_method
                       WHEN 'cash' THEN 'Contanti'
                       WHEN 'card' THEN 'Bancomat'
                       WHEN 'digital' THEN 'Satispay'
                       ELSE payment_method
                   END as payment_method,
                   notes, created_at, ready_at, completed_at FROM orders
        ");
        $pdo->exec("DROP TABLE orders");
        $pdo->exec("ALTER TABLE orders_new RENAME TO orders");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at)");
        $pdo->exec('COMMIT');
        $pdo->exec('PRAGMA foreign_keys = ON');
        echo "Migrazione completata.\n";
    } else {
        // Mapping di eventuali residui
        $pdo->exec("UPDATE orders SET payment_method = CASE payment_method WHEN 'cash' THEN 'Contanti' WHEN 'card' THEN 'Bancomat' WHEN 'digital' THEN 'Satispay' ELSE payment_method END");
        echo "Schema già aggiornato; eseguito mapping residui.\n";
    }

    // Verifica finale
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
    $ordersSql2 = $stmt->fetchColumn();
    echo "ORDERS_SQL_AFTER=\n" . $ordersSql2 . "\n";
    echo 'HAS_NEW_CHECK_AFTER=' . (hasNewItalianCheck($ordersSql2) ? '1' : '0') . "\n";
    $pmStmt = $pdo->query("SELECT DISTINCT payment_method FROM orders ORDER BY payment_method");
    $pmValues = $pmStmt->fetchAll(PDO::FETCH_COLUMN);
    echo 'DISTINCT_PM=' . implode(',', $pmValues) . "\n";
} catch (Exception $e) {
    echo 'ERRORE_MIGRAZIONE=' . $e->getMessage() . "\n";
    try { $pdo->exec('ROLLBACK'); } catch (Exception $ee) {}
    $pdo->exec('PRAGMA foreign_keys = ON');
    exit(1);
}

?>