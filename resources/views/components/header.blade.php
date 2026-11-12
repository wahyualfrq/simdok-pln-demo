@props(['title' => 'Dashboard'])

<header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-100">
    <div class="flex items-center justify-between px-6 h-14">

        {{-- Left: Hamburger (mobile only) + Page title --}}
        <div class="flex items-center gap-3">
            {{-- Hamburger button — visible only on mobile --}}
            <button id="btn-sidebar-toggle"
                    onclick="openSidebar()"
                    title="Buka menu"
                    class="lg:hidden flex items-center justify-center w-9 h-9 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-all duration-150">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        {{-- Right Actions --}}
        <div class="flex items-center gap-2">

            {{-- Refresh Button --}}
            <button id="btn-refresh"
                    onclick="handleGlobalRefresh(event)"
                    title="Refresh halaman & bersihkan cache"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-all duration-150 cursor-pointer">
                <svg id="btn-refresh-icon" class="w-3.5 h-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span class="hidden sm:inline">Refresh</span>
            </button>

            <script>
                function handleGlobalRefresh(e) {
                    if (e) e.preventDefault();
                    const btn = document.getElementById('btn-refresh');
                    const icon = document.getElementById('btn-refresh-icon');
                    if (icon) icon.classList.add('animate-spin');
                    if (btn) {
                        btn.classList.add('opacity-60', 'pointer-events-none');
                    }
                    const u = new URL(window.location.href);
                    u.searchParams.set('refresh', Date.now().toString());
                    window.location.href = u.toString();
                }
            </script>

            {{-- User Profile & Dropdown --}}
            @auth
            <div class="relative pl-2 border-l border-slate-100 ml-1" id="header-profile-wrapper">
                <button onclick="toggleHeaderProfileDropdown(event)"
                        type="button"
                        class="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-full hover:bg-slate-100 transition-all duration-150 text-left focus:outline-none cursor-pointer group">
                    <div class="w-8 h-8 rounded-full bg-pln-blue/10 border border-pln-blue/20 flex items-center justify-center flex-shrink-0 group-hover:border-pln-blue/40 transition-colors">
                        <span class="text-xs font-bold text-pln-blue uppercase">{{ substr(Auth::user()->name, 0, 1) }}</span>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 max-w-[120px] truncate hidden sm:inline-block">{{ Auth::user()->name }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 transition-transform duration-150" id="header-profile-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div id="header-profile-dropdown" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-white border border-slate-100 shadow-xl p-1.5 z-50 origin-top-right focus:outline-none">
                    <div class="px-3 py-2.5 border-b border-slate-50">
                        <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ Auth::user()->name }}</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5 leading-tight">{{ Auth::user()->username }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="block w-full mt-1">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-red-600 hover:bg-red-50 text-left transition-colors cursor-pointer group">
                            <svg class="w-4 h-4 text-red-500 group-hover:text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            <span>Keluar / Logout</span>
                        </button>
                    </form>
                </div>
            </div>

            <script>
                function toggleHeaderProfileDropdown(event) {
                    event.stopPropagation();
                    const dropdown = document.getElementById('header-profile-dropdown');
                    const chevron = document.getElementById('header-profile-chevron');
                    if (dropdown.classList.contains('hidden')) {
                        dropdown.classList.remove('hidden');
                        if (chevron) chevron.classList.add('rotate-180');
                    } else {
                        dropdown.classList.add('hidden');
                        if (chevron) chevron.style.transform = '';
                    }
                }
                document.addEventListener('click', function(event) {
                    const wrapper = document.getElementById('header-profile-wrapper');
                    const dropdown = document.getElementById('header-profile-dropdown');
                    const chevron = document.getElementById('header-profile-chevron');
                    if (wrapper && dropdown && !wrapper.contains(event.target)) {
                        dropdown.classList.add('hidden');
                        if (chevron) chevron.classList.remove('rotate-180');
                    }
                });
            </script>
            @endauth

        </div>
    </div>
</header>
