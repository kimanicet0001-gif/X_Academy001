<?php
/**
 * auth/logout.php
 * Détruit la session en cours et renvoie vers la page de connexion.
 */
require_once __DIR__ . '/../config/config.php';

logAudit('logout', 'Déconnexion');
$_SESSION = [];
session_destroy();

session_start();
setFlash('info', 'Vous avez été déconnecté.');
redirect('/auth/login.php');
