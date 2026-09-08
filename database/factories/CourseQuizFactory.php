<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseQuiz>
 */
class CourseQuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            // lesson_id MUST precede course_id: Factory::expandAttributes()
            // evaluates attributes in array order, so the course_id closure can
            // read the already-resolved lesson id.
            'lesson_id' => CourseLesson::factory(),
            // (** executed: derive course_id from the lesson so a bare create()
            // stays internally consistent; explicit ->for($course) overrides.
            // The plan's definition used two independent factories, producing
            // quizzes whose course_id and lesson_id belong to different
            // courses. **)
            // (** executed: footgun — `for($course)->create()` alone does NOT
            // hit this closure: the state overrides course_id while lesson_id
            // stays the definition's fresh lesson, so the quiz links to a new
            // lesson from another course. Callers wanting a course-level quiz
            // MUST also pass ['lesson_id' => null]; callers wanting a lesson
            // quiz pass ->for($lesson, 'lesson'). **)
            'course_id' => function (array $attributes) {
                $lessonId = $attributes['lesson_id'] ?? null;

                return $lessonId !== null
                    ? CourseLesson::find($lessonId)?->course_id ?? Course::factory()
                    : Course::factory();
            },
            'title' => fake()->randomElement(['Kuis 1', 'Kuis 2', 'Evaluasi Akhir']),
            'pass_threshold' => 70,
        ];
    }
}
