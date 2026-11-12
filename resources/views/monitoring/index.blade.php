<x-layouts.app title="Monitoring" :active-menu="'monitoring'">

    {{-- ===== PDF.js Library CDN ===== --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    {{-- ===== Mobile-Only Responsive Styles ===== --}}
    <style>
        @media (max-width: 639px) {
            /* Header: stack vertically on mobile */
            .mon-page-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.75rem !important;
                margin-bottom: 1rem !important;
            }
            /* Header subtitle: full width */
            .mon-page-header > div:first-child {
                min-width: 0;
                max-width: 100%;
            }
            /* Action buttons: horizontal scroll strip */
            .mon-page-header > div:last-child {
                display: flex !important;
                width: 100% !important;
                overflow-x: auto !important;
                padding-bottom: 4px !important;
                flex-shrink: 0;
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            .mon-page-header > div:last-child::-webkit-scrollbar {
                display: none !important;
            }
            .mon-page-header > div:last-child button {
                flex-shrink: 0 !important;
                white-space: nowrap !important;
                font-size: 0.75rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            /* Summary row: horizontal scroll on mobile */
            .mon-summary-row {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: none !important;
            }
            .mon-summary-row::-webkit-scrollbar { display: none !important; }
            /* Inner flex container: don't collapse width */
            .mon-summary-row > div {
                min-width: max-content !important;
            }
            /* Each stat cell: fixed width, no shrink */
            .mon-summary-row > div > div {
                flex: 0 0 auto !important;
                min-width: 85px !important;
                width: 85px !important;
                padding: 0.75rem 0.875rem !important;
            }
            /* Progress bar container inside stat cell: full width, no extra padding */
            .mon-summary-row > div > div > div {
                width: 100% !important;
                min-width: 0 !important;
                padding: 0 !important;
                flex: none !important;
            }
            /* Progress bar fill: keep inline style width intact */
            .mon-summary-row > div > div > div > div {
                height: 4px !important;
            }
            /* Filter card: stack rows */
            .mon-filter-inner {
                flex-direction: column !important;
                gap: 0.75rem !important;
            }
            .mon-filter-inner form {
                width: 100% !important;
                justify-content: space-between !important;
            }
            .mon-filter-inner form .relative {
                flex: 1 !important;
            }
            .mon-filter-inner form select {
                width: 100% !important;
            }
            .mon-filter-inner > div {
                width: 100% !important;
            }
            .mon-filter-inner > div .relative {
                width: 100% !important;
            }
            /* Monitoring table: horizontal scroll */
            .mon-region-table {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            .mon-region-table table {
                min-width: 580px !important;
            }

            /* ===== PDF/Excel Print Modal – Mobile Responsive ===== */
            /* Modal shrinks to content height, not forced full-screen */
            #modal-pdf-preview .mon-modal-inner {
                width: 100vw !important;
                height: auto !important;
                max-width: 100vw !important;
                max-height: 96dvh !important;
                border-radius: 0.75rem !important;
                margin: auto !important;
            }
            /* Stack the split-screen vertically, shrink to content */
            #modal-pdf-preview .mon-modal-body {
                flex-direction: column !important;
                flex: 0 0 auto !important;
                min-height: 0 !important;
            }
            /* Left (PDF viewer) panel: fixed height when visible on mobile */
            #modal-pdf-preview .mon-pdf-panel {
                width: 100% !important;
                min-width: 0 !important;
                border-right: none !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }
            /* When PDF panel is active (not hidden), give it a proper tall height */
            #modal-pdf-preview .mon-pdf-panel:not(.mon-panel-hidden) {
                flex: 0 0 62vh !important;
                height: 62vh !important;
                min-height: 62vh !important;
            }
            /* When PDF panel is active, make modal inner taller to accommodate */
            #modal-pdf-preview:has(.mon-pdf-panel:not(.mon-panel-hidden)) .mon-modal-inner {
                height: 96dvh !important;
            }
            #modal-pdf-preview:has(.mon-pdf-panel:not(.mon-panel-hidden)) .mon-modal-body {
                flex: 1 1 auto !important;
            }
            /* Right (footer form) panel: fixed height, full width, scrollable */
            #modal-pdf-preview .mon-footer-panel {
                width: 100% !important;
                min-width: 0 !important;
                flex: 0 0 auto !important;
                max-height: 42vh !important;
                overflow-y: auto !important;
                border-left: none !important;
                border-top: 1px solid #e2e8f0 !important;
                padding: 0.875rem !important;
            }
            /* Compact form fields inside footer panel on mobile */
            #modal-pdf-preview .mon-footer-panel .space-y-3 {
                gap: 0.5rem !important;
            }
            #modal-pdf-preview .mon-footer-panel input[type="text"] {
                padding: 0.375rem 0.625rem !important;
                font-size: 0.75rem !important;
            }
            #modal-pdf-preview .mon-footer-panel label {
                font-size: 0.7rem !important;
                margin-bottom: 0.125rem !important;
            }
            #modal-pdf-preview .mon-footer-panel .pt-3 {
                padding-top: 0.5rem !important;
            }
            #modal-pdf-preview .mon-footer-panel .pt-2 {
                padding-top: 0.25rem !important;
            }
            /* Tab switcher strip: only visible on mobile */
            #modal-pdf-preview .mon-modal-tabs {
                display: flex !important;
            }
            /* Hide the zoom toolbar on mobile when PDF panel is hidden */
            #modal-pdf-preview .mon-pdf-panel.mon-panel-hidden {
                display: none !important;
            }
            /* Hide footer panel when PDF tab is active */
            #modal-pdf-preview .mon-footer-panel.mon-panel-hidden {
                display: none !important;
            }
            /* Action bar: stack vertically on mobile */
            #modal-pdf-preview .mon-modal-actions {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.5rem !important;
                padding: 0.625rem 0.875rem !important;
            }
            #modal-pdf-preview .mon-modal-actions > button:first-child {
                order: 2 !important;
                width: 100% !important;
                text-align: center !important;
                justify-content: center !important;
                padding: 0.5rem !important;
                font-size: 0.7rem !important;
            }
            #modal-pdf-preview .mon-modal-actions > div {
                order: 1 !important;
                width: 100% !important;
                display: flex !important;
                gap: 0.5rem !important;
            }
            #modal-pdf-preview .mon-modal-actions > div button {
                flex: 1 !important;
                font-size: 0.7rem !important;
                padding: 0.625rem 0.375rem !important;
                justify-content: center !important;
            }
            /* Modal header: compact on mobile */
            #modal-pdf-preview .mon-modal-header {
                padding: 0.75rem 1rem !important;
            }
            #modal-pdf-preview .mon-modal-header p {
                display: none !important;
            }
        }

        /* Tab switcher hidden on desktop (>= 640px) */
        @media (min-width: 640px) {
            #modal-pdf-preview .mon-modal-tabs {
                display: none !important;
            }
        }

        /* Tab button active state */
        .mon-tab-btn.mon-tab-active {
            color: #059669 !important;
            border-bottom-color: #059669 !important;
            font-weight: 700 !important;
        }
    </style>

    {{-- ===== Page Header ===== --}}
    <div class="mon-page-header flex items-start justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Monitoring Upload</h2>
            <p class="text-sm text-slate-400 mt-0.5">Status kelengkapan dokumen per unit — Database Cache Instant (0 Google API Call)</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($selectedSemesterId)
            {{-- Sync Engine Google Drive --}}
            <button type="button"
                    id="btn-sync-drive"
                    onclick="startDriveSync()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-pln-blue rounded-xl shadow-sm hover:bg-blue-700 active:scale-95 transition-all duration-150 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                <svg id="btn-sync-icon" class="w-4 h-4 text-white transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span id="btn-sync-label">Sync Google Drive</span>
            </button>

            {{-- Cetak PDF Button --}}
            <button type="button"
                    id="btn-cetak-pdf"
                    onclick="openPdfPreviewModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700 active:scale-95 transition-all duration-150 cursor-pointer">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                <span>Cetak PDF</span>
            </button>

            {{-- Cetak Excel Button --}}
            <button type="button"
                    id="btn-cetak-excel-header"
                    onclick="downloadExcelReport()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl shadow-sm active:scale-95 transition-all duration-150 cursor-pointer"
                    style="background-color: #107c41; color: #ffffff;">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color: #ffffff;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span style="color: #ffffff;">Cetak Excel</span>
            </button>
            @endif
        </div>
    </div>

    {{-- ===== Real Content ===== --}}
    <div data-content class="sk-content">

    @if ($error)
        <div class="mb-5"><x-alert type="error" :message="$error" /></div>
    @endif

    @if (isset($rootWarning) && $rootWarning)
        <div class="mb-5"><x-alert type="warning" :message="$rootWarning" /></div>
    @endif

    {{-- ===== Sync Progress Banner (Interactive Real-time Polling) ===== --}}
    <div id="sync-progress-banner"
         class="hidden mb-5 rounded-2xl shadow-xl p-5 transition-all duration-300 relative overflow-hidden"
         style="background: linear-gradient(135deg, #0F6CBD 0%, #004B87 100%); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="absolute -right-10 -bottom-10 w-40 h-40 rounded-full blur-2xl pointer-events-none" style="background: rgba(255, 255, 255, 0.1);"></div>
        <div class="flex items-center justify-between gap-4 mb-3 relative z-10">
            <div class="flex items-center gap-3.5">
                <div id="sync-spinner" class="w-9 h-9 rounded-xl flex items-center justify-center animate-spin shrink-0" style="background: rgba(255, 255, 255, 0.2);">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div>
                    <h4 id="sync-status-title" class="text-sm font-bold tracking-wide uppercase" style="color: #ffffff;">Sinkronisasi Google Drive & Sheet Sedang Berjalan...</h4>
                    <p id="sync-progress-text" class="text-xs mt-0.5 font-medium" style="color: #BAE6FD;">Memindai folder & file bukti screenshot di Google Drive...</p>
                </div>
            </div>
            <span id="sync-progress-percent" class="text-lg font-extrabold font-mono tracking-tight px-3.5 py-1 rounded-xl shadow-inner" style="background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3);">0%</span>
        </div>
        <div class="w-full rounded-full h-3 overflow-hidden p-0.5 relative z-10" style="background: rgba(0, 0, 0, 0.25);">
            <div id="sync-progress-bar" class="h-full rounded-full transition-all duration-300 shadow-md" style="width: 0%; background: linear-gradient(90deg, #38BDF8 0%, #FFFFFF 100%);"></div>
        </div>
    </div>

    {{-- ===== Filter ===== --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
        <div class="mon-filter-inner flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <form method="GET" action="{{ route('monitoring') }}" class="flex items-center gap-3">
                <label for="filter-semester" class="text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Semester</label>
                <div class="relative">
                    <select id="filter-semester"
                            name="semester_id"
                            onchange="this.form.submit()"
                            class="block pl-4 pr-9 py-2 text-sm text-slate-700 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 appearance-none cursor-pointer font-medium">
                        @foreach ($semesters as $semester)
                            <option value="{{ $semester }}" {{ $selectedSemesterId === $semester ? 'selected' : '' }}>
                                {{ $semester }}
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </form>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <label for="search-unit" class="text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Cari Unit</label>
                <div class="relative w-full sm:w-64">
                    <input type="text"
                           id="search-unit"
                           placeholder="Ketik nama unit..."
                           class="block w-full pl-4 pr-9 py-2 text-sm text-slate-700 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 font-medium"
                           oninput="filterUnits(this.value)">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($selectedSemesterId && !$error)

        {{-- ===== Summary Row ===== --}}
        <div class="mon-summary-row bg-white rounded-2xl shadow-sm mb-5 overflow-hidden">
            <div class="flex items-center">
                {{-- Total Target --}}
                <div class="flex-1 px-5 py-4 min-w-0" style="border-right: 1px solid #f1f5f9;">
                    <p class="text-xl font-bold text-slate-800 leading-none">{{ number_format($summary['total'], 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Total Target</p>
                </div>
                {{-- Complete --}}
                <div class="flex-1 px-5 py-4 min-w-0" style="border-right: 1px solid #f1f5f9;">
                    <p class="text-xl font-bold text-green-600 leading-none">{{ number_format($summary['complete'], 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Complete</p>
                </div>
                {{-- Pending --}}
                <div class="flex-1 px-5 py-4 min-w-0" style="border-right: 1px solid #f1f5f9;">
                    <p class="text-xl font-bold text-yellow-500 leading-none">{{ number_format($summary['pending'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Pending</p>
                </div>
                {{-- Mismatch --}}
                <div class="flex-1 px-5 py-4 min-w-0" style="border-right: 1px solid #f1f5f9;">
                    <p class="text-xl font-bold text-amber-500 leading-none">{{ number_format($summary['mismatch'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Mismatch</p>
                </div>
                {{-- Incomplete --}}
                <div class="flex-1 px-5 py-4 min-w-0" style="border-right: 1px solid #f1f5f9;">
                    <p class="text-xl font-bold text-red-500 leading-none">{{ number_format($summary['incomplete'], 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Incomplete</p>
                </div>
                {{-- Progress --}}
                <div class="flex-1 px-5 py-4 min-w-0">
                    <p class="text-xl font-bold text-pln-blue leading-none">{{ $summary['progress'] }}%</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Progress</p>
                    <div class="mt-2 h-1 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-pln-blue rounded-full transition-all duration-700"
                             style="width: {{ $summary['progress'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Monitoring Tables ===== --}}
        @if (!empty($regions))
            @foreach ($regions as $region)
            <div class="mb-5 region-container">
                {{-- Region heading --}}
                <div class="flex items-center gap-2 mb-2.5 px-1">
                    <div class="w-1 h-4 rounded-full bg-pln-blue"></div>
                    <h3 class="text-sm font-semibold text-slate-700">{{ $region['name'] }}</h3>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="mon-region-table overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr style="border-bottom:1px solid #f1f5f9; background:#fafafa;">
                                    <th class="text-left px-5 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider w-[40%]">Unit</th>
                                    @foreach ($months as $month)
                                        <th class="text-center px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider w-[10%]">{{ $month['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($region['units'] as $unit)
                                <tr class="hover:bg-slate-50/60 transition-colors duration-100 unit-row" data-unit-name="{{ strtolower($unit['name']) }} {{ strtolower($unit['originalName']) }}">
                                    <td class="px-5 py-3 w-[40%]">
                                        <span class="text-sm font-medium text-slate-700">{{ $unit['name'] }}</span>
                                        @if ($unit['originalName'] !== $unit['name'])
                                            <span class="text-[10px] text-slate-400 block mt-0.5">{{ $unit['originalName'] }}</span>
                                        @endif
                                    </td>
                                    @foreach ($months as $month)
                                        @php
                                            $status = $unit['statuses'][$month['name']] ?? 'incomplete';
                                            $evidenceFile = $unit['evidenceFiles'][$month['name']] ?? null;
                                            $lastSync = $unit['lastSync'] ?? null;
                                            $navigateUrl = route('drive.navigate', [
                                                'semester' => $selectedSemesterId,
                                                'month' => $month['name'],
                                                'daerah' => $region['name'],
                                                'unit' => $unit['originalName']
                                            ]);

                                            $title = match ($status) {
                                                'complete' => "COMPLETE — Centang Sheet & Screenshot Valid ({$evidenceFile})",
                                                'pending'  => "PENDING FINALISASI — Ada " . ($unit['evidenceCounts'][$month['name']] ?? 1) . " file di Drive, belum ceklist di Sheet",
                                                'mismatch' => "MISMATCH — Ketidakcocokan Google Sheet vs Drive (Bukti: " . ($evidenceFile ?: 'TIDAK ADA') . ")",
                                                default    => "BELUM UPLOAD — Belum ada bukti screenshot di Drive",
                                            };
                                        @endphp
                                        <td class="px-3 py-3 text-center w-[10%]">
                                            @if ($status === 'complete')
                                                <a href="{{ $navigateUrl }}"
                                                   title="{{ $title }}"
                                                   class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-all duration-150 hover:scale-110"
                                                   style="background:#DCFCE7; color:#16A34A;">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                    </svg>
                                                </a>
                                            @elseif ($status === 'pending')
                                                {{-- Pending Finalisasi: ada file di Drive tapi belum ceklist di Sheet --}}
                                                <a href="{{ $navigateUrl }}"
                                                   title="{{ $title }}"
                                                   class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-all duration-150 hover:scale-110"
                                                   style="background:#FEF9C3; color:#CA8A04;">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="w-2.5 h-2.5">
                                                        <circle cx="10" cy="10" r="5"/>
                                                    </svg>
                                                </a>
                                            @elseif ($status === 'mismatch')
                                                <a href="{{ $navigateUrl }}"
                                                   title="{{ $title }}"
                                                   class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-all duration-150 hover:scale-110"
                                                   style="background:#FEF3C7; color:#D97706;">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625l6.28-10.875zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                                    </svg>
                                                </a>
                                            @else
                                                <a href="{{ $navigateUrl }}"
                                                   title="{{ $title }}"
                                                   class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-all duration-150 hover:scale-110"
                                                   style="background:#FEE2E2; color:#DC2626;">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                                    </svg>
                                                </a>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach
        @else
            {{-- Empty State --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center">
                <div class="w-16 h-16 bg-blue-50 text-pln-blue rounded-2xl flex items-center justify-center mx-auto mb-4">
                    @if (isset($rootWarning) && $rootWarning)
                        <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    @else
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    @endif
                </div>

                @if (isset($rootWarning) && $rootWarning)
                    <h3 class="text-base font-bold text-slate-800 mb-1">Root Folder Belum Dipilih</h3>
                    <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Silakan pilih Root Folder Google Drive Anda terlebih dahulu agar sistem SIMDOK dapat menyinkronkan data dokumen & unit Anda.</p>
                    <x-button variant="primary"
                              onclick="window.location.href = '{{ route('auth.google.status') }}'"
                              icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A9 9 0 0112 3v0a9 9 0 019 9v.75M2.25 12.75A2.25 2.25 0 004.5 15h15a2.25 2.25 0 002.25-2.25M2.25 12.75H21" />'>
                        Pilih Root Folder Sekarang
                    </x-button>
                @else
                    <h3 class="text-base font-bold text-slate-800 mb-1">Data Monitoring Belum Disinkronkan</h3>
                    <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Database lokal belum memiliki data untuk {{ $selectedSemesterId }}. Klik tombol di bawah untuk menjalankan Sync Engine pertama kali.</p>
                    <x-button variant="primary"
                              onclick="startDriveSync()"
                              icon='<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />'>
                        Jalankan Sync Google Drive
                    </x-button>
                @endif
            </div>
        @endif

    @endif

    </div>{{-- end data-content --}}

    {{-- ===== Modal Preview PDF (Excel-First Single Source of Truth) ===== --}}
    <div id="modal-pdf-preview"
         class="fixed inset-0 z-50 hidden"
         role="dialog"
         aria-modal="true">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
             onclick="closePdfPreviewModal()"></div>

        {{-- Container --}}
        <div class="fixed inset-0 flex items-center justify-center p-2 sm:p-4">
            <div class="mon-modal-inner relative bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col overflow-hidden transform transition-all"
                 style="width: 95vw; height: 92vh; max-width: 1600px; max-height: 960px;">

                {{-- Modal Header --}}
                <div class="mon-modal-header flex items-center justify-between px-6 py-3.5 border-b border-slate-100 bg-slate-50/60 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-6 rounded-full bg-emerald-500"></div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <span>Preview Laporan PDF Monitoring</span>
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800" id="pdf-preview-semester-badge">{{ $selectedSemesterId }}</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Seluruh layout berasal dari Master Template Excel. Tampilan preview 100% identik dengan PDF final.</p>
                        </div>
                    </div>
                    <button onclick="closePdfPreviewModal()"
                            class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 rounded-xl transition-all cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Mobile Tab Switcher (hidden on desktop via CSS) --}}
                <div class="mon-modal-tabs hidden border-b border-slate-100 bg-white shrink-0">
                    <button type="button" id="mob-tab-footer" onclick="switchModalTab('footer')"
                            class="mon-tab-btn mon-tab-active flex-1 py-2.5 text-xs font-semibold text-center border-b-2 border-emerald-600 text-emerald-600 transition-all cursor-pointer">
                        ✏️ Edit Footer
                    </button>
                    <button type="button" id="mob-tab-preview" onclick="switchModalTab('preview')"
                            class="mon-tab-btn flex-1 py-2.5 text-xs font-semibold text-center border-b-2 border-transparent text-slate-500 transition-all cursor-pointer">
                        📄 Preview PDF
                    </button>
                </div>

                {{-- Modal Body: Split Screen (Left PDF Viewer 70%, Right Panel Footer 30%) --}}
                <div class="mon-modal-body flex-1 flex overflow-hidden bg-slate-100/60" style="display: flex; flex-direction: row; flex: 1; min-height: 0;">

                    {{-- Left Side: PDF Viewer (70%) --}}
                    <div id="mob-pdf-panel" class="mon-pdf-panel mon-panel-hidden p-0 flex flex-col relative bg-slate-200/80 border-r border-slate-200" style="flex: 1; height: 100%; min-width: 0; position: relative;">
                        
                        {{-- Zoom Toolbar --}}
                        <div class="flex items-center justify-between px-4 py-2 bg-slate-100 border-b border-slate-200 shrink-0 text-xs shadow-2xs">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    PDF Preview (Master Excel Render)
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 font-medium">Zoom:</span>
                                <button type="button" onclick="zoomPdf(-0.1)" class="px-2.5 py-1 bg-white hover:bg-slate-200 rounded border border-slate-200 font-bold text-slate-700 cursor-pointer shadow-2xs" title="Zoom Out">-</button>
                                <select id="pdf-zoom-select" onchange="setPdfZoom(parseFloat(this.value))" class="px-2 py-1 bg-white border border-slate-200 rounded font-semibold text-slate-700 cursor-pointer">
                                    <option value="0.5">50%</option>
                                    <option value="0.68" selected>68% (Default)</option>
                                    <option value="0.85">85%</option>
                                    <option value="1.0">100%</option>
                                    <option value="1.25">125%</option>
                                    <option value="1.5">150%</option>
                                </select>
                                <button type="button" onclick="zoomPdf(0.1)" class="px-2.5 py-1 bg-white hover:bg-slate-200 rounded border border-slate-200 font-bold text-slate-700 cursor-pointer shadow-2xs" title="Zoom In">+</button>
                            </div>
                        </div>

                        {{-- Loading Overlay with Step Progress --}}
                        <div id="pdf-preview-loading" class="absolute inset-0 z-20 bg-slate-900/50 backdrop-blur-sm flex flex-col items-center justify-center gap-3 text-white p-6">
                            <div class="w-10 h-10 border-4 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>
                            <div class="bg-slate-900/90 border border-slate-700 px-5 py-3 rounded-2xl shadow-xl flex flex-col items-center text-center max-w-xs">
                                <span id="pdf-loading-text" class="text-xs font-bold text-emerald-400 mb-1.5">Menyiapkan Preview Excel...</span>
                                <div class="w-48 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div id="pdf-loading-bar" class="bg-emerald-400 h-full transition-all duration-300" style="width: 20%;"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Error Overlay --}}
                        <div id="pdf-preview-error" class="hidden absolute inset-0 z-20 bg-white/95 flex flex-col items-center justify-center p-6 text-center">
                            <div class="w-12 h-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1">Gagal membuat Preview PDF</h4>
                            <p id="pdf-error-message" class="text-xs text-slate-500 max-w-sm mb-4">Silakan coba kembali.</p>
                            <button type="button" onclick="fetchPdfPreview()" class="px-4 py-2 text-xs font-semibold bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 shadow-sm transition-all cursor-pointer">
                                Coba Lagi
                            </button>
                        </div>

                        {{-- Native PDF Viewer Container --}}
                        <div id="pdf-viewport-container" class="w-full h-full overflow-hidden p-2 bg-slate-300/40 relative" style="overflow: hidden; flex: 1; max-width: 100%; height: 100%; position: relative;">
                            <iframe id="pdf-preview-iframe" class="w-full h-full rounded-xl shadow-inner border border-slate-300 bg-white" style="width: 100%; height: 100%; border: none;" src="about:blank"></iframe>
                        </div>
                    </div>

                    {{-- Right Side: Edit Footer Panel (30%) --}}
                    <div id="mob-footer-panel" class="mon-footer-panel bg-white p-5 overflow-y-auto flex flex-col shrink-0 border-l border-slate-100 shadow-sm" style="width: 360px; min-width: 340px; shrink: 0;">
                        <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Panel Edit Footer</h4>
                        </div>

                        <form id="form-pdf-footer" onsubmit="event.preventDefault(); updatePdfPreview();" class="space-y-3 flex-1 text-xs">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Lokasi</label>
                                <input type="text" id="footer-lokasi" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="Palembang">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Tanggal</label>
                                <input type="text" id="footer-tanggal" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="04 Agustus 2026">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Disusun Oleh</label>
                                <input type="text" id="footer-disusun-oleh" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="Nama Penyusun (Demo)">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Jabatan Penyusun</label>
                                <input type="text" id="footer-jabatan-penyusun" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="JABATAN PENYUSUN (DEMO)">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Mengetahui</label>
                                <input type="text" id="footer-mengetahui" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="Nama Pejabat (Demo)">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Jabatan Mengetahui</label>
                                <input type="text" id="footer-jabatan" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all font-medium text-slate-800" placeholder="JABATAN PEJABAT (DEMO)">
                            </div>

                            <div class="pt-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" id="footer-save-default" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                                    <span class="text-xs font-medium text-slate-700">Simpan sebagai Default</span>
                                </label>
                                <p class="text-[10px] text-slate-400 mt-0.5 ml-6">Jika dicentang, semua export berikutnya memakai footer terakhir</p>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                                <button type="button" onclick="updatePdfPreview()" id="btn-update-preview" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span id="btn-update-preview-text">Simpan Perubahan</span>
                                </button>
                                <button type="button" onclick="resetPdfFooterToDefault()" class="w-full py-1.5 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl font-medium text-xs transition-all cursor-pointer text-center">
                                    Reset Ke Default
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if (config('demo.enabled'))
                    {{-- DEMO: catatan export PDF/Excel --}}
                    <div class="px-6 pb-3 shrink-0">
                        <x-demo-note>Contoh keluaran — isi tabel PDF adalah data dummy yang tetap; hanya footer yang mengikuti isian di atas.</x-demo-note>
                    </div>
                @endif

                {{-- Modal Action Bar --}}
                <div class="mon-modal-actions px-6 py-3.5 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0">
                    <button type="button" onclick="closePdfPreviewModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-200/60 rounded-xl transition-all cursor-pointer">
                        Batal
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="downloadExcelReport()" id="btn-export-excel" class="px-5 py-2.5 rounded-xl shadow-md transition-all active:scale-95 flex items-center gap-2 cursor-pointer" style="background-color: #107c41; color: #ffffff;">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="color: #ffffff;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            <span id="btn-export-excel-text" style="color: #ffffff;">Unduh Excel (.xlsx)</span>
                        </button>
                        <button type="button" onclick="downloadFinalPdf()" id="btn-generate-pdf" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all active:scale-95 flex items-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span id="btn-generate-pdf-text">Generate PDF</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ===== Javascript Filter & Polling Logic ===== --}}
    <script>
        function filterUnits(query) {
            const q = query.toLowerCase().trim();
            const regions = document.querySelectorAll('.region-container');

            regions.forEach(region => {
                const rows = region.querySelectorAll('.unit-row');
                let visibleCount = 0;

                rows.forEach(row => {
                    const name = row.getAttribute('data-unit-name') || '';
                    if (name.includes(q)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount > 0 || q === '') {
                    region.style.display = '';
                } else {
                    region.style.display = 'none';
                }
            });
        }

        // ===== Polling & Interactive AJAX Sync Engine =====
        let isSyncing = false;
        let pollInterval = null;
        const selectedSemester = '{{ $selectedSemesterId }}';

        function setButtonLoading(loading) {
            const btn = document.getElementById('btn-sync-drive');
            const icon = document.getElementById('btn-sync-icon');
            const label = document.getElementById('btn-sync-label');
            if (!btn) return;

            if (loading) {
                btn.disabled = true;
                if (icon) icon.classList.add('animate-spin');
                if (label) label.innerText = 'Memproses Sync...';
            } else {
                btn.disabled = false;
                if (icon) icon.classList.remove('animate-spin');
                if (label) label.innerText = 'Sync Google Drive';
            }
        }

        function showBanner(title, text, percent, barWidth) {
            const banner = document.getElementById('sync-progress-banner');
            const titleEl = document.getElementById('sync-status-title');
            const textEl = document.getElementById('sync-progress-text');
            const percentEl = document.getElementById('sync-progress-percent');
            const barEl = document.getElementById('sync-progress-bar');

            if (banner) banner.classList.remove('hidden');
            if (title && titleEl) titleEl.innerText = title;
            if (text && textEl) textEl.innerText = text;
            if (percent !== undefined && percentEl) percentEl.innerText = `${percent}%`;
            if (barWidth !== undefined && barEl) barEl.style.width = `${barWidth}%`;
        }

        function hideBanner() {
            const banner = document.getElementById('sync-progress-banner');
            if (banner) banner.classList.add('hidden');
        }

        function pollSyncStatus() {
            if (!selectedSemester) return;
            const statusUrl = `{{ route('monitoring.sync_status') }}?semester_id=${encodeURIComponent(selectedSemester)}`;

            fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                const status = (data.status || '').toLowerCase();

                if (status === 'running' || status === 'pending') {
                    isSyncing = true;
                    setButtonLoading(true);
                    const processed = data.processed || 0;
                    const total = data.total || 432;
                    const pct = Math.min(100, Math.max(0, data.progress || 0));
                    const currentRegion = data.current_region || '';
                    const currentUnit = data.current_unit || '';

                    let unitDetail = `${processed} / ${total} unit diproses`;
                    if (currentRegion || currentUnit) {
                        unitDetail += ` • Sedang memproses: ${currentRegion} ${currentUnit ? '— ' + currentUnit : ''}`;
                    }

                    showBanner(
                        'Sinkronisasi Google Drive & Sheet Sedang Berjalan...',
                        unitDetail,
                        pct,
                        pct
                    );
                } else if ((status === 'completed' || status === 'success') && isSyncing) {
                    isSyncing = false;
                    showBanner(
                        '✅ Sinkronisasi Selesai!',
                        'Data monitoring berhasil diperbarui. Memuat ulang halaman...',
                        100,
                        100
                    );
                    setButtonLoading(false);
                    if (pollInterval) clearInterval(pollInterval);
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    if (!isSyncing) {
                        hideBanner();
                        setButtonLoading(false);
                    }
                }
            })
            .catch(err => {
                console.warn('Poll status error:', err);
            });
        }

        function startDriveSync() {
            if (isSyncing) return;
            isSyncing = true;
            setButtonLoading(true);

            showBanner(
                'Memulai Sinkronisasi Google Drive...',
                'Memicu pindaian folder dan sheet di background...',
                0,
                0
            );

            const fullSyncUrl = `{{ route('monitoring.full_sync', ['semester_id' => $selectedSemesterId]) }}`;

            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(pollSyncStatus, 1000);

            fetch(fullSyncUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success && data.message) {
                    showBanner(
                        '⚠️ Informasi Sinkronisasi',
                        data.message,
                        100,
                        100
                    );
                }
                pollSyncStatus();
            })
            .catch(err => {
                console.warn('Sync trigger finished/handled:', err);
                pollSyncStatus();
            });
        }

        // ===== Excel-First PDF Preview & Footer Logic =====
        let currentPdfTempId = null;
        let defaultFooterData = null;

        // Zoom scale
        let currentPdfZoom = 0.68; // Default Zoom 68% pada pembukaan pertama
        let isFirstModalOpen = true;

        function openPdfPreviewModal() {
            const modal = document.getElementById('modal-pdf-preview');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            // Reset tab state: on mobile start at "Edit Footer", on desktop ensure both panels shown
            if (window.innerWidth < 640) {
                switchModalTab('footer');
            } else {
                const pdfPanel    = document.getElementById('mob-pdf-panel');
                const footerPanel = document.getElementById('mob-footer-panel');
                if (pdfPanel)    pdfPanel.classList.remove('mon-panel-hidden');
                if (footerPanel) footerPanel.classList.remove('mon-panel-hidden');
            }

            // Atur Zoom 68% HANYA saat pertama kali modal dibuka
            if (isFirstModalOpen) {
                currentPdfZoom = 0.68;
                const select = document.getElementById('pdf-zoom-select');
                if (select) select.value = '0.68';
                isFirstModalOpen = false;
            }

            fetchPdfPreview();
        }

        function closePdfPreviewModal() {
            const modal = document.getElementById('modal-pdf-preview');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            isFirstModalOpen = true;
        }

        // Mobile tab switcher: toggle between "Edit Footer" and "Preview PDF" panels
        function switchModalTab(tab) {
            const pdfPanel    = document.getElementById('mob-pdf-panel');
            const footerPanel = document.getElementById('mob-footer-panel');
            const tabFooter   = document.getElementById('mob-tab-footer');
            const tabPreview  = document.getElementById('mob-tab-preview');

            if (tab === 'preview') {
                if (pdfPanel)    pdfPanel.classList.remove('mon-panel-hidden');
                if (footerPanel) footerPanel.classList.add('mon-panel-hidden');
                if (tabPreview)  tabPreview.classList.add('mon-tab-active');
                if (tabFooter)   tabFooter.classList.remove('mon-tab-active');
            } else {
                if (footerPanel) footerPanel.classList.remove('mon-panel-hidden');
                if (pdfPanel)    pdfPanel.classList.add('mon-panel-hidden');
                if (tabFooter)   tabFooter.classList.add('mon-tab-active');
                if (tabPreview)  tabPreview.classList.remove('mon-tab-active');
            }
        }

        // On resize: if screen becomes desktop-size, ensure both panels are visible
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 640) {
                const pdfPanel    = document.getElementById('mob-pdf-panel');
                const footerPanel = document.getElementById('mob-footer-panel');
                if (pdfPanel)    pdfPanel.classList.remove('mon-panel-hidden');
                if (footerPanel) footerPanel.classList.remove('mon-panel-hidden');
            }
        });

        function updatePdfLoadingProgress(percent, text) {
            const loading = document.getElementById('pdf-preview-loading');
            const textEl = document.getElementById('pdf-loading-text');
            const barEl = document.getElementById('pdf-loading-bar');
            if (loading) loading.classList.remove('hidden');
            if (textEl) textEl.textContent = text;
            if (barEl) barEl.style.width = percent + '%';
        }

        function getFooterFormData() {
            return {
                lokasi: document.getElementById('footer-lokasi').value || '',
                tanggal: document.getElementById('footer-tanggal').value || '',
                disusun_oleh: document.getElementById('footer-disusun-oleh').value || '',
                jabatan_penyusun: document.getElementById('footer-jabatan-penyusun').value || '',
                mengetahui: document.getElementById('footer-mengetahui').value || '',
                jabatan: document.getElementById('footer-jabatan').value || ''
            };
        }

        function setFooterFormData(data) {
            if (!data) return;
            if (data.lokasi !== undefined) document.getElementById('footer-lokasi').value = data.lokasi;
            if (data.tanggal !== undefined) document.getElementById('footer-tanggal').value = data.tanggal;
            if (data.disusun_oleh !== undefined) document.getElementById('footer-disusun-oleh').value = data.disusun_oleh;
            if (data.jabatan_penyusun !== undefined) document.getElementById('footer-jabatan-penyusun').value = data.jabatan_penyusun;
            if (data.mengetahui !== undefined) document.getElementById('footer-mengetahui').value = data.mengetahui;
            if (data.jabatan !== undefined) document.getElementById('footer-jabatan').value = data.jabatan;
        }

        function fetchPdfPreview() {
            const errorEl = document.getElementById('pdf-preview-error');
            if (errorEl) errorEl.classList.add('hidden');

            updatePdfLoadingProgress(20, "Menyiapkan Master Template Excel...");

            const payload = {
                semester: selectedSemester,
                footer: getFooterFormData()
            };

            setTimeout(() => {
                updatePdfLoadingProgress(60, "Mengonversi ke PDF...");
            }, 300);

            fetch('{{ route("monitoring.pdf.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.pdf_url) {
                    currentPdfTempId = data.temp_id;
                    if (data.footer) {
                        defaultFooterData = data.footer;
                        if (!document.getElementById('footer-lokasi').value) {
                            setFooterFormData(data.footer);
                        }
                    }

                    renderPdfIframe(data.pdf_url);
                } else {
                    throw new Error(data.message || 'Gagal membuat Preview PDF.');
                }
            })
            .catch(err => {
                console.error('[PDF PREVIEW ERROR]', err);
                const loading = document.getElementById('pdf-preview-loading');
                if (loading) loading.classList.add('hidden');
                if (errorEl) errorEl.classList.remove('hidden');
                const msgEl = document.getElementById('pdf-error-message');
                if (msgEl) msgEl.textContent = err.message || 'Gagal membuat Preview PDF. Silakan coba kembali.';
            });
        }

        function updatePdfPreview() {
            const errorEl = document.getElementById('pdf-preview-error');
            if (errorEl) errorEl.classList.add('hidden');

            const btn = document.getElementById('btn-update-preview');
            const btnText = document.getElementById('btn-update-preview-text');
            if (btn) btn.disabled = true;
            if (btnText) btnText.textContent = 'Menyimpan...';

            updatePdfLoadingProgress(40, "Memperbarui Cell Footer Excel...");

            const saveDefault = document.getElementById('footer-save-default').checked;

            const payload = {
                semester: selectedSemester,
                temp_id: currentPdfTempId || '',
                footer: getFooterFormData(),
                save_default: saveDefault
            };

            fetch('{{ route("monitoring.pdf.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.pdf_url) {
                    currentPdfTempId = data.temp_id;
                    renderPdfIframe(data.pdf_url + '?t=' + Date.now());
                    if (btnText) btnText.textContent = '✓ Perubahan Tersimpan!';
                    setTimeout(() => {
                        if (btnText) btnText.textContent = 'Simpan Perubahan';
                        if (btn) btn.disabled = false;
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Gagal memperbarui preview.');
                }
            })
            .catch(err => {
                console.error('[UPDATE PREVIEW ERROR]', err);
                if (btnText) btnText.textContent = 'Simpan Perubahan';
                if (btn) btn.disabled = false;

                const loading = document.getElementById('pdf-preview-loading');
                if (loading) loading.classList.add('hidden');
                if (errorEl) errorEl.classList.remove('hidden');
                const msgEl = document.getElementById('pdf-error-message');
                if (msgEl) msgEl.textContent = err.message || 'Gagal membuat Preview PDF. Silakan coba kembali.';
            });
        }

        function renderPdfIframe(url) {
            updatePdfLoadingProgress(100, "Preview Siap!");
            const iframe = document.getElementById('pdf-preview-iframe');
            const loading = document.getElementById('pdf-preview-loading');

            if (iframe) {
                const cleanUrl = url.split('#')[0];
                const zoomHash = `#zoom=${Math.round(currentPdfZoom * 100)}`;
                iframe.src = cleanUrl + zoomHash;

                iframe.onload = () => {
                    if (loading) loading.classList.add('hidden');
                };

                setTimeout(() => {
                    if (loading) loading.classList.add('hidden');
                }, 600);
            } else {
                if (loading) loading.classList.add('hidden');
            }
        }

        function setPdfZoom(scale) {
            currentPdfZoom = scale;
            const iframe = document.getElementById('pdf-preview-iframe');
            if (iframe && iframe.src) {
                const cleanUrl = iframe.src.split('#')[0];
                iframe.src = cleanUrl + `#zoom=${Math.round(currentPdfZoom * 100)}`;
            }
        }

        function zoomPdf(delta) {
            let newZoom = Math.min(2.0, Math.max(0.4, currentPdfZoom + delta));
            newZoom = Math.round(newZoom * 10) / 10;
            currentPdfZoom = newZoom;

            const select = document.getElementById('pdf-zoom-select');
            if (select) select.value = currentPdfZoom.toString();

            setPdfZoom(currentPdfZoom);
        }

        function resetPdfFooterToDefault() {
            if (defaultFooterData) {
                setFooterFormData(defaultFooterData);
                updatePdfPreview();
            }
        }

        function downloadFinalPdf() {
            const btn = document.getElementById('btn-generate-pdf');
            const btnText = document.getElementById('btn-generate-pdf-text');
            if (btn) btn.disabled = true;
            if (btnText) btnText.textContent = 'Mengunduh PDF...';

            const saveDefault = document.getElementById('footer-save-default').checked;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("monitoring.pdf.generate") }}';
            form.target = '_blank';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);

            const semInput = document.createElement('input');
            semInput.type = 'hidden';
            semInput.name = 'semester';
            semInput.value = selectedSemester;
            form.appendChild(semInput);

            const tempInput = document.createElement('input');
            tempInput.type = 'hidden';
            tempInput.name = 'temp_id';
            tempInput.value = currentPdfTempId || '';
            form.appendChild(tempInput);

            const saveDefInput = document.createElement('input');
            saveDefInput.type = 'hidden';
            saveDefInput.name = 'save_default';
            saveDefInput.value = saveDefault ? '1' : '0';
            form.appendChild(saveDefInput);

            const footerData = getFooterFormData();
            for (const [key, val] of Object.entries(footerData)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `footer[${key}]`;
                input.value = val;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            setTimeout(() => {
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = 'Generate PDF';
            }, 2000);
        }

        function downloadExcelReport() {
            const btn = document.getElementById('btn-export-excel');
            const btnText = document.getElementById('btn-export-excel-text');
            if (btn) btn.disabled = true;
            if (btnText) btnText.textContent = 'Mengunduh Excel...';

            const saveDefault = document.getElementById('footer-save-default')?.checked || false;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("monitoring.excel.export") }}';
            form.target = '_blank';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);

            const semInput = document.createElement('input');
            semInput.type = 'hidden';
            semInput.name = 'semester';
            semInput.value = selectedSemester;
            form.appendChild(semInput);

            const tempInput = document.createElement('input');
            tempInput.type = 'hidden';
            tempInput.name = 'temp_id';
            tempInput.value = currentPdfTempId || '';
            form.appendChild(tempInput);

            const saveDefInput = document.createElement('input');
            saveDefInput.type = 'hidden';
            saveDefInput.name = 'save_default';
            saveDefInput.value = saveDefault ? '1' : '0';
            form.appendChild(saveDefInput);

            const footerData = getFooterFormData();
            for (const [key, val] of Object.entries(footerData)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `footer[${key}]`;
                input.value = val;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            setTimeout(() => {
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = 'Unduh Excel (.xlsx)';
            }, 2000);
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function toggleBackToTopBtn() {
            const btn = document.getElementById('btn-back-to-top');
            if (!btn) return;
            if (window.scrollY > 200) {
                if (btn.style.display === 'none' || btn.style.display === '') {
                    btn.style.display = 'flex';
                    setTimeout(() => {
                        btn.style.opacity = '1';
                        btn.style.transform = 'translateY(0) scale(1)';
                    }, 10);
                }
            } else {
                if (btn.style.display !== 'none') {
                    btn.style.opacity = '0';
                    btn.style.transform = 'translateY(14px) scale(0.95)';
                    setTimeout(() => {
                        if (window.scrollY <= 200) {
                            btn.style.display = 'none';
                        }
                    }, 250);
                }
            }
        }

        window.addEventListener('scroll', toggleBackToTopBtn);

        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('btn-back-to-top');
            if (btn && btn.parentElement !== document.body) {
                document.body.appendChild(btn);
            }
            toggleBackToTopBtn();
            pollSyncStatus();
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(pollSyncStatus, 1000);
        });
    </script>

    {{-- ===== Back to Top Button (Clean PLN Blue - High Visibility) ===== --}}
    <button id="btn-back-to-top"
            type="button"
            onclick="scrollToTop()"
            class="w-14 h-14 rounded-full flex items-center justify-center cursor-pointer active:scale-95 group"
            style="position: fixed !important; bottom: 28px !important; right: 28px !important; z-index: 99999 !important; display: none; opacity: 0; transform: translateY(14px); background: linear-gradient(135deg, #0F6CBD 0%, #004B87 100%); border: 2px solid #ffffff; box-shadow: 0 10px 25px -3px rgba(15, 108, 189, 0.5), 0 4px 10px -2px rgba(0, 0, 0, 0.12); transition: opacity 0.25s ease, transform 0.25s ease, box-shadow 0.2s ease;"
            onmouseenter="this.style.transform='translateY(0) scale(1.08)'; this.style.boxShadow='0 14px 30px -3px rgba(15, 108, 189, 0.65), 0 6px 12px -2px rgba(0, 0, 0, 0.15)';"
            onmouseleave="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 10px 25px -3px rgba(15, 108, 189, 0.5), 0 4px 10px -2px rgba(0, 0, 0, 0.12)';"
            title="Kembali ke Atas Halaman">
        <svg class="w-7 h-7 text-white group-hover:-translate-y-0.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
        </svg>
    </button>

</x-layouts.app>