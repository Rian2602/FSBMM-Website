<?php

namespace App\Filament\Resources\CourseQuizResource\Pages;

use App\Filament\Resources\CourseQuizResource;
use App\Models\CourseLesson;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseQuiz extends CreateRecord
{
    protected static string $resource = CourseQuizResource::class;

    // (** executed: Filament v3 calls mutateFormDataBeforeCreate() on the
    // PAGE (instance method), not as a static Resource hook — the static
    // version on the Resource was dead code. course_quizzes.course_id is NOT
    // NULL; when the author picks a lesson, the course must come from that
    // lesson so the quiz never lands in a different course than its lesson
    // (same invariant enforced in QuizzesRelationManager). **)
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['lesson_id'] ?? null) !== null) {
            $data['course_id'] = CourseLesson::find($data['lesson_id'])->course_id;
        }

        return $data;
    }
}
