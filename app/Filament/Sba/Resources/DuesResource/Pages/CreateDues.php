<?php

namespace App\Filament\Sba\Resources\DuesResource\Pages;

use App\Filament\Sba\Resources\DuesResource;
use App\Models\Due;
use App\Models\Member;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateDues extends CreateRecord
{
    protected static string $resource = DuesResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = auth()->user()->organization_id;
        $data['recorded_by'] = auth()->user()->id;

        // Belt-and-suspenders: member must belong to this org (UI scopes the Select,
        // but a direct HTTP request could bypass the dropdown).
        if (! Member::where('id', $data['member_id'])
            ->where('organization_id', $data['organization_id'])->exists()) {
            throw ValidationException::withMessages([
                'data.member_id' => 'Anggota tidak terkait dengan organisasi ini.',
            ]);
        }

        // Composite unique (member_id, period) — surfaces as form error, not 500.
        if (Due::where('member_id', $data['member_id'])
            ->where('period', $data['period'])->exists()) {
            throw ValidationException::withMessages([
                'data.member_id' => 'Anggota ini sudah memiliki iuran untuk periode ini.',
            ]);
        }

        return $data;
    }
}
