<?php

namespace App\Exports;

use App\Models\Due;
use Illuminate\Database\Eloquent\Builder;

class DuesExport extends ReportExport
{
    protected static function columns(array $filters = []): array
    {
        return [
            'member.name' => 'Nama Anggota',
            'period' => 'Periode',
            'amount' => 'Nominal',
            'paid_at' => 'Tanggal Pembayaran',
            'recordedBy.name' => 'Pencatat',
        ];
    }

    protected static function filenamePrefix(): string
    {
        return 'iuran';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
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

    protected static function row($model, array $filters = []): array
    {
        $due = $model;

        return [
            $due->member?->name,
            $due->period,
            $due->amount,
            $due->paid_at?->format('Y-m-d'),
            $due->recordedBy?->name ?? '',
        ];
    }
}
