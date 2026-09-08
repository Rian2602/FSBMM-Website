/**
 * FSBMM public site — small, dependency-free interactivity layer.
 * Pattern mirrors spm-kecap-bango's core.js utilities (toast/reveal/counter),
 * adapted for a server-rendered public site: no app state, no SPA routing —
 * every function here progressively enhances Blade-rendered HTML that already
 * works without JS.
 */

/* ── Toast ─────────────────────────────────────────────────────────── */
function ensureToastContainer() {
    let el = document.getElementById('toast-container');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-container';
        el.className = 'toast-container';
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'true');
        document.body.appendChild(el);
    }
    return el;
}

function showToast(message, type = 'info') {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    window.setTimeout(() => {
        toast.classList.add('toast-leaving');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }, 3000);
}

window.FSBMM = window.FSBMM || {};
window.FSBMM.toast = showToast;

/* ── Scroll-reveal (IntersectionObserver, one-shot) ──────────────────── */
function initReveal() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    // Progressive enhancement: elements are visible by default (see app.css).
    // Only arm-and-hide them once we know we can actually reveal them again —
    // never leave a visitor with permanently invisible content.
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        return;
    }

    items.forEach((el) => el.classList.add('reveal-armed'));

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );

    items.forEach((el) => observer.observe(el));
}

/* ── Animated stat counters ───────────────────────────────────────────── */
function animateCounter(el) {
    const target = Number(el.dataset.counterTarget || 0);
    const suffix = el.dataset.counterSuffix || '';
    const duration = 1100;
    const start = performance.now();

    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out-cubic
        const value = Math.round(target * eased);
        el.textContent = value.toLocaleString('id-ID') + suffix;
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    }

    requestAnimationFrame(tick);
}

function initCounters() {
    const items = document.querySelectorAll('[data-counter]');
    if (!items.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        items.forEach((el) => {
            const target = Number(el.dataset.counterTarget || 0);
            const suffix = el.dataset.counterSuffix || '';
            el.textContent = target.toLocaleString('id-ID') + suffix;
        });
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.4 }
    );

    items.forEach((el) => observer.observe(el));
}

/* Back-to-top is already implemented inline in layouts/public.blade.php
   (Tailwind utility classes + a small scoped script) — not duplicated here. */

/* ── Reading progress bar (whole-page scroll) ───────────────────────────── */
function initReadingProgress() {
    const bar = document.getElementById('reading-progress');
    if (!bar) return;

    const update = () => {
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
        bar.style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
    };

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
}

/* ── Copy-link buttons ─────────────────────────────────────────────────── */
function initCopyLinks() {
    document.querySelectorAll('[data-copy-link]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-copy-link');
            try {
                await navigator.clipboard.writeText(url);
                showToast('Tautan disalin ke clipboard.', 'success');
            } catch (err) {
                showToast('Gagal menyalin tautan. Salin manual dari address bar.', 'error');
            }
        });
    });
}

/* ── Download-feedback buttons (real navigation still happens) ─────────── */
function initDownloadFeedback() {
    document.querySelectorAll('[data-download-feedback]').forEach((link) => {
        link.addEventListener('click', () => {
            showToast('Menyiapkan unduhan…', 'info');
        });
    });
}

/* ── Live client-side search/filter for already-rendered lists ─────────── */
function initLiveSearch() {
    document.querySelectorAll('[data-live-search]').forEach((scope) => {
        const input = scope.querySelector('[data-live-search-input]');
        const items = scope.querySelectorAll('[data-live-search-item]');
        const empty = scope.querySelector('[data-live-search-empty]');
        if (!input || !items.length) return;

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;

            items.forEach((item) => {
                const haystack = item.dataset.searchText || '';
                const matches = query === '' || haystack.includes(query);
                item.hidden = !matches;
                if (matches) visibleCount += 1;
            });

            if (empty) empty.hidden = visibleCount !== 0;
        });
    });
}

/* ── Boot ──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initReveal();
    initCounters();
    initReadingProgress();
    initCopyLinks();
    initDownloadFeedback();
    initLiveSearch();
});
