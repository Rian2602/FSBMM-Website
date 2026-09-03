@extends('layouts.public')

@section('title', 'E-Resource')

@section('meta')
    <meta name="description" content="Pustaka digital dokumen resmi, template, dan materi pendidikan FSBMM untuk pengurus dan anggota SBA.">
@endsection

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-widest text-accent">Pustaka Digital</p>
            <h1 class="font-display text-3xl font-bold text-brand-950">E-Resource</h1>
            <p class="mt-2 max-w-2xl text-stone-600">
                Dokumen resmi, template, dan materi pendidikan untuk pengurus dan anggota SBA.
            </p>
        </header>

        @if ($eresources->isEmpty())
            <p class="rounded border-2 border-dashed border-brand-950/20 p-8 text-center text-stone-500">
                Belum ada dokumen tersedia.
            </p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2">
                @foreach ($eresources as $resource)
                    <li class="flex flex-col border-2 border-brand-950 bg-white p-6">
                        <h2 class="font-display text-xl font-bold text-brand-950">{{ $resource->title }}</h2>
                        @if ($resource->description)
                            <p class="mt-2 text-sm text-stone-600">{{ $resource->description }}</p>
                        @endif
                        <div class="mt-auto flex items-center justify-between pt-5">
                            <span class="text-xs font-semibold text-stone-500">
                                {{ number_format($resource->downloads_count, 0, ',', '.') }}× diunduh
                            </span>
                            <a
                                href="{{ URL::signedRoute('eresources.download', ['eresource' => $resource->slug]) }}"
                                class="border-2 border-brand-950 bg-accent px-4 py-2 text-sm font-bold text-brand-950"
                            >
                                Unduh PDF
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
