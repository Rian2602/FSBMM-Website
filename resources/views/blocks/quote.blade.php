@if (! empty($payload['quote']))
    <section class="mx-auto max-w-4xl px-4 py-14">
        <blockquote class="border-l-4 border-accent pl-6">
            <p class="font-display text-2xl font-bold leading-snug text-brand-950">{{ $payload['quote'] }}</p>
            @if (! empty($payload['author']))
                <footer class="mt-3 text-sm font-semibold text-stone-600">— {{ $payload['author'] }}</footer>
            @endif
        </blockquote>
    </section>
@endif
