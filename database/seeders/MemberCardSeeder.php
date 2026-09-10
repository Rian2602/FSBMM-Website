<?php

namespace Database\Seeders;

use App\Models\MemberCard;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MemberCardSeeder extends Seeder
{
    private const DEMO_ORG_SLUG = 'spm-kecap-bango';

    public function run(): void
    {
        $org = Organization::where('slug', self::DEMO_ORG_SLUG)->first();
        if ($org === null) {
            return;
        }

        if (MemberCard::where('organization_id', $org->id)->exists()) {
            return;
        }

        $demoMembers = $org->members()->orderBy('id')->get();
        if ($demoMembers->count() < 2) {
            return;
        }

        $creator = User::where('role', User::ROLE_SBA_ADMIN)
            ->where('organization_id', $org->id)
            ->first()?->id;

        MemberCard::firstOrCreate(
            ['card_number' => 'FSBMM-'.now()->format('Y').'-'.strtoupper(Str::random(8))],
            [
                'organization_id' => $org->id,
                'member_id' => $demoMembers[0]->id,
                'verification_token' => bin2hex(random_bytes(32)),
                'status' => MemberCard::STATUS_ACTIVE,
                'issued_at' => now()->subWeeks(2),
                'created_by' => $creator,
            ]
        );

        MemberCard::firstOrCreate(
            ['card_number' => 'FSBMM-'.now()->format('Y').'-'.strtoupper(Str::random(8))],
            [
                'organization_id' => $org->id,
                'member_id' => $demoMembers[1]->id,
                'verification_token' => bin2hex(random_bytes(32)),
                'status' => MemberCard::STATUS_REVOKED,
                'issued_at' => now()->subWeeks(3),
                'revoked_at' => now()->subWeek(),
                'revocation_reason' => 'Penggantian kartu',
                'created_by' => $creator,
            ]
        );
    }
}
