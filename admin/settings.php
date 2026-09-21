<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$row = getDB()->query('SELECT * FROM settings WHERE id = 1')->fetch();
$row = $row ?: ['university_name' => 'Université', 'po_box' => '', 'city' => '', 'email' => ''];

$error = null;
$universityName = $row['university_name'];
$poBox = $row['po_box'];
$city = $row['city'];
$email = $row['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $universityName = trim($_POST['university_name'] ?? '');
    $poBox = trim($_POST['po_box'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($universityName === '') {
        $error = "Le nom de l'université est obligatoire.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail n'est pas valide.";
    } else {
        $stmt = getDB()->prepare(
            'INSERT INTO settings (id, university_name, po_box, city, email)
             VALUES (1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE university_name = VALUES(university_name),
                po_box = VALUES(po_box), city = VALUES(city), email = VALUES(email)'
        );
        $stmt->execute([$universityName, $poBox ?: null, $city ?: null, $email ?: null]);
        setFlash('success', 'Informations de l\'université mises à jour.');
        redirect('/admin/settings.php');
    }
}

$pageTitle = "Paramètres de l'université";
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Paramètres de l'université</h1>
<p class="text-slate-500 mb-6">Ces informations apparaissent sur l'en-tête des registres PDF générés.</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom de l'université</label>
            <input type="text" name="university_name" required value="<?= h($universityName) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Boîte postale</label>
            <input type="text" name="po_box" value="<?= h($poBox) ?>" placeholder="Ex : B.P. 2100"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Ville</label>
            <input type="text" name="city" value="<?= h($city) ?>" placeholder="Ex : Bujumbura"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Adresse e-mail de l'université</label>
            <input type="email" name="email" value="<?= h($email) ?>" placeholder="Ex : contact@universite.bi"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Enregistrer
        </button>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
