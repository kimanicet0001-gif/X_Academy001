<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$stats = getDepartmentStats();

$byProgress = $stats;
usort($byProgress, fn($a, $b) => $a['progress'] <=> $b['progress']);
$mostBehind = $byProgress[0] ?? null;
$mostAhead = end($byProgress) ?: null;

$byAttendance = $stats;
usort($byAttendance, fn($a, $b) => $a['attendance_rate'] <=> $b['attendance_rate']);
$worstAttendance = $byAttendance[0] ?? null;

$labels = array_column($stats, 'name');
$progressData = array_column($stats, 'progress');
$attendanceData = array_column($stats, 'attendance_rate');

$pageTitle = 'Statistiques de l\'université';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Statistiques de l'université</h1>
<p class="text-slate-500 mb-6">Comparaison de l'avancement des enseignements et de l'assiduité, département par département.</p>

<?php if (empty($stats)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center text-slate-400 text-sm">
        Aucune donnée disponible pour le moment.
    </div>
<?php else: ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><?= icon('sparkles', 'w-4 h-4') ?></span>
            <h2 class="font-semibold text-slate-800">Aperçu automatique</h2>
        </div>
        <div class="space-y-2">
            <?php foreach (generateInsights($stats, 'le département') as $insight):
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

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Département le plus en retard</p>
            <p class="text-lg font-display font-bold text-red-600"><?= h($mostBehind['name'] ?? '—') ?></p>
            <p class="text-sm text-slate-500"><?= $mostBehind['progress'] ?? 0 ?>% de progression</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Département le plus en avance</p>
            <p class="text-lg font-display font-bold text-green-600"><?= h($mostAhead['name'] ?? '—') ?></p>
            <p class="text-sm text-slate-500"><?= $mostAhead['progress'] ?? 0 ?>% de progression</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Plus fort taux d'absence</p>
            <p class="text-lg font-display font-bold text-amber-600"><?= h($worstAttendance['name'] ?? '—') ?></p>
            <p class="text-sm text-slate-500"><?= $worstAttendance['attendance_rate'] ?? 0 ?>% de présence</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Progression des enseignements par département</h2>
            <canvas id="progressChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Taux de présence par département</h2>
            <canvas id="attendanceChart" height="220"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                    <th class="py-3 px-4">Département</th>
                    <th class="py-3 px-4">Heures prévues</th>
                    <th class="py-3 px-4">Heures prestées</th>
                    <th class="py-3 px-4">Progression</th>
                    <th class="py-3 px-4">Taux de présence</th>
                    <th class="py-3 px-4">Score de santé</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $s):
                $health = calculateHealthScore($s['progress'], $s['attendance_rate']);
                $healthColor = $health >= 75 ? 'text-green-600' : ($health >= 50 ? 'text-amber-600' : 'text-red-600');
            ?>
                <tr class="border-t border-slate-100">
                    <td class="py-3 px-4 font-medium"><?= h($s['name']) ?></td>
                    <td class="py-3 px-4"><?= number_format($s['planned'], 1) ?> h</td>
                    <td class="py-3 px-4"><?= number_format($s['completed'], 1) ?> h</td>
                    <td class="py-3 px-4"><?= $s['progress'] ?>%</td>
                    <td class="py-3 px-4"><?= $s['attendance_rate'] ?>%</td>
                    <td class="py-3 px-4 font-display font-bold <?= $healthColor ?>"><?= $health ?>/100</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

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

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
