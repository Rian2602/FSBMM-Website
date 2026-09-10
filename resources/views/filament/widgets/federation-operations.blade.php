@php
    $metrics = $this->getMetrics();
    $breakdown = $this->getPerSbaBreakdown();
@endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-clipboard-document-list">
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Ringkasan Operasional Federasi</span>
                <a href="{{ url('/admin/generate-report') }}"
                   class="filament-button filament-page filament-table:inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-primary-500 focus:ring-2 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400">
                    <x-heroicon-s-document-arrow-down class="h-3.5 w-3.5" />
                    Unduh Laporan PDF
                </a>
            </div>
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

        {{-- Visual chart: Anggota per SBA --}}
        @if ($breakdown)
            @php
                $maxMembers = collect($breakdown)->max('active_members') ?: 1;
                $barColors = ['#3a86ff', '#8ac926', '#ffb703', '#ff006e', '#8338ec', '#fb5607', '#06aed5', '#2ec4b6'];
            @endphp
            <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #6b7280; margin-bottom: 0.75rem;">📊 Distribusi Anggota per SBA</p>
                <div class="space-y-2">
                    @foreach ($breakdown as $idx => $row)
                        @php
                            $pct = $maxMembers > 0 ? ($row['active_members'] / $maxMembers) * 100 : 0;
                            $color = $barColors[$idx % count($barColors)];
                        @endphp
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row['name'] }}</span>
                                <span class="font-bold text-gray-900">{{ number_format($row['active_members'], 0, ',', '.') }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%; background: {{ $color }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Data table --}}
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
