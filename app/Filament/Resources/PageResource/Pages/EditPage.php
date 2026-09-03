<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Same normalization as CreatePage: blocks live in page_blocks rows.
        $blocks = $data['blocks'] ?? [];
        unset($data['blocks']);

        $record = parent::handleRecordUpdate($record, $data);
        $record->syncBlocks($blocks);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
