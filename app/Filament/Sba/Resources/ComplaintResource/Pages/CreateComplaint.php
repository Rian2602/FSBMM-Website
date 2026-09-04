<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use App\Models\Member;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateComplaint extends CreateRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = auth()->user()->organization_id;

        if (! empty($data['member_id'])) {
            if (! Member::where('id', $data['member_id'])
                ->where('organization_id', $data['organization_id'])->exists()) {
                throw ValidationException::withMessages([
                    'data.member_id' => 'Anggota tidak terkait dengan organisasi ini.',
                ]);
            }
        }

        return $data;
    }
}
