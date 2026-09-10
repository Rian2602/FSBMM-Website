<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use SnappyPDF;

class FederationReportGenerator
{
    /**
     * Generate a PDF report for federation leaders.
     * Returns a signed URL to download the PDF.
     */
    public static function generate(): string
    {
        $data = static::gatherData();
        $html = view('reports.federation-summary', $data)->render();

        $name = 'laporan-federasi-'.now()->format('Y-m-d').'.pdf';
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');

        $path = 'exports/'.$name;
        SnappyPDF::loadHTML($html)
            ->setOption('enable-local-file-access', true)
            ->setOption('page-size', 'A4')
            ->setOption('margin-top', '15mm')
            ->setOption('margin-bottom', '15mm')
            ->setOption('encoding', 'UTF-8')
            ->save($disk->path($path));

        // Sweep expired exports
        static::sweepExpired($disk);

        return URL::temporarySignedRoute('exports.download', now()->addHour(), [
            'file' => $path,
            'org' => 0, // federation-level report, org check bypassed for super_admin
        ]);
    }

    private static function gatherData(): array
    {
        $currentMonth = now()->format('Y-m');

        // Organizations
        $organizations = Organization::orderBy('name')->get();
        $totalOrgs = $organizations->count();
        $totalMembers = (int) Organization::sum('member_count');

        // Dues
        $totalDues = (float) Due::where('period', $currentMonth)->sum('amount');
        $paidMembers = Due::where('period', $currentMonth)->distinct('member_id')->count('member_id');
        $pendingDues = max(0, $totalMembers - $paidMembers);
        $duesRate = $totalMembers > 0 ? round($paidMembers / $totalMembers * 100, 1) : 0;

        // Events
        $totalEvents = Event::count();
        $upcomingEvents = Event::where('event_date', '>=', now())
            ->orderBy('event_date')
            ->limit(5)
            ->get();

        // Complaints
        $openComplaints = Complaint::where('status', '!=', 'selesai')->count();
        $newComplaints = Complaint::where('status', 'baru')->count();
        $processingComplaints = Complaint::where('status', 'diproses')->count();
        $resolvedComplaints = Complaint::where('status', 'selesai')->count();

        // Per-SBA breakdown
        $sbaBreakdown = $organizations->map(function ($org) use ($currentMonth) {
            $orgDues = Due::where('organization_id', $org->id)
                ->where('period', $currentMonth)
                ->sum('amount');
            $orgComplaints = Complaint::where('organization_id', $org->id)
                ->where('status', '!=', 'selesai')
                ->count();
            $orgEvents = Event::where('organization_id', $org->id)->count();

            return [
                'name' => $org->name,
                'members' => (int) $org->member_count,
                'dues' => $orgDues,
                'complaints' => $orgComplaints,
                'events' => $orgEvents,
            ];
        });

        return [
            'generated_at' => now()->format('d F Y H:i'),
            'period' => now()->format('F Y'),
            'total_orgs' => $totalOrgs,
            'total_members' => $totalMembers,
            'total_dues' => $totalDues,
            'paid_members' => $paidMembers,
            'pending_dues' => $pendingDues,
            'dues_rate' => $duesRate,
            'total_events' => $totalEvents,
            'upcoming_events' => $upcomingEvents,
            'open_complaints' => $openComplaints,
            'new_complaints' => $newComplaints,
            'processing_complaints' => $processingComplaints,
            'resolved_complaints' => $resolvedComplaints,
            'sba_breakdown' => $sbaBreakdown,
        ];
    }

    private static function sweepExpired($disk): void
    {
        foreach ($disk->files('exports') as $file) {
            if ($disk->lastModified($file) < now()->subHour()->timestamp) {
                $disk->delete($file);
            }
        }
    }
}
