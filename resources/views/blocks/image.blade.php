@if (! empty($payload['image_path']))
    <figure data-reveal class="group mx-auto max-w-6xl px-4 py-10">
        <div class="rounded-3xl bg-linear-to-br from-vivid-sky via-vivid-violet to-vivid-rose p-1.5 shadow-xl transition-transform duration-300 group-hover:-translate-y-1 group-hover:shadow-2xl">
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payload['image_path']) }}"
                alt="{{ $payload['caption'] ?? '' }}"
                class="mx-auto w-full rounded-[calc(1.5rem-4px)] object-cover"
                loading="lazy"
            >
        </div>
        @if (! empty($payload['caption']))
            <figcaption class="mt-3 flex items-center justify-center gap-2 text-center text-sm text-stone-500">
                <span class="inline-block h-1.5 w-6 rounded-full bg-linear-to-r from-vivid-amber via-vivid-rose to-vivid-violet"></span>
                {{ $payload['caption'] }}
            </figcaption>
        @endif
    </figure>
@endif
