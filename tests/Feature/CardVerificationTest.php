<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_shows_minimal_data(): void
    {
        [$member, $creator, $card] = $this->fixture();

        $this->get('/verifikasi/kartu/'.$card->verification_token)
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($card->card_number)
            ->assertSee('Kartu Valid');
    }

    public function test_invalid_token_shows_generic_error(): void
    {
        $this->get('/verifikasi/kartu/nonexistent-token-abc')
            ->assertOk()
            ->assertSee('Kartu Tidak Ditemukan')
            ->assertDontSee('NIK');
    }

    public function test_revoked_card_shows_not_active(): void
    {
        [$member, $creator, $card] = $this->fixture();

        $service = new MemberCardService;
        $service->revoke($card, 'Kartu rusak', $creator);

        $this->get('/verifikasi/kartu/'.$card->verification_token)
            ->assertOk()
            ->assertSee('Kartu Tidak Aktif')
            ->assertSee($card->card_number);
    }

    public function test_no_nik_in_response(): void
    {
        [$member, $creator, $card] = $this->fixture();

        $this->get('/verifikasi/kartu/'.$card->verification_token)
            ->assertOk()
            ->assertDontSee($member->nik);
    }

    public function test_no_address_in_response(): void
    {
        [$member, $creator, $card] = $this->fixture();

        $this->get('/verifikasi/kartu/'.$card->verification_token)
            ->assertOk()
            ->assertDontSee($member->address);
    }

    public function test_no_salary_in_response(): void
    {
        [$member, $creator, $card] = $this->fixture();

        $this->get('/verifikasi/kartu/'.$card->verification_token)
            ->assertOk()
            ->assertDontSee((string) $member->basic_salary);
    }

    public function test_no_member_enumeration_possible(): void
    {
        [$memberA, $creatorA, $cardA] = $this->fixture();

        $orgB = Organization::factory()->create();
        $adminB = User::factory()->sbaAdmin($orgB)->create();
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Secret Member B']);

        // Verify card A does not leak member B's data
        $this->get('/verifikasi/kartu/'.$cardA->verification_token)
            ->assertOk()
            ->assertDontSee('Secret Member B');
    }

    public function test_route_is_above_catch_all(): void
    {
        // Verify the route resolves correctly (not caught by page slug catch-all)
        $this->get('/verifikasi/kartu/test-token')
            ->assertOk(); // Should return view, not 404 from catch-all
    }

    private function fixture(): array
    {
        $org = Organization::factory()->create();
        $creator = User::factory()->sbaAdmin($org)->create();
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        $card = (new MemberCardService)->issue($member, $creator);

        return [$member, $creator, $card];
    }
}
