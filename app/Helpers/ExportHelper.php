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

    public static function pdf(string $filename, string $title, array $rows, array $opts = []): void
    {
        $headers = empty($rows) ? [] : array_keys($rows[0]);

        $place      = $opts['place']       ?? 'Jakarta';
        $signerName = $opts['signer_name'] ?? '';
        $signerRole = $opts['signer_role'] ?? '';

        $logoPath   = __DIR__ . '/../../reporting/kop_image1.png';
        $logoSrc    = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '';

        $monthsId = ['','Januari','Februari','Maret','April','Mei','Juni',
                     'Juli','Agustus','September','Oktober','November','Desember'];
        $dateStr  = (int) date('d') . ' ' . $monthsId[(int) date('n')] . ' ' . date('Y');

        $html  = '<style>';
        $html .= 'body{font-family:Arial,sans-serif;font-size:10px;margin:0}';
        $html .= '.kop-table{width:100%;border-collapse:collapse;margin-bottom:4px}';
        $html .= '.kop-logo{width:70px;text-align:center;vertical-align:middle;padding-right:8px}';
        $html .= '.kop-text{vertical-align:middle;text-align:center}';
        $html .= '.company-name{font-size:16px;font-weight:bold;font-family:"Book Antiqua",Georgia,serif;letter-spacing:1px}';
        $html .= '.company-addr{font-size:9px;margin-top:2px;color:#333}';
        $html .= '.kop-line{border:0;border-top:3px solid #000;margin:4px 0 8px}';
        $html .= '.report-title{text-align:center;font-size:13px;font-weight:bold;margin:6px 0 4px}';
        $html .= '.report-meta{text-align:center;font-size:8px;color:#666;margin-bottom:8px}';
        $html .= 'table.data{border-collapse:collapse;width:100%}';
        $html .= 'table.data th{background:#4472C4;color:#fff;padding:5px 8px;text-align:left;font-size:9px}';
        $html .= 'table.data td{border:1px solid #ddd;padding:4px 8px;font-size:9px}';
        $html .= 'table.data tr:nth-child(even) td{background:#f5f8ff}';
        $html .= '.footer{margin-top:24px;width:100%}';
        $html .= '.footer-right{text-align:right;font-size:10px}';
        $html .= '.signer-box{display:inline-block;text-align:center;margin-top:4px;min-width:160px}';
        $html .= '.signer-line{border-top:1px solid #000;margin-top:60px;padding-top:4px;font-size:10px;font-weight:bold}';
        $html .= '.signer-role{font-size:9px;color:#444}';
        $html .= '</style>';

        // Kop surat
        $html .= '<table class="kop-table"><tr>';
        if ($logoSrc) {
            $html .= '<td class="kop-logo"><img src="' . $logoSrc . '" width="60" height="60" /></td>';
        }
        $html .= '<td class="kop-text">';
        $html .= '<div class="company-name">PT. YANG PENTING DULUPAJA</div>';
        $html .= '<div class="company-addr">Jl. Harapan I No. 35, Setu, Cipayung, Jakarta Timur, DKI Jakarta 13880</div>';
        $html .= '</td></tr></table>';
        $html .= '<hr class="kop-line">';

        // Title & meta
        $html .= '<div class="report-title">' . htmlspecialchars($title) . '</div>';
        $html .= '<div class="report-meta">Dicetak: ' . date('d/m/Y H:i') . ' &nbsp;|&nbsp; Jumlah data: ' . count($rows) . '</div>';

        // Data table
        $html .= '<table class="data"><thead><tr>';
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

        // Footer: tempat, tanggal, nama
        $html .= '<table class="footer"><tr><td width="60%"></td><td width="40%" class="footer-right">';
        $html .= '<div>' . htmlspecialchars($place) . ', ' . $dateStr . '</div>';
        $html .= '<div class="signer-box">';
        $html .= '<div class="signer-line">' . htmlspecialchars($signerName) . '</div>';
        if ($signerRole) {
            $html .= '<div class="signer-role">' . htmlspecialchars($signerRole) . '</div>';
        }
        $html .= '</div>';
        $html .= '</td></tr></table>';

        $mpdf = new \Mpdf\Mpdf([
            'orientation'   => 'L',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
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
