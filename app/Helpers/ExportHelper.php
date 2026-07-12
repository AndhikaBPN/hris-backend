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
        // Kop surat — match DOCX layout: logo left, company text center
        $html .= '.kop-wrap{width:100%;border-collapse:collapse;margin-bottom:0}';
        $html .= '.kop-logo-cell{width:75px;text-align:left;vertical-align:middle;padding:0 10px 0 0}';
        $html .= '.kop-text-cell{vertical-align:middle;text-align:center;padding:4px 0}';
        $html .= '.company-name{font-size:18px;font-weight:bold;font-family:Georgia,serif;letter-spacing:2px;color:#000;line-height:1.2}';
        $html .= '.company-addr{font-size:9px;margin-top:3px;color:#333;letter-spacing:0.3px}';
        // Double bottom border matching DOCX kop surat style
        $html .= '.kop-border-outer{border-top:3px solid #000;margin:6px 0 0}';
        $html .= '.kop-border-inner{border-top:1px solid #000;margin:2px 0 8px}';
        // Report section
        $html .= '.report-title{text-align:center;font-size:13px;font-weight:bold;margin:6px 0 2px;text-transform:uppercase;letter-spacing:0.5px}';
        $html .= '.report-meta{text-align:center;font-size:8px;color:#666;margin-bottom:8px}';
        // Data table
        $html .= 'table.data{border-collapse:collapse;width:100%}';
        $html .= 'table.data th{background:#722F37;color:#fff;padding:5px 8px;text-align:left;font-size:9px}';
        $html .= 'table.data td{border:1px solid #ccc;padding:4px 8px;font-size:9px}';
        $html .= 'table.data tr:nth-child(even) td{background:#fdf5f5}';
        // Signature footer
        $html .= '.sig-wrap{width:100%;border-collapse:collapse;margin-top:30px}';
        $html .= '.sig-date{text-align:center;font-size:10px;width:200px;vertical-align:top;padding-bottom:70px}';
        $html .= '.sig-name-cell{text-align:center;width:200px}';
        $html .= '.sig-name{font-weight:bold;font-size:11px;border-top:1px solid #000;padding-top:4px;display:block}';
        $html .= '.sig-role{font-size:9px;color:#444}';
        $html .= '</style>';

        // ── Kop surat ──────────────────────────────────────────────────────────
        $html .= '<table class="kop-wrap"><tr>';
        if ($logoSrc) {
            $html .= '<td class="kop-logo-cell"><img src="' . $logoSrc . '" width="65" height="65" /></td>';
        }
        $html .= '<td class="kop-text-cell">';
        $html .= '<div class="company-name">PT. YANG PENTING DULUPAJA</div>';
        $html .= '<div class="company-addr">Jl. Harapan I No. 35, Setu, Cipayung, Jakarta Timur, DKI Jakarta 13880</div>';
        $html .= '</td></tr></table>';
        $html .= '<div class="kop-border-outer"></div>';
        $html .= '<div class="kop-border-inner"></div>';

        // ── Judul & meta ───────────────────────────────────────────────────────
        $html .= '<div class="report-title">' . htmlspecialchars($title) . '</div>';
        $html .= '<div class="report-meta">Dicetak: ' . date('d/m/Y H:i') . ' &nbsp;|&nbsp; Jumlah data: ' . count($rows) . ' baris</div>';

        // ── Data table ─────────────────────────────────────────────────────────
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

        // ── Footer: tempat, tanggal, tanda tangan, nama ────────────────────────
        $html .= '<table class="sig-wrap">';
        $html .= '<tr>';
        $html .= '<td></td>';
        $html .= '<td class="sig-date">' . htmlspecialchars($place) . ', ' . $dateStr . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td></td>';
        $html .= '<td class="sig-name-cell">';
        $html .= '<span class="sig-name">' . htmlspecialchars($signerName) . '</span>';
        if ($signerRole) {
            $html .= '<br><span class="sig-role">' . htmlspecialchars($signerRole) . '</span>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

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
