<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-users">
        <x-slot name="heading">
            Akun SBA
        </x-slot>

        <dl class="mb-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500">Akun SBA</dt>
                <dd>{{ number_format($this->getTotalAccounts()) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Organisasi terdaftar</dt>
                <dd>{{ number_format($this->getTotalOrganizations()) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Organisasi terbit</dt>
                <dd>{{ number_format($this->getPublishedOrganizations()) }}</dd>
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
