<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM filieres WHERE id = ?');
$stmt->execute([$id]);
$filiere = $stmt->fetch();

if (!$filiere) {
    setFlash('error', 'Filière introuvable.');
    redirect('/admin/filieres.php');
}

$departments = getDB()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$error = null;
$name = $filiere['name'];
$departmentId = $filiere['department_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);

    if ($name === '' || $departmentId <= 0) {
        $error = 'Le nom et le département sont obligatoires.';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM filieres WHERE department_id = ? AND name = ? AND id != ?');
        $chk->execute([$departmentId, $name, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Une autre filière porte déjà ce nom dans ce département.';
        } else {
            $upd = getDB()->prepare('UPDATE filieres SET name = ?, department_id = ? WHERE id = ?');
            $upd->execute([$name, $departmentId, $id]);
            logAudit('filiere_edit', "Filière #$id modifiée");
            setFlash('success', 'Filière mise à jour.');
            redirect('/admin/filieres.php');
        }
    }
}

$pageTitle = 'Modifier la filière';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier la filière</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom de la filière</label>
            <input type="text" name="name" required value="<?= h($name) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Département</label>
            <select name="department_id" required
                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int) $departmentId === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/admin/filieres.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
