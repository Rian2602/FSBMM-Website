@php
    use App\Support\QrCodeRenderer;

    $verifyUrl = url('/verifikasi/kartu/'.$card->verification_token);
    $issuedDate = $card->issued_at?->format('d/m/Y');
    $qr = QrCodeRenderer::svg($verifyUrl, 80);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Anggota — {{ $card->member->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f3f4f6; display: block; padding: 2rem 0; }
        /* (** executed: single wkhtmltopdf-affine template shared by browser
             print and on-demand PDF — no flexbox/gap (Qt WebKit cannot render
             them), §9.9 fields only (no NIK/photo/emoji). **) */
        .card { width: 340px; margin: 0 auto 2rem; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,.15); position: relative; page-break-inside: avoid; }
        .card:last-child { margin-bottom: 0; }
        .card-front { background-color: #065f46; background-image: linear-gradient(135deg, #065f46, #10b981, #34d399); color: #fff; padding: 1.2rem; height: 214px; }
        .card-header { display: block; }
        .card-header::after { content: ''; display: table; clear: both; }
        .logo { float: left; width: 44px; height: 44px; background: #fff; border-radius: 8px; text-align: center; line-height: 44px; font-weight: 900; color: #065f46; font-size: 11px; }
        .header-org { float: left; margin-left: .6rem; padding-top: 4px; }
        .header-org .to { font-size: 9px; opacity: .85; display: block; }
        .header-org .org { font-size: 12px; font-weight: 700; display: block; line-height: 1.25; }
        .qr-wrap { float: right; width: 64px; }
        .qr-wrap img { width: 64px; height: 64px; display: block; }
        .card-body { margin-top: 1.1rem; }
        .card-body .label { font-size: 9px; text-transform: uppercase; letter-spacing: .8px; opacity: .7; display: block; margin-bottom: 2px; }
        .card-body .name { font-size: 21px; font-weight: 800; line-height: 1.15; display: block; }
        .card-body .sba { font-size: 11px; opacity: .9; display: block; margin-top: 3px; }
        .card-footer { margin-top: 1.25rem; }
        .card-footer::after { content: ''; display: table; clear: both; }
        .card-number { font-family: 'Courier New', monospace; font-size: 12px; font-weight: 700; letter-spacing: .5px; }
        .card-back { background-color: #f0fdf4; background-image: linear-gradient(135deg, #f0fdf4, #ecfdf5); color: #065f46; padding: 1rem 1.2rem; min-height: 214px; border: 1px solid #d1fae5; }
        .card-back .back-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: .6rem; }
        .card-back .statement { font-size: 10px; line-height: 1.45; color: #374151; display: block; }
        .back-meta { margin-top: .75rem; }
        .back-meta .row { display: block; font-size: 10px; color: #374151; margin-bottom: 3px; }
        .back-meta .row b { color: #065f46; }
        .card-back .motto { font-size: 9px; font-weight: 700; color: #059669; text-align: center; margin-top: .75rem; padding-top: .6rem; border-top: 1px solid #d1fae5; display: block; }
        .card-back .verification { font-size: 8px; color: #6b7280; text-align: center; word-break: break-all; display: block; margin-top: .35rem; }
        @media print {
            body { background: #fff; padding: 0; }
            .card { box-shadow: none; border: 1px solid #e5e7eb; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position:fixed;top:1rem;right:1rem;z-index:999">
        <button onclick="window.print()" style="padding:.5rem 1rem;background:#065f46;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
            Cetak Kartu
        </button>
    </div>

    <!-- Front -->
    <div class="card card-front">
        <div class="card-header">
            <div class="logo">FSBMM</div>
            <div class="header-org">
                <span class="to">Federasi Serikat Buruh</span>
                <span class="org">Mandiri se-Indonesia</span>
            </div>
            <div class="qr-wrap">
                <img src="data:image/svg+xml;base64,{{ base64_encode($qr) }}" alt="Kode verifikasi kartu anggota">
            </div>
        </div>
        <div class="card-body">
            <span class="label">Anggota</span>
            <span class="name">{{ $card->member->name }}</span>
            <span class="sba">{{ $card->organization->name }}</span>
        </div>
        <div class="card-footer">
            <span class="card-number">{{ $card->card_number }}</span>
        </div>
    </div>

    <!-- Back -->
    <div class="card card-back">
        <span class="back-title">Kartu Anggota FSBMM</span>
        <span class="statement">
            Kartu ini adalah bukti keanggotaan resmi dalam Federasi Serikat Buruh Mandiri se-Indonesia.
            Kartu ini tidak dapat dipindah tangankan dan harus ditunjukkan apabila diperlukan verifikasi.
        </span>
        <div class="back-meta">
            <span class="row"><b>Tanggal penerbitan:</b> {{ $issuedDate }}</span>
            @if($card->organization->location)
                <span class="row"><b>Organisasi:</b> {{ $card->organization->location }}</span>
            @endif
            @if($card->organization->website)
                <span class="row"><b>Kontak:</b> {{ $card->organization->website }}</span>
            @endif
        </div>
        <span class="motto">"BERANI BERJUANG PASTI MENANG"</span>
        <span class="verification">Verifikasi: {{ $verifyUrl }}</span>
    </div>
</body>
</html>