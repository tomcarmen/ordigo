<?php
// Diagnostica schema tabella orders e metodi di pagamento
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
$sql = $stmt->fetchColumn();
echo "ORDERS_SQL=\n" . $sql . "\n";

$normalized = strtolower(preg_replace('/\s+/', '', $sql));
$hasNewCheck = strpos($normalized, "check(payment_methodin('contanti','bancomat','satispay'))") !== false;
echo 'HAS_NEW_CHECK=' . ($hasNewCheck ? '1' : '0') . "\n";

$pmStmt = $pdo->query("SELECT DISTINCT payment_method FROM orders ORDER BY payment_method");
$pmValues = $pmStmt->fetchAll(PDO::FETCH_COLUMN);
echo 'DISTINCT_PM=' . implode(',', $pmValues) . "\n";

?>