<section class="bg-brand-950 py-20 text-white">
    <div class="mx-auto max-w-6xl px-4">
        @if (! empty($payload['eyebrow']))
            <p class="text-sm font-bold uppercase tracking-widest text-accent">{{ $payload['eyebrow'] }}</p>
        @endif
        <h1 class="mt-2 max-w-3xl font-display text-5xl font-bold">{{ $payload['title'] }}</h1>
        @if (! empty($payload['subtitle']))
            <p class="mt-4 max-w-2xl text-lg text-white/80">{{ $payload['subtitle'] }}</p>
        @endif
        @if (! empty($payload['cta_label']))
            <a href="{{ $payload['cta_url'] ?? '#' }}" class="mt-8 inline-block bg-accent px-6 py-3 font-bold text-brand-950">{{ $payload['cta_label'] }}</a>
        @endif
        @if (! empty($payload['image_path']))
            <img src="{{ asset('storage/'.$payload['image_path']) }}" alt="" class="mt-10 max-h-96 w-full border-2 border-white/20 object-cover">
        @endif
    </div>
</section>
