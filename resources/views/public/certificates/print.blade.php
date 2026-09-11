@php
    use App\Support\QrCodeRenderer;

    $verifyUrl = url('/verifikasi/sertifikat/'.$certificate->verification_token);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Kelulusan — {{ $certificate->course->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .toolbar { position: fixed; top: 1rem; right: 1rem; z-index: 10; display: flex; gap: .5rem; }
        .toolbar button, .toolbar a {
            padding: .55rem 1.1rem; border: none; border-radius: 8px; cursor: pointer;
            font-size: 14px; font-family: inherit; text-decoration: none;
        }
        .toolbar .primary { background: #065f46; color: #fff; }
        .toolbar .ghost { background: #fff; color: #065f46; border: 1px solid #d1d5db; }

        .certificate {
            position: relative;
            width: 297mm;
            max-width: 100%;
            background: #fff;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .18);
            padding: 14mm;
            overflow: hidden;
        }
        .frame {
            position: relative;
            border: 2px solid #065f46;
            outline: 1px solid #c9d43a;
            outline-offset: 6px;
            padding: 14mm 16mm;
            text-align: center;
            background:
                radial-gradient(circle at 100% 0, rgba(16, 185, 129, .10), transparent 45%),
                radial-gradient(circle at 0 100%, rgba(201, 212, 58, .14), transparent 45%);
        }
        .seal {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-18deg);
            font-size: 130px; font-weight: 900; color: #065f46; opacity: .045;
            letter-spacing: 8px; pointer-events: none; white-space: nowrap;
        }
        .org { display: flex; align-items: center; justify-content: center; gap: .75rem; }
        .logo {
            width: 54px; height: 54px; border-radius: 10px; background: #065f46; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 13px; letter-spacing: .5px;
        }
        .org-name { text-align: left; }
        .org-name strong { display: block; color: #065f46; font-size: 15px; letter-spacing: .4px; }
        .org-name span { font-size: 11px; color: #6b7280; }
        .kicker {
            margin-top: 10mm; font-size: 30px; font-weight: 900; letter-spacing: 6px;
            color: #065f46; text-transform: uppercase;
        }
        .divider { width: 90px; height: 3px; background: #c9d43a; margin: 3mm auto 5mm; }
        .given { font-size: 13px; color: #6b7280; letter-spacing: 2px; text-transform: uppercase; }
        .name {
            margin-top: 3mm; font-size: 34px; font-weight: 800; color: #111827;
            border-bottom: 1px dashed #d1d5db; padding-bottom: 2mm; display: inline-block;
        }
        .statement { margin-top: 5mm; font-size: 13px; color: #374151; line-height: 1.7; }
        .course { font-size: 19px; font-weight: 700; color: #065f46; margin-top: 1mm; }
        .meta {
            margin-top: 8mm; display: flex; align-items: flex-end; justify-content: space-between;
            gap: 8mm; text-align: left;
        }
        .meta .qr { text-align: center; }
        .meta .qr svg { width: 96px; height: 96px; }
        .meta .qr small { display: block; font-size: 9px; color: #9ca3af; margin-top: 2px; }
        .meta .facts { font-size: 11.5px; color: #374151; }
        .meta .facts div { margin-bottom: 1.5mm; }
        .meta .facts strong { color: #065f46; }
        .signature { text-align: center; min-width: 55mm; }
        .signature .line { border-top: 1px solid #374151; margin-top: 14mm; padding-top: 2mm; font-size: 12px; }
        .signature .line strong { display: block; color: #111827; }
        .signature .line span { color: #6b7280; font-size: 10.5px; }
        .number {
            margin-top: 8mm; font-family: ui-monospace, monospace; font-size: 11px; color: #6b7280;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .certificate { box-shadow: none; width: 100%; height: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="primary" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <a class="ghost" href="#" onclick="window.close(); return false;">Tutup</a>
    </div>

    <div class="certificate">
        <div class="frame">
            <div class="seal">FSBMM</div>

            <div class="org">
                <div class="logo">FSBMM</div>
                <div class="org-name">
                    <strong>FEDERASI SERIKAT BURUH MANDIRI</strong>
                    <span>Federasi Serikat Buruh Makanan dan Minuman se-Indonesia</span>
                </div>
            </div>

            <div class="kicker">Sertifikat Kelulusan</div>
            <div class="divider"></div>

            <div class="given">Diberikan kepada</div>
            <div class="name">{{ $certificate->user->name }}</div>

            <div class="statement">
                atas keberhasilan menyelesaikan program pembelajaran
                <div class="course">{{ $certificate->course->title }}</div>
                tingkat {{ ucfirst($certificate->course->level) }}
                @if ($certificate->final_score !== null)
                    dengan nilai akhir <strong>{{ $certificate->final_score }}</strong>
                @endif
            </div>

            <div class="meta">
                <div class="qr">
                    {!! QrCodeRenderer::svg($verifyUrl, 96) !!}
                    <small>Pindai untuk verifikasi</small>
                </div>

                <div class="facts">
                    <div><strong>Nomor Sertifikat:</strong> {{ $certificate->certificate_number }}</div>
                    <div><strong>Tanggal Terbit:</strong> {{ $certificate->issued_at->translatedFormat('d F Y') }}</div>
                    @if ($certificate->user->organization)
                        <div><strong>Organisasi:</strong> {{ $certificate->user->organization->name }}</div>
                    @endif
                    <div><strong>Verifikasi:</strong> {{ $verifyUrl }}</div>
                </div>

                <div class="signature">
                    <div class="line">
                        <strong>Pimpinan Federasi</strong>
                        <span>FSBMM</span>
                    </div>
                </div>
            </div>

            <div class="number">Keabsahan sertifikat ini dapat diverifikasi secara publik melalui tautan di atas.</div>
        </div>
    </div>
</body>
</html>
