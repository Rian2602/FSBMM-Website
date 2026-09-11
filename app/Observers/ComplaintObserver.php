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

    public function deleted(Complaint $complaint): void
    {
        // (** executed: Fase A review — the bulk/single delete actions used
        // to remove complaints with no audit trace; hook this so every delete
        // (like member.deleted) lands in the append-only trail. **)
        app(AuditLogger::class)->record(
            'complaint.deleted',
            'Pengaduan dihapus',
            $complaint,
            $complaint->organization_id,
        );
    }
}
