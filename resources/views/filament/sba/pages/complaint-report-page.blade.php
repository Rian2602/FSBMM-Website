<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form + Export -->
        <x-filament::section heading="Filter Laporan Pengaduan">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    {{ $this->form }}
                </div>
                <div class="flex gap-2">
                    @php
                        $params = http_build_query(array_filter($this->data ?? []));
                        $orgId = auth()->user()->organization_id;
                    @endphp
                    <a href="{{ url('/panel-sba/complaint-report/export?format=csv&org=' . $orgId . ($params ? '&' . $params : '')) }}"
                       class="filament-button filament-page filament-table:inline-flex items-center gap-1 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10">
                        <x-heroicon-s-arrow-down-tray class="h-4 w-4" />
                        Export CSV
                    </a>
                    <a href="{{ url('/panel-sba/complaint-report/export?format=xlsx&org=' . $orgId . ($params ? '&' . $params : '')) }}"
                       class="filament-button filament-page filament-table:inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500 focus:ring-2 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400">
                        <x-heroicon-s-arrow-down-tray class="h-4 w-4" />
                        Export XLSX
                    </a>
                </div>
            </div>
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