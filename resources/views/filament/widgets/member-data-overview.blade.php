<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-user-group">
        <x-slot name="heading">
            Data Anggota (agregat)
        </x-slot>

        <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
            <div style="border-left: 4px solid #3a86ff; background: #eef5ff; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #3a86ff;">Total anggota aktif</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($this->getTotalActiveMembers(), 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #8ac926; background: #f3fae6; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #5a850d;">Iuran bulan ini</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">Rp {{ number_format($this->getCurrentMonthDuesTotal(), 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #ff006e; background: #ffedf5; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #d3005d;">Pengaduan terbuka</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($this->getOpenComplaintsCount(), 0, ',', '.') }}</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
