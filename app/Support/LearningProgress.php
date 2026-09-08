<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Per-user e-learning progress queries shared by the learning pages in both
 * panels (Task 5). Mutation methods (set/undo completion, course-complete
 * rule) are added in Task 7.
 */
class LearningProgress
{
    public function forUser(?User $user = null): Collection
    {
        $userId = ($user ?? auth()->user())?->id;

        return CourseProgress::where('user_id', $userId)->get();
    }

    /** Completed progress rows for a course (optionally a given user). */
    public function forCourse(Course $course, ?User $user = null): Collection
    {
        $userId = ($user ?? auth()->user())?->id;

        return CourseProgress::where('course_id', $course->id)
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->get();
    }

    public function isLessonComplete(Course $course, CourseLesson $lesson, ?User $user = null): bool
    {
        $userId = ($user ?? auth()->user())?->id;
        if ($userId === null) {
            return false;
        }

        return CourseProgress::where('course_id', $course->id)
            ->where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->where('is_completed', true)
            ->exists();
    }
}
