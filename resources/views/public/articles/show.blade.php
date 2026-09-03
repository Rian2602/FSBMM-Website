@extends('layouts.public')

@section('title', $article->title)

@section('meta')
    @if ($article->excerpt)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article->excerpt), 160) }}">
    @endif
@endsection

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-12">
        <p class="mb-6">
            <a href="{{ route('articles.index') }}" class="text-sm font-semibold text-brand-950 underline decoration-accent underline-offset-4 hover:text-accent">&larr; Kembali ke Berita</a>
        </p>

        @if ($article->category)
            <span class="text-xs font-bold uppercase tracking-widest text-accent">{{ $article->category->name }}</span>
        @endif
        <h1 class="mt-2 font-display text-3xl font-bold text-brand-950">{{ $article->title }}</h1>
        <p class="mt-3 text-sm text-stone-500">
            {{ $article->published_at->translatedFormat('d F Y') }}
            @if ($article->author)
                &middot; {{ $article->author->name }}
            @endif
        </p>

        @if ($article->cover_image_path)
            <img
                src="{{ asset('storage/'.$article->cover_image_path) }}"
                alt="Sampul {{ $article->title }}"
                class="mt-8 border-2 border-brand-950 object-cover"
            >
        @endif

        {{-- Trusted staff-authored RichEditor HTML (authors are authenticated federation staff) — same convention as Organization description. --}}
        <div class="rich-text mt-8 text-stone-700">{!! $article->body !!}</div>
    </article>
@endsection
