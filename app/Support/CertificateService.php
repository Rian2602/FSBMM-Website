<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Issues and resolves e-learning completion certificates.
 *
 * Certificates are only issued for a *published* course the learner has
 * actually completed (all lessons done + final quiz passed, per
 * LearningProgress), and are idempotent: one certificate per learner/course
 * keeps its original number and verification token.
 */
class CertificateService
{
    public function issue(User $user, Course $course): CourseCertificate
    {
        if (! $course->is_published) {
            throw new InvalidArgumentException('Sertifikat hanya diterbitkan untuk kursus yang sudah terbit.');
        }

        if (! app(LearningProgress::class)->isCourseComplete($user, $course)) {
            throw new DomainException('Kursus belum diselesaikan — sertifikat belum bisa diterbitkan.');
        }

        $existing = $this->forUserCourse($user, $course);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $course): CourseCertificate {
            $certificate = CourseCertificate::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'certificate_number' => $this->generateUniqueNumber(),
                'verification_token' => $this->generateVerificationToken(),
                'final_score' => $this->finalScore($user, $course),
                'issued_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                'certificate.issued',
                'Sertifikat kelulusan diterbitkan (' . $certificate->certificate_number . ')',
                $certificate,
                $user->organization_id,
            );

            return $certificate;
        });
    }

    public function forUserCourse(User $user, Course $course): ?CourseCertificate
    {
        return CourseCertificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }

    /** Resolve a verification token; revoked or unknown tokens return null. */
    public function validateToken(string $token): ?CourseCertificate
    {
        $certificate = CourseCertificate::where('verification_token', $token)
            ->with(['user', 'course'])
            ->first();

        if ($certificate === null || $certificate->isRevoked()) {
            return null;
        }

        return $certificate;
    }

    /** Best score among the learner's passed final-quiz attempts (null when no final quiz). */
    public function finalScore(User $user, Course $course): ?int
    {
        $finalQuiz = $course->finalQuiz();

        if ($finalQuiz === null) {
            return null;
        }

        $best = $finalQuiz->attempts()
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->max('score');

        return $best === null ? null : (int) $best;
    }

    public function generateCertificateNumber(): string
    {
        return 'FSBMM-CERT-' . now()->format('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function generateUniqueNumber(): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $number = $this->generateCertificateNumber();
            if (! CourseCertificate::where('certificate_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Gagal menghasilkan nomor sertifikat unik setelah 3 percobaan.');
    }
}
