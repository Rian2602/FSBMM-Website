<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use App\Models\Due;
use App\Models\Organization;
use Filament\Widgets\Widget;

class MemberDataOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.member-data-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * Three cheap aggregate queries — render server-side so the numbers are
     * present in the initial /admin HTML (and the no-PII assertion is real).
     */
    protected static bool $isLazy = false;

    /** Federation member data is super-admin aggregate visibility only (spec §5/§8). */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getTotalActiveMembers(): int
    {
        // Never query the members table from the federation side — read the
        // already-synced column (spec §7).
        return (int) Organization::sum('member_count');
    }

    public function getCurrentMonthDuesTotal(): float
    {
        return (float) Due::where('period', now()->format('Y-m'))->sum('amount');
    }

    public function getOpenComplaintsCount(): int
    {
        return Complaint::where('status', '!=', 'selesai')->count();
    }
}
