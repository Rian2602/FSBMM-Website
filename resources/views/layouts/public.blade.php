<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @yield('meta')
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-brand-950 text-white">
        <div class="mx-auto max-w-6xl px-4 py-5 flex flex-wrap items-center justify-between gap-y-3">
            <a href="{{ route('home') }}" class="font-display text-2xl font-bold tracking-tight">FSBMM<span class="text-accent">.</span></a>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
                <a href="{{ route('articles.index') }}" class="hover:text-accent">Berita</a>
                <a href="{{ route('organizations.index') }}" class="hover:text-accent">SBA</a>
                <a href="{{ route('eresources.index') }}" class="hover:text-accent">E-Resource</a>
                <a href="{{ route('courses.index') }}" class="hover:text-accent">E-Learning</a>
            </nav>
        </div>
    </header>
    <main class="flex-1">@yield('content')</main>
    <footer class="bg-brand-950 text-white/80 text-sm">
        <div class="mx-auto max-w-6xl px-4 py-6">© {{ date('Y') }} FSBMM — Federasi Serikat Buruh Makanan dan Minuman</div>
    </footer>
</body>
</html>
