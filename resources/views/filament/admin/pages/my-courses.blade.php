<x-filament-panels::page>
    @php $rows = $this->getCourses(); @endphp
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($rows as $row)
            @php
                $course = $row['course'];
                $done = $row['lessons_done'];
                $total = $row['lessons_total'];
                $complete = $row['is_complete'];
            @endphp
            <a href="{{ route('filament.admin.courses.show', $course->slug) }}"
               class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $course->title }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ ucfirst($course->level) }} · {{ $total }} pelajaran
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($complete)
                            <span class="rounded-full bg-lime-600 px-2 py-0.5 text-[11px] font-semibold text-white">✓ Selesai</span>
                        @endif
                        <x-filament::icon icon="heroicon-o-arrow-right"
                                          class="h-5 w-5 shrink-0 text-gray-400 transition group-hover:text-primary-500" />
                    </div>
                </div>

                <p class="mt-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $course->description }}</p>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Progres</span>
                        <span>{{ $done }}/{{ $total }} pelajaran selesai</span>
                    </div>
                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500 transition-all"
                             style="width: {{ $total > 0 ? round(($done / $total) * 100) : 0 }}%"></div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10">
            Belum ada kursus yang terbit.
        </div>
    @endif
</x-filament-panels::page>