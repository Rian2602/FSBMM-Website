<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use App\Models\Member;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditComplaint extends EditRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if (isset($data['status']) && $data['status'] !== 'baru') {
            $data['handled_by'] = $user->id;
        }

        if (isset($data['status']) && $data['status'] === 'selesai') {
            $data['resolved_at'] = now();
        }

        if (! empty($data['member_id'])) {
            if (! Member::where('id', $data['member_id'])
                ->where('organization_id', $this->record->organization_id)->exists()) {
                throw ValidationException::withMessages([
                    'member_id' => 'Anggota tidak terkait dengan organisasi ini.',
                ]);
            }
        }

        return $data;
    }
}
