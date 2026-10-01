<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];

$stmt = getDB()->prepare(
    "SELECT c.*, p.name AS promotion_name, p.group_name,
            (SELECT COUNT(*) FROM sessions se WHERE se.course_id = c.id) AS session_count
     FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     WHERE p.department_id = ?
     ORDER BY p.name, c.name"
);
$stmt->execute([$deptId]);
$courses = $stmt->fetchAll();

// ---- Comparaison inter-promotions (diagrammes) ----
$promoStats = getPromotionStats($deptId);

$byProgress = $promoStats;
usort($byProgress, fn($a, $b) => $a['progress'] <=> $b['progress']);
$mostBehind = $byProgress[0] ?? null;

$byAttendance = $promoStats;
usort($byAttendance, fn($a, $b) => $a['attendance_rate'] <=> $b['attendance_rate']);
$worstAttendance = $byAttendance[0] ?? null;

$labels = array_column($promoStats, 'name');
$progressData = array_column($promoStats, 'progress');
$attendanceData = array_column($promoStats, 'attendance_rate');

// ---- Cours communs comparés entre promotions ----
$sharedCourses = getSharedCourseComparison($deptId);

$pageTitle = 'Statistiques & Registres PDF';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Statistiques &amp; Registres PDF</h1>
<p class="text-slate-500 mb-6">Comparaison des promotions de votre département.</p>

<?php if (!empty($promoStats)): ?>
    <?php
    $insights = generateInsights($promoStats, 'la promotion');
    $courseInsight = generateSharedCourseInsight($sharedCourses);
    if ($courseInsight) {
        $insights[] = $courseInsight;
    }
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><?= icon('sparkles', 'w-4 h-4') ?></span>
            <h2 class="font-semibold text-slate-800">Aperçu automatique</h2>
        </div>
        <div class="space-y-2">
            <?php foreach ($insights as $insight):
                $styles = match ($insight['type']) {
                    'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                    'success' => 'bg-green-50 text-green-700 border-green-200',
                    default   => 'bg-slate-50 text-slate-600 border-slate-200',
                };
            ?>
                <p class="text-sm border rounded-lg px-3 py-2 <?= $styles ?>"><?= h($insight['text']) ?></p>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Promotion la plus en retard</p>
            <p class="text-lg font-display font-bold text-red-600"><?= h($mostBehind['name'] ?? '—') ?></p>
            <p class="text-sm text-slate-500"><?= $mostBehind['progress'] ?? 0 ?>% de progression</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Plus fort taux d'absence</p>
            <p class="text-lg font-display font-bold text-amber-600"><?= h($worstAttendance['name'] ?? '—') ?></p>
            <p class="text-sm text-slate-500"><?= $worstAttendance['attendance_rate'] ?? 0 ?>% de présence</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Progression par promotion</h2>
            <canvas id="progressChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Taux de présence par promotion</h2>
            <canvas id="attendanceChart" height="220"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                        <th class="py-3 px-4">Promotion</th>
                        <th class="py-3 px-4">Heures prévues</th>
                        <th class="py-3 px-4">Heures prestées</th>
                        <th class="py-3 px-4">Progression</th>
                        <th class="py-3 px-4">Taux de présence</th>
                        <th class="py-3 px-4">Score de santé</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($promoStats as $p):
                    $health = calculateHealthScore($p['progress'], $p['attendance_rate']);
                    $healthColor = $health >= 75 ? 'text-green-600' : ($health >= 50 ? 'text-amber-600' : 'text-red-600');
                ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-3 px-4 font-medium"><?= h($p['name']) ?></td>
                        <td class="py-3 px-4"><?= number_format($p['planned'], 1) ?> h</td>
                        <td class="py-3 px-4"><?= number_format($p['completed'], 1) ?> h</td>
                        <td class="py-3 px-4"><?= $p['progress'] ?>%</td>
                        <td class="py-3 px-4"><?= $p['attendance_rate'] ?>%</td>
                        <td class="py-3 px-4 font-display font-bold <?= $healthColor ?>"><?= $health ?>/100</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($sharedCourses)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <h2 class="font-semibold text-slate-800 mb-4">Cours communs — comparaison entre promotions</h2>
            <div class="space-y-4">
                <?php foreach ($sharedCourses as $courseName => $rows): ?>
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2"><?= h($courseName) ?></p>
                        <div class="space-y-1.5">
                            <?php foreach ($rows as $r): ?>
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-32 shrink-0 text-slate-500 truncate"><?= h($r['promotion']) ?></span>
                                    <div class="flex-1 bg-slate-200 rounded-full h-2">
                                        <div class="<?= $r['progress'] < 50 ? 'bg-red-500' : 'bg-indigo-600' ?> h-2 rounded-full transition-all" style="width: <?= $r['progress'] ?>%"></div>
                                    </div>
                                    <span class="w-10 text-right text-slate-500"><?= $r['progress'] ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
        const labels = <?= json_encode($labels) ?>;
        const progressData = <?= json_encode($progressData) ?>;
        const attendanceData = <?= json_encode($attendanceData) ?>;

        Chart.register(ChartDataLabels);
        Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        Chart.defaults.color = '#64748b';

        function makeBarChart(canvasId, data, colorFrom, colorTo) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, colorFrom);
            gradient.addColorStop(1, colorTo);

            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: gradient,
                        hoverBackgroundColor: colorFrom,
                        borderRadius: { topLeft: 8, topRight: 8 },
                        borderSkipped: false,
                        maxBarThickness: 44,
                    }],
                },
                options: {
                    responsive: true,
                    animation: { duration: 800, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { family: "'Plus Jakarta Sans', sans-serif", weight: '700', size: 13 },
                            bodyFont: { family: "'Inter', sans-serif", size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: { label: (c) => c.parsed.y + '%' },
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: (v) => v + '%',
                            font: { family: "'Inter', sans-serif", weight: '600', size: 11 },
                            color: '#334155',
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true, max: 100,
                            grid: { color: '#e2e8f0', drawTicks: false },
                            border: { display: false },
                            ticks: { callback: (v) => v + '%', font: { size: 11 }, padding: 8 },
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { size: 11, weight: '500' } },
                        },
                    },
                    layout: { padding: { top: 16 } },
                },
            });
        }

        makeBarChart('progressChart', progressData, '#818cf8', '#4f46e5');
        makeBarChart('attendanceChart', attendanceData, '#6ee7b7', '#10b981');
    </script>
<?php endif; ?>

<h2 class="font-semibold text-slate-800 mb-3">Registres PDF par cours</h2>
<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#reportsTable" placeholder="Rechercher un cours..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm" id="reportsTable">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Séances réalisées</th>
                <th class="py-3 px-4">Heures</th>
                <th class="py-3 px-4">Progression</th>
                <th class="py-3 px-4 text-right">Registre PDF</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $c):
            $completed = getCompletedHours((int) $c['id']);
            $progress  = calculateProgress((float) $c['planned_hours'], $completed);
        ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($c['name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($c['promotion_name']) ?><?= $c['group_name'] ? ' — ' . h($c['group_name']) : '' ?></td>
                <td class="py-3 px-4"><?= (int) $c['session_count'] ?></td>
                <td class="py-3 px-4"><?= number_format($completed, 2) ?> h / <?= number_format((float) $c['planned_hours'], 2) ?> h</td>
                <td class="py-3 px-4"><?= $progress ?>%</td>
                <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                    <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $c['id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('eye', 'w-4 h-4') ?> Voir</a>
                    <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('download', 'w-4 h-4') ?> Télécharger</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
            <tr><td colspan="6" class="py-10 text-center text-slate-400 text-sm">Aucun cours enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
