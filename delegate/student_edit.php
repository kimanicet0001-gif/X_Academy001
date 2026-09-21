<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$id = (int) ($_GET['id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/students.php');
}

$stmt = getDB()->prepare('SELECT * FROM students WHERE id = ? AND promotion_id = ?');
$stmt->execute([$id, $promotion['id']]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Étudiant introuvable.');
    redirect('/delegate/students.php');
}

$error = null;
$fullName = $student['full_name'];
$username = $student['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $newStatus = isset($_POST['status']) ? 'active' : 'inactive';

    if ($fullName === '' || $username === '') {
        $error = "Le nom complet et l'identifiant sont obligatoires.";
    } elseif (isUsernameTaken($username, 'students', $id)) {
        $error = 'Cet identifiant de connexion est déjà utilisé.';
    } else {
        $upd = getDB()->prepare('UPDATE students SET full_name = ?, username = ?, status = ? WHERE id = ?');
        $upd->execute([$fullName, $username, $newStatus, $id]);
        setFlash('success', 'Étudiant mis à jour.');
        redirect('/delegate/students.php');
    }
}

$pageTitle = "Modifier l'étudiant";
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier l'étudiant</h1>

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
            <input type="text" value="<?= h($student['matricule']) ?>" disabled
                   class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-lg px-3.5 py-2.5 text-sm cursor-not-allowed">
            <p class="text-xs text-slate-400 mt-1">Verrouillé — seul le Chef de Département peut le corriger, sur demande.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Identifiant de connexion</label>
            <input type="text" name="username" required value="<?= h($username) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="status" <?= $student['status'] === 'active' ? 'checked' : '' ?>>
            Étudiant actif
        </label>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/delegate/student_password.php?id=<?= $id ?>" class="text-amber-700 hover:text-amber-800 font-medium text-sm self-center">Réinitialiser le mot de passe</a>
            <a href="<?= BASE_URL ?>/delegate/students.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
