<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only accountability trail for panel/admin actions.
 *
 * Records a denormalised actor snapshot (name + role) so the entry survives an
 * account deletion, plus the owning organization. Descriptions must stay
 * **PII-free**: the federation-side viewer (`/admin/audit-trail`) is
 * super_admin-only but still must not expose individual member data
 * (see the federation PII-minimization policy in AGENTS.md), so never pass a
 * member name, NIK, address, salary, or user-supplied complaint text here.
 */
class AuditLogger
{
    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        ?int $organizationId = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'role' => $actor?->role,
            'organization_id' => $organizationId ?? $actor?->organization_id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()->ip(),
        ]);
    }
}
