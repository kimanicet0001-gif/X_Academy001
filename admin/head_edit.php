<?php
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

$departments = getDB()->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$error = null;
$fullName = $headUser['full_name'];
$username = $headUser['username'];
$departmentId = $headUser['department_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName     = trim($_POST['full_name'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);

    if ($fullName === '' || $username === '' || $departmentId <= 0) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $chk->execute([$username, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Cet identifiant est déjà utilisé par un autre compte.';
        } else {
            $upd = getDB()->prepare('UPDATE users SET full_name = ?, username = ?, department_id = ? WHERE id = ?');
            $upd->execute([$fullName, $username, $departmentId, $id]);
            logAudit('head_edit', "Chef de département #$id modifié");
            setFlash('success', 'Informations du Chef mises à jour.');
            redirect('/admin/heads.php');
        }
    }
}

$pageTitle = 'Modifier le Chef de Département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier le Chef de Département</h1>

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
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Département</label>
            <select name="department_id" required
                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int) $departmentId === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= h($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/admin/heads.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
