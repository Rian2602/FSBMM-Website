<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

class AttendanceExport extends ReportExport
{
    protected static function columns(array $filters = []): array
    {
        return [
            'member.name' => 'Nama Anggota',
            'event.title' => 'Kegiatan',
            'event.event_date' => 'Tanggal',
            'status' => 'Status',
            'note' => 'Catatan',
        ];
    }

    protected static function filenamePrefix(): string
    {
        return 'kehadiran';
    }

    protected static function scopedQuery(int $organizationId, array $filters): Builder
    {
        $eventQuery = Event::query()->where('events.organization_id', $organizationId);
        if (! empty($filters['event_id'])) {
            $eventQuery->whereKey($filters['event_id']);
        }
        if (! empty($filters['event_date_start'])) {
            $eventQuery->whereDate('events.event_date', '>=', $filters['event_date_start']);
        }
        if (! empty($filters['event_date_end'])) {
            $eventQuery->whereDate('events.event_date', '<=', $filters['event_date_end']);
        }

        return Attendance::query()
            ->with('member', 'event')
            ->where('attendances.organization_id', $organizationId)
            ->whereIn('attendances.event_id', $eventQuery->select('id'))
            ->orderBy('id');
    }

    protected static function row($model, array $filters = []): array
    {
        $attendance = $model;

        return [
            $attendance->member?->name,
            $attendance->event?->title,
            $attendance->event?->event_date?->format('Y-m-d'),
            match ($attendance->status) {
                'hadir' => 'Hadir',
                'izin' => 'Izin',
                default => 'Tidak Hadir',
            },
            $attendance->note ?? '',
        ];
    }
}
