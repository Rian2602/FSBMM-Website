@if (! empty($payload['quote']))
    <section class="mx-auto max-w-4xl px-4 py-16">
        <div data-reveal class="rounded-2xl bg-linear-to-r from-vivid-amber via-vivid-rose to-vivid-violet p-1 shadow-lg">
            <blockquote class="relative rounded-[calc(1rem-2px)] bg-brand-950 px-8 py-8 sm:px-10">
                <svg class="absolute left-4 top-5 h-9 w-9 text-vivid-rose" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true">
                    <path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14H8c0-1.1.9-2 2-2V8zm14 0c-3.3 0-6 2.7-6 6v10h10V14h-6c0-1.1.9-2 2-2V8z"/>
                </svg>
                <p class="font-display text-2xl font-bold leading-snug text-white sm:text-3xl">{{ $payload['quote'] }}</p>
                @if (! empty($payload['author']))
                    <footer class="mt-5 flex items-center gap-2 text-sm font-bold uppercase tracking-wide">
                        <span class="inline-block h-3 w-3 rounded-full bg-linear-to-r from-vivid-amber to-vivid-rose"></span>
                        <span class="text-vivid-amber">— {{ $payload['author'] }}</span>
                    </footer>
                @endif
            </blockquote>
        </div>
    </section>
@endif
