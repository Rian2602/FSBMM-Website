<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * The org Select is `dehydrated(false)` for non-sba roles, so on Edit its
     * value never reaches $data and the existing organization_id would survive
     * a demotion. Null it explicitly when the role is no longer SBA.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['role'] ?? null) !== User::ROLE_SBA_ADMIN) {
            $data['organization_id'] = null;
        }

        return $data;
    }
}
