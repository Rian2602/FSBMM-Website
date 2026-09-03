@extends('layouts.public')

@section('title', 'E-Learning')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-widest text-accent">Pengembangan Kapasitas</p>
            <h1 class="font-display text-3xl font-bold text-brand-950">E-Learning</h1>
            <p class="mt-2 max-w-2xl text-stone-600">
                Katalog pelatihan untuk pengurus dan anggota. Materi lengkap dengan evaluasi akan hadir menyusul.
            </p>
        </header>

        @if ($courses->isEmpty())
            <p class="rounded border-2 border-dashed border-brand-950/20 p-8 text-center text-stone-500">
                Belum ada pelatihan tersedia.
            </p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <li class="flex flex-col border-2 border-brand-950 bg-white p-6 shadow-[4px_4px_0_0_#073b32]">
                        <span class="self-start border-2 border-brand-950 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-brand-950">
                            {{ match ($course->level) { 'dasar' => 'Dasar', 'menengah' => 'Menengah', default => 'Lanjut' } }}
                        </span>
                        <h2 class="mt-3 font-display text-xl font-bold text-brand-950">{{ $course->title }}</h2>
                        <p class="mt-2 text-sm text-stone-600">{{ $course->description }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
