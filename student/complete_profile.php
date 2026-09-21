<?php
/**
 * student/complete_profile.php
 * Étape obligatoire après le changement de mot de passe forcé, avant
 * tout autre accès. L'étudiant confirme/corrige son identité. Le
 * matricule et la promotion (Département > Filière > Promotion > Groupe)
 * ne sont jamais modifiables ici — ils sont hérités du compte créé par
 * le Délégué.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$user = currentUser();
if ($user['role'] !== ROLE_STUDENT) {
    redirect(dashboardPath($user['role']));
}
if (!empty($user['must_change_password'])) {
    redirect('/auth/change_password.php');
}
if (!empty($user['profile_completed'])) {
    redirect('/student/dashboard.php');
}

$stmt = getDB()->prepare(
    "SELECT s.*, p.name AS promotion_name, p.group_name, p.academic_year, p.semester,
            f.name AS filiere_name, d.name AS department_name
     FROM students s
     JOIN promotions p ON p.id = s.promotion_id
     LEFT JOIN filieres f ON f.id = p.filiere_id
     JOIN departments d ON d.id = p.department_id
     WHERE s.id = ?"
);
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$error = null;
$fullName = $student['full_name'];
$phone = $student['phone'];
$email = $student['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($fullName === '') {
        $error = 'Le nom complet est obligatoire.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail n'est pas valide.";
    } else {
        $upd = getDB()->prepare('UPDATE students SET full_name = ?, phone = ?, email = ?, profile_completed = 1 WHERE id = ?');
        $upd->execute([$fullName, $phone ?: null, $email ?: null, $user['id']]);

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['profile_completed'] = true;

        logAudit('profile_complete', 'Profil étudiant complété');
        setFlash('success', 'Profil complété. Bienvenue sur X_Academy !');
        redirect('/student/dashboard.php');
    }
}

$pageTitle = 'Compléter mon profil';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Bienvenue sur X_Academy</h1>
<p class="text-slate-500 mb-6">Avant de continuer, vérifiez et complétez vos informations personnelles.</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <div class="bg-slate-50 rounded-xl p-4 mb-6 text-sm space-y-1.5">
        <div class="flex justify-between"><span class="text-slate-500">Département</span><span class="font-medium"><?= h($student['department_name']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Filière</span><span class="font-medium"><?= h($student['filiere_name'] ?? '—') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Promotion</span><span class="font-medium"><?= h($student['promotion_name']) ?><?= $student['group_name'] ? ' — ' . h($student['group_name']) : '' ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Matricule</span><span class="font-medium"><?= h($student['matricule']) ?></span></div>
    </div>
    <p class="text-xs text-slate-400 mb-4">Ces informations sont gérées par votre établissement et ne sont pas modifiables ici.</p>

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
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Téléphone (optionnel)</label>
            <input type="text" name="phone" value="<?= h($phone) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">E-mail (optionnel)</label>
            <input type="email" name="email" value="<?= h($email) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition">
            Continuer
        </button>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
