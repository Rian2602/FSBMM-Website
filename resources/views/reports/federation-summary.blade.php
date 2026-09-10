<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Federasi — {{ $period }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; line-height: 1.6; }
        .header { text-align: center; padding: 30px 0; border-bottom: 3px solid #073b32; }
        .header h1 { color: #073b32; font-size: 24px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 12px; }
        .section { margin: 20px 0; }
        .section h2 { color: #073b32; font-size: 16px; border-bottom: 2px solid #c9d43a; padding-bottom: 5px; margin-bottom: 15px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; border-left: 4px solid #073b32; padding: 15px; border-radius: 4px; }
        .stat-card.blue { border-left-color: #3a86ff; }
        .stat-card.green { border-left-color: #8ac926; }
        .stat-card.orange { border-left-color: #ffb703; }
        .stat-card.red { border-left-color: #ff006e; }
        .stat-card .label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 20px; font-weight: bold; color: #073b32; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #073b32; color: white; padding: 10px 12px; text-align: left; font-weight: 600; }
        td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #073b32; text-align: center; font-size: 10px; color: #666; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏛️ FEDERASI SERIKAT BURUH MAKANAN DAN MINUMAN</h1>
        <p>Laporan Operasional — {{ $period }}</p>
        <p>Dicetak: {{ $generated_at }}</p>
    </div>

    <div class="section">
        <h2>📊 Ringkasan Utama</h2>
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="label">Jumlah SBA</div>
                <div class="value">{{ number_format($total_orgs) }}</div>
            </div>
            <div class="stat-card green">
                <div class="label">Total Anggota</div>
                <div class="value">{{ number_format($total_members) }}</div>
            </div>
            <div class="stat-card orange">
                <div class="label">Iuran Terkumpul</div>
                <div class="value">Rp {{ number_format($total_dues, 0, ',', '.') }}</div>
            </div>
            <div class="stat-card red">
                <div class="label">Pengaduan Terbuka</div>
                <div class="value">{{ $open_complaints }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>💰 Iuran & Keuangan</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Anggota Sudah Bayar</div>
                <div class="value">{{ number_format($paid_members) }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Belum Tercatat</div>
                <div class="value">{{ number_format($pending_dues) }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Tingkat Pembayaran</div>
                <div class="value">{{ $dues_rate }}%</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>📋 Pengaduan</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-danger">Baru</span></td>
                    <td>{{ $new_complaints }}</td>
                </tr>
                <tr>
                    <td><span class="badge badge-warning">Diproses</span></td>
                    <td>{{ $processing_complaints }}</td>
                </tr>
                <tr>
                    <td><span class="badge badge-success">Selesai</span></td>
                    <td>{{ $resolved_complaints }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>📅 Kegiatan Mendatang</h2>
        @if ($upcomingEvents->isEmpty())
            <p style="font-size: 12px; color: #666;">Tidak ada kegiatan yang dijadwalkan dalam 7 hari ke depan.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Kegiatan</th>
                        <th>Tanggal</th>
                        <th>SBA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($upcomingEvents as $event)
                        <tr>
                            <td>{{ $event->title }}</td>
                            <td>{{ $event->event_date->format('d M Y') }}</td>
                            <td>{{ $event->organization?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <h2>🏢 Detail per SBA</h2>
        <table>
            <thead>
                <tr>
                    <th>Nama SBA</th>
                    <th>Anggota</th>
                    <th>Iuran</th>
                    <th>Kegiatan</th>
                    <th>Pengaduan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sba_breakdown as $sba)
                    <tr>
                        <td>{{ $sba['name'] }}</td>
                        <td>{{ number_format($sba['members']) }}</td>
                        <td>Rp {{ number_format($sba['dues'], 0, ',', '.') }}</td>
                        <td>{{ $sba['events'] }}</td>
                        <td>{{ $sba['complaints'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>FSBMM — Federasi Serikat Buruh Makanan dan Minuman</p>
        <p>Laporan ini dihasilkan secara otomatis pada {{ $generated_at }}</p>
    </div>
</body>
</html>
