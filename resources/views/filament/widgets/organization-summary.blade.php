<x-filament-widgets::widget>
    @php($organization = $this->getOrganization())

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
