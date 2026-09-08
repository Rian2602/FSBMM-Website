<x-filament-panels::page>
    <div class="space-y-4">
        <a href="{{ route('filament.sba.courses.show', $this->getCourse()->slug) }}"
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            ← Kembali ke {{ $this->getCourse()->title }}
        </a>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">{{ $this->getLesson()->title }}</h2>
            {{-- (** trusted HTML: authored only by authenticated staff — same
                 convention as article/page content (AGENTS.md). **) --}}
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $this->getLesson()->content !!}
            </div>
        </div>
    </div>
</x-filament-panels::page>