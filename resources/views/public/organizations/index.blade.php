@extends('layouts.public')

@section('title', 'Direktori SBA')

@section('meta')
    <meta name="description" content="Direktori serikat pekerja tingkat perusahaan (SBA) yang bernaung di bawah Federasi Serikat Buruh Makanan dan Minuman (FSBMM).">
@endsection

@section('content')
    <section class="relative mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-24 top-6 h-64 w-64 bg-vivid-amber/15"></div>
        <div class="blob pointer-events-none absolute left-1/4 top-40 h-48 w-48 bg-vivid-violet/10"></div>

        <header class="relative mb-10 max-w-2xl">
            <p class="eyebrow">Serikat Pekerja / Buruh</p>
            <h1 class="mt-1 font-display text-3xl font-bold text-brand-950 sm:text-4xl">Direktori SBA</h1>
            <p class="mt-3 text-stone-600">
                Daftar serikat pekerja tingkat perusahaan (SBA) yang bernaung di bawah FSBMM.
            </p>
            <span class="mt-4 block h-1.5 w-24 rounded-full bg-linear-to-r from-vivid-sky via-vivid-violet to-vivid-rose"></span>
        </header>

        @if ($organizations->isEmpty())
            <p class="relative rounded-2xl border-2 border-dashed border-brand-950/20 p-10 text-center text-stone-500">
                Belum ada SBA terdaftar.
            </p>
        @else
            @php
                $accents = [
                    ['strip' => 'bg-vivid-rose', 'chip' => 'bg-vivid-rose/10 text-vivid-rose', 'icon' => 'text-vivid-rose'],
                    ['strip' => 'bg-vivid-sky', 'chip' => 'bg-vivid-sky/10 text-vivid-sky', 'icon' => 'text-vivid-sky'],
                    ['strip' => 'bg-vivid-violet', 'chip' => 'bg-vivid-violet/10 text-vivid-violet', 'icon' => 'text-vivid-violet'],
                    ['strip' => 'bg-vivid-amber', 'chip' => 'bg-vivid-amber/15 text-vivid-orange', 'icon' => 'text-vivid-orange'],
                    ['strip' => 'bg-vivid-lime', 'chip' => 'bg-vivid-lime/15 text-brand-800', 'icon' => 'text-brand-600'],
                ];
            @endphp
            <div data-live-search class="relative">
                <label for="sba-search" class="sr-only">Cari SBA</label>
                <div class="relative mb-6 max-w-md">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input id="sba-search" type="search" data-live-search-input placeholder="Cari nama SBA, perusahaan, atau lokasi…" class="search-input !pl-11">
                </div>
                <p data-live-search-empty hidden class="mb-6 rounded-2xl border-2 border-dashed border-brand-950/20 p-6 text-center text-stone-500">
                    Tidak ada SBA yang cocok dengan pencarianmu.
                </p>
                <ul class="relative grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($organizations as $org)
                    @php
                        $a = $accents[$loop->index % count($accents)];
                        $searchText = \Illuminate\Support\Str::lower($org->name.' '.$org->company.' '.$org->location);
                    @endphp
                    <li
                        data-live-search-item
                        data-search-text="{{ $searchText }}"
                        data-reveal
                        style="transition-delay: {{ min($loop->index, 8) * 60 }}ms"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-brand-950/10 bg-white shadow-sm transition-all duration-200 hover:-translate-y-1.5 hover:shadow-xl"
                    >
                        <span class="h-1.5 w-full {{ $a['strip'] }} transition-all duration-300 group-hover:h-2"></span>
                        <div class="flex flex-1 flex-col p-6">
                            <h2 class="font-display text-xl font-bold text-brand-950">
                                <a href="{{ route('organizations.show', $org) }}" class="transition-colors hover:text-brand-600">{{ $org->name }}</a>
                            </h2>
                            @if ($org->company)
                                <p class="mt-1 text-sm font-semibold text-stone-700">{{ $org->company }}</p>
                            @endif
                            @if ($org->location)
                                <p class="mt-3 flex items-center gap-1.5 text-sm text-stone-500">
                                    <svg class="h-4 w-4 shrink-0 {{ $a['icon'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ $org->location }}
                                </p>
                            @endif
                            <p class="mt-auto pt-5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold {{ $a['chip'] }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                                    </svg>
                                    {{ number_format($org->member_count, 0, ',', '.') }} anggota
                                </span>
                            </p>
                        </div>
                    </li>
                @endforeach
                </ul>
            </div>
        @endif
    </section>
@endsection
