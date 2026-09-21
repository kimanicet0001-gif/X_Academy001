<?php
/**
 * index.php
 * Point d'entrée : redirige vers le tableau de bord si connecté,
 * sinon vers la page de connexion.
 */
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user'])) {
    redirect(dashboardPath($_SESSION['user']['role']));
}
redirect('/auth/login.php');
