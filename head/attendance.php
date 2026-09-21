<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$sessionId = (int) ($_GET['session_id'] ?? 0);

$stmt = getDB()->prepare(
    "SELECT sess.*, c.name AS course_name, p.name AS promotion_name
     FROM sessions sess
     JOIN courses c ON c.id = sess.course_id
     JOIN promotions p ON p.id = c.promotion_id
     WHERE sess.id = ? AND p.department_id = ?"
);
$stmt->execute([$sessionId, $deptId]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Séance introuvable.');
    redirect('/head/sessions.php');
}

$stmt = getDB()->prepare(
    "SELECT st.full_name, a.status
     FROM attendance a
     JOIN students st ON st.id = a.student_id
     WHERE a.session_id = ?
     ORDER BY st.full_name"
);
$stmt->execute([$sessionId]);
$attendance = $stmt->fetchAll();

$present = count(array_filter($attendance, fn($a) => $a['status'] === 'present'));
$total = count($attendance);
$rate = calculateAttendanceRate($present, $total);

$pageTitle = 'Présences de la séance';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight"><?= h($session['course_name']) ?> — <?= formatDateFr($session['session_date']) ?></h1>
<p class="text-slate-500 mb-6"><?= h($session['promotion_name']) ?> · Matière : <?= h($session['topic']) ?></p>

<div class="grid grid-cols-3 gap-4 mb-6 max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-slate-900"><?= $total ?></div>
        <div class="text-xs text-slate-500">Total étudiants</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-green-600"><?= $present ?></div>
        <div class="text-xs text-slate-500">Présents</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-indigo-600"><?= $rate ?>%</div>
        <div class="text-xs text-slate-500">Taux de présence</div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-lg">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold"><th class="py-2 px-4">Étudiant</th><th class="py-2 px-4">Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($attendance as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="py-2 px-4"><?= h($a['full_name']) ?></td>
                <td class="py-2 px-4">
                    <?php if ($a['status'] === 'present'): ?>
                        <span class="inline-flex items-center gap-1 text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Présent</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-red-600 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full text-xs font-medium">Absent</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
