@if (! empty($payload['items']))
    @php
        $chartColors = [
            'bg-vivid-rose',
            'bg-vivid-sky',
            'bg-vivid-violet',
            'bg-vivid-orange',
            'bg-vivid-lime',
            'bg-accent',
        ];
        $textColors = [
            'text-vivid-rose',
            'text-vivid-sky',
            'text-vivid-violet',
            'text-vivid-orange',
            'text-vivid-lime',
            'text-brand-800',
        ];
        $maxValue = collect($payload['items'])->max('value') ?: 1;
    @endphp
    <section class="relative overflow-hidden bg-brand-950 py-16">
        <div class="blob pointer-events-none absolute -right-20 -top-20 h-64 w-64 bg-vivid-violet/25"></div>
        <div class="blob pointer-events-none absolute -bottom-24 -left-16 h-56 w-56 bg-vivid-lime/15"></div>

        <div class="relative mx-auto max-w-6xl px-4">
            @if (! empty($payload['title']))
                <div class="mb-10">
                    <p class="eyebrow-chip">{{ $payload['title'] ?? 'Grafik' }}</p>
                </div>
            @endif

            <div class="space-y-5">
                @foreach ($payload['items'] as $item)
                    @php
                        $value = (int) ($item['value'] ?? 0);
                        $pct = $maxValue > 0 ? ($value / $maxValue) * 100 : 0;
                        $colorIdx = $loop->index % count($chartColors);
                    @endphp
                    <div
                        data-reveal
                        style="transition-delay: {{ $loop->index * 80 }}ms"
                        class="group"
                    >
                        <div class="mb-2 flex items-baseline justify-between">
                            <span class="text-sm font-semibold text-white/90">{{ $item['label'] ?? '' }}</span>
                            <span class="font-display text-lg font-bold {{ $textColors[$colorIdx] }}">
                                {{ number_format($value, 0, ',', '.') }}
                                @if (! empty($item['suffix']))
                                    <span class="text-xs text-white/50">{{ $item['suffix'] }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="h-8 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-full rounded-full {{ $chartColors[$colorIdx] }} transition-all duration-700 ease-out group-hover:brightness-110"
                                style="width: {{ $pct }}%;"
                                role="progressbar"
                                aria-valuenow="{{ $value }}"
                                aria-valuemin="0"
                                aria-valuemax="{{ $maxValue }}"
                                aria-label="{{ $item['label'] ?? '' }}"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
