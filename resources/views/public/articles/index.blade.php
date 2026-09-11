@extends('layouts.public')

@section('title', 'Berita')

@section('meta')
    <meta name="description" content="Berita dan informasi terbaru dari Federasi Serikat Buruh Makanan dan Minuman (FSBMM) untuk pengurus dan anggota SBA.">
@endsection

@section('content')
    <section class="relative mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-24 top-4 h-64 w-64 bg-vivid-sky/15"></div>
        <div class="blob pointer-events-none absolute left-1/3 top-32 h-48 w-48 bg-vivid-rose/10"></div>

        <header class="relative mb-10 max-w-2xl">
            <p class="eyebrow">Informasi &amp; Kabar</p>
            <h1 class="mt-1 font-display text-3xl font-bold text-brand-950 sm:text-4xl">Berita</h1>
            <span class="mt-4 block h-1.5 w-24 rounded-full bg-linear-to-r from-vivid-amber via-vivid-rose to-vivid-violet"></span>
        </header>

        @if ($categories->isNotEmpty())
            @php
                $chipColors = [
                    ['border' => 'border-vivid-rose/60', 'text' => 'text-vivid-rose', 'active' => 'bg-vivid-rose text-white', 'hover' => 'hover:bg-vivid-rose/10'],
                    ['border' => 'border-vivid-sky/60', 'text' => 'text-vivid-sky', 'active' => 'bg-vivid-sky text-white', 'hover' => 'hover:bg-vivid-sky/10'],
                    ['border' => 'border-vivid-violet/60', 'text' => 'text-vivid-violet', 'active' => 'bg-vivid-violet text-white', 'hover' => 'hover:bg-vivid-violet/10'],
                    ['border' => 'border-vivid-amber/60', 'text' => 'text-vivid-orange', 'active' => 'bg-vivid-orange text-white', 'hover' => 'hover:bg-vivid-orange/10'],
                    ['border' => 'border-vivid-lime/60', 'text' => 'text-vivid-lime', 'active' => 'bg-vivid-lime text-brand-950', 'hover' => 'hover:bg-vivid-lime/10'],
                ];
            @endphp
            <nav class="relative mb-10 flex flex-wrap gap-2" aria-label="Filter kategori">
                <a href="{{ route('articles.index') }}"
                   class="rounded-full border-2 px-4 py-1.5 text-sm font-bold transition-all hover:-translate-y-0.5 {{ $activeCategory ? 'border-brand-950/20 bg-white text-brand-950 hover:border-brand-950' : 'bg-brand-950 text-white shadow-md' }}">
                    Semua
                </a>
                @foreach ($categories as $category)
                    @php
                        $c = $chipColors[$loop->index % count($chipColors)];
                    @endphp
                    <a href="{{ route('articles.index', ['category' => $category->slug]) }}"
                       class="rounded-full border-2 px-4 py-1.5 text-sm font-bold transition-all hover:-translate-y-0.5 {{ $activeCategory === $category->slug ? $c['active'].' shadow-md' : $c['border'].' bg-white '.$c['text'].' '.$c['hover'].' hover:border-brand-950' }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($articles->isEmpty())
            <p class="relative rounded-2xl border-2 border-dashed border-brand-950/20 p-10 text-center text-stone-500">
                Belum ada berita{{ $activeCategory ? ' dalam kategori ini' : '' }}.
            </p>
        @else
            @php
                $rowStyles = [
                    ['border' => 'border-vivid-rose', 'text' => 'text-vivid-rose'],
                    ['border' => 'border-vivid-sky', 'text' => 'text-vivid-sky'],
                    ['border' => 'border-vivid-violet', 'text' => 'text-vivid-violet'],
                    ['border' => 'border-vivid-amber', 'text' => 'text-vivid-orange'],
                    ['border' => 'border-vivid-lime', 'text' => 'text-brand-700'],
                ];
            @endphp
            <div data-live-search class="relative">
                <label for="article-search" class="sr-only">Cari berita</label>
                <div class="relative mb-6 max-w-md">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input id="article-search" type="search" data-live-search-input placeholder="Cari judul atau isi berita…" class="search-input !pl-11">
                </div>
                <p data-live-search-empty hidden class="mb-6 rounded-2xl border-2 border-dashed border-brand-950/20 p-6 text-center text-stone-500">
                    Tidak ada berita yang cocok dengan pencarianmu.
                </p>
                <ul class="relative grid gap-6">
                @foreach ($articles as $article)
                    @php
                        $row = $rowStyles[$loop->index % count($rowStyles)];
                        $searchText = \Illuminate\Support\Str::lower($article->title.' '.$article->excerpt.' '.($article->category->name ?? ''));
                    @endphp
                    <li
                        data-live-search-item
                        data-search-text="{{ $searchText }}"
                        data-reveal
                        style="transition-delay: {{ min($loop->index, 8) * 60 }}ms"
                        class="group flex flex-col gap-5 overflow-hidden rounded-2xl border border-brand-950/10 border-l-4 {{ $row['border'] }} bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand-950/10 sm:flex-row sm:items-center sm:p-5"
                    >
                        @if ($article->cover_image_path)
                            <a href="{{ route('articles.show', $article) }}" class="block shrink-0 overflow-hidden rounded-xl">
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path) }}"
                                    alt="Sampul {{ $article->title }}"
                                    class="h-40 w-full object-cover transition-transform duration-300 group-hover:scale-105 sm:h-24 sm:w-36"
                                    loading="lazy"
                                >
                            </a>
                        @endif
                        <div class="min-w-0">
                            @if ($article->category)
                                <span class="text-xs font-bold uppercase tracking-widest {{ $row['text'] }}">{{ $article->category->name }}</span>
                            @endif
                            <h2 class="mt-1 font-display text-xl font-bold text-brand-950">
                                <a href="{{ route('articles.show', $article) }}" class="transition-colors hover:text-brand-600">{{ $article->title }}</a>
                            </h2>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-stone-500">
                                <svg class="h-4 w-4 text-vivid-violet" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $article->published_at->translatedFormat('d F Y') }}
                            </p>
                            <p class="mt-2 line-clamp-2 text-stone-600">{{ $article->excerpt }}</p>
                        </div>
                    </li>
                @endforeach
                </ul>
            </div>
        @endif
    </section>
@endsection
