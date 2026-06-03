<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExportHelper
{
    public static function xlsx(string $filename, array $rows): void
    {
        $headers = empty($rows) ? [] : array_keys($rows[0]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $cell = $colLetter . '1';
            $sheet->setCellValue($cell, self::formatHeader($header));
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $colIdx = 0;
            foreach ($row as $val) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($colLetter . $rowNum, $val);
                $colIdx++;
            }
            $rowNum++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new XlsxWriter($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public static function pdf(string $filename, string $title, array $rows): void
    {
        $headers = empty($rows) ? [] : array_keys($rows[0]);

        $html  = '<style>';
        $html .= 'body{font-family:Arial,sans-serif;font-size:10px}';
        $html .= 'h2{color:#4472C4;margin-bottom:4px}';
        $html .= 'p{margin:0 0 8px;color:#666;font-size:9px}';
        $html .= 'table{border-collapse:collapse;width:100%}';
        $html .= 'th{background:#4472C4;color:#fff;padding:5px 8px;text-align:left;font-size:9px}';
        $html .= 'td{border:1px solid #ddd;padding:4px 8px;font-size:9px}';
        $html .= 'tr:nth-child(even) td{background:#f5f8ff}';
        $html .= '</style>';

        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        $html .= '<p>Generated: ' . date('Y-m-d H:i:s', time()) . ' (Asia/Jakarta)</p>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars(self::formatHeader($h)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $val) {
                $html .= '<td>' . htmlspecialchars((string) ($val ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $mpdf = new \Mpdf\Mpdf([
            'orientation' => 'L',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 15,
            'margin_bottom' => 10,
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, 'D');
        exit;
    }

    private static function formatHeader(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
