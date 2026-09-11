@extends('layouts.public')

@section('title', 'Verifikasi Sertifikat — FSBMM')

@section('content')
<main class="min-h-screen bg-gradient-to-br from-stone-50 via-emerald-50/30 to-stone-50 pt-24 pb-16">
    <div class="mx-auto max-w-lg px-4">
        @if ($certificate === null)
            <div class="rounded-2xl border border-amber-200 bg-white p-8 text-center shadow-lg" data-reveal>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-8 w-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h1 class="mb-2 text-2xl font-bold text-amber-700">Sertifikat Tidak Ditemukan</h1>
                <p class="mb-6 text-gray-600">Nomor verifikasi tidak terdaftar, atau sertifikat ini sudah dicabut dan tidak lagi berlaku.</p>
                <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-500">
                    Kembali ke Beranda
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-white p-8 shadow-lg" data-reveal>
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-8 w-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h1 class="mb-1 text-2xl font-bold text-emerald-700">Sertifikat Valid ✓</h1>
                    <p class="text-sm text-gray-500">Sertifikat kelulusan ini terdaftar dan sah dalam sistem FSBMM.</p>
                </div>

                <div class="rounded-xl bg-gradient-to-br from-emerald-800 via-emerald-600 to-emerald-400 p-5 text-white">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-md bg-white text-xs font-black text-emerald-800">FSBMM</div>
                        <div class="text-xs leading-tight opacity-90">Federasi Serikat Buruh<br>Mandiri se-Indonesia</div>
                    </div>
                    <div class="text-2xl font-bold">{{ $certificate->user->name }}</div>
                    <div class="mt-1 text-sm opacity-90">{{ $certificate->course->title }}</div>
                    <div class="mt-1 text-xs opacity-75">Tingkat {{ ucfirst($certificate->course->level) }}</div>
                </div>

                <div class="mt-6 space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="font-medium text-gray-600">Nomor Sertifikat</span>
                        <span class="font-mono text-gray-900">{{ $certificate->certificate_number }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="font-medium text-gray-600">Tanggal Terbit</span>
                        <span class="text-gray-900">{{ $certificate->issued_at->translatedFormat('d F Y') }}</span>
                    </div>
                    @if ($certificate->final_score !== null)
                        <div class="flex justify-between gap-3">
                            <span class="font-medium text-gray-600">Nilai Akhir</span>
                            <span class="text-gray-900">{{ $certificate->final_score }}</span>
                        </div>
                    @endif
                    @if ($certificate->user->organization)
                        <div class="flex justify-between gap-3">
                            <span class="font-medium text-gray-600">Organisasi</span>
                            <span class="text-gray-900">{{ $certificate->user->organization->name }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <span class="font-medium text-gray-600">Status</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Berlaku</span>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-500">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        @endif
    </div>
</main>
@endsection
