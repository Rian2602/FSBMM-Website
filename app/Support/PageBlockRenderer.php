<?php

namespace App\Support;

use App\Models\PageBlock;
use Illuminate\Support\Facades\View;

class PageBlockRenderer
{
    public function render(PageBlock $block): string
    {
        $view = 'blocks.'.$block->type;

        abort_unless(View::exists($view), 500, "Tipe blok belum terdaftar: {$block->type}");

        return view($view, ['payload' => $block->payload ?? []])->render();
    }
}
