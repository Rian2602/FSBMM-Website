<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
