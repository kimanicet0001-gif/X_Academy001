<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$id = (int) ($_GET['id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/students.php');
}

$stmt = getDB()->prepare('SELECT * FROM students WHERE id = ? AND promotion_id = ?');
$stmt->execute([$id, $promotion['id']]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Étudiant introuvable.');
    redirect('/delegate/students.php');
}

$newPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = generateTempPassword();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    getDB()->prepare('UPDATE students SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([$hash, $id]);
    logAudit('student_password_reset', "Mot de passe réinitialisé pour l'étudiant #$id");
}

$pageTitle = 'Réinitialiser le mot de passe';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Réinitialiser le mot de passe</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <p class="mb-4 text-slate-600">
        Étudiant : <strong><?= h($student['full_name']) ?></strong> (<?= h($student['username']) ?>)
    </p>

    <?php if ($newPassword): ?>
        <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl px-4 py-3 mb-4 text-sm">
            Nouveau mot de passe temporaire : <strong class="font-mono"><?= h($newPassword) ?></strong><br>
            Communiquez-le à l'étudiant — il devra le changer à sa prochaine connexion.
        </div>
        <a href="<?= BASE_URL ?>/delegate/students.php" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm">Retour à la liste</a>
    <?php else: ?>
        <p class="text-slate-600 mb-4 text-sm">
            Cette action génère un nouveau mot de passe temporaire pour cet étudiant.
            Il devra le modifier à sa prochaine connexion.
        </p>
        <form method="post">
            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Générer un nouveau mot de passe
            </button>
            <a href="<?= BASE_URL ?>/delegate/students.php" class="ml-3 text-slate-500 hover:text-slate-700 text-sm transition">Annuler</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
