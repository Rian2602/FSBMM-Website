<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @yield('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body class="flex min-h-screen flex-col bg-stone-50">
    <div id="reading-progress" aria-hidden="true"></div>

    <a href="#main-content" class="sr-only z-50 focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-full focus:bg-accent focus:px-4 focus:py-2 focus:font-bold focus:text-brand-950">
        Lompat ke konten
    </a>

    <div class="rainbow-bar h-1.5 w-full" aria-hidden="true"></div>

    <header class="sticky top-0 z-40 border-b border-white/10 bg-brand-950/95 text-white shadow-lg backdrop-blur supports-[backdrop-filter]:bg-brand-950/85">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center font-display text-2xl font-bold tracking-tight transition-opacity hover:opacity-80">
                FSBMM<span class="text-gradient">.</span>
            </a>

            <nav class="hidden items-center gap-x-7 text-sm font-semibold md:flex">
                <a href="{{ route('articles.index') }}" aria-current="{{ request()->routeIs('articles.*') ? 'page' : 'false' }}" class="nav-link py-1 transition-colors hover:text-vivid-amber {{ request()->routeIs('articles.*') ? 'text-vivid-amber' : '' }}">Berita</a>
                <a href="{{ route('organizations.index') }}" aria-current="{{ request()->routeIs('organizations.*') ? 'page' : 'false' }}" class="nav-link py-1 transition-colors hover:text-vivid-lime {{ request()->routeIs('organizations.*') ? 'text-vivid-lime' : '' }}">SBA</a>
                <a href="{{ route('eresources.index') }}" aria-current="{{ request()->routeIs('eresources.*') ? 'page' : 'false' }}" class="nav-link py-1 transition-colors hover:text-vivid-sky {{ request()->routeIs('eresources.*') ? 'text-vivid-sky' : '' }}">E-Resource</a>
                <a href="{{ route('courses.index') }}" aria-current="{{ request()->routeIs('courses.*') ? 'page' : 'false' }}" class="nav-link py-1 transition-colors hover:text-vivid-rose {{ request()->routeIs('courses.*') ? 'text-vivid-rose' : '' }}">E-Learning</a>
            </nav>

            @auth
                <a
                    href="{{ auth()->user()->role === 'sba_admin' ? route('filament.sba.pages.dashboard') : route('filament.admin.pages.dashboard') }}"
                    class="btn-vivid hidden !px-4 !py-2 text-xs md:inline-flex"
                >
                    Dashboard
                </a>
            @endauth

            @guest
                <div class="hidden items-center gap-2 md:flex">
                    <a href="{{ route('filament.sba.auth.login') }}" class="btn-vivid !px-4 !py-2 text-xs">Masuk Pengurus</a>
                    <a href="{{ route('filament.admin.auth.login') }}" class="btn-vivid !px-4 !py-2 text-xs">Masuk Admin</a>
                </div>
            @endguest

            {{-- Dark mode toggle --}}
            <button
                type="button"
                id="dark-toggle"
                class="dark-toggle"
                aria-label="Ganti mode tampilan"
                title="Ganti mode terang/gelap"
            >
                <svg class="icon-sun h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg class="icon-moon h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>

            <button
                type="button"
                id="nav-toggle"
                aria-controls="mobile-nav"
                aria-expanded="false"
                aria-label="Buka menu navigasi"
                class="inline-flex items-center justify-center rounded-full p-2 transition-colors hover:bg-white/10 md:hidden"
            >
                <svg id="nav-icon-open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg id="nav-icon-close" class="hidden h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav id="mobile-nav" class="hidden flex-col gap-1 border-t border-white/10 px-4 pb-4 pt-2 text-sm font-semibold md:hidden">
            <a href="{{ route('articles.index') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-amber" style="transition-delay: 0ms">Berita</a>
            <a href="{{ route('organizations.index') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-lime" style="transition-delay: 40ms">SBA</a>
            <a href="{{ route('eresources.index') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-sky" style="transition-delay: 80ms">E-Resource</a>
            <a href="{{ route('courses.index') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-rose" style="transition-delay: 120ms">E-Learning</a>
            @auth
                <a href="{{ auth()->user()->role === 'sba_admin' ? route('filament.sba.pages.dashboard') : route('filament.admin.pages.dashboard') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-amber" style="transition-delay: 160ms">Dashboard</a>
            @endauth
            @guest
                <a href="{{ route('filament.sba.auth.login') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-amber" style="transition-delay: 160ms">Masuk Pengurus</a>
                <a href="{{ route('filament.admin.auth.login') }}" class="mobile-nav-item rounded-lg px-3 py-2.5 transition-colors hover:bg-white/10 hover:text-vivid-sky" style="transition-delay: 200ms">Masuk Admin</a>
            @endguest
        </nav>
    </header>

    <main id="main-content" class="flex-1">@yield('content')</main>

    <div class="rainbow-bar h-1" aria-hidden="true"></div>

    <footer class="bg-brand-950 text-white/80">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-3">
            <div>
                <a href="{{ route('home') }}" class="font-display text-xl font-bold text-white">FSBMM<span class="text-accent">.</span></a>
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-white/60">
                    Federasi Serikat Buruh Makanan dan Minuman — bersama menguatkan
                    solidaritas pekerja di seluruh Indonesia.
                </p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-accent">Jelajahi</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('articles.index') }}" class="transition-colors hover:text-accent">Berita</a></li>
                    <li><a href="{{ route('organizations.index') }}" class="transition-colors hover:text-accent">Direktori SBA</a></li>
                    <li><a href="{{ route('eresources.index') }}" class="transition-colors hover:text-accent">E-Resource</a></li>
                    <li><a href="{{ route('courses.index') }}" class="transition-colors hover:text-accent">E-Learning</a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-accent">Federasi</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="transition-colors hover:text-accent">Beranda</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="mx-auto max-w-6xl px-4 py-5 text-xs text-white/50">
                © {{ date('Y') }} FSBMM — Federasi Serikat Buruh Makanan dan Minuman
            </div>
        </div>
    </footer>

    <button
        type="button"
        id="back-to-top"
        aria-label="Kembali ke atas"
        class="btn-vivid invisible fixed bottom-6 right-6 z-50 !rounded-full !p-3 opacity-0 transition-all duration-300 hover:-translate-y-1"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
        </svg>
    </button>

    <script>
        (function () {
            /* ── Mobile nav drawer with animation ── */
            var toggle = document.getElementById('nav-toggle');
            var menu = document.getElementById('mobile-nav');
            var iconOpen = document.getElementById('nav-icon-open');
            var iconClose = document.getElementById('nav-icon-close');
            if (toggle && menu) {
                toggle.addEventListener('click', function () {
                    var isOpen = menu.classList.contains('flex');
                    if (isOpen) {
                        /* Close: stagger items out, then collapse */
                        var items = menu.querySelectorAll('.mobile-nav-item');
                        items.forEach(function (item, i) {
                            item.style.transitionDelay = ((items.length - 1 - i) * 30) + 'ms';
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(-12px)';
                        });
                        setTimeout(function () {
                            menu.classList.remove('flex');
                            menu.classList.add('hidden');
                            iconOpen.classList.remove('hidden');
                            iconClose.classList.add('hidden');
                            toggle.setAttribute('aria-expanded', 'false');
                            /* Reset inline styles for next open */
                            items.forEach(function (item) {
                                item.style.transitionDelay = '';
                                item.style.opacity = '';
                                item.style.transform = '';
                            });
                        }, 180);
                    } else {
                        /* Open: show menu, stagger items in */
                        menu.classList.remove('hidden');
                        menu.classList.add('flex');
                        iconOpen.classList.add('hidden');
                        iconClose.classList.remove('hidden');
                        toggle.setAttribute('aria-expanded', 'true');
                        var items = menu.querySelectorAll('.mobile-nav-item');
                        items.forEach(function (item, i) {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(-12px)';
                            item.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                            setTimeout(function () {
                                item.style.transitionDelay = (i * 40) + 'ms';
                                item.style.opacity = '1';
                                item.style.transform = 'translateX(0)';
                            }, 10);
                        });
                    }
                });
            }

            /* ── Dark mode toggle ── */
            var darkBtn = document.getElementById('dark-toggle');
            var stored = localStorage.getItem('fsbmm_theme');
            if (stored === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            if (darkBtn) {
                darkBtn.addEventListener('click', function () {
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.documentElement.removeAttribute('data-theme');
                        localStorage.removeItem('fsbmm_theme');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        localStorage.setItem('fsbmm_theme', 'dark');
                    }
                });
            }

            /* ── Back-to-top ── */
            var toTop = document.getElementById('back-to-top');
            if (toTop) {
                var shown = false;
                var update = function () {
                    var shouldShow = window.scrollY > 480;
                    if (shouldShow === shown) return;
                    shown = shouldShow;
                    toTop.classList.toggle('invisible', !shown);
                    toTop.classList.toggle('opacity-0', !shown);
                };
                window.addEventListener('scroll', update, { passive: true });
                update();
                toTop.addEventListener('click', function () {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        })();
    </script>
</body>
</html>
