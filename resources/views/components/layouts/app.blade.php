<!DOCTYPE html>
<html lang="id" class="min-h-screen">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Smart Document Automation Dashboard — Portal pengelolaan dokumen PLN">

    <title>{{ $title ?? 'SIMDOK' }} — SIMDOK PLN S2JB</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/logo.webp') }}">

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen antialiased bg-slate-50" style="font-family:'Inter',ui-sans-serif,system-ui,sans-serif;">

    {{-- Mobile Overlay --}}
    <div id="sidebar-overlay"
         class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm hidden lg:hidden"
         onclick="closeSidebar()"></div>

    {{-- Page Transition Progress Bar --}}
    <div id="page-progress-bar"></div>

    {{-- Sidebar --}}
    <x-sidebar :active="$activeMenu ?? ''" />

    {{-- Main Content Area --}}
    <div class="lg:ml-64 min-h-screen flex flex-col transition-all duration-300">

        {{-- Header --}}
        <x-header :title="$title ?? 'Dashboard'" />

        @if (config('demo.enabled'))
            {{-- DEMO: Banner global demonstrasi (di aliran konten, di bawah header sticky; tidak menyentuh sidebar fixed) --}}
            <x-demo-banner />
        @endif

        {{-- Global Alert: Google connected but no Root Folder --}}
        @php
            if (config('demo.enabled')) {
                // DEMO: setara file_exists(token.json) & file_exists(settings.json), lewat service simulasi
                $demoOAuth = app(\App\Services\Google\GoogleOAuthService::class);
                $isGoogleConnected = $demoOAuth->hasToken();
                $isRootConfigured = ! empty($demoOAuth->getSettings());
            } else {
                $tokenPath = storage_path('app/google/token.json');
                $settingsPath = storage_path('app/google/settings.json');
                $isGoogleConnected = file_exists($tokenPath);
                $isRootConfigured = file_exists($settingsPath);
            }
        @endphp

        @if ($isGoogleConnected && !$isRootConfigured && !request()->routeIs('auth.google.*'))
            <div class="px-4 sm:px-6 mt-4">
                <div class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl text-xs font-medium"
                     style="background:#FFFBEB; border:1px solid #FDE68A; color:#92400E;">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse flex-shrink-0"></span>
                        <span>Google terhubung tetapi Root Folder belum dipilih. Drive Explorer dan Monitoring belum dapat digunakan.</span>
                    </div>
                    <a href="{{ route('auth.google.status') }}"
                       class="flex-shrink-0 font-semibold underline hover:text-amber-900 transition-colors">
                        Pilih Root Folder →
                    </a>
                </div>
            </div>
        @endif

        {{-- Flash Messages --}}
        @if (session('success') || session('error') || session('warning') || session('info'))
            <div class="px-4 sm:px-6 pt-4">
                @if (session('success'))
                    <x-alert type="success" :message="session('success')" />
                @endif
                @if (session('error'))
                    <x-alert type="error" :message="session('error')" />
                @endif
                @if (session('warning'))
                    <x-alert type="warning" :message="session('warning')" />
                @endif
                @if (session('info'))
                    <x-alert type="info" :message="session('info')" />
                @endif
            </div>
        @endif

        {{-- Page Content --}}
        <main class="flex-1 px-4 sm:px-6 py-6">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="px-4 sm:px-6 py-5 mt-auto">
            <p class="text-[11px] text-slate-400 text-right leading-none">
                SIMDOK v1.0 — System Monitoring Dokumen &bull; &copy; PT PLN (Persero) UIDs S2JB
                @if (config('demo.enabled'))
                    {{-- DEMO: penanda versi demo di footer (teks lain tidak diubah) --}}
                    &bull; {{ config('demo.footer_label') }}
                @endif
            </p>
        </footer>

    </div>

    {{-- ===== Sidebar Toggle + Skeleton + Page Transition Script ===== --}}
    <script>
        function openSidebar() {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar) sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Handle Page Load Progress Bar
        window.addEventListener('beforeunload', () => {
            const bar = document.getElementById('page-progress-bar');
            if (bar) {
                bar.classList.add('is-loading');
                bar.style.width = '70%';
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('page-progress-bar');
            if (bar) {
                bar.style.width = '100%';
                setTimeout(() => {
                    bar.classList.remove('is-loading');
                    bar.style.width = '0%';
                }, 200);
            }
        });
    </script>

</body>

</html>