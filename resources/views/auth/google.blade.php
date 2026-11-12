<x-layouts.app title="Google Connection" :active-menu="'google-auth'">

    {{-- ===== Mobile-Only Responsive Styles ===== --}}
    <style>
        @media (max-width: 639px) {
            /* Connection status header: wrap badge below on mobile */
            .goog-status-header {
                flex-wrap: wrap !important;
                gap: 0.75rem !important;
            }
            /* Detail grid: stack label + value vertically */
            .goog-detail-row {
                grid-template-columns: 1fr !important;
                gap: 0.25rem !important;
            }
            .goog-detail-row span:last-child {
                word-break: break-all !important;
            }
            /* Action buttons: single column */
            .goog-action-grid {
                grid-template-columns: 1fr !important;
            }
            .goog-action-grid .sm\:col-span-2 {
                grid-column: span 1 !important;
                margin-top: 0 !important;
            }
        }
    </style>
    <div class="max-w-2xl mx-auto">

        {{-- ===== Page Header ===== --}}
        <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-800">Google Connection</h2>
            <p class="text-sm text-slate-400 mt-0.5">
                Hubungkan akun Google Anda dan pilih folder utama untuk menyimpan semua dokumen SIMDOK.
            </p>
        </div>

        @if ($driveError)
            <div class="mb-5">
                <x-alert type="warning" :message="$driveError" />
            </div>
        @endif

        {{-- ===== Connection Status Card ===== --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">

            {{-- Status Header --}}
            <div class="goog-status-header px-6 py-5 border-b border-slate-100 flex items-center justify-between gap-4 bg-slate-50/40">
                <div class="flex items-center gap-3.5">
                    @if ($isConnected)
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0 border border-green-100">
                            <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">Google Terhubung</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $email ?? 'Akun Google aktif' }}</p>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100">
                            <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">Belum Terhubung</p>
                            <p class="text-xs text-slate-400 mt-0.5">Hubungkan akun Google Anda untuk mengelola file.</p>
                        </div>
                    @endif
                </div>

                <div>
                    @if ($isConnected)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            Terhubung
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-50 text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                            Terputus
                        </span>
                    @endif
                </div>
            </div>

            {{-- Detail & Actions --}}
            <div class="px-6 py-6">

                @if ($isConnected)
                    @php
                        $rootFolderId = $settings['root_folder_id'] ?? null;
                        $rootFolderName = $settings['root_folder_name'] ?? null;
                        $connectedAt = $settings['connected_at'] ?? null;
                        $isRootFolderConfigured = !empty($rootFolderId);
                    @endphp

                    @if (!$isRootFolderConfigured)
                        {{-- ROOT FOLDER NOT SELECTED VIEW --}}
                        <div class="text-center py-8 px-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700 mb-1">Belum Memilih Root Folder</h3>
                            <p class="text-xs text-slate-400 max-w-sm mx-auto mb-5 leading-relaxed">
                                Silakan pilih folder utama Google Drive Anda agar seluruh fitur SIMDOK dapat digunakan.
                            </p>
                            <x-button id="btn-open-picker"
                                      variant="primary"
                                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />'
                                      onclick="openFolderPickerModal()">
                                Pilih Root Folder
                            </x-button>
                        </div>

                        {{-- Disconnect action button when not configured --}}
                        <div class="flex border-t border-slate-100 pt-5 mt-6">
                            <form method="POST" action="{{ route('auth.google.disconnect') }}" class="w-full"
                                  onsubmit="return confirm('Yakin ingin memutus koneksi Google?')">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-red-100 bg-red-50 text-red-600 text-sm font-semibold hover:bg-red-100 hover:text-red-700 transition-colors">
                                    Putus Koneksi Google
                                </button>
                            </form>
                        </div>

                    @else
                        {{-- DETAILS VIEW --}}
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                                <div class="w-1.5 h-3.5 bg-pln-blue rounded-full"></div>
                                <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider">Detail Konfigurasi</h3>
                            </div>

                            <div class="goog-detail-row grid grid-cols-3 gap-4 py-1 text-sm border-b border-slate-50 pb-2.5">
                                <span class="text-slate-400 font-medium font-sans">Connected Email</span>
                                <span class="col-span-2 text-slate-700 font-semibold break-all">{{ $email }}</span>
                            </div>

                            <div class="goog-detail-row grid grid-cols-3 gap-4 py-1 text-sm border-b border-slate-50 pb-2.5">
                                <span class="text-slate-400 font-medium font-sans">Root Folder Name</span>
                                <span class="col-span-2 text-pln-blue font-bold flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                                    </svg>
                                    {{ $rootFolderName }}
                                </span>
                            </div>

                            <div class="goog-detail-row grid grid-cols-3 gap-4 py-1 text-sm border-b border-slate-50 pb-2.5">
                                <span class="text-slate-400 font-medium font-sans">Root Folder ID</span>
                                <span class="col-span-2 text-slate-500 font-mono text-xs truncate" title="{{ $rootFolderId }}">
                                    {{ $rootFolderId }}
                                </span>
                            </div>

                            <div class="goog-detail-row grid grid-cols-3 gap-4 py-1 text-sm pb-2">
                                <span class="text-slate-400 font-medium font-sans">Connected At</span>
                                <span class="col-span-2 text-slate-600">{{ $connectedAt }}</span>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="goog-action-grid grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-slate-100 pt-5 mt-6">
                            {{-- Ganti Root Folder --}}
                            <x-button id="btn-change-root"
                                      variant="secondary"
                                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />'
                                      onclick="openFolderPickerModal()">
                                Ganti Root Folder
                            </x-button>

                            {{-- Refresh Root Folder --}}
                            <form method="POST" action="{{ route('auth.google.root.refresh') }}">
                                @csrf
                                <button id="btn-refresh-root"
                                        type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-all duration-150">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    Refresh Metadata
                                </button>
                            </form>

                            {{-- Disconnect Google --}}
                            <div class="sm:col-span-2 mt-2">
                                <button id="btn-disconnect-trigger"
                                        type="button"
                                        onclick="openModal('modal-disconnect')"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-red-100 bg-red-50 text-red-600 text-sm font-semibold hover:bg-red-100 transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    Disconnect Google Account
                                </button>
                            </div>
                        </div>
                    @endif

                @else
                    {{-- Why Connect Info --}}
                    <p class="text-sm font-bold text-slate-700 mb-3">Fitur yang akan aktif setelah terhubung:</p>
                    <ul class="space-y-2 mb-6">
                        @foreach ([
                            'Upload File ke Google Drive secara aman',
                            'Download File langsung dari Drive Explorer',
                            'Buat & kelola Folder di Google Drive secara dinamis',
                            'Monitoring status upload dokumen via Google Sheet resmi',
                        ] as $feature)
                            <li class="flex items-center gap-2 text-sm text-slate-500">
                                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>

                    @if (config('demo.enabled'))
                    {{-- DEMO: langkah simulasi milik SIMDOK (bukan tampilan/logo Google). Tombol memakai alur route connect → callback demo. --}}
                    <x-demo-note class="mb-4"><strong>SIMULASI — tidak terhubung ke Google.</strong> Tombol di bawah hanya menandai akun demo sebagai terhubung; tidak ada login atau akses ke Google yang sebenarnya.</x-demo-note>
                    <a id="btn-connect-google"
                       href="{{ route('auth.google.connect') }}"
                       class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl bg-pln-blue text-white text-sm font-semibold hover:bg-blue-700 transition-colors duration-150 shadow-sm shadow-blue-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                        Hubungkan akun demo
                    </a>
                    @else
                    {{-- Connect Button --}}
                    <a id="btn-connect-google"
                       href="{{ route('auth.google.connect') }}"
                       class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl bg-pln-blue text-white text-sm font-semibold hover:bg-blue-700 transition-colors duration-150 shadow-sm shadow-blue-200">
                        {{-- Google Icon --}}
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#ffffff" opacity="0.9"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#ffffff" opacity="0.9"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#ffffff" opacity="0.9"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#ffffff" opacity="0.9"/>
                        </svg>
                        Hubungkan dengan Google
                    </a>
                    @endif

                    @if (! config('demo.enabled'))
                    {{-- DEMO: teks persetujuan Google disembunyikan pada mode demo --}}
                    <p class="text-xs text-slate-400 text-center mt-3">
                        Persetujuan autentikasi hanya dilakukan sekali.
                    </p>
                    @endif
                @endif

            </div>
        </div>

        @if (config('demo.enabled'))
        {{-- DEMO: info penyimpanan token pada versi asli tidak berlaku di demo --}}
        <x-demo-note class="mt-4"><strong>SIMULASI — tidak terhubung ke Google.</strong> Pada versi demo tidak ada token atau berkas kredensial yang disimpan; status koneksi dan Root Folder hanya tersimpan sementara sebagai data demo.</x-demo-note>
        @else
        {{-- Info Box --}}
        <div class="mt-4 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
            <p class="text-xs text-blue-800 font-bold mb-1">ℹ️ Informasi Keamanan & Penyimpanan</p>
            <p class="text-xs text-blue-600 leading-relaxed">
                Aplikasi menyimpan token Google Anda di <code class="bg-blue-100/60 px-1.5 py-0.5 rounded text-xs font-mono text-blue-800">storage/app/google/token.json</code> dan detail konfigurasi folder utama di <code class="bg-blue-100/60 px-1.5 py-0.5 rounded text-xs font-mono text-blue-800">settings.json</code>. Tidak ada kredensial yang di-commit ke Git.
            </p>
        </div>
        @endif

    </div>

    {{-- MODAL: GOOGLE DRIVE FOLDER PICKER --}}
    @if ($isConnected)
        <x-modal id="modal-folder-picker" title="Pilih Root Folder" size="lg">
            <div class="flex flex-col h-[460px] max-h-[60vh]">
                
                {{-- Search & Info --}}
                <div class="mb-4 flex-shrink-0">
                    <p class="text-xs text-slate-400 mb-2">Cari dan pilih salah satu folder Google Drive Anda sebagai folder utama (Root Folder).</p>
                    <div class="relative">
                        <input type="text"
                               id="picker-search-input"
                               placeholder="Cari nama folder..."
                               class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-colors">
                        <div class="absolute left-3.5 top-2.5 text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.604 10.604z" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Split Content Area --}}
                <div class="flex-1 flex flex-col md:flex-row gap-4 overflow-hidden min-h-0">
                    
                    {{-- Left side: Folder List --}}
                    <div class="flex-1 flex flex-col border border-slate-100 rounded-xl bg-slate-50/50 overflow-hidden">
                        <div class="overflow-y-auto flex-1 p-2 space-y-1" id="picker-folder-list">
                            {{-- JS populated list --}}
                            <div class="text-center py-12 text-slate-400 text-sm">
                                <svg class="w-8 h-8 animate-spin text-pln-blue mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Memuat daftar folder...
                            </div>
                        </div>
                    </div>

                    {{-- Right side: Folder Details --}}
                    <div class="w-full md:w-64 border border-slate-100 rounded-xl p-4 bg-white flex-shrink-0 flex flex-col justify-between overflow-y-auto">
                        <div id="picker-details-container" class="text-slate-400 text-center py-12 text-xs flex-1 flex flex-col justify-center">
                            <svg class="w-10 h-10 text-slate-200 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            Pilih folder di sebelah kiri untuk melihat detail.
                        </div>

                        {{-- Hidden Form for Use Folder --}}
                        <form method="POST" action="{{ route('auth.google.root.save') }}" id="form-use-folder" class="hidden">
                            @csrf
                            <input type="hidden" name="root_folder_id" id="selected-folder-id">
                        </form>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-100 flex-shrink-0">
                    <x-button variant="secondary" type="button" onclick="closeFolderPickerModal()">Batal</x-button>
                </div>

            </div>
        </x-modal>

        <script>
            let currentSearchTimeout = null;
            let selectedFolderData = null;

            function openFolderPickerModal() {
                openModal('modal-folder-picker');
                loadFolders('');
            }

            function closeFolderPickerModal() {
                closeModal('modal-folder-picker');
            }

            // Input listener with simple debounce
            document.getElementById('picker-search-input').addEventListener('input', function(e) {
                const keyword = e.target.value;
                clearTimeout(currentSearchTimeout);

                currentSearchTimeout = setTimeout(() => {
                    loadFolders(keyword);
                }, 300);
            });

            // Fetch folders from AJAX
            function loadFolders(keyword = '') {
                const listContainer = document.getElementById('picker-folder-list');
                listContainer.innerHTML = `
                    <div class="text-center py-12 text-slate-400 text-sm">
                        <svg class="w-6 h-6 animate-spin text-pln-blue mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Mencari folder...
                    </div>
                `;

                fetch(`{{ route('auth.google.folders') }}?q=${encodeURIComponent(keyword)}`)
                    .then(response => response.json())
                    .then(folders => {
                        if (folders.error) {
                            listContainer.innerHTML = `<div class="text-center py-12 text-red-500 text-xs font-semibold">${folders.error}</div>`;
                            return;
                        }

                        if (folders.length === 0) {
                            listContainer.innerHTML = `<div class="text-center py-12 text-slate-400 text-xs">Tidak ada folder ditemukan.</div>`;
                            return;
                        }

                        listContainer.innerHTML = '';
                        folders.forEach(folder => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-white border border-transparent hover:border-slate-100 hover:shadow-sm text-left transition-all duration-100 picker-item';
                            btn.dataset.id = folder.id;
                            btn.innerHTML = `
                                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-500 flex-shrink-0">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-slate-700 truncate flex-1">${folder.name}</span>
                            `;

                            btn.addEventListener('click', () => {
                                selectFolder(folder);
                                
                                // Reset active states
                                document.querySelectorAll('.picker-item').forEach(el => {
                                    el.classList.remove('bg-blue-50/50', 'border-pln-blue/30');
                                });
                                btn.classList.add('bg-blue-50/50', 'border-pln-blue/30');
                            });

                            listContainer.appendChild(btn);
                        });
                    })
                    .catch(err => {
                        listContainer.innerHTML = `<div class="text-center py-12 text-red-500 text-xs font-semibold">Gagal menghubungi server.</div>`;
                    });
            }

            // Select a folder and show metadata details
            function selectFolder(folder) {
                selectedFolderData = folder;
                
                const detailsContainer = document.getElementById('picker-details-container');

                // Remove flex centering styling for details view
                detailsContainer.classList.remove('text-center', 'py-12', 'justify-center');

                // Format Created At
                let createdDateStr = '—';
                if (folder.createdTime) {
                    try {
                        const date = new Date(folder.createdTime);
                        createdDateStr = date.toLocaleDateString('id-ID', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    } catch(e) {}
                }

                detailsContainer.innerHTML = `
                    <div class="text-left space-y-4 h-full flex flex-col justify-between">
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase text-slate-400 tracking-wider">Detail Folder</h4>
                            
                            <div class="space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Nama Folder</span>
                                <p class="text-sm font-bold text-slate-800 break-words leading-tight">${folder.name}</p>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Folder ID</span>
                                <p class="text-[11px] font-mono text-slate-500 break-all leading-none bg-slate-50 p-2 rounded-lg border border-slate-100">${folder.id}</p>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Pemilik (Owner)</span>
                                <p class="text-xs text-slate-600 font-medium">${folder.owner || '—'}</p>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Tanggal Dibuat</span>
                                <p class="text-xs text-slate-600 font-medium">${createdDateStr}</p>
                            </div>
                        </div>

                        <!-- Use Folder Button -->
                        <button type="button"
                                onclick="submitSelectedFolder('${folder.id}')"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-pln-blue hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all duration-150 mt-6">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Gunakan Folder Ini
                        </button>
                    </div>
                `;
            }

            // Submit form helper
            function submitSelectedFolder(folderId) {
                const selectedInput = document.getElementById('selected-folder-id');
                const formUse = document.getElementById('form-use-folder');
                
                selectedInput.value = folderId;
                formUse.submit();
            }
        </script>

        {{-- MODAL: CONFIRM DISCONNECT --}}
        <x-modal id="modal-disconnect" title="Konfirmasi Putus Koneksi" size="sm">
            <div class="text-center p-4">
                <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-2">Yakin Ingin Memutus Koneksi?</h3>
                <p class="text-sm text-slate-500 mb-6">
                    Seluruh fitur SIMDOK yang terintegrasi dengan Google Drive dan Monitoring akan <strong>berhenti berfungsi</strong> sampai Anda menghubungkannya kembali.
                </p>
                <div class="flex justify-center gap-3">
                    <x-button variant="secondary" type="button" onclick="closeModal('modal-disconnect')">Batal</x-button>
                    <form method="POST" action="{{ route('auth.google.disconnect') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold shadow-sm transition-colors duration-150">
                            Ya, Putus Koneksi
                        </button>
                    </form>
                </div>
            </div>
        </x-modal>
    @endif

</x-layouts.app>
