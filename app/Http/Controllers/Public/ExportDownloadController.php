<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportDownloadController extends Controller
{
    public function download(Request $request): BinaryFileResponse
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

        return response()->download($disk->path($path), basename($path))->deleteFileAfterSend(true);
    }
}
