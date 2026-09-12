<?php

namespace App\Support;

use Filament\Facades\Filament;

trait ResolvesPanelRoutes
{
    /**
     * @param  array<int, int|string>  $parameters
     */
    public function panelRoute(string $name, array $parameters = []): string
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();

        return route('filament.' . $panel->getId() . '.' . $name, $parameters);
    }
}
