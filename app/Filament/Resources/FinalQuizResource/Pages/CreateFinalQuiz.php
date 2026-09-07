<?php

namespace App\Filament\Resources\FinalQuizResource\Pages;

use App\Filament\Resources\FinalQuizResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinalQuiz extends CreateRecord
{
    protected static string $resource = FinalQuizResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['lesson_id'] = null;

        return $data;
    }
}
