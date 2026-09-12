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

        $name = 'laporan-federasi-' . now()->format('Y-m-d');
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');

        $path = static::writePdfOrHtml($disk, $name, $html);

        app(AuditLogger::class)->record(
            'export.generated',
            'Laporan operasional federasi dibuat',
        );

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

    /**
     * Render the report as PDF, or as a print-ready HTML page when the Snappy
     * backend (wkhtmltopdf) is not installed on the host.
     */
    private static function writePdfOrHtml($disk, string $name, string $html): string
    {
        $pdfPath = 'exports/' . $name . '.pdf';
        $tmpPath = null;

        try {
            // (** executed: Snappy needs a real on-disk file; render to a
            // temp path, then stream it onto the (possibly S3-backed) disk. **)
            $tmpPath = tempnam(sys_get_temp_dir(), 'report_');
            if ($tmpPath === false) {
                throw new \RuntimeException('Gagal membuat berkas sementara.');
            }

            SnappyPDF::loadHTML($html)
                ->setOption('enable-local-file-access', true)
                ->setOption('page-size', 'A4')
                ->setOption('margin-top', '15mm')
                ->setOption('margin-bottom', '15mm')
                ->setOption('encoding', 'UTF-8')
                ->save($tmpPath);

            $disk->put($pdfPath, (string) file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $pdfPath;
        } catch (\Throwable $e) {
            // (** executed: the wkhtmltopdf binary is not guaranteed on every
            // host (this dev box has none, nor does Vercel's container
            // runtime), which previously surfaced a 500 to the dashboard.
            // Fall back to the same report as an inline print-ready HTML page
            // — ExportDownloadController serves .html inline instead of
            // forcing a download. **)
            report($e);

            if ($tmpPath !== null && is_string($tmpPath)) {
                @unlink($tmpPath);
            }

            $htmlPath = 'exports/' . $name . '.html';
            $disk->put($htmlPath, $html);

            return $htmlPath;
        }
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
