<?php

namespace App\Filament\Sba\Resources\ComplaintResource\Pages;

use App\Filament\Sba\Resources\ComplaintResource;
use Filament\Resources\Pages\EditRecord;

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

        return $data;
    }
}
