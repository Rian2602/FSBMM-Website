<?php

namespace App\Filament\Sba\Resources\MemberResource\Pages;

use App\Filament\Sba\Resources\MemberResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Filament::auth()->user()->organization_id;

        return $data;
    }
}
