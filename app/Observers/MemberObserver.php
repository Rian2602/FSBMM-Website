<?php

namespace App\Observers;

use App\Models\Member;
use App\Support\AuditLogger;

class MemberObserver
{
    public function created(Member $member): void
    {
        $member->organization->syncMemberCount();

        $this->audit('member.created', 'Data anggota baru ditambahkan', $member);
    }

    public function updated(Member $member): void
    {
        if ($member->wasChanged('status')) {
            $member->organization->syncMemberCount();
        }

        $this->audit('member.updated', 'Data anggota diperbarui ('.$this->changedFields($member).')', $member);
    }

    public function deleted(Member $member): void
    {
        $member->organization->syncMemberCount();

        $this->audit('member.deleted', 'Data anggota dihapus', $member);
    }

    public function restored(Member $member): void
    {
        $member->organization->syncMemberCount();

        $this->audit('member.restored', 'Data anggota dipulihkan', $member);
    }

    /**
     * Audit entry with a PII-free payload: only the changed column names are
     * recorded (never the member name/NIK/address/salary values).
     */
    private function audit(string $action, string $description, Member $member): void
    {
        app(AuditLogger::class)->record($action, $description, $member, $member->organization_id);
    }

    private function changedFields(Member $member): string
    {
        $fields = array_keys($member->getChanges());
        $fields = array_values(array_diff($fields, ['updated_at']));

        return $fields === [] ? 'tidak ada perubahan' : implode(', ', $fields);
    }
}
