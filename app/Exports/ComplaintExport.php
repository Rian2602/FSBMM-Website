<?php

namespace App\Exports;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;

class ComplaintExport extends ReportExport
{
    protected static function columns(array $filters = []): array
    {
        $columns = [
            'id' => 'ID',
            'title' => 'Judul',
            'status' => 'Status',
            'submitted_at' => 'Tanggal Pengajuan',
            'resolved_at' => 'Tanggal Penyelesaian',
        ];

        if (! empty($filters['include_description'])) {
            $columns['description'] = 'Deskripsi';
        }

        return $columns;
    }

    protected static function filenamePrefix(): string
    {
        return 'pengaduan';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $query = Complaint::query()->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['submitted_start'])) {
            $query->whereDate('submitted_at', '>=', $filters['submitted_start']);
        }
        if (! empty($filters['submitted_end'])) {
            $query->whereDate('submitted_at', '<=', $filters['submitted_end']);
        }

        return $query->orderBy('id');
    }

    protected static function row($model, array $filters = []): array
    {
        $complaint = $model;

        $values = [
            $complaint->id,
            $complaint->title,
            match ($complaint->status) {
                'baru' => 'Baru',
                'diproses' => 'Diproses',
                default => 'Selesai',
            },
            $complaint->submitted_at?->format('Y-m-d'),
            $complaint->resolved_at?->format('Y-m-d') ?? '',
        ];

        if (! empty($filters['include_description'])) {
            $values[] = $complaint->description;
        }

        return $values;
    }
}
