<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

if (!$promotion) {
    setFlash('error', 'Aucune promotion assignée. Contactez votre Chef de Département.');
    redirect('/delegate/students.php');
}

$error = null;
$fullName = '';
$matricule = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name'] ?? '');
    $matricule = trim($_POST['matricule'] ?? '');
    $username  = trim($_POST['username'] ?? '');

    if ($fullName === '' || $matricule === '' || $username === '') {
        $error = 'Le nom complet, le matricule et l\'identifiant sont obligatoires.';
    } elseif (isMatriculeTaken($matricule)) {
        $error = 'Ce matricule est déjà utilisé par un autre étudiant.';
    } elseif (isUsernameTaken($username)) {
        $error = 'Cet identifiant de connexion est déjà utilisé.';
    } else {
        $tempPassword = generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $ins = getDB()->prepare(
            'INSERT INTO students (promotion_id, full_name, matricule, username, password_hash, status, must_change_password, profile_completed)
             VALUES (?, ?, ?, ?, ?, "active", 1, 0)'
        );
        $ins->execute([$promotion['id'], $fullName, $matricule, $username, $hash]);
        logAudit('student_create', "Étudiant créé : $fullName ($matricule)");

        setFlash('success', "Étudiant ajouté. Identifiant : $username — Mot de passe temporaire : $tempPassword", true);
        redirect('/delegate/students.php');
    }
}

$pageTitle = 'Ajouter un étudiant';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Ajouter un étudiant</h1>

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
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Matricule</label>
            <input type="text" name="matricule" required value="<?= h($matricule) ?>" placeholder="Ex : ETU-2026-0001"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            <p class="text-xs text-slate-400 mt-1">Une fois le compte créé, seul le Chef de Département pourra corriger ce matricule.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Identifiant de connexion</label>
            <input type="text" name="username" required value="<?= h($username) ?>" placeholder="Ex : jdupont"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <p class="text-xs text-slate-500">Un mot de passe temporaire sera généré automatiquement. L'étudiant devra le changer et compléter son profil à sa première connexion.</p>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer le compte
            </button>
            <a href="<?= BASE_URL ?>/delegate/students.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
