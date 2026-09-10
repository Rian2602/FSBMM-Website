<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form + Export -->
        <x-filament::section heading="Filter Laporan">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    {{ $this->form }}
                </div>
                <div class="flex gap-2">
                    @php
                        $params = http_build_query(array_filter($this->data ?? []));
                        $orgId = auth()->user()->organization_id;
                    @endphp
                    <a href="{{ url('/panel-sba/member-report/export?format=csv&org=' . $orgId . ($params ? '&' . $params : '')) }}"
                       class="filament-button filament-page filament-table:inline-flex items-center gap-1 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10">
                        <x-heroicon-s-arrow-down-tray class="h-4 w-4" />
                        Export CSV
                    </a>
                    <a href="{{ url('/panel-sba/member-report/export?format=xlsx&org=' . $orgId . ($params ? '&' . $params : '')) }}"
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
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Anggota</div>
                <div class="text-3xl font-bold">{{ $stats['total'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Anggota Aktif</div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $stats['active'] }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Anggota Tidak Aktif</div>
                <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['inactive'] }}</div>
            </x-filament::section>
        </div>

        <!-- Breakdowns -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <x-filament::section heading="Distribusi per Departemen">
                <ul class="space-y-2">
                    @forelse($this->getBreakdownByDepartment() as $dept => $count)
                        <li class="flex justify-between items-center">
                            <span>{{ $dept ?: 'Tidak ada departemen' }}</span>
                            <span class="px-2 py-1 bg-gray-100 rounded-full text-sm font-semibold dark:bg-gray-800">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">Tidak ada data</li>
                    @endforelse
                </ul>
            </x-filament::section>
            
            <x-filament::section heading="Distribusi per Jabatan">
                <ul class="space-y-2">
                    @forelse($this->getBreakdownByPosition() as $pos => $count)
                        <li class="flex justify-between items-center">
                            <span>{{ $pos ?: 'Tidak ada jabatan' }}</span>
                            <span class="px-2 py-1 bg-gray-100 rounded-full text-sm font-semibold dark:bg-gray-800">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">Tidak ada data</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>

        <!-- Table -->
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
