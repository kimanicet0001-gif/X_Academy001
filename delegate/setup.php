<?php
/**
 * delegate/setup.php
 * La promotion (année académique, semestre, groupe) est créée et
 * assignée par le Chef de Département. Cette page permet au Délégué
 * de consulter cette configuration et de vérifier que tout est correct
 * avant de commencer à enregistrer des cours.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];

$stmt = getDB()->prepare(
    "SELECT p.*, d.name AS department_name, f.name AS filiere_name,
            (SELECT COUNT(*) FROM students s WHERE s.promotion_id = p.id) AS student_count
     FROM promotions p
     JOIN departments d ON d.id = p.department_id
     LEFT JOIN filieres f ON f.id = p.filiere_id
     WHERE p.delegate_id = ?
     ORDER BY p.status = 'active' DESC, p.created_at DESC
     LIMIT 1"
);
$stmt->execute([$userId]);
$promotion = $stmt->fetch();

$pageTitle = 'Ma promotion';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Configuration de ma promotion</h1>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3">
        Aucune promotion ne vous est encore assignée par votre Chef de Département.
        Cette étape est nécessaire avant de pouvoir créer des cours et faire l'appel.
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 max-w-lg space-y-3">
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Département</span>
            <span class="font-medium"><?= h($promotion['department_name']) ?></span>
        </div>
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Filière</span>
            <span class="font-medium"><?= h($promotion['filiere_name'] ?? '—') ?></span>
        </div>
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Promotion</span>
            <span class="font-medium"><?= h($promotion['name']) ?></span>
        </div>
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Groupe</span>
            <span class="font-medium"><?= h($promotion['group_name'] ?? '—') ?></span>
        </div>
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Année académique</span>
            <span class="font-medium"><?= h($promotion['academic_year']) ?></span>
        </div>
        <div class="flex justify-between border-b border-slate-100 pb-2.5">
            <span class="text-slate-500">Semestre</span>
            <span class="font-medium"><?= h($promotion['semester']) ?></span>
        </div>
        <div class="flex justify-between">
            <span class="text-slate-500">Étudiants enregistrés</span>
            <span class="font-medium"><?= (int) $promotion['student_count'] ?></span>
        </div>
    </div>

    <p class="text-sm text-slate-500 mt-4">
        Pour modifier ces informations (nom, année, semestre), contactez votre Chef de Département.
        Vous pouvez en revanche gérer librement la liste de vos étudiants et vos cours.
    </p>

    <div class="mt-4 flex gap-3">
        <a href="<?= BASE_URL ?>/delegate/students.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
            Gérer mes étudiants
        </a>
        <a href="<?= BASE_URL ?>/delegate/courses.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">
            Gérer mes cours
        </a>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
