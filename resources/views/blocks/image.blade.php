@if (! empty($payload['image_path']))
    <figure class="mx-auto max-w-6xl px-4 py-8">
        <img
            src="{{ asset('storage/'.$payload['image_path']) }}"
            alt="{{ $payload['caption'] ?? '' }}"
            class="mx-auto border-2 border-brand-950"
        >
        @if (! empty($payload['caption']))
            <figcaption class="mt-2 text-center text-sm text-stone-500">{{ $payload['caption'] }}</figcaption>
        @endif
    </figure>
@endif
