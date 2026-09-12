<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
    public function download(Request $request)
    {
        abort_unless(Auth::check(), 403);
        abort_unless((int) Auth::user()->organization_id === (int) $request->query('org'), 403);

        $disk = Storage::disk('local');
        $path = (string) $request->query('file');

        // (** executed: logical traversal guard — S3-backed disks have no
        // realpath(). Reject absolute paths and any '..' segment. File names
        // are generated server-side (ReportExport/FederationReportGenerator),
        // so a prefix check on 'exports/' is enough. **)
        abort_unless(str_starts_with($path, 'exports/') && ! str_contains($path, '..') && ! str_starts_with($path, '/'), 404);
        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        abort_unless($disk->lastModified($path) >= now()->subHour()->timestamp, 404, 'File telah kedaluwarsa.');

        // (** executed: PDF fallback artefacts are plain HTML — serve them
        // inline (print-ready) rather than forcing a .html download. **)
        if (str_ends_with($path, '.html')) {
            return response($disk->get($path))
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // (** executed: response()->download() needs a local path, which is
        // the norm everywhere except the Vercel container runtime (S3-backed
        // 'local' disk). Stream from object storage when there is no local
        // path; the 1-hour TTL + periodic sweep drop the object later. **)
        if ((config('filesystems.disks.local.driver') ?? 'local') === 'local') {
            return response()->download($disk->path($path), basename($path))->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($disk, $path) {
            echo $disk->get($path);
        }, basename($path));
    }
}
