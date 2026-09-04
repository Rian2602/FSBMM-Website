<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Sba\Resources\EventResource\Pages\EditEvent;
use App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaEventAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (EventTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/events')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_sba_events(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/events')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/events')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_events_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Event::factory()->for($org)->create(['title' => 'Rapat Bulanan Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Event::factory()->for($other)->create(['title' => 'Rapat Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/events')
            ->assertOk()
            ->assertSee('Rapat Bulanan Saya')
            ->assertDontSee('Rapat Orang Lain');
    }

    public function test_editing_another_organizations_event_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create();
        $event = Event::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/events/'.$event->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_an_event_works(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateEvent::class)
            ->fillForm([
                'title' => 'Rapat Koordinasi',
                'event_date' => now()->format('Y-m-d'),
                'description' => 'Agenda bulanan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'organization_id' => $org->id,
            'title' => 'Rapat Koordinasi',
        ]);
    }

    public function test_adding_attendance_for_own_member_works(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create(['name' => 'Anggota Hadir']);

        Livewire::actingAs($user)
            ->test(AttendancesRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'hadir',
                'note' => 'Hadir tepat waktu',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'status' => 'hadir',
        ]);
    }

    public function test_adding_attendance_for_foreign_member_fails(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $other = Organization::factory()->create();
        $foreignMember = Member::factory()->for($other)->create();

        Livewire::actingAs($user)
            ->test(AttendancesRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('create', data: [
                'member_id' => $foreignMember->id,
                'status' => 'hadir',
            ])
            ->assertHasTableActionErrors(['member_id']);
    }

    public function test_duplicate_attendance_fails(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create();
        Attendance::factory()->for($event)->for($member)->for($org)->create();

        Livewire::actingAs($user)
            ->test(AttendancesRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'izin',
            ])
            ->assertHasTableActionErrors(['member_id']);
    }

    public function test_attendance_status_must_be_valid(): void
    {
        [$user, $org] = $this->sbaUser();
        $event = Event::factory()->for($org)->create();
        $member = Member::factory()->for($org)->create();

        Livewire::actingAs($user)
            ->test(AttendancesRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->callTableAction('create', data: [
                'member_id' => $member->id,
                'status' => 'status_tidak_valid',
            ])
            ->assertHasTableActionErrors(['status']);
    }
}
