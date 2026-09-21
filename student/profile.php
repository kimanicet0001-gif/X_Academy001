<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();

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
$username = $student['username'];
$phone = $student['phone'];
$email = $student['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($fullName === '' || $username === '') {
        $error = "Le nom complet et l'identifiant sont obligatoires.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail n'est pas valide.";
    } elseif (isUsernameTaken($username, 'students', (int) $user['id'])) {
        $error = 'Cet identifiant de connexion est déjà utilisé.';
    } else {
        $upd = getDB()->prepare('UPDATE students SET full_name = ?, username = ?, phone = ?, email = ? WHERE id = ?');
        $upd->execute([$fullName, $username, $phone ?: null, $email ?: null, $user['id']]);

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['username'] = $username;

        setFlash('success', 'Profil mis à jour.');
        redirect('/student/profile.php');
    }
}

$pageTitle = 'Mon profil';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Mon profil</h1>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-3xl">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Informations académiques</h2>
        <div class="space-y-2.5 text-sm">
            <div class="flex justify-between border-b border-slate-100 pb-2.5"><span class="text-slate-500">Département</span><span class="font-medium"><?= h($student['department_name']) ?></span></div>
            <div class="flex justify-between border-b border-slate-100 pb-2.5"><span class="text-slate-500">Filière</span><span class="font-medium"><?= h($student['filiere_name'] ?? '—') ?></span></div>
            <div class="flex justify-between border-b border-slate-100 pb-2.5"><span class="text-slate-500">Promotion</span><span class="font-medium"><?= h($student['promotion_name']) ?><?= $student['group_name'] ? ' — ' . h($student['group_name']) : '' ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Matricule</span><span class="font-medium"><?= h($student['matricule']) ?></span></div>
        </div>
        <p class="text-xs text-slate-400 mt-4">
            Pour corriger votre matricule ou changer de promotion, contactez votre Chef de Département.
        </p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h2 class="font-semibold text-slate-800 mb-4">Mes informations personnelles</h2>
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
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Identifiant de connexion</label>
                <input type="text" name="username" required value="<?= h($username) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Téléphone</label>
                <input type="text" name="phone" value="<?= h($phone) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">E-mail</label>
                <input type="email" name="email" value="<?= h($email) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                    Enregistrer
                </button>
                <a href="<?= BASE_URL ?>/auth/change_password.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Changer mon mot de passe</a>
            </div>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
