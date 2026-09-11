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

        $real = realpath($disk->path($path));
        $root = realpath($disk->path(''));
        abort_unless(
            $real !== false && $root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR),
            404,
        );
        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        abort_unless($disk->lastModified($path) >= now()->subHour()->timestamp, 404, 'File telah kedaluwarsa.');

        // (** executed: PDF fallback artefacts are plain HTML — serve them
        // inline (print-ready) rather than forcing a .html download. **)
        if (str_ends_with($path, '.html')) {
            return response($disk->get($path))
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response()->download($disk->path($path), basename($path))->deleteFileAfterSend(true);
    }
}
