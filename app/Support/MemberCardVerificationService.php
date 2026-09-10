<?php

namespace App\Support;

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

        $status = match ($card->status) {
            'aktif' => 'active',
            'dicabut' => 'revoked',
            default => 'unknown',
        };

        return [
            'status' => $status,
            'card_number' => $card->card_number,
            'member_name' => $card->member->name,
            'organization_name' => $card->member->organization->name,
            'issued_at' => $card->issued_at?->format('d/m/Y'),
            'valid_until' => $card->issued_at?->addYear()->format('d/m/Y'),
        ];
    }
}
