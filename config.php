<?php

// ==============================
// CONFIGURATION BASE DE DONNÉES
// ==============================
define('DB_HOST', 'sqledu.univ-eiffel.fr');
define('DB_PORT', '5432');
define('DB_NAME', 'cheikhbencheik_db');
define('DB_USER', 'cheikhbencheik');
define('DB_PASS', 'Nasseredine14/');   // À changer si nécessaire

// ==============================
// CONFIGURATION SITE
// ==============================
define('BASE_URL', '/~cheikhbencheik/sae204');
define('SITE_NAME', 'FruitsPro — Gestion commerciale');
define('SESSION_DURATION', 3600);   // 1 heure

// ==============================
// CONFIGURATION APPLICATION
// ==============================
define('DEFAULT_ID_PERSONNEL', 1);
define('STATUT_COMMANDE_SAISIE',   'Saisie');
define('STATUT_COMMANDE_PREPAREE', 'Preparee');
define('STATUT_COMMANDE_LIVREE',   'Livree');

// ==============================
// SÉCURITÉ
// ==============================
define('DEBUG_MODE', true);   // false pour le rendu final

// Affichage des erreurs PHP uniquement en debug
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
