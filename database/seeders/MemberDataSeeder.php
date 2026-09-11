<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemberDataSeeder extends Seeder
{
    private const DEMO_ORG_SLUGS = ['spm-kecap-bango', 'spm-minuman-segar', 'spm-roti-nusantara'];

    /**
     * Fictional Indonesian placeholder names — this repo is public (SP1 §8);
     * never seed anything resembling a real roster.
     */
    private const DEMO_NAMES = ['Yoga Pratama', 'Siti Rahma', 'Budi Santoso', 'Dewi Lestari', 'Andi Wijaya'];

    private const ATTENDANCE_STATUSES = ['hadir', 'hadir', 'izin', 'tidak_hadir', 'hadir'];

    public function run(): void
    {
        foreach (self::DEMO_ORG_SLUGS as $orgIndex => $slug) {
            $org = Organization::where('slug', $slug)->firstOrFail();

            // Never fight existing data: orgs that already onboarded member data
            // (or keep a federation-manual member_count) are left untouched.
            if ($org->hasMembers()) {
                continue;
            }

            $this->seedOrganization($org, $orgIndex);
        }
    }

    private function seedOrganization(Organization $org, int $orgIndex): void
    {
        $recorder = User::where('role', User::ROLE_SBA_ADMIN)
            ->where('organization_id', $org->id)
            ->first();

        $members = [];

        foreach (self::DEMO_NAMES as $i => $name) {
            // Deterministic fictional 16-digit NIK, unique per (org, nik).
            $nik = (string) (9900000000000000 + $orgIndex * 100 + ($i + 1));

            $member = Member::firstOrCreate(
                ['organization_id' => $org->id, 'nik' => $nik],
                Member::factory()->for($org)->make([
                    'nik' => $nik,
                    'name' => $name,
                ])->getAttributes(),
            );

            $members[] = $member;
        }

        // Dues for the current month (a couple of members also have last month).
        foreach ($members as $i => $member) {
            $currentMonth = now()->format('Y-m');

            Due::firstOrCreate(
                ['member_id' => $member->id, 'period' => $currentMonth],
                Due::factory()->for($org)->for($member)->make([
                    // Factory defaults period to the current month; make it
                    // explicit so the inserted row matches the where clause.
                    'period' => $currentMonth,
                    'amount' => 50000 + ($i * 5000),
                    'recorded_by' => $recorder?->id,
                ])->getAttributes(),
            );

            if ($i >= 3) {
                $lastMonth = now()->subMonth()->format('Y-m');
                Due::firstOrCreate(
                    ['member_id' => $member->id, 'period' => $lastMonth],
                    Due::factory()->for($org)->for($member)->make([
                        'period' => $lastMonth,
                        'amount' => 50000 + ($i * 5000),
                        'recorded_by' => $recorder?->id,
                    ])->getAttributes(),
                );
            }
        }

        // One event with attendance rows for every member (mixed statuses).
        $event = Event::firstOrCreate(
            ['organization_id' => $org->id, 'title' => 'Rapat Anggota ' . $org->name],
            Event::factory()->for($org)->make([
                'title' => 'Rapat Anggota ' . $org->name,
                'description' => 'Agenda rutin organisasi (data demo SP3).',
            ])->getAttributes(),
        );

        foreach ($members as $i => $member) {
            Attendance::firstOrCreate(
                ['event_id' => $event->id, 'member_id' => $member->id],
                Attendance::factory()->for($org)->for($event)->for($member)->make([
                    'status' => self::ATTENDANCE_STATUSES[$i],
                    'note' => null,
                ])->getAttributes(),
            );
        }

        // One complaint, optionally tied to the first member.
        Complaint::firstOrCreate(
            ['organization_id' => $org->id, 'title' => 'Permintaan perbaikan fasilitas (demo)'],
            Complaint::factory()->for($org)->make([
                'reporter_name' => $members[0]->name,
                'member_id' => $members[0]->id,
                'title' => 'Permintaan perbaikan fasilitas (demo)',
                'description' => 'Contoh pengaduan data demo: fasilitas ruang istirahat perlu diperbaiki.',
            ])->getAttributes(),
        );

        // DatabaseSeeder runs with WithoutModelEvents, so the MemberObserver is
        // silent here — sync the real count explicitly (5 active demo members).
        $org->syncMemberCount();
    }
}
