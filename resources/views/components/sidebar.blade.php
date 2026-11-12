@props(['active' => ''])

<aside id="app-sidebar"
       class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-slate-100 transition-transform duration-300 -translate-x-full lg:translate-x-0"
       style="width:256px; background:#fff;">

    {{-- ===== Logo ===== --}}
    <div class="flex items-center gap-3 px-5 py-[18px] border-b border-slate-100">
        <img src="{{ asset('images/logo.webp') }}" alt="PLN Logo" class="w-8 h-8 object-contain flex-shrink-0">
        <div class="min-w-0">
            <p class="text-sm font-bold text-slate-900 leading-tight truncate">SIMDOK</p>
            <p class="text-[11px] text-slate-400 leading-tight truncate">PLN S2JB</p>
        </div>
    </div>

    {{-- ===== Navigation ===== --}}
    <nav class="flex-1 px-3 py-5 space-y-0.5 overflow-y-auto">

        <p class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Navigasi</p>

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           id="nav-dashboard"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active === 'dashboard'
                      ? 'bg-pln-blue text-white'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <svg class="w-4.5 h-4.5 flex-shrink-0 {{ $active === 'dashboard' ? 'text-white' : 'text-slate-400 group-hover:text-slate-600' }}"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            <span class="truncate">Dashboard</span>
        </a>

        {{-- Drive Explorer --}}
        <a href="{{ route('drive.index') }}"
           id="nav-drive"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active === 'drive'
                      ? 'bg-pln-blue text-white'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <svg class="w-4.5 h-4.5 flex-shrink-0 {{ $active === 'drive' ? 'text-white' : 'text-slate-400 group-hover:text-slate-600' }}"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>
            <span class="truncate">Drive Explorer</span>
        </a>

        {{-- Monitoring --}}
        <a href="{{ route('monitoring') }}"
           id="nav-monitoring"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active === 'monitoring'
                      ? 'bg-pln-blue text-white'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <svg class="w-4.5 h-4.5 flex-shrink-0 {{ $active === 'monitoring' ? 'text-white' : 'text-slate-400 group-hover:text-slate-600' }}"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            <span class="truncate">Monitoring</span>
        </a>

        {{-- Separator --}}
        <div class="my-3 border-t border-slate-100"></div>
        <p class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Konfigurasi</p>

        {{-- Google Connection --}}
        <a href="{{ route('auth.google.status') }}"
           id="nav-google-auth"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active === 'google-auth'
                      ? 'bg-pln-blue text-white'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <svg class="w-4.5 h-4.5 flex-shrink-0 {{ $active === 'google-auth' ? 'text-white' : 'text-slate-400 group-hover:text-slate-600' }}"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
            </svg>
            <span class="flex-1 truncate">Google</span>
            @php
                if (config('demo.enabled')) {
                    // DEMO: status koneksi simulasi lewat service (tanpa membaca token.json)
                    $isGoogleConnected = app(\App\Services\Google\GoogleOAuthService::class)->hasToken();
                } else {
                    $tokenPath = storage_path('app/google/token.json');
                    $isGoogleConnected = file_exists($tokenPath) && !empty(json_decode(file_get_contents($tokenPath), true)['refresh_token'] ?? '');
                }
            @endphp
            <span title="{{ $isGoogleConnected ? 'Terhubung' : 'Tidak terhubung' }}"
                  class="w-2 h-2 rounded-full flex-shrink-0 {{ $isGoogleConnected ? 'bg-green-400' : 'bg-slate-300' }}"></span>
        </a>

    </nav>

</aside>
