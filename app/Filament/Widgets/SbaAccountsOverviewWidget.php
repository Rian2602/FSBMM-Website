<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class SbaAccountsOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.sba-accounts-overview';

    protected int|string|array $columnSpan = 'full';

    /** Federation overview is a super-admin view; editors stay out. */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getTotalAccounts(): int
    {
        return User::where('role', User::ROLE_SBA_ADMIN)->count();
    }

    public function getTotalOrganizations(): int
    {
        return Organization::count();
    }

    public function getPublishedOrganizations(): int
    {
        return Organization::published()->count();
    }

    public function getAccounts(): Collection
    {
        return User::where('role', User::ROLE_SBA_ADMIN)
            ->with('organization')
            ->orderBy('name')
            ->get();
    }
}
