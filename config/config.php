<?php
/**
 * config/config.php
 * Configuration générale de X_Academy.
 * Ce fichier doit être inclus en tout premier, avant tout affichage HTML.
 */

// Affichage des erreurs (mettre à 0 / 'Off' en production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Africa/Bujumbura');

// Démarrage de la session (une seule fois)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// URL de base de l'application (adapter selon l'installation XAMPP)
// Exemple : http://localhost/X_Academy
define('BASE_URL', '/X_Academy');

// Chemin absolu vers la racine du projet
define('ROOT_PATH', dirname(__DIR__));

// Dossier de stockage des PDF générés
define('PDF_STORAGE_PATH', ROOT_PATH . '/uploads/pdf');

// Nom de l'application (affiché dans les en-têtes / titres)
define('APP_NAME', 'X_Academy');

// Durée réelle (en minutes) d'une "heure" académique dans cette université.
// Sert à convertir les horaires réels des séances en heures académiques
// pour le calcul du volume horaire des cours (ex: 50 min = 1 heure académique).
define('MINUTES_PER_ACADEMIC_HOUR', 50);

// Chargement des fichiers communs
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/permissions.php';
require_once ROOT_PATH . '/includes/flash.php';
