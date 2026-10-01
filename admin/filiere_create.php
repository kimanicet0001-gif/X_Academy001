<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$departments = getDB()->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();

$error = null;
$name = '';
$departmentId = (int) ($_GET['department_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);

    if ($name === '' || $departmentId <= 0) {
        $error = 'Le nom et le département sont obligatoires.';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM filieres WHERE department_id = ? AND name = ?');
        $chk->execute([$departmentId, $name]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Cette filière existe déjà dans ce département.';
        } else {
            $ins = getDB()->prepare('INSERT INTO filieres (department_id, name, status) VALUES (?, ?, "active")');
            $ins->execute([$departmentId, $name]);
            logAudit('filiere_create', "Filière créée : $name");
            setFlash('success', 'Filière créée avec succès.');
            redirect('/admin/filieres.php');
        }
    }
}

$pageTitle = 'Nouvelle filière';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Nouvelle filière</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom de la filière</label>
            <input type="text" name="name" required value="<?= h($name) ?>" placeholder="Ex : Systèmes et Réseaux"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Département</label>
            <select name="department_id" required
                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                <option value="">-- Choisir --</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $departmentId === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer
            </button>
            <a href="<?= BASE_URL ?>/admin/filieres.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
