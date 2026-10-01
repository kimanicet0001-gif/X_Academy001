<?php
/**
 * search.php
 * Recherche transversale, dont la portée dépend du rôle connecté :
 * - Admin : toute l'université
 * - Chef : son département
 * - Délégué : sa promotion
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/auth/auth.php';
requireLogin();

$q = trim($_GET['q'] ?? '');
$user = currentUser();
$db = getDB();

$results = ['students' => [], 'courses' => [], 'promotions' => [], 'departments' => []];

if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';

    if ($user['role'] === ROLE_ADMIN) {
        $stmt = $db->prepare(
            "SELECT s.id, s.full_name, p.name AS promotion_name, d.name AS department_name
             FROM students s JOIN promotions p ON p.id = s.promotion_id JOIN departments d ON d.id = p.department_id
             WHERE s.full_name LIKE ? LIMIT 15"
        );
        $stmt->execute([$like]);
        $results['students'] = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT c.id, c.name, c.teacher_name, p.name AS promotion_name, d.name AS department_name
             FROM courses c JOIN promotions p ON p.id = c.promotion_id JOIN departments d ON d.id = p.department_id
             WHERE c.name LIKE ? OR c.teacher_name LIKE ? LIMIT 15"
        );
        $stmt->execute([$like, $like]);
        $results['courses'] = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT id, name, group_name FROM promotions WHERE name LIKE ? OR group_name LIKE ? LIMIT 15");
        $stmt->execute([$like, $like]);
        $results['promotions'] = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT id, name FROM departments WHERE name LIKE ? LIMIT 15");
        $stmt->execute([$like]);
        $results['departments'] = $stmt->fetchAll();

    } elseif ($user['role'] === ROLE_HEAD) {
        $deptId = $user['department_id'];

        $stmt = $db->prepare(
            "SELECT s.id, s.full_name, p.name AS promotion_name
             FROM students s JOIN promotions p ON p.id = s.promotion_id
             WHERE p.department_id = ? AND s.full_name LIKE ? LIMIT 20"
        );
        $stmt->execute([$deptId, $like]);
        $results['students'] = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT c.id, c.name, c.teacher_name, p.name AS promotion_name
             FROM courses c JOIN promotions p ON p.id = c.promotion_id
             WHERE p.department_id = ? AND (c.name LIKE ? OR c.teacher_name LIKE ?) LIMIT 20"
        );
        $stmt->execute([$deptId, $like, $like]);
        $results['courses'] = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT id, name, group_name FROM promotions WHERE department_id = ? AND (name LIKE ? OR group_name LIKE ?) LIMIT 20");
        $stmt->execute([$deptId, $like, $like]);
        $results['promotions'] = $stmt->fetchAll();

    } elseif ($user['role'] === ROLE_DELEGATE) {
        $promotion = getDelegatePromotion((int) $user['id']);
        if ($promotion) {
            $stmt = $db->prepare('SELECT id, full_name FROM students WHERE promotion_id = ? AND full_name LIKE ? LIMIT 25');
            $stmt->execute([$promotion['id'], $like]);
            $results['students'] = $stmt->fetchAll();

            $stmt = $db->prepare('SELECT id, name, teacher_name FROM courses WHERE promotion_id = ? AND (name LIKE ? OR teacher_name LIKE ?) LIMIT 25');
            $stmt->execute([$promotion['id'], $like, $like]);
            $results['courses'] = $stmt->fetchAll();
        }
    }
}

$totalResults = count($results['students']) + count($results['courses']) + count($results['promotions']) + count($results['departments']);

$pageTitle = 'Recherche';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Recherche</h1>
<p class="text-slate-500 mb-6"><?= mb_strlen($q) >= 2 ? "$totalResults résultat(s) pour « " . h($q) . " »" : 'Saisissez au moins 2 caractères.' ?></p>

<form method="get" class="mb-6 max-w-lg">
    <input type="text" name="q" value="<?= h($q) ?>" autofocus placeholder="Étudiant, cours, promotion, département..."
           class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
</form>

<?php if (!empty($results['departments'])): ?>
    <h2 class="font-semibold text-slate-700 text-sm uppercase tracking-wide mb-2">Départements</h2>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 mb-6">
        <?php foreach ($results['departments'] as $d): ?>
            <a href="<?= BASE_URL ?>/admin/department_edit.php?id=<?= $d['id'] ?>" class="block px-4 py-3 hover:bg-slate-50 text-sm"><?= h($d['name']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($results['promotions'])): ?>
    <h2 class="font-semibold text-slate-700 text-sm uppercase tracking-wide mb-2">Promotions</h2>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 mb-6">
        <?php foreach ($results['promotions'] as $p): ?>
            <div class="px-4 py-3 text-sm"><?= h($p['name']) ?><?= !empty($p['group_name']) ? ' — ' . h($p['group_name']) : '' ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($results['courses'])): ?>
    <h2 class="font-semibold text-slate-700 text-sm uppercase tracking-wide mb-2">Cours</h2>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 mb-6">
        <?php foreach ($results['courses'] as $c): ?>
            <div class="px-4 py-3 text-sm flex items-center justify-between">
                <span><?= h($c['name']) ?> <span class="text-slate-400">— <?= h($c['teacher_name']) ?></span></span>
                <?php if ($user['role'] === ROLE_DELEGATE): ?>
                    <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium text-xs">Voir →</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($results['students'])): ?>
    <h2 class="font-semibold text-slate-700 text-sm uppercase tracking-wide mb-2">Étudiants</h2>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 mb-6">
        <?php foreach ($results['students'] as $s): ?>
            <div class="px-4 py-3 text-sm flex items-center justify-between">
                <span><?= h($s['full_name']) ?> <span class="text-slate-400">— <?= h($s['promotion_name'] ?? '') ?></span></span>
                <?php if ($user['role'] === ROLE_DELEGATE): ?>
                    <a href="<?= BASE_URL ?>/delegate/student_view.php?id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium text-xs">Voir →</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (mb_strlen($q) >= 2 && $totalResults === 0): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400 text-sm">
        Aucun résultat pour cette recherche.
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
