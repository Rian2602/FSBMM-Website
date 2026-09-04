<?php

namespace App\Filament\Sba\Widgets;

use App\Models\Organization;
use Filament\Widgets\Widget;

class OrganizationSummaryWidget extends Widget
{
    protected static string $view = 'filament.widgets.organization-summary';

    protected int|string|array $columnSpan = 'full';

    public function getOrganization(): ?Organization
    {
        return auth()->user()?->organization;
    }

    /** @return array<string, int|float> */
    public function stats(): array
    {
        $organization = $this->getOrganization();

        if (! $organization) {
            return [
                'active_members' => 0,
                'current_month_dues' => 0,
                'open_complaints' => 0,
            ];
        }

        return [
            'active_members' => (int) $organization->member_count,
            'current_month_dues' => (float) $organization->dues()
                ->where('period', now()->format('Y-m'))
                ->sum('amount'),
            'open_complaints' => $organization->complaints()
                ->where('status', '!=', 'selesai')
                ->count(),
        ];
    }
}
