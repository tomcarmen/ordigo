<?php
// Endpoint di sincronizzazione offline
// Accetta payload JSON e risponde con 200 OK senza effetti collaterali per ora.

header('Content-Type: application/json; charset=utf-8');

// Solo POST supportato
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Leggi JSON del corpo
$raw = file_get_contents('php://input');
$data = null;
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $data = $decoded;
    }
}

// Placeholder: in futuro si potrà registrare l'operazione in sync_log
// e processarla quando online.

echo json_encode([
    'status' => 'ok',
    'received' => $data ?? null
]);
exit;
?>