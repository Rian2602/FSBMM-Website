<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form + Export -->
        <x-filament::section heading="Filter Laporan Kegiatan &amp; Absensi">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    {{ $this->form }}
                </div>
                <div class="flex gap-2">
                    @php
                        $params = http_build_query(array_filter($this->data ?? []));
                        $orgId = auth()->user()->organization_id;
                    @endphp
                    <a href="{{ url('/panel-sba/attendance-report/export?format=csv&org=' . $orgId . ($params ? '&' . $params : '')) }}"
                       class="filament-button filament-page filament-table:inline-flex items-center gap-1 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10">
                        <x-heroicon-s-arrow-down-tray class="h-4 w-4" />
                        Export CSV
                    </a>
                    <a href="{{ url('/panel-sba/attendance-report/export?format=xlsx&org=' . $orgId . ($params ? '&' . $params : '')) }}"
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
                    Jumlah Kegiatan
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ $this->getDateContext() }})</span>
                </div>
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $stats['event_count'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Peserta</div>
                <div class="text-3xl font-bold">{{ $stats['participant_count'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Persentase Kehadiran</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $stats['attendance_rate'] }}%</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Hadir</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $stats['hadir'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Izin</div>
                <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $stats['izin'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tidak Hadir</div>
                <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['tidak_hadir'] }}</div>
            </x-filament::section>
        </div>

        <!-- Breakdown per Kegiatan -->
        <x-filament::section heading="Rekap per Kegiatan">
            <ul class="space-y-3">
                @forelse($this->getBreakdownByEvent() as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800 last:border-0 last:pb-0">
                        <div>
                            <div class="font-semibold">{{ $row['title'] }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $row['event_date'] }}</div>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="px-2 py-1 rounded-full bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">Hadir: {{ $row['hadir'] }}</span>
                            <span class="px-2 py-1 rounded-full bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">Izin: {{ $row['izin'] }}</span>
                            <span class="px-2 py-1 rounded-full bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Tidak Hadir: {{ $row['tidak_hadir'] }}</span>
                        </div>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">Tidak ada data</li>
                @endforelse
            </ul>
        </x-filament::section>

        <!-- Table -->
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>