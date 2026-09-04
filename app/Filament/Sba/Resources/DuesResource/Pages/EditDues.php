<?php

namespace App\Filament\Sba\Resources\DuesResource\Pages;

use App\Filament\Sba\Resources\DuesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDues extends EditRecord
{
    protected static string $resource = DuesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
