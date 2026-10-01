<?php
/**
 * pdf/generate_register.php
 * Construit l'objet SimplePDF du registre d'un cours, à partir des
 * données enregistrées en base (MySQL = source de vérité).
 * Ce fichier ne produit aucune sortie : il définit uniquement la
 * fonction buildRegisterPdf(), utilisée par view_register.php et
 * download_register.php.
 */
require_once ROOT_PATH . '/includes/SimplePDF.php';

const REGISTER_MAX_COLS_PER_PAGE = 12; // nb de séances (colonnes) par page
const REGISTER_MAX_ROWS_PER_PAGE = 22; // nb d'étudiants (lignes) par page

/**
 * Construit le registre PDF complet d'un cours (toutes les séances,
 * tous les étudiants ayant été appelés), avec pagination automatique.
 *
 * @return array{pdf: SimplePDF, filename: string}|null null si le cours n'existe pas.
 */
function buildRegisterPdf(int $courseId): ?array
{
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT c.*, p.name AS promotion_name, p.group_name, p.academic_year,
                d.name AS department_name, f.name AS filiere_name
         FROM courses c
         JOIN promotions p ON p.id = c.promotion_id
         JOIN departments d ON d.id = p.department_id
         LEFT JOIN filieres f ON f.id = p.filiere_id
         WHERE c.id = ?"
    );
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        return null;
    }

    // Séances du cours, dans l'ordre chronologique
    $stmt = $db->prepare('SELECT * FROM sessions WHERE course_id = ? ORDER BY session_date, start_time');
    $stmt->execute([$courseId]);
    $sessions = $stmt->fetchAll();

    // Étudiants ayant été appelés au moins une fois pour ce cours (historique fidèle)
    $stmt = $db->prepare(
        "SELECT DISTINCT st.id, st.full_name
         FROM students st
         JOIN attendance a ON a.student_id = st.id
         JOIN sessions se ON se.id = a.session_id
         WHERE se.course_id = ?
         ORDER BY st.full_name"
    );
    $stmt->execute([$courseId]);
    $students = $stmt->fetchAll();

    // Carte des présences : [session_id][student_id] = 'present'|'absent'
    $attendanceMap = [];
    if (!empty($sessions)) {
        $stmt = $db->prepare(
            "SELECT a.session_id, a.student_id, a.status
             FROM attendance a
             JOIN sessions se ON se.id = a.session_id
             WHERE se.course_id = ?"
        );
        $stmt->execute([$courseId]);
        foreach ($stmt->fetchAll() as $row) {
            $attendanceMap[$row['session_id']][$row['student_id']] = $row['status'];
        }
    }

    $completedHours = getCompletedHours($courseId);
    $remainingHours = calculateRemainingHours((float) $course['planned_hours'], $completedHours);
    $progress = calculateProgress((float) $course['planned_hours'], $completedHours);

    $pdf = new SimplePDF();

    $sessionChunks = array_chunk($sessions, REGISTER_MAX_COLS_PER_PAGE) ?: [[]];
    $studentChunks = array_chunk($students, REGISTER_MAX_ROWS_PER_PAGE) ?: [[]];

    $totalPages = count($sessionChunks) * count($studentChunks) + 1; // +1 pour la page de synthèse
    $pageNum = 0;

    foreach ($sessionChunks as $sessionChunk) {
        foreach ($studentChunks as $studentChunk) {
            $pageNum++;
            $pdf->addPage('L');
            renderRegisterPage($pdf, $course, $sessionChunk, $studentChunk, $attendanceMap,
                $completedHours, $remainingHours, $progress, $pageNum, $totalPages);
        }
    }

    $pageNum++;
    $pdf->addPage('L');
    renderSynthesisPage($pdf, $course, $sessions, $students, $attendanceMap,
        $completedHours, $remainingHours, $progress, $pageNum, $totalPages);

    $filename = registerFileName($course['name'], $course['promotion_name']);

    return ['pdf' => $pdf, 'filename' => $filename];
}

/**
 * Dessine la page de synthèse finale du registre (statistiques globales).
 */
function renderSynthesisPage(
    SimplePDF $pdf,
    array $course,
    array $sessions,
    array $students,
    array $attendanceMap,
    float $completedHours,
    float $remainingHours,
    float $progress,
    int $pageNum,
    int $totalPages
): void {
    $marginX = 10;
    $y = 14;

    $pdf->setFont('B', 16);
    $pdf->text($marginX, $y, 'Synthese du cours');
    $y += 10;

    $pdf->setFont('B', 11);
    $pdf->text($marginX, $y, $course['name'] . ' - ' . $course['promotion_name'] . ($course['group_name'] ? ' - ' . $course['group_name'] : ''));
    $y += 10;

    // Statistiques globales de présence
    $totalPresent = 0;
    $totalRecords = 0;
    foreach ($attendanceMap as $sessionAttendance) {
        foreach ($sessionAttendance as $status) {
            $totalRecords++;
            if ($status === 'present') {
                $totalPresent++;
            }
        }
    }
    $globalRate = calculateAttendanceRate($totalPresent, $totalRecords);

    $pdf->setFont('', 10);
    $lines = [
        sprintf('Nombre de seances realisees : %d', count($sessions)),
        sprintf('Nombre d\'etudiants suivis : %d', count($students)),
        sprintf('Volume horaire prevu : %.2f h', (float) $course['planned_hours']),
        sprintf('Heures prestees : %.2f h', $completedHours),
        sprintf('Heures restantes : %.2f h', $remainingHours),
        sprintf('Progression du cours : %.2f %%', $progress),
        sprintf('Taux de presence global : %.2f %% (%d presences sur %d appels)', $globalRate, $totalPresent, $totalRecords),
    ];
    foreach ($lines as $line) {
        $pdf->text($marginX, $y, $line);
        $y += 7;
    }

    $y += 6;
    $pdf->setFont('B', 10);
    $pdf->text($marginX, $y, 'Etudiants les moins assidus sur ce cours :');
    $y += 7;

    // Classement des étudiants par taux de présence croissant (les 5 plus faibles)
    $studentRates = [];
    foreach ($students as $s) {
        $present = 0;
        $total = 0;
        foreach ($attendanceMap as $sessionAttendance) {
            if (isset($sessionAttendance[$s['id']])) {
                $total++;
                if ($sessionAttendance[$s['id']] === 'present') {
                    $present++;
                }
            }
        }
        if ($total > 0) {
            $studentRates[] = ['name' => $s['full_name'], 'rate' => calculateAttendanceRate($present, $total)];
        }
    }
    usort($studentRates, fn($a, $b) => $a['rate'] <=> $b['rate']);

    $pdf->setFont('', 9);
    foreach (array_slice($studentRates, 0, 5) as $sr) {
        $pdf->text($marginX + 4, $y, sprintf('- %s : %.1f %% de presence', $sr['name'], $sr['rate']));
        $y += 6;
    }
    if (empty($studentRates)) {
        $pdf->text($marginX + 4, $y, 'Aucune donnee de presence disponible.');
    }

    $pdf->setFont('', 7);
    $pdf->text($marginX, $pdf->pageHeightMm() - 10,
        'Document genere automatiquement par X_Academy le ' . date('d/m/Y H:i') . '   |   Page ' . $pageNum . '/' . $totalPages);
}

/**
 * Dessine une page du registre (en-tête + tableau de présence).
 */
function renderRegisterPage(
    SimplePDF $pdf,
    array $course,
    array $sessionChunk,
    array $studentChunk,
    array $attendanceMap,
    float $completedHours,
    float $remainingHours,
    float $progress,
    int $pageNum,
    int $totalPages
): void {
    $marginX = 10;
    $y = 12;

    $settings = getUniversitySettings();

    $pdf->setFont('B', 14);
    $pdf->text($marginX, $y, $settings['university_name'] . ' — Registre de présence');
    $y += 6;

    $contactLine = [];
    if (!empty($settings['po_box'])) {
        $contactLine[] = 'B.P. ' . $settings['po_box'];
    }
    if (!empty($settings['city'])) {
        $contactLine[] = $settings['city'];
    }
    if (!empty($settings['email'])) {
        $contactLine[] = $settings['email'];
    }
    if (!empty($contactLine)) {
        $pdf->setFont('', 8);
        $pdf->text($marginX, $y, implode('   |   ', $contactLine));
        $y += 5;
    }
    $y += 2;

    $pdf->setFont('', 9);
    $pdf->text($marginX, $y, 'Departement : ' . $course['department_name']
        . ($course['filiere_name'] ? '   |   Filiere : ' . $course['filiere_name'] : '')
        . '   |   Promotion : ' . $course['promotion_name'] . ($course['group_name'] ? ' - ' . $course['group_name'] : ''));
    $y += 5;

    $pdf->text($marginX, $y, 'Annee academique : ' . $course['academic_year']
        . '   |   Cours : ' . $course['name']
        . '   |   Enseignant : ' . $course['teacher_name']
        . '   |   Semestre : ' . $course['semester']);
    $y += 5;

    $pdf->text($marginX, $y, sprintf(
        'Volume prevu : %.2f h   |   Heures prestees : %.2f h   |   Heures restantes : %.2f h   |   Progression : %.2f %%',
        (float) $course['planned_hours'], $completedHours, $remainingHours, $progress
    ));
    $y += 8;

    // ----- Tableau -----
    $nameColWidth = 55;
    $dateColWidth = 15;
    $rowHeight = 6;

    $tableTop = $y;
    $tableLeft = $marginX;

    // En-tête du tableau
    $pdf->setFont('B', 8);
    $pdf->filledRect($tableLeft, $y, $nameColWidth, $rowHeight, 0.85);
    $pdf->rect($tableLeft, $y, $nameColWidth, $rowHeight);
    $pdf->text($tableLeft + 2, $y + 4.2, 'Etudiant');

    $x = $tableLeft + $nameColWidth;
    foreach ($sessionChunk as $session) {
        $pdf->filledRect($x, $y, $dateColWidth, $rowHeight, 0.85);
        $pdf->rect($x, $y, $dateColWidth, $rowHeight);
        $shortDate = date('d/m', strtotime($session['session_date']));
        $pdf->text($x + 1.5, $y + 4.2, $shortDate);
        $x += $dateColWidth;
    }
    $tableWidth = $nameColWidth + ($dateColWidth * count($sessionChunk));
    $y += $rowHeight;

    // Lignes étudiants
    $pdf->setFont('', 8);
    foreach ($studentChunk as $student) {
        $pdf->rect($tableLeft, $y, $nameColWidth, $rowHeight);
        $pdf->text($tableLeft + 2, $y + 4.2, $student['full_name']);

        $x = $tableLeft + $nameColWidth;
        foreach ($sessionChunk as $session) {
            $pdf->rect($x, $y, $dateColWidth, $rowHeight);
            $status = $attendanceMap[$session['id']][$student['id']] ?? null;
            $mark = $status === 'present' ? 'P' : ($status === 'absent' ? 'A' : '-');
            $pdf->text($x + $dateColWidth / 2 - 1.5, $y + 4.2, $mark);
            $x += $dateColWidth;
        }
        $y += $rowHeight;

        if ($y > $pdf->pageHeightMm() - 20) {
            break; // sécurité anti-débordement (ne devrait pas arriver vu la pagination)
        }
    }

    if (empty($studentChunk)) {
        $pdf->text($tableLeft + 2, $y + 5, 'Aucun etudiant appele pour ce cours.');
    }

    // Pied de page
    $pdf->setFont('', 7);
    $pdf->text($marginX, $pdf->pageHeightMm() - 10,
        'Legende : P = Present, A = Absent   |   Genere le ' . date('d/m/Y H:i') . '   |   Page ' . $pageNum . '/' . $totalPages);
}
