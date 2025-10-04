<?php
// Endpoint semplice per controllo aggiornamenti
// Restituisce JSON con campo "updates" (false per default)

header('Content-Type: application/json; charset=utf-8');

// In futuro si può aggiungere logica per determinare aggiornamenti reali,
// ad esempio confrontando timestamp di ultimo aggiornamento in DB.
// Per ora, risponde sempre con nessun aggiornamento per evitare errori del client.

echo json_encode(['updates' => false]);
exit;
?>