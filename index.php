<?php
// Applicazione gestione ordini Web Festa Oratorio
// Configurazione iniziale
session_start();
// Abilita buffering di output per consentire header() anche dopo inclusioni
if (!headers_sent()) { ob_start(); }

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
// Flush finale del buffer
if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
?>