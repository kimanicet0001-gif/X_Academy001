<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$error = null;
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Le nom du département est obligatoire.';
    } else {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM departments WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Un département portant ce nom existe déjà.';
        } else {
            $ins = getDB()->prepare('INSERT INTO departments (name, status) VALUES (?, "active")');
            $ins->execute([$name]);
            logAudit('department_create', "Département créé : $name");
            setFlash('success', 'Département créé avec succès.');
            redirect('/admin/departments.php');
        }
    }
}

$pageTitle = 'Nouveau département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Nouveau département</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom du département</label>
            <input type="text" name="name" required value="<?= h($name) ?>"
                   placeholder="Ex : Télécommunications"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer
            </button>
            <a href="<?= BASE_URL ?>/admin/departments.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
