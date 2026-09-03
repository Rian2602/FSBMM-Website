@extends('layouts.public')

@section('title', 'Berita')

@section('meta')
    <meta name="description" content="Berita dan informasi terbaru dari Federasi Serikat Buruh Makanan dan Minuman (FSBMM) untuk pengurus dan anggota SBA.">
@endsection

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-widest text-accent">Informasi &amp; Kabar</p>
            <h1 class="font-display text-3xl font-bold text-brand-950">Berita</h1>
        </header>

        @if ($categories->isNotEmpty())
            <nav class="mb-8 flex flex-wrap gap-2" aria-label="Filter kategori">
                <a href="{{ route('articles.index') }}"
                   class="border-2 border-brand-950 px-3 py-1 text-sm font-semibold {{ $activeCategory ? 'bg-white text-brand-950' : 'bg-brand-950 text-white' }}">
                    Semua
                </a>
                @foreach ($categories as $category)
                    <a href="{{ route('articles.index', ['category' => $category->slug]) }}"
                       class="border-2 border-brand-950 px-3 py-1 text-sm font-semibold {{ $activeCategory === $category->slug ? 'bg-brand-950 text-white' : 'bg-white text-brand-950 hover:bg-brand-100' }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($articles->isEmpty())
            <p class="rounded border-2 border-dashed border-brand-950/20 p-8 text-center text-stone-500">
                Belum ada berita{{ $activeCategory ? ' dalam kategori ini' : '' }}.
            </p>
        @else
            <ul class="divide-y-2 divide-brand-950/10 border-y-2 border-brand-950/10">
                @foreach ($articles as $article)
                    <li class="flex gap-6 py-6">
                        @if ($article->cover_image_path)
                            <a href="{{ route('articles.show', $article) }}" class="shrink-0">
                                <img
                                    src="{{ asset('storage/'.$article->cover_image_path) }}"
                                    alt="Sampul {{ $article->title }}"
                                    class="h-24 w-36 border-2 border-brand-950 object-cover"
                                >
                            </a>
                        @endif
                        <div class="min-w-0">
                            @if ($article->category)
                                <span class="text-xs font-bold uppercase tracking-widest text-accent">{{ $article->category->name }}</span>
                            @endif
                            <h2 class="mt-1 font-display text-xl font-bold text-brand-950">
                                <a href="{{ route('articles.show', $article) }}" class="hover:text-accent">{{ $article->title }}</a>
                            </h2>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ $article->published_at->translatedFormat('d F Y') }}
                            </p>
                            <p class="mt-2 line-clamp-2 text-stone-600">{{ $article->excerpt }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
