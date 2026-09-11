<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseAttempt;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\Organization;
use App\Models\User;
use App\Support\CertificateService;
use App\Support\LearningProgress;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_requires_a_completed_course(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseLesson::factory()->for($course)->create();

        $this->expectException(DomainException::class);

        app(CertificateService::class)->issue($user, $course);
    }

    public function test_issue_rejects_an_unpublished_course(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->unpublished()->create();

        $this->expectException(InvalidArgumentException::class);

        app(CertificateService::class)->issue($user, $course);
    }

    public function test_issue_is_idempotent_with_a_stable_number_and_token(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        [$course] = $this->completedCourseFor($user, 88);

        $service = app(CertificateService::class);
        $first = $service->issue($user, $course);
        $second = $service->issue($user, $course);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->certificate_number, $second->certificate_number);
        $this->assertMatchesRegularExpression('/^FSBMM-CERT-\d{4}-[0-9A-F]{8}$/', $first->certificate_number);
        $this->assertSame(64, strlen($first->verification_token));
        $this->assertSame(88, $first->final_score);
        $this->assertDatabaseCount('course_certificates', 1);
    }

    public function test_final_score_uses_the_best_passed_attempt(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        [$course, , $final] = $this->completedCourseFor($user, 60);

        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $user->id,
            'score' => 95,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        $certificate = app(CertificateService::class)->issue($user, $course);

        $this->assertSame(95, $certificate->final_score);
    }

    public function test_validate_token_resolves_valid_revoked_and_unknown_tokens(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        [$course] = $this->completedCourseFor($user);

        $service = app(CertificateService::class);
        $certificate = $service->issue($user, $course);

        $this->assertSame($certificate->id, $service->validateToken($certificate->verification_token)?->id);
        $this->assertNull($service->validateToken('token-tidak-dikenal'));

        $certificate->update(['revoked_at' => now(), 'revocation_reason' => 'Uji cabut']);

        $this->assertNull($service->validateToken($certificate->verification_token));
    }

    public function test_public_verification_page_shows_the_certificate(): void
    {
        $user = User::factory()->create(['name' => 'Peserta Lulus', 'role' => User::ROLE_EDITOR]);
        [$course] = $this->completedCourseFor($user);

        $certificate = app(CertificateService::class)->issue($user, $course);

        $this->get('/verifikasi/sertifikat/'.$certificate->verification_token)
            ->assertOk()
            ->assertSee('Sertifikat Valid')
            ->assertSee('Peserta Lulus')
            ->assertSee($course->title)
            ->assertSee($certificate->certificate_number);
    }

    public function test_public_verification_page_rejects_an_unknown_token(): void
    {
        $this->get('/verifikasi/sertifikat/token-palsu')
            ->assertOk()
            ->assertSee('Sertifikat Tidak Ditemukan');
    }

    public function test_print_route_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $this->get('/admin/certificates/'.$course->slug.'/print')
            ->assertRedirect('/admin/login');
    }

    public function test_learner_prints_their_own_certificate_and_it_is_issued(): void
    {
        $user = User::factory()->create(['name' => 'Peserta Cetak', 'role' => User::ROLE_EDITOR]);
        [$course] = $this->completedCourseFor($user);

        $this->actingAs($user)
            ->get('/admin/certificates/'.$course->slug.'/print')
            ->assertOk()
            ->assertSee('Sertifikat Kelulusan')
            ->assertSee('Peserta Cetak')
            ->assertSee($course->title);

        $this->assertDatabaseHas('course_certificates', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_print_route_is_forbidden_for_an_incomplete_course(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseLesson::factory()->for($course)->create();

        $this->actingAs($user)
            ->get('/admin/certificates/'.$course->slug.'/print')
            ->assertForbidden();

        $this->assertDatabaseCount('course_certificates', 0);
    }

    public function test_sba_admin_prints_a_certificate_on_the_sba_panel(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create(['name' => 'Admin Sertifikat']);
        [$course] = $this->completedCourseFor($sba);

        $this->actingAs($sba)
            ->get('/panel-sba/certificates/'.$course->slug.'/print')
            ->assertOk()
            ->assertSee('Sertifikat Kelulusan')
            ->assertSee('Admin Sertifikat');
    }

    public function test_my_courses_offers_the_certificate_only_after_completion(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['title' => 'Kursus Sertifikat UI']);
        $lesson = CourseLesson::factory()->for($course)->create();
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        $this->actingAs($user)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertDontSee('Cetak Sertifikat');

        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $user->id,
            'score' => 100,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertSee('Cetak Sertifikat');
    }

    /**
     * Complete a fresh course for the given user.
     *
     * @return array{0: Course, 1: CourseLesson, 2: CourseQuiz}
     */
    private function completedCourseFor(User $user, int $score = 90): array
    {
        $course = Course::factory()->create(['title' => 'Kursus Sertifikat']);
        $lesson = CourseLesson::factory()->for($course)->create();
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $user->id,
            'score' => $score,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        return [$course, $lesson, $final];
    }
}
