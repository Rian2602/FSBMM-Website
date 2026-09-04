<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->randomElement([
                'Pengantar K3', 'Bentuk Serikat', 'Perundingan Bersama',
                'Hak & Kewajiban', 'Kepemimpinan Anggota',
            ]),
            'content' => '<h2>Materi</h2><p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
        ];
    }
}
