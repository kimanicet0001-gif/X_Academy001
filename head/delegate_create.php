<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$error = null;
$fullName = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($fullName === '' || $username === '') {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $chk->execute([$username]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Cet identifiant est déjà utilisé.';
        } else {
            $tempPassword = generateTempPassword();
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

            $ins = getDB()->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, department_id, status, must_change_password)
                 VALUES (?, ?, ?, "delegate", ?, "active", 1)'
            );
            $ins->execute([$username, $hash, $fullName, $deptId]);

            logAudit('delegate_create', "Délégué créé : \$username");
            logAudit('delegate_create', "Délégué créé : $username");
            setFlash('success', "Délégué créé. Identifiant : $username — Mot de passe temporaire : $tempPassword", true);
            redirect('/head/delegates.php');
        }
    }
}

$pageTitle = 'Nouveau Délégué';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Nouveau Délégué</h1>

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
        <p class="text-xs text-slate-500">Un mot de passe temporaire sera généré automatiquement ; le Délégué devra le changer à sa première connexion.</p>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer le compte
            </button>
            <a href="<?= BASE_URL ?>/head/delegates.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
