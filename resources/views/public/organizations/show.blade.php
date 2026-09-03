@extends('layouts.public')

@section('title', $organization->name)

@section('meta')
    @if ($organization->description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($organization->description), 160) }}">
    @endif
@endsection

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12">
        <p class="mb-4">
            <a href="{{ route('organizations.index') }}" class="text-sm font-semibold text-brand-950 underline decoration-accent underline-offset-4 hover:text-accent">&larr; Kembali ke direktori</a>
        </p>

        <article class="border-2 border-brand-950 bg-white p-8 shadow-[4px_4px_0_0_#073b32]">
            <header class="flex flex-wrap items-start gap-6">
                @if ($organization->logo_path)
                    <img
                        src="{{ asset('storage/'.$organization->logo_path) }}"
                        alt="Logo {{ $organization->name }}"
                        class="h-20 w-20 border-2 border-brand-950 object-contain"
                    >
                @endif
                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-3xl font-bold text-brand-950">{{ $organization->name }}</h1>
                    @if ($organization->company)
                        <p class="mt-1 font-semibold text-stone-700">{{ $organization->company }}</p>
                    @endif
                </div>
            </header>

            @if ($organization->description)
                {{-- Staff-authored RichEditor HTML (trusted authors only) — same convention as article body, Task 5. --}}
                <div class="rich-text mt-6 text-stone-700">{!! $organization->description !!}</div>
            @endif

            <dl class="mt-8 grid gap-4 border-t-2 border-brand-950/10 pt-6 text-sm sm:grid-cols-2">
                @if ($organization->location)
                    <div>
                        <dt class="font-bold uppercase tracking-wide text-brand-950">Lokasi</dt>
                        <dd class="mt-1 text-stone-600">{{ $organization->location }}</dd>
                    </div>
                @endif
                @if ($organization->founded_year)
                    <div>
                        <dt class="font-bold uppercase tracking-wide text-brand-950">Berdiri</dt>
                        <dd class="mt-1 text-stone-600">{{ $organization->founded_year }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="font-bold uppercase tracking-wide text-brand-950">Anggota</dt>
                    <dd class="mt-1 text-stone-600">{{ number_format($organization->member_count, 0, ',', '.') }} pekerja</dd>
                </div>
                @if ($organization->website)
                    <div>
                        <dt class="font-bold uppercase tracking-wide text-brand-950">Website</dt>
                        <dd class="mt-1">
                            <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer" class="text-brand-950 underline decoration-accent underline-offset-4 hover:text-accent">{{ $organization->website }}</a>
                        </dd>
                    </div>
                @endif
            </dl>
        </article>
    </section>
@endsection
