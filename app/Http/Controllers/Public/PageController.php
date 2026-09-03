<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Eresource;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::published()->where('slug', $slug)->with('blocks')->firstOrFail();

        $data = ['page' => $page];

        // Home is the only page that mixes a dynamic feed under its blocks;
        // the queries live here (not in the view) so every page render stays
        // data-driven and testable.
        if ($page->slug === 'home') {
            $data['homeArticles'] = Article::published()
                ->with('category')
                ->latest('published_at')
                ->take(3)
                ->get();

            $data['homeResources'] = Eresource::published()
                ->orderByDesc('downloads_count')
                ->take(3)
                ->get();
        }

        return view('public.pages.show', $data);
    }
}
