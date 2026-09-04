<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDataSeedTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_ORG_SLUGS = ['spm-kecap-bango', 'spm-minuman-segar', 'spm-roti-nusantara'];

    public function test_seeding_populates_demo_member_data_per_org(): void
    {
        $this->seed();

        $kecap = Organization::where('slug', 'spm-kecap-bango')->firstOrFail();

        // The real computed count replaces the federation-manual placeholder (429).
        $this->assertSame(5, $kecap->member_count);
        $this->assertNotSame(429, $kecap->member_count);
        $this->assertSame(5, $kecap->members()->count());
        $this->assertGreaterThan(0, $kecap->dues()->count());
        $this->assertSame(1, $kecap->events()->count());
        $this->assertSame(5, $kecap->events()->first()->attendances()->count());
        $this->assertSame(1, $kecap->complaints()->count());

        // Every demo org got seeded with the same demo roster.
        $seeded = Organization::whereIn('slug', self::DEMO_ORG_SLUGS)->get();
        $this->assertCount(3, $seeded);
        $this->assertTrue($seeded->every(fn (Organization $org) => $org->members()->count() === 5));
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $kecap = Organization::where('slug', 'spm-kecap-bango')->firstOrFail();

        $this->assertSame(5, $kecap->member_count);
        $this->assertSame(5, $kecap->members()->count());
    }

    public function test_public_directory_shows_real_seeded_member_count(): void
    {
        $this->seed();

        $kecap = Organization::where('slug', 'spm-kecap-bango')->firstOrFail();

        // The computed count must be the real one, not the federation-manual
        // placeholder (429) that the demo org is seeded with.
        $this->assertNotSame(429, $kecap->member_count);

        // Derive the expectation from the model instead of hardcoding it, so the
        // assertion stays valid if the demo roster size ever changes.
        $this->get('/sba/spm-kecap-bango')
            ->assertOk()
            ->assertSee(number_format($kecap->member_count, 0, ',', '.').' pekerja')
            ->assertDontSee('429 pekerja');
    }
}
