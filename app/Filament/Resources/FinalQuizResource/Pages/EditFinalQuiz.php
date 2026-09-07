<?php

namespace App\Filament\Resources\FinalQuizResource\Pages;

use App\Filament\Resources\FinalQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinalQuiz extends EditRecord
{
    protected static string $resource = FinalQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
