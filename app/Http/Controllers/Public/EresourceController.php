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

        // Fail cleanly if the stored path would resolve outside the public
        // disk root (Flysystem/OS would otherwise turn '../' into a traversal).
        $real = realpath($disk->path($path));
        $root = realpath($disk->path(''));

        abort_unless(
            $real !== false && $root !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR),
            404,
            'File tidak ditemukan.',
        );

        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');

        $eresource->increment('downloads_count');

        return $disk->download($path);
    }
}
