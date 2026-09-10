<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form -->
        <x-filament::section heading="Filter Laporan Iuran">
            {{ $this->form }}
        </x-filament::section>

        <!-- Summary Cards -->
        @php
            $stats = $this->getStats();
        @endphp
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Pembayaran</div>
                <div class="text-3xl font-bold">{{ $stats['payment_count'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Nominal</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                    Rp {{ number_format((float) $stats['total_amount'], 0, ',', '.') }}
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Rata-rata Nominal</div>
                <div class="text-3xl font-bold">
                    Rp {{ number_format((float) $stats['average_amount'], 0, ',', '.') }}
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Anggota Aktif</div>
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $stats['active_members'] }}</div>
            </x-filament::section>
            <x-filament::section class="md:col-span-2">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Belum Tercatat Membayar
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ $this->getPeriodContext() }})</span>
                </div>
                <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['members_without_dues'] }}</div>
            </x-filament::section>
        </div>

        <!-- Table -->
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>