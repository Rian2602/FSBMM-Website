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

        abort_unless(Storage::disk('public')->exists($eresource->file_path), 404, 'File tidak ditemukan.');

        $eresource->increment('downloads_count');

        return Storage::disk('public')->download($eresource->file_path);
    }
}
