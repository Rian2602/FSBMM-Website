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

    private function hasMemberCards(): bool
    {
        return Schema::hasTable('member_cards');
    }
}
