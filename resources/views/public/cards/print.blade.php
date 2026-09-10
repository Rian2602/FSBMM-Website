<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Anggota — {{ $card->member->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 2rem; }
        .card-container { display: flex; gap: 2rem; flex-wrap: wrap; justify-content: center; }
        .card { width: 340px; height: 214px; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,.15); position: relative; }
        .card-front { background: linear-gradient(135deg, #065f46, #10b981, #34d399); color: white; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between; }
        .card-front::before { content: ''; position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: rgba(255,255,255,.08); border-radius: 50%; }
        .card-front::after { content: ''; position: absolute; bottom: -40px; left: -20px; width: 100px; height: 100px; background: rgba(255,255,255,.05); border-radius: 50%; }
        .card-header { display: flex; align-items: center; gap: .5rem; z-index: 1; }
        .card-header .logo { width: 32px; height: 32px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #065f46; font-size: 10px; }
        .card-header .org-name { font-size: 10px; opacity: .9; line-height: 1.2; }
        .card-body { z-index: 1; display: flex; gap: .8rem; align-items: flex-end; }
        .card-photo { width: 56px; height: 56px; border-radius: 8px; background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.4); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700; flex-shrink: 0; }
        .card-info { flex: 1; min-width: 0; }
        .card-info .name { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .card-info .position { font-size: 10px; opacity: .85; }
        .card-info .nik { font-size: 9px; opacity: .7; margin-top: 2px; font-family: monospace; }
        .card-footer { z-index: 1; display: flex; justify-content: space-between; align-items: flex-end; }
        .card-footer .card-number { font-family: monospace; font-size: 11px; font-weight: 600; letter-spacing: .5px; }
        .card-footer .validity { font-size: 9px; opacity: .8; }
        .card-back { background: linear-gradient(135deg, #f0fdf4, #ecfdf5); color: #065f46; padding: 1rem 1.2rem; display: flex; flex-direction: column; justify-content: space-between; border: 1px solid #d1fae5; }
        .card-back .back-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .card-back .back-header .title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .card-back .qr-code { width: 64px; height: 64px; }
        .card-back .qr-code svg { width: 100%; height: 100%; }
        .card-back .statement { font-size: 8.5px; line-height: 1.4; color: #374151; }
        .card-back .motto { font-size: 9px; font-weight: 600; color: #059669; text-align: center; margin-top: .5rem; padding-top: .5rem; border-top: 1px solid #d1fae5; }
        .card-back .verification { font-size: 7.5px; color: #6b7280; text-align: center; }
        .back-stamp { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); opacity: .04; font-size: 60px; font-weight: 900; color: #065f46; pointer-events: none; }
        @media print {
            body { background: white; padding: 0; }
            .card { box-shadow: none; border: 1px solid #e5e7eb; page-break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position:fixed;top:1rem;right:1rem;z-index:999">
        <button onclick="window.print()" style="padding:.5rem 1rem;background:#065f46;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
            🖨️ Cetak Kartu
        </button>
    </div>

    <div class="card-container">
        <!-- Front -->
        <div class="card card-front">
            <div class="card-header">
                <div class="logo">FSBMM</div>
                <div class="org-name">Federasi Serikat Buruh<br>Mandiri se-Indonesia</div>
            </div>
            <div class="card-body">
                <div class="card-photo">
                    @if($card->member->gender === 'P')
                        👩
                    @else
                        👨
                    @endif
                </div>
                <div class="card-info">
                    <div class="name">{{ $card->member->name }}</div>
                    <div class="position">{{ $card->member->position }} — {{ $card->member->department }}</div>
                    <div class="nik">NIK: {{ $card->member->nik }}</div>
                </div>
            </div>
            <div class="card-footer">
                <div>
                    <div class="card-number">{{ $card->card_number }}</div>
                    <div class="validity">Berlaku s.d. {{ $card->issued_at->addYear()->format('d/m/Y') }}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:9px;opacity:.7">{{ $card->organization->name }}</div>
                </div>
            </div>
            <div class="back-stamp">FSBMM</div>
        </div>

        <!-- Back -->
        <div class="card card-back">
            <div class="back-header">
                <div class="title">Kartu Anggota FSBMM</div>
                <div class="qr-code" id="qr-code"></div>
            </div>
            <div class="statement">
                Kartu ini adalah bukti keanggotaan resmi dalam Federasi Serikat Buruh Mandiri se-Indonesia.
                Kartu ini tidak dapat dipindah tangankan dan harus ditunjukkan apabila diperlukan verifikasi.
            </div>
            <div>
                <div class="motto">"BERANI BERJUANG PASTI MENANG"</div>
                <div class="verification">Verifikasi: {{ url('/verifikasi/kartu/' . $card->verification_token) }}</div>
            </div>
        </div>
    </div>

    <script>
        // Generate QR code as SVG using simple inline approach
        (function() {
            const url = '{{ url("/verifikasi/kartu/" . $card->verification_token) }}';
            const qr = document.getElementById('qr-code');
            // Simple QR-like visual (placeholder — production should use bacon-qr-code)
            const size = 64;
            const cellSize = 4;
            const cells = Math.floor(size / cellSize);
            let svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">`;
            svg += `<rect width="${size}" height="${size}" fill="white"/>`;
            // Position patterns (corners)
            const drawFinder = (x, y) => {
                for (let i = 0; i < 7; i++) {
                    for (let j = 0; j < 7; j++) {
                        if (i === 0 || i === 6 || j === 0 || j === 6 || (i >= 2 && i <= 4 && j >= 2 && j <= 4)) {
                            svg += `<rect x="${(x + i) * cellSize}" y="${(y + j) * cellSize}" width="${cellSize}" height="${cellSize}" fill="#065f46"/>`;
                        }
                    }
                }
            };
            drawFinder(0, 0);
            drawFinder(cells - 7, 0);
            drawFinder(0, cells - 7);
            // Data modules (deterministic from URL hash)
            let hash = 0;
            for (let i = 0; i < url.length; i++) hash = ((hash << 5) - hash) + url.charCodeAt(i);
            for (let y = 8; y < cells - 8; y++) {
                for (let x = 8; x < cells - 8; x++) {
                    hash = ((hash << 5) - hash) + x * 31 + y * 37;
                    if (Math.abs(hash) % 3 === 0) {
                        svg += `<rect x="${x * cellSize}" y="${y * cellSize}" width="${cellSize}" height="${cellSize}" fill="#065f46"/>`;
                    }
                }
            }
            svg += '</svg>';
            qr.innerHTML = svg;
        })();
    </script>
</body>
</html>
