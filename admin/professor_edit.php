<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'professor'");
$stmt->execute([$id]);
$prof = $stmt->fetch();

if (!$prof) {
    setFlash('error', 'Professeur introuvable.');
    redirect('/admin/professors.php');
}

$error = null;
$fullName = $prof['full_name'];
$username = $prof['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($fullName === '' || $username === '') {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (isUsernameTaken($username, 'users', $id)) {
        $error = 'Cet identifiant est déjà utilisé par un autre compte.';
    } else {
        getDB()->prepare('UPDATE users SET full_name = ?, username = ? WHERE id = ?')->execute([$fullName, $username, $id]);
        logAudit('professor_edit', "Professeur #$id modifié");
        setFlash('success', 'Informations du professeur mises à jour.');
        redirect('/admin/professors.php');
    }
}

$pageTitle = 'Modifier le professeur';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier le professeur</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom complet</label>
            <input type="text" name="full_name" required value="<?= h($fullName) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Identifiant</label>
            <input type="text" name="username" required value="<?= h($username) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Enregistrer</button>
            <a href="<?= BASE_URL ?>/admin/professors.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
