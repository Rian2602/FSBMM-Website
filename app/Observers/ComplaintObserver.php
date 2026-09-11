<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Support\AuditLogger;

class ComplaintObserver
{
    public function created(Complaint $complaint): void
    {
        app(AuditLogger::class)->record(
            'complaint.created',
            'Pengaduan baru dicatat',
            $complaint,
            $complaint->organization_id,
        );
    }

    public function updated(Complaint $complaint): void
    {
        if (! $complaint->wasChanged('status')) {
            return;
        }

        app(AuditLogger::class)->record(
            'complaint.status_changed',
            'Status pengaduan: '.$complaint->getOriginal('status').' → '.$complaint->status,
            $complaint,
            $complaint->organization_id,
        );
    }
}
