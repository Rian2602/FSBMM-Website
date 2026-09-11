<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Catatan aktivitas administrasi organisasi Anda (anggota, kartu, pengaduan, ekspor).
                Hanya aktivitas SBA Anda yang ditampilkan.
            </p>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
