<?php
/**
 * auth/unauthorized.php
 * Affichée quand un utilisateur connecté tente d'accéder à une page
 * réservée à un autre rôle.
 */
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Accès refusé';
$user = currentUser();
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-200/60 p-8 text-center">
    <div class="w-16 h-16 mx-auto rounded-2xl bg-red-50 flex items-center justify-center mb-4">
        <?= icon('shield-alert', 'w-8 h-8 text-red-600') ?>
    </div>
    <h1 class="text-xl font-display font-bold text-red-700 mb-2">Accès non autorisé</h1>
    <p class="text-slate-600 mb-6">
        Vous n'avez pas les droits nécessaires pour accéder à cette page.
    </p>
    <?php if ($user): ?>
        <a href="<?= dashboardUrlForRole($user['role']) ?>"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Retour à mon tableau de bord
        </a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/auth/login.php"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Se connecter
        </a>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
