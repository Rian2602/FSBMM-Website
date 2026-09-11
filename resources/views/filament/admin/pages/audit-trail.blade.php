<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Catatan aktivitas administratif lintas panel (anggota, kartu, pengaduan, ekspor,
                sertifikat). Jejak ini <strong>tidak memuat data pribadi anggota</strong> — tidak ada
                nama, NIK, alamat, atau upah, sesuai kebijakan minimisasi PII federasi.
            </p>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
