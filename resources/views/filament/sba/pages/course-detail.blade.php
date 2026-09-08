<x-filament-panels::page>
    @php $courseProgress = $this->getCourseProgress(); @endphp
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->getCourse()->title }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Tingkat {{ ucfirst($this->getCourse()->level) }}
                    </p>
                </div>
                <a href="{{ \App\Filament\Sba\Pages\MyCoursesPage::getUrl() }}"
                   class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                    ← Kembali ke Kursus Saya
                </a>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $this->getCourse()->description }}</p>
        </div>

        <div>
            <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">Daftar Pelajaran</h3>
            <ol class="space-y-2">
                @foreach ($courseProgress['lessons'] as $item)
                    @php
                        $lesson = $item['lesson'];
                        $complete = $item['done'];
                    @endphp
                    <li class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium
                                {{ $complete ? 'bg-lime-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10' }}">
                                {{ $complete ? '✓' : $loop->iteration }}
                            </span>
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $lesson->title }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- (** executed: spec §6b status: Belum / Selesai /
                                 kuis belum lulus. **) --}}
                            <span class="text-xs {{ $complete ? 'text-lime-600 dark:text-lime-400' : ($lesson->quizzes->isNotEmpty() ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400') }}">
                                {{ $complete ? 'Selesai' : ($lesson->quizzes->isNotEmpty() ? 'Kuis belum lulus' : 'Belum') }}
                            </span>
                            <a href="{{ route('filament.sba.courses.lessons.show', [$this->getCourse()->slug, $lesson->id]) }}"
                               class="rounded-lg bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-500">
                                Baca Materi
                            </a>
@foreach ($lesson->quizzes as $quiz)
                            <a href="{{ route('filament.sba.quizzes.show', $quiz->id) }}"
                               class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-400">
                                Kerjakan Kuis
                            </a>
                        @endforeach
                            {{-- spec §6b: lesson without quiz → manual toggle --}}
                            @if ($lesson->quizzes->isEmpty())
                                <button wire:click="toggleLessonCompletion({{ $lesson->id }})"
                                        class="rounded-lg px-3 py-1 text-xs font-medium {{ $complete ? 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-white/10 dark:text-gray-200' : 'bg-lime-600 text-white hover:bg-lime-500' }}">
                                    {{ $complete ? 'Batal Selesai' : 'Tandai Selesai' }}
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($courseProgress['final_quiz'])
            <div class="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                    🏁 Kuis Akhir — {{ $courseProgress['final_quiz_passed'] ? '✓ lulus' : 'kerjakan setelah semua pelajaran selesai.' }}
                </p>
                <a href="{{ route('filament.sba.quizzes.show', $courseProgress['final_quiz']->id) }}"
                   class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-500">
                    Kerjakan Kuis Akhir
                </a>
            </div>
        @endif
    </div>
</x-filament-panels::page>