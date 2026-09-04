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
}
