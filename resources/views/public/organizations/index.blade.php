@extends('layouts.public')

@section('title', 'Direktori SBA')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-widest text-accent">Serikat Pekerja / Buruh</p>
            <h1 class="font-display text-3xl font-bold text-brand-950">Direktori SBA</h1>
            <p class="mt-2 max-w-2xl text-stone-600">
                Daftar serikat pekerja tingkat perusahaan (SBA) yang bernaung di bawah FSBMM.
            </p>
        </header>

        @if ($organizations->isEmpty())
            <p class="rounded border-2 border-dashed border-brand-950/20 p-8 text-center text-stone-500">
                Belum ada SBA terdaftar.
            </p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($organizations as $org)
                    <li class="flex flex-col border-2 border-brand-950 bg-white p-6 shadow-[4px_4px_0_0_#073b32]">
                        <h2 class="font-display text-xl font-bold text-brand-950">
                            <a href="{{ route('organizations.show', $org) }}" class="hover:text-accent">{{ $org->name }}</a>
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-stone-700">{{ $org->company }}</p>
                        @if ($org->location)
                            <p class="mt-3 text-sm text-stone-500">{{ $org->location }}</p>
                        @endif
                        <p class="mt-auto pt-4 text-sm font-semibold text-brand-950">
                            {{ number_format($org->member_count, 0, ',', '.') }} anggota
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
