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

// Helper per comporre URL asset funzionanti sia in root (/) che in sottocartella (/ordigo)
if (!function_exists('asset_path')) {
    function asset_path($path) {
        $path = (string)$path;
        if ($path === '') return '';
        // Non modificare URL assoluti o data URI
        if (preg_match('/^(https?:|data:|\/\/)/i', $path)) {
            return $path;
        }
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $base = $base ? $base : '';
        // Normalizza e garantisce uno slash singolo tra base e path
        return $base . '/' . ltrim($path, '/\\');
    }
}

// Header comune
include 'templates/header.php';

// Routing delle pagine
switch ($route) {
    case 'admin':
        include 'admin/index.php';
        break;
    case 'report':
        // Mostra i report dentro il layout Admin (con sidebar)
        $_GET['page'] = 'reports';
        include 'admin/index.php';
        break;
    default:
        include 'pages/home.php';
}

// Footer comune
include 'templates/footer.php';
// Flush finale del buffer
if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
?>