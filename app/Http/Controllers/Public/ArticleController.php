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
            'categories' => Category::withCount([
                'articles' => fn ($q) => $q->published(),
            ])->orderBy('name')->get()->filter(fn ($category) => $category->articles_count > 0),
            'activeCategory' => $categorySlug ?? null,
        ]);
    }

    public function show(Article $article)
    {
        abort_unless($article->published_at?->isPast(), 404);

        $article->load(['category', 'author']);

        return view('public.articles.show', ['article' => $article]);
    }
}
