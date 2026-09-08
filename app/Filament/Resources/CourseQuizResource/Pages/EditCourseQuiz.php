<?php

namespace App\Filament\Resources\CourseQuizResource\Pages;

use App\Filament\Resources\CourseQuizResource;
use App\Models\CourseLesson;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourseQuiz extends EditRecord
{
    protected static string $resource = CourseQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // (** executed: CourseQuizResource disables course_id on edit but leaves
    // lesson_id editable — without this a quiz could be moved to a lesson of
    // another course, breaking the create invariant (course from lesson) that
    // CreateCourseQuiz::mutateFormDataBeforeCreate enforces (Task 3
    // evaluation, C-T3-2). course_id is absent from the save data because the
    // field is disabled, so it must be re-derived here. **)
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['lesson_id'] ?? null) !== null) {
            $data['course_id'] = CourseLesson::find($data['lesson_id'])->course_id;
        }

        return $data;
    }
}
