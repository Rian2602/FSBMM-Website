<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Eresource;
use Illuminate\Support\Facades\Storage;

class EresourceController extends Controller
{
    public function index()
    {
        return view('public.eresources.index', [
            'eresources' => Eresource::published()->orderBy('title')->get(),
        ]);
    }

    public function download(Eresource $eresource)
    {
        abort_unless($eresource->is_published, 404);

        $disk = Storage::disk('public');
        $path = $eresource->file_path;

        // (** executed: Vercel container runtime has no local filesystem path
        // for S3-backed disks, so the realpath() traversal guard is replaced
        // with a logical one — reject absolute paths and any '..' segments. **)
        abort_unless(! str_contains($path, '..') && ! str_starts_with($path, '/'), 404, 'File tidak ditemukan.');

        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');

        $eresource->increment('downloads_count');

        return $disk->download($path);
    }
}
