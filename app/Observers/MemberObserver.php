<?php

namespace App\Observers;

use App\Models\Member;

class MemberObserver
{
    public function created(Member $member): void
    {
        $member->organization->syncMemberCount();
    }

    public function updated(Member $member): void
    {
        if ($member->wasChanged('status')) {
            $member->organization->syncMemberCount();
        }
    }

    public function deleted(Member $member): void
    {
        $member->organization->syncMemberCount();
    }

    public function restored(Member $member): void
    {
        $member->organization->syncMemberCount();
    }
}
