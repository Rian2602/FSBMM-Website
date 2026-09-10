<?php

namespace App\Support;

use App\Models\Member;
use App\Models\MemberCard;

class MemberCardVerificationService
{
    public function lookup(string $token): ?array
    {
        $card = MemberCard::where('verification_token', $token)
            ->with('member.organization')
            ->first();

        if (! $card) {
            return null;
        }

        $status = match (true) {
            $card->status === MemberCard::STATUS_REVOKED => 'revoked',
            $card->member?->status !== Member::STATUS_ACTIVE => 'inactive',
            default => 'active',
        };

        return [
            'status' => $status,
            'card_number' => $card->card_number,
            'member_name' => $card->member->name,
            'organization_name' => $card->member->organization->name,
            'issued_at' => $card->issued_at?->format('d/m/Y'),
        ];
    }
}
