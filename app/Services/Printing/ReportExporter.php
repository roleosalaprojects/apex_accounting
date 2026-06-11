<?php

declare(strict_types=1);

namespace App\Services\Printing;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Loose-leaf-compatible report export to XLSX and PDF (§12). Reports hand it a
 * title, a header row, and data rows; the company name / TIN + branch code and
 * period header sit at the top of every page.
 */
final class ReportExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function toXlsx(string $companyHeader, string $title, array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', $companyHeader);
        $sheet->setCellValue('A2', $title);
        $sheet->fromArray($headers, null, 'A4');
        $sheet->fromArray($rows, null, 'A5');

        $writer = new Xlsx($spreadsheet);

        $tmp = tempnam(sys_get_temp_dir(), 'apex_xlsx_');
        $writer->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function toPdf(string $companyHeader, string $title, array $headers, array $rows): string
    {
        return Pdf::loadView('print.report', [
            'companyHeader' => $companyHeader,
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
        ])->output();
    }
}
