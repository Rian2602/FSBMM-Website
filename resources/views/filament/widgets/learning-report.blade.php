<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-academic-cap">
        <x-slot name="heading">
            Laporan Pembelajaran
        </x-slot>

        <div class="space-y-6">
            @forelse ($this->getReport() as $item)
                @php
                    $completers = $item['rows']->where('is_complete', true)->count();
                    $totalUsers = $item['rows']->count();
                @endphp
                <div>
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $item['course']->title }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $completers }}/{{ $totalUsers }} peserta selesai
                        </span>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200 text-left text-xs text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3 font-semibold">Peserta</th>
                                <th class="py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($item['rows'] as $row)
                                @php
                                    // spec: sudah (Selesai) / sedang (in-progress) / belum
                                    $status = $row['is_complete'] ? 'Selesai'
                                        : ($row['lessons_done'] > 0 ? 'Sedang' : 'Belum');
                                    $pill = $row['is_complete']
                                        ? 'bg-lime-100 text-lime-700 dark:bg-lime-500/10 dark:text-lime-300'
                                        : ($row['lessons_done'] > 0
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400');
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2 pr-3">{{ $row['user']->name }}</td>
                                    <td class="py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $pill }}">
                                            {{ $status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-2 text-gray-500">Belum ada peserta.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada kursus terbit.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>