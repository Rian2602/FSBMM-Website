<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getCourses() as $course)
            @php
                $done = $this->getProgress()->forCourse($course)->count();
                $total = $course->lessons->count();
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
                    <x-filament::icon icon="heroicon-o-arrow-right"
                                      class="h-5 w-5 shrink-0 text-gray-400 transition group-hover:text-primary-500" />
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

    @if ($this->getCourses()->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10">
            Belum ada kursus yang terbit.
        </div>
    @endif
</x-filament-panels::page>