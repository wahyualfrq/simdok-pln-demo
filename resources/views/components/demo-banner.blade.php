{{-- DEMO: Banner global SATU BARIS, dapat ditutup. Hanya tampil saat config('demo.enabled'). Pilihan "tutup" disimpan di sessionStorage (dibungkus try/catch) sehingga tampil lagi pada sesi/tab baru. Borderless, rounded-2xl, shadow-sm, amber lembut (07_UI_UX_GUIDELINE). --}}
@if (config('demo.enabled'))
    <div id="demo-banner" class="px-4 sm:px-6 pt-3">
        <div role="note" class="flex items-center gap-2 px-3 py-2 rounded-2xl shadow-sm bg-amber-50 text-amber-800 w-full">
            <svg class="w-4 h-4 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <p class="text-xs flex-1">
                <span class="font-bold">{{ config('demo.banner.title') }}</span>
                &mdash; {{ config('demo.banner.message') }}
            </p>
            <button type="button" id="demo-banner-close" aria-label="Tutup banner demo" class="flex-shrink-0 p-1 rounded-lg text-amber-700 hover:bg-amber-100 transition-colors duration-150">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
    <script>
        (function () {
            var banner = document.getElementById('demo-banner');
            if (!banner) { return; }
            try {
                if (window.sessionStorage.getItem('simdok_demo_banner_closed') === '1') { banner.remove(); return; }
            } catch (e) { /* sessionStorage tidak tersedia: banner tetap tampil */ }
            document.getElementById('demo-banner-close').addEventListener('click', function () {
                banner.remove();
                try { window.sessionStorage.setItem('simdok_demo_banner_closed', '1'); } catch (e) { /* abaikan */ }
            });
        })();
    </script>
@endif
