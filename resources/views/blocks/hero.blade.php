<section class="relative overflow-hidden bg-brand-950 py-20 text-white sm:py-28">
    {{-- Decorative multi-color glow blobs — pure CSS, drift slowly, no assets. --}}
    <div class="pointer-events-none absolute inset-0 opacity-50" style="background: radial-gradient(55% 45% at 85% 0%, var(--color-brand-600) 0%, transparent 70%);"></div>
    <div class="blob animate-drift pointer-events-none absolute -top-16 right-[12%] h-72 w-72 bg-vivid-violet/40"></div>
    <div class="blob animate-drift-slow pointer-events-none absolute bottom-[-6rem] left-[8%] h-80 w-80 bg-vivid-sky/30"></div>
    <div class="blob animate-drift pointer-events-none absolute -right-16 top-1/3 h-64 w-64 bg-vivid-rose/30" style="animation-delay: 4s;"></div>
    <div class="blob pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 bg-accent/15"></div>

    <div class="relative mx-auto max-w-6xl px-4">
        @if (! empty($payload['eyebrow']))
            <p class="eyebrow-chip animate-rise">{{ $payload['eyebrow'] }}</p>
        @endif
        <h1 class="animate-rise rise-delay-1 mt-3 max-w-3xl font-display text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">{{ $payload['title'] ?? '' }}</h1>
        @if (! empty($payload['subtitle']))
            <p class="animate-rise rise-delay-2 mt-5 max-w-2xl text-lg text-white/80">{{ $payload['subtitle'] }}</p>
        @endif
        @if (! empty($payload['cta_label']))
            <p class="animate-rise rise-delay-3 mt-8 flex flex-wrap gap-3">
                <a href="{{ $payload['cta_url'] ?? '#' }}" class="btn-vivid">
                    {{ $payload['cta_label'] }}
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </p>
        @endif
        @if (! empty($payload['image_path']))
            <div class="animate-rise rise-delay-3 mt-12 rounded-2xl bg-linear-to-br from-vivid-amber via-vivid-rose to-vivid-violet p-1 shadow-2xl">
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payload['image_path']) }}"
                    alt=""
                    class="max-h-96 w-full rounded-[calc(1rem-2px)] object-cover"
                    loading="lazy"
                >
            </div>
        @endif
    </div>
</section>
