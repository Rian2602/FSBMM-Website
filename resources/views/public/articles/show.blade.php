@extends('layouts.public')

@section('title', $article->title)

@section('meta')
    @if ($article->excerpt)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article->excerpt), 160) }}">
    @endif
@endsection

@section('content')
    <article class="relative mx-auto max-w-3xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-28 top-8 h-64 w-64 bg-vivid-violet/10"></div>
        <div class="blob pointer-events-none absolute -left-28 top-56 h-56 w-56 bg-vivid-sky/10"></div>

        <p class="relative mb-6">
            <a href="{{ route('articles.index') }}" class="inline-flex items-center gap-1.5 rounded-full border-2 border-brand-950/15 bg-white px-4 py-1.5 text-sm font-bold text-brand-950 shadow-sm transition-all hover:-translate-y-0.5 hover:border-vivid-rose hover:text-vivid-rose">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Kembali ke Berita
            </a>
        </p>

        <header class="relative rounded-3xl border border-brand-950/10 bg-white p-6 shadow-sm sm:p-8">
            <span class="mb-4 inline-block h-1.5 w-16 rounded-full bg-linear-to-r from-vivid-amber via-vivid-rose to-vivid-violet"></span>
            @if ($article->category)
                <p>
                    <span class="rounded-full bg-linear-to-r from-vivid-rose to-vivid-violet px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                        {{ $article->category->name }}
                    </span>
                </p>
            @endif
            <h1 class="mt-3 font-display text-3xl font-bold leading-tight text-brand-950 sm:text-4xl">{{ $article->title }}</h1>
            <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-stone-500">
                @if ($article->author)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-linear-to-br from-vivid-sky to-vivid-violet text-[10px] font-bold text-white">
                            {{ strtoupper(\Illuminate\Support\Str::substr($article->author->name, 0, 1)) }}
                        </span>
                        {{ $article->author->name }}
                    </span>
                    <span aria-hidden="true">&middot;</span>
                @endif
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-vivid-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ $article->published_at->translatedFormat('d F Y') }}
                </span>
            </p>

            <button
                type="button"
                data-copy-link="{{ route('articles.show', $article) }}"
                class="mt-5 inline-flex items-center gap-1.5 rounded-full border-2 border-brand-950/15 bg-white px-4 py-1.5 text-sm font-bold text-brand-950 shadow-sm transition-all hover:-translate-y-0.5 hover:border-vivid-sky hover:text-vivid-sky"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" />
                </svg>
                Salin tautan
            </button>
        </header>

        @if ($article->cover_image_path)
            <div class="relative mt-6 rounded-3xl bg-linear-to-br from-vivid-sky via-vivid-violet to-vivid-rose p-1.5 shadow-lg">
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path) }}"
                    alt="Sampul {{ $article->title }}"
                    class="w-full rounded-[calc(1.5rem-4px)] object-cover"
                    loading="lazy"
                >
            </div>
        @endif

        {{-- Trusted staff-authored RichEditor HTML (authors are authenticated federation staff) — same convention as Organization description. --}}
        <div data-reveal class="rich-text relative mt-8 text-stone-700">{!! $article->body !!}</div>

        <div class="relative mt-10 border-t border-dashed border-brand-950/20 pt-6">
            <a href="{{ route('articles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-vivid-violet transition-colors hover:text-brand-950">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                </svg>
                Berita lainnya
            </a>
        </div>
    </article>
@endsection
