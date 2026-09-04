<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-user-group">
        <x-slot name="heading">
            Data Anggota (agregat)
        </x-slot>

        <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500">Total anggota aktif</dt>
                <dd>{{ number_format($this->getTotalActiveMembers(), 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Iuran bulan ini</dt>
                <dd>Rp {{ number_format($this->getCurrentMonthDuesTotal(), 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Pengaduan terbuka</dt>
                <dd>{{ number_format($this->getOpenComplaintsCount(), 0, ',', '.') }}</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
