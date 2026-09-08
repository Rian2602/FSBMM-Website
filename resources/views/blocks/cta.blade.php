<section class="relative overflow-hidden bg-linear-to-r from-vivid-amber/15 via-vivid-rose/10 to-vivid-violet/15 py-14">
    <div class="blob pointer-events-none absolute -right-20 -top-20 h-64 w-64 bg-vivid-violet/20"></div>
    <div class="blob pointer-events-none absolute -bottom-24 -left-16 h-56 w-56 bg-vivid-lime/20"></div>

    <div data-reveal class="relative mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 rounded-3xl bg-white px-8 py-10 shadow-lg ring-1 ring-brand-950/5 sm:flex-row sm:items-center">
        <div>
            @if (! empty($payload['title']))
                <h2 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">{{ $payload['title'] }}</h2>
            @endif
            @if (! empty($payload['body']))
                <p class="mt-2 max-w-2xl text-stone-600">{{ $payload['body'] }}</p>
            @endif
        </div>
        @if (! empty($payload['label']))
            <a href="{{ $payload['url'] ?? '#' }}" class="btn-vivid shrink-0">{{ $payload['label'] }}</a>
        @endif
    </div>
</section>
