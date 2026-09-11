<?php

namespace App\Support;

use App\Models\Member;
use App\Models\MemberCard;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MemberCardService
{
    public function issue(Member $member, User $creator): MemberCard
    {
        $this->guardEligible($member, $creator);

        $this->resolveCurrentCard($member)?->update([
            'status' => MemberCard::STATUS_REVOKED,
            'revoked_at' => now(),
            'revocation_reason' => 'Digantikan kartu baru',
        ]);

        $card = MemberCard::create([
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'card_number' => $this->generateUniqueCardNumber(),
            'verification_token' => $this->generateVerificationToken(),
            'status' => MemberCard::STATUS_ACTIVE,
            'issued_at' => now(),
            'created_by' => $creator->id,
        ]);

        app(AuditLogger::class)->record(
            'card.issued',
            'Kartu anggota diterbitkan (' . $card->card_number . ')',
            $card,
            $card->organization_id,
            $creator,
        );

        return $card;
    }

    public function revoke(MemberCard $card, string $reason, User $actor): MemberCard
    {
        if ($card->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Kartu bukan milik organisasi Anda.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pencabutan wajib diisi.');
        }

        $card->update([
            'status' => MemberCard::STATUS_REVOKED,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        app(AuditLogger::class)->record(
            'card.revoked',
            'Kartu anggota dicabut (' . $card->card_number . ')',
            $card,
            $card->organization_id,
            $actor,
        );

        return $card;
    }

    public function reissue(MemberCard $oldCard, User $actor): MemberCard
    {
        $this->revoke($oldCard, 'Penggantian kartu', $actor);

        return $this->issue($oldCard->member, $actor);
    }

    public function resolveCurrentCard(Member $member): ?MemberCard
    {
        return MemberCard::where('member_id', $member->id)
            ->where('status', MemberCard::STATUS_ACTIVE)
            ->latest('id')
            ->first();
    }

    public function generateCardNumber(): string
    {
        return 'FSBMM-' . now()->format('Y') . '-' . strtoupper(Str::random(8));
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function validateToken(string $token): ?MemberCard
    {
        $card = MemberCard::where('verification_token', $token)->first();

        if (! $card) {
            return null;
        }

        if ($card->status === MemberCard::STATUS_REVOKED) {
            return $card;
        }

        return $card->member?->status === Member::STATUS_ACTIVE ? $card : null;
    }

    public function getCardHistory(Member $member): Collection
    {
        return MemberCard::where('member_id', $member->id)
            ->with('creator')
            ->orderByDesc('id')
            ->get();
    }

    protected function generateUniqueCardNumber(): string
    {
        // ponytail: exists()-lalu-create tidak atomic (race hanya di demo-scale);
        // DB unique index 5.1 = safety net terakhir.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $number = $this->generateCardNumber();
            if (! MemberCard::where('card_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Gagal menghasilkan nomor kartu unik setelah 3 percobaan.');
    }

    protected function guardEligible(Member $member, User $creator): void
    {
        if ($member->organization_id !== $creator->organization_id) {
            throw new AuthorizationException('Kartu hanya bisa diterbitkan untuk anggota organisasi sendiri.');
        }

        if ($member->status !== Member::STATUS_ACTIVE) {
            throw new DomainException('Kartu hanya bisa diterbitkan untuk anggota berstatus aktif.');
        }
    }
}
