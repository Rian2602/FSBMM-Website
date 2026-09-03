<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::published()
            ->with(['category', 'author'])
            ->orderByDesc('published_at');

        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        return view('public.articles.index', [
            'articles' => $query->get(),
            'categories' => Category::orderBy('name')->get(),
            'activeCategory' => $categorySlug ?? null,
        ]);
    }

    public function show(Article $article)
    {
        abort_unless($article->published_at?->isPast(), 404);

        return view('public.articles.show', ['article' => $article]);
    }
}
