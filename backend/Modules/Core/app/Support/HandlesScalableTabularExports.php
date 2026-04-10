<?php

namespace Modules\Core\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesScalableTabularExports
{
    protected function maxCopyRows(): int
    {
        return 10000;
    }

    protected function maxPrintRows(): int
    {
        return 2000;
    }

    protected function maxPdfRows(): int
    {
        return 5000;
    }

    protected function maxExcelRows(): int
    {
        // Excel supports 1,048,576 rows total, including the heading row.
        return 1048575;
    }

    protected function csvChunkSize(): int
    {
        return 2000;
    }

    protected function normalizeSpreadsheetExtension(string $type): string
    {
        return strtolower($type) === 'csv' ? 'csv' : 'xlsx';
    }

    protected function countExportRows(Builder $query): int
    {
        $countQuery = clone $query;

        return (int) $countQuery->toBase()->getCountForPagination();
    }

    protected function enforceCopyLimit(Builder $query): void
    {
        $this->enforceRowLimit(
            $query,
            $this->maxCopyRows(),
            'Clipboard copies',
            'CSV or Excel'
        );
    }

    protected function enforcePrintLimit(Builder $query): void
    {
        $this->enforceRowLimit(
            $query,
            $this->maxPrintRows(),
            'Print exports',
            'CSV, Excel, or filtered print output'
        );
    }

    protected function enforcePdfLimit(Builder $query): void
    {
        $this->enforceRowLimit(
            $query,
            $this->maxPdfRows(),
            'PDF exports',
            'CSV or Excel'
        );
    }

    protected function enforceExcelLimit(Builder $query): void
    {
        $this->enforceRowLimit(
            $query,
            $this->maxExcelRows(),
            'Excel exports',
            'CSV'
        );
    }

    protected function streamCsvDownload(
        Builder $query,
        string $filename,
        array $headings,
        Closure $mapRow
    ): StreamedResponse {
        set_time_limit(0);

        return response()->streamDownload(function () use ($query, $headings, $mapRow) {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            // UTF-8 BOM keeps Excel happy with non-ASCII text.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings);

            $rowNumber = 0;
            $streamQuery = clone $query;

            $streamQuery->chunk($this->csvChunkSize(), function ($records) use (&$rowNumber, $mapRow, $output) {
                foreach ($records as $record) {
                    $rowNumber++;
                    fputcsv($output, $mapRow($record, $rowNumber));
                }

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                flush();
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    protected function enforceRowLimit(
        Builder $query,
        int $limit,
        string $formatLabel,
        string $recommendedAlternative
    ): void {
        $count = $this->countExportRows($query);

        if ($count <= $limit) {
            return;
        }

        abort(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            sprintf(
                '%s are limited to %s rows. This request matches %s rows. Use %s for very large datasets.',
                $formatLabel,
                number_format($limit),
                number_format($count),
                $recommendedAlternative
            )
        );
    }
}
