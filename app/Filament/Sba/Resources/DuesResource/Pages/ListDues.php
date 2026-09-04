<?php

namespace App\Filament\Sba\Resources\DuesResource\Pages;

use App\Filament\Sba\Resources\DuesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDues extends ListRecords
{
    protected static string $resource = DuesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
