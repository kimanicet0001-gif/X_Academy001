<?php
/**
 * auth/change_password.php
 * Permet à un utilisateur connecté (Délégué, Chef, Admin) de changer
 * son mot de passe. Obligatoire si must_change_password = 1.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$user = currentUser();
$isStudent = $user['role'] === ROLE_STUDENT;
$table = $isStudent ? 'students' : 'users';
$error = null;
$success = null;
$forced = !empty($user['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = getDB()->prepare("SELECT password_hash FROM $table WHERE id = ?");
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Le mot de passe actuel est incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($new !== $confirm) {
        $error = 'La confirmation ne correspond pas au nouveau mot de passe.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = getDB()->prepare("UPDATE $table SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $upd->execute([$newHash, $user['id']]);

        logAudit('password_change', 'Mot de passe changé par l\'utilisateur lui-même');
        $_SESSION['user']['must_change_password'] = false;
        setFlash('success', 'Votre mot de passe a été mis à jour.');

        if ($isStudent && empty($user['profile_completed'])) {
            redirect('/student/complete_profile.php');
        }
        redirect(dashboardPath($user['role']));
    }
}

$pageTitle = 'Changer le mot de passe';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 <?= $forced ? '' : 'max-w-lg mx-auto' ?>">
    <h1 class="text-xl font-display font-bold text-slate-900 mb-2">Changer le mot de passe</h1>

    <?php if ($forced): ?>
        <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mb-4 text-sm">
            Pour votre sécurité, vous devez définir un nouveau mot de passe avant de continuer.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Mot de passe actuel</label>
            <input type="password" name="current_password" required
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nouveau mot de passe</label>
            <input type="password" name="new_password" required minlength="6"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Confirmer le nouveau mot de passe</label>
            <input type="password" name="confirm_password" required minlength="6"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Mettre à jour
        </button>
        <?php if (!$forced): ?>
            <a href="<?= dashboardUrlForRole($user['role']) ?>" class="ml-3 text-slate-500 hover:text-slate-700 text-sm transition">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
