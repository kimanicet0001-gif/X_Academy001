<?php
/**
 * includes/functions.php
 * Fonctions utilitaires générales.
 */

/**
 * Échappe une chaîne pour un affichage HTML sûr.
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige vers une URL relative à BASE_URL et arrête l'exécution.
 */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Formate une date SQL (Y-m-d) en format français (d/m/Y).
 */
function formatDateFr(?string $sqlDate): string
{
    if (empty($sqlDate)) {
        return '-';
    }
    $ts = strtotime($sqlDate);
    return $ts ? date('d/m/Y', $ts) : $sqlDate;
}

/**
 * Formate une heure SQL (H:i:s) en H:i.
 */
function formatTime(?string $sqlTime): string
{
    if (empty($sqlTime)) {
        return '-';
    }
    return substr($sqlTime, 0, 5);
}

/**
 * Calcule les heures restantes d'un cours.
 *
 * @param float $plannedHours Volume horaire prévu
 * @param float $completedHours Heures déjà prestées
 */
function calculateRemainingHours(float $plannedHours, float $completedHours): float
{
    $remaining = $plannedHours - $completedHours;
    return $remaining > 0 ? round($remaining, 2) : 0.0;
}

/**
 * Calcule le pourcentage de progression d'un cours.
 */
function calculateProgress(float $plannedHours, float $completedHours): float
{
    if ($plannedHours <= 0) {
        return 0.0;
    }
    $progress = ($completedHours / $plannedHours) * 100;
    return round(min($progress, 100), 2);
}

/**
 * Calcule le taux de présence (en %) à partir du nombre de présents et du total.
 */
function calculateAttendanceRate(int $present, int $total): float
{
    if ($total <= 0) {
        return 0.0;
    }
    return round(($present / $total) * 100, 2);
}

/**
 * Additionne les heures prestées de toutes les séances "Terminées"
 * (statut 'done') d'un cours donné. Les séances en brouillon, publiées
 * mais pas encore données, ou annulées ne comptent jamais dans les
 * heures prestées.
 */
function getCompletedHours(int $courseId): float
{
    $stmt = getDB()->prepare(
        "SELECT COALESCE(SUM(duration_hours), 0) AS total FROM sessions
         WHERE course_id = ? AND status = 'done'"
    );
    $stmt->execute([$courseId]);
    return (float) $stmt->fetchColumn();
}

/**
 * Calcule la durée d'une séance en HEURES ACADÉMIQUES (pas en heures
 * d'horloge) à partir de deux horaires HH:MM. Dans cette université,
 * une "heure" académique dure MINUTES_PER_ACADEMIC_HOUR minutes réelles
 * (50 min par défaut) — le volume horaire des cours est exprimé dans
 * cette même unité, donc TOUT calcul de durée doit passer par ici.
 */
function computeDurationHours(string $start, string $end): float
{
    $startTs = strtotime($start);
    $endTs   = strtotime($end);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        return 0.0;
    }
    $realMinutes = ($endTs - $startTs) / 60;
    return round($realMinutes / MINUTES_PER_ACADEMIC_HOUR, 2);
}

/**
 * Génère un nom de fichier sûr pour le registre PDF d'un cours.
 */
function registerFileName(string $courseName, string $promotionName): string
{
    $slug = strtoupper(trim($courseName . '_' . $promotionName));
    $slug = preg_replace('/[^A-Z0-9]+/u', '_', $slug);
    $slug = trim($slug, '_');
    return 'REGISTRE_' . $slug . '.pdf';
}

/**
 * Retourne la promotion active assignée à un Délégué, ou null.
 */
function getDelegatePromotion(int $delegateId): ?array
{
    $stmt = getDB()->prepare(
        "SELECT * FROM promotions WHERE delegate_id = ? AND status = 'active'
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$delegateId]);
    $promotion = $stmt->fetch();
    return $promotion ?: null;
}

/**
 * Retourne la configuration de l'université (nom, boîte postale, ville, email).
 * Toujours un tableau valide, même si la table est vide (valeurs par défaut).
 */
function getUniversitySettings(): array
{
    static $settings = null;
    if ($settings === null) {
        $row = getDB()->query('SELECT * FROM settings WHERE id = 1')->fetch();
        $settings = $row ?: [
            'university_name' => 'Université',
            'po_box' => null,
            'city' => null,
            'email' => null,
        ];
    }
    return $settings;
}

/**
 * Statistiques agrégées pour un département : heures prévues/prestées,
 * progression globale et taux de présence global — utilisées pour les
 * diagrammes de comparaison inter-départements (vue Admin).
 */
function getDepartmentStats(): array
{
    $db = getDB();
    $departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
    $stats = [];

    foreach ($departments as $d) {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(c.planned_hours), 0) FROM courses c
             JOIN promotions p ON p.id = c.promotion_id WHERE p.department_id = ?"
        );
        $stmt->execute([$d['id']]);
        $planned = (float) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(se.duration_hours), 0) FROM sessions se
             JOIN courses c ON c.id = se.course_id
             JOIN promotions p ON p.id = c.promotion_id WHERE p.department_id = ? AND se.status = 'done'"
        );
        $stmt->execute([$d['id']]);
        $completed = (float) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance a
             JOIN sessions se ON se.id = a.session_id
             JOIN courses c ON c.id = se.course_id
             JOIN promotions p ON p.id = c.promotion_id
             WHERE p.department_id = ? AND se.status = 'done' AND a.status = 'present'"
        );
        $stmt->execute([$d['id']]);
        $present = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance a
             JOIN sessions se ON se.id = a.session_id
             JOIN courses c ON c.id = se.course_id
             JOIN promotions p ON p.id = c.promotion_id
             WHERE p.department_id = ? AND se.status = 'done'"
        );
        $stmt->execute([$d['id']]);
        $total = (int) $stmt->fetchColumn();

        $stats[] = [
            'id'              => $d['id'],
            'name'            => $d['name'],
            'planned'         => $planned,
            'completed'       => $completed,
            'progress'        => calculateProgress($planned, $completed),
            'present'         => $present,
            'total'           => $total,
            'attendance_rate' => calculateAttendanceRate($present, $total),
        ];
    }

    return $stats;
}

/**
 * Statistiques agrégées pour chaque promotion d'un département : heures,
 * progression et taux de présence — utilisées pour les diagrammes de
 * comparaison inter-promotions (vue Chef de Département).
 */
function getPromotionStats(int $departmentId): array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM promotions WHERE department_id = ? ORDER BY name");
    $stmt->execute([$departmentId]);
    $promotions = $stmt->fetchAll();
    $stats = [];

    foreach ($promotions as $p) {
        $stmt = $db->prepare('SELECT COALESCE(SUM(planned_hours), 0) FROM courses WHERE promotion_id = ?');
        $stmt->execute([$p['id']]);
        $planned = (float) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(se.duration_hours), 0) FROM sessions se
             JOIN courses c ON c.id = se.course_id WHERE c.promotion_id = ? AND se.status = 'done'"
        );
        $stmt->execute([$p['id']]);
        $completed = (float) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance a
             JOIN sessions se ON se.id = a.session_id
             JOIN courses c ON c.id = se.course_id
             WHERE c.promotion_id = ? AND se.status = 'done' AND a.status = 'present'"
        );
        $stmt->execute([$p['id']]);
        $present = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance a
             JOIN sessions se ON se.id = a.session_id
             JOIN courses c ON c.id = se.course_id
             WHERE c.promotion_id = ? AND se.status = 'done'"
        );
        $stmt->execute([$p['id']]);
        $total = (int) $stmt->fetchColumn();

        $stats[] = [
            'id'              => $p['id'],
            'name'            => $p['name'] . ($p['group_name'] ? ' — ' . $p['group_name'] : ''),
            'planned'         => $planned,
            'completed'       => $completed,
            'progress'        => calculateProgress($planned, $completed),
            'present'         => $present,
            'total'           => $total,
            'attendance_rate' => calculateAttendanceRate($present, $total),
        ];
    }

    return $stats;
}

/**
 * Compare, pour un même intitulé de cours donné à plusieurs promotions
 * d'un département, la progression de chaque promotion sur ce cours.
 * Ne retourne que les intitulés partagés par au moins 2 promotions —
 * permet au Chef de voir sur quel cours un groupe précis prend du retard
 * par rapport aux autres groupes qui suivent le même cours.
 */
function getSharedCourseComparison(int $departmentId): array
{
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT c.id AS course_id, c.name AS course_name, c.planned_hours,
                p.name AS promotion_name, p.group_name
         FROM courses c
         JOIN promotions p ON p.id = c.promotion_id
         WHERE p.department_id = ?
         ORDER BY c.name"
    );
    $stmt->execute([$departmentId]);

    $byName = [];
    foreach ($stmt->fetchAll() as $row) {
        $completed = getCompletedHours((int) $row['course_id']);
        $byName[$row['course_name']][] = [
            'promotion' => $row['promotion_name'] . ($row['group_name'] ? ' — ' . $row['group_name'] : ''),
            'progress'  => calculateProgress((float) $row['planned_hours'], $completed),
        ];
    }

    return array_filter($byName, fn($group) => count($group) >= 2);
}

/**
 * Génère des observations automatiques ("aperçu intelligent") à partir de
 * statistiques déjà calculées (getDepartmentStats() ou getPromotionStats()).
 * Purement local — aucun appel externe, aucun coût.
 *
 * @param array $stats Résultat de getDepartmentStats() ou getPromotionStats()
 * @param string $label 'le département' ou 'la promotion' (accord dans le texte)
 * @return array Liste de ['type' => 'warning'|'success'|'info', 'text' => string]
 */
function generateInsights(array $stats, string $label = 'le département'): array
{
    if (count($stats) < 2) {
        return [];
    }

    $insights = [];
    $avgProgress = array_sum(array_column($stats, 'progress')) / count($stats);
    $avgAttendance = array_sum(array_column($stats, 'attendance_rate')) / count($stats);

    $byProgress = $stats;
    usort($byProgress, fn($a, $b) => $a['progress'] <=> $b['progress']);
    $behind = $byProgress[0];
    $ahead = end($byProgress);

    if ($behind['progress'] < $avgProgress - 10) {
        $insights[] = [
            'type' => 'warning',
            'text' => sprintf(
                '%s « %s » accuse un retard notable : %s%% de progression, contre %s%% en moyenne.',
                ucfirst($label), $behind['name'], $behind['progress'], round($avgProgress, 1)
            ),
        ];
    }

    if ($ahead['name'] !== $behind['name'] && $ahead['progress'] > $avgProgress + 10) {
        $insights[] = [
            'type' => 'success',
            'text' => sprintf(
                '%s « %s » est en avance avec %s%% de progression.',
                ucfirst($label), $ahead['name'], $ahead['progress']
            ),
        ];
    }

    $byAttendance = $stats;
    usort($byAttendance, fn($a, $b) => $a['attendance_rate'] <=> $b['attendance_rate']);
    $worstAttendance = $byAttendance[0];

    if ($worstAttendance['total'] > 0 && $worstAttendance['attendance_rate'] < $avgAttendance - 10) {
        $insights[] = [
            'type' => 'warning',
            'text' => sprintf(
                '%s « %s » a le taux de présence le plus faible : %s%%, contre %s%% en moyenne.',
                ucfirst($label), $worstAttendance['name'], $worstAttendance['attendance_rate'], round($avgAttendance, 1)
            ),
        ];
    }

    if (empty($insights)) {
        $insights[] = [
            'type' => 'info',
            'text' => "Aucun écart significatif détecté : la progression et l'assiduité sont globalement homogènes.",
        ];
    }

    return $insights;
}

/**
 * Repère, parmi des cours communs à plusieurs promotions (voir
 * getSharedCourseComparison()), l'écart de progression le plus marqué
 * entre deux promotions sur un même cours.
 */
function generateSharedCourseInsight(array $sharedCourses): ?array
{
    $worst = null;
    $worstGap = 0;

    foreach ($sharedCourses as $courseName => $rows) {
        $progresses = array_column($rows, 'progress');
        $min = min($progresses);
        $max = max($progresses);
        $gap = $max - $min;

        if ($gap > $worstGap) {
            $worstGap = $gap;
            $minRow = null;
            foreach ($rows as $r) {
                if ($r['progress'] == $min) {
                    $minRow = $r;
                    break;
                }
            }
            $worst = ['course' => $courseName, 'promotion' => $minRow['promotion'] ?? '', 'progress' => $min, 'gap' => $gap];
        }
    }

    if ($worst && $worst['gap'] >= 15) {
        return [
            'type' => 'warning',
            'text' => sprintf(
                'Sur le cours « %s », la promotion « %s » est nettement en retard par rapport aux autres groupes qui suivent le même cours (%s%%, écart de %s points).',
                $worst['course'], $worst['promotion'], $worst['progress'], $worst['gap']
            ),
        ];
    }

    return null;
}

/**
 * Enregistre une action sensible dans le journal d'audit.
 * Ne bloque jamais l'exécution même si l'écriture échoue.
 */
function logAudit(string $action, string $details = ''): void
{
    try {
        $user = currentUser();
        $stmt = getDB()->prepare(
            'INSERT INTO audit_logs (user_id, username, role, action, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'] ?? null,
            $user['username'] ?? null,
            $user['role'] ?? null,
            $action,
            $details ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Le journal d'audit ne doit jamais empêcher une action métier.
    }
}

/**
 * Statistiques de présence d'un étudiant : taux global, détail par cours,
 * et liste des absences — base du profil étudiant et de la détection de risque.
 */
function getStudentAttendanceStats(int $studentId): array
{
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT c.id AS course_id, c.name AS course_name, a.status, s.session_date, s.topic
         FROM attendance a
         JOIN sessions s ON s.id = a.session_id
         JOIN courses c ON c.id = s.course_id
         WHERE a.student_id = ?
         ORDER BY s.session_date, s.start_time"
    );
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll();

    $total = count($rows);
    $present = 0;
    $byCourse = [];
    $absences = [];

    foreach ($rows as $r) {
        if ($r['status'] === 'present') {
            $present++;
        } else {
            $absences[] = ['date' => $r['session_date'], 'course' => $r['course_name'], 'topic' => $r['topic']];
        }
        if (!isset($byCourse[$r['course_id']])) {
            $byCourse[$r['course_id']] = ['name' => $r['course_name'], 'present' => 0, 'total' => 0];
        }
        $byCourse[$r['course_id']]['total']++;
        if ($r['status'] === 'present') {
            $byCourse[$r['course_id']]['present']++;
        }
    }

    foreach ($byCourse as &$c) {
        $c['rate'] = calculateAttendanceRate($c['present'], $c['total']);
    }
    unset($c);

    return [
        'total_sessions' => $total,
        'present' => $present,
        'rate' => calculateAttendanceRate($present, $total),
        'by_course' => array_values($byCourse),
        'absences' => array_reverse($absences),
    ];
}

/**
 * Classe un niveau d'assiduité à partir d'un taux de présence (%).
 * Reste une aide à la décision, jamais une sanction automatique.
 */
function classifyRisk(float $rate, int $totalSessions): array
{
    if ($totalSessions === 0) {
        return ['label' => 'Pas encore de données', 'color' => 'bg-slate-100 text-slate-600 border-slate-200'];
    }
    if ($rate >= 85) {
        return ['label' => 'Normal', 'color' => 'bg-green-50 text-green-700 border-green-200'];
    }
    if ($rate >= 65) {
        return ['label' => 'À surveiller', 'color' => 'bg-amber-50 text-amber-800 border-amber-200'];
    }
    return ['label' => 'À risque', 'color' => 'bg-red-50 text-red-700 border-red-200'];
}

/**
 * Attribue des badges d'assiduité (gamification légère, sobre).
 */
function getAttendanceBadges(array $attendanceStats): array
{
    $badges = [];
    if ($attendanceStats['total_sessions'] === 0) {
        return $badges;
    }
    if ($attendanceStats['rate'] == 100) {
        $badges[] = ['label' => 'Présence parfaite', 'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200'];
    } elseif ($attendanceStats['rate'] >= 90) {
        $badges[] = ['label' => 'Assidu', 'color' => 'bg-green-50 text-green-700 border-green-200'];
    }
    if ($attendanceStats['total_sessions'] >= 5) {
        $recentAbsences = 0;
        $recent = array_slice($attendanceStats['absences'], 0, 5);
        $recentAbsences = count($recent);
        if ($recentAbsences === 0) {
            $badges[] = ['label' => 'Régulier', 'color' => 'bg-blue-50 text-blue-700 border-blue-200'];
        }
    }
    return $badges;
}

/**
 * Détecte les cours dont l'intitulé se ressemble fortement (variantes
 * d'orthographe) au sein d'une même promotion — problème de qualité des
 * données évitant de fausser les statistiques par cours.
 *
 * @param int|null $departmentId Limite la recherche à un département (null = toute l'université)
 */
function detectCourseNameVariants(?int $departmentId = null): array
{
    $db = getDB();
    $sql = "SELECT c.id, c.name, c.promotion_id, p.name AS promotion_name, p.group_name, d.name AS department_name
            FROM courses c
            JOIN promotions p ON p.id = c.promotion_id
            JOIN departments d ON d.id = p.department_id";
    $params = [];
    if ($departmentId !== null) {
        $sql .= " WHERE p.department_id = ?";
        $params[] = $departmentId;
    }
    $sql .= " ORDER BY c.promotion_id, c.name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    $pairs = [];
    $byPromotion = [];
    foreach ($courses as $c) {
        $byPromotion[$c['promotion_id']][] = $c;
    }

    foreach ($byPromotion as $group) {
        $count = count($group);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $group[$i];
                $b = $group[$j];
                if ($a['name'] === $b['name']) {
                    continue; // identiques : pas une variante, un vrai doublon exact bloqué par la contrainte d'unicité
                }
                similar_text(mb_strtolower($a['name']), mb_strtolower($b['name']), $percent);
                if ($percent >= 80) {
                    $pairs[] = ['a' => $a, 'b' => $b, 'similarity' => round($percent, 1)];
                }
            }
        }
    }

    usort($pairs, fn($x, $y) => $y['similarity'] <=> $x['similarity']);
    return $pairs;
}

/**
 * Estime la date probable d'achèvement d'un cours à partir du rythme
 * historique des séances déjà réalisées.
 */
function estimateCompletionDate(float $plannedHours, float $completedHours, array $sessions): ?string
{
    $remaining = calculateRemainingHours($plannedHours, $completedHours);
    if ($remaining <= 0 || count($sessions) < 2) {
        return null;
    }

    $first = strtotime($sessions[0]['session_date']);
    $last = strtotime(end($sessions)['session_date']);
    $daySpan = max(1, ($last - $first) / 86400);
    $avgHoursPerDay = $completedHours / $daySpan;

    if ($avgHoursPerDay <= 0) {
        return null;
    }

    $daysNeeded = ceil($remaining / $avgHoursPerDay);
    return date('Y-m-d', strtotime('+' . $daysNeeded . ' days', $last));
}

/**
 * Calcule un score de santé académique (0-100) combinant progression et
 * assiduité pour un département ou une promotion.
 */
function calculateHealthScore(float $progress, float $attendanceRate): int
{
    $score = ($progress * 0.5) + ($attendanceRate * 0.5);
    return (int) round(max(0, min(100, $score)));
}

/**
 * Génère des alertes concrètes et actionnables à partir d'une liste de
 * cours (chaque élément doit contenir : id, name, planned_hours,
 * promotion_name éventuel). Complète les "insights" narratifs par des
 * signaux exploitables un par un.
 */
function getCourseAlerts(array $courses): array
{
    $alerts = [];
    $db = getDB();

    foreach ($courses as $c) {
        $completed = getCompletedHours((int) $c['id']);
        $progress = calculateProgress((float) $c['planned_hours'], $completed);
        $remaining = calculateRemainingHours((float) $c['planned_hours'], $completed);

        $stmt = $db->prepare('SELECT MAX(session_date) FROM sessions WHERE course_id = ?');
        $stmt->execute([$c['id']]);
        $lastSession = $stmt->fetchColumn();

        $daysSinceLast = $lastSession ? (int) floor((time() - strtotime($lastSession)) / 86400) : null;
        $label = $c['name'] . (isset($c['promotion_name']) ? ' (' . $c['promotion_name'] . ')' : '');

        if ($remaining > 0 && $daysSinceLast !== null && $daysSinceLast >= 14) {
            $alerts[] = [
                'type' => 'warning',
                'text' => sprintf('« %s » n\'a plus eu de séance depuis %d jours, alors qu\'il reste %.1f h à prester.', $label, $daysSinceLast, $remaining),
            ];
        }
        if ($remaining > 0 && $remaining <= ((float) $c['planned_hours'] * 0.1)) {
            $alerts[] = [
                'type' => 'info',
                'text' => sprintf('« %s » approche de la fin de son volume horaire : plus que %.1f h restantes.', $label, $remaining),
            ];
        }
        if ($progress >= 100 && $remaining <= 0) {
            // Cours terminé : pas une alerte, simplement ignoré ici.
            continue;
        }
    }

    return $alerts;
}

/**
 * Exporte un tableau de données au format CSV et termine la requête.
 * Compatible Excel (BOM UTF-8 + séparateur point-virgule).
 */
function exportCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM pour un affichage correct des accents dans Excel
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

/**
 * Génère un mot de passe temporaire aléatoire (pour réinitialisation).
 */
function generateTempPassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Bibliothèque d'icônes SVG minimalistes (traits fins, sans dépendance).
 * Utilisée partout à la place des emojis pour une interface sobre et
 * professionnelle. Retourne du SVG brut à insérer directement en HTML.
 *
 * @param string $name Nom de l'icône (voir le match ci-dessous).
 * @param string $class Classes Tailwind pour la taille/couleur (ex: "w-5 h-5").
 */
function icon(string $name, string $class = 'w-5 h-5'): string
{
    $attrs = 'class="' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"';

    $paths = match ($name) {
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'building' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/><path d="M10 21v-4h4v4"/>',
        'briefcase' => '<rect x="3" y="7.5" width="18" height="12" rx="1.5"/><path d="M8 7.5V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1.5"/><path d="M3 12.5h18"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 8.2a3 3 0 1 1 3.6 4.4"/><path d="M15.5 14.3A5.5 5.5 0 0 1 20.5 20"/>',
        'academic-cap' => '<path d="M12 4 2.5 8.5 12 13l9.5-4.5L12 4Z"/><path d="M6.5 10.7V16c0 1.1 2.5 2.5 5.5 2.5s5.5-1.4 5.5-2.5v-5.3"/><path d="M21.5 9v6"/>',
        'book' => '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H18a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6.5A1.5 1.5 0 0 1 5 18.5v-14Z"/><path d="M5 18.5A1.5 1.5 0 0 1 6.5 17H19"/>',
        'book-open' => '<path d="M12 6.5c-1.8-1.4-4.3-2-7-2v12.5c2.7 0 5.2.6 7 2 1.8-1.4 4.3-2 7-2V4.5c-2.7 0-5.2.6-7 2Z"/><path d="M12 6.5v12.5"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path d="M3.5 9.5h17"/><path d="M8 3v3.5M16 3v3.5"/>',
        'document' => '<path d="M7 3.5h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-16a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4"/><path d="M9 13h6M9 16.5h6"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V19.5a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H4.5a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.56-1.04 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H10.6a1.7 1.7 0 0 0 1.04-1.56V4.5a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.09c.28.7.86 1.25 1.56 1.04H19.5a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.6 1.04Z" stroke-width="1.3"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'eye' => '<path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>',
        'download' => '<path d="M12 3.5v11.5"/><path d="M7.5 11.5 12 16l4.5-4.5"/><path d="M4.5 17.5v2a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-2"/>',
        'menu' => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
        'shield' => '<path d="M12 3.5 4.5 6.5v5c0 5 3.2 8.3 7.5 9.9 4.3-1.6 7.5-4.9 7.5-9.9v-5L12 3.5Z"/><path d="M9 12.2l2 2 4-4.2"/>',
        'shield-alert' => '<path d="M12 3.5 4.5 6.5v5c0 5 3.2 8.3 7.5 9.9 4.3-1.6 7.5-4.9 7.5-9.9v-5L12 3.5Z"/><path d="M12 9v4"/><circle cx="12" cy="16" r="0.6" fill="currentColor" stroke="none"/>',
        'logout' => '<path d="M9 21H5.5A1.5 1.5 0 0 1 4 19.5v-15A1.5 1.5 0 0 1 5.5 3H9"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'key' => '<circle cx="8" cy="15.5" r="3.5"/><path d="M10.5 13 18 5.5"/><path d="M15.5 8 18 5.5M17.5 10.5 20 8"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.3 11 14.8l4.8-5.6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>',
        'printer' => '<path d="M7 8.5V4h10v4.5"/><rect x="4.5" y="8.5" width="15" height="8" rx="1.2"/><path d="M7 15.5h10V20H7v-4.5Z"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'chat' => '<path d="M4 5.5h16a1 1 0 0 1 1 1V15a1 1 0 0 1-1 1H9.5l-4.2 3.8a.5.5 0 0 1-.8-.37V16H4a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'send' => '<path d="M21 3 3 10.5l7 2.5 2.5 7L21 3Z"/><path d="M12.5 13 21 3"/>',
        'sparkles' => '<path d="M12 3v3.5M12 17.5V21M3 12h3.5M17.5 12H21M5.5 5.5l2.5 2.5M16 16l2.5 2.5M18.5 5.5 16 8M8 16l-2.5 2.5"/><circle cx="12" cy="12" r="2.2"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.8-4.8"/>',
        'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9Z"/><path d="M10 18.5a2 2 0 0 0 4 0"/>',
        'map-pin' => '<path d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.3"/>',
        'edit' => '<path d="M4 20.5h4.2L19 9.7a2 2 0 0 0 0-2.8l-1.9-1.9a2 2 0 0 0-2.8 0L3.5 15.8v4.7Z"/><path d="M13.5 6l3.5 3.5"/>',
        'ban' => '<circle cx="12" cy="12" r="8.5"/><path d="M6.5 6.5l11 11"/>',
        default => '<circle cx="12" cy="12" r="8.5"/>',
    };

    return "<svg $attrs>$paths</svg>";
}

/**
 * Crée une notification pour chaque étudiant actif d'une promotion.
 * Utilisée quand une séance est publiée, modifiée après publication, ou annulée.
 */
function notifyPromotion(int $promotionId, string $type, string $message, ?string $link = null, ?int $sessionId = null): void
{
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM students WHERE promotion_id = ? AND status = 'active'");
    $stmt->execute([$promotionId]);
    $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($studentIds)) {
        return;
    }

    $ins = $db->prepare(
        'INSERT INTO notifications (student_id, session_id, type, message, link) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($studentIds as $studentId) {
        $ins->execute([$studentId, $sessionId, $type, $message, $link]);
    }
}

/**
 * Nombre de notifications non lues pour un étudiant OU un professeur
 * (badge dans la navbar). $recipientType vaut 'student' (défaut) ou 'professor'.
 */
function getUnreadNotificationCount(int $recipientId, string $recipientType = 'student'): int
{
    $column = $recipientType === 'professor' ? 'user_id' : 'student_id';
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE $column = ? AND is_read = 0");
    $stmt->execute([$recipientId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Crée une notification adressée à un utilisateur (professeur, chef...),
 * par opposition à notifyPromotion() qui vise les étudiants.
 */
function notifyUser(int $userId, string $type, string $message, ?string $link = null): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $message, $link]);
}

/**
 * Libellé et style d'un statut de séance.
 */
function sessionStatusInfo(string $status): array
{
    return match ($status) {
        'draft'     => ['label' => 'Brouillon', 'color' => 'bg-slate-100 text-slate-600 border-slate-200'],
        'published' => ['label' => 'Publiée', 'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'done'      => ['label' => 'Terminée', 'color' => 'bg-green-50 text-green-700 border-green-200'],
        'cancelled' => ['label' => 'Annulée', 'color' => 'bg-red-50 text-red-700 border-red-200'],
        default     => ['label' => $status, 'color' => 'bg-slate-100 text-slate-600 border-slate-200'],
    };
}

/**
 * Vérifie qu'un matricule n'est pas déjà utilisé par un autre étudiant.
 */
function isMatriculeTaken(string $matricule, ?int $excludeStudentId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM students WHERE matricule = ?';
    $params = [$matricule];
    if ($excludeStudentId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeStudentId;
    }
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie qu'un identifiant de connexion (username) n'est pas déjà pris,
 * tous comptes confondus (staff ET étudiants, une seule table de connexion
 * logique même si physiquement séparée).
 */
function isUsernameTaken(string $username, ?string $excludeTable = null, ?int $excludeId = null): bool
{
    $db = getDB();

    $sql1 = 'SELECT COUNT(*) FROM users WHERE username = ?';
    $params1 = [$username];
    if ($excludeTable === 'users' && $excludeId !== null) {
        $sql1 .= ' AND id != ?';
        $params1[] = $excludeId;
    }
    $stmt = $db->prepare($sql1);
    $stmt->execute($params1);
    if ($stmt->fetchColumn() > 0) {
        return true;
    }

    $sql2 = 'SELECT COUNT(*) FROM students WHERE username = ?';
    $params2 = [$username];
    if ($excludeTable === 'students' && $excludeId !== null) {
        $sql2 .= ' AND id != ?';
        $params2[] = $excludeId;
    }
    $stmt = $db->prepare($sql2);
    $stmt->execute($params2);
    return $stmt->fetchColumn() > 0;
}

/**
 * Retourne la liste des filières actives d'un département.
 */
function getFilieres(int $departmentId, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM filieres WHERE department_id = ?';
    if ($activeOnly) {
        $sql .= " AND status = 'active'";
    }
    $sql .= ' ORDER BY name';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([$departmentId]);
    return $stmt->fetchAll();
}

// =====================================================================
// MODULE PROFESSEUR & NOTES
// Chaîne de calcul : NOTE -> GPA -> POINTS PONDÉRÉS -> UE -> SEMESTRE
// -> ANNÉE -> CYCLE. Aucune de ces valeurs n'est jamais saisie à la main :
// seule la note (score) l'est, tout le reste est dérivé ici.
// Les résultats officiels (UE, semestre, année, cycle) ne comptent que les
// notes soumises ou verrouillées : jamais les brouillons.
// =====================================================================

/**
 * GPA à partir d'une note sur 100. Formule de référence : note / 25,
 * plafonné à 4.00 (une note de 100 donne exactement 4.00).
 */
function calculateGPA(float $score): float
{
    return round(min($score / 25, 4.00), 2);
}

/**
 * Points pondérés = GPA x crédits du cours.
 */
function calculateWeightedPoints(float $score, float $credits): float
{
    return round(calculateGPA($score) * $credits, 2);
}

/**
 * Lettre correspondant à une note, selon le barème actif (table
 * grading_scales, configurable depuis l'admin). Retourne 'N/A' si aucune
 * tranche ne correspond (barème incomplet) plutôt que de deviner.
 */
function calculateGrade(float $score): string
{
    $stmt = getDB()->prepare(
        "SELECT grade FROM grading_scales
         WHERE active = 1 AND ? BETWEEN min_score AND max_score
         ORDER BY min_score DESC LIMIT 1"
    );
    $stmt->execute([$score]);
    $grade = $stmt->fetchColumn();
    return $grade !== false ? $grade : 'N/A';
}

/**
 * Seuil de validation d'une UE (note moyenne à partir de laquelle une UE
 * est considérée Validée). Configurable dans admin/settings.php.
 */
function getUeValidationThreshold(): float
{
    $settings = getUniversitySettings();
    return isset($settings['ue_validation_threshold']) ? (float) $settings['ue_validation_threshold'] : 60.0;
}

/**
 * Recalcule et enregistre les champs dérivés (gpa, weighted_points, grade)
 * d'une note à partir de son score. À appeler à chaque création/modification
 * d'un student_grades.score, en DRAFT comme en correction post-verrouillage.
 */
function refreshGradeDerivedFields(int $gradeId, float $score): void
{
    $stmt = getDB()->prepare('SELECT c.credits FROM student_grades sg JOIN courses c ON c.id = sg.course_id WHERE sg.id = ?');
    $stmt->execute([$gradeId]);
    $credits = (float) $stmt->fetchColumn();

    $upd = getDB()->prepare('UPDATE student_grades SET gpa = ?, weighted_points = ?, grade = ? WHERE id = ?');
    $upd->execute([
        calculateGPA($score),
        calculateWeightedPoints($score, $credits),
        calculateGrade($score),
        $gradeId,
    ]);
}

/**
 * Vérifie qu'un professeur est bien affecté (activement) à un cours donné.
 */
function isProfessorAssignedToCourse(int $professorId, int $courseId): bool
{
    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM teaching_assignments WHERE professor_id = ? AND course_id = ? AND status = 'active'"
    );
    $stmt->execute([$professorId, $courseId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Liste des enseignements actifs affectés à un professeur, avec le
 * contexte complet (cours, promotion, filière, département) déduit par
 * jointure — jamais stocké en double dans teaching_assignments.
 */
function getProfessorAssignments(int $professorId): array
{
    $stmt = getDB()->prepare(
        "SELECT ta.id AS assignment_id, c.id AS course_id, c.name AS course_name, c.credits,
                c.semester, p.id AS promotion_id, p.name AS promotion_name, p.group_name,
                p.academic_year, f.name AS filiere_name, d.name AS department_name,
                (SELECT COUNT(*) FROM students st WHERE st.promotion_id = p.id AND st.status = 'active') AS student_count,
                (SELECT COUNT(*) FROM student_grades sg WHERE sg.course_id = c.id) AS graded_count
         FROM teaching_assignments ta
         JOIN courses c ON c.id = ta.course_id
         JOIN promotions p ON p.id = c.promotion_id
         LEFT JOIN filieres f ON f.id = p.filiere_id
         JOIN departments d ON d.id = p.department_id
         WHERE ta.professor_id = ? AND ta.status = 'active'
         ORDER BY p.academic_year DESC, c.name"
    );
    $stmt->execute([$professorId]);
    return $stmt->fetchAll();
}

/**
 * Étudiants actifs de la promotion d'un cours, avec leur note existante
 * pour ce cours si elle existe déjà (jointure LEFT).
 */
function getCourseStudentsWithGrades(int $courseId): array
{
    $stmt = getDB()->prepare(
        "SELECT st.id AS student_id, st.full_name, st.matricule, sg.id AS grade_id,
                sg.score, sg.gpa, sg.grade, sg.status AS grade_status
         FROM courses c
         JOIN students st ON st.promotion_id = c.promotion_id AND st.status = 'active'
         LEFT JOIN student_grades sg ON sg.student_id = st.id AND sg.course_id = c.id
         WHERE c.id = ?
         ORDER BY st.full_name"
    );
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

/**
 * Moyenne et GPA d'une UE pour un étudiant, pondérés par les crédits de
 * chaque cours (ECUE) de cette UE. Retourne null si aucune note saisie.
 */
function calculateUEResult(int $studentId, int $ueId): ?array
{
    $stmt = getDB()->prepare(
        "SELECT sg.score, sg.gpa, c.credits, c.name AS course_name
         FROM student_grades sg JOIN courses c ON c.id = sg.course_id
         WHERE sg.student_id = ? AND c.ue_id = ? AND sg.status <> 'draft'"
    );
    $stmt->execute([$studentId, $ueId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return null;
    }

    $totalCredits = array_sum(array_column($rows, 'credits'));
    if ($totalCredits <= 0) {
        return null;
    }

    $weightedScore = 0.0;
    $weightedGpa = 0.0;
    foreach ($rows as $r) {
        $weightedScore += $r['score'] * $r['credits'];
        $weightedGpa   += $r['gpa'] * $r['credits'];
    }

    $mean = round($weightedScore / $totalCredits, 2);
    $threshold = getUeValidationThreshold();

    return [
        'credits'  => $totalCredits,
        'mean'     => $mean,
        'gpa'      => round($weightedGpa / $totalCredits, 2),
        'validated' => $mean >= $threshold,
        'courses'  => $rows,
    ];
}

/**
 * Résultat complet d'un semestre pour un étudiant : crédits, points
 * pondérés, moyenne, GPA, et détail UE par UE (validée / non validée).
 * GPA semestre = somme des points pondérés / somme des crédits — jamais
 * une simple moyenne des GPA des cours (fausse si les crédits diffèrent).
 */
function calculateSemesterResult(int $studentId, string $academicYear, string $semester): array
{
    $stmt = getDB()->prepare(
        "SELECT sg.score, sg.gpa, sg.weighted_points, c.credits, c.ue_id, u.name AS ue_name
         FROM student_grades sg
         JOIN courses c ON c.id = sg.course_id
         JOIN promotions p ON p.id = c.promotion_id
         LEFT JOIN ues u ON u.id = c.ue_id
         WHERE sg.student_id = ? AND p.academic_year = ? AND p.semester = ? AND sg.status <> 'draft'"
    );
    $stmt->execute([$studentId, $academicYear, $semester]);
    $rows = $stmt->fetchAll();

    $totalCredits = array_sum(array_column($rows, 'credits'));
    $totalWeighted = array_sum(array_column($rows, 'weighted_points'));

    $ueIds = array_unique(array_filter(array_column($rows, 'ue_id')));
    $ueResults = [];
    foreach ($ueIds as $ueId) {
        $ueResults[] = array_merge(['ue_id' => $ueId], calculateUEResult($studentId, (int) $ueId) ?? []);
    }
    $validatedCount = count(array_filter($ueResults, fn($u) => !empty($u['validated'])));

    return [
        'academic_year'   => $academicYear,
        'semester'        => $semester,
        'credits'         => $totalCredits,
        'weighted_points' => round($totalWeighted, 2),
        'mean'            => $totalCredits > 0 ? round(array_sum(array_map(fn($r) => $r['score'] * $r['credits'], $rows)) / $totalCredits, 2) : 0.0,
        'gpa'             => $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : 0.0,
        'ue_results'      => $ueResults,
        'ue_validated'    => $validatedCount,
        'ue_total'        => count($ueResults),
    ];
}

/**
 * Résultat annuel : cumul des deux semestres (S1 + S2) d'une même année
 * académique. GPA annuel = total des points pondérés / total des crédits.
 */
function calculateAnnualResult(int $studentId, string $academicYear): array
{
    $s1 = calculateSemesterResult($studentId, $academicYear, 'S1');
    $s2 = calculateSemesterResult($studentId, $academicYear, 'S2');

    $totalCredits = $s1['credits'] + $s2['credits'];
    $totalWeighted = $s1['weighted_points'] + $s2['weighted_points'];

    return [
        'academic_year'   => $academicYear,
        'credits'         => $totalCredits,
        'weighted_points' => round($totalWeighted, 2),
        'gpa'             => $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : 0.0,
        'mean_percent'    => $totalCredits > 0 ? round(($totalWeighted / $totalCredits) * 25, 2) : 0.0,
        'semesters'       => ['S1' => $s1, 'S2' => $s2],
    ];
}

/**
 * Cumul de cycle : moyenne pondérée par les crédits de TOUTES les années
 * académiques où l'étudiant a des notes (déduites de ses student_grades,
 * peu importe les changements de promotion au fil des années).
 */
function calculateCumulativeResult(int $studentId): array
{
    $stmt = getDB()->prepare(
        "SELECT DISTINCT p.academic_year
         FROM student_grades sg
         JOIN courses c ON c.id = sg.course_id
         JOIN promotions p ON p.id = c.promotion_id
         WHERE sg.student_id = ? AND sg.status <> 'draft'
         ORDER BY p.academic_year"
    );
    $stmt->execute([$studentId]);
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalCredits = 0.0;
    $totalWeighted = 0.0;
    $yearResults = [];
    foreach ($years as $year) {
        $annual = calculateAnnualResult($studentId, $year);
        $yearResults[] = $annual;
        $totalCredits += $annual['credits'];
        $totalWeighted += $annual['weighted_points'];
    }

    return [
        'credits'         => $totalCredits,
        'weighted_points' => round($totalWeighted, 2),
        'gpa'             => $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : 0.0,
        'mean_percent'    => $totalCredits > 0 ? round(($totalWeighted / $totalCredits) * 25, 2) : 0.0,
        'years'           => $yearResults,
    ];
}

/**
 * Vérifie qu'un utilisateur a le droit d'agir sur une note donnée
 * (consultation ou modification selon le rôle). Ne remplace pas
 * requireRole() : à utiliser en complément pour les contrôles fins
 * (ex: "ce chef a-t-il le droit de modifier CETTE note précise ?").
 */
function validateGradeAccess(int $userId, int $gradeId): bool
{
    $stmt = getDB()->prepare(
        "SELECT sg.student_id, c.id AS course_id, p.department_id
         FROM student_grades sg
         JOIN courses c ON c.id = sg.course_id
         JOIN promotions p ON p.id = c.promotion_id
         WHERE sg.id = ?"
    );
    $stmt->execute([$gradeId]);
    $grade = $stmt->fetch();
    if (!$grade) {
        return false;
    }

    $user = currentUser();
    return match ($user['role'] ?? null) {
        ROLE_ADMIN     => true,
        ROLE_HEAD      => (int) $grade['department_id'] === (int) ($user['department_id'] ?? 0),
        ROLE_PROFESSOR => isProfessorAssignedToCourse((int) $userId, (int) $grade['course_id']),
        ROLE_STUDENT   => (int) $grade['student_id'] === (int) $userId,
        default        => false,
    };
}

/**
 * Soumission des notes d'un cours par son professeur : fait passer
 * toutes les notes DRAFT de ce cours en SUBMITTED (verrouillage pour le
 * professeur — seul un Chef de Département pourra ensuite les corriger).
 * Retourne ['success' => bool, 'count' => int, 'error' => ?string].
 */
function submitGrades(int $professorId, int $courseId): array
{
    if (!isProfessorAssignedToCourse($professorId, $courseId)) {
        return ['success' => false, 'count' => 0, 'error' => "Vous n'êtes pas affecté à ce cours."];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM student_grades WHERE course_id = ? AND status = 'draft'");
    $stmt->execute([$courseId]);
    $count = (int) $stmt->fetchColumn();

    if ($count === 0) {
        return ['success' => false, 'count' => 0, 'error' => 'Aucune note en brouillon à soumettre pour ce cours.'];
    }

    $upd = $db->prepare(
        "UPDATE student_grades SET status = 'submitted', submitted_by = ?, submitted_at = NOW()
         WHERE course_id = ? AND status = 'draft'"
    );
    $upd->execute([$professorId, $courseId]);

    $cn = $db->prepare('SELECT name FROM courses WHERE id = ?');
    $cn->execute([$courseId]);
    $courseName = (string) $cn->fetchColumn();
    $rows = $db->prepare("SELECT student_id, score FROM student_grades WHERE course_id = ? AND status = 'submitted' AND submitted_by = ?");
    $rows->execute([$courseId, $professorId]);
    foreach ($rows->fetchAll() as $r) {
        notifyStudentOfGrade((int) $r['student_id'], $courseName, (float) $r['score']);
    }

    logAudit('grades_submit', "Notes soumises pour le cours #$courseId ($count note(s))");
    return ['success' => true, 'count' => $count, 'error' => null];
}

/**
 * Enregistre (crée ou met à jour) une note en BROUILLON pour un étudiant
 * dans un cours, puis recalcule GPA / points pondérés / lettre.
 * Refuse toute modification si la note est déjà soumise ou verrouillée.
 * Retourne true si la note a été enregistrée.
 */
function saveDraftGrade(int $studentId, int $courseId, float $score): bool
{
    if ($score < 0 || $score > 100) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, status FROM student_grades WHERE student_id = ? AND course_id = ?');
    $stmt->execute([$studentId, $courseId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] !== 'draft') {
            return false;
        }
        $db->prepare('UPDATE student_grades SET score = ? WHERE id = ?')->execute([$score, $existing['id']]);
        $gradeId = (int) $existing['id'];
    } else {
        $db->prepare("INSERT INTO student_grades (student_id, course_id, score, status) VALUES (?, ?, ?, 'draft')")
           ->execute([$studentId, $courseId, $score]);
        $gradeId = (int) $db->lastInsertId();
    }

    refreshGradeDerivedFields($gradeId, $score);
    return true;
}

/**
 * Récupère, pour un cours donné, les étudiants inscrits indexés par
 * matricule en majuscules — utilisé par les deux parseurs d'import
 * (CSV et PDF) pour éviter de dupliquer la requête et la logique.
 */
function getCourseEnrolledByMatricule(int $courseId): array
{
    $stmt = getDB()->prepare(
        "SELECT st.id, st.full_name, st.matricule, st.status AS student_status, sg.status AS grade_status
         FROM courses c
         JOIN students st ON st.promotion_id = c.promotion_id
         LEFT JOIN student_grades sg ON sg.student_id = st.id AND sg.course_id = c.id
         WHERE c.id = ?"
    );
    $stmt->execute([$courseId]);
    $enrolled = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['matricule'] !== null && $row['matricule'] !== '') {
            $enrolled[strtoupper($row['matricule'])] = $row;
        }
    }
    return $enrolled;
}

/**
 * Valide un couple (matricule, note) candidat face aux étudiants inscrits
 * dans le cours. Renvoie une ligne de résultat au même format pour le
 * CSV et le PDF, afin que la prévisualisation et l'import restent identiques
 * quelle que soit la source du fichier.
 */
function evaluateGradeCandidate(string $matricule, string $rawScore, array $enrolled, array &$seen, PDOStatement $lookupStmt): array
{
    $entry = ['matricule' => $matricule, 'name' => '', 'score' => $rawScore,
              'student_id' => null, 'ok' => false, 'message' => ''];
    $key = strtoupper($matricule);

    if ($matricule === '') {
        $entry['message'] = 'Matricule manquant';
    } elseif (isset($seen[$key])) {
        $entry['message'] = 'Matricule en double dans le fichier';
    } elseif (!is_numeric($rawScore)) {
        $entry['message'] = 'Note non numérique';
    } elseif ((float) $rawScore < 0 || (float) $rawScore > 100) {
        $entry['message'] = 'Note hors limites (0 à 100)';
    } elseif (!isset($enrolled[$key])) {
        $lookupStmt->execute([$matricule]);
        $entry['message'] = $lookupStmt->fetchColumn() > 0
            ? 'Étudiant hors de la promotion de ce cours'
            : 'Matricule inconnu';
    } elseif ($enrolled[$key]['student_status'] !== 'active') {
        $entry['name'] = $enrolled[$key]['full_name'];
        $entry['message'] = 'Étudiant inactif';
    } elseif ($enrolled[$key]['grade_status'] !== null && $enrolled[$key]['grade_status'] !== 'draft') {
        $entry['name'] = $enrolled[$key]['full_name'];
        $entry['message'] = 'Note déjà soumise (non modifiable)';
    } else {
        $entry['name'] = $enrolled[$key]['full_name'];
        $entry['student_id'] = (int) $enrolled[$key]['id'];
        $entry['score'] = round((float) $rawScore, 2);
        $entry['ok'] = true;
        $entry['message'] = 'OK';
    }

    $seen[$key] = true;
    return $entry;
}

/**
 * Extraction de texte "maison" d'un PDF, sans bibliothèque externe
 * (aucun accès réseau disponible sur ce serveur pour installer une
 * dépendance). Fonctionne pour des PDF texte (export Word/Excel/LibreOffice,
 * tableau généré numériquement) — PAS pour un PDF scanné/image, qui
 * demanderait de l'OCR, indisponible ici.
 * Chaque ligne du PDF devient une ligne de la chaîne retournée.
 */
function extractTextFromPdf(string $filePath): string
{
    $raw = file_get_contents($filePath);
    if ($raw === false) {
        return '';
    }

    $text = '';
    // 1. Repère tous les flux (stream ... endstream) et les décompresse
    //    si nécessaire (FlateDecode est le filtre quasi systématique).
    if (preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $streams)) {
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);
            if ($content === false) {
                $content = $stream; // flux déjà en clair (rare mais possible)
            }
            // 2. Dans un flux de contenu PDF, le texte est porté par les
            //    opérateurs Tj (chaîne unique) et TJ (tableau de chaînes),
            //    à l'intérieur de blocs BT...ET. Td/TD/T* marquent un
            //    changement de ligne dans la mise en page du PDF.
            if (preg_match_all('/BT(.*?)ET/s', $content, $blocks)) {
                foreach ($blocks[1] as $block) {
                    $line = '';
                    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj|\[(?:[^\[\]]*)\]\s*TJ|T[dD*]/', $block, $ops)) {
                        foreach ($ops[0] as $op) {
                            if (str_ends_with($op, 'Tj')) {
                                $line .= pdfDecodeLiteralString(substr($op, 0, -3));
                            } elseif (str_ends_with($op, 'TJ')) {
                                preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $op, $parts);
                                foreach ($parts[0] as $p) {
                                    $line .= pdfDecodeLiteralString($p);
                                }
                            } else {
                                $line .= "\n"; // Td / TD / T* : nouvelle ligne
                            }
                        }
                    }
                    $text .= $line . "\n";
                }
            }
        }
    }

    return $text;
}

/**
 * Décode une chaîne littérale PDF "(...)" : retire les parenthèses et
 * interprète les échappements \(, \), \\, \n, \r, \t.
 */
function pdfDecodeLiteralString(string $s): string
{
    $s = trim($s);
    if ($s === '' || $s[0] !== '(') {
        return '';
    }
    $s = substr($s, 1, -1);
    return str_replace(
        ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
        ['(', ')', '\\', "\n", "\r", "\t"],
        $s
    );
}

/**
 * Analyse un fichier CSV de notes (colonnes : Matricule ; Note) pour un
 * cours donné, SANS rien enregistrer. Chaque ligne est vérifiée :
 * matricule connu, étudiant bien inscrit dans la promotion du cours,
 * note numérique entre 0 et 100, pas de doublon dans le fichier, note
 * pas déjà soumise. Retourne la liste des lignes avec leur statut.
 */
function parseGradesCsv(string $filePath, int $courseId): array
{
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return [];
    }

    // Détection du séparateur sur la première ligne (; ou ,)
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',') ? ';' : ',';

    $enrolled = getCourseEnrolledByMatricule($courseId);
    $lookup = getDB()->prepare('SELECT COUNT(*) FROM students WHERE matricule = ?');
    $rows = [];
    $seen = [];
    $lineNo = 0;

    while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
        $lineNo++;
        if (count($data) < 2 || trim(implode('', $data)) === '') {
            continue;
        }
        $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]); // BOM Excel
        $matricule = trim($data[0]);
        $rawScore  = trim(str_replace(',', '.', $data[1]));

        // En-tête éventuel : la note n'est pas numérique sur la 1re ligne
        if ($lineNo === 1 && !is_numeric($rawScore)) {
            continue;
        }

        $rows[] = array_merge(['line' => $lineNo], evaluateGradeCandidate($matricule, $rawScore, $enrolled, $seen, $lookup));
    }
    fclose($handle);
    return $rows;
}

/**
 * Analyse un PDF de notes pour un cours donné (matricule + note par
 * ligne, dans n'importe quel ordre sur la ligne), SANS rien enregistrer.
 * Même format de retour que parseGradesCsv() pour une prévisualisation
 * identique quelle que soit la source.
 */
function parseGradesPdf(string $filePath, int $courseId): array
{
    $text = extractTextFromPdf($filePath);
    $lines = array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== '');
    if (empty($lines)) {
        return [];
    }

    $enrolled = getCourseEnrolledByMatricule($courseId);
    $lookup = getDB()->prepare('SELECT COUNT(*) FROM students WHERE matricule = ?');
    $seen = [];
    $rows = [];
    $lineNo = 0;

    foreach ($lines as $line) {
        $lineNo++;
        $tokens = preg_split('/\s+/', $line);
        $matricule = null;
        $score = null;

        foreach ($tokens as $tok) {
            if ($matricule === null && preg_match('/^[A-Z0-9\-\/]{4,}$/i', $tok) && preg_match('/\d/', $tok)) {
                // Le premier jeton "alphanumérique long avec chiffres" est
                // considéré comme le matricule (ex : 24100330, ETU-2026-012).
                $matricule = $tok;
                continue;
            }
            $normalized = str_replace(',', '.', $tok);
            if (is_numeric($normalized) && (float) $normalized >= 0 && (float) $normalized <= 100) {
                $score = $normalized; // dernière valeur plausible retenue comme note
            }
        }

        if ($matricule === null || $score === null) {
            continue; // ligne d'en-tête, de titre ou non exploitable : ignorée silencieusement
        }

        $rows[] = array_merge(['line' => $lineNo], evaluateGradeCandidate($matricule, $score, $enrolled, $seen, $lookup));
    }

    return $rows;
}



/**
 * Classement des promotions (= filière + groupe + année + semestre) d'un
 * département par moyenne pondérée, du meilleur au moins bon. Ne compte
 * que les notes soumises ou verrouillées (jamais les brouillons).
 */
function getPromotionGradeRanking(int $departmentId): array
{
    $stmt = getDB()->prepare(
        "SELECT p.id AS promotion_id, p.name AS promotion_name, p.group_name, p.academic_year, p.semester,
                f.name AS filiere_name,
                SUM(sg.score * c.credits) / SUM(c.credits) AS mean,
                SUM(sg.weighted_points) / SUM(c.credits) AS gpa,
                COUNT(DISTINCT sg.student_id) AS student_count,
                SUM(c.credits) AS total_credits
         FROM student_grades sg
         JOIN courses c ON c.id = sg.course_id
         JOIN promotions p ON p.id = c.promotion_id
         LEFT JOIN filieres f ON f.id = p.filiere_id
         WHERE p.department_id = ? AND sg.status <> 'draft'
         GROUP BY p.id
         HAVING total_credits > 0
         ORDER BY mean DESC"
    );
    $stmt->execute([$departmentId]);
    return $stmt->fetchAll();
}

/**
 * Classement des étudiants d'un département par moyenne pondérée
 * (toutes leurs notes soumises/verrouillées dans ce département).
 * $order 'DESC' = meilleurs d'abord, 'ASC' = à accompagner en priorité.
 */
function getStudentGradeRanking(int $departmentId, string $order = 'DESC', int $limit = 10): array
{
    $order = $order === 'ASC' ? 'ASC' : 'DESC';
    $stmt = getDB()->prepare(
        "SELECT st.id AS student_id, st.full_name, st.matricule, p.name AS promotion_name, p.group_name,
                SUM(sg.score * c.credits) / SUM(c.credits) AS mean,
                SUM(sg.weighted_points) / SUM(c.credits) AS gpa,
                COUNT(sg.id) AS graded_courses
         FROM student_grades sg
         JOIN courses c ON c.id = sg.course_id
         JOIN promotions p ON p.id = c.promotion_id
         JOIN students st ON st.id = sg.student_id
         WHERE p.department_id = ? AND sg.status <> 'draft' AND st.status = 'active'
         GROUP BY st.id
         HAVING SUM(c.credits) > 0
         ORDER BY mean $order
         LIMIT ?"
    );
    $stmt->bindValue(1, $departmentId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Notifie un étudiant qu'une note lui a été ajoutée (soumise, donc
 * désormais visible) ou corrigée. Jamais appelée pour un simple
 * brouillon, invisible pour l'étudiant.
 */
function notifyStudentOfGrade(int $studentId, string $courseName, float $score, bool $isCorrection = false): void
{
    $message = $isCorrection
        ? sprintf("Votre note pour le cours « %s » a été corrigée : %.2f/100.", $courseName, $score)
        : sprintf("Une nouvelle note est disponible pour le cours « %s » : %.2f/100.", $courseName, $score);

    $stmt = getDB()->prepare('INSERT INTO notifications (student_id, type, message, link) VALUES (?, ?, ?, ?)');
    $stmt->execute([$studentId, 'grade', $message, BASE_URL . '/student/grades.php']);
}

/**
 * Notifie le professeur affecté à un cours qu'une note qu'il a soumise
 * vient d'être corrigée par le Chef de Département.
 */
function notifyProfessorOfGradeChange(int $professorUserId, string $matricule, string $courseName, float $oldScore, float $newScore): void
{
    $message = sprintf(
        "Modification d'une note — Étudiant : %s — Cours : %s — Ancienne note : %s — Nouvelle note : %s — Modifié par : Chef de Département",
        $matricule, $courseName, $oldScore, $newScore
    );
    notifyUser($professorUserId, 'grade_change', $message, BASE_URL . '/professor/notifications.php');
}

