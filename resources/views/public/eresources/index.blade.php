@extends('layouts.public')

@section('title', 'E-Resource')

@section('meta')
    <meta name="description" content="Pustaka digital dokumen resmi, template, dan materi pendidikan FSBMM untuk pengurus dan anggota SBA.">
@endsection

@section('content')
    <section class="relative mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-24 top-4 h-64 w-64 bg-vivid-rose/10"></div>
        <div class="blob pointer-events-none absolute left-1/4 top-52 h-48 w-48 bg-vivid-sky/15"></div>

        <header class="relative mb-10 max-w-2xl">
            <p class="eyebrow">Pustaka Digital</p>
            <h1 class="mt-1 font-display text-3xl font-bold text-brand-950 sm:text-4xl">E-Resource</h1>
            <p class="mt-3 text-stone-600">
                Dokumen resmi, template, dan materi pendidikan untuk pengurus dan anggota SBA.
            </p>
            <span class="mt-4 block h-1.5 w-24 rounded-full bg-linear-to-r from-vivid-orange via-vivid-rose to-vivid-violet"></span>
        </header>

        @if ($eresources->isEmpty())
            <p class="relative rounded-2xl border-2 border-dashed border-brand-950/20 p-10 text-center text-stone-500">
                Belum ada dokumen tersedia.
            </p>
        @else
            @php
                $accents = [
                    ['tile' => 'bg-vivid-rose/10 text-vivid-rose', 'btn' => 'from-vivid-rose to-vivid-violet'],
                    ['tile' => 'bg-vivid-sky/10 text-vivid-sky', 'btn' => 'from-vivid-sky to-vivid-violet'],
                    ['tile' => 'bg-vivid-amber/15 text-vivid-orange', 'btn' => 'from-vivid-orange to-vivid-rose'],
                    ['tile' => 'bg-vivid-violet/10 text-vivid-violet', 'btn' => 'from-vivid-violet to-vivid-sky'],
                    ['tile' => 'bg-vivid-lime/15 text-brand-700', 'btn' => 'from-vivid-lime to-vivid-sky'],
                ];
            @endphp
            <div data-live-search class="relative">
                <label for="eresource-search" class="sr-only">Cari dokumen</label>
                <div class="relative mb-6 max-w-md">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input id="eresource-search" type="search" data-live-search-input placeholder="Cari judul atau deskripsi dokumen…" class="search-input !pl-11">
                </div>
                <p data-live-search-empty hidden class="mb-6 rounded-2xl border-2 border-dashed border-brand-950/20 p-6 text-center text-stone-500">
                    Tidak ada dokumen yang cocok dengan pencarianmu.
                </p>
                <ul class="relative grid gap-6 sm:grid-cols-2">
                @foreach ($eresources as $resource)
                    @php
                        $a = $accents[$loop->index % count($accents)];
                        $searchText = \Illuminate\Support\Str::lower($resource->title.' '.$resource->description);
                    @endphp
                    <li
                        data-live-search-item
                        data-search-text="{{ $searchText }}"
                        data-reveal
                        style="transition-delay: {{ min($loop->index, 8) * 60 }}ms"
                        class="group flex flex-col rounded-2xl border border-brand-950/10 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1.5 hover:shadow-xl"
                    >
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-110 group-hover:-rotate-3 {{ $a['tile'] }}">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </span>
                            <h2 class="font-display text-xl font-bold text-brand-950">{{ $resource->title }}</h2>
                        </div>
                        @if ($resource->description)
                            <p class="mt-3 text-sm text-stone-600">{{ $resource->description }}</p>
                        @endif
                        <div class="mt-auto flex items-center justify-between gap-3 pt-6">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-100/60 px-3 py-1 text-xs font-bold text-brand-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                                </svg>
                                {{ number_format($resource->downloads_count, 0, ',', '.') }}× diunduh
                            </span>
                            <a
                                href="{{ URL::temporarySignedRoute('eresources.download', now()->addMinutes(30), ['eresource' => $resource->slug]) }}"
                                data-download-feedback
                                class="inline-flex items-center gap-1.5 rounded-full bg-linear-to-r px-4 py-2 text-sm font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md {{ $a['btn'] }}"
                            >
                                Unduh PDF
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4" />
                                </svg>
                            </a>
                        </div>
                    </li>
                @endforeach
                </ul>
            </div>
        @endif
    </section>
@endsection
