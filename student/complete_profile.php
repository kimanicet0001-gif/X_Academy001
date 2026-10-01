<?php
/**
 * student/complete_profile.php
 * Étape obligatoire après le changement de mot de passe forcé, avant
 * tout autre accès. L'étudiant renseigne son identité complète. Le
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
$lastName        = $student['last_name'] ?? '';
$firstName       = $student['first_name'] ?? '';
$birthDate       = $student['birth_date'] ?? '';
$nationality     = $student['nationality'] ?? '';
$phone           = $student['phone'] ?? '';
$email           = $student['email'] ?? '';
$currentAddress  = $student['current_address'] ?? '';
$homeAddress     = $student['home_address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName       = trim($_POST['last_name'] ?? '');
    $firstName      = trim($_POST['first_name'] ?? '');
    $birthDate      = trim($_POST['birth_date'] ?? '');
    $nationality    = trim($_POST['nationality'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $currentAddress = trim($_POST['current_address'] ?? '');
    $homeAddress    = trim($_POST['home_address'] ?? '');

    if ($lastName === '' || $firstName === '') {
        $error = 'Le nom et le prénom sont obligatoires.';
    } elseif ($birthDate === '' || !DateTime::createFromFormat('Y-m-d', $birthDate)) {
        $error = 'La date de naissance est obligatoire et doit être valide.';
    } elseif ($nationality === '') {
        $error = 'La nationalité est obligatoire.';
    } elseif ($currentAddress === '' || $homeAddress === '') {
        $error = "L'adresse actuelle et l'adresse d'origine sont obligatoires.";
    } elseif ($phone === '') {
        $error = 'Le numéro de téléphone est obligatoire.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail est obligatoire et doit être valide.";
    } else {
        $fullName = trim($firstName . ' ' . $lastName);
        $upd = getDB()->prepare(
            'UPDATE students SET full_name = ?, last_name = ?, first_name = ?, birth_date = ?, nationality = ?,
                phone = ?, email = ?, current_address = ?, home_address = ?, profile_completed = 1
             WHERE id = ?'
        );
        $upd->execute([$fullName, $lastName, $firstName, $birthDate, $nationality,
            $phone, $email, $currentAddress, $homeAddress, $user['id']]);

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['profile_completed'] = true;

        logAudit('profile_complete', 'Profil étudiant complété');
        setFlash('success', 'Profil complété. Bienvenue sur X_Academy !');
        redirect('/student/dashboard.php');
    }
}

$pageTitle = 'Compléter mon profil';
require_once ROOT_PATH . '/includes/header.php';
$input = 'w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition';
$label = 'block text-sm font-medium text-slate-600 mb-1.5';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Bienvenue sur X_Academy</h1>
<p class="text-slate-500 mb-6">Avant de continuer, complétez vos informations personnelles.</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-xl">
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
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="<?= $label ?>">Nom de famille</label>
                <input type="text" name="last_name" required value="<?= h($lastName) ?>" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Prénom</label>
                <input type="text" name="first_name" required value="<?= h($firstName) ?>" class="<?= $input ?>">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="<?= $label ?>">Date de naissance</label>
                <input type="date" name="birth_date" required value="<?= h($birthDate) ?>" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Nationalité</label>
                <input type="text" name="nationality" required value="<?= h($nationality) ?>" class="<?= $input ?>">
            </div>
        </div>
        <div>
            <label class="<?= $label ?>">Adresse actuelle</label>
            <input type="text" name="current_address" required value="<?= h($currentAddress) ?>" placeholder="Lieu de résidence pendant les études" class="<?= $input ?>">
        </div>
        <div>
            <label class="<?= $label ?>">Adresse d'origine</label>
            <input type="text" name="home_address" required value="<?= h($homeAddress) ?>" placeholder="Adresse familiale / de résidence habituelle" class="<?= $input ?>">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="<?= $label ?>">Téléphone</label>
                <input type="text" name="phone" required value="<?= h($phone) ?>" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">E-mail</label>
                <input type="email" name="email" required value="<?= h($email) ?>" class="<?= $input ?>">
            </div>
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition">
            Enregistrer et continuer
        </button>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
