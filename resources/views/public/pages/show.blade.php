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
            @php
                $catColors = ['text-vivid-rose', 'text-vivid-sky', 'text-vivid-violet', 'text-vivid-orange'];
                $barGradients = ['bg-linear-to-r from-vivid-rose to-vivid-orange', 'bg-linear-to-r from-vivid-sky to-vivid-violet', 'bg-linear-to-r from-vivid-violet to-vivid-rose', 'bg-linear-to-r from-vivid-orange to-vivid-amber'];
            @endphp
            <section class="relative mx-auto max-w-6xl px-4 py-16">
                <div class="blob pointer-events-none absolute -right-24 top-10 h-56 w-56 bg-vivid-rose/10"></div>
                <div class="mb-8 flex flex-wrap items-end justify-between gap-3 border-b border-brand-950/10 pb-4">
                    <h2 class="flex items-center gap-3 font-display text-2xl font-bold text-brand-950 sm:text-3xl">
                        <span class="inline-block h-8 w-2 rounded-full bg-linear-to-b from-vivid-amber via-vivid-rose to-vivid-violet"></span>
                        Berita Terbaru
                    </h2>
                    <a href="{{ route('articles.index') }}" class="link-underline text-sm font-semibold">Semua berita &rarr;</a>
                </div>
                <ul class="grid gap-6 sm:grid-cols-3">
                    @foreach ($homeArticles as $article)
                        @php
                            $cat = $catColors[$loop->index % count($catColors)];
                            $bar = $barGradients[$loop->index % count($barGradients)];
                        @endphp
                        <li data-reveal style="transition-delay: {{ $loop->index * 90 }}ms" class="group flex flex-col overflow-hidden rounded-2xl border border-brand-950/10 bg-white shadow-sm transition-all duration-200 hover:-translate-y-1.5 hover:shadow-xl">
                            @if ($article->cover_image_path)
                                <a href="{{ route('articles.show', $article) }}" class="block overflow-hidden">
                                    <img
                                        src="{{ asset('storage/'.$article->cover_image_path) }}"
                                        alt="Sampul {{ $article->title }}"
                                        class="h-40 w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    >
                                </a>
                            @endif
                            <div class="flex flex-1 flex-col p-5">
                                @if ($article->category)
                                    <span class="text-xs font-bold uppercase tracking-widest {{ $cat }}">{{ $article->category->name }}</span>
                                @endif
                                <h3 class="mt-1 font-display text-lg font-bold leading-snug text-brand-950">
                                    <a href="{{ route('articles.show', $article) }}" class="transition-colors hover:text-brand-600">{{ $article->title }}</a>
                                </h3>
                                <p class="mt-auto flex items-center gap-1.5 pt-4 text-xs text-stone-500">
                                    <span class="h-1.5 w-6 rounded-full {{ $bar }}"></span>
                                    {{ $article->published_at->translatedFormat('d F Y') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (($homeResources ?? null)?->isNotEmpty())
            @php
                $tints = [
                    ['icon' => 'bg-vivid-rose/10 text-vivid-rose', 'btn' => 'bg-linear-to-r from-vivid-rose to-vivid-violet text-white'],
                    ['icon' => 'bg-vivid-sky/10 text-vivid-sky', 'btn' => 'bg-linear-to-r from-vivid-sky to-vivid-violet text-white'],
                    ['icon' => 'bg-vivid-amber/15 text-vivid-orange', 'btn' => 'bg-linear-to-r from-vivid-orange to-vivid-rose text-white'],
                ];
            @endphp
            <section class="relative overflow-hidden bg-brand-950 py-16">
                <div class="blob pointer-events-none absolute -right-20 -top-20 h-64 w-64 bg-vivid-violet/25"></div>
                <div class="blob pointer-events-none absolute -bottom-24 -left-16 h-56 w-56 bg-vivid-lime/15"></div>
                <div class="relative mx-auto max-w-6xl px-4">
                    <div class="mb-8 flex flex-wrap items-end justify-between gap-3 border-b border-white/10 pb-4">
                        <h2 class="flex items-center gap-3 font-display text-2xl font-bold text-white sm:text-3xl">
                            <span class="inline-block h-8 w-2 rounded-full bg-linear-to-b from-vivid-amber via-vivid-lime to-vivid-sky"></span>
                            Unduhan
                        </h2>
                        <a href="{{ route('eresources.index') }}" class="text-sm font-bold text-vivid-amber transition-colors hover:text-white">Semua e-resource &rarr;</a>
                    </div>
                    <ul class="grid gap-4 sm:grid-cols-3">
                        @foreach ($homeResources as $resource)
                            @php
                                $t = $tints[$loop->index % count($tints)];
                            @endphp
                            <li data-reveal style="transition-delay: {{ $loop->index * 90 }}ms" class="group flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur transition-all duration-200 hover:-translate-y-1 hover:border-white/25 hover:bg-white/10 sm:flex-row sm:items-center">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-110 {{ $t['icon'] }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-white">{{ $resource->title }}</h3>
                                    <p class="text-xs text-white/60">{{ number_format($resource->downloads_count, 0, ',', '.') }}× diunduh</p>
                                </div>
                                <a
                                    href="{{ URL::temporarySignedRoute('eresources.download', now()->addMinutes(30), ['eresource' => $resource->slug]) }}"
                                    data-download-feedback
                                    class="shrink-0 rounded-full px-4 py-2 text-xs font-bold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg {{ $t['btn'] }}"
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
