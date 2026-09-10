<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form -->
        <x-filament::section heading="Filter Laporan Pengaduan">
            {{ $this->form }}
        </x-filament::section>

        <!-- Summary Cards -->
        @php
            $stats = $this->getStats();
        @endphp
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total Pengaduan
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ $this->getDateContext() }})</span>
                </div>
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $stats['total'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Baru</div>
                <div class="text-3xl font-bold text-info-600 dark:text-info-400">{{ $stats['baru'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Diproses</div>
                <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $stats['diproses'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Selesai</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $stats['selesai'] }}</div>
            </x-filament::section>
            <x-filament::section class="md:col-span-2">
                <div class="text-sm text-gray-500 dark:text-gray-400">Belum Selesai (Open)</div>
                <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['open'] }}</div>
            </x-filament::section>
        </div>

        <!-- Table -->
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>