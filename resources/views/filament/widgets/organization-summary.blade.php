<x-filament-widgets::widget>
    @php
        $organization = $this->getOrganization();
    @endphp

    <x-filament::section icon="heroicon-o-building-office-2">
        <x-slot name="heading">
            {{ $organization?->name ?? __('Tidak ada organisasi tertaut') }}
        </x-slot>

        @if ($organization)
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Lokasi</dt>
                    <dd>{{ $organization->location }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Perusahaan</dt>
                    <dd>{{ $organization->company }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Tahun berdiri</dt>
                    <dd>{{ $organization->founded_year }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Jumlah anggota</dt>
                    <dd>{{ number_format($organization->member_count) }} anggota</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Status publikasi</dt>
                    <dd>{{ $organization->is_published ? 'Terbit' : 'Belum terbit' }}</dd>
                </div>
            </dl>

            @php
                $stats = $this->stats();
            @endphp

            <dl class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                <div style="border-left: 4px solid #3a86ff; background: #eef5ff; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                    <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #3a86ff;">Anggota aktif (SP3)</dt>
                    <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">{{ number_format($stats['active_members'], 0, ',', '.') }}</dd>
                </div>
                <div style="border-left: 4px solid #8ac926; background: #f3fae6; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                    <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #5a850d;">Iuran bulan ini</dt>
                    <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">Rp {{ number_format($stats['current_month_dues'], 0, ',', '.') }}</dd>
                </div>
                <div style="border-left: 4px solid #ff006e; background: #ffedf5; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                    <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #d3005d;">Pengaduan terbuka</dt>
                    <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">{{ number_format($stats['open_complaints'], 0, ',', '.') }}</dd>
                </div>
            </dl>

            <div class="mt-4">
                <x-filament::button
                    :href="\App\Filament\Sba\Resources\OrganizationResource::getUrl('edit', ['record' => $organization])"
                    tag="a">
                    Kelola profil organisasi
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
