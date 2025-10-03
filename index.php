<?php
// Applicazione gestione ordini Web Festa Oratorio
// Configurazione iniziale
session_start();

// Includi la configurazione del database
require_once 'config/database.php';

// Routing semplice
$route = $_GET['route'] ?? 'home';

// Header comune
include 'templates/header.php';

// Routing delle pagine
switch ($route) {
    case 'admin':
        include 'admin/index.php';
        break;
    case 'report':
        include 'admin/reports.php';
        break;
    default:
        include 'pages/home.php';
}

// Footer comune
include 'templates/footer.php';
?>