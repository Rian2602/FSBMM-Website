<?php

namespace App\Exports;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

abstract class ReportExport
{
    /** @return array<string, string> key => public label */
    abstract protected static function columns(array $filters = []): array;

    abstract protected static function filenamePrefix(): string;

    abstract protected static function scopedQuery(int $organizationId, array $filters): Builder;

    abstract protected static function row($model, array $filters = []): array;

    public static function generate(int $organizationId, array $filters, string $format = 'csv'): string
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $name = sprintf('%s-%s-%s.%s', static::filenamePrefix(), $organizationId, now()->format('Y-m-d'), $format);
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        static::sweepExpired($disk); // ponytail: lazy sweep per generate; hourly-command only if files accumulate
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx'
            ? static::writeXlsx($query, $disk->path('exports/'.$name), $filters)
            : static::writeCsv($query, $disk->path('exports/'.$name), $filters);

        // PII-free audit entry: only the dataset + format are recorded, never
        // the exported member rows.
        app(AuditLogger::class)->record(
            'export.generated',
            sprintf('Ekspor %s dibuat (%s)', static::filenamePrefix(), strtoupper($format)),
            null,
            $organizationId,
        );

        return URL::temporarySignedRoute('exports.download', now()->addHour(), ['file' => 'exports/'.$name, 'org' => $organizationId]);
    }

    private static function sweepExpired($disk): void
    {
        foreach ($disk->files('exports') as $file) {
            if ($disk->lastModified($file) < now()->subHour()->timestamp) {
                $disk->delete($file);
            }
        }
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
