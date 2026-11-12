<x-layouts.app title="Dashboard" :active-menu="'dashboard'">

    {{-- Chart.js + Datalabels plugin --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

    <style>
        .is-hidden { display: none !important; }
        .skeleton-text { height: 16px; background: #e2e8f0; border-radius: 4px; animation: sk-pulse 1.5s infinite ease-in-out; }
        .skeleton-circle { background: #e2e8f0; border-radius: 9999px; animation: sk-pulse 1.5s infinite ease-in-out; }
        .skeleton-card { background: #ffffff; border-radius: 16px; padding: 24px; animation: sk-pulse 1.5s infinite ease-in-out; }
        @keyframes sk-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        @media (max-width: 639px) {
            .kpi-mobile-scroll {
                display: flex !important;
                overflow-x: auto !important;
                scroll-snap-type: x mandatory !important;
                gap: 10px !important;
                margin-left: -1rem !important;
                margin-right: -1rem !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                padding-bottom: 6px !important;
                -webkit-overflow-scrolling: touch;
            }
            .kpi-mobile-scroll > * {
                min-width: 148px !important;
                max-width: 165px !important;
                flex-shrink: 0 !important;
                scroll-snap-align: start !important;
            }
            .kpi-mobile-scroll::-webkit-scrollbar {
                display: none;
            }

            /* Compact inner card styling for Mobile ONLY */
            .kpi-mobile-scroll .bg-white {
                padding: 14px 12px !important;
                border-radius: 16px !important;
            }
            .kpi-mobile-scroll .rounded-full.flex {
                width: 32px !important;
                height: 32px !important;
                margin-bottom: 8px !important;
            }
            .kpi-mobile-scroll .rounded-full.flex svg {
                width: 16px !important;
                height: 16px !important;
            }
            .kpi-mobile-scroll h3 {
                font-size: 11px !important;
                margin-bottom: 2px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            .kpi-mobile-scroll p.text-\[36px\] {
                font-size: 24px !important;
                margin-bottom: 4px !important;
            }
            .kpi-mobile-scroll p.text-\[13px\] {
                font-size: 11px !important;
                line-height: 1.25 !important;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .kpi-mobile-scroll .mt-4 {
                margin-top: 8px !important;
            }
            .kpi-mobile-scroll a {
                font-size: 10px !important;
                padding: 3px 8px !important;
            }
        }
    </style>

    {{-- ===== Skeleton Loading ===== --}}
    <div data-skeleton class="is-hidden" id="sk-dashboard">
        <div class="mb-5 flex justify-between items-center">
            <div>
                <div class="skeleton-text w-32 mb-2"></div>
                <div class="skeleton-text w-64"></div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="bg-white rounded-2xl shadow-sm p-6 space-y-3">
                    <div class="skeleton-circle w-11 h-11"></div>
                    <div class="skeleton-text w-20"></div>
                    <div class="skeleton-text w-16 h-8"></div>
                    <div class="skeleton-text w-32"></div>
                </div>
            @endfor
        </div>
    </div>

    {{-- ===== Real Content ===== --}}
    <div data-content class="sk-content">

    @if ($error)
        <div class="mb-5">
            <x-alert type="warning" :message="$error" />
        </div>
    @endif

    {{-- ===== Subtitle Header with Live Date & Time Widget ===== --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-sm text-slate-400">Kelola dan pantau kelengkapan dokumen unit secara real-time.</p>
        </div>

        {{-- Date & Time Widget --}}
        <div class="flex items-center flex-shrink-0 transition-all duration-200 hover:-translate-y-[1px] hover:shadow-md cursor-default self-start sm:self-auto"
             style="height: 48px; background-color: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 4px 16px rgba(15,23,42,0.06); padding: 0 16px;">
            {{-- Icon Container --}}
            <div style="width: 32px; height: 32px; background-color: #0F6CBD; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 12px;">
                <svg style="width: 16px; height: 16px; stroke: #FFFFFF; stroke-width: 2;" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </div>
            
            {{-- Date & Time Content --}}
            <div style="display: flex; align-items: center;">
                <span id="live-date" style="font-size: 14px; font-weight: 600; color: #1F2937; white-space: nowrap; font-family: Inter, ui-sans-serif, system-ui, sans-serif;">--</span>
                <span style="width: 1px; height: 16px; background-color: #E5E7EB; margin: 0 12px; display: inline-block; flex-shrink: 0;"></span>
                <span id="live-clock-time" style="font-size: 14px; font-weight: 600; color: #0F6CBD; white-space: nowrap; font-family: Inter, ui-sans-serif, system-ui, sans-serif;">--</span>
            </div>
        </div>
    </div>

    {{-- ===== State: DISCONNECTED ===== --}}
    @if (!$isConnected && !$error)
        <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 text-sm mb-1">Koneksi Google Diperlukan</h3>
                    <p class="text-sm text-slate-500 mb-4 leading-relaxed">Aplikasi belum terhubung dengan akun Google. Silakan lakukan autentikasi OAuth untuk menjelajahi Google Drive.</p>
                    <a href="{{ route('auth.google.status') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-pln-blue hover:bg-blue-700 text-white text-sm font-medium shadow-sm shadow-blue-100 transition-all duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                        Hubungkan Google Account
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== State: CONNECTED BUT NO ROOT FOLDER ===== --}}
    @if ($isConnected && !$isRootFolderConfigured && !$error)
        <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 text-sm mb-1">Root Folder Belum Dipilih</h3>
                    <p class="text-sm text-slate-500 mb-4 leading-relaxed">Google telah terhubung tetapi Root Folder belum dipilih. Pilih folder utama di Drive Anda.</p>
                    <a href="{{ route('auth.google.status') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-pln-blue hover:bg-blue-700 text-white text-sm font-medium shadow-sm shadow-blue-100 transition-all duration-150">Pilih Root Folder</a>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Root Folder Aktif Banner ===== --}}
    @if ($isConnected && $isRootFolderConfigured && !$error)
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider leading-none mb-0.5">Root Folder Aktif</p>
                        <p class="text-sm font-bold text-slate-800 leading-tight">{{ $rootFolderName }}</p>
                        @if ($email)<p class="text-[11px] text-slate-400 leading-tight mt-0.5">{{ $email }}</p>@endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium" style="background:#F0FDF4;color:#166534;">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Terhubung
                    </span>
                    <a href="{{ route('auth.google.status') }}" class="px-3 py-1.5 rounded-xl bg-slate-100/70 hover:bg-slate-200/70 text-slate-600 text-xs font-medium transition-all duration-150">Pengaturan</a>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== 5 KPI Summary Cards ===== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-5 kpi-mobile-scroll">
        <x-summary-card icon="users"               color="blue"     title="Total Unit"          value="{{ $summary['total_units'] ?? 0 }}" description="Unit dan Sub Unit" />
        <x-summary-card icon="check-circle"        color="green"    title="Complete"             value="{{ $summary['complete'] ?? 0 }}"    description="Folder telah difinalisasi" />
        <x-summary-card icon="clock"               color="amber"    title="Pending Finalisasi"   value="{{ $summary['pending'] ?? 0 }}"     description="Folder menunggu finalisasi" actionUrl="{{ route('drive.pending') }}" actionText="Lihat Draft →" />
        <x-summary-card icon="exclamation-triangle" color="red"     title="Belum Upload"         value="{{ $summary['incomplete'] ?? 0 }}"  description="Folder belum memiliki dokumen" />
        <x-summary-card icon="chart-bar"           color="pln-blue" title="Progress"             value="{{ $summary['progress'] ?? 0 }}"    description="<span data-count-desc='{{ $summary['progress'] ?? 0 }}'>0</span>% dokumen telah selesai" isProgress="true" />
    </div>

    @if ($isConnected && $isRootFolderConfigured && !$error)

    {{-- ===== Chart + Quick Actions ===== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        {{-- ── Progress Upload Chart (2/3) ── --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <svg class="w-4 h-4 text-pln-blue flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <span class="text-sm font-semibold text-slate-700">Progress Upload Semester Aktif</span>
                    {{-- Semester Toggle --}}
                    <div class="flex items-center gap-1 ml-1">
                        <button id="btn-sem1" onclick="switchSemester('Semester 1')"
                                class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold transition-all duration-150">
                            Semester 1
                        </button>
                        <button id="btn-sem2" onclick="switchSemester('Semester 2')"
                                class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold transition-all duration-150">
                            Semester 2
                        </button>
                    </div>
                </div>
                {{-- Legend --}}
                <div class="flex items-center gap-4 text-[11px] text-slate-400 flex-shrink-0">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:#22C55E;"></span>Selesai
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:#F59E0B;"></span>Proses
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:#E2E8F0;"></span>Belum
                    </div>
                </div>
            </div>
            <div class="px-5 pt-5 pb-2">
                <div style="position:relative;height:240px;width:100%;">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
            <div class="px-5 py-3 bg-slate-50/60 flex items-center gap-2 text-xs font-medium text-slate-400">
                <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <span>Klik pada bulan untuk melihat detail monitoring.</span>
            </div>
        </div>

        {{-- ── Quick Actions (1/3) ── --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-pln-blue" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    Aksi Cepat
                </h3>
            </div>
            <div class="p-3 space-y-1.5">
                <a href="{{ route('drive.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-slate-50 transition-colors duration-150 group">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-pln-blue transition-colors duration-150">
                        <svg class="w-4 h-4 text-pln-blue group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 leading-tight">Buka Drive Explorer</p>
                        <p class="text-xs text-slate-400 leading-tight mt-0.5">Jelajahi dan upload dokumen</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
                <a href="{{ route('monitoring') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-slate-50 transition-colors duration-150 group">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-pln-blue transition-colors duration-150">
                        <svg class="w-4 h-4 text-pln-blue group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 leading-tight">Lihat Monitoring</p>
                        <p class="text-xs text-slate-400 leading-tight mt-0.5">Status upload per unit</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>

    </div>{{-- end chart + actions grid --}}

    {{-- ===== Folder Semester — Accordion ===== --}}
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4">
            <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-pln-blue" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                Folder Semester di Google Drive
            </h3>
        </div>

        @php
            $semMeta = [
                'Semester 1' => ['range' => 'Desember – Mei',     'slug' => 'sem1'],
                'Semester 2' => ['range' => 'Juni – November',    'slug' => 'sem2'],
            ];
        @endphp

        <div class="px-3 pb-3 space-y-1">
        @foreach (['Semester 1', 'Semester 2'] as $semName)
        @php
            $slug     = $semMeta[$semName]['slug'];
            $range    = $semMeta[$semName]['range'];
            $semData  = $monthlyProgress[$semName] ?? null;
            $semPct   = $semData ? $semData['pct'] : 0.0;
            $mCount   = count($semData['months'] ?? []);
            $pctColor = $semPct >= 100 ? 'text-emerald-600' : ($semPct > 0 ? 'text-amber-600' : 'text-slate-400');
            $pctBg    = $semPct >= 100 ? 'bg-emerald-50' : ($semPct > 0 ? 'bg-amber-50' : 'bg-slate-100');
        @endphp

        <div class="rounded-xl overflow-hidden">
            {{-- Accordion Header --}}
            <button id="head-{{ $slug }}"
                    onclick="toggleAccordion('{{ $slug }}')"
                    class="w-full flex items-center gap-3 px-3.5 py-3 text-left hover:bg-slate-50/80 rounded-xl transition-colors duration-150 cursor-pointer">
                <div class="w-8.5 h-8.5 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-700 leading-tight">{{ $semName }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $range }} &bull; {{ $mCount }} folder</p>
                </div>
                <span class="text-xs font-bold {{ $pctColor }} {{ $pctBg }} px-2.5 py-1 rounded-full mr-1.5 flex-shrink-0">
                    {{ number_format($semPct, 1, ',', '.') }}%
                </span>
                <svg id="chevron-{{ $slug }}"
                     class="w-4 h-4 text-slate-400 flex-shrink-0"
                     style="transition:transform 0.25s ease;"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            {{-- Accordion Body --}}
            <div id="body-{{ $slug }}"
                 style="max-height:0;overflow:hidden;transition:max-height 0.3s ease;">
                <div class="px-2 pt-1 pb-2 space-y-1">
                    @foreach ($semData['months'] ?? [] as $month)
                    @php
                        $mPct      = $month['pct'];
                        $mColor    = $mPct >= 100 ? 'text-emerald-600' : ($mPct > 0 ? 'text-amber-600' : 'text-slate-400');
                        $mBadgeBg  = $mPct >= 100 ? 'bg-emerald-50' : ($mPct > 0 ? 'bg-amber-50' : 'bg-slate-100');
                        $hasFolder = ! empty($month['folder_id']);
                        $mHref     = $hasFolder
                            ? route('drive.show', $month['folder_id'])
                            : 'javascript:void(0)';
                    @endphp
                    <a href="{{ $mHref }}"
                       @if (! $hasFolder) onclick="showToast('Folder {{ $month['name'] }} belum dibuat di Google Drive'); return false;" @endif
                       class="flex items-center gap-3 py-2 px-3 rounded-xl hover:bg-slate-50 transition-colors duration-150 group">
                        <div class="w-7 h-7 {{ $mBadgeBg }} rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-3.5 h-3.5 {{ $mColor }}" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-slate-600 flex-1 group-hover:text-pln-blue transition-colors">{{ $month['name'] }}</span>
                        <span class="text-[11px] font-semibold {{ $mColor }} px-2 py-0.5 {{ $mBadgeBg }} rounded-full">
                            {{ number_format($mPct, 1, ',', '.') }}%
                        </span>
                        <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500 group-hover:translate-x-0.5 transition-all flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
        </div>
    </div>

    @endif

    </div>{{-- end data-content --}}

    {{-- ===== Scripts ===== --}}
    <script>
        // ── Toast Notification ────────────────
        function showToast(message) {
            let container = document.getElementById('simdok-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'simdok-toast-container';
                container.className = 'fixed top-5 right-5 z-50 flex flex-col gap-2.5 pointer-events-none';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-900/90 text-white text-xs font-semibold shadow-2xl transition-all duration-300 transform -translate-y-2 opacity-0 backdrop-blur-md';
            toast.innerHTML = `
                <div class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse flex-shrink-0"></div>
                <span class="leading-tight">${message}</span>
            `;

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('-translate-y-2', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            });

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('-translate-y-2', 'opacity-0');
                setTimeout(() => toast.remove(), 350);
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // ── Live Clock & Date ─────────────────────────────────────
            function updateLiveClock() {
                const now = new Date();
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                const dayName = days[now.getDay()];
                const dayDate = String(now.getDate()).padStart(2, '0');
                const monthName = months[now.getMonth()];
                const year = now.getFullYear();

                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');

                const dateEl = document.getElementById('live-date');
                const timeEl = document.getElementById('live-clock-time');

                if (dateEl) dateEl.textContent = `${dayName}, ${dayDate} ${monthName} ${year}`;
                if (timeEl) timeEl.textContent = `${hours}:${minutes}`;
            }
            updateLiveClock();
            setInterval(updateLiveClock, 1000);

            // ── Count-up Animation ──────────────────────────────────────
            document.querySelectorAll('[data-count]').forEach(el => {
                const target = parseFloat(el.getAttribute('data-count'));
                const isPercent = el.getAttribute('data-percent') === 'true';
                if (isNaN(target)) return;

                let current = 0;
                const duration = 700;
                const startTime = performance.now();

                const wrapper = el.closest('.count-animate-wrapper');
                if (wrapper) wrapper.classList.remove('opacity-0');

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easedProgress = 1 - Math.pow(1 - progress, 3);
                    current = easedProgress * target;

                    if (isPercent) {
                        el.textContent = current.toFixed(1).replace('.', ',') + '%';
                    } else {
                        el.textContent = Math.round(current).toLocaleString('id-ID');
                    }

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        if (isPercent) {
                            el.textContent = target.toFixed(1).replace('.', ',') + '%';
                        } else {
                            el.textContent = target.toLocaleString('id-ID');
                        }
                    }
                }
                requestAnimationFrame(step);
            });

            // ── Progress Bar Animation ──────────────────────────────────
            document.querySelectorAll('[data-progress]').forEach(el => {
                const pct = parseFloat(el.getAttribute('data-progress'));
                if (!isNaN(pct)) {
                    setTimeout(() => {
                        el.style.width = Math.min(pct, 100) + '%';
                    }, 100);
                }
            });

            // ── Chart.js Bar Chart ──────────────────────────────────────
            const chartCanvas = document.getElementById('progressChart');
            if (chartCanvas) {
                const monthlyData = @json($monthlyProgress);
                const driveFolderUrl = "/drive/";
                const monitoringUrl = "{{ route('monitoring') }}";

                let currentSemester = "{{ $activeSemester ?? 'Semester 2' }}";

                Chart.register(ChartDataLabels);

                let months = (monthlyData[currentSemester] && monthlyData[currentSemester].months)
                    ? monthlyData[currentSemester].months
                    : [];

                const getChartColors = (dataArr) => {
                    return dataArr.map(m => {
                        const pct = m.pct;
                        if (pct >= 100) return '#22C55E';
                        if (pct > 0)    return '#F59E0B';
                        return '#E2E8F0';
                    });
                };

                const chart = new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: months.map(m => m.label),
                        datasets: [{
                            data: months.map(m => m.pct),
                            backgroundColor: getChartColors(months),
                            borderRadius: 6,
                            borderSkipped: false,
                            barThickness: 28,
                            maxBarThickness: 36,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: { top: 22, bottom: 0 }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1E293B',
                                titleFont: { size: 12, family: 'Inter,ui-sans-serif,sans-serif' },
                                bodyFont: { size: 11, family: 'Inter,ui-sans-serif,sans-serif' },
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    title: (items) => {
                                        const idx = items[0].dataIndex;
                                        return months[idx] ? months[idx].name : '';
                                    },
                                    label: (item) => {
                                        const m = months[item.dataIndex];
                                        if (!m) return '';
                                        return ` Selesai: ${m.complete}/${m.total} unit (${m.pct}%)`;
                                    }
                                }
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                offset: 2,
                                formatter: (val) => val.toFixed(1).replace('.', ',') + '%',
                                font: { size: 10, weight: '600', family: 'Inter,ui-sans-serif,sans-serif' },
                                color: (ctx) => {
                                    const val = ctx.dataset.data[ctx.dataIndex];
                                    if (val >= 100) return '#166534';
                                    if (val > 0)    return '#B45309';
                                    return '#94A3B8';
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: {
                                    font: { size: 11, family: 'Inter,ui-sans-serif,sans-serif' },
                                    color: '#64748B',
                                }
                            },
                            y: {
                                min: 0,
                                max: 115,
                                grid: { color: '#F1F5F9' },
                                border: { display: false },
                                ticks: {
                                    stepSize: 25,
                                    callback: v => v <= 100 ? v + '%' : '',
                                    font: { size: 11, family: 'Inter,ui-sans-serif,sans-serif' },
                                    color: '#94A3B8',
                                }
                            }
                        },
                        onClick: (event, elements) => {
                            if (elements.length > 0) {
                                const m = months[elements[0].index];
                                if (m.folder_id) {
                                    window.location.href = driveFolderUrl + m.folder_id;
                                } else {
                                    showToast('Folder ' + m.name + ' belum dibuat di Google Drive');
                                }
                            }
                        },
                        onHover: (event, elements) => {
                            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        }
                    }
                });

                // Update Semester Active Button
                window.updateSemBtnStyles = (activeSem) => {
                    const btn1 = document.getElementById('btn-sem1');
                    const btn2 = document.getElementById('btn-sem2');
                    if (activeSem === 'Semester 1') {
                        btn1.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-pln-blue text-white shadow-2xs transition-all duration-150';
                        btn2.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500 hover:bg-slate-200 transition-all duration-150';
                    } else {
                        btn2.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-pln-blue text-white shadow-2xs transition-all duration-150';
                        btn1.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500 hover:bg-slate-200 transition-all duration-150';
                    }
                };

                window.switchSemester = (semName) => {
                    currentSemester = semName;
                    updateSemBtnStyles(semName);
                    months = (monthlyData[semName] && monthlyData[semName].months)
                        ? monthlyData[semName].months
                        : [];

                    chart.data.labels = months.map(m => m.label);
                    chart.data.datasets[0].data = months.map(m => m.pct);
                    chart.data.datasets[0].backgroundColor = getChartColors(months);
                    chart.update();
                };

                updateSemBtnStyles(currentSemester);
            }

            // ── Accordion Toggle ──────────────────────────────────────
            window.toggleAccordion = function(id) {
                const body    = document.getElementById('body-' + id);
                const chevron = document.getElementById('chevron-' + id);
                if (!body) return;
                if (body.style.maxHeight && body.style.maxHeight !== '0px') {
                    body.style.maxHeight = '0';
                    if (chevron) chevron.style.transform = '';
                } else {
                    body.style.maxHeight = body.scrollHeight + 'px';
                    if (chevron) chevron.style.transform = 'rotate(180deg)';
                }
            };
        });
    </script>
</x-layouts.app>
