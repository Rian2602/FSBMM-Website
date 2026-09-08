<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Per-user e-learning progress: manual lesson completion, quiz-derived
 * lesson status, derived course completion, per-user/per-course views for
 * the learning pages, and the federation report (Task 8).
 */
class LearningProgress
{
    /** Set (or clear) a lesson's manual completion for a user. */
    public function setLessonCompleted(User $user, Course $course, CourseLesson $lesson, bool $completed = true): void
    {
        CourseProgress::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id],
            ['is_completed' => $completed, 'completed_at' => $completed ? now() : null]
        );
    }

    /** Lesson complete if a passed-flagged progress row exists or its quiz was passed. */
    public function lessonStatus(User $user, Course $course, CourseLesson $lesson): bool
    {
        $row = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($row) {
            return (bool) $row->is_completed;
        }

        // A quiz-bearing lesson is complete iff any attempt passed.
        foreach ($lesson->quizzes as $quiz) {
            if ($quiz->attempts()->where('user_id', $user->id)->where('passed', true)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** True when every lesson is complete and the final quiz was ever passed. */
    public function isCourseComplete(User $user, Course $course): bool
    {
        foreach ($course->lessons as $lesson) {
            if (! $this->lessonStatus($user, $course, $lesson)) {
                return false;
            }
        }

        $finalQuiz = $course->finalQuiz();

        return $finalQuiz !== null
            && $finalQuiz->attempts()->where('user_id', $user->id)->where('passed', true)->exists();
    }

    /** Published courses with the given user's per-course progress. */
    public function forUser(User $user): Collection // (** executed: map() yields an Illuminate\Support\Collection, not Eloquent. **)
    {
        // (** executed: the plan's with(['lessons.quizzes', 'finalQuiz']) is
        // invalid — `finalQuiz` is a method returning ?CourseQuiz, not a
        // relation, and eager-loading it crashes (Task 5 evaluation finding).
        // Access it via $course->finalQuiz() instead. **)
        return Course::published()->with('lessons.quizzes')
            ->get()
            ->map(fn (Course $course) => [
                'course' => $course,
                'is_complete' => $this->isCourseComplete($user, $course),
                'lessons_done' => $course->lessons->filter(fn ($l) => $this->lessonStatus($user, $course, $l))->count(),
                'lessons_total' => $course->lessons->count(),
            ])
            ->values();
    }

    /** Per-lesson + final-quiz status map for one course/user (course detail page). */
    public function forCourse(User $user, Course $course): array
    {
        return [
            'course' => $course,
            'is_complete' => $this->isCourseComplete($user, $course),
            'lessons' => $course->lessons->map(fn ($lesson) => [
                'lesson' => $lesson,
                'done' => $this->lessonStatus($user, $course, $lesson),
            ])->values(),
            'final_quiz' => $course->finalQuiz(),
            'final_quiz_passed' => $course->finalQuiz()
                ? $course->finalQuiz()->attempts()->where('user_id', $user->id)->where('passed', true)->exists()
                : false,
        ];
    }

    /** Course × user status summary for the federation report. */
    public function report(): Collection // (** executed: map() yields an Illuminate\Support\Collection, not Eloquent. **)
    {
        // (** executed: same finalQuiz eager-load fix as forUser. **)
        $courses = Course::published()->with('lessons')->get();
        $users = User::orderBy('name')->get();

        return $courses->map(fn (Course $course) => [
            'course' => $course,
            'rows' => $users->map(fn (User $user) => [
                'user' => $user,
                'is_complete' => $this->isCourseComplete($user, $course),
            ])->values(),
        ])->values();
    }
}
