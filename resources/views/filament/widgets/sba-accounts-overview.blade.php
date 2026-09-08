<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-users">
        <x-slot name="heading">
            Akun SBA
        </x-slot>

        <dl class="mb-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
            <div style="border-left: 4px solid #3a86ff; background: #eef5ff; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #3a86ff;">Akun SBA</dt>
                <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">{{ number_format($this->getTotalAccounts()) }}</dd>
            </div>
            <div style="border-left: 4px solid #8338ec; background: #f6f0fe; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #8338ec;">Organisasi terdaftar</dt>
                <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">{{ number_format($this->getTotalOrganizations()) }}</dd>
            </div>
            <div style="border-left: 4px solid #ffb703; background: #fff7de; border-radius: 0.5rem; padding: 0.5rem 0.75rem;">
                <dt style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #b57e00;">Organisasi terbit</dt>
                <dd style="margin-top: 0.15rem; font-weight: 800; color: #073b32;">{{ number_format($this->getPublishedOrganizations()) }}</dd>
            </div>
        </dl>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b-2 border-gray-200 text-left text-xs text-gray-500">
                    <th class="py-2 pr-3 font-semibold">Nama</th>
                    <th class="py-2 pr-3 font-semibold">Email</th>
                    <th class="py-2 pr-3 font-semibold">Organisasi</th>
                    <th class="py-2 font-semibold">Dibuat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getAccounts() as $account)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3">{{ $account->name }}</td>
                        <td class="py-2 pr-3">{{ $account->email }}</td>
                        <td class="py-2 pr-3">{{ $account->organization?->name ?? '-' }}</td>
                        <td class="py-2">{{ $account->created_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-2 text-gray-500">Belum ada akun pengurus SBA.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
