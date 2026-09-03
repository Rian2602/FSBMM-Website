@extends('layouts.public')

@section('title', $page->meta_title ?: $page->title)

@section('meta')
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
@endsection

@section('content')
    @foreach ($page->blocks as $block)
        {!! app(\App\Support\PageBlockRenderer::class)->render($block) !!}
    @endforeach

    @if ($page->slug === 'home')
        @if (($homeArticles ?? null)?->isNotEmpty())
            <section class="mx-auto max-w-6xl px-4 py-12">
                <div class="mb-6 flex items-end justify-between border-b-2 border-brand-950 pb-2">
                    <h2 class="font-display text-2xl font-bold text-brand-950">Berita Terbaru</h2>
                    <a href="{{ route('articles.index') }}" class="text-sm font-semibold text-brand-950 underline decoration-accent underline-offset-4 hover:text-accent">Semua berita &rarr;</a>
                </div>
                <ul class="grid gap-6 sm:grid-cols-3">
                    @foreach ($homeArticles as $article)
                        <li class="flex flex-col border-2 border-brand-950 bg-white p-5">
                            @if ($article->category)
                                <span class="text-xs font-bold uppercase tracking-widest text-accent">{{ $article->category->name }}</span>
                            @endif
                            <h3 class="mt-1 font-display text-lg font-bold leading-snug text-brand-950">
                                <a href="{{ route('articles.show', $article) }}" class="hover:text-accent">{{ $article->title }}</a>
                            </h3>
                            <p class="mt-auto pt-3 text-xs text-stone-500">{{ $article->published_at->translatedFormat('d F Y') }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (($homeResources ?? null)?->isNotEmpty())
            <section class="border-y-2 border-brand-950/10 bg-white py-12">
                <div class="mx-auto max-w-6xl px-4">
                    <div class="mb-6 flex items-end justify-between border-b-2 border-brand-950 pb-2">
                        <h2 class="font-display text-2xl font-bold text-brand-950">Unduhan</h2>
                        <a href="{{ route('eresources.index') }}" class="text-sm font-semibold text-brand-950 underline decoration-accent underline-offset-4 hover:text-accent">Semua e-resource &rarr;</a>
                    </div>
                    <ul class="grid gap-4 sm:grid-cols-3">
                        @foreach ($homeResources as $resource)
                            <li class="flex items-center justify-between gap-3 border-2 border-brand-950/20 p-4">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-brand-950">{{ $resource->title }}</h3>
                                    <p class="text-xs text-stone-500">{{ number_format($resource->downloads_count, 0, ',', '.') }}× diunduh</p>
                                </div>
                                <a
                                    href="{{ URL::temporarySignedRoute('eresources.download', now()->addMinutes(30), ['eresource' => $resource->slug]) }}"
                                    class="shrink-0 border-2 border-brand-950 bg-accent px-3 py-1.5 text-xs font-bold text-brand-950"
                                >
                                    Unduh
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    @endif
@endsection
