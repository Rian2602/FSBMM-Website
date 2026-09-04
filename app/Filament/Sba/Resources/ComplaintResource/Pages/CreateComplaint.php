<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComplaint extends CreateRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = auth()->user()->organization_id;

        return $data;
    }
}
