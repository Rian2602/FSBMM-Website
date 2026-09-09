<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Organization;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FederationOperationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.federation-operations';

    protected int|string|array $columnSpan = 'full';

    // (** executed: SP5 Task 3.1 — server-rendered so totals are in the initial
    // /admin HTML and the no-PII dashboard assertions are real (SP3/SP4
    // widget convention, same as MemberDataOverviewWidget). **)
    protected static bool $isLazy = false;

    // Memoized per render: the member_cards schema check runs once instead of 3×.
    private ?bool $memberCardsTableExists = null;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getMetrics(): array
    {
        return [
            'organizations' => Organization::count(),
            'active_members' => (int) Organization::sum('member_count'),
            'current_dues' => (float) Due::where('period', now()->format('Y-m'))->sum('amount'),
            'events' => Event::count(),
            'attendances_hadir' => Attendance::where('status', 'hadir')->count(),
            'open_complaints' => Complaint::where('status', '!=', 'selesai')->count(),
            // ponytail: member_cards arrives in Phase 5 (Task 5.1/5.2); the
            // hasTable guard keeps the 8-metric surface rendering 0 until then.
            // Replace these DB::table queries with MemberCard::where(status, ...)
            // and delete hasMemberCards() when Task 5.2 lands.
            'active_cards' => $this->hasMemberCards() ? (int) DB::table('member_cards')->where('status', 'aktif')->count() : 0,
            'revoked_cards' => $this->hasMemberCards() ? (int) DB::table('member_cards')->where('status', 'dicabut')->count() : 0,
        ];
    }

    public function getPerSbaBreakdown(): array
    {
        $duesByOrg = Due::where('period', now()->format('Y-m'))
            ->selectRaw('organization_id, sum(amount) as total')
            ->groupBy('organization_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (float) $row->total]);

        $eventsByOrg = Event::selectRaw('organization_id, count(*) as c')
            ->groupBy('organization_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c]);

        $openComplaintsByOrg = Complaint::where('status', '!=', 'selesai')
            ->selectRaw('organization_id, count(*) as c')
            ->groupBy('organization_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c]);

        $activeCardsByOrg = $this->hasMemberCards()
            ? DB::table('member_cards')->where('status', 'aktif')
                ->selectRaw('organization_id, count(*) as c')
                ->groupBy('organization_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (int) $row->c])
            : collect();

        return Organization::orderBy('name')
            ->get()
            ->map(fn (Organization $org) => [
                'name' => $org->name,
                'active_members' => (int) $org->member_count,
                'current_dues' => (float) ($duesByOrg[$org->id] ?? 0),
                'events' => (int) ($eventsByOrg[$org->id] ?? 0),
                'open_complaints' => (int) ($openComplaintsByOrg[$org->id] ?? 0),
                'active_cards' => (int) ($activeCardsByOrg[$org->id] ?? 0),
            ])
            ->all();
    }

    private function hasMemberCards(): bool
    {
        return $this->memberCardsTableExists ??= Schema::hasTable('member_cards');
    }
}
