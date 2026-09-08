# FSBMM Website — SP4 Implementation Plan (Authoring & Progres E-Learning)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add federation-authored e-learning (courses with lessons/quizzes) plus per-user progress tracking and a federation learning report, without any new panel or login.

**Architecture:** Same one Laravel 12 app, same two Filament panels (`admin`, `sba`). Authoring lives only in `/admin` as a resource + RelationManager chain (Course → Lesson → Quiz → Question → Option, plus a final quiz at the course level). Learning is custom Filament pages in both panels ("Kursus Saya"), reading only published courses. Progress is stored per `user_id`; course completion is derived (all lessons complete + final quiz passed), never stored as a column. A super-admin-only widget on `/admin` reports course × user status.

**Tech Stack:** PHP 8.3+, Laravel 12, Filament 3 (both panels, unchanged versions), SQLite `:memory:` tests, PHPUnit feature tests, Pint.

**Spec:** `docs/superpowers/specs/2026-09-04-fsbmm-website-sp4-elearning-authoring.md` (same repo). SP1/SP2/SP3 specs+plans: read them, **never edit them**. Record executed deviations in *this* plan as inline `(** executed: ... **)` annotations — never rewrite SP1/SP2/SP3 history.

## Global Constraints

- Working directory: repo root. SP1–SP3 are committed and green; only **additive** changes (new tables, new column via new migration, new resources, new pages, new widget). Do not rewrite existing migrations/models/columns.
- UI copy and Filament labels in **Bahasa Indonesia**. Code identifiers, migration/class names, and commit messages in English (repo convention, unchanged since SP1).
- **No new panel, no new login.** Learners are the existing `users` table: `super_admin`/`editor` in `/admin`, `sba_admin` in `/panel-sba`. No self-registration, no public learning route.
- **Authoring = federation only** (`super_admin`/`editor`, all in `/admin`). No tenant dimension in course content; `sba_admin` never sees authoring resources.
- Course content (`course_lessons.content`) is **trusted HTML** rendered raw — authored only by staff via RichEditor (same policy as articles/pages; AGENTS.md: "Escape all non-staff input; only these fields may bypass escaping").
- **Quiz scoring is server-side**: the learning page compares submitted answers to `quiz_options.is_correct` in the DB; clients never receive the correct-answer flag.
- **Progress is per `user_id`** (not per organization): each person has personal progress whether they are federation staff or an SBA admin.
- `sba_admin` sees only published courses (`Course::published()`); federation staff may also only be offered published courses in the learning area.
- Federation report (`LearningReportWidget`) is `super_admin` only via `canView()` (mirror `SbaAccountsOverviewWidget` SP2). This data is **not** member PII, so it is allowed in `/admin`.
- Commit after every task's green test run. Run `vendor/bin/pint` before committing. Whole existing SP1+SP2+SP3 suite must stay green.

## File Structure (locked in here)

```
database/migrations/2026_09_04_000014_create_course_lessons_table.php
database/migrations/2026_09_04_000015_create_course_quizzes_table.php
database/migrations/2026_09_04_000016_create_quiz_questions_table.php
database/migrations/2026_09_04_000017_create_quiz_options_table.php
database/migrations/2026_09_04_000018_create_course_progress_table.php
database/migrations/2026_09_04_000019_create_course_attempts_table.php
database/migrations/2026_09_04_000020_add_pass_threshold_to_courses_table.php
app/Models/CourseLesson.php
app/Models/CourseQuiz.php
app/Models/QuizQuestion.php
app/Models/QuizOption.php
app/Models/CourseProgress.php
app/Models/CourseAttempt.php
app/Models/Course.php                     (+ pass_threshold fillable/cast, lessons(), quizzes(), participants())
app/Models/User.php                       (+ progress(), attempts(), coursesTaken() if needed)
app/Support/LearningProgress.php          (course-completion helpers for learner + report)
app/Filament/Resources/CourseLessonResource.php + Pages/{ListCourseLessons,CreateCourseLesson,EditCourseLesson}.php
app/Filament/Resources/CourseLessonResource/RelationManagers/QuizzesRelationManager.php
app/Filament/Resources/CourseQuizResource.php + Pages
app/Filament/Resources/CourseQuizResource/RelationManagers/QuestionsRelationManager.php
app/Filament/Resources/QuizQuestionResource.php + Pages
app/Filament/Resources/QuizQuestionResource/RelationManagers/OptionsRelationManager.php
app/Filament/Resources/FinalQuizResource.php + Pages
app/Filament/Resources/FinalQuizResource/RelationManagers/QuestionsRelationManager.php
app/Filament/Resources/CourseResource.php   (+ pass_threshold field, LessonsRelationManager + FinalQuizRelationManager)
app/Filament/Resources/CourseResource/RelationManagers/LessonsRelationManager.php
app/Filament/Resources/CourseResource/RelationManagers/FinalQuizRelationManager.php
app/Filament/Admin/Pages/MyCoursesPage.php
app/Filament/Admin/Pages/CourseDetailPage.php
app/Filament/Admin/Pages/LessonViewPage.php
app/Filament/Admin/Pages/QuizViewPage.php
app/Filament/Sba/Pages/MyCoursesPage.php
app/Filament/Sba/Pages/CourseDetailPage.php
app/Filament/Sba/Pages/LessonViewPage.php
app/Filament/Sba/Pages/QuizViewPage.php
app/Filament/Widgets/LearningReportWidget.php
resources/views/filament/widgets/learning-report.blade.php
database/factories/CourseLessonFactory.php
database/factories/CourseQuizFactory.php
database/factories/QuizQuestionFactory.php
database/factories/QuizOptionFactory.php
database/seeders/CourseContentSeeder.php  (+ DatabaseSeeder wiring)
tests/Feature/CourseAuthoringTest.php
tests/Feature/QuizEngineTest.php
tests/Feature/CourseProgressTest.php
tests/Feature/LearnerAccessTest.php
tests/Feature/LearningReportTest.php
```

The two `QuizViewPage` classes (Admin + Sba) share a single responsible core so the scoring/attempt/progress logic lives in one place: a small `App\Support\QuizEngine` service (or a private method duplicated in a shared trait). Use a **trait** `App\Filament\Support\InteractsWithQuiz` shared by both page classes, or a plain service `App\Support\QuizEngine`. Prefer the service (one `submit()` method scoped by nothing — it takes the quiz, the user, and the answers array, and returns a result DTO). Both page classes are thin Livewire views calling it.

---

### Task 1: Migrations + models + relations + factories + `pass_threshold`

**Files:**
- Create: the 7 migrations, `app/Models/{CourseLesson,CourseQuiz,QuizQuestion,QuizOption,CourseProgress,CourseAttempt}.php`, `database/factories/{CourseLesson,CourseQuiz,QuizQuestion,QuizOption}Factory.php`, `tests/Feature/QuizEngineTest.php` (partial)
- Modify: `app/Models/Course.php`, `app/Models/User.php`

**Interfaces:**
- Produces: tables `course_lessons`, `course_quizzes`, `quiz_questions`, `quiz_options`, `course_progress`, `course_attempts`; `courses.pass_threshold`; models with relations; `CourseQuiz::score(array $answers): int` (0–100) and `CourseQuiz::passThreshold()`.
- Consumes: existing `Course` (SP1) and `User` (SP1/SP2).

- [ ] **Step 1: Write the failing test `tests/Feature/QuizEngineTest.php`** (scoring + threshold are the branchiest logic; drive them first):

```php
<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizEngineTest extends TestCase
{
    use RefreshDatabase;

    private function quizWithThreshold(?int $threshold = null): CourseQuiz
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson)->create(['pass_threshold' => $threshold]);

        $q1 = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($q1)->count(3)->create(['is_correct' => false]);
        QuizOption::factory()->for($q1)->create(['is_correct' => true]); // 1 of 4 correct

        $q2 = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($q2)->count(2)->create(['is_correct' => false]);
        QuizOption::factory()->for($q2)->create(['is_correct' => true]); // 1 of 3 correct

        return $quiz;
    }
(** executed: the shipped helper differs from the snippet: `->for($lesson, 'lesson')`
and `->for($quiz, 'quiz')` / `->for($q, 'question')` pass the relationship name
explicitly (`Factory::for()` infers from the class name, but the relations are
`lesson`, `quiz`, `question`), and `pass_threshold` is only set when non-null —
the column is NOT NULL, so an explicit null insert fails. The shipped file also
gains Task-6-only submit tests (`test_submit_...`, `test_retake_...`) that
depend on `App\Support\QuizEngine`. **)

    public function test_score_is_100_when_all_answers_correct(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->firstWhere('is_correct')->id,
        ];

        $this->assertSame(100, $quiz->score($answers));
    }

    public function test_score_is_0_when_all_answers_wrong(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->where('is_correct', false)->first()->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id,
        ];

        $this->assertSame(0, $quiz->score($answers));
    }

    public function test_score_counts_partial_correct(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id, // right
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id, // wrong
        ];

        $this->assertSame(50, $quiz->score($answers));
    }

    public function test_passes_when_score_meets_threshold(): void
    {
        $quiz = $this->quizWithThreshold(50);
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id,
        ];

        $this->assertTrue($quiz->score($answers) >= $quiz->passThreshold());
    }

    public function test_per_quiz_threshold_overrides_course_default(): void
    {
        $course = Course::factory()->create(['pass_threshold' => 70]);
        // quiz with no explicit threshold → uses course default
        $defaultQuiz = CourseQuiz::factory()->for($course)->create();
        $this->assertSame(70, $defaultQuiz->passThreshold());

        // quiz with explicit threshold → wins
        $explicit = CourseQuiz::factory()->for($course)->create(['pass_threshold' => 60]);
        $this->assertSame(60, $explicit->passThreshold());
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter QuizEngineTest`
Expected: FAIL (no models/tables → method calls error).

- [ ] **Step 3: Migrations**

Run:
```bash
php artisan make:migration create_course_lessons_table
php artisan make:migration create_course_quizzes_table
php artisan make:migration create_quiz_questions_table
php artisan make:migration create_quiz_options_table
php artisan make:migration create_course_progress_table
php artisan make:migration create_course_attempts_table
php artisan make:migration add_pass_threshold_to_courses_table
```

Fill each migration (rename the generated files to the locked timestamps in File Structure, or accept the generated timestamp and keep the filenames consistent — repo convention uses descriptive timestamps; rename to `2026_09_04_0000NN_*` as shown):

```php
// ..._create_course_lessons_table.php
Schema::create('course_lessons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->longText('content');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index(['course_id', 'sort_order']);
});
```

```php
// ..._create_course_quizzes_table.php
Schema::create('course_quizzes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->cascadeOnDelete();
    $table->string('title');
    $table->unsignedTinyInteger('pass_threshold')->default(70);
    $table->timestamps();
    $table->index('course_id');
});
```
(** executed: the column was created `nullable()` so an empty authoring value
stores NULL and `CourseQuiz::passThreshold()` falls back to the course default.
The plan snippet omitted `->nullable()`. **)

```php
// ..._create_quiz_questions_table.php
Schema::create('quiz_questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_quiz_id')->constrained()->cascadeOnDelete();
    $table->text('question');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index(['course_quiz_id', 'sort_order']);
});
```

```php
// ..._create_quiz_options_table.php
Schema::create('quiz_options', function (Blueprint $table) {
    $table->id();
    $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
    $table->text('option');
    $table->boolean('is_correct')->default(false);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index(['quiz_question_id', 'sort_order']);
});
```

```php
// ..._create_course_progress_table.php
Schema::create('course_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->cascadeOnDelete();
    $table->boolean('is_completed')->default(false);
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'course_id', 'lesson_id']);
});
```

```php
// ..._create_course_attempts_table.php
Schema::create('course_attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_quiz_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('score');
    $table->boolean('passed');
    $table->timestamp('attempt_date');
    $table->timestamps();
    $table->index(['user_id', 'course_quiz_id']);
});
```

```php
// ..._add_pass_threshold_to_courses_table.php
Schema::table('courses', function (Blueprint $table) {
    $table->unsignedTinyInteger('pass_threshold')->default(70)->after('is_published');
});
```

- [ ] **Step 4: Models**

```php
<?php
// app/Models/CourseLesson.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'content', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function quizzes() { return $this->hasMany(CourseQuiz::class); }
}
```
(** executed: the shipped relation is `hasMany(CourseQuiz::class, 'lesson_id')`
— the class-name inference (`course_lesson_id`) doesn't match the actual FK
column and breaks any count/relation usage. **)

```php
<?php
// app/Models/CourseQuiz.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CourseQuiz extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'lesson_id', 'title', 'pass_threshold'];

    protected function casts(): array
    {
        return ['pass_threshold' => 'integer', 'lesson_id' => 'integer'];
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function lesson() { return $this->belongsTo(CourseLesson::class); }
    public function questions() { return $this->hasMany(QuizQuestion::class)->orderBy('sort_order'); }
    public function attempts() { return $this->hasMany(CourseAttempt::class); }

    /** Whether this is the course's final quiz (no owning lesson). */
    public function isFinal(): bool { return $this->lesson_id === null; }

    /** The effective pass threshold (per-quiz value overrides course default). */
    public function passThreshold(): int
    {
        return $this->pass_threshold ?? $this->course->pass_threshold ?? 70;
    }

    /**
     * Server-side score (0–100) for a submitted MCQ answer set
     * keyed [question_id => option_id].
     *
     * @param array<int,int> $answers
     */
    public function score(array $answers): int
    {
        $questions = $this->questions()->with('options')->get();
        if ($questions->isEmpty()) {
            return 0;
        }

        $correct = 0;
        foreach ($questions as $question) {
            $right = $question->options->firstWhere('is_correct', true)?->id;
            if ($right !== null && ($answers[$question->id] ?? null) === $right) {
                $correct++;
            }
        }

        return (int) round(($correct / $questions->count()) * 100);
    }
}
```
(** executed: `score()` compares answer ids with strict `===`, but live form
answers arrive as strings (`value="{{ $option->id }}"` radio + `wire:model`),
so every submission scored 0 and no quiz could be passed. Fixed by casting both
sides to int at this trust boundary (`(int) $chosen === (int) $right`), proven
by `test_score_tolerates_string_option_ids_from_form_input` added to
`QuizEngineTest`. **)

```php
<?php
// app/Models/QuizQuestion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['course_quiz_id', 'question', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function quiz() { return $this->belongsTo(CourseQuiz::class); }
    public function options() { return $this->hasMany(QuizOption::class)->orderBy('sort_order'); }
}
```

```php
<?php
// app/Models/QuizOption.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizOption extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_question_id', 'option', 'is_correct', 'sort_order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'sort_order' => 'integer'];
    }

    public function question() { return $this->belongsTo(QuizQuestion::class); }
}
```

```php
<?php
// app/Models/CourseProgress.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseProgress extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'user_id', 'lesson_id', 'is_completed', 'completed_at'];

    protected function casts(): array
    {
        return ['is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function lesson() { return $this->belongsTo(CourseLesson::class); }
}
```

```php
<?php
// app/Models/CourseAttempt.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['course_quiz_id', 'user_id', 'score', 'passed', 'attempt_date'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'passed' => 'boolean', 'attempt_date' => 'datetime'];
    }

    public function quiz() { return $this->belongsTo(CourseQuiz::class); }
    public function user() { return $this->belongsTo(User::class); }
}
```

- [ ] **Step 5: `Course.php` + `User.php` additions**

Add to `app/Models/Course.php`: extend `$fillable` with `'pass_threshold'`, cast it, add `lessons()`, `quizzes()`, `finalQuiz()`.

```php
// inside class Course
protected $fillable = ['title', 'slug', 'description', 'level', 'is_published', 'pass_threshold'];

protected function casts(): array
{
    return [
        'is_published' => 'boolean',
        'pass_threshold' => 'integer',
    ];
}

public function lessons() { return $this->hasMany(CourseLesson::class)->orderBy('sort_order'); }
public function quizzes() { return $this->hasMany(CourseQuiz::class); }

/** The course's final quiz (lesson_id null), if any. */
public function finalQuiz(): ?CourseQuiz
{
    return $this->quizzes()->whereNull('lesson_id')->first();
}
```

Add to `app/Models/User.php`:

```php
public function progress() { return $this->hasMany(CourseProgress::class); }
public function attempts() { return $this->hasMany(CourseAttempt::class); }
```

- [ ] **Step 6: Factories**

```php
<?php
// database/factories/CourseLessonFactory.php
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
```

```php
<?php
// database/factories/CourseQuizFactory.php
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
            'course_id' => Course::factory(),
            'lesson_id' => CourseLesson::factory(),
            'title' => fake()->randomElement(['Kuis 1', 'Kuis 2', 'Evaluasi Akhir']),
            'pass_threshold' => 70,
        ];
    }
}
```

```php
<?php
// database/factories/QuizQuestionFactory.php
namespace Database\Factories;

use App\Models\CourseQuiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_quiz_id' => CourseQuiz::factory(),
            'question' => fake()->sentence().'?',
            'sort_order' => 0,
        ];
    }
}
```

```php
<?php
// database/factories/QuizOptionFactory.php
namespace Database\Factories;

use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizOption>
 */
class QuizOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_question_id' => QuizQuestion::factory(),
            'option' => fake()->sentence(),
            'is_correct' => false,
            'sort_order' => 0,
        ];
    }
}
```

Note: `CourseQuizFactory` sets `lesson_id` to a fresh lesson factory by default. For a **final quiz** (course-level, no lesson), tests/setup pass `'lesson_id' => null`. A dedicated `finalQuiz()` state is not needed; just override `lesson_id => null` where needed.
(** executed: the shipped factory derives `course_id` from the resolved
`lesson_id` (closure, with `lesson_id` ordered first because
`Factory::expandAttributes()` resolves in array order) — the plan's two
independent factories produced quizzes whose `course_id` and `lesson_id`
belonged to different courses. Footgun found in evaluation: `->for($course)`
alone overrides `course_id` while `lesson_id` stays a fresh unrelated lesson,
so recipe-level quizzes MUST pass `->for($lesson, 'lesson')` and final quizzes
MUST pass `['lesson_id' => null]`. **)

- [ ] **Step 7: Run to verify green**

Run: `php artisan test --filter QuizEngineTest`
Expected: 5 PASS. (If factories/relations are wrong, fix those — this task is the foundation everything else builds on.)

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(elearning): course lesson/quiz/question/option/progress/attempt tables + scoring model"
```

---

### Task 2: Authoring — Course lessons + per-lesson quizzes

**Files:**
- Create: `app/Filament/Resources/CourseLessonResource.php` + Pages, `app/Filament/Resources/CourseLessonResource/RelationManagers/QuizzesRelationManager.php`, `app/Filament/Resources/CourseResource/RelationManagers/LessonsRelationManager.php`, `tests/Feature/CourseAuthoringTest.php`
- Modify: `app/Filament/Resources/CourseResource.php`

**Interfaces:**
- Consumes: Task 1 models (`CourseLesson`, `CourseQuiz`, relations).
- Produces: authoring CRUD for lessons and their quizzes in `/admin` (`super_admin`/`editor`); `LessonsRelationManager` attached to `CourseResource`; `QuizzesRelationManager` attached to `CourseLessonResource`.

- [ ] **Step 1: Write the failing test `tests/Feature/CourseAuthoringTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseLessonResource\Pages\CreateCourseLesson;
use App\Filament\Resources\CourseLessonResource\Pages\EditCourseLesson;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_sba_admin_cannot_access_lesson_resource(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();
        $this->actingAs($sba)->get('/admin/course-lessons')->assertForbidden();
    }

    public function test_editor_can_create_a_lesson_on_a_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseLesson::class, ['record' => $course->getKey()])
            ->fillForm([
                'title' => 'Pelajaran Perkenalan',
                'content' => '<p>Isi materi.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_lessons', [
            'course_id' => $course->id,
            'title' => 'Pelajaran Perkenalan',
        ]);
    }

    public function test_lesson_requires_title_and_content(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseLesson::class, ['record' => $course->getKey()])
            ->fillForm(['title' => '', 'content' => ''])
            ->call('create')
            ->assertHasFormErrors(['title', 'content']);
    }
};
```

(The test above references `Organization` in `test_sba_admin_cannot_access_lesson_resource` — add `use App\Models\Organization;`. A cross-tenant 404 test like SP3 doesn't apply here because lessons are not tenant-scoped; the federation authoring is global. Note this in an annotation.)

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter CourseAuthoringTest` → FAIL (no resource).

- [ ] **Step 3: Create `CourseLessonResource` + pages**

```bash
php artisan make:filament-resource CourseLesson --generate --no-interaction
```

Customize `app/Filament/Resources/CourseLessonResource.php`: navigation group "E-Learning", label "Pelajari". Form fields: Select `course_id` (relationship course, title, required, searchable, disabled-on-edit since parent drives it — but standalone resource needs it visible on create), TextInput `title` (required), RichEditor `content` (required, columnSpanFull). Keep auto pages. Add `getRelations()` → `[QuizzesRelationManager::class]`.

`EditCourseLesson` gains no extra mutate; the BelongsTo `course_id` Select on create is fine (federation authoring is global — there is no tenant to enforce).

- [ ] **Step 4: `QuizzesRelationManager`** (mirror SP3's committed relation-manager pattern — plain form, no special configure needed beyond relationship):

```php
<?php
// app/Filament/Resources/CourseLessonResource/RelationManagers/QuizzesRelationManager.php
namespace App\Filament\Resources\CourseLessonResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuizzesRelationManager extends RelationManager
{
    protected static string $relationship = 'quizzes';

    protected static ?string $title = 'Kuis';

    protected static ?string $modelLabel = 'Kuis';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->default(70)
                ->helperText('Kosong = pakai ambang default kursus.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('questions_count')->label('Jml Soal')->counts('questions'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

`pass_threshold` stores an int; when null it should fall back to the course default. Use `->nullable()` so an empty value stores null and `passThreshold()` falls back. Update the field: add `->nullable()`.

- [ ] **Step 5: `LessonsRelationManager` on Course** (mirror QuizzesRelationManager but for lessons on the Course resource):

```php
<?php
// app/Filament/Resources/CourseResource/RelationManagers/LessonsRelationManager.php
namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Pelajaran';

    protected static ?string $modelLabel = 'Pelajaran';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\RichEditor::make('content')->label('Isi Materi')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->orderColumn('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('quizzes_count')->label('Jml Kuis')->counts('quizzes'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

Add `LessonsRelationManager::class` to `CourseResource::getRelations()` (`app/Filament/Resources/CourseResource.php`):

```php
public static function getRelations(): array
{
    return [
        \App\Filament\Resources\CourseResource\RelationManagers\LessonsRelationManager::class,
    ];
}
```

- [ ] **Step 6: Run green** — `php artisan test --filter CourseAuthoringTest`, then `php artisan test` (SP1–SP3 suite must stay green).

- [ ] **Step 7: Commit** — `feat(elearning): authoring — course lesson resource + per-lesson quizzes`

---

### Task 3: Authoring — questions + options

**Files:**
- Create: `app/Filament/Resources/CourseQuizResource.php` + Pages, `app/Filament/Resources/QuizQuestionResource.php` + Pages, `app/Filament/Resources/CourseQuizResource/RelationManagers/QuestionsRelationManager.php`, `app/Filament/Resources/QuizQuestionResource/RelationManagers/OptionsRelationManager.php`, extend `tests/Feature/CourseAuthoringTest.php`

**Interfaces:**
- Consumes: Task 1 models (`CourseQuiz`, `QuizQuestion`, `QuizOption`), Task 2 resources.
- Produces: CRUD for quiz questions and their MCQ options in `/admin`; `QuestionsRelationManager` on `CourseQuizResource`; `OptionsRelationManager` on `QuizQuestionResource`.

- [ ] **Step 1: Add authoring tests** (append to `CourseAuthoringTest.php`):

```php
    public function test_editor_can_add_questions_and_options_to_a_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        // create a question on the quiz
        Livewire::actingAs($editor)
            ->test(\App\Filament\Resources\CourseQuizResource\Pages\CreateCourseQuiz::class) // placeholder; see note
            ->assertOk();
    }
```

> **(** executed note for the implementer: the above is a stub. The real, load-bearing tests are the Question/Options relation managers. Write them as full Livewire relation-manager tests mirroring SP3 `SbaEventAttendanceTest` (`callTableAction('create', data: [...])` against the owner record), which are the canonical form in this repo. Replace the stub with concrete cases before committing Task 3. **)**

Because relation-manager create flows (Question on a Quiz, Option on a Question) are the real deliverable, the tests must exercise `QuestionsRelationManager` and `OptionsRelationManager` directly via their `create` table actions — exactly how SP3 tests `AttendancesRelationManager`. Provide those now:

```php
    public function test_editor_can_create_a_question_on_a_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        Livewire::actingAs($editor)
            ->test(\App\Filament\Resources\CourseQuizResource\RelationManagers\QuestionsRelationManager::class, ['ownerRecord' => $quiz])
            ->callTableAction('create', data: ['question' => 'Apa itu serikat pekerja?'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_questions', [
            'course_quiz_id' => $quiz->id,
            'question' => 'Apa itu serikat pekerja?',
        ]);
    }

    public function test_editor_can_create_an_option_and_mark_it_correct(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();

        Livewire::actingAs($editor)
            ->test(\App\Filament\Resources\QuizQuestionResource\RelationManagers\OptionsRelationManager::class, ['ownerRecord' => $question])
            ->callTableAction('create', data: ['option' => 'Jawaban Benar', 'is_correct' => true])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_options', [
            'quiz_question_id' => $question->id,
            'option' => 'Jawaban Benar',
            'is_correct' => true,
        ]);
    }

    public function test_question_requires_text(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        Livewire::actingAs($editor)
            ->test(\App\Filament\Resources\CourseQuizResource\RelationManagers\QuestionsRelationManager::class, ['ownerRecord' => $quiz])
            ->callTableAction('create', data: ['question' => ''])
            ->assertHasTableActionErrors(['question']);
    }
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter CourseAuthoringTest` → FAIL (no resources/relation managers).

- [ ] **Step 3: `CourseQuizResource` + `QuestionsRelationManager`**

```bash
php artisan make:filament-resource CourseQuiz --generate --no-interaction
```

Customize label "Kuis", nav group "E-Learning". Form: TextInput `title` (required), Select `pass_threshold` (50/60/70/80/90, nullable), Select `lesson_id` (relationship `lesson`, `course.name`, required? — for lesson quizzes it is required; for the final quiz it stays null via a different resource `FinalQuizResource` (Task 4), so here make it optional but indicate "kosong hanya untuk kuis akhir"). Add `getRelations()` → `[QuestionsRelationManager::class]`.

```php
<?php
// app/Filament/Resources/CourseQuizResource/RelationManagers/QuestionsRelationManager.php
namespace App\Filament\Resources\CourseQuizResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal';

    protected static ?string $modelLabel = 'Soal';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('question')->label('Pertanyaan')->required()->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->orderColumn('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
                Tables\Columns\TextColumn::make('question')->label('Pertanyaan')->limit(60),
                Tables\Columns\TextColumn::make('options_count')->label('Jml Opsi')->counts('options'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

- [ ] **Step 4: `QuizQuestionResource` + `OptionsRelationManager`**

```bash
php artisan make:filament-resource QuizQuestion --generate --no-interaction
```

Customize label "Soal", nav group "E-Learning". Add `getRelations()` → `[OptionsRelationManager::class]`.

```php
<?php
// app/Filament/Resources/QuizQuestionResource/RelationManagers/OptionsRelationManager.php
namespace App\Filament\Resources\QuizQuestionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Opsi Jawaban';

    protected static ?string $modelLabel = 'Opsi';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('option')->label('Teks Opsi')->required()->columnSpanFull(),
            Forms\Components\Toggle::make('is_correct')->label('Kunci jawaban benar'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->orderColumn('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('option')->label('Opsi'),
                Tables\Columns\IconColumn::make('is_correct')->label('Benar')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

- [ ] **Step 5: Run green** — `php artisan test --filter CourseAuthoringTest`, then full suite.

- [ ] **Step 6: Commit** — `feat(elearning): authoring — quiz questions + options relation managers`

---

### Task 4: Authoring — final quiz (course-level)

**Files:**
- Create: `app/Filament/Resources/FinalQuizResource.php` + Pages, `app/Filament/Resources/FinalQuizResource/RelationManagers/QuestionsRelationManager.php`, `app/Filament/Resources/CourseResource/RelationManagers/FinalQuizRelationManager.php`
- Modify: `app/Filament/Resources/CourseResource.php` (register `FinalQuizRelationManager`)
- Test: extend `tests/Feature/CourseAuthoringTest.php`

**Interfaces:**
- Consumes: Task 1 model `CourseQuiz` (with `lesson_id null` = final), Task 3 `QuestionsRelationManager` pattern.
- Produces: a course-level final quiz (a `course_quizzes` row with `lesson_id = null`) editable from the Course resource.

- [ ] **Step 1: Add failing test** (append to `CourseAuthoringTest.php`):

```php
    public function test_editor_can_create_a_final_quiz_on_a_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(\App\Filament\Resources\CourseResource\RelationManagers\FinalQuizRelationManager::class, ['ownerRecord' => $course])
            ->callTableAction('create', data: ['title' => 'Evaluasi Akhir', 'pass_threshold' => 70])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'course_id' => $course->id,
            'lesson_id' => null,
            'title' => 'Evaluasi Akhir',
        ]);
    }
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter CourseAuthoringTest` → FAIL.

- [ ] **Step 3: `FinalQuizResource` + its Questions RM**

The final quiz is a `CourseQuiz` row with `lesson_id = null`. Implement it as a thin resource whose default create forces `lesson_id = null`:

```php
<?php
// app/Filament/Resources/FinalQuizResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\FinalQuizResource\Pages;
use App\Filament\Resources\FinalQuizResource\RelationManagers\QuestionsRelationManager;
use App\Models\CourseQuiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinalQuizResource extends Resource
{
    protected static ?string $model = CourseQuiz::class;

    protected static ?string $navigationGroup = 'E-Learning';

    protected static ?string $navigationLabel = 'Kuis Akhir';

    protected static ?string $modelLabel = 'Kuis Akhir';

    /** Only course-level (final) quizzes show here. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->whereNull('lesson_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('course_id')->relationship('course', 'title')->required()->searchable(),
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->default(70),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.title')->label('Kursus')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Judul'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [QuestionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinalQuizzes::route('/'),
            'create' => Pages\CreateFinalQuiz::route('/create'),
            'edit' => Pages\EditFinalQuiz::route('/{record}/edit'),
        ];
    }
}
```

`CreateFinalQuiz::mutateFormDataBeforeCreate()` forces `lesson_id = null`:

```php
<?php
// app/Filament/Resources/FinalQuizResource/Pages/CreateFinalQuiz.php
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
```

`QuestionsRelationManager` under `FinalQuizResource` is identical in shape to the CourseQuiz one (reuse the same class via inheritance or a `QuestionsRelationManager` under that namespace). Keep it separate (`app/Filament/Resources/FinalQuizResource/RelationManagers/QuestionsRelationManager.php`) copying the CourseQuiz one — the repo's convention favors explicit, non-shared relation managers.

- [ ] **Step 4: `FinalQuizRelationManager` on Course**

A convenience: on the Course resource, a relation manager listing that course's final quizzes (so the author stays in the course screen).

```php
<?php
// app/Filament/Resources/CourseResource/RelationManagers/FinalQuizRelationManager.php
namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinalQuizRelationManager extends RelationManager
{
    protected static string $relationship = 'quizzes';

    protected static ?string $title = 'Kuis Akhir';

    protected static ?string $modelLabel = 'Kuis Akhir';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->whereNull('lesson_id');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->default(70),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->mutateDataUsing(function (array $data): array {
                    $data['lesson_id'] = null;
                    return $data;
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

Register it: in `CourseResource::getRelations()`, add both `LessonsRelationManager::class` and `FinalQuizRelationManager::class`.

- [ ] **Step 5: Run green** — `php artisan test --filter CourseAuthoringTest`, then full suite.

- [ ] **Step 6: Commit** — `feat(elearning): authoring — course-level final quiz`

---

### Task 5: Learning surface — "Kursus Saya" + course detail + lesson view (both panels)

**Files:**
- Create: `app/Filament/Admin/Pages/{MyCoursesPage,CourseDetailPage,LessonViewPage}.php`, `app/Filament/Sba/Pages/{MyCoursesPage,CourseDetailPage,LessonViewPage}.php`, `tests/Feature/LearnerAccessTest.php`

**Interfaces:**
- Consumes: Task 1 models/relations, `Course::published()`, `CourseLesson`.
- Produces: twin Filament pages (one namespace per panel) listing published courses with per-user progress, a course detail page listing lessons with statuses + actions, and a lesson view page rendering `content`. Learner = whatever `users()` is logged into that panel.

Design: to avoid duplicating query logic across the four Admin pages and four Sba pages, share a single `App\Support\LearningProgress` helper (see Task 7) and have the Admin/Sba pages be near-identical thin views. To keep the plan DRY, the pages in both panels are structurally identical; only the namespace differs.

- [ ] **Step 1: Write the failing test `tests/Feature/LearnerAccessTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sba_admin_sees_only_published_courses_in_my_courses(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $published = Course::factory()->create(['title' => 'Kursus Terbit']);
        Course::factory()->unpublished()->create(['title' => 'Kursus Draf']);

        $this->actingAs($sba)
            ->get('/panel-sba/my-courses')
            ->assertOk()
            ->assertSee('Kursus Terbit')
            ->assertDontSee('Kursus Draf');
    }

    public function test_editor_sees_only_published_courses_in_my_courses(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        Course::factory()->create(['title' => 'Kursus Terbit']);
        Course::factory()->unpublished()->create(['title' => 'Kursus Draf']);

        $this->actingAs($editor)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertSee('Kursus Terbit')
            ->assertDontSee('Kursus Draf');
    }

    public function test_anonymous_is_redirected_away_from_my_courses(): void
    {
        $this->get('/panel-sba/my-courses')->assertRedirect('/panel-sba/login');
        $this->get('/admin/my-courses')->assertRedirect('/admin/login');
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter LearnerAccessTest` → FAIL (pages don't exist).

- [ ] **Step 3: `MyCoursesPage` (both panels)**

```bash
php artisan make:filament-page MyCoursesPage --panel=admin --no-interaction
php artisan make:filament-page MyCoursesPage --panel=sba --no-interaction
```

Each page lists published courses with a per-user progress line. Use a simple Blade view (custom page) rather than a table component for design freedom. Admin page example:

```php
<?php
// app/Filament/Admin/Pages/MyCoursesPage.php
namespace App\Filament\Admin\Pages;

use App\Models\Course;
use Filament\Pages\Page;

class MyCoursesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kursus Saya';

    protected static ?string $title = 'Kursus Saya';

    protected string $view = 'filament.admin.pages.my-courses';

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelajaran';
    }

    public function getCourses()
    {
        return Course::published()->with(['lessons', 'finalQuiz'])->orderBy('title')->get();
    }
}
```

Create the Blade view `resources/views/filament/admin/pages/my-courses.blade.php` (and the Sba twin) rendering course cards with `$this->app(\App\Support\LearningProgress::class)->forCourse($course)` — see Task 7 for that helper. Each card links to `CourseDetailPage`.

The Sba twin lives at `app/Filament/Sba/Pages/MyCoursesPage.php` with `protected string $view = 'filament.sba.pages.my-courses';` and identical logic (both read `Course::published()`, so no tenant scoping needed).

- [ ] **Step 4: `CourseDetailPage` + `LessonViewPage` (both panels)**

`CourseDetailPage` takes a `?Course` record param (`Route::get('/courses/{record}')` via the page's `getRoutes()`), lists the course's lessons with per-user status, and renders per-lesson actions. It also shows the final quiz entry point. `LessonViewPage` renders one lesson's `content` (`{!! $lesson->content !!}` — trusted HTML) plus a back link.

Because these are custom Filament pages with resource-style routing, register routes in each page's `getRoutes()`:

```php
// app/Filament/Admin/Pages/CourseDetailPage.php (abridged — implementer fills the view)
protected static function getRoutes(): array
{
    return [
        '/courses/{record}' => static fn (): static => static::class,
    ];
}
```

The Sba twin mirrors this under `/panel-sba`. The implementer should follow Filament 3 custom-page routing conventions exactly (the `getRoutes()` static method pattern) and verify the URLs `/admin/courses/{id}` and `/panel-sba/courses/{id}` render 200 for the right roles in the tests.

- [ ] **Step 5: Run green + smoke** — `php artisan test --filter LearnerAccessTest`, then smoke `php artisan serve` + curl `/admin/my-courses` and `/panel-sba/my-courses` for 200 with the right role.

- [ ] **Step 6: Commit** — `feat(elearning): learner "Kursus Saya" + course detail + lesson view pages (both panels)`

---

### Task 6: Quiz taking engine (`QuizViewPage` + `QuizEngine` service)

**Files:**
- Create: `app/Support/QuizEngine.php`, `app/Filament/Admin/Pages/QuizViewPage.php`, `app/Filament/Sba/Pages/QuizViewPage.php`, extend `tests/Feature/QuizEngineTest.php` + `tests/Feature/CourseProgressTest.php`

**Interfaces:**
- Consumes: Task 1 (`CourseQuiz::score`, `isFinal`, models), Task 4 final quiz, Task 5 pages.
- Produces: `QuizEngine::submit(CourseQuiz $quiz, User $user, array $answers): array` returning `['score' => int, 'passed' => bool, 'attempt' => CourseAttempt]`, which records the attempt, and (only for lesson quizzes) sets the lesson `CourseProgress.is_completed` when passed. `QuizViewPage` (Admin + Sba twins) is the Livewire form that collects answers and calls the service.

- [ ] **Step 1: Write failing test (append to `QuizEngineTest.php`)**

```php
    public function test_submit_records_attempt_and_marks_lesson_complete_when_passed(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson)->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($q)->create(['is_correct' => false]);
        QuizOption::factory()->for($q)->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->firstWhere('is_correct')->id];

        $result = app(\App\Support\QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertSame(100, $result['score']);
        $this->assertTrue($result['passed']);
        $this->assertDatabaseHas('course_attempts', [
            'course_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => 100,
            'passed' => true,
        ]);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_failed_attempt_does_not_mark_lesson_complete(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson)->create(['pass_threshold' => 90]);
        $q = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($q)->create(['is_correct' => false]);
        QuizOption::factory()->for($q)->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->where('is_correct', false)->first()->id];

        app(\App\Support\QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertDatabaseHas('course_progress', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => false,
        ]);
    }

    public function test_retake_records_multiple_attempts(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson)->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($q)->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->first()->id];

        app(\App\Support\QuizEngine::class)->submit($quiz, $user, $answers);
        app(\App\Support\QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertSame(2, $quiz->attempts()->where('user_id', $user->id)->count());
    }
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter QuizEngineTest` → FAIL (no service/`submit`).

- [ ] **Step 3: `QuizEngine` service**

```php
<?php
// app/Support/QuizEngine.php
namespace App\Support;

use App\Models\CourseAttempt;
use App\Models\CourseProgress;
use App\Models\CourseQuiz;
use App\Models\User;

class QuizEngine
{
    /**
     * Record a quiz attempt and, for a passing lesson quiz, mark the owning
     * lesson complete for this user. Final quizzes (lesson_id null) record
     * the attempt only — course completion is derived in LearningProgress.
     *
     * @param array<int,int> $answers keyed [question_id => option_id]
     * @return array{score:int, passed:bool, attempt:CourseAttempt}
     */
    public function submit(CourseQuiz $quiz, User $user, array $answers): array
    {
        $score = $quiz->score($answers);
        $passed = $score >= $quiz->passThreshold();

        $attempt = CourseAttempt::create([
            'course_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => $score,
            'passed' => $passed,
            'attempt_date' => now(),
        ]);

        if ($passed && $quiz->lesson_id !== null) {
            CourseProgress::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $quiz->course_id, 'lesson_id' => $quiz->lesson_id],
                ['is_completed' => true, 'completed_at' => now()]
            );
        }

        return ['score' => $score, 'passed' => $passed, 'attempt' => $attempt];
    }
}
```

- [ ] **Step 4: `QuizViewPage` (Admin + Sba twins)**

Livewire-driven Filament page with a form keyed by `question_id => option_id` (radio per question) and a submit action. Both pages share the service. Sba variant is a copy in `app/Filament/Sba/Pages/QuizViewPage.php`; only the namespace/view differ. Admin example (abridged structure — implementer fills the Blade form):

```php
<?php
// app/Filament/Admin/Pages/QuizViewPage.php
namespace App\Filament\Admin\Pages;

use App\Models\CourseQuiz;
use App\Support\QuizEngine;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QuizViewPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected string $view = 'filament.admin.pages.quiz-view';

    public ?CourseQuiz $quiz = null;

    public array $answers = [];

    public ?array $result = null;

    public function mount(CourseQuiz $record): void
    {
        $this->quiz = $record;
        $this->answers = collect($record->questions)->mapWithKeys(
            fn ($q) => [$q->id => null]
        )->all();
    }

    public function submit(): void
    {
        $this->result = app(QuizEngine::class)->submit($this->quiz, Auth::user(), $this->answers);
    }

    protected static function getRoutes(): array
    {
        return [
            '/quizzes/{record}' => static fn (): static => static::class,
        ];
    }
}
```

The Blade view renders each question's options as radio inputs bound to `wire:model="answers.{id}"`, a submit button, and, when `$this->result` is set, the score + pass/fail + an "Ulangi" link. Include validation that every question has an answer before submit. The Sba twin (`app/Filament/Sba/Pages/QuizViewPage.php`, view `filament.sba.pages.quiz-view`) is identical; both call the same `QuizEngine`.

- [ ] **Step 5: Run green** — `php artisan test --filter QuizEngineTest` (now includes the new submit tests), then full suite.

- [ ] **Step 6: Commit** — `feat(elearning): quiz taking engine (server-side scoring, attempts, lesson completion)`

---

### Task 7: Progress semantics + derived course completion

**Files:**
- Create: `app/Support/LearningProgress.php`, extend `tests/Feature/CourseProgressTest.php`

**Interfaces:**
- Consumes: Task 1 models, Task 6 `QuizEngine`, Task 5 pages (this helper powers "Kursus Saya" and the report).
- Produces: `LearningProgress::forUser(User $user): array` (list of published courses with per-user progress + completed flag) and `LearningProgress::forCourse(Course $course): array` (per-lesson statuses for the acting user + final quiz passed state + `is_complete`). Also `LearningProgress::report(): Collection` (course × user statuses) for Task 8.

- [ ] **Step 1: Write the failing test `tests/Feature/CourseProgressTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\LearningProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressTest extends TestCase
{
    use RefreshDatabase;

    private function completedCourse(User $user): Course
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($finalQuiz)->create();
        QuizOption::factory()->for($q)->create(['is_correct' => true]);

        // lesson with no quiz → manual complete
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        // final quiz passed
        app(\App\Support\QuizEngine::class)->submit($finalQuiz, $user, [$q->id => $q->options->first()->id]);

        return $course;
    }

    public function test_course_is_complete_when_lessons_done_and_final_quiz_passed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($user);

        $this->assertTrue(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_course_is_not_complete_without_final_quiz_pass(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        CourseQuiz::factory()->for($course)->create(['lesson_id' => null]); // final quiz, never passed

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_course_is_not_complete_with_unfinished_lesson(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseLesson::factory()->for($course)->create(); // not completed
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($finalQuiz)->create();
        QuizOption::factory()->for($q)->create(['is_correct' => true]);
        app(\App\Support\QuizEngine::class)->submit($finalQuiz, $user, [$q->id => $q->options->first()->id]);

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_undoing_lesson_completion_degrades_course_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($user);
        $lesson = $course->lessons->first();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson, false);

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_for_user_lists_only_published_courses_with_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $done = $this->completedCourse($user);
        Course::factory()->unpublished()->create(['title' => 'Draf']);

        $rows = app(LearningProgress::class)->forUser($user);

        $this->assertCount(1, $rows);
        $this->assertSame($done->id, $rows->first()['course']->id);
        $this->assertTrue($rows->first()['is_complete']);
    }
};
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter CourseProgressTest` → FAIL (no `LearningProgress`).

- [ ] **Step 3: `LearningProgress` helper**

```php
<?php
// app/Support/LearningProgress.php
namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
    public function forUser(User $user): Collection
    {
        return Course::published()->with(['lessons.quizzes', 'finalQuiz'])
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
}
```

- [ ] **Step 4: Wire `LearningProgress` into Task 5 pages**

`MyCoursesPage::getCourses()` should return `app(LearningProgress::class)->forUser(auth()->user())`; `CourseDetailPage` uses `forCourse(auth()->user(), $course)`. Update the two `MyCoursesPage` classes and the two `CourseDetailPage` classes to call the helper (single source of truth). Update `LearnerAccessTest` if the page's bound data changed shape — the HTTP assertions (See/DontSee) remain valid.

- [ ] **Step 5: Run green** — `php artisan test --filter "CourseProgressTest|LearnerAccessTest"`, then full suite.

- [ ] **Step 6: Commit** — `feat(elearning): progress semantics + derived course completion`

---

### Task 8: Federation learning report (super-admin)

**Files:**
- Create: `app/Filament/Widgets/LearningReportWidget.php`, `resources/views/filament/widgets/learning-report.blade.php`, `tests/Feature/LearningReportTest.php`

**Interfaces:**
- Consumes: Task 7 `LearningProgress::report()` (add it), Course/User models.
- Produces: a super-admin-only dashboard widget showing per-course completers and a course × user status table.

- [ ] **Step 1: Write the failing test `tests/Feature/LearningReportTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Widgets\LearningReportWidget;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LearningReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_widget_is_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor);
        $this->assertFalse(LearningReportWidget::canView());

        $this->actingAs($super);
        $this->assertTrue(LearningReportWidget::canView());
    }

    public function test_report_lists_course_and_user_statuses(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $course = Course::factory()->create(['title' => 'Kursus Laporan']);
        $userA = User::factory()->create(['name' => 'Peserta A', 'role' => User::ROLE_EDITOR]);
        $userB = User::factory()->create(['name' => 'Peserta B', 'role' => User::ROLE_EDITOR]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(LearningReportWidget::class)
            ->assertOk()
            ->assertSee('Kursus Laporan')
            ->assertSee('Peserta A')
            ->assertSee('Peserta B');
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --filter LearningReportTest` → FAIL (no widget).

- [ ] **Step 3: `LearningProgress::report()`** (append to `app/Support/LearningProgress.php`)

```php
/** Course × user status summary for the federation report. */
public function report(): Collection
{
    $courses = Course::published()->with(['lessons', 'finalQuiz'])->get();
    $users = User::orderBy('name')->get();

    return $courses->map(fn (Course $course) => [
        'course' => $course,
        'rows' => $users->map(fn (User $user) => [
            'user' => $user,
            'is_complete' => $this->isCourseComplete($user, $course),
        ])->values(),
    ])->values();
}
```

- [ ] **Step 4: `LearningReportWidget`**

```php
<?php
// app/Filament/Widgets/LearningReportWidget.php
namespace App\Filament\Widgets;

use App\Support\LearningProgress;
use Filament\Widgets\Widget;

class LearningReportWidget extends Widget
{
    protected static string $view = 'filament.widgets.learning-report';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getReport()
    {
        return app(LearningProgress::class)->report();
    }
}
```

Blade `resources/views/filament/widgets/learning-report.blade.php`: a heading "Laporan Pembelajaran", per-course a table of user → status (Selesai / Belum). Use a plain Tailwind table (repo pattern after SP2's `sba-accounts-overview`), not the `x-filament::table` component.

- [ ] **Step 5: Run green** — `php artisan test --filter LearningReportTest`, then full suite. Verify the widget shows on `/admin` dashboard for super_admin and not for editor.

- [ ] **Step 6: Commit** — `feat(elearning): federation learning report widget (super-admin only)`

---

### Task 9: Demo seed data + README + full green + final verification

**Files:**
- Create: `database/seeders/CourseContentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `README.md`

**Interfaces:**
- Consumes: Task 1–4 models, `Course`/`CourseQuiz` factories.
- Produces: one published demo course with 2 lessons (one with a quiz, one without), a final quiz, all with questions/options, so "Kursus Saya" and the report render real rows after `migrate:fresh --seed`.

- [ ] **Step 1: `CourseContentSeeder`**

```php
<?php
// database/seeders/CourseContentSeeder.php
namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class CourseContentSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::firstOrCreate(
            ['slug' => 'dasar-kepengurusan-serikat'],
            [
                'title' => 'Dasar Kepengurusan Serikat',
                'description' => 'Pengantar peran dan tanggung jawab pengurus serikat pekerja.',
                'level' => 'dasar',
                'is_published' => true,
                'pass_threshold' => 70,
            ]
        );

        if ($course->lessons()->exists()) {
            return; // idempotent
        }

        $lesson = CourseLesson::create([
            'course_id' => $course->id,
            'title' => 'Peran Pengurus',
            'content' => '<h2>Pengurus</h2><p>Pengurus mewakili dan melindungi hak anggota.</p>',
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_id' => $course->id,
            'title' => 'Struktur Organisasi',
            'content' => '<h2>Struktur</h2><p>Bentuk serikat dan hierarki pengurus.</p>',
            'sort_order' => 2,
        ]);

        $quiz = CourseQuiz::create([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'title' => 'Kuis Peran Pengurus',
            'pass_threshold' => 70,
        ]);

        $q = QuizQuestion::create(['course_quiz_id' => $quiz->id, 'question' => 'Siapa yang diwakili pengurus?', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q->id, 'option' => 'Anggota', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q->id, 'option' => 'Pemilik pabrik', 'is_correct' => false, 'sort_order' => 2]);

        $finalQuiz = CourseQuiz::create([
            'course_id' => $course->id,
            'lesson_id' => null,
            'title' => 'Evaluasi Akhir',
            'pass_threshold' => 70,
        ]);

        $fq = QuizQuestion::create(['course_quiz_id' => $finalQuiz->id, 'question' => 'Serikat bertujuan melindungi…', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $fq->id, 'option' => 'Hak pekerja', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $fq->id, 'option' => 'Keuntungan perusahaan', 'is_correct' => false, 'sort_order' => 2]);
    }
}
```

Wire into `DatabaseSeeder::run()` (after the existing content seeders — order independent, but place after `SbaAccountSeeder` so all demo accounts exist):

```php
$this->call([..., CourseContentSeeder::class]);
```

- [ ] **Step 2: README updates**

Add an E-Learning row: authors (`/admin`), learning area ("Kursus Saya" in both panels), progress + super-admin report; note SP4 in the roadmap as done. Add a security bullet: course content is trusted staff HTML; quiz scoring server-side; report is super-admin only.

- [ ] **Step 3: Full suite green + lint**

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint
npm run build
```

Expected: every SP1+SP2+SP3 test plus all new SP4 tests pass; Pint clean; build clean.

- [ ] **Step 4: Final verification** — `php artisan serve`, manual smoke:
- `/admin/my-courses` (editor/super) lists the seeded published course; `/panel-sba/my-courses` (sba_admin) lists the same.
- Open the course → lessons render; take the lesson quiz → attempt recorded, lesson flips to complete on pass; take the final quiz → course marks Selesai.
- `/admin` dashboard (super_admin) shows "Laporan Pembelajaran"; editor does not.
- `/e-learning` (public) still lists the course as a static catalog card.

- [ ] **Step 5: Commit** — `feat(elearning): demo seed data + README docs for SP4`

---

## Final Verification

```bash
php artisan test                  # all SP1+SP2+SP3+SP4 feature tests pass
vendor/bin/pint                   # clean
npm run build                     # clean
php artisan migrate:fresh --seed  # clean DB with SP4 demo course
php artisan serve                 # manual smoke listings above
```

## Guard-count/scope bookkeeping

SP4 adds: 6 tables + 1 column across 7 migrations, 6 new models (+2 modified), authoring chain (Course resource extended + 4 new resources + 4 relation managers), 4 custom learning pages in each of the two panels, 1 quiz-engine service, 1 progress helper, 1 super-admin widget, 1 seeder, 5 new feature-test files. Public site surface is **unchanged** (no public route/course-content changes; `/e-learning` keeps its static catalog).

## Non-Goals (from spec, repeated for executor clarity)

- No public/self registration for learners — existing panel accounts only.
- No question types beyond MCQ; no certificates, leaderboard, forum, rating, or notifications.
- No SBA authoring / no tenant dimension in course content — courses are federation-owned and global.
- No per-course gating beyond `is_published` + panel auth; no DRM/SCORM import.
- Report is summary-level (course × user) only — no detailed time-on-course/session analytics.
