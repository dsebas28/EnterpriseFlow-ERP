<?php

namespace App\Reports\Export;

use App\Models\Company;
use App\Reports\Column;
use App\Reports\Report;
use App\Reports\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Writes a report to a local temporary file in CSV, XLSX or PDF. Rows are
 * streamed (CSV/XLSX) so memory stays flat for large reports.
 */
final class ReportWriter
{
    /**
     * PDFs are laid out in memory by dompdf; very large reports belong in CSV/XLSX.
     */
    public const PDF_MAX_ROWS = 2000;

    /**
     * @return array{path: string, rows: int}
     */
    public function write(Report $report, ReportFilters $filters, string $format, Company $company): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        $rows = match ($format) {
            'csv' => $this->csv($report, $filters, $company, $path),
            'xlsx' => $this->xlsx($report, $filters, $company, $path),
            'pdf' => $this->pdf($report, $filters, $company, $path),
            default => throw new RuntimeException("Unsupported format [{$format}]."),
        };

        return ['path' => $path, 'rows' => $rows];
    }

    private function csv(Report $report, ReportFilters $filters, Company $company, string $path): int
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException('Could not open the export file.');
        }

        // UTF-8 BOM so Excel opens accented characters correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(fn (Column $c) => $c->label, $report->columns()), escape: '\\');

        $count = 0;
        foreach ($report->rows($filters) as $row) {
            fputcsv($handle, $this->cells($report, $row, $company->currency), escape: '\\');
            $count++;
        }

        fclose($handle);

        return $count;
    }

    private function xlsx(Report $report, ReportFilters $filters, Company $company, string $path): int
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_map(fn (Column $c) => $c->label, $report->columns())));

        $count = 0;
        foreach ($report->rows($filters) as $row) {
            $writer->addRow(Row::fromValues($this->cells($report, $row, $company->currency)));
            $count++;
        }

        $writer->close();

        return $count;
    }

    private function pdf(Report $report, ReportFilters $filters, Company $company, string $path): int
    {
        $rows = [];
        foreach ($report->rows($filters) as $row) {
            if (count($rows) >= self::PDF_MAX_ROWS) {
                throw new RuntimeException('This report has more than '.self::PDF_MAX_ROWS.' rows. Export it as CSV or Excel instead.');
            }
            $rows[] = $row;
        }

        $pdf = Pdf::loadView('pdf.report', [
            'report' => $report,
            'filters' => $filters,
            'company' => $company,
            'rows' => $rows,
            'totals' => $report->totals($rows),
        ])->setPaper('a4', count($report->columns()) > 5 ? 'landscape' : 'portrait');

        file_put_contents($path, $pdf->output());

        return count($rows);
    }

    /**
     * @param  array<string, string|int|float|null>  $row
     * @return list<string|int|float|null>
     */
    private function cells(Report $report, array $row, string $currency): array
    {
        return array_map(
            fn (Column $column) => CellValue::format($column, $row[$column->key] ?? null, $currency),
            $report->columns(),
        );
    }
}
