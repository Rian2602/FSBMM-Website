<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use PDF;
use Tests\TestCase;

class MemberCardPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_returns_pdf_or_html_artefact(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);
        $member->load('organization');

        $response = $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $card->id]));

        $response->assertOk();

        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'application/pdf') || str_contains($contentType, 'text/html'),
            "Content-Type harus application/pdf atau text/html, dapat: {$contentType}",
        );

        if (str_contains($contentType, 'application/pdf')) {
            $this->assertStringStartsWith('%PDF', $response->getContent());
        } else {
            $response->assertSee($member->name)
                ->assertSee($card->card_number)
                ->assertSee('data:image/svg+xml;base64');
        }
    }

    public function test_print_does_not_leak_nik_or_gender_emoji(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $card->id]))
            ->assertOk()
            ->assertDontSee($member->nik)
            ->assertDontSee(str_contains($member->gender, 'P') ? '👩' : '👨')
            ->assertDontSee('Berlaku');
    }

    public function test_print_is_forbidden_for_another_sba_card(): void
    {
        [$user] = $this->fixture();

        $otherOrg = Organization::factory()->create();
        $otherAdmin = User::factory()->sbaAdmin($otherOrg)->create();
        $otherMember = Member::factory()->for($otherOrg)->create(['status' => Member::STATUS_ACTIVE]);
        $otherCard = (new MemberCardService)->issue($otherMember, $otherAdmin);

        $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $otherCard->id]))
            ->assertNotFound();
    }

    public function test_anonymous_cannot_print(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        $this->get(route('filament.sba.card.print', ['record' => $card->id]))
            ->assertRedirect();
    }

    public function test_revoked_card_cannot_be_printed(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);
        (new MemberCardService)->revoke($card, 'Kartu hilang', $user);

        $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $card->id]))
            ->assertNotFound();
    }

    public function test_pdf_branch_serves_pdf_when_snappy_available(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        PDF::shouldReceive('loadHTML')->once()->andReturnSelf();
        PDF::shouldReceive('setOption')->andReturnSelf();
        PDF::shouldReceive('output')->once()->andReturn('%PDF-1.4 mocksnappy');

        $response = $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $card->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="kartu-' . $card->card_number . '.pdf"');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_pdf_html_source_contains_card_data_and_qr(): void
    {
        [$user, $member] = $this->fixture();
        $card = (new MemberCardService)->issue($member, $user);

        $html = null;
        PDF::shouldReceive('loadHTML')->once()
            ->with(Mockery::on(function (string $handled) use (&$html): bool {
                $html = $handled;

                return true;
            }))
            ->andReturnSelf();
        PDF::shouldReceive('setOption')->andReturnSelf();
        PDF::shouldReceive('output')->once()->andReturn('%PDF-1.4 mocksnappy');

        $this->actingAs($user)
            ->get(route('filament.sba.card.print', ['record' => $card->id]))
            ->assertOk();

        $this->assertNotNull($html);
        $this->assertStringContainsString($member->name, $html);
        $this->assertStringContainsString($card->card_number, $html);
        $this->assertStringContainsString('data:image/svg+xml;base64', $html);
        $this->assertStringNotContainsString($member->nik, $html);
    }

    private function fixture(): array
    {
        $organisasi = Organization::factory()->create([
            'name' => 'SPM ' . Str::random(4),
            'location' => 'Jakarta',
            'website' => 'https://example.test',
        ]);
        $user = User::factory()->sbaAdmin($organisasi)->create();
        $member = Member::factory()->for($organisasi)->create(['status' => Member::STATUS_ACTIVE]);

        return [$user, $member];
    }
}
