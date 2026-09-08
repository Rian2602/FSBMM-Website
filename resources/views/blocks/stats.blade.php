@if (! empty($payload['items']))
    @php
        $numberColors = ['text-vivid-orange', 'text-vivid-rose', 'text-vivid-violet', 'text-vivid-sky', 'text-vivid-lime'];
    @endphp
    <section class="bg-white py-16">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($payload['items'] as $item)
                @php
                    $rawValue = (string) ($item['value'] ?? '');
                    preg_match('/[\d.,]+/', $rawValue, $numMatch);
                    $digits = isset($numMatch[0]) ? preg_replace('/\D/', '', $numMatch[0]) : '';
                    $counterTarget = $digits !== '' ? (int) $digits : null;
                    $counterSuffix = $counterTarget !== null ? trim(str_replace($numMatch[0], '', $rawValue)) : '';
                @endphp
                <div
                    data-reveal
                    style="transition-delay: {{ $loop->index * 90 }}ms"
                    class="group rounded-2xl border border-brand-950/10 bg-brand-100/40 p-6 text-center shadow-sm transition-all hover:-translate-y-1.5 hover:shadow-xl"
                >
                    <p
                        class="font-display text-4xl font-bold transition-transform group-hover:scale-110 {{ $numberColors[$loop->index % count($numberColors)] }}"
                        @if ($counterTarget !== null)
                            data-counter
                            data-counter-target="{{ $counterTarget }}"
                            data-counter-suffix="{{ $counterSuffix }}"
                        @endif
                    >
                        {{ $counterTarget !== null ? '0' . $counterSuffix : $rawValue }}
                    </p>
                    <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-brand-600">{{ $item['label'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
