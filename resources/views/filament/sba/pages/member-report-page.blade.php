<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form -->
        <x-filament::section heading="Filter Laporan">
            {{ $this->form }}
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
