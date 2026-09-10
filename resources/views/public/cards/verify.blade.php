@extends('layouts.public')

@section('title', 'Verifikasi Kartu Anggota — FSBMM')

@section('content')
<main class="min-h-screen bg-gradient-to-br from-stone-50 via-emerald-50/30 to-stone-50 pt-24 pb-16">
    <div class="mx-auto max-w-lg px-4">
        @if($data === null)
            {{-- Invalid Token --}}
            <div class="rounded-2xl border border-red-200 bg-white p-8 shadow-lg text-center" data-reveal>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-red-700 mb-2">Kartu Tidak Ditemukan</h1>
                <p class="text-gray-600 mb-6">Token verifikasi tidak valid atau kartu tidak terdaftar dalam sistem kami.</p>
                <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-500 transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Kembali ke Beranda
                </a>
            </div>

        @elseif($data['status'] === 'revoked')
            {{-- Revoked Card --}}
            <div class="rounded-2xl border border-amber-200 bg-white p-8 shadow-lg text-center" data-reveal>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-8 w-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-amber-700 mb-2">Kartu Tidak Aktif</h1>
                <p class="text-gray-600 mb-4">Kartu anggota ini telah dicabut dan tidak lagi berlaku.</p>
                <div class="rounded-lg bg-gray-50 p-4 text-left text-sm space-y-2">
                    <div><span class="font-medium text-gray-700">Nomor Kartu:</span> <span class="font-mono text-gray-900">{{ $data['card_number'] }}</span></div>
                    <div><span class="font-medium text-gray-700">Nama:</span> <span class="text-gray-900">{{ $data['member_name'] }}</span></div>
                    <div><span class="font-medium text-gray-700">Organisasi:</span> <span class="text-gray-900">{{ $data['organization_name'] }}</span></div>
                </div>
                <div class="mt-6">
                    <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Kembali ke Beranda
                    </a>
                </div>
            </div>

        @elseif($data['status'] === 'inactive')
            {{-- Inactive Member --}}
            <div class="rounded-2xl border border-gray-300 bg-white p-8 shadow-lg text-center" data-reveal>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-200">
                    <svg class="h-8 w-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2 2 2m0-4l-2 2-2-2m10 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-700 mb-2">Kartu Tidak Aktif</h1>
                <p class="text-gray-600 mb-4">Kartu anggota ini tidak dapat diverifikasi karena status anggota tidak aktif.</p>
                <div class="rounded-lg bg-gray-50 p-4 text-left text-sm space-y-2">
                    <div><span class="font-medium text-gray-700">Nomor Kartu:</span> <span class="font-mono text-gray-900">{{ $data['card_number'] }}</span></div>
                    <div><span class="font-medium text-gray-700">Nama:</span> <span class="text-gray-900">{{ $data['member_name'] }}</span></div>
                    <div><span class="font-medium text-gray-700">Organisasi:</span> <span class="text-gray-900">{{ $data['organization_name'] }}</span></div>
                </div>
                <div class="mt-6">
                    <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Kembali ke Beranda
                    </a>
                </div>
            </div>

        @else
            {{-- Active Card --}}
            <div class="rounded-2xl border border-emerald-200 bg-white p-8 shadow-lg" data-reveal>
                <div class="text-center mb-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-8 w-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-emerald-700 mb-1">Kartu Valid ✓</h1>
                    <p class="text-gray-500 text-sm">Kartu anggota ini terdaftar dan aktif dalam sistem kami.</p>
                </div>

                {{-- Card Visual --}}
                <div class="rounded-xl overflow-hidden shadow-md mb-6" style="background: linear-gradient(135deg, #065f46, #10b981, #34d399); color: white;">
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 bg-white rounded-md flex items-center justify-center font-black text-emerald-800 text-xs">FSBMM</div>
                            <div class="text-xs opacity-90 leading-tight">Federasi Serikat Buruh<br>Mandiri se-Indonesia</div>
                        </div>
                        <div class="flex gap-3 items-end">
                            <div class="w-14 h-14 rounded-lg bg-white/20 border-2 border-white/40 flex items-center justify-center text-2xl font-bold">
                                @if($data['member_name'])
                                    {{ strtoupper(substr($data['member_name'], 0, 1)) }}
                                @else
                                    ?
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-base">{{ $data['member_name'] }}</div>
                                <div class="text-xs opacity-85">{{ $data['organization_name'] }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-black/10 flex justify-between items-center">
                        <span class="font-mono text-sm font-semibold tracking-wide">{{ $data['card_number'] }}</span>
                    </div>
                </div>

                {{-- Detail Info --}}
                <div class="rounded-lg bg-gray-50 p-4 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-600">Status</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-600">Nomor Kartu</span>
                        <span class="font-mono text-gray-900">{{ $data['card_number'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-600">Nama Anggota</span>
                        <span class="text-gray-900">{{ $data['member_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-600">Organisasi</span>
                        <span class="text-gray-900">{{ $data['organization_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-600">Diterbitkan</span>
                        <span class="text-gray-900">{{ $data['issued_at'] }}</span>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        @endif
    </div>
</main>
@endsection
