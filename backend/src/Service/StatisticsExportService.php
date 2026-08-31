<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Export générique (XLSX / PDF) des résultats de l'API Statistiques, quelle que soit la forme
 * de la structure retournée par un endpoint (liste plate, arbre région -> département ->
 * arrondissement, bundle "vue-globale"...). KPI "Export des données" de l'écran de disponibilité
 * des filtres : même donnée, mêmes filtres déjà appliqués en amont, juste une présentation
 * fichier au lieu de JSON.
 */
final class StatisticsExportService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function respondXlsx(array $data, string $title): Response
    {
        $content = $this->buildXlsx($this->toTable($data), $title);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->safeFilename($title) . '.xlsx"');

        return $response;
    }

    public function respondPdf(array $data, string $title): Response
    {
        $content = $this->buildPdf($this->toTable($data), $title);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->safeFilename($title) . '.pdf"');

        return $response;
    }

    /**
     * Aplatit une structure arbitraire en tableau {headers, rows}.
     * - Liste homogène d'objets (la grande majorité des endpoints stats) -> une ligne par élément,
     *   colonnes = union des clés rencontrées.
     * - Objet associatif (bundles "vue-globale", paires nombre/pourcentage...) -> tableau clé/valeur
     *   à 2 colonnes.
     * Dans les deux cas, une valeur elle-même imbriquée (sous-liste, sous-objet) est encodée en
     * JSON dans sa cellule plutôt que perdue : rester lisible dans un tableur prime sur un
     * découpage parfait de chaque forme de réponse, très hétérogènes d'un endpoint à l'autre.
     *
     * @return array{headers: string[], rows: array<int, array<int, string>>}
     */
    private function toTable(array $data): array
    {
        if ($this->isListOfArrays($data)) {
            return $this->flattenList($data);
        }

        return $this->flattenAssociative($data);
    }

    private function isListOfArrays(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string>>}
     */
    private function flattenList(array $list): array
    {
        $headers = [];
        foreach ($list as $item) {
            foreach (array_keys($item) as $key) {
                if (!in_array((string) $key, $headers, true)) {
                    $headers[] = (string) $key;
                }
            }
        }

        $rows = array_map(function (array $item) use ($headers) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $this->cellValue($item[$header] ?? '');
            }

            return $row;
        }, $list);

        return ['headers' => $headers, 'rows' => array_values($rows)];
    }

    /**
     * @return array{headers: string[], rows: array<int, array<int, string>>}
     */
    private function flattenAssociative(array $data): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [(string) $key, $this->cellValue($value)];
        }

        return ['headers' => ['Clé', 'Valeur'], 'rows' => $rows];
    }

    private function cellValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return null === $value ? '' : (string) $value;
    }

    /**
     * @param array{headers: string[], rows: array<int, array<int, string>>} $table
     */
    private function buildXlsx(array $table, string $title): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31) ?: 'Export');

        foreach ($table['headers'] as $i => $header) {
            $sheet->setCellValue($this->columnLetter($i + 1) . '1', $header);
        }

        if ([] !== $table['headers']) {
            $lastColumn = $this->columnLetter(count($table['headers']));
            $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        }

        $rowNum = 2;
        foreach ($table['rows'] as $row) {
            foreach ($row as $i => $value) {
                $sheet->setCellValue($this->columnLetter($i + 1) . $rowNum, $value);
            }
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @param array{headers: string[], rows: array<int, array<int, string>>} $table
     */
    private function buildPdf(array $table, string $title): string
    {
        $html = $this->twig->render('stats/export.html.twig', [
            'title' => $title,
            'headers' => $table['headers'],
            'rows' => $table['rows'],
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Conversion index de colonne (1-based) -> lettre Excel ("A", "Z", "AA"...), écrite à la
     * main plutôt que via un utilitaire PhpSpreadsheet pour ne pas dépendre d'une API interne
     * susceptible de changer de nom d'une version à l'autre.
     */
    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function safeFilename(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'export';
        $slug = trim($slug, '-');

        return '' !== $slug ? $slug : 'export';
    }
}
