<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM departments WHERE id = ?');
$stmt->execute([$id]);
$department = $stmt->fetch();

if (!$department) {
    setFlash('error', 'Département introuvable.');
    redirect('/admin/departments.php');
}

$error = null;
$name = $department['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Le nom du département est obligatoire.';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM departments WHERE name = ? AND id != ?');
        $chk->execute([$name, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Un autre département porte déjà ce nom.';
        } else {
            $upd = getDB()->prepare('UPDATE departments SET name = ? WHERE id = ?');
            $upd->execute([$name, $id]);
            logAudit('department_edit', "Département #$id renommé en : $name");
            setFlash('success', 'Département mis à jour.');
            redirect('/admin/departments.php');
        }
    }
}

$pageTitle = 'Modifier le département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier le département</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom du département</label>
            <input type="text" name="name" required value="<?= h($name) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/admin/departments.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
