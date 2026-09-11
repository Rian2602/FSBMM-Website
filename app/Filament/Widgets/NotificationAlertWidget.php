<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Organization;
use Filament\Widgets\Widget;

class NotificationAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.notification-alert';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getAlerts(): array
    {
        $alerts = [];

        // Open complaints — aggregate only (no PII/complaint content leaked)
        $openComplaintsCount = Complaint::where('status', '!=', 'selesai')->count();

        if ($openComplaintsCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
                'title' => 'Pengaduan Terbuka',
                'message' => $openComplaintsCount . ' pengaduan belum diselesaikan',
                'details' => [],
                'url' => '/admin/complaints',
            ];
        }

        // Pending dues (current month)
        $currentMonth = now()->format('Y-m');
        $totalMembers = Organization::sum('member_count');
        $paidMembers = Due::where('period', $currentMonth)
            ->distinct('member_id')
            ->count('member_id');
        $pendingCount = max(0, $totalMembers - $paidMembers);

        if ($pendingCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-banknotes',
                'title' => 'Iuran Belum Tercatat',
                'message' => $pendingCount . ' anggota belum tercatat membayar iuran bulan ini',
                'details' => [],
                'url' => '/admin/dues',
            ];
        }

        // Upcoming events this week
        $upcomingEvents = Event::where('event_date', '>=', now())
            ->where('event_date', '<=', now()->addDays(7))
            ->with('organization')
            ->get();

        if ($upcomingEvents->isNotEmpty()) {
            $alerts[] = [
                'type' => 'success',
                'icon' => 'heroicon-o-calendar',
                'title' => 'Kegiatan Minggu Ini',
                'message' => count($upcomingEvents) . ' kegiatan akan dilaksanakan',
                'details' => $upcomingEvents->take(5)->map(fn ($e) => [
                    'text' => $e->title . ' — ' . $e->event_date->format('d M Y'),
                    'status' => 'upcoming',
                ])->toArray(),
                'url' => '/admin/events',
            ];
        }

        return $alerts;
    }
}
