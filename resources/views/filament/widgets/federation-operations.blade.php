@php
    $metrics = $this->getMetrics();
    $breakdown = $this->getPerSbaBreakdown();
@endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-clipboard-document-list">
        <x-slot name="heading">
            Ringkasan Operasional Federasi
        </x-slot>

        <dl class="mb-4 grid grid-cols-2 gap-2 text-sm lg:grid-cols-4">
            <div style="border-left: 4px solid #3a86ff; background: #eef5ff; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #3a86ff;">Jumlah SBA</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['organizations'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #8ac926; background: #f3fae6; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #5a850d;">Total anggota aktif</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['active_members'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #2ec4b6; background: #e7fcf9; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #168b7d;">Iuran bulan berjalan</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">Rp {{ number_format($metrics['current_dues'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #ffb703; background: #fff7de; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #b57e00;">Jumlah kegiatan</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['events'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #8338ec; background: #f6f0fe; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #8338ec;">Peserta hadir</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['attendances_hadir'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #ff006e; background: #ffedf5; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #d3005d;">Pengaduan terbuka</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['open_complaints'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #06aed5; background: #e7f7fc; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #05809c;">Kartu aktif</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['active_cards'], 0, ',', '.') }}</dd>
            </div>
            <div style="border-left: 4px solid #e63946; background: #fdedef; border-radius: 0.5rem; padding: 0.6rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #b31f2b;">Kartu dicabut</dt>
                <dd style="margin-top: 0.25rem; font-size: 1.05rem; font-weight: 800; color: #073b32;">{{ number_format($metrics['revoked_cards'], 0, ',', '.') }}</dd>
            </div>
        </dl>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b-2 border-gray-200 text-left text-xs text-gray-500">
                    <th class="py-2 pr-3 font-semibold">Nama</th>
                    <th class="py-2 pr-3 font-semibold">Anggota Aktif</th>
                    <th class="py-2 pr-3 font-semibold">Iuran</th>
                    <th class="py-2 pr-3 font-semibold">Kegiatan</th>
                    <th class="py-2 pr-3 font-semibold">Pengaduan Terbuka</th>
                    <th class="py-2 font-semibold">Kartu Aktif</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breakdown as $row)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3">{{ $row['name'] }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['active_members'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">Rp {{ number_format($row['current_dues'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['events'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format($row['open_complaints'], 0, ',', '.') }}</td>
                        <td class="py-2">{{ number_format($row['active_cards'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-2 text-gray-500">Belum ada data operasional.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
