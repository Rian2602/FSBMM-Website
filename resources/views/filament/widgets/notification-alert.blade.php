<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell-alert">
        <x-slot name="heading">
            Pemberitahuan & Alert
        </x-slot>

        @php
            $alerts = $this->getAlerts();
        @endphp

        @if (empty($alerts))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Tidak ada pemberitahuan saat ini. Semua berjalan lancar! ✅
            </p>
        @else
            <div class="space-y-3">
                @foreach ($alerts as $alert)
                    <a
                        href="{{ $alert['url'] }}"
                        @class([
                            'flex items-start gap-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:hover:bg-white/5',
                            'border-warning-200 bg-warning-50 dark:border-warning-500/20 dark:bg-warning-500/5' => $alert['type'] === 'warning',
                            'border-info-200 bg-info-50 dark:border-info-500/20 dark:bg-info-500/5' => $alert['type'] === 'info',
                            'border-success-200 bg-success-50 dark:border-success-500/20 dark:bg-success-500/5' => $alert['type'] === 'success',
                        ])
                    >
                        <div @class([
                            'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                            'bg-warning-100 text-warning-600 dark:bg-warning-500/20 dark:text-warning-400' => $alert['type'] === 'warning',
                            'bg-info-100 text-info-600 dark:bg-info-500/20 dark:text-info-400' => $alert['type'] === 'info',
                            'bg-success-100 text-success-600 dark:bg-success-500/20 dark:text-success-400' => $alert['type'] === 'success',
                        ])>
                            <x-dynamic-component :component="$alert['icon']" class="h-4 w-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $alert['title'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ $alert['message'] }}
                            </p>
                            @if (!empty($alert['details']))
                                <ul class="mt-2 space-y-1">
                                    @foreach ($alert['details'] as $detail)
                                        <li class="text-xs text-gray-500 dark:text-gray-400">
                                            • {{ $detail['text'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div class="shrink-0">
                            <x-heroicon-s-chevron-right class="h-5 w-5 text-gray-400" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
