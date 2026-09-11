@extends('layouts.public')

@section('title', $organization->name)

@section('meta')
    @if ($organization->description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($organization->description), 160) }}">
    @endif
@endsection

@section('content')
    <section class="relative mx-auto max-w-4xl px-4 py-12 sm:py-16">
        <div class="blob pointer-events-none absolute -right-24 top-10 h-64 w-64 bg-vivid-sky/15"></div>
        <div class="blob pointer-events-none absolute -left-24 top-72 h-56 w-56 bg-vivid-rose/10"></div>

        <p class="relative mb-6">
            <a href="{{ route('organizations.index') }}" class="inline-flex items-center gap-1.5 rounded-full border-2 border-brand-950/15 bg-white px-4 py-1.5 text-sm font-bold text-brand-950 shadow-sm transition-all hover:-translate-y-0.5 hover:border-vivid-violet hover:text-vivid-violet">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Kembali ke direktori
            </a>
        </p>

        <article data-reveal class="relative overflow-hidden rounded-3xl border border-brand-950/10 bg-white shadow-lg">
            <div class="rainbow-bar h-2 w-full" aria-hidden="true"></div>
            <div class="p-6 sm:p-10">
                <header class="flex flex-wrap items-start gap-6">
                    @if ($organization->logo_path)
                        <div class="rounded-2xl bg-linear-to-br from-vivid-sky via-vivid-violet to-vivid-rose p-1">
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($organization->logo_path) }}"
                                alt="Logo {{ $organization->name }}"
                                class="h-20 w-20 rounded-[calc(1rem-2px)] border border-white/40 bg-white object-contain"
                            >
                        </div>
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

                <dl class="mt-8 grid gap-4 border-t border-dashed border-brand-950/15 pt-6 text-sm sm:grid-cols-2">
                    @if ($organization->location)
                        <div class="flex items-start gap-3 rounded-2xl bg-vivid-sky/10 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-vivid-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold uppercase tracking-widest text-vivid-sky">Lokasi</dt>
                                <dd class="mt-1 text-stone-700">{{ $organization->location }}</dd>
                            </div>
                        </div>
                    @endif
                    @if ($organization->founded_year)
                        <div class="flex items-start gap-3 rounded-2xl bg-vivid-amber/15 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-vivid-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold uppercase tracking-widest text-vivid-orange">Berdiri</dt>
                                <dd class="mt-1 text-stone-700">{{ $organization->founded_year }}</dd>
                            </div>
                        </div>
                    @endif
                    <div class="flex items-start gap-3 rounded-2xl bg-vivid-rose/10 p-4">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-vivid-rose" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                        </svg>
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-widest text-vivid-rose">Anggota</dt>
                            <dd class="mt-1 font-display text-lg font-bold text-brand-950">{{ number_format($organization->member_count, 0, ',', '.') }} pekerja</dd>
                        </div>
                    </div>
                    @if ($organization->website)
                        {{-- Only http(s) values become links; anything else (e.g. javascript:) renders as inert text. --}}
                        @php
                            $isSafeWebsite = str_starts_with($organization->website, 'http://') || str_starts_with($organization->website, 'https://');
                        @endphp
                        <div class="flex items-start gap-3 rounded-2xl bg-vivid-violet/10 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-vivid-violet" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5m10.5-2.5l1.5-1.5a4 4 0 10-5.656-5.656l-4 4a4 4 0 105.656 5.656" />
                            </svg>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold uppercase tracking-widest text-vivid-violet">Website</dt>
                                <dd class="mt-1 break-all">
                                    @if ($isSafeWebsite)
                                        <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer" class="link-underline">{{ $organization->website }}</a>
                                    @else
                                        <span class="text-stone-500">{{ $organization->website }}</span>
                                    @endif
                                </dd>
                            </div>
                        </div>
                    @endif
                </dl>
            </div>
        </article>
    </section>
@endsection
