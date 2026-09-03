<section class="bg-brand-100 py-12">
    <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-4 sm:flex-row sm:items-center">
        <div>
            @if (! empty($payload['title']))
                <h2 class="font-display text-2xl font-bold text-brand-950">{{ $payload['title'] }}</h2>
            @endif
            @if (! empty($payload['body']))
                <p class="mt-1 max-w-2xl text-stone-700">{{ $payload['body'] }}</p>
            @endif
        </div>
        @if (! empty($payload['label']))
            <a href="{{ $payload['url'] ?? '#' }}" class="shrink-0 border-2 border-brand-950 bg-accent px-6 py-3 font-bold text-brand-950">{{ $payload['label'] }}</a>
        @endif
    </div>
</section>
