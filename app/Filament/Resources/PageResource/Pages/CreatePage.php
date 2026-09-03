<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // The 'blocks' Builder field is not a Page column; normalize it into
        // page_blocks rows ({type, data} per Builder item).
        $blocks = $data['blocks'] ?? [];
        unset($data['blocks']);

        $record = parent::handleRecordCreation($data);
        $record->syncBlocks($blocks);

        return $record;
    }
}
