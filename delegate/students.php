<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

$students = [];
if ($promotion) {
    $stmt = getDB()->prepare('SELECT * FROM students WHERE promotion_id = ? ORDER BY full_name');
    $stmt->execute([$promotion['id']]);
    $students = $stmt->fetchAll();
}

$pageTitle = 'Mes étudiants';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Mes étudiants</h1>
    <?php if ($promotion): ?>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/delegate/export_students.php"
               class="inline-flex items-center gap-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-3.5 py-2.5 rounded-lg transition">
                <?= icon('download', 'w-4 h-4') ?> Exporter CSV
            </a>
            <a href="<?= BASE_URL ?>/delegate/student_create.php"
               class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
                + Ajouter un étudiant
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3">
        Aucune promotion assignée pour le moment. Contactez votre Chef de Département.
    </div>
<?php else: ?>
    <p class="text-slate-500 mb-4">
        Promotion : <strong><?= h($promotion['name']) ?><?= $promotion['group_name'] ? ' — ' . h($promotion['group_name']) : '' ?></strong>
        — <?= count($students) ?> étudiant(s)
    </p>

    <div class="flex justify-end mb-3">
    <input type="text" data-table-search="#delegateStudentsTable" placeholder="Rechercher un étudiant..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm" id="delegateStudentsTable">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                    <th class="py-3 px-4">#</th>
                    <th class="py-3 px-4">Nom complet</th>
                    <th class="py-3 px-4">Matricule</th>
                    <th class="py-3 px-4">Assiduité</th>
                    <th class="py-3 px-4">Statut</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $s):
                $stats = getStudentAttendanceStats((int) $s['id']);
                $risk = classifyRisk($stats['rate'], $stats['total_sessions']);
            ?>
                <tr class="border-t border-slate-100">
                    <td class="py-2 px-4 text-slate-400"><?= $i + 1 ?></td>
                    <td class="py-2 px-4 font-medium">
                        <a href="<?= BASE_URL ?>/delegate/student_view.php?id=<?= $s['id'] ?>" class="hover:text-indigo-600"><?= h($s['full_name']) ?></a>
                    </td>
                    <td class="py-2 px-4 text-slate-500"><?= h($s['matricule'] ?? '-') ?></td>
                    <td class="py-2 px-4">
                        <span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $risk['color'] ?>"><?= h($risk['label']) ?></span>
                    </td>
                    <td class="py-2 px-4">
                        <?php if ($s['status'] === 'active'): ?>
                            <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                        <?php else: ?>
                            <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 px-4 text-right">
                        <a href="<?= BASE_URL ?>/delegate/student_edit.php?id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Modifier</a>
                        <a href="<?= BASE_URL ?>/delegate/student_password.php?id=<?= $s['id'] ?>" class="text-amber-700 hover:text-amber-800 font-medium">Mot de passe</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucun étudiant enregistré pour cette promotion.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
