<?php

namespace App\Filament\Resources\EresourceResource\Pages;

use App\Filament\Resources\EresourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEresource extends EditRecord
{
    protected static string $resource = EresourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
