@extends('layouts.public')

@section('title', 'E-Learning')

@section('meta')
    <meta name="description" content="Katalog pelatihan e-learning FSBMM untuk pengembangan kapasitas pengurus dan anggota serikat pekerja.">
@endsection

@section('content')
    <section class="relative mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-24 top-4 h-64 w-64 bg-vivid-lime/15"></div>
        <div class="blob pointer-events-none absolute left-1/4 top-48 h-48 w-48 bg-vivid-rose/10"></div>

        <header class="relative mb-10 max-w-2xl">
            <p class="eyebrow">Pengembangan Kapasitas</p>
            <h1 class="mt-1 font-display text-3xl font-bold text-brand-950 sm:text-4xl">E-Learning</h1>
            <p class="mt-3 text-stone-600">
                Katalog pelatihan untuk pengurus dan anggota. Materi lengkap dengan evaluasi akan hadir menyusul.
            </p>
            <span class="mt-4 block h-1.5 w-24 rounded-full bg-linear-to-r from-vivid-lime via-vivid-amber to-vivid-rose"></span>
        </header>

        @if ($courses->isEmpty())
            <p class="relative rounded-2xl border-2 border-dashed border-brand-950/20 p-10 text-center text-stone-500">
                Belum ada pelatihan tersedia.
            </p>
        @else
            @php
                $levelStyles = [
                    'dasar' => [
                        'badge' => 'bg-vivid-sky/15 text-vivid-sky',
                        'strip' => 'bg-vivid-sky',
                        'icon' => 'text-vivid-sky',
                    ],
                    'menengah' => [
                        'badge' => 'bg-vivid-amber/20 text-vivid-orange',
                        'strip' => 'bg-vivid-amber',
                        'icon' => 'text-vivid-orange',
                    ],
                    'lanjut' => [
                        'badge' => 'bg-linear-to-r from-vivid-rose to-vivid-violet text-white',
                        'strip' => 'bg-linear-to-r from-vivid-rose to-vivid-violet',
                        'icon' => 'text-white',
                    ],
                ];
                $levelNames = ['dasar' => 'Dasar', 'menengah' => 'Menengah', 'lanjut' => 'Lanjut'];
            @endphp
            <div data-live-search class="relative">
                <label for="course-search" class="sr-only">Cari pelatihan</label>
                <div class="relative mb-6 max-w-md">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input id="course-search" type="search" data-live-search-input placeholder="Cari judul atau topik pelatihan…" class="search-input !pl-11">
                </div>
                <p data-live-search-empty hidden class="mb-6 rounded-2xl border-2 border-dashed border-brand-950/20 p-6 text-center text-stone-500">
                    Tidak ada pelatihan yang cocok dengan pencarianmu.
                </p>
                <ul class="relative grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    @php
                        $s = $levelStyles[$course->level] ?? $levelStyles['lanjut'];
                        $searchText = \Illuminate\Support\Str::lower($course->title.' '.$course->description.' '.($levelNames[$course->level] ?? ''));
                    @endphp
                    <li
                        data-live-search-item
                        data-search-text="{{ $searchText }}"
                        data-reveal
                        style="transition-delay: {{ min($loop->index, 8) * 60 }}ms"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-brand-950/10 bg-white shadow-sm transition-all duration-200 hover:-translate-y-1.5 hover:shadow-xl"
                    >
                        <span class="h-1.5 w-full {{ $s['strip'] }}"></span>
                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $s['badge'] }}">
                                    <svg class="h-3.5 w-3.5 {{ $s['icon'] ?? '' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    {{ $levelNames[$course->level] ?? $course->level }}
                                </span>
                            </div>
                            <h2 class="mt-3 font-display text-xl font-bold text-brand-950">{{ $course->title }}</h2>
                            <p class="mt-2 flex-1 text-sm text-stone-600">{{ $course->description }}</p>
                        </div>
                    </li>
                @endforeach
                </ul>
            </div>
        @endif
    </section>
@endsection
