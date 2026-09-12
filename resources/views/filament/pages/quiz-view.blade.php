<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->getQuiz()->title }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->getQuiz()->course->title }} · Ambang lulus {{ $this->getQuiz()->passThreshold() }}
                </p>
            </div>
            <a href="{{ $this->panelRoute('courses.show', [$this->getQuiz()->course->slug]) }}"
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                ← Kembali ke kursus
            </a>
        </div>

        @if ($this->getResult())
            <div class="rounded-xl border p-6 text-center {{ $this->getResult()['passed'] ? 'border-lime-300 bg-lime-50 dark:border-lime-500/30 dark:bg-lime-500/10' : 'border-rose-300 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' }}">
                <p class="text-sm font-medium {{ $this->getResult()['passed'] ? 'text-lime-800 dark:text-lime-200' : 'text-rose-800 dark:text-rose-200' }}">
                    {{ $this->getResult()['passed'] ? '🎉 Lulus!' : 'Belum lulus — coba lagi.' }}
                </p>
                <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ $this->getResult()['score'] }}<span class="text-base text-gray-500">/100</span>
                </p>
                @unless ($this->getResult()['passed'])
                    <a href="{{ request()->url() }}" class="mt-3 inline-block text-sm text-primary-600 hover:underline dark:text-primary-400">
                        Ulangi kuis
                    </a>
                @endunless
            </div>
        @else
            <form wire:submit="submit" class="space-y-6">
                @foreach ($this->getQuiz()->questions as $question)
                    <fieldset class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
                        <legend class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $loop->iteration }}. {{ $question->question }}
                        </legend>
                        <div class="space-y-2">
                            @foreach ($question->options as $option)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-4 py-2.5 text-sm hover:border-primary-400 dark:border-white/10">
                                    <input type="radio"
                                           name="answer-{{ $question->id }}"
                                           value="{{ $option->id }}"
                                           wire:model.live="answers.{{ $question->id }}"
                                           class="text-primary-600 focus:ring-primary-500">
                                    <span class="text-gray-800 dark:text-gray-200">{{ $option->option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <x-filament::button type="submit">
                    Kumpulkan Jawaban
                </x-filament::button>
            </form>
        @endif
    </div>
</x-filament-panels::page>