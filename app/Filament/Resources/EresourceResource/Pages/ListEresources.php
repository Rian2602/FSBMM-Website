<?php

namespace App\Filament\Resources\EresourceResource\Pages;

use App\Filament\Resources\EresourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEresources extends ListRecords
{
    protected static string $resource = EresourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
