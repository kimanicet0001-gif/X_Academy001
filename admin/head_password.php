<?php
/**
 * admin/head_password.php
 * Réinitialise le mot de passe d'un Chef de Département (oubli, etc.).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'head'");
$stmt->execute([$id]);
$headUser = $stmt->fetch();

if (!$headUser) {
    setFlash('error', 'Chef de département introuvable.');
    redirect('/admin/heads.php');
}

$newPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = generateTempPassword();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    getDB()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([$hash, $id]);
    logAudit('head_password_reset', "Mot de passe réinitialisé pour le Chef de Département #$id");
}

$pageTitle = 'Réinitialiser le mot de passe';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Réinitialiser le mot de passe</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <p class="mb-4 text-slate-600">
        Chef de département : <strong><?= h($headUser['full_name']) ?></strong> (<?= h($headUser['username']) ?>)
    </p>

    <?php if ($newPassword): ?>
        <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl px-4 py-3 mb-4 text-sm">
            Nouveau mot de passe temporaire : <strong class="font-mono"><?= h($newPassword) ?></strong><br>
            Communiquez-le au Chef de Département — il devra le changer à sa prochaine connexion.
        </div>
        <a href="<?= BASE_URL ?>/admin/heads.php" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm">Retour à la liste</a>
    <?php else: ?>
        <p class="text-slate-600 mb-4 text-sm">
            Cette action génère un nouveau mot de passe temporaire pour ce Chef de Département.
            Il devra le modifier à sa prochaine connexion.
        </p>
        <form method="post">
            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Générer un nouveau mot de passe
            </button>
            <a href="<?= BASE_URL ?>/admin/heads.php" class="ml-3 text-slate-500 hover:text-slate-700 text-sm transition">Annuler</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
