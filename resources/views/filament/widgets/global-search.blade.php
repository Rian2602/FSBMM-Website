<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-magnifying-glass">
        <x-slot name="heading">
            Pencarian Lintas Modul
        </x-slot>

        <div class="space-y-4">
            {{ $this->form }}

            @if (!empty($this->results))
                <div class="space-y-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($this->results) }} hasil ditemukan
                    </p>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->results as $result)
                            <li class="py-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $result['color'] === 'primary',
                                            'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400' => $result['color'] === 'info',
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $result['color'] === 'warning',
                                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $result['color'] === 'success',
                                        ])>
                                            {{ $result['type'] }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $result['title'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $result['subtitle'] }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ $result['url'] }}" class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                        Buka →
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif (strlen((string) ($this->data['searchQuery'] ?? '')) >= 2)
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada hasil ditemukan.</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
