<?php
/**
 * includes/SimplePDF.php
 *
 * Générateur PDF minimal, en PHP pur, sans aucune dépendance externe
 * (pas de Composer, pas de bibliothèque tierce). Suffisant pour produire
 * des documents texte / tableaux comme le registre de présence.
 *
 * Unités utilisées dans l'API publique : millimètres, origine en haut à
 * gauche de la page (comme dans un logiciel de mise en page classique).
 */
class SimplePDF
{
    private array $pages = [];      // chaque page : ['w'=>pt,'h'=>pt,'content'=>string]
    private ?string $buffer = null; // contenu de la page courante
    private float $curW = 0;
    private float $curH = 0;
    private string $curFont = 'F1'; // F1 = Helvetica, F2 = Helvetica-Bold
    private float $curSize = 10;

    private const MM_TO_PT = 2.834645669;

    public function addPage(string $orientation = 'P'): void
    {
        $this->flushPage();

        if (strtoupper($orientation) === 'L') {
            $this->curW = 297 * self::MM_TO_PT;
            $this->curH = 210 * self::MM_TO_PT;
        } else {
            $this->curW = 210 * self::MM_TO_PT;
            $this->curH = 297 * self::MM_TO_PT;
        }

        $this->buffer = '';
        $this->curFont = 'F1';
        $this->curSize = 10;
        $this->applyFont();
    }

    public function setFont(string $style = '', float $size = 10): void
    {
        $this->curFont = (strtoupper($style) === 'B') ? 'F2' : 'F1';
        $this->curSize = $size;
        $this->applyFont();
    }

    private function applyFont(): void
    {
        $this->buffer .= sprintf("/%s %.2F Tf\n", $this->curFont, $this->curSize);
    }

    /** Écrit du texte. $x, $y en mm depuis le coin haut-gauche de la page. */
    public function text(float $x, float $y, string $txt): void
    {
        $px = $x * self::MM_TO_PT;
        $py = $this->curH - ($y * self::MM_TO_PT);
        $encoded = $this->encodeText($txt);
        $this->buffer .= sprintf("BT %.2F %.2F Td (%s) Tj ET\n", $px, $py, $encoded);
    }

    /** Trace une ligne entre deux points (mm). */
    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $px1 = $x1 * self::MM_TO_PT; $py1 = $this->curH - ($y1 * self::MM_TO_PT);
        $px2 = $x2 * self::MM_TO_PT; $py2 = $this->curH - ($y2 * self::MM_TO_PT);
        $this->buffer .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $px1, $py1, $px2, $py2);
    }

    /** Trace un rectangle (contour seul), $x/$y = coin haut-gauche, en mm. */
    public function rect(float $x, float $y, float $w, float $h): void
    {
        $px = $x * self::MM_TO_PT;
        $py = $this->curH - (($y + $h) * self::MM_TO_PT);
        $pw = $w * self::MM_TO_PT;
        $ph = $h * self::MM_TO_PT;
        $this->buffer .= sprintf("%.2F %.2F %.2F %.2F re S\n", $px, $py, $pw, $ph);
    }

    /** Remplit un rectangle avec un gris clair (0.0 = noir, 1.0 = blanc). */
    public function filledRect(float $x, float $y, float $w, float $h, float $gray = 0.9): void
    {
        $px = $x * self::MM_TO_PT;
        $py = $this->curH - (($y + $h) * self::MM_TO_PT);
        $pw = $w * self::MM_TO_PT;
        $ph = $h * self::MM_TO_PT;
        $this->buffer .= sprintf("%.3F g %.2F %.2F %.2F %.2F re f 0 g\n", $gray, $px, $py, $pw, $ph);
    }

    /** Largeur de page utile en mm (utile pour centrer / calculer des colonnes). */
    public function pageWidthMm(): float
    {
        return $this->curW / self::MM_TO_PT;
    }

    public function pageHeightMm(): float
    {
        return $this->curH / self::MM_TO_PT;
    }

    private function encodeText(string $txt): string
    {
        // Police standard non-embarquée avec /WinAnsiEncoding (~ Windows-1252).
        $latin1 = @mb_convert_encoding($txt, 'CP1252', 'UTF-8');
        if ($latin1 === false) {
            $latin1 = @mb_convert_encoding($txt, 'ISO-8859-1', 'UTF-8');
        }
        if ($latin1 === false) {
            $latin1 = $txt;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
    }

    private function flushPage(): void
    {
        if ($this->buffer !== null) {
            $this->pages[] = ['w' => $this->curW, 'h' => $this->curH, 'content' => $this->buffer];
        }
    }

    /**
     * Génère le PDF final.
     *
     * @param string $dest 'I' = affichage inline navigateur, 'D' = téléchargement,
     *                      'S' = retourne la chaîne binaire, 'F' = enregistre sur disque.
     * @param string $filenameOrPath nom de fichier (I/D) ou chemin complet (F).
     */
    public function output(string $dest = 'I', string $filenameOrPath = 'document.pdf'): ?string
    {
        $this->flushPage();
        $this->buffer = null;

        if (empty($this->pages)) {
            $this->pages[] = ['w' => 210 * self::MM_TO_PT, 'h' => 297 * self::MM_TO_PT, 'content' => ''];
        }

        $pdf = $this->buildPdfString();

        switch ($dest) {
            case 'F':
                file_put_contents($filenameOrPath, $pdf);
                return null;
            case 'S':
                return $pdf;
            case 'D':
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . basename($filenameOrPath) . '"');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf;
                return null;
            case 'I':
            default:
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . basename($filenameOrPath) . '"');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf;
                return null;
        }
    }

    private function buildPdfString(): string
    {
        $objects = [];   // index 1..N => contenu de l'objet (sans "N 0 obj ... endobj")
        $pageObjIds = [];
        $contentObjIds = [];

        // Réservation des ID : 1 = Catalog, 2 = Pages, 3 = Font Helvetica, 4 = Font Helvetica-Bold
        $nextId = 5;
        foreach ($this->pages as $page) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $stream = $page['content'];
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $page['w'], $page['h'], $contentId
            );
            $pageObjIds[] = $pageId;
            $contentObjIds[] = $contentId;
        }

        $kids = implode(' ', array_map(fn($id) => "$id 0 R", $pageObjIds));

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [$kids] /Count " . count($pageObjIds) . " >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        ksort($objects);

        $out = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }

        $xrefStart = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 $count\n";
        $out .= "0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $out .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";

        return $out;
    }
}
