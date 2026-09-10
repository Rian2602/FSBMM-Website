<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Summary Cards -->
        @php
            $stats = $this->getStats();
        @endphp
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Kartu</div>
                <div class="text-3xl font-bold">{{ $stats['total'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Kartu Aktif</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $stats['active'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Kartu Dicabut</div>
                <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['revoked'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Belum Punya Kartu</div>
                <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $stats['without_card'] }}</div>
            </x-filament::section>
        </div>

        <!-- Filter Form -->
        <x-filament::section heading="Filter">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    {{ $this->form }}
                </div>
            </div>
        </x-filament::section>

        <!-- Table -->
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
