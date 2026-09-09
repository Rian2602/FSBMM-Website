<?php

namespace App\Exports;

use App\Models\Due;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DuesExport
{
    public const COLUMNS = [
        'member.name' => 'Nama Anggota',
        'period' => 'Periode',
        'amount' => 'Nominal',
        'paid_at' => 'Tanggal Pembayaran',
        'recordedBy.name' => 'Pencatat',
    ];

    public static function streamFor(int $organizationId, array $filters, string $format = 'csv'): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = sys_get_temp_dir().'/fsbmm_'.Str::random(8).'.'.$format;
        $query = static::scopedQuery($organizationId, $filters);

        $format === 'xlsx' ? static::writeXlsx($query, $path) : static::writeCsv($query, $path);

        return response()
            ->download($path, sprintf('iuran-%s-%s.%s', $organizationId, now()->format('Y-m-d'), $format))
            ->deleteFileAfterSend(true);
    }

    private static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Due::query()->with('member', 'recordedBy')->where('organization_id', $organizationId);

        if (! empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }
        if (! empty($filters['period_start'])) {
            $query->where('period', '>=', $filters['period_start']);
        }
        if (! empty($filters['period_end'])) {
            $query->where('period', '<=', $filters['period_end']);
        }

        return $query->orderBy('id');
    }

    private static function writeCsv(Builder $query, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_values(self::COLUMNS));
        static::rows($query)->each(function (Due $due) use ($handle): void {
            fputcsv($handle, static::row($due));
        });
        fclose($handle);
    }

    private static function writeXlsx(Builder $query, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS)));
        static::rows($query)->each(function (Due $due) use ($writer): void {
            $writer->addRow(Row::fromValues(static::row($due)));
        });
        $writer->close();
    }

    private static function rows(Builder $query): LazyCollection
    {
        return $query->cursor();
    }

    private static function row(Due $due): array
    {
        return [
            $due->member?->name,
            $due->period,
            $due->amount,
            $due->paid_at?->format('Y-m-d'),
            $due->recordedBy?->name ?? '',
        ];
    }
}
