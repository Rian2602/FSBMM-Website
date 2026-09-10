<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class ReportExport
{
    /** @return array<string, string> key => public label */
    abstract protected static function columns(array $filters = []): array;

    abstract protected static function filenamePrefix(): string;

    abstract protected static function scopedQuery(int $organizationId, array $filters): Builder;

    abstract protected static function row($model, array $filters = []): array;

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path, $filters) : static::writeCsv($query, $path, $filters);

        return response()
            ->download($path, sprintf('%s-%s-%s.%s', static::filenamePrefix(), $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function writeCsv(Builder $query, string $path, array $filters): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(static::columns($filters)));
        $query->cursor()->each(function ($model) use ($handle, $filters): void {
            fputcsv($handle, static::row($model, $filters));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path, array $filters): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(static::columns($filters))));
        $query->cursor()->each(function ($model) use ($writer, $filters): void {
            $writer->addRow(Row::fromValues(static::row($model, $filters)));
        });
        $writer->close();
    }
}
