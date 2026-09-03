@if (! empty($payload['items']))
    <section class="border-y-2 border-brand-950/10 bg-white py-12">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($payload['items'] as $item)
                <div class="border-2 border-brand-950 p-6 text-center">
                    <p class="font-display text-4xl font-bold text-brand-950">{{ $item['value'] }}</p>
                    <p class="mt-1 text-sm font-semibold uppercase tracking-wide text-stone-600">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
